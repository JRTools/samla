<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.exports','0001_jobs',[
    "CREATE TABLE export_jobs (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        owner_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        status ENUM('pending','processing','ready','failed','cancelled') NOT NULL DEFAULT 'pending',
        revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
        snapshot_json MEDIUMTEXT NOT NULL,
        provider_signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        required_json TEXT NULL,
        file_count SMALLINT UNSIGNED NOT NULL,
        content_bytes BIGINT UNSIGNED NOT NULL,
        reserved_bytes BIGINT UNSIGNED NOT NULL,
        archive_size BIGINT UNSIGNED NULL,
        archive_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        error_code VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
        created_at BIGINT UNSIGNED NOT NULL,
        expires_at BIGINT UNSIGNED NOT NULL,
        KEY owner_listing(owner_id,created_at,id),
        KEY expiration(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
])];
