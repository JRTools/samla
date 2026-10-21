<?php
declare(strict_types=1);
namespace Samla\PluginApi;
/** Lock proposal excludes the opaque proof token and client-provided owner label. */
final readonly class StorageLockChange
{
    public function __construct(public bool $recursive, public int $seconds)
    { if ($seconds < 0 || $seconds > 3600) { throw new \InvalidArgumentException('Invalid lock duration.'); } }
}
