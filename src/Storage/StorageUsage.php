<?php
declare(strict_types=1);
namespace Samla\Core\Storage;
/** Internal quota projection. Trash and historical versions remain charged. */
final class StorageUsage
{
    public static function bytes(\PDO $pdo, string $space): int
    {
        $q = $pdo->prepare('SELECT (SELECT COALESCE(SUM(size_bytes), 0) FROM storage_entries WHERE space_id = ?) +
            (SELECT COALESCE(SUM(v.size_bytes), 0) FROM storage_versions v JOIN storage_entries e ON e.id = v.entry_id WHERE e.space_id = ?)');
        $q->execute([$space, $space]); return (int) $q->fetchColumn();
    }
}
