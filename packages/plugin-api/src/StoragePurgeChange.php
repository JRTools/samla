<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Exact retained subtree proposed for irreversible catalog deletion; no names, bytes or tokens. */
final readonly class StoragePurgeChange
{
    /** @var array<string,int> */
    public array $entryRevisions;
    public function __construct(array $entryRevisions)
    {
        if ($entryRevisions === [] || count($entryRevisions) > 10000) { throw new \InvalidArgumentException('Invalid purge extent.'); }
        $snapshot = [];
        foreach ($entryRevisions as $id => $revision) {
            if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id) || !is_int($revision) || $revision < 1) { throw new \InvalidArgumentException('Invalid purge reference.'); }
            $snapshot[$id] = (int) $revision;
        }
        ksort($snapshot, SORT_STRING); $this->entryRevisions = $snapshot;
    }
}
