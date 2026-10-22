<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

use Samla\PluginApi\{AccessDenied,AccessProfileAdministration,MutationConflict,MutationContext,MutationRunner,SpaceAccessCleanup,StorageSpaceChange,Scope};

/** Authorized catalog operations; delete is intentionally limited to completely empty spaces. */
final readonly class SpaceAdministration
{
    public function __construct(private \PDO $pdo,private AccessProfileAdministration $access,
        private SpaceAccessCleanup $cleanup,private MutationRunner $mutations){}
    public function canManage(string $actor):bool{return $this->access->canManage($actor);}
    public function details(string $actor,string $space):array
    {
        $this->guard($actor);$row=$this->find($space);
        $row['used_bytes']=StorageUsage::bytes($this->pdo,$space);
        $q=$this->pdo->prepare('SELECT COUNT(*) FROM storage_entries WHERE space_id=?');$q->execute([$space]);
        $row['entry_count']=(int)$q->fetchColumn();$row['fingerprint']=$this->fingerprint($row);
        return $row;
    }
    public function create(string $actor,string $name,int $quota):string
    {
        $space='space.'.bin2hex(random_bytes(16));
        $this->change($actor,new StorageSpaceChange($space,'create',null,$name,$quota));return $space;
    }
    public function update(string $actor,string $space,string $name,int $quota,string $expected):bool
    {return $this->change($actor,new StorageSpaceChange($space,'update',$expected,$name,$quota));}
    public function delete(string $actor,string $space,string $expected):bool
    {return $this->change($actor,new StorageSpaceChange($space,'delete',$expected,null,null));}
    private function change(string $actor,StorageSpaceChange $proposal):bool
    {
        $space=$proposal->spaceId;
        $guard=function()use($actor,$proposal,$space):void{
            $this->guard($actor);
            if($proposal->operation==='create'){
                $q=$this->pdo->prepare('SELECT 1 FROM storage_spaces WHERE id=?');$q->execute([$space]);
                if($q->fetchColumn()!==false){throw new MutationConflict();}return;
            }
            $row=$this->find($space);
            if(!hash_equals($this->fingerprint($row),$proposal->expectedState)){throw new MutationConflict();}
            if($proposal->operation==='update'&&StorageUsage::bytes($this->pdo,$space)>$proposal->quotaBytes){throw new MutationConflict();}
            if($proposal->operation==='delete'){
                foreach(['storage_entries','storage_locks']as$table){
                    $q=$this->pdo->prepare('SELECT 1 FROM '.$table.' WHERE space_id=?'.($table==='storage_locks'?' AND expires_at>?':'').' LIMIT 1');$q->execute($table==='storage_locks'?[$space,time()]:[$space]);
                    if($q->fetchColumn()!==false){throw new MutationConflict();}
                }
            }
        };
        return $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'storage.space.catalog.'.$proposal->operation,
            Scope::Space,$space,['space'=>$space],storageSpace:$proposal),$guard,function()use($actor,$proposal,$space):bool{
                if($proposal->operation==='create'){
                    $q=$this->pdo->prepare('INSERT INTO storage_spaces(id,name,quota_bytes) VALUES(?,?,?)');$q->execute([$space,$proposal->name,$proposal->quotaBytes]);return true;
                }
                if($proposal->operation==='delete'){
                    $this->cleanup->removeSpaceInsideTransaction($actor,$space);
                    $q=$this->pdo->prepare('DELETE FROM storage_spaces WHERE id=?');$q->execute([$space]);return true;
                }
                $row=$this->find($space);
                if($row['name']===$proposal->name&&(int)$row['quota_bytes']===$proposal->quotaBytes){return false;}
                $q=$this->pdo->prepare('UPDATE storage_spaces SET name=?,quota_bytes=? WHERE id=?');$q->execute([$proposal->name,$proposal->quotaBytes,$space]);return true;
            });
    }
    private function guard(string $actor):void{if(!$this->canManage($actor)){throw new AccessDenied();}}
    private function find(string $space):array
    {
        if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$space)){throw new \InvalidArgumentException('Invalid space.');}
        $q=$this->pdo->prepare('SELECT id,name,quota_bytes FROM storage_spaces WHERE id=?');$q->execute([$space]);
        return $q->fetch()?:throw new MutationConflict();
    }
    private function fingerprint(array $row):string
    {return hash('sha256',json_encode([$row['id'],$row['name'],(int)$row['quota_bytes']],JSON_THROW_ON_ERROR));}
}
