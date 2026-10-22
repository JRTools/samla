<?php
declare(strict_types=1);
namespace Samla\Core\Export;
use Samla\Core\Configuration\ExportConfig;
use Samla\Core\Database\Transaction;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService,StorageUnavailable};
use Samla\PluginApi\{AccessDenied,AuthorizationChecker,AuthorizationRequest,Decision,ExportChange,ExportSource,IdentityReader,MutationConflict,MutationContext,MutationRunner,OutboxEvent,Scope};

final readonly class ExportService
{
    public function __construct(private \PDO $pdo,private FileService $files,private AuthorizationChecker $authorization,
        private IdentityReader $identity,private MutationRunner $mutations,private PluginRuntime $runtime,
        private ZipArchiveStore $store,private ExportConfig $config=new ExportConfig()){}
    public function available():bool{return ZipArchiveStore::available();}
    public function canExport(string $actor,string $space):bool
    {
        foreach(['files.list','files.read','files.export']as$action){if(!$this->allowed($actor,$space,$action)){return false;}}return true;
    }
    private function allowed(string $actor,string $space,string $action):bool
    {return $this->authorization->decide(new AuthorizationRequest($actor,$action,Scope::Space,$space))->decision===Decision::Permit;}
    private function ownAccess(string $actor):void{if(!$this->identity->isAccessAllowed($actor)){throw new AccessDenied();}}
    /** @param array<string,int> $entryRevisions */
    public function request(string $actor,string $space,array $entryRevisions):string
    {
        $id=bin2hex(random_bytes(16));$proposal=new ExportChange($id,$space,'request',$entryRevisions);
        $this->ownAccess($actor);if(!$this->canExport($actor,$space)){throw new AccessDenied();}
        if(!$this->available()){throw new StorageUnavailable('Native ZIP capability unavailable.');}
        (new ExportCollector($this->pdo,$this->store))->collect($actor);
        $sources=$this->selection($actor,$space,$proposal->entryRevisions);$snapshot=$this->snapshot($sources);
        $total=array_sum(array_map(static fn(ExportSource $s):int=>$s->sizeBytes,$sources));
        $reserved=2*($total+ExportConfig::MANIFEST_MAX_BYTES+count($sources)*1024);
        $signature=ExportMetadata::signature($this->runtime);$time=time();
        $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'exports.request',Scope::Space,$space,['export'=>$id],exportChange:$proposal),
            function()use($actor,$space,$proposal,$snapshot,$total,$reserved):void{
                $this->ownAccess($actor);if(!$this->canExport($actor,$space)){throw new AccessDenied();}
                if($this->snapshot($this->selection($actor,$space,$proposal->entryRevisions))!==$snapshot){throw new MutationConflict();}
                if($total>$this->config->maxBytes||count($proposal->entryRevisions)>$this->config->maxFiles){throw new \LengthException('Export selection limit.');}
                $q=$this->pdo->prepare('SELECT COUNT(*) FROM export_jobs WHERE owner_id=?');$q->execute([$actor]);if((int)$q->fetchColumn()>=5){throw new \LengthException('Owner export queue capacity.');}
                if((int)$this->pdo->query('SELECT COUNT(*) FROM export_jobs')->fetchColumn()>=1000
                    ||(int)$this->pdo->query('SELECT COALESCE(SUM(reserved_bytes),0) FROM export_jobs')->fetchColumn()+$reserved>$this->config->reservedBytes){throw new \LengthException('Export storage capacity.');}
            },function()use($id,$actor,$space,$snapshot,$signature,$sources,$total,$reserved,$time):bool{
                $q=$this->pdo->prepare('INSERT INTO export_jobs(id,owner_id,space_id,snapshot_json,provider_signature,file_count,content_bytes,reserved_bytes,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $q->execute([$id,$actor,$space,$snapshot,$signature,count($sources),$total,$reserved,$time,$time+$this->config->lifetimeSeconds]);return true;
            });
        return $id;
    }
    private function snapshot(array $sources):string
    {
        $rows=array_map(static fn(ExportSource $s):array=>['entry_id'=>$s->entryId,'revision'=>$s->revision,'name'=>$s->name,'size'=>$s->sizeBytes,'sha256'=>$s->sha256],$sources);
        $json=json_encode($rows,JSON_THROW_ON_ERROR);if(strlen($json)>262144){throw new \LengthException('Export snapshot limit.');}return $json;
    }
    private function selection(string $actor,string $space,array $entries):array
    {
        if(!$this->canExport($actor,$space)){throw new AccessDenied();}$sources=[];
        foreach($entries as$id=>$revision){
            $row=$this->files->details($actor,$space,$id);
            if($row['kind']!=='file'||(int)$row['revision']!==$revision){throw new MutationConflict();}
            $sources[]=new ExportSource($actor,$space,$id,$revision,$row['name'],(int)$row['size_bytes'],$row['sha256']);
        }
        return $sources;
    }
    private function sources(array $job):array
    {
        $rows=json_decode($job['snapshot_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($rows)||!array_is_list($rows)||count($rows)<1||count($rows)>100){throw new \RuntimeException('Invalid persisted export.');}
        $sources=[];foreach($rows as$row){$sources[]=new ExportSource($job['owner_id'],$job['space_id'],$row['entry_id'],$row['revision'],$row['name'],$row['size'],$row['sha256']);}
        return $sources;
    }
    private function validate(array $job,array $required=[]):array
    {
        $this->ownAccess($job['owner_id']);
        if((int)$job['expires_at']<=time()||!hash_equals($job['provider_signature'],ExportMetadata::signature($this->runtime))){throw new MutationConflict();}
        foreach(array_unique(['files.list','files.read','files.export',...$required])as$action){
            if(!is_string($action)||!$this->allowed($job['owner_id'],$job['space_id'],$action)){throw new AccessDenied();}
        }
        $sources=$this->sources($job);$revisions=[];foreach($sources as$source){$revisions[$source->entryId]=$source->revision;}
        if($this->snapshot($this->selection($job['owner_id'],$job['space_id'],$revisions))!==$job['snapshot_json']){throw new MutationConflict();}
        return $sources;
    }
    private function job(string $id,?string $actor=null):array
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id)){throw new \InvalidArgumentException('Invalid export identifier.');}
        $q=$this->pdo->prepare('SELECT * FROM export_jobs WHERE id=?'.($actor===null?'':' AND owner_id=?'));$q->execute($actor===null?[$id]:[$id,$actor]);
        return $q->fetch()?:throw new AccessDenied();
    }
    public function listing(string $actor):array
    {
        $this->ownAccess($actor);$q=$this->pdo->prepare('SELECT id,status,revision,file_count,content_bytes,created_at,expires_at,error_code,space_id FROM export_jobs WHERE owner_id=? ORDER BY created_at DESC,id DESC LIMIT 50');$q->execute([$actor]);
        $rows=$q->fetchAll();foreach($rows as&$row){$row['space_name']=null;
            if($this->canExport($actor,$row['space_id'])){$s=$this->pdo->prepare('SELECT name FROM storage_spaces WHERE id=?');$s->execute([$row['space_id']]);$row['space_name']=$s->fetchColumn()?:null;}
            $row['expired']=(int)$row['expires_at']<=time();unset($row['space_id']);
        }unset($row);return $rows;
    }
    public function change(string $actor,string $id,int $expected,string $operation):void
    {
        if(!in_array($operation,['retry','remove'],true)){throw new \InvalidArgumentException();}
        $this->ownAccess($actor);$job=$this->job($id,$actor);$entries=[];
        if($operation==='retry'){foreach($this->sources($job)as$source){$entries[$source->entryId]=$source->revision;}}
        $proposal=new ExportChange($id,$job['space_id'],$operation,$entries,$expected);
        $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'exports.'.$operation,Scope::Space,$job['space_id'],['export'=>$id],exportChange:$proposal),
            function()use($actor,$id,$expected,$operation):void{
                $this->ownAccess($actor);$current=$this->job($id,$actor);
                if((int)$current['revision']!==$expected){throw new MutationConflict();}
                if($operation==='retry'){
                    if($current['status']!=='failed'){throw new MutationConflict();}$this->validate($current);
                    if(!$this->available()){throw new StorageUnavailable('Native ZIP capability unavailable.');}
                }
            },function()use($id,$operation):bool{
                $q=$this->pdo->prepare('UPDATE export_jobs SET status=?,error_code=NULL,revision=revision+1 WHERE id=?');$q->execute([$operation==='remove'?'cancelled':'pending',$id]);return true;
            });
        if($operation==='remove'){(new ExportCollector($this->pdo,$this->store))->collect($actor);}
    }
    /** Trusted worker callback; current owner authorization is re-evaluated, not frozen by the job. */
    public function handle(OutboxEvent $event):void
    {
        (new ExportCollector($this->pdo,$this->store))->collect();$id=$event->payload['targets']['export']??null;
        if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)){throw new \InvalidArgumentException('Invalid export event.');}
        $q=$this->pdo->prepare('SELECT 1 FROM export_jobs WHERE id=?');$q->execute([$id]);if($q->fetchColumn()===false){return;}
        $result=$this->store->locked($id,function()use($id):bool{
            $job=$this->job($id);if(!in_array($job['status'],['pending','processing'],true)){return true;}
            $claim=Transaction::run($this->pdo,function()use($id):array{
                if($this->pdo->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchColumn()===false){throw new \RuntimeException('Administration lock missing.');}
                $row=$this->job($id);if(!in_array($row['status'],['pending','processing'],true)){return [];}
                $this->pdo->prepare("UPDATE export_jobs SET status='processing',revision=revision+1 WHERE id=?")->execute([$id]);return $this->job($id);
            });
            if($claim===[]){return true;}
            try{
                $sources=$this->validate($claim);$metadata=new ExportMetadata($this->runtime,$this->authorization);
                $artifact=$this->store->build($id,$sources,function(ExportSource $source){
                    if(!$this->canExport($source->actorId,$source->spaceId)){throw new AccessDenied();}
                    return $this->files->download($source->actorId,$source->spaceId,$source->entryId,$source->revision)['stream'];
                },$metadata->collect(...));
                Transaction::run($this->pdo,function()use($id,$claim,$artifact):bool{
                    if($this->pdo->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchColumn()===false){throw new \RuntimeException('Administration lock missing.');}
                    $row=$this->job($id);if($row['status']!=='processing'||$row['revision']!==$claim['revision']){throw new MutationConflict();}
                    $this->validate($row,$artifact['required']);
                    $q=$this->pdo->prepare("UPDATE export_jobs SET status='ready',revision=revision+1,archive_size=?,archive_sha256=?,required_json=?,error_code=NULL WHERE id=?");
                    $q->execute([$artifact['size'],$artifact['sha256'],json_encode($artifact['required'],JSON_THROW_ON_ERROR),$id]);$this->completed($row,'ready');return true;
                });
            }catch(\Throwable $error){
                try{$this->store->discard($id);}catch(\Throwable){}
                $code=$error instanceof AccessDenied?'AccessChanged':($error instanceof MutationConflict||$error instanceof \InvalidArgumentException?'SourceChanged':($error instanceof \LengthException||$error instanceof ExportBudgetExceeded?'Limit':(!$this->available()?'ZipUnavailable':'Storage')));
                Transaction::run($this->pdo,function()use($id,$claim,$code):bool{
                    if($this->pdo->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchColumn()===false){throw new \RuntimeException('Administration lock missing.');}
                    $q=$this->pdo->prepare("UPDATE export_jobs SET status='failed',revision=revision+1,error_code=? WHERE id=? AND status='processing' AND revision=?");$q->execute([$code,$id,$claim['revision']]);
                    if($q->rowCount()===1){$this->completed($claim,'failed',$code);}return true;
                });
            }
            return true;
        });
        if($result===null){throw new \RuntimeException('Export artifact busy.');}
    }
    private function completed(array $job,string $status,?string $error=null):void
    {
        $event=bin2hex(random_bytes(16));$type='exports.package.'.$status;
        $payload=['id'=>$event,'actor_id'=>$job['owner_id'],'operation'=>$type,'scope'=>'space','space_id'=>$job['space_id'],'targets'=>['export'=>$job['id']],'error_code'=>$error];
        $q=$this->pdo->prepare('INSERT INTO outbox_events(id,event_type,payload,occurred_at) VALUES(?,?,?,?)');$q->execute([$event,$type,json_encode($payload,JSON_THROW_ON_ERROR),time()]);
    }
    public function download(string $actor,string $id):array
    {
        $this->ownAccess($actor);$job=$this->job($id,$actor);if($job['status']!=='ready'){throw new MutationConflict();}
        $required=json_decode($job['required_json'],true,16,JSON_THROW_ON_ERROR);
        if(!is_array($required)||count($required)>128){throw new \RuntimeException('Invalid persisted export policy.');}
        $this->validate($job,$required);$stream=$this->store->open($id,(int)$job['archive_size']);
        try{
            $hash=hash_init('sha256');hash_update_stream($hash,$stream);
            if(!hash_equals($job['archive_sha256'],hash_final($hash))||!rewind($stream)){throw new StorageUnavailable('Export integrity mismatch.');}
            $current=$this->job($id,$actor);
            if($current['status']!=='ready'||$current['revision']!==$job['revision']){throw new MutationConflict();}
            $this->validate($current,$required);
            return ['stream'=>$stream,'size'=>(int)$job['archive_size'],'sha256'=>$job['archive_sha256']];
        }catch(\Throwable $error){fclose($stream);throw $error;}
    }
}
