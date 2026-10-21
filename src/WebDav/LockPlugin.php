<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;

/** Lease cleanup belongs to the same SQL transaction as the core deletion. */
final class LockPlugin extends \Sabre\DAV\Locks\Plugin
{
    public function afterUnbind($path) {}
}
