<?php
declare(strict_types=1);
namespace Samla\PluginApi;
final readonly class MediaRefreshChange
{
    public function __construct(public string $entryId,public int $expectedRevision)
    {if(!preg_match('/^[a-f0-9]{32}$/D',$entryId)||$expectedRevision<1){throw new \InvalidArgumentException('Invalid media refresh.');}}
}
