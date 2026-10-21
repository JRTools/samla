<?php
declare(strict_types=1);

use Samla\Core\Administration\{AdministrationQueryService, PdoMutationRunner, PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Identity\{GroupDetailsService, GroupMembershipService, IdentityPlugin, PdoIdentityRepository, UserStatusService};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService, LocalContentStore, StoragePlugin};
use Samla\Core\Web\{AdminController, FileController, PageRenderer};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacGroupMembershipPolicy, RbacPlugin};

return static function (\PDO $pdo, AppConfig $config): AdminController {
    $identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds);
    $roles = new PdoRoleRepository($pdo);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($roles, $identity)]);
    $auth = new AuthorizationService($runtime, $identity);
    $resources = new PdoResourceDirectory($pdo); $mutations = new PdoMutationRunner($pdo, $runtime);
    return new AdminController(new AdministrationQueryService($pdo, $auth), $auth,
        new UserStatusService($identity, $auth, $mutations), new GroupDetailsService($identity, $auth, $mutations),
        new GroupMembershipService($identity, $auth, new RbacGroupMembershipPolicy($roles, $identity, $auth, $runtime, $resources), $resources, $mutations),
        new PageRenderer(), new FileController(new FileService($pdo, $auth, $mutations,
            new LocalContentStore($config->storageRoot))));
};
