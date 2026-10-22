<?php
declare(strict_types=1);
namespace Samla\Core\Catalog;

use Samla\Core\Storage\FileService;
use Samla\PluginApi\{AccessDenied,AuthorizationChecker,AuthorizationRequest,CatalogEntryChange,CatalogTagChange,Decision,MutationConflict,MutationContext,MutationRunner,Scope};

final readonly class CatalogService
{
    public function __construct(private \PDO $pdo,private FileService $files,private AuthorizationChecker $authorization,private MutationRunner $mutations){}
    public function allowed(string $actor,string $space,string $action):bool
    {return $this->authorization->decide(new AuthorizationRequest($actor,$action,Scope::Space,$space))->decision===Decision::Permit;}
    private function guard(string $actor,string $space,string $action='catalog.read'):void
    {
        foreach(array_unique(['files.list','files.read','catalog.read',$action])as$required){if(!$this->allowed($actor,$space,$required)){throw new AccessDenied();}}
        $q=$this->pdo->prepare('SELECT 1 FROM storage_spaces WHERE id=?');$q->execute([$space]);if($q->fetchColumn()===false){throw new AccessDenied();}
    }
    public function tags(string $actor,string $space):array
    {
        $this->guard($actor,$space);$q=$this->pdo->prepare('SELECT id,name,revision FROM catalog_tags WHERE space_id=? ORDER BY name,id LIMIT 101');$q->execute([$space]);$rows=$q->fetchAll();
        if(count($rows)>100){throw new \LengthException('Tag catalog limit.');}return $rows;
    }
    public function details(string $actor,string $space,string $entry):array
    {
        $this->guard($actor,$space);$file=$this->files->details($actor,$space,$entry);if($file['kind']!=='file'){throw new \InvalidArgumentException();}
        $q=$this->pdo->prepare('SELECT revision,description FROM catalog_entries WHERE entry_id=?');$q->execute([$entry]);$catalog=$q->fetch()?:['revision'=>0,'description'=>''];
        $q=$this->pdo->prepare('SELECT tag_id FROM catalog_entry_tags WHERE entry_id=? ORDER BY tag_id LIMIT 51');$q->execute([$entry]);$ids=$q->fetchAll(\PDO::FETCH_COLUMN);
        if(count($ids)>50){throw new \LengthException('File tag limit.');}
        return ['entry'=>$file,'catalog'=>$catalog,'selected_tags'=>$ids,'tags'=>$this->tags($actor,$space),
            'can_write'=>$this->allowed($actor,$space,'catalog.write'),'can_manage_tags'=>$this->allowed($actor,$space,'catalog.tags.manage')];
    }
    public function save(string $actor,CatalogEntryChange $change):bool
    {
        $guard=function()use($actor,$change):void{
            $this->guard($actor,$change->spaceId,'catalog.write');$current=$this->details($actor,$change->spaceId,$change->entryId);
            if((int)$current['entry']['revision']!==$change->entryRevision||(int)$current['catalog']['revision']!==$change->catalogRevision){throw new MutationConflict();}
            $choices=array_column($current['tags'],'id');foreach($change->tagIds as$id){if(!in_array($id,$choices,true)){throw new \InvalidArgumentException('Unknown tag in this space.');}}
        };
        return $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'catalog.entry.update',Scope::Space,$change->spaceId,['entry'=>$change->entryId],catalogEntry:$change),$guard,
            function()use($actor,$change):bool{
                $current=$this->details($actor,$change->spaceId,$change->entryId);
                if($current['catalog']['description']===$change->description&&$current['selected_tags']===$change->tagIds){return false;}
                $q=$this->pdo->prepare('INSERT INTO catalog_entries(entry_id,revision,description) VALUES(?,?,?) ON DUPLICATE KEY UPDATE revision=?,description=?');
                $q->execute([$change->entryId,$change->catalogRevision+1,$change->description,$change->catalogRevision+1,$change->description]);
                $this->pdo->prepare('DELETE FROM catalog_entry_tags WHERE entry_id=?')->execute([$change->entryId]);
                $q=$this->pdo->prepare('INSERT INTO catalog_entry_tags(entry_id,tag_id) VALUES(?,?)');foreach($change->tagIds as$id){$q->execute([$change->entryId,$id]);}return true;
            });
    }
    public function changeTag(string $actor,CatalogTagChange $change):bool
    {
        $key=$change->name===null?null:hash('sha256',mb_strtolower($change->name,'UTF-8'));
        $guard=function()use($actor,$change,$key):void{
            $this->guard($actor,$change->spaceId,'catalog.tags.manage');$rows=$this->tags($actor,$change->spaceId);$current=null;
            foreach($rows as$row){if($row['id']===$change->tagId){$current=$row;}}
            if($change->operation==='create'){
                if($current!==null){throw new MutationConflict();}if(count($rows)>=100){throw new \LengthException('Tag catalog full.');}
            }elseif($current===null||(int)$current['revision']!==$change->expectedRevision){throw new MutationConflict();}
            if($key!==null){$q=$this->pdo->prepare('SELECT id FROM catalog_tags WHERE space_id=? AND name_key=? AND id<>?');$q->execute([$change->spaceId,$key,$change->tagId]);if($q->fetchColumn()!==false){throw new MutationConflict();}}
            if($change->operation==='delete'){
                $q=$this->pdo->prepare('SELECT 1 FROM catalog_entry_tags WHERE tag_id=? LIMIT 1');$q->execute([$change->tagId]);if($q->fetchColumn()!==false){throw new MutationConflict();}
            }
        };
        return $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'catalog.tag.'.$change->operation,Scope::Space,$change->spaceId,['tag'=>$change->tagId],catalogTag:$change),$guard,
            function()use($change,$key):bool{
                if($change->operation==='create'){$q=$this->pdo->prepare('INSERT INTO catalog_tags(id,space_id,name,name_key) VALUES(?,?,?,?)');$q->execute([$change->tagId,$change->spaceId,$change->name,$key]);return true;}
                if($change->operation==='delete'){$this->pdo->prepare('DELETE FROM catalog_tags WHERE id=?')->execute([$change->tagId]);return true;}
                $q=$this->pdo->prepare('SELECT name FROM catalog_tags WHERE id=?');$q->execute([$change->tagId]);if($q->fetchColumn()===$change->name){return false;}
                $q=$this->pdo->prepare('UPDATE catalog_tags SET name=?,name_key=?,revision=revision+1 WHERE id=?');$q->execute([$change->name,$key,$change->tagId]);return true;
            });
    }
}
