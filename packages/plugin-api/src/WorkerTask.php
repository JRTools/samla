<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Trusted, bounded, idempotent housekeeping, under the worker's exclusive run lock. */
final readonly class WorkerTask
{
    public function __construct(public string $id,public \Closure $run)
    {if(!preg_match('/^[a-z][a-z0-9_.]{1,95}$/D',$id)){throw new \InvalidArgumentException('Invalid worker task.');}}
}
