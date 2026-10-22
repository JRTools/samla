<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.media','0001_assets',[
    "CREATE TABLE media_assets (
        blob_id CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        source_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        source_size BIGINT UNSIGNED NOT NULL,
        status VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        metadata_json TEXT NOT NULL,
        providers_json TEXT NOT NULL,
        preview MEDIUMBLOB NULL,
        preview_bytes INT UNSIGNED NOT NULL DEFAULT 0,
        processed_at BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY(blob_id,signature)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TABLE media_refresh_requests (
        entry_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        requested_at BIGINT UNSIGNED NOT NULL,
        FOREIGN KEY(entry_id) REFERENCES storage_entries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
])];
