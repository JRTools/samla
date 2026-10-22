<?php
declare(strict_types=1);

use Samla\Core\Background\{OutboxWorker,ReceiptPlugin};
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Identity\{IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin};

return static function (\PDO $pdo,AppConfig $config): OutboxWorker {
    $identity=new PdoIdentityRepository($pdo,$config->groupMaxAgeSeconds);
    $runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin(new PdoRoleRepository($pdo),$identity),new ReceiptPlugin($pdo)]);
    return new OutboxWorker($pdo,$runtime);
};
