<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.media','0002_reference_indexes',[
    'ALTER TABLE storage_entries ADD INDEX media_blob_reference(blob_id)',
    'ALTER TABLE storage_versions ADD INDEX media_version_reference(blob_id)',
])];
