<?php
declare(strict_types=1);
use Samla\Core\Administration\{PdoMutationRunner,PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Catalog\CatalogPlugin;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Export\ExportPlugin;
use Samla\Core\Identity\{IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Media\MediaPlugin;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{PersonalSpaceService,StoragePlugin};
use Samla\Plugins\Rbac\{PdoRoleRepository,PersonalSpaceAccessProvisioner,RbacPlugin};
return static function(\PDO $pdo,AppConfig $config):PersonalSpaceService{
    $identity=new PdoIdentityRepository($pdo,$config->groupMaxAgeSeconds);$roles=new PdoRoleRepository($pdo);
    $runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new MediaPlugin(),new ExportPlugin(),new CatalogPlugin(),new RbacPlugin($roles,$identity)]);
    return new PersonalSpaceService($pdo,$identity,new PdoMutationRunner($pdo,$runtime),new PersonalSpaceAccessProvisioner($roles,$identity,$runtime,new PdoResourceDirectory($pdo)),new AuthorizationService($runtime,$identity),$config->personalSpaces);
};
