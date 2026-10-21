<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;
use Samla\Core\Storage\{FileService, StorageLockService};

/** Protocol projection of the shared stable-ID leases, never a separate DAV lock table. */
final class LockBackend extends DAV\Locks\Backend\AbstractBackend
{
    public function __construct(private readonly Directory $root, private readonly FileService $files,
        private readonly StorageLockService $locks, private readonly string $actor) {}
    private function resolve(string $uri): DAV\INode
    {
        $node = $this->root;
        foreach (explode('/', trim($uri, '/')) as $name) {
            if ($name === '') { continue; }
            if (!$node instanceof Directory) { throw new DAV\Exception\NotFound(); }
            $node = $node->getChild($name);
        }
        return $node;
    }
    private function location(DAV\INode $node): array
    {
        if ($node instanceof Directory && $node->space !== null) { return [$node->space, $node->entry]; }
        if ($node instanceof File) { return [$node->space, $node->snapshot()['id']]; }
        throw new DAV\Exception\Forbidden();
    }
    public function getLocks($uri, $returnChildLocks)
    {
        if (trim($uri, '/') === '') { return []; }
        try { $node = $this->resolve($uri); }
        catch (DAV\Exception\NotFound) {
            $parent = str_contains($uri, '/') ? substr($uri, 0, strrpos($uri, '/')) : '';
            if ($parent === '') { return []; }
            $node = $this->resolve($parent);
        }
        [$space, $entry] = $this->location($node); $result = [];
        foreach ($this->locks->listing($this->actor, $space, $entry, (bool) $returnChildLocks) as $lease) {
            $info = new DAV\Locks\LockInfo();
            $info->token = $lease['token']; $info->owner = $lease['owner_label'];
            $info->depth = $lease['recursive_lock'] ? DAV\Server::DEPTH_INFINITY : 0;
            $info->created = time(); $info->timeout = max(1, (int) $lease['expires_at'] - time());
            $parts = []; $current = $lease['entry_id'];
            while ($current !== '') {
                if (count($parts) >= 128) { throw new DAV\Exception\ServiceUnavailable(); }
                $row = $this->files->details($this->actor, $space, $current);
                array_unshift($parts, $row['name']); $current = $row['parent_id'];
            }
            $info->uri = $space . ($parts === [] ? '' : '/' . implode('/', $parts)); $result[] = $info;
        }
        return $result;
    }
    public function lock($uri, DAV\Locks\LockInfo $lockInfo)
    {
        if ($lockInfo->scope !== DAV\Locks\LockInfo::EXCLUSIVE) { throw new DAV\Exception\NotImplemented(); }
        [$space, $entry] = $this->location($this->resolve($uri));
        $seconds = $lockInfo->timeout === null || $lockInfo->timeout <= 0 ? 3600 : min(3600, $lockInfo->timeout);
        $existing = null;
        foreach ($this->locks->listing($this->actor, $space, $entry) as $lease) { if ($lease['token'] === $lockInfo->token) { $existing = $lease; break; } }
        if ($existing === null) {
            $this->locks->acquire($this->actor, $space, $entry, $lockInfo->depth === DAV\Server::DEPTH_INFINITY, $seconds, (string) $lockInfo->owner, $lockInfo->token);
        } else { $this->locks->renew($this->actor, $space, $entry, $lockInfo->token, $seconds); }
        $lockInfo->timeout = $seconds; $lockInfo->created = time(); return true;
    }
    public function unlock($uri, DAV\Locks\LockInfo $lockInfo)
    {
        [$space, $entry] = $this->location($this->resolve($uri));
        $this->locks->release($this->actor, $space, $entry, $lockInfo->token); return true;
    }
}
