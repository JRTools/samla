<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.administration','0003_delivery_revision',[
    'ALTER TABLE outbox_deliveries ADD revision BIGINT UNSIGNED NOT NULL DEFAULT 1',
])];
