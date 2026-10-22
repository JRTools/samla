<?php
declare(strict_types=1);
namespace Samla\Plugins\Rbac;
use Samla\PluginApi\{IdentityReader,PermissionRegistry,PersonalSpaceAccess,PersonalSpaceChange,ResourceDirectory,Scope};

final readonly class PersonalSpaceAccessProvisioner implements PersonalSpaceAccess
{
    public const string ROLE='samla.personal.owner.v1';
    public const array PERMISSIONS=['files.list','files.read','files.create','files.update','files.delete','files.restore','files.purge','files.versions.read',
        'files.export','files.export.sensitive','media.previews.read','media.metadata.read','media.sensitive.read','media.process','catalog.read','catalog.write','catalog.tags.manage'];
    public function __construct(private PdoRoleRepository $roles,private IdentityReader $identity,private PermissionRegistry $permissions,private ResourceDirectory $resources){}
    public function provisionInsideTransaction(PersonalSpaceChange $change):void
    {
        if(!$this->identity->isAccessAllowed($change->ownerId)||!$this->resources->hasGroup($change->groupId)||!$this->resources->hasSpace($change->spaceId)
            ||!in_array($change->groupId,$this->identity->groupIds($change->ownerId),true)){throw new \LogicException('Owner setup prerequisites missing.');}
        $role=$this->roles->find(self::ROLE);
        if($role===null){$this->roles->saveInsideTransaction(new Role(self::ROLE,Scope::Space,self::PERMISSIONS),$this->permissions);}
        elseif($role->scope!==Scope::Space||array_diff($role->permissions,self::PERMISSIONS)!==[]||array_diff(self::PERMISSIONS,$role->permissions)!==[]){throw new \LogicException('Reserved owner role changed.');}
        $this->roles->assignInsideTransaction(new RoleAssignment($change->groupId,self::ROLE,Scope::Space,$change->spaceId));
    }
}
