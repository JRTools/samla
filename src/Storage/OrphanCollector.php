<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

use Samla\PluginApi\{MutationContext, MutationRunner, Scope};

/** Offline maintenance only. Stop all writers before inspecting or collecting old objects. */
final readonly class OrphanCollector
{
    public function __construct(private \PDO $pdo, private LocalContentStore $store, private MutationRunner $mutations) {}
    public function collect(bool $apply = false, ?int $now = null): array
    {
        $cutoff = ($now ?? time()) - 86400; $candidates = []; $removed = [];
        foreach (new \DirectoryIterator($this->store->root) as $file) {
            $name = $file->getFilename();
            if (!$this->eligible($name, $cutoff)) { continue; }
            if (count($candidates) >= 1000) { break; }
            $candidates[] = $name;
            if (!$apply) { continue; }
            $blob = str_starts_with($name, '.stage.') ? substr($name, 7) : $name;
            $changed = $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)), 'system.storage',
                'storage.orphan.purge', Scope::Global, null, ['object' => $blob]),
                function () use ($name, $cutoff): void { if (!$this->eligible($name, $cutoff)) { throw new \RuntimeException('Object is no longer collectible.'); } },
                function () use ($name): bool { if (!unlink($this->store->root . '/' . $name)) { throw new \RuntimeException('Object removal failed.'); } return true; });
            if ($changed) { $removed[] = $name; }
        }
        return ['candidates' => $candidates, 'removed' => $removed];
    }
    private function eligible(string $name, int $cutoff): bool
    {
        if (!preg_match('/^(?:\.stage\.)?[a-f0-9]{64}$/D', $name)) { return false; }
        $path = $this->store->root . '/' . $name; clearstatcache(true, $path);
        if (is_link($path) || !is_file($path)) { return false; }
        $stat = lstat($path);
        if ($stat === false || $stat['mtime'] > $cutoff || $stat['nlink'] !== 1) { return false; }
        $blob = str_starts_with($name, '.stage.') ? substr($name, 7) : $name;
        $query = $this->pdo->prepare('SELECT 1 FROM storage_entries WHERE blob_id = ? LIMIT 1'); $query->execute([$blob]);
        return $query->fetchColumn() === false;
    }
}
