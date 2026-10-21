<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.identity', '0005_app_passwords', [
    "CREATE TABLE identity_app_passwords (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        user_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        label VARCHAR(255) NOT NULL,
        password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        created_at BIGINT UNSIGNED NOT NULL,
        expires_at BIGINT UNSIGNED NOT NULL,
        revoked_at BIGINT UNSIGNED NULL,
        KEY user_credentials (user_id, id),
        FOREIGN KEY (user_id) REFERENCES identity_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TABLE identity_app_auth_limits (
        bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        window_start BIGINT UNSIGNED NOT NULL,
        failures INT UNSIGNED NOT NULL
    ) ENGINE=InnoDB",
])];
