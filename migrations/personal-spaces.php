<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.storage','0009_personal_spaces',[
    "CREATE TABLE personal_storage_spaces (
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        owner_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        owner_group_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
        created_at BIGINT UNSIGNED NOT NULL,
        FOREIGN KEY(space_id) REFERENCES storage_spaces(id) ON DELETE RESTRICT,
        FOREIGN KEY(owner_id) REFERENCES identity_users(id) ON DELETE RESTRICT,
        FOREIGN KEY(owner_group_id) REFERENCES identity_groups(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB",
])];
