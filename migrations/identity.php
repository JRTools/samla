<?php
declare(strict_types=1);

use Samla\PluginApi\SchemaMigration;

return [new SchemaMigration('platform.identity', '0001_identity', [
    "CREATE TABLE identity_users (
        id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        issuer VARBINARY(1024) NOT NULL,
        subject VARBINARY(255) NOT NULL,
        display_name VARCHAR(255) NOT NULL,
        status ENUM('active','blocked') NOT NULL DEFAULT 'active',
        groups_confirmed_at BIGINT UNSIGNED NULL,
        authorization_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
        UNIQUE KEY identity_subject (issuer, subject)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TABLE identity_groups (
        id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        name VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
    "CREATE TABLE identity_group_mappings (
        issuer VARBINARY(1024) NOT NULL,
        external_id VARBINARY(256) NOT NULL,
        internal_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY (issuer, external_id, internal_id),
        FOREIGN KEY (internal_id) REFERENCES identity_groups(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB",
    "CREATE TABLE identity_external_groups (
        user_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        external_id VARBINARY(256) NOT NULL,
        PRIMARY KEY (user_id, external_id),
        FOREIGN KEY (user_id) REFERENCES identity_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "CREATE TABLE identity_memberships (
        user_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        group_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        origin ENUM('manual','external') NOT NULL,
        source_group VARBINARY(256) NOT NULL,
        PRIMARY KEY (user_id, group_id, origin, source_group),
        FOREIGN KEY (user_id) REFERENCES identity_users(id) ON DELETE CASCADE,
        FOREIGN KEY (group_id) REFERENCES identity_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
])];
