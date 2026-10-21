<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.storage', '0006_active_names', [
    "ALTER TABLE storage_entries DROP INDEX sibling_name, ADD active_name VARCHAR(255) GENERATED ALWAYS AS (CASE WHEN trashed_at IS NULL THEN name ELSE NULL END) STORED, ADD UNIQUE KEY active_sibling_name (space_id, parent_id, active_name)",
])];
