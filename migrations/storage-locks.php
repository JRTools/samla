<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.storage', '0005_locks', [
    "CREATE TABLE storage_locks (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        token VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        entry_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        recursive_lock TINYINT UNSIGNED NOT NULL,
        owner_label VARCHAR(4096) NOT NULL,
        expires_at BIGINT UNSIGNED NOT NULL,
        KEY active_space (space_id, expires_at),
        FOREIGN KEY (space_id) REFERENCES storage_spaces(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
])];
