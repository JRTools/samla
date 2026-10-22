<?php
declare(strict_types=1);
use Samla\PluginApi\SchemaMigration;
return [new SchemaMigration('platform.administration', '0002_outbox_delivery', [
    'ALTER TABLE outbox_events ADD sequence_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, ADD UNIQUE KEY event_sequence (sequence_id)',
    "CREATE TABLE outbox_subscribers (
        id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
        scanned_sequence BIGINT UNSIGNED NOT NULL,
        last_served_order BIGINT UNSIGNED NOT NULL DEFAULT 0,
        signature CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
    ) ENGINE=InnoDB",
    "CREATE TABLE outbox_deliveries (
        subscriber_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        status ENUM('pending','processing','delivered','failed') NOT NULL DEFAULT 'pending',
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        available_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
        lease_until BIGINT UNSIGNED NOT NULL DEFAULT 0,
        lease_token CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
        error_class VARCHAR(160) CHARACTER SET ascii COLLATE ascii_bin NULL,
        PRIMARY KEY (subscriber_id,event_id),
        KEY due_deliveries (subscriber_id,status,available_at),
        FOREIGN KEY (subscriber_id) REFERENCES outbox_subscribers(id),
        FOREIGN KEY (event_id) REFERENCES outbox_events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
    "CREATE TABLE outbox_receipts (
        subscriber_id VARCHAR(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        event_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        received_at BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (subscriber_id,event_id),
        FOREIGN KEY (event_id) REFERENCES outbox_events(id) ON DELETE CASCADE
    ) ENGINE=InnoDB",
])];
