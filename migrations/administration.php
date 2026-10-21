<?php
declare(strict_types=1);

use Samla\PluginApi\SchemaMigration;

return [new SchemaMigration('platform.administration', '0001_mutations', [
    "CREATE TABLE administration_lock (id TINYINT UNSIGNED PRIMARY KEY) ENGINE=InnoDB",
    "INSERT INTO administration_lock (id) VALUES (1)",
    "CREATE TABLE audit_events (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        actor_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        operation VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        scope ENUM('global','space') NOT NULL,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
        targets TEXT NOT NULL,
        changed TINYINT UNSIGNED NOT NULL,
        occurred_at BIGINT UNSIGNED NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TABLE outbox_events (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        event_type VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        payload TEXT NOT NULL,
        occurred_at BIGINT UNSIGNED NOT NULL,
        status ENUM('pending','delivered') NOT NULL DEFAULT 'pending'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
])];
