<?php
declare(strict_types=1);
namespace Samla\Core\Administration;

use Samla\PluginApi\{AccessDenied,AccessProfileAdministration};

/** Administrative names and capacity only. No file listings or content access are conveyed. */
final readonly class AccessAdministrationDirectory
{
    public function __construct(private \PDO $pdo,private AccessProfileAdministration $access){}
    public function choices(string $actor):array
    {
        if(!$this->access->canManage($actor)){throw new AccessDenied();}
        $groups=$this->pdo->query('SELECT id,name FROM identity_groups ORDER BY name,id LIMIT 1001')->fetchAll();
        $spaces=$this->pdo->query('SELECT id,name,quota_bytes FROM storage_spaces ORDER BY name,id LIMIT 1001')->fetchAll();
        if(count($groups)>1000||count($spaces)>1000){throw new \LengthException('Administrative selection limit.');}
        return ['groups'=>$groups,'spaces'=>$spaces];
    }
}
