<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.storage', '0004_versions', [
    'ALTER TABLE storage_entries ADD revision BIGINT UNSIGNED NOT NULL DEFAULT 1',
    "CREATE TABLE storage_versions (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        entry_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        revision BIGINT UNSIGNED NOT NULL,
        blob_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        size_bytes BIGINT UNSIGNED NOT NULL,
        sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at BIGINT UNSIGNED NOT NULL,
        UNIQUE KEY entry_revision (entry_id, revision),
        FOREIGN KEY (entry_id) REFERENCES storage_entries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
])];
