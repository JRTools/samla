<?php
declare(strict_types=1);
namespace Samla\Plugins\Rbac;

use Samla\PluginApi\{AccessDenied,AccessProfile,AccessProfileAdministration,AccessProfileChange,AuthorizationChecker,AuthorizationRequest,Decision,MutationConflict,MutationContext,MutationRunner,PermissionRegistry,ResourceDirectory,Scope};

/** Online counterpart to the existing maintenance profile tools, restricted to the same fixed bundles.
 * Does not edit arbitrary roles, administrative foundations, or delegation ceilings. */
final readonly class ProfileAccessAdministration implements AccessProfileAdministration, \Samla\PluginApi\SpaceAccessCleanup
{
    public function __construct(private PdoRoleRepository $roles, private AuthorizationChecker $authorization,
        private PermissionRegistry $permissions, private ResourceDirectory $resources, private MutationRunner $mutations) {}

    public function canManage(string $actorId): bool
    {
        foreach (['identity.users.read','identity.groups.read','identity.groups.memberships.manage'] as $action) {
            if ($this->authorization->decide(new AuthorizationRequest($actorId,$action,Scope::Global))->decision !== Decision::Permit) { return false; }
        }
        return true;
    }
    public function state(string $actorId,string $groupId,?string $spaceId): array
    {
        $this->guard($actorId,$groupId,$spaceId);
        $result=[];
        foreach ($this->profiles() as $profile) {
            if (($profile->scope===Scope::Global)!==($spaceId===null)) { continue; }
            $result[]=['profile'=>$profile,...$this->observed($profile,$groupId,$spaceId)];
        }
        if($spaceId!==null){
            foreach($this->roles->contextAssignments($groupId,$spaceId)as$assignment){
                $profile=$this->legacyProfile($assignment->roleId,$spaceId);
                if($profile!==null){$result[]=['profile'=>$profile,...$this->observed($profile,$groupId,$spaceId)];}
            }
        }
        return $result;
    }
    public function change(string $actorId,string $profileId,string $groupId,?string $spaceId,bool $grant,string $expectedState): bool
    {
        if(!$this->canManage($actorId)){throw new AccessDenied();}
        $profile=$this->profiles()[$profileId]??$this->legacyProfile($profileId,$spaceId)??throw new \InvalidArgumentException('Unknown access profile.');
        $proposal=new AccessProfileChange($profile,$groupId,$spaceId,$grant,$expectedState);
        $guard=function()use($actorId,$profile,$groupId,$spaceId,$expectedState,$grant):void {
            $this->guard($actorId,$groupId,$spaceId);
            $state=$this->observed($profile,$groupId,$spaceId);
            if (!$state['available']||($grant&&str_starts_with($profile->id,'files.')&&!$state['assigned'])) { throw new AccessDenied(); }
            if (!hash_equals($state['fingerprint'],$expectedState)) { throw new MutationConflict(); }
        };
        return $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actorId,
            $grant?'rbac.profile.grant':'rbac.profile.revoke',$profile->scope,$spaceId,['group'=>$groupId,'role'=>$profileId],accessProfile:$proposal),
            $guard,function()use($profile,$groupId,$spaceId,$grant):bool {
                $assignment=new RoleAssignment($groupId,$profile->id,$profile->scope,$spaceId);
                if (!$grant) { return $this->roles->find($profile->id)!==null && $this->roles->revokeInsideTransaction($assignment); }
                if ($this->roles->find($profile->id)===null) {
                    $this->roles->saveInsideTransaction(new Role($profile->id,$profile->scope,$profile->permissions),$this->permissions);
                }
                return $this->roles->assignInsideTransaction($assignment);
            });
    }
    private function guard(string $actorId,string $groupId,?string $spaceId):void
    {
        if (!$this->canManage($actorId)) { throw new AccessDenied(); }
        foreach (array_filter([$groupId,$spaceId],static fn($id)=>$id!==null) as $id) {
            if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$id)) { throw new \InvalidArgumentException('Invalid target.'); }
        }
        if (!$this->resources->hasGroup($groupId) || ($spaceId!==null&&!$this->resources->hasSpace($spaceId))) { throw new AccessDenied(); }
    }
    private function observed(AccessProfile $profile,string $groupId,?string $spaceId):array
    {
        $role=$this->roles->find($profile->id);
        $valid=$role===null || ($role->scope===$profile->scope && count(array_diff($profile->permissions,$role->permissions))===0 && count(array_diff($role->permissions,$profile->permissions))===0);
        foreach ($profile->permissions as $action) {
            $permission=$this->permissions->find($action);
            $valid=$valid&&$permission!==null&&in_array($profile->scope,$permission->scopes,true);
        }
        $assigned=$role!==null&&$this->roles->isAssigned(new RoleAssignment($groupId,$profile->id,$profile->scope,$spaceId));
        $rolePermissions=$role?->permissions??[];sort($rolePermissions,SORT_STRING);
        $fingerprint=hash('sha256',json_encode([$profile->id,$groupId,$spaceId,$assigned,$role?->scope->value,$rolePermissions,$valid],JSON_THROW_ON_ERROR));
        return ['assigned'=>$assigned,'available'=>$valid,'fingerprint'=>$fingerprint];
    }
    /** Global directory administrators may manage membership only for the fixed, non-administrative profiles.
     * Unknown/custom and administration-bearing roles still require the existing delegation policy. */
    public function allowsProfileMembership(string $actorId,string $groupId):bool
    {
        if(!$this->canManage($actorId)||!$this->resources->hasGroup($groupId)){return false;}
        $catalog=$this->profiles();
        foreach($this->roles->assignmentsFor([$groupId])as$assignment){
            $profile=$catalog[$assignment->roleId]??$this->legacyProfile($assignment->roleId,$assignment->spaceId);
            if($profile===null||$profile->scope!==$assignment->scope
                ||($assignment->spaceId!==null&&!$this->resources->hasSpace($assignment->spaceId))
                ||!$this->observed($profile,$groupId,$assignment->spaceId)['available']){return false;}
        }
        return true;
    }
    public function removeSpaceInsideTransaction(string $actorId,string $spaceId):void
    {
        if(!$this->canManage($actorId)||!$this->resources->hasSpace($spaceId)){throw new AccessDenied();}
        $this->roles->removeSpaceInsideTransaction($spaceId);
    }
    private function legacyProfile(string $roleId,?string $space):?AccessProfile
    {
        if($space===null||!preg_match('/^files\.[a-f0-9]{32}$/D',$roleId)){return null;}
        $role=$this->roles->find($roleId);$permissions=['files.create','files.list','files.read'];
        if($role===null||$role->scope!==Scope::Space||array_diff($role->permissions,$permissions)||array_diff($permissions,$role->permissions)){return null;}
        return new AccessProfile($roleId,'Hochladen (bisherige Zuweisung)','Bestehende Zuweisung aus der früheren Bereichsanlage: lesen und hochladen. Sie kann hier entzogen werden; neue Zuweisungen verwenden die Standardprofile.',Scope::Space,$permissions);
    }
    private function profiles():array
    {
        $profiles=[];
        foreach ([
            ['samla.files.read','Lesen','Dateien und Ordner anzeigen und Originale herunterladen.',Scope::Space,['files.list','files.read']],
            ['samla.files.contribute','Hochladen','Lesen und neue Dateien oder Ordner anlegen.',Scope::Space,['files.list','files.read','files.create']],
            ['samla.files.manage','Dateien verwalten','Lesen, hochladen, ändern, in den Papierkorb legen und wiederherstellen. Endgültiges Löschen und Historienlesen sind separat.',Scope::Space,['files.list','files.read','files.create','files.update','files.delete','files.restore']],
            ['samla.catalog.read','Katalog lesen','Beschreibungen und Tags sichtbarer Originaldateien lesen.',Scope::Space,['files.list','files.read','catalog.read']],
            ['samla.catalog.edit','Katalog bearbeiten','Manuelle Beschreibungen und Tagzuordnungen bearbeiten; Originale bleiben unverändert.',Scope::Space,['files.list','files.read','catalog.read','catalog.write']],
            ['samla.catalog.manage','Katalog und Tags verwalten','Zusätzlich Tags des Bereichs anlegen, umbenennen und unbenutzte Tags löschen.',Scope::Space,['files.list','files.read','catalog.read','catalog.write','catalog.tags.manage']],
            ['samla.files.export','Originale exportieren','Dateien auflisten, Originale lesen und private ZIP-Exporte beauftragen.',Scope::Space,['files.list','files.read','files.export']],
            ['samla.files.export-sensitive','Sensible Metadaten exportieren','Zusätzlich ausdrücklich exportierbare sensible Manifestfelder ausgeben. Feldleserecht und normaler Exportzugriff bleiben zusätzlich nötig.',Scope::Space,['files.export.sensitive']],
            ['samla.files.history','Versionen lesen','Frühere Dateiversionen lesen. Bereichs- und Dateizugriff werden zusätzlich geprüft.',Scope::Space,['files.versions.read']],
            ['samla.files.purge','Endgültig löschen','Papierkorbinhalte endgültig löschen. Die jeweilige Löschung erfordert weiterhin eine gesonderte Bestätigung.',Scope::Space,['files.purge']],
            ['samla.files.media','Medien verwenden','Vorschauen und allgemeine Metadaten anzeigen sowie Medienanalyse beauftragen. Bereichsliste ist zusätzlich nötig.',Scope::Space,['media.previews.read','media.metadata.read','media.process']],
            ['samla.files.media-sensitive','Sensible Metadaten','Allgemeine und besonders geschützte Metadaten lesen, darunter vorhandene Prompts. Originaldownloads können weitere eingebettete Daten enthalten.',Scope::Space,['media.metadata.read','media.sensitive.read']],
            ['samla.worker.read','Hintergrundaufgaben ansehen','Globalen Zustellstatus der Hintergrundaufgaben ansehen.',Scope::Global,['operations.worker.read']],
            ['samla.worker.manage','Hintergrundaufgaben verwalten','Globalen Zustellstatus ansehen und endgültig fehlgeschlagene Zustellungen erneut vormerken.',Scope::Global,['operations.worker.read','operations.worker.retry']],
        ] as $definition) {
            $profile=new AccessProfile(...$definition);$profiles[$profile->id]=$profile;
        }
        return $profiles;
    }
}
