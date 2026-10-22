<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Trusted transactional lifecycle hook, called inside the audited empty-space deletion.
 * Implementations must reject missing transactions and unauthorized actors. Not an HTTP endpoint. */
interface SpaceAccessCleanup
{
    public function removeSpaceInsideTransaction(string $actorId,string $spaceId):void;
}
