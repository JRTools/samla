<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;

return [new SchemaMigration('platform.storage', '0002_files', [
    'ALTER TABLE storage_spaces ADD quota_bytes BIGINT UNSIGNED NOT NULL DEFAULT 1073741824',
    "CREATE TABLE storage_entries (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        parent_id VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '',
        name VARCHAR(255) NOT NULL,
        kind ENUM('folder','file') NOT NULL,
        blob_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
        sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        created_at BIGINT UNSIGNED NOT NULL,
        UNIQUE KEY sibling_name (space_id, parent_id, name),
        KEY child_listing (space_id, parent_id, id),
        FOREIGN KEY (space_id) REFERENCES storage_spaces(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
])];
