<?php
declare(strict_types=1);

use Samla\Core\Administration\{AdministrationQueryService, PdoMutationRunner, PdoResourceDirectory,AccessAdministrationDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Export\{ExportPlugin,ExportService,ZipArchiveStore};
use Samla\Core\Background\{ReceiptPlugin,WorkerAdministration,WorkerPlugin};
use Samla\Core\Configuration\{AppConfig,OidcConfig};
use Samla\Core\Media\{MediaPlugin,MediaService};
use Samla\Core\Identity\{GroupMappingService, GroupDetailsService, GroupMembershipService, IdentityPlugin, PdoIdentityRepository, UserStatusService};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService, LocalContentStore, StoragePlugin,SpaceAdministration};
use Samla\Core\Web\{AdminController, FileController, PageRenderer,WorkerAdministrationController,MediaController,PermissionController,SpaceAdministrationController,ExportController,GroupMappingController};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacGroupMappingPolicy, RbacGroupMembershipPolicy, RbacPlugin,ProfileAccessAdministration};

return static function (\PDO $pdo, AppConfig $config, ?OidcConfig $oidc=null): AdminController {
    $identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds);
    $roles = new PdoRoleRepository($pdo);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($roles, $identity),new WorkerPlugin(),new ReceiptPlugin($pdo),new MediaPlugin(),new ExportPlugin()]);
    $auth = new AuthorizationService($runtime, $identity);
    $resources = new PdoResourceDirectory($pdo); $mutations = new PdoMutationRunner($pdo, $runtime);
    $access=new ProfileAccessAdministration($roles,$auth,$runtime,$resources,$mutations);
    $accessDirectory=new AccessAdministrationDirectory($pdo,$access);
    return new AdminController(new AdministrationQueryService($pdo, $auth), $auth,
        new UserStatusService($identity, $auth, $mutations), new GroupDetailsService($identity, $auth, $mutations),
        new GroupMembershipService($identity, $auth, new RbacGroupMembershipPolicy($roles, $identity, $auth, $runtime, $resources,$access), $resources, $mutations),
        new PageRenderer(), new FileController(new FileService($pdo, $auth, $mutations,
            new LocalContentStore($config->storageRoot, $config->maxUploadBytes))),new WorkerAdministrationController(new WorkerAdministration($pdo,$auth,$mutations,$runtime)),new MediaController(new MediaService($pdo,new FileService($pdo,$auth,$mutations,new LocalContentStore($config->storageRoot,$config->maxUploadBytes)),$auth,$mutations,$runtime)),new PermissionController($access,$accessDirectory),new SpaceAdministrationController(new SpaceAdministration($pdo,$access,$access,$mutations),$accessDirectory),new ExportController(new ExportService($pdo,new FileService($pdo,$auth,$mutations,new LocalContentStore($config->storageRoot,$config->maxUploadBytes)),$auth,$identity,$mutations,$runtime,new ZipArchiveStore($config->storageRoot,$config->exports),$config->exports)),new GroupMappingController(new GroupMappingService($identity,$auth,new RbacGroupMappingPolicy(new RbacGroupMembershipPolicy($roles,$identity,$auth,$runtime,$resources,$access)),$resources,$mutations),new AdministrationQueryService($pdo,$auth),$oidc?->issuer,$oidc?->groupsClaim));
};
