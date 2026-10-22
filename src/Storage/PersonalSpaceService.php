<?php
declare(strict_types=1);
namespace Samla\Core\Storage;
use Samla\Core\Configuration\PersonalSpaceConfig;
use Samla\Core\Identity\{AdministrationAccessGuard,PdoIdentityRepository};
use Samla\PluginApi\{AccessDenied,AuthorizationChecker,MutationConflict,MutationContext,MutationRunner,PersonalSpaceAccess,PersonalSpaceChange,Scope};

/** Called only for the verified account at login; no actor/owner override from HTTP forms. */
final readonly class PersonalSpaceService
{
    public function __construct(private \PDO $pdo,private PdoIdentityRepository $identity,private MutationRunner $mutations,
        private PersonalSpaceAccess $access,private AuthorizationChecker $authorization,private PersonalSpaceConfig $config=new PersonalSpaceConfig()){}
    public function ensure(string $owner):?string
    {
        if(!$this->config->enabled){return null;}
        if(!$this->identity->isAccessAllowed($owner)){throw new AccessDenied();}
        $q=$this->pdo->prepare('SELECT space_id FROM personal_storage_spaces WHERE owner_id=?');$q->execute([$owner]);
        $existing=$q->fetchColumn();if($existing!==false){return $existing;}
        $q=$this->pdo->prepare('SELECT display_name FROM identity_users WHERE id=?');$q->execute([$owner]);$name=$q->fetchColumn();
        if(!is_string($name)){throw new AccessDenied();}
        // Login labels are untrusted text. Keep a bounded safe initial label; the stable account ID defines ownership.
        $name=preg_replace('/[\x00-\x1f\x7f]/u',' ',mb_scrub($name,'UTF-8'));
        $change=new PersonalSpaceChange($owner,'Persönlich: '.mb_substr($name,0,230,'UTF-8'),$this->config->quotaBytes);
        $continuity=new AdministrationAccessGuard($this->identity,$this->authorization);$protected=null;
        $guard=function()use($owner,$change,$continuity,&$protected):void{
            if(!$this->identity->isAccessAllowed($owner)){throw new AccessDenied();}
            $protected??=$continuity->snapshot();
            $q=$this->pdo->prepare('SELECT space_id,owner_group_id FROM personal_storage_spaces WHERE owner_id=?');$q->execute([$owner]);$row=$q->fetch();
            if($row!==false){if($row['space_id']!==$change->spaceId||$row['owner_group_id']!==$change->groupId){throw new MutationConflict();}return;}
            if(count($this->identity->groupIds($owner))>=1000){throw new \LengthException('Identity group capacity.');}
            foreach([['storage_spaces',$change->spaceId],['identity_groups',$change->groupId]]as[$table,$id]){
                $q=$this->pdo->prepare('SELECT 1 FROM '.$table.' WHERE id=?');$q->execute([$id]);if($q->fetchColumn()!==false){throw new MutationConflict();}
            }
        };
        $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$owner,'storage.space.personal.provision',Scope::Space,$change->spaceId,
            ['space'=>$change->spaceId,'owner'=>$owner,'group'=>$change->groupId],personalSpace:$change),$guard,function()use($owner,$change,$continuity,&$protected):bool{
                $q=$this->pdo->prepare('SELECT 1 FROM personal_storage_spaces WHERE owner_id=?');$q->execute([$owner]);if($q->fetchColumn()!==false){$continuity->assertPreserved($protected);return false;}
                $q=$this->pdo->prepare('INSERT INTO storage_spaces(id,name,quota_bytes) VALUES(?,?,?)');$q->execute([$change->spaceId,$change->name,$change->quotaBytes]);
                $this->identity->createGroup($change->groupId,$change->name);
                $this->identity->setManualMembershipInsideTransaction($owner,$change->groupId,true);
                $this->access->provisionInsideTransaction($change);
                $q=$this->pdo->prepare('INSERT INTO personal_storage_spaces(space_id,owner_id,owner_group_id,created_at) VALUES(?,?,?,?)');$q->execute([$change->spaceId,$owner,$change->groupId,time()]);$continuity->assertPreserved($protected);return true;
            });
        return $change->spaceId;
    }
}
