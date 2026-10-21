<?php
declare(strict_types=1);

namespace Samla\Core\Database;

use Samla\PluginApi\SchemaMigration;

final readonly class MigrationRunner
{
    public function __construct(private \PDO $pdo) {}

    /** Explicit trusted order; core never discovers plugin internals. @param list<SchemaMigration> $migrations @return list<string> */
    public function migrate(array $migrations): array
    {
        if ($this->pdo->inTransaction()) { throw new \LogicException('Migration cannot run inside a transaction.'); }
        $known = [];
        foreach ($migrations as $migration) {
            if (!$migration instanceof SchemaMigration || isset($known[$migration->key()])) { throw new \LogicException('Duplicate or invalid migration.'); }
            $known[$migration->key()] = $migration;
        }
        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        if ($database === '') { throw new \RuntimeException('No database selected.'); }
        $lockName = 'mp_schema_' . substr(hash('sha256', $database), 0, 40);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 0)'); $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) { throw new \RuntimeException('Migration lock unavailable.'); }
        try {
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
                owner VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                migration_id VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                checksum CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
                status ENUM('running','applied','failed') NOT NULL,
                applied_at BIGINT UNSIGNED NULL,
                PRIMARY KEY (owner, migration_id)
            ) ENGINE=InnoDB");
            $applied = [];
            foreach ($this->pdo->query('SELECT owner, migration_id, checksum, status FROM schema_migrations') as $row) {
                $key = $row['owner'] . ':' . $row['migration_id'];
                if (!isset($known[$key]) || $row['checksum'] !== $known[$key]->checksum() || $row['status'] !== 'applied') {
                    throw new \RuntimeException('Unknown, changed or unfinished migration: ' . $key);
                }
                $applied[$key] = true;
            }
            $completed = [];
            foreach ($migrations as $migration) {
                if (isset($applied[$migration->key()])) { continue; }
                $insert = $this->pdo->prepare("INSERT INTO schema_migrations (owner, migration_id, checksum, status) VALUES (?, ?, ?, 'running')");
                $insert->execute([$migration->owner, $migration->id, $migration->checksum()]);
                try {
                    foreach ($migration->statements as $sql) { $this->pdo->exec($sql); }
                    $update = $this->pdo->prepare("UPDATE schema_migrations SET status = 'applied', applied_at = ? WHERE owner = ? AND migration_id = ?");
                    $update->execute([time(), $migration->owner, $migration->id]);
                } catch (\Throwable $error) {
                    try {
                        $failed = $this->pdo->prepare("UPDATE schema_migrations SET status = 'failed' WHERE owner = ? AND migration_id = ?");
                        $failed->execute([$migration->owner, $migration->id]);
                    } catch (\Throwable) { /* Connection loss leaves running, which also blocks retries. */ }
                    throw new \RuntimeException('Migration failed; restore or inspect before retrying: ' . $migration->key(), 0, $error);
                }
                $completed[] = $migration->key();
            }
            return $completed;
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]);
        }
    }
}
