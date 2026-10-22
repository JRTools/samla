<?php
declare(strict_types=1);
namespace Samla\PluginApi;
final readonly class OutboxRetryChange
{
    public function __construct(public string $subscriberId,public string $eventId,public int $expectedRevision)
    {
        if(!preg_match('/^[a-z][a-z0-9_.]{1,95}$/D',$subscriberId)||!preg_match('/^[a-f0-9]{32}$/D',$eventId)||$expectedRevision<1){throw new \InvalidArgumentException('Invalid delivery retry.');}
    }
}
