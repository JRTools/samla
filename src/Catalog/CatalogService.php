<?php
declare(strict_types=1);
namespace Samla\Core\Catalog;

use Samla\Core\Storage\FileService;
use Samla\PluginApi\{AccessDenied,AuthorizationChecker,AuthorizationRequest,CatalogBatchChange,CatalogEntryChange,CatalogTagChange,Decision,MutationConflict,MutationContext,MutationRunner,Scope};

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
    private function state(string $actor,string $space,string $entry):array
    {
        $this->guard($actor,$space);$file=$this->files->details($actor,$space,$entry);if($file['kind']!=='file'){throw new \InvalidArgumentException();}
        $q=$this->pdo->prepare('SELECT revision,description FROM catalog_entries WHERE entry_id=?');$q->execute([$entry]);$catalog=$q->fetch()?:['revision'=>0,'description'=>''];
        $q=$this->pdo->prepare('SELECT tag_id FROM catalog_entry_tags WHERE entry_id=? ORDER BY tag_id LIMIT 51');$q->execute([$entry]);$ids=$q->fetchAll(\PDO::FETCH_COLUMN);
        if(count($ids)>50){throw new \LengthException('File tag limit.');}
        return ['entry'=>$file,'catalog'=>$catalog,'selected_tags'=>$ids,
            'can_write'=>$this->allowed($actor,$space,'catalog.write'),'can_manage_tags'=>$this->allowed($actor,$space,'catalog.tags.manage')];
    }
    public function details(string $actor,string $space,string $entry):array
    {return $this->state($actor,$space,$entry)+['tags'=>$this->tags($actor,$space)];}
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
    public function search(string $actor,string $space,string $text='',?string $tag=null,string $extension='',?string $after=null):array
    {
        $this->guard($actor,$space);
        if(strlen($text)>512||!mb_check_encoding($text,'UTF-8')||mb_strlen($text,'UTF-8')>128||preg_match('/[\x00-\x1f\x7f]/u',$text)
            ||($tag!==null&&!preg_match('/^[a-f0-9]{32}$/D',$tag))||($after!==null&&!preg_match('/^[a-f0-9]{32}$/D',$after))
            ||($extension!==''&&!preg_match('/^[a-z0-9]{1,16}$/D',$extension))){throw new \InvalidArgumentException('Invalid catalog search.');}
        $tags=$this->tags($actor,$space);$names=array_column($tags,'name','id');if($tag!==null&&!array_key_exists($tag,$names)){throw new \InvalidArgumentException('Unknown search tag.');}
        $candidates=array_values(array_filter($this->files->visibleEntries($actor,$space),static fn(\Samla\PluginApi\FileCatalogEntry $e):bool=>
            $e->kind==='file'&&($after===null||strcmp($e->id,$after)>0)&&($extension===''||strtolower(pathinfo($e->name,PATHINFO_EXTENSION))===$extension)));
        $rows=[];
        foreach(array_chunk($candidates,100)as$chunk){
            $ids=array_map(static fn($e):string=>$e->id,$chunk);$marks=implode(',',array_fill(0,count($ids),'?'));
            $q=$this->pdo->prepare('SELECT entry_id,description FROM catalog_entries WHERE entry_id IN ('.$marks.')');$q->execute($ids);$descriptions=$q->fetchAll(\PDO::FETCH_KEY_PAIR);
            $q=$this->pdo->prepare('SELECT entry_id,tag_id FROM catalog_entry_tags WHERE entry_id IN ('.$marks.') ORDER BY entry_id,tag_id LIMIT 5001');$q->execute($ids);$links=$q->fetchAll();
            if(count($links)>5000){throw new \LengthException('Tag search reference limit.');}
            $byEntry=[];foreach($links as$link){if(isset($names[$link['tag_id']])){$byEntry[$link['entry_id']][$link['tag_id']]=$names[$link['tag_id']];}}
            foreach($chunk as$entry){
                $labels=$byEntry[$entry->id]??[];$description=$descriptions[$entry->id]??'';
                if(strlen($description)>\Samla\PluginApi\CatalogEntryChange::MAX_DESCRIPTION_BYTES||count($labels)>50){throw new \LengthException('Catalog search field limit.');}
                if(($tag!==null&&!isset($labels[$tag]))||($text!==''&&mb_stripos($entry->name."\n".$description."\n".implode("\n",$labels),$text,0,'UTF-8')===false)){continue;}
                try{$current=$this->files->details($actor,$space,$entry->id);}catch(\InvalidArgumentException){continue;}
                if((int)$current['revision']!==$entry->revision){continue;}
                sort($labels,SORT_STRING);$rows[]=['entry'=>$entry,'description'=>mb_substr($description,0,160,'UTF-8'),'tags'=>$labels];
                if(count($rows)===101){break 2;}
            }
        }
        $this->guard($actor,$space);$next=null;if(count($rows)>100){array_pop($rows);$next=$rows[99]['entry']->id;}
        return ['rows'=>$rows,'next'=>$next,'tags'=>$tags,'search_text'=>$text,'search_tag'=>$tag,'search_extension'=>$extension,'space_id'=>$space];
    }
    public function batchListing(string $actor,string $space,string $parent='',?string $after=null):array
    {
        $this->guard($actor,$space,'catalog.write');$data=$this->files->listing($actor,$space,$parent,$after);
        $ids=array_column(array_filter($data['rows'],static fn(array $row):bool=>$row['kind']==='file'),'id');$revisions=[];
        if($ids!==[]){$q=$this->pdo->prepare('SELECT entry_id,revision FROM catalog_entries WHERE entry_id IN ('.implode(',',array_fill(0,count($ids),'?')).')');$q->execute($ids);$revisions=$q->fetchAll(\PDO::FETCH_KEY_PAIR);}
        $rows=[];foreach($data['rows']as$row){if($row['kind']==='file'){$row['catalog_revision']=(int)($revisions[$row['id']]??0);}$rows[]=$row;}
        $data['rows']=$rows;$data['tags']=$this->tags($actor,$space);return $data;
    }
    /** @param array<string,array{int,int}> $entries client file/catalog revisions */
    public function batch(string $actor,string $space,string $tag,string $operation,array $entries):bool
    {
        $this->guard($actor,$space,'catalog.write');
        if(count($entries)<1||count($entries)>100||!in_array($operation,['add','remove'],true)){throw new \InvalidArgumentException();}
        $plan=[];foreach($entries as$id=>$state){
            if(!is_string($id)||!is_array($state)||array_keys($state)!==[0,1]||!is_int($state[0])||!is_int($state[1])){throw new \InvalidArgumentException();}
            $current=$this->state($actor,$space,$id);
            if((int)$current['entry']['revision']!==$state[0]||(int)$current['catalog']['revision']!==$state[1]){throw new MutationConflict();}
            $present=in_array($tag,$current['selected_tags'],true);$plan[$id]=[$state[0],$state[1],$present!==($operation==='add')];
        }
        $change=new CatalogBatchChange($space,$tag,$operation,$plan);
        $guard=function()use($actor,$change):void{
            $this->guard($actor,$change->spaceId,'catalog.write');$tags=array_column($this->tags($actor,$change->spaceId),'id');
            if(!in_array($change->tagId,$tags,true)){throw new \InvalidArgumentException('Unknown batch tag.');}
            foreach($change->entries as$id=>$state){
                $current=$this->state($actor,$change->spaceId,$id);$present=in_array($change->tagId,$current['selected_tags'],true);
                if((int)$current['entry']['revision']!==$state[0]||(int)$current['catalog']['revision']!==$state[1]
                    ||($present!==($change->operation==='add'))!==$state[2]){throw new MutationConflict();}
                if($change->operation==='add'&&!$present&&count($current['selected_tags'])>=50){throw new \LengthException('File tag limit.');}
            }
        };
        return $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'catalog.tags.batch.'.$operation,Scope::Space,$space,
            ['batch'=>$change->signature(),'tag'=>$tag],catalogBatch:$change),$guard,function()use($change):bool{
                foreach($change->entries as$id=>$state){
                    if(!$state[2]){continue;}
                    $q=$this->pdo->prepare("INSERT INTO catalog_entries(entry_id,revision,description) VALUES(?,1,'') ON DUPLICATE KEY UPDATE revision=revision+1");$q->execute([$id]);
                    $q=$this->pdo->prepare($change->operation==='add'?'INSERT INTO catalog_entry_tags(entry_id,tag_id) VALUES(?,?)':'DELETE FROM catalog_entry_tags WHERE entry_id=? AND tag_id=?');$q->execute([$id,$change->tagId]);
                }return $change->changedEntries()!==[];
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
