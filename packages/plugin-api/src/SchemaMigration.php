<?php
declare(strict_types=1);

namespace Samla\PluginApi;

final readonly class SchemaMigration
{
    /** @param non-empty-list<string> $statements One SQL statement per item, no transactions. */
    public function __construct(public string $owner, public string $id, public array $statements)
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $owner)
            || !preg_match('/^[a-zA-Z0-9_.-]{1,128}$/D', $id) || $statements === []) {
            throw new \InvalidArgumentException('Invalid schema migration.');
        }
        foreach ($statements as $sql) {
            if (!is_string($sql) || trim($sql) === '') { throw new \InvalidArgumentException('Empty migration statement.'); }
        }
    }
    public function checksum(): string { return hash('sha256', json_encode([$this->owner, $this->id, $this->statements], JSON_THROW_ON_ERROR)); }
    public function key(): string { return $this->owner . ':' . $this->id; }
}
