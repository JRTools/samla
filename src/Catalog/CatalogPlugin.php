<?php
declare(strict_types=1);
namespace Samla\Core\Catalog;
use Samla\PluginApi\{Plugin,PluginRegistrar,Permission,Scope};
final class CatalogPlugin implements Plugin
{
    public function id():string{return 'platform.catalog';}
    public function apiMajor():int{return 0;}
    public function register(PluginRegistrar $registrar):void
    {foreach(['read','write','tags.manage']as$action){$registrar->permission(new Permission('catalog.'.$action,$this->id(),[Scope::Space]));}}
}
