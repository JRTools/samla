<?php
declare(strict_types=1);

use Samla\Core\Background\{OutboxWorker,ReceiptPlugin,WorkerPlugin};
use Samla\Core\Catalog\CatalogPlugin;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Media\{MediaPlugin,MediaPipeline,MediaCacheCollector};
use Samla\Core\Storage\LocalContentStore;
use Samla\Core\Export\{ExportPlugin,ExportCollector,ExportService,ZipArchiveStore};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Storage\FileService;
use Samla\Core\Identity\{IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin};

return static function (\PDO $pdo,AppConfig $config): OutboxWorker {
    $identity=new PdoIdentityRepository($pdo,$config->groupMaxAgeSeconds);
    $runtime=null;
    $media=new MediaPlugin(static function(\Samla\PluginApi\OutboxEvent $event)use(&$runtime,$pdo,$config):void{(new MediaPipeline($pdo,$runtime,new LocalContentStore($config->storageRoot,$config->maxUploadBytes)))->handle($event);},static function()use($pdo):void{(new MediaCacheCollector($pdo))->collect();});
    $store=new ZipArchiveStore($config->storageRoot,$config->exports);
    $exports=new ExportPlugin(static function(\Samla\PluginApi\OutboxEvent $event)use(&$runtime,$pdo,$config,$identity,$store):void{
        $auth=new AuthorizationService($runtime,$identity);$mutations=new PdoMutationRunner($pdo,$runtime);
        (new ExportService($pdo,new FileService($pdo,$auth,$mutations,new LocalContentStore($config->storageRoot,$config->maxUploadBytes)),$auth,$identity,$mutations,$runtime,$store,$config->exports))->handle($event);
    },static function()use($pdo,$store):void{(new ExportCollector($pdo,$store))->collect();});
    $runtime=PluginRuntime::boot([new CatalogPlugin(),new IdentityPlugin(),new StoragePlugin(),new RbacPlugin(new PdoRoleRepository($pdo),$identity),new ReceiptPlugin($pdo),new WorkerPlugin(),$media,$exports]);
    return new OutboxWorker($pdo,$runtime);
};
