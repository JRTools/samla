<?php
declare(strict_types=1);
namespace Samla\Core\Export;
use Samla\PluginApi\{OutboxEvent,OutboxHandler,OutboxSubscription,Permission,Plugin,PluginRegistrar,Scope};
final readonly class ExportPlugin implements Plugin
{
    public function __construct(private ?\Closure $process=null,private ?\Closure $cleanup=null){}
    public function id():string{return 'platform.exports';}
    public function apiMajor():int{return 0;}
    public function register(PluginRegistrar $registrar):void
    {
        foreach(['files.export','files.export.sensitive']as$permission){$registrar->permission(new Permission($permission,$this->id(),[Scope::Space]));}
        $registrar->workerTask(new \Samla\PluginApi\WorkerTask('platform.exports.expire_v1',function():void{
            if($this->cleanup===null){throw new \LogicException('Use worker composition for export cleanup.');}($this->cleanup)();
        }));
        $registrar->outboxSubscriber(new OutboxSubscription('platform.exports.packages_v1',[
            'exports.request.completed','exports.retry.completed','exports.remove.completed',
        ],new class($this->process)implements OutboxHandler{
            public function __construct(private ?\Closure $process){}
            public function handle(OutboxEvent $event):void{
                if($this->process===null){throw new \LogicException('Use worker composition for exports.');}($this->process)($event);
            }
        },replayExisting:true));
    }
}
