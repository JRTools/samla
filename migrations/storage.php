<?php
declare(strict_types=1);

use Samla\PluginApi\SchemaMigration;

// Initial resource catalog only. Quotas, content and filesystem operations follow separately.
return [new SchemaMigration('platform.storage', '0001_space_catalog', [
    "CREATE TABLE storage_spaces (
        id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
])];
