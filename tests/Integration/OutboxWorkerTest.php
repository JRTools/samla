<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Background\OutboxWorker;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory,MigrationRunner};
use Samla\Core\Identity\PdoIdentityRepository;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{OutboxEvent,OutboxHandler,OutboxSubscription,PluginRegistrar};
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin};
use Samla\Tests\Fixtures\Extension;

final class OutboxWorkerTest extends TestCase
{
    private \PDO $pdo;
    private int $now=10000;
    private function connect(): \PDO
    {$c=AppConfig::fromGlobals();if($c->environment!=='test'){throw new \RuntimeException();}return (new ConnectionFactory())->connect($c->database);}
    protected function setUp():void
    {
        $this->pdo=$this->connect();(new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        $this->pdo->exec('DROP TRIGGER IF EXISTS test_worker_ack_failure');
        foreach(['outbox_deliveries','outbox_receipts','outbox_subscribers','outbox_events']as$table){$this->pdo->exec('DELETE FROM '.$table);}
    }
    private function runtime(array $subscriptions):PluginRuntime
    {
        $r=new RbacPlugin(new PdoRoleRepository($this->pdo),new PdoIdentityRepository($this->pdo,3600));
        return PluginRuntime::boot([$r,new Extension('test.events',static function(PluginRegistrar $registrar)use($subscriptions):void{foreach($subscriptions as$s){$registrar->outboxSubscriber($s);}})]);
    }
    private function handler(\Closure $callback):OutboxHandler
    {return new class($callback) implements OutboxHandler{public function __construct(private \Closure $callback){}public function handle(OutboxEvent $event):void{($this->callback)($event);}};}
    private function subscription(\Closure $callback,bool $replay=true,string $id='test.events.one',array $types=['storage.file.upload.completed']):OutboxSubscription
    {return new OutboxSubscription($id,$types,$this->handler($callback),$replay);}
    private function worker(array $subscriptions,?\PDO $pdo=null):OutboxWorker
    {return new OutboxWorker($pdo??$this->pdo,$this->runtime($subscriptions),fn():int=>$this->now);}
    private function event(?\PDO $pdo=null,string $type='storage.file.upload.completed',?string $payload=null):string
    {
        $id=bin2hex(random_bytes(16));($pdo??$this->pdo)->prepare('INSERT INTO outbox_events(id,event_type,payload,occurred_at) VALUES (?,?,?,?)')->execute([$id,$type,$payload??json_encode(['id'=>$id,'actor_id'=>'alice']),$this->now]);return $id;
    }
    public function testBoundedReplayAndDurableAcknowledgementsDoNotRedeliver():void
    {
        $seen=[];for($i=0;$i<120;++$i){$this->event();}$worker=$this->worker([$this->subscription(static function(OutboxEvent $e)use(&$seen):void{$seen[]=$e->id;})]);
        self::assertSame(50,$worker->run()['delivered']);self::assertSame(50,$worker->status()['pending']);
        self::assertSame(50,$worker->run()['delivered']);self::assertSame(20,$worker->run()['delivered']);self::assertSame(0,$worker->run()['processed']);
        self::assertCount(120,array_unique($seen));self::assertSame(120,$worker->status()['delivered']);
    }
    public function testNewSubscriptionsStartAtCurrentWatermarkUnlessReplayWasExplicit():void
    {
        $old=$this->event();$seen=[];$subscription=$this->subscription(static function(OutboxEvent $e)use(&$seen):void{$seen[]=$e->id;},false);
        $worker=$this->worker([$subscription]);self::assertSame(0,$worker->run()['processed']);$new=$this->event();$this->event(type:'unrelated.completed');
        self::assertSame(1,$worker->run()['delivered']);self::assertSame([$new],$seen);self::assertNotContains($old,$seen);
        self::assertSame(0,$this->worker([])->run()['processed']);
    }
    public function testFailuresBackOffAndBecomeVisibleAfterEightAttempts():void
    {
        $this->event();$calls=0;$worker=$this->worker([$this->subscription(static function()use(&$calls):void{++$calls;throw new \RuntimeException('secret that must not be recorded');})]);
        for($i=1;$i<=8;++$i){self::assertSame(1,$worker->run()['errors']);self::assertSame(0,$worker->run()['processed']);$this->now+=5*(2**($i-1));}
        self::assertSame(8,$calls);self::assertSame(1,$worker->status()['failed']);self::assertSame(0,$worker->run()['processed']);
        self::assertSame('HandlerFailure',$this->pdo->query('SELECT error_class FROM outbox_deliveries')->fetchColumn());
    }
    public function testMalformedEventsAreNeverPassedToHandlers():void
    {
        $this->event(payload:'{broken');$called=false;$worker=$this->worker([$this->subscription(static function()use(&$called):void{$called=true;})]);
        self::assertSame(1,$worker->run()['errors']);self::assertFalse($called);self::assertSame('InvalidEvent',$this->pdo->query('SELECT error_class FROM outbox_deliveries')->fetchColumn());
    }
    public function testParallelAndRecursiveInvocationsAreExcludedWhileHandlerIsRunning():void
    {
        $other=$this->connect();$subscription=$this->subscription(static function():void{});$second=$this->worker([$subscription],$other);$nested=null;$worker=null;
        $first=$this->subscription(function()use($second,&$nested,&$worker):void{$nested=[$second->run(),$worker->run()];});$worker=$this->worker([$first]);$this->event();
        self::assertSame(1,$worker->run()['delivered']);self::assertTrue($nested[0]['busy']);self::assertTrue($nested[1]['busy']);self::assertSame(0,$nested[0]['processed']);
        self::assertFalse($second->run()['busy']);
    }
    public function testCrashAfterEffectBeforeAcknowledgementReplaysIdempotently():void
    {
        $id=$this->event();$handler=$this->subscription(function(OutboxEvent $event):void{$this->pdo->prepare('INSERT IGNORE INTO outbox_receipts(subscriber_id,event_id,received_at) VALUES (?,?,?)')->execute(['test.events.one',$event->id,$this->now]);});$worker=$this->worker([$handler]);
        $this->pdo->exec("CREATE TRIGGER test_worker_ack_failure BEFORE UPDATE ON outbox_deliveries FOR EACH ROW SET NEW.status=IF(NEW.status='delivered',NULL,NEW.status)");
        try{try{$worker->run();self::fail('Ack failure ignored.');}catch(\PDOException){self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_receipts')->fetchColumn());}}
        finally{$this->pdo->exec('DROP TRIGGER test_worker_ack_failure');}
        self::assertSame(0,$worker->run()['processed']);$this->now+=121;self::assertSame(1,$worker->run()['delivered']);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_receipts')->fetchColumn());self::assertSame(2,(int)$this->pdo->query('SELECT attempts FROM outbox_deliveries')->fetchColumn());
    }
    public function testInterruptedEighthAttemptIsFinalAndSmallBatchesAreFair():void
    {
        $this->event();$seen=[];$a=$this->subscription(static function()use(&$seen):void{$seen[]='a';});$b=$this->subscription(static function()use(&$seen):void{$seen[]='b';},id:'test.events.two');$worker=$this->worker([$a,$b]);
        self::assertSame(1,$worker->run(1)['delivered']);self::assertSame(1,$worker->run(1)['delivered']);self::assertSame(['a','b'],$seen);
        $this->pdo->exec("UPDATE outbox_deliveries SET status='processing',attempts=8,lease_until=1,lease_token='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'");
        self::assertSame(0,$worker->run()['processed']);self::assertSame(2,$worker->status()['failed']);self::assertSame('InterruptedDelivery',$this->pdo->query('SELECT error_class FROM outbox_deliveries LIMIT 1')->fetchColumn());
    }
    public function testChangedSubscriptionContractCannotSilentlySkipOldEvents():void
    {
        $subscription=$this->subscription(static function():void{});$this->worker([$subscription])->run();$this->event();
        $changed=$this->subscription(static function():void{},types:['other.completed']);
        $this->expectException(\LogicException::class);$this->worker([$changed])->run();
    }
    public function testDiscoveryWaitsForMutationWritersBeforeAdvancingSequenceWatermarks():void
    {
        $writer=$this->connect();$writer->beginTransaction();$writer->query('SELECT id FROM administration_lock WHERE id=1 FOR UPDATE');$low=$this->event($writer);
        // Simulate a higher sequence already visible while a lower writer is uncommitted.
        $high=$this->event();$this->pdo->exec('SET SESSION innodb_lock_wait_timeout=1');$seen=[];$worker=$this->worker([$this->subscription(static function(OutboxEvent $e)use(&$seen):void{$seen[]=$e->id;})]);
        try{try{$worker->run();self::fail('Uncommitted sequence skipped.');}catch(\PDOException){self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_subscribers')->fetchColumn());}}
        finally{$writer->commit();$this->pdo->exec('SET SESSION innodb_lock_wait_timeout=50');}
        self::assertSame(2,$worker->run()['delivered']);self::assertSame([$low,$high],$seen);
    }
    public function testHandlerCannotLeaveAnUncommittedTransactionInTheWorkerConnection():void
    {
        $this->event();$worker=$this->worker([$this->subscription(function(OutboxEvent $event):void{
            $this->pdo->beginTransaction();$this->pdo->prepare('INSERT INTO outbox_receipts(subscriber_id,event_id,received_at) VALUES (?,?,?)')->execute(['test.events.one',$event->id,$this->now]);
        })]);
        self::assertSame(1,$worker->run()['errors']);self::assertFalse($this->pdo->inTransaction());self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_receipts')->fetchColumn());self::assertSame(1,$worker->status()['pending']);
    }
    public function testBoundsAndMandatoryProviderCannotBeBypassed():void
    {
        $worker=new OutboxWorker($this->pdo,PluginRuntime::boot([]));
        try{$worker->run();self::fail('Missing mandatory provider accepted.');}catch(\LogicException){self::assertTrue(true);}
        $worker=$this->worker([]);foreach([0,51]as$max){try{$worker->run($max);self::fail('Limit accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
}
