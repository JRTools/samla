<?php
declare(strict_types=1);
namespace Samla\PluginApi;
/** Authorized catalog labels and revisions, never local filesystem paths or original bytes. */
final readonly class FileCatalogEntry
{
    public function __construct(public string $id,public string $spaceId,public string $parentId,public string $name,
        public string $kind,public int $revision,public int $sizeBytes,public string $folderPath)
    {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$spaceId)
            ||($parentId!==''&&!preg_match('/^[a-f0-9]{32}$/D',$parentId))||!in_array($kind,['file','folder'],true)
            ||$revision<1||$sizeBytes<0||$sizeBytes>1099511627776||$name===''||strlen($name)>1020||!mb_check_encoding($name,'UTF-8')
            ||strlen($folderPath)>131072||!mb_check_encoding($folderPath,'UTF-8')){throw new \InvalidArgumentException('Invalid catalog entry.');}
    }
}
