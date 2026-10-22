<?php
declare(strict_types=1);

use Samla\Core\Background\{OutboxWorker,ReceiptPlugin,WorkerPlugin};
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Media\{MediaPlugin,MediaPipeline};
use Samla\Core\Storage\LocalContentStore;
use Samla\Core\Identity\{IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin};

return static function (\PDO $pdo,AppConfig $config): OutboxWorker {
    $identity=new PdoIdentityRepository($pdo,$config->groupMaxAgeSeconds);
    $runtime=null;
    $media=new MediaPlugin(static function(\Samla\PluginApi\OutboxEvent $event)use(&$runtime,$pdo,$config):void{(new MediaPipeline($pdo,$runtime,new LocalContentStore($config->storageRoot,$config->maxUploadBytes)))->handle($event);});
    $runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin(new PdoRoleRepository($pdo),$identity),new ReceiptPlugin($pdo),new WorkerPlugin(),$media]);
    return new OutboxWorker($pdo,$runtime);
};
