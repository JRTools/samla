<?php
declare(strict_types=1);
namespace Samla\Core\Background;

use Samla\PluginApi\{OutboxEvent,OutboxHandler,OutboxSubscription,Plugin,PluginRegistrar};

/** Reference technical consumer. No file contents, permissions or tokens are copied. */
final readonly class ReceiptPlugin implements Plugin
{
    public function __construct(private \PDO $pdo) {}
    public function id(): string { return 'platform.receipts'; }
    public function apiMajor(): int { return 0; }
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->outboxSubscriber(new OutboxSubscription('platform.receipts.storage_v1', [
            'storage.file.upload.completed','storage.file.replace.completed','storage.version.restore.completed',
            'storage.entry.purge.completed','storage.folder.create.completed',
        ],new class($this->pdo) implements OutboxHandler {
            public function __construct(private \PDO $pdo) {}
            public function handle(OutboxEvent $event): void
            {
                $q=$this->pdo->prepare('INSERT IGNORE INTO outbox_receipts(subscriber_id,event_id,received_at) VALUES (?,?,?)');
                $q->execute(['platform.receipts.storage_v1',$event->id,time()]);
            }
        },replayExisting:true));
    }
}
