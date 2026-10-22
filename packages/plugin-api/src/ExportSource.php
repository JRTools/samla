<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Authorized current-file description for exporters; never a path or credential. */
final readonly class ExportSource
{
    public function __construct(public string $actorId,public string $spaceId,public string $entryId,
        public int $revision,public string $name,public int $sizeBytes,public string $sha256)
    {
        foreach([$actorId,$spaceId]as$id){if(!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$id)){throw new \InvalidArgumentException('Invalid export actor or space.');}}
        if(!preg_match('/^[a-f0-9]{32}$/D',$entryId)||$revision<1||$sizeBytes<0||$sizeBytes>1099511627776
            ||!preg_match('/^[a-f0-9]{64}$/D',$sha256)||$name===''||!mb_check_encoding($name,'UTF-8')||mb_strlen($name)>255
            ||preg_match('/[\x00-\x1f\x7f]/u',$name)){throw new \InvalidArgumentException('Invalid export source.');}
    }
}
