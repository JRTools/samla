<?php
declare(strict_types=1);

namespace Samla\Core\Administration;

use Samla\PluginApi\ResourceDirectory;

final readonly class PdoResourceDirectory implements ResourceDirectory
{
    public function __construct(private \PDO $pdo) {}
    public function hasGroup(string $id): bool
    {
        $query = $this->pdo->prepare('SELECT 1 FROM identity_groups WHERE id = ?'); $query->execute([$id]);
        return $query->fetchColumn() !== false;
    }
    public function hasSpace(string $id): bool
    {
        $query = $this->pdo->prepare('SELECT 1 FROM storage_spaces WHERE id = ?'); $query->execute([$id]);
        return $query->fetchColumn() !== false;
    }
}
