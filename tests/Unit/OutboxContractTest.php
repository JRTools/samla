<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{OutboxEvent,OutboxHandler,OutboxSubscription,PluginRegistrar};
use Samla\Tests\Fixtures\Extension;

final class OutboxContractTest extends TestCase
{
    private function handler():OutboxHandler{return new class implements OutboxHandler{public function handle(OutboxEvent $event):void{}};}
    public function testEventAndSubscriptionBreakMutableInputReferences(): void
    {
        $value='before';$event=new OutboxEvent(str_repeat('a',32),'storage.file.upload.completed',1,['nested'=>['value'=>&$value]]);$value='after';self::assertSame('before',$event->payload['nested']['value']);
        $type='storage.file.upload.completed';$sub=new OutboxSubscription('test.events.one',[&$type],$this->handler());$type='other';self::assertSame(['storage.file.upload.completed'],$sub->eventTypes);
    }
    public function testEventPayloadBoundsAndIdsAreEnforced(): void
    {
        foreach([['bad','type',[]],[str_repeat('a',32),'bad type',[]],[str_repeat('a',32),'type',['large'=>str_repeat('x',65536)]]]as[$id,$type,$payload]){
            try{new OutboxEvent($id,$type,1,$payload);self::fail('Invalid event accepted.');}catch(\InvalidArgumentException|\LengthException){self::assertTrue(true);}
        }
    }
    public function testSubscriptionsRejectDuplicatesEmptyAndInvalidTypes(): void
    {
        foreach([[],['valid','valid'],['not valid'],array_fill(0,17,'valid')]as$types){try{new OutboxSubscription('test.events.one',$types,$this->handler());self::fail('Invalid subscription accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function testOwnerDuplicateAndClosedRegistrarAreEnforced(): void
    {
        foreach(['foreign.events.one','test.events.one']as$id){$handler=$this->handler();try{
            PluginRuntime::boot([new Extension('test.events',static function(PluginRegistrar $r)use($id,$handler):void{$s=new OutboxSubscription($id,['valid'],$handler);$r->outboxSubscriber($s);$r->outboxSubscriber($s);})]);self::fail('Invalid registration accepted.');
        }catch(\LogicException){self::assertTrue(true);}}
        $saved=null;$handler=$this->handler();$runtime=PluginRuntime::boot([new Extension('test.events',static function(PluginRegistrar $r)use(&$saved,$handler):void{$saved=$r;$r->outboxSubscriber(new OutboxSubscription('test.events.one',['valid'],$handler));})]);self::assertCount(1,$runtime->subscribers());
        $this->expectException(\LogicException::class);$saved->outboxSubscriber(new OutboxSubscription('test.events.two',['valid'],$handler));
    }
}
