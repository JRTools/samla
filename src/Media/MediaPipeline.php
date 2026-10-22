<?php
declare(strict_types=1);
namespace Samla\Core\Media;
use Samla\Core\Database\Transaction;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{LocalContentStore,StorageWriteGuard};
use Samla\PluginApi\{MediaSource,OutboxEvent};

/** Technical worker service. It grants no user access and never changes source contents. */
final readonly class MediaPipeline
{
    public function __construct(private \PDO $pdo,private PluginRuntime $runtime,private LocalContentStore $store){}
    public static function signature(PluginRuntime $runtime):string
    {
        $fields=$runtime->mediaFields();ksort($fields,SORT_STRING);$providers=[];
        foreach($runtime->mediaProviders()as$p){$providers[]=[$p->id(),$p->version()];}
        return hash('sha256',json_encode(['format'=>1,'fields'=>$fields,'providers'=>$providers],JSON_THROW_ON_ERROR));
    }
    public function handle(OutboxEvent $event):void
    {
        $target=$event->payload['targets']['result']??$event->payload['targets']['entry']??null;
        if(!is_string($target)||!preg_match('/^[a-f0-9]{32}$/D',$target)){return;}
        $this->processEntry($target,$event->type==='media.asset.refresh.completed');
    }
    public function processEntry(string $entry,bool $force=false):void
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$entry)){throw new \InvalidArgumentException();}
        $q=$this->pdo->prepare("SELECT space_id,blob_id,size_bytes,sha256 FROM storage_entries WHERE id=? AND kind='file' AND trashed_at IS NULL");$q->execute([$entry]);$row=$q->fetch();if($row===false){return;}
        try{(new StorageWriteGuard($this->pdo))->assertVisible($row['space_id'],$entry);}catch(\InvalidArgumentException){return;}
        $signature=self::signature($this->runtime);$q=$this->pdo->prepare('SELECT providers_json,source_sha256,source_size FROM media_assets WHERE blob_id=? AND signature=?');$q->execute([$row['blob_id'],$signature]);$existing=$q->fetch();
        if(!$force&&$existing!==false&&$existing['source_sha256']===$row['sha256']&&(int)$existing['source_size']===(int)$row['size_bytes']&&!array_filter(json_decode($existing['providers_json'],true,16,JSON_THROW_ON_ERROR),static fn(array $r):bool=>$r['retryable'])){return;}
        $values=[];$reports=[];$preview=null;$retryable=false;
        if((int)$row['size_bytes']>MediaSource::MAX_BYTES){$reports[]=['id'=>'platform.media.source','version'=>'1','status'=>'limit','retryable'=>false];}
        else{
            $stream=$this->store->open($row['blob_id'],(int)$row['size_bytes']);try{$bytes=stream_get_contents($stream,MediaSource::MAX_BYTES+1);}finally{fclose($stream);}
            if($bytes===false||strlen($bytes)!==(int)$row['size_bytes']){throw new \RuntimeException('Media source read failed.');}
            $source=new MediaSource($bytes,$row['sha256']);$fields=$this->runtime->mediaFields();
            foreach($this->runtime->mediaProviders()as$provider){
                $report=['id'=>$provider->id(),'version'=>$provider->version(),'status'=>'error','retryable'=>false];
                try{
                    $result=$provider->extract($source);$candidate=[];
                    foreach($result->values as$value){
                        $field=$fields[$value->fieldId]??null;
                        if($field===null||($value->normalized!==null&&(($field->type==='integer'&&!is_int($value->normalized))||($field->type==='text'&&!is_string($value->normalized))))){throw new \LogicException('Unregistered or invalid provider field.');}
                        $candidate[]=['field'=>$value->fieldId,'source'=>$value->source,'raw'=>$value->raw,'normalized'=>$value->normalized,'status'=>$value->status,
                            'provider'=>$provider->id(),'provider_version'=>$provider->version()];
                    }
                    if(strlen(json_encode([...$values,...$candidate],JSON_THROW_ON_ERROR))>32768){$report['status']='limit';}
                    else{$values=[...$values,...$candidate];$preview??=$result->preview?->png;$report['status']=$result->status;$report['retryable']=$result->status==='unavailable';}
                }catch(\Throwable){$report['retryable']=true;}
                $retryable=$retryable||$report['retryable'];$reports[]=$report;
            }
        }
        if($reports===[]){$reports[]=['id'=>'platform.media.pipeline','version'=>'1','status'=>'unavailable','retryable'=>true];$retryable=true;}
        $statuses=array_column($reports,'status');
        $status=$preview!==null?((count(array_diff($statuses,['ready','unsupported']))===0)?'ready':'partial'):
            (in_array('unavailable',$statuses,true)?'unavailable':(in_array('error',$statuses,true)?'error':(in_array('limit',$statuses,true)?'limit':((in_array('partial',$statuses,true)||$values!==[])?'partial':'unsupported'))));
        Transaction::run($this->pdo,function()use($row,$signature,$values,$reports,$preview,$status):bool{
            if($this->pdo->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchColumn()===false){throw new \RuntimeException();}
            $q=$this->pdo->prepare('SELECT 1 FROM storage_entries WHERE blob_id=? UNION SELECT 1 FROM storage_versions WHERE blob_id=? LIMIT 1');$q->execute([$row['blob_id'],$row['blob_id']]);if($q->fetchColumn()===false){return false;}
            $q=$this->pdo->prepare('SELECT preview_bytes FROM media_assets WHERE blob_id=? AND signature=?');$q->execute([$row['blob_id'],$signature]);$old=$q->fetchColumn();
            if($old===false&&(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn()>=20000){throw new \LengthException('Media cache capacity reached.');}
            $used=(int)$this->pdo->query('SELECT COALESCE(SUM(preview_bytes),0) FROM media_assets')->fetchColumn();
            if($preview!==null&&$used-(int)($old===false?0:$old)+strlen($preview)>268435456){$preview=null;$status='limit';}
            $data=['blob_id'=>$row['blob_id'],'signature'=>$signature,'source_sha256'=>$row['sha256'],'source_size'=>(int)$row['size_bytes'],
                'status'=>$status,'metadata_json'=>json_encode($values,JSON_THROW_ON_ERROR),'providers_json'=>json_encode($reports,JSON_THROW_ON_ERROR),
                'preview'=>$preview,'preview_bytes'=>$preview===null?0:strlen($preview),'processed_at'=>time()];
            if($old===false){$sql='INSERT INTO media_assets('.implode(',',array_keys($data)).') VALUES (:'.implode(',:',array_keys($data)).')';}
            else{$assign=[];foreach(array_keys($data)as$key){if(!in_array($key,['blob_id','signature'],true)){$assign[]=$key.'=:'.$key;}}$sql='UPDATE media_assets SET '.implode(',',$assign).' WHERE blob_id=:blob_id AND signature=:signature';}
            $q=$this->pdo->prepare($sql);foreach($data as$key=>$value){$type=$value===null?\PDO::PARAM_NULL:($key==='preview'?\PDO::PARAM_LOB:(is_int($value)?\PDO::PARAM_INT:\PDO::PARAM_STR));$q->bindValue(':'.$key,$value,$type);}$q->execute();return true;
        });
        if($retryable){throw new \RuntimeException('Media provider unavailable or failed.');}
    }
}
