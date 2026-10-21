<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.storage', '0003_lifecycle', [
    'ALTER TABLE storage_entries ADD trashed_at BIGINT UNSIGNED NULL',
])];
