<?php
declare(strict_types=1);
namespace Samla\Core\Background;

use Samla\Core\Database\Transaction;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\OutboxEvent;

/** Trusted operational entry point; transports must authenticate before constructing it. */
final readonly class OutboxWorker
{
    public function __construct(private \PDO $pdo, private PluginRuntime $runtime, private ?\Closure $clock = null) {}
    private function now(): int { return $this->clock === null ? time() : ($this->clock)(); }
    public function run(int $maxEvents = 50, int $seconds = 45): array
    {
        if ($maxEvents < 1 || $maxEvents > 50 || $seconds < 1 || $seconds > 45 || $this->pdo->inTransaction()) { throw new \InvalidArgumentException('Invalid worker bounds.'); }
        if ($this->runtime->provider() === null) { throw new \LogicException('Mandatory authorization provider unavailable.'); }
        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'samla_worker_' . substr(hash('sha256', $database), 0, 40);
        $existing = $this->pdo->prepare('SELECT IS_USED_LOCK(?)'); $existing->execute([$lockName]);
        if ($existing->fetchColumn() !== null) { return ['busy'=>true,'processed'=>0,'delivered'=>0,'errors'=>0]; }
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?,0)'); $lock->execute([$lockName]);
        $result = ['busy' => false, 'processed' => 0, 'delivered' => 0, 'errors' => 0];
        if ((int) $lock->fetchColumn() !== 1) { return array_replace($result, ['busy' => true]); }
        $deadline = hrtime(true) + $seconds * 1000000000;
        try {
            $this->discover();
            $ids = array_keys($this->runtime->subscribers());
            // Persisted fair order also works with one-event batches and fixed cron intervals.
            if ($ids !== []) {
                $q=$this->pdo->prepare('SELECT id FROM outbox_subscribers WHERE id IN (' . implode(',',array_fill(0,count($ids),'?')) . ') ORDER BY last_served_order,id');
                $q->execute($ids);$ids=$q->fetchAll(\PDO::FETCH_COLUMN);
            }
            do {
                $progress = false;
                foreach ($ids as $subscriber) {
                    if ($result['processed'] >= $maxEvents || hrtime(true) >= $deadline) { break 2; }
                    $delivery = $this->claim($subscriber);
                    if ($delivery === null) { continue; }
                    $progress = true; ++$result['processed'];
                    try {
                        $payload = json_decode($delivery['payload'], true, 32, JSON_THROW_ON_ERROR);
                        if (!is_array($payload) || ($payload['id'] ?? null) !== $delivery['event_id']) { throw new \InvalidArgumentException('Invalid persisted event.'); }
                        $event = new OutboxEvent($delivery['event_id'], $delivery['event_type'], (int)$delivery['occurred_at'], $payload);
                        $this->runtime->subscribers()[$subscriber]->handler->handle($event);
                        if ($this->pdo->inTransaction()) { throw new \LogicException('Handler left a transaction open.'); }
                    } catch (\Throwable $error) {
                        if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                        $this->finish($delivery, false, $error instanceof \InvalidArgumentException || $error instanceof \JsonException ? 'InvalidEvent' : 'HandlerFailure');
                        ++$result['errors']; continue;
                    }
                    $this->finish($delivery, true); ++$result['delivered'];
                }
            } while ($progress);
            return $result;
        } finally { $release=$this->pdo->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]); }
    }
    private function discover(): void
    {
        Transaction::run($this->pdo, function (): bool {
            // Event writers share this lock: no lower uncommitted sequence can be skipped.
            if ($this->pdo->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE')->fetchColumn() === false) { throw new \RuntimeException('Mutation lock missing.'); }
            $max = (int)$this->pdo->query('SELECT COALESCE(MAX(sequence_id),0) FROM outbox_events')->fetchColumn();
            foreach ($this->runtime->subscribers() as $subscription) {
                $types=$subscription->eventTypes; sort($types,SORT_STRING);
                $signature=hash('sha256',json_encode([$types,$subscription->replayExisting],JSON_THROW_ON_ERROR));
                $insert=$this->pdo->prepare('INSERT IGNORE INTO outbox_subscribers(id,scanned_sequence,signature) VALUES (?,?,?)');
                $insert->execute([$subscription->id,$subscription->replayExisting?0:$max,$signature]);
                $q=$this->pdo->prepare('SELECT scanned_sequence,signature FROM outbox_subscribers WHERE id=? FOR UPDATE'); $q->execute([$subscription->id]);$state=$q->fetch();
                if (!hash_equals($state['signature'],$signature)) { throw new \LogicException('Subscription contract changed. Register a new subscription ID.'); }
                $q=$this->pdo->prepare('SELECT id,sequence_id FROM outbox_events WHERE sequence_id>? AND sequence_id<=? AND event_type IN (' . implode(',',array_fill(0,count($types),'?')) . ') ORDER BY sequence_id LIMIT 100');
                $q->execute([(int)$state['scanned_sequence'],$max,...$types]);$events=$q->fetchAll();
                $insert=$this->pdo->prepare('INSERT IGNORE INTO outbox_deliveries(subscriber_id,event_id) VALUES (?,?)');
                foreach ($events as $event) { $insert->execute([$subscription->id,$event['id']]); }
                $scanned=count($events)===100?(int)$events[99]['sequence_id']:$max;
                $this->pdo->prepare('UPDATE outbox_subscribers SET scanned_sequence=? WHERE id=?')->execute([$scanned,$subscription->id]);
            }
            return true;
        });
    }
    private function claim(string $subscriber): ?array
    {
        return Transaction::run($this->pdo,function () use ($subscriber): ?array {
            $now=$this->now();
            $this->pdo->prepare("UPDATE outbox_deliveries SET status='failed',lease_token=NULL,error_class='InterruptedDelivery' WHERE subscriber_id=? AND status='processing' AND lease_until<=? AND attempts>=8")->execute([$subscriber,$now]);
            $q=$this->pdo->prepare("SELECT d.*,e.event_type,e.payload,e.occurred_at FROM outbox_deliveries d JOIN outbox_events e ON e.id=d.event_id WHERE subscriber_id=? AND attempts<8 AND ((d.status='pending' AND available_at<=?) OR (d.status='processing' AND lease_until<=?)) ORDER BY e.sequence_id LIMIT 1 FOR UPDATE");
            $q->execute([$subscriber,$now,$now]);$row=$q->fetch();if($row===false){return null;}
            $row['lease_token']=bin2hex(random_bytes(16));++$row['attempts'];
            $this->pdo->prepare("UPDATE outbox_deliveries SET status='processing',attempts=?,lease_token=?,lease_until=? WHERE subscriber_id=? AND event_id=?")->execute([$row['attempts'],$row['lease_token'],$now+120,$subscriber,$row['event_id']]);
            $order=(int)$this->pdo->query('SELECT COALESCE(MAX(last_served_order),0)+1 FROM outbox_subscribers')->fetchColumn();
            $this->pdo->prepare('UPDATE outbox_subscribers SET last_served_order=? WHERE id=?')->execute([$order,$subscriber]);
            return $row;
        });
    }
    private function finish(array $delivery, bool $success, ?string $error = null): void
    {
        $status=$success?'delivered':((int)$delivery['attempts']>=8?'failed':'pending');
        $q=$this->pdo->prepare('UPDATE outbox_deliveries SET status=?,lease_token=NULL,lease_until=0,available_at=?,error_class=? WHERE subscriber_id=? AND event_id=? AND status=\'processing\' AND lease_token=?');
        $q->execute([$status,$success?0:$this->now()+min(3600,5*(2**((int)$delivery['attempts']-1))),$error,$delivery['subscriber_id'],$delivery['event_id'],$delivery['lease_token']]);
        if ($q->rowCount() !== 1) { throw new \RuntimeException('Delivery lease lost.'); }
    }
    /** Counts only registered subscriptions and already discovered deliveries. */
    public function status(): array
    {
        $ids=array_keys($this->runtime->subscribers());$result=['pending'=>0,'processing'=>0,'delivered'=>0,'failed'=>0];
        if ($ids===[]) {return $result;}
        $q=$this->pdo->prepare('SELECT status,COUNT(*) AS total FROM outbox_deliveries WHERE subscriber_id IN (' . implode(',',array_fill(0,count($ids),'?')) . ') GROUP BY status');$q->execute($ids);
        foreach($q as $row){$result[$row['status']]=(int)$row['total'];}return $result;
    }
}
