<?php
declare(strict_types=1);
namespace Samla\Core\Media;
use Samla\PluginApi\{MediaField,OutboxEvent,OutboxHandler,OutboxSubscription,Permission,Plugin,PluginRegistrar,Scope};
final readonly class MediaPlugin implements Plugin
{
    public function __construct(private ?\Closure $process=null,private ?\Closure $cleanup=null){}
    public function id():string{return 'platform.media';}
    public function apiMajor():int{return 0;}
    public function register(PluginRegistrar $registrar):void
    {
        foreach(['media.previews.read','media.metadata.read','media.process']as$action){$registrar->permission(new Permission($action,$this->id(),[Scope::Space]));}
        foreach(['mime'=>['Erkanntes Bildformat','text'],'width'=>['Pixelbreite des Originals','integer'],'height'=>['Pixelhöhe des Originals','integer'],
            'orientation'=>['EXIF-Orientierung','integer'],'camera_make'=>['Kamerahersteller','text'],'camera_model'=>['Kameramodell','text'],'captured_at'=>['Aufnahmezeit laut EXIF, Zeitzone ungeprüft','text']]as$id=>[$label,$type]){
            $registrar->mediaField(new MediaField($this->id().'.'.$id,$label,$type));
        }
        $registrar->mediaProvider(new RasterProvider());
        $registrar->outboxSubscriber(new OutboxSubscription('platform.media.assets_v1',[
            'storage.file.upload.completed','storage.file.replace.completed','storage.version.restore.completed','storage.file.copy.completed',
            'storage.file.create_locked.completed','storage.entry.restore.completed','media.asset.refresh.completed',
        ],new class($this->process)implements OutboxHandler{
            public function __construct(private ?\Closure $process){}
            public function handle(OutboxEvent $event):void{if($this->process===null){throw new \LogicException('Use worker composition.');}($this->process)($event);}
        },replayExisting:true));
        $registrar->outboxSubscriber(new OutboxSubscription('platform.media.purged_v1',['storage.entry.purge.completed'],new class($this->cleanup)implements OutboxHandler{
            public function __construct(private ?\Closure $cleanup){}
            public function handle(OutboxEvent $event):void{if($this->cleanup===null){throw new \LogicException('Use worker composition.');}($this->cleanup)();}
        },replayExisting:true));
    }
}
