<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.catalog','0001_manual',[
    "CREATE TABLE catalog_tags (
        id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        name VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        name_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        revision BIGINT UNSIGNED NOT NULL DEFAULT 1,
        UNIQUE KEY catalog_tag_name(space_id,name_key),
        FOREIGN KEY(space_id) REFERENCES storage_spaces(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "CREATE TABLE catalog_entries (
        entry_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        revision BIGINT UNSIGNED NOT NULL,
        description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        FOREIGN KEY(entry_id) REFERENCES storage_entries(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "CREATE TABLE catalog_entry_tags (
        entry_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        tag_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY(entry_id,tag_id),
        FOREIGN KEY(entry_id) REFERENCES storage_entries(id) ON DELETE CASCADE,
        FOREIGN KEY(tag_id) REFERENCES catalog_tags(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
])];
