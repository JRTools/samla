<?php
declare(strict_types=1);

use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Configuration\{AppConfig, OidcConfig};
use Samla\Core\Database\ConnectionFactory;
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Identity\Oidc\LoginProvisioner;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Core\Web\OidcWebController;
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin};

return static function (AppConfig $app, OidcConfig $oidc): OidcWebController {
    $pdo = (new ConnectionFactory())->connect($app->database);
    $identity = new PdoIdentityRepository($pdo, $app->groupMaxAgeSeconds);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin(new PdoRoleRepository($pdo), $identity)]);
    return new OidcWebController($oidc, $pdo, $identity, new LoginProvisioner($pdo, $identity, new PdoMutationRunner($pdo, $runtime)),
        (require __DIR__ . '/web.php')($pdo, $app));
};
