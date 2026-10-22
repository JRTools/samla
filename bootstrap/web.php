<?php
declare(strict_types=1);

use Samla\Core\Administration\{AdministrationQueryService, PdoMutationRunner, PdoResourceDirectory,AccessAdministrationDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Background\{ReceiptPlugin,WorkerAdministration,WorkerPlugin};
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Media\{MediaPlugin,MediaService};
use Samla\Core\Identity\{GroupDetailsService, GroupMembershipService, IdentityPlugin, PdoIdentityRepository, UserStatusService};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService, LocalContentStore, StoragePlugin,SpaceAdministration};
use Samla\Core\Web\{AdminController, FileController, PageRenderer,WorkerAdministrationController,MediaController,PermissionController,SpaceAdministrationController};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacGroupMembershipPolicy, RbacPlugin,ProfileAccessAdministration};

return static function (\PDO $pdo, AppConfig $config): AdminController {
    $identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds);
    $roles = new PdoRoleRepository($pdo);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($roles, $identity),new WorkerPlugin(),new ReceiptPlugin($pdo),new MediaPlugin()]);
    $auth = new AuthorizationService($runtime, $identity);
    $resources = new PdoResourceDirectory($pdo); $mutations = new PdoMutationRunner($pdo, $runtime);
    $access=new ProfileAccessAdministration($roles,$auth,$runtime,$resources,$mutations);
    $accessDirectory=new AccessAdministrationDirectory($pdo,$access);
    return new AdminController(new AdministrationQueryService($pdo, $auth), $auth,
        new UserStatusService($identity, $auth, $mutations), new GroupDetailsService($identity, $auth, $mutations),
        new GroupMembershipService($identity, $auth, new RbacGroupMembershipPolicy($roles, $identity, $auth, $runtime, $resources,$access), $resources, $mutations),
        new PageRenderer(), new FileController(new FileService($pdo, $auth, $mutations,
            new LocalContentStore($config->storageRoot, $config->maxUploadBytes))),new WorkerAdministrationController(new WorkerAdministration($pdo,$auth,$mutations,$runtime)),new MediaController(new MediaService($pdo,new FileService($pdo,$auth,$mutations,new LocalContentStore($config->storageRoot,$config->maxUploadBytes)),$auth,$mutations,$runtime)),new PermissionController($access,$accessDirectory),new SpaceAdministrationController(new SpaceAdministration($pdo,$access,$access,$mutations),$accessDirectory));
};
