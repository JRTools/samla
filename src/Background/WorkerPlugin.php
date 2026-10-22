<?php
declare(strict_types=1);
namespace Samla\Core\Background;
use Samla\PluginApi\{Permission,Plugin,PluginRegistrar,Scope};
final class WorkerPlugin implements Plugin
{
    public function id():string{return 'platform.worker';}
    public function apiMajor():int{return 0;}
    public function register(PluginRegistrar $registrar):void
    {foreach(['operations.worker.read','operations.worker.retry']as$action){$registrar->permission(new Permission($action,$this->id(),[Scope::Global]));}}
}
