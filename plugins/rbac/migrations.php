<?php
declare(strict_types=1);

use Samla\PluginApi\SchemaMigration;

return [new SchemaMigration('platform.rbac', '0001_roles', [
    "CREATE TABLE rbac_roles (
        id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        scope ENUM('global','space') NOT NULL
    ) ENGINE=InnoDB",
    "CREATE TABLE rbac_role_permissions (
        role_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        permission_id VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY (role_id, permission_id),
        FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "CREATE TABLE rbac_assignments (
        group_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        role_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        scope ENUM('global','space') NOT NULL,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY (group_id, role_id, scope, space_id),
        FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB",
]), new SchemaMigration('platform.rbac', '0002_delegation', [
    "CREATE TABLE rbac_delegations (
        grantor_group_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        role_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY (grantor_group_id, role_id, space_id),
        FOREIGN KEY (role_id) REFERENCES rbac_roles(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB",
    "CREATE TABLE rbac_delegation_permissions (
        grantor_group_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        role_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        space_id VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        permission_id VARCHAR(191) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY (grantor_group_id, role_id, space_id, permission_id),
        FOREIGN KEY (grantor_group_id, role_id, space_id) REFERENCES rbac_delegations(grantor_group_id, role_id, space_id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
])];
