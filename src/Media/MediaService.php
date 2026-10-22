<?php
declare(strict_types=1);
namespace Samla\Core\Media;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\FileService;
use Samla\PluginApi\{AccessDenied,AuthorizationChecker,AuthorizationRequest,Decision,MediaPreview,MediaRefreshChange,MutationConflict,MutationContext,MutationRunner,Scope};

final readonly class MediaService
{
    public function __construct(private \PDO $pdo,private FileService $files,private AuthorizationChecker $authorization,private MutationRunner $mutations,private PluginRuntime $runtime){}
    private function allowed(string $actor,string $action,string $space):bool
    {return $this->authorization->decide(new AuthorizationRequest($actor,$action,Scope::Space,$space))->decision===Decision::Permit;}
    private function requireAccess(string $actor,string $action,string $space):void
    {if(!$this->allowed($actor,$action,$space)){throw new AccessDenied();}}
    private function capabilities(string $actor,string $space):array
    {
        $caps=[];foreach(['previews.read','metadata.read','process']as$action){$caps[$action]=$this->allowed($actor,'media.'.$action,$space);}
        if(!in_array(true,$caps,true)){throw new AccessDenied();}return $caps;
    }
    public function spaces(string $actor,?string $after=null):array
    {
        $page=$this->files->spaces($actor,$after);$page['rows']=array_values(array_filter($page['rows'],function(array $row)use($actor):bool{
            try{$this->capabilities($actor,$row['id']);return true;}catch(AccessDenied){return false;}
        }));return $page;
    }
    public function gallery(string $actor,string $space,string $parent='',?string $after=null):array
    {
        $caps=$this->capabilities($actor,$space);$page=$this->files->listing($actor,$space,$parent,$after);$ids=[];
        foreach($page['rows']as$row){if($row['kind']==='file'){$ids[]=$this->files->details($actor,$space,$row['id']);}}
        $assets=[];
        if($ids!==[]){$blobs=array_column($ids,'blob_id');$q=$this->pdo->prepare('SELECT blob_id,source_sha256,source_size,status,preview_bytes FROM media_assets WHERE signature=? AND blob_id IN ('.implode(',',array_fill(0,count($blobs),'?')).')');$q->execute([MediaPipeline::signature($this->runtime),...$blobs]);$assets=array_column($q->fetchAll(),null,'blob_id');}
        $sources=array_column($ids,null,'id');
        foreach($page['rows']as&$row){$source=$sources[$row['id']]??null;$asset=$source===null?null:($assets[$source['blob_id']]??null);
            if($source!==null&&$asset!==null&&($source['sha256']!==$asset['source_sha256']||(int)$source['size_bytes']!==(int)$asset['source_size'])){$asset=null;}
            $row['media_status']=$asset['status']??'pending';$row['has_preview']=$caps['previews.read']&&$asset!==null&&(int)$asset['preview_bytes']>0;
        }unset($row);$page['media_caps']=$caps;return $page;
    }
    public function details(string $actor,string $space,string $entry):array
    {
        $row=$this->files->details($actor,$space,$entry);if($row['kind']!=='file'){throw new \InvalidArgumentException();}$caps=$this->capabilities($actor,$space);
        $q=$this->pdo->prepare('SELECT source_sha256,source_size,status,processed_at,preview_bytes'.($caps['metadata.read']?',metadata_json':'').' FROM media_assets WHERE blob_id=? AND signature=?');
        $q->execute([$row['blob_id'],MediaPipeline::signature($this->runtime)]);$asset=$q->fetch();$values=[];
        if($asset!==false&&($asset['source_sha256']!==$row['sha256']||(int)$asset['source_size']!==(int)$row['size_bytes'])){$asset=false;}
        if($asset!==false&&$caps['metadata.read']){
            $fields=$this->runtime->mediaFields();foreach(json_decode($asset['metadata_json'],true,32,JSON_THROW_ON_ERROR)as$value){$field=$fields[$value['field']]??null;
                if($field!==null&&$this->allowed($actor,$field->readPermission,$space)){$values[]=$value+['label'=>$field->label];}
            }
        }
        return ['entry'=>$row,'space_id'=>$space,'media_caps'=>$caps,'media_status'=>$asset['status']??'pending','processed_at'=>$asset['processed_at']??null,
            'has_preview'=>$caps['previews.read']&&$asset!==false&&(int)$asset['preview_bytes']>0,'metadata'=>$values,'can_download'=>$this->files->allowed($actor,'files.read',$space)];
    }
    public function preview(string $actor,string $space,string $entry,int $revision):string
    {
        $this->requireAccess($actor,'media.previews.read',$space);$row=$this->files->details($actor,$space,$entry);
        if($row['kind']!=='file'||(int)$row['revision']!==$revision){throw new MutationConflict();}
        $q=$this->pdo->prepare('SELECT source_sha256,source_size,preview FROM media_assets WHERE blob_id=? AND signature=?');$q->execute([$row['blob_id'],MediaPipeline::signature($this->runtime)]);$asset=$q->fetch();
        if($asset===false||$asset['preview']===null){throw new MediaNotReady();}
        if($asset['source_sha256']!==$row['sha256']||(int)$asset['source_size']!==(int)$row['size_bytes']){throw new \RuntimeException('Invalid media projection.');}
        return (new MediaPreview($asset['preview']))->png;
    }
    public function refresh(string $actor,string $space,string $entry,int $revision):bool
    {
        $proposal=new MediaRefreshChange($entry,$revision);
        return $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'media.asset.refresh',Scope::Space,$space,['entry'=>$entry],mediaRefresh:$proposal),
            function()use($actor,$space,$entry,$revision):void{
                $this->requireAccess($actor,'media.process',$space);$row=$this->files->details($actor,$space,$entry);
                if($row['kind']!=='file'||(int)$row['revision']!==$revision){throw new MutationConflict();}
            },function()use($entry):bool{
                $q=$this->pdo->prepare('SELECT requested_at FROM media_refresh_requests WHERE entry_id=?');$q->execute([$entry]);$last=$q->fetchColumn();
                if($last!==false&&(int)$last>time()-60){return false;}
                $q=$this->pdo->prepare($last===false?'INSERT INTO media_refresh_requests(requested_at,entry_id) VALUES (?,?)':'UPDATE media_refresh_requests SET requested_at=? WHERE entry_id=?');$q->execute([time(),$entry]);return true;
            });
    }
}
