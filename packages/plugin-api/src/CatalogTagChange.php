<?php
declare(strict_types=1);
namespace Samla\PluginApi;

final readonly class CatalogTagChange
{
    public function __construct(public string $spaceId,public string $tagId,public string $operation,
        public ?int $expectedRevision,public ?string $name)
    {
        if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$spaceId)||!preg_match('/^[a-f0-9]{32}$/D',$tagId)
            ||!in_array($operation,['create','rename','delete'],true)
            ||($operation==='create'? $expectedRevision!==null:($expectedRevision===null||$expectedRevision<1))
            ||($operation==='delete'? $name!==null:($name===null||$name===''||$name!==trim($name)||strlen($name)>320
                ||!mb_check_encoding($name,'UTF-8')||mb_strlen($name,'UTF-8')>80||preg_match('/[\x00-\x1f\x7f]/u',$name)))){throw new \InvalidArgumentException('Invalid tag change.');}
    }
}
