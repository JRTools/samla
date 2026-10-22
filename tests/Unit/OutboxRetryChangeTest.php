<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{MutationContext,OutboxRetryChange,Scope};
final class OutboxRetryChangeTest extends TestCase
{
    public function testRetryContextRequiresExactGlobalTargetsAndProposal():void
    {
        $id=str_repeat('a',32);$proposal=new OutboxRetryChange('example.receiver',$id,3);$targets=['subscriber'=>hash('sha256',$proposal->subscriberId),'event'=>$id];
        $context=new MutationContext($id,'alice','operations.delivery.retry',Scope::Global,null,$targets,outboxRetry:$proposal);self::assertSame($proposal,$context->outboxRetry);
        foreach([['operations.delivery.retry',Scope::Global,null,$targets,null],['operations.delivery.retry',Scope::Space,'a',$targets,$proposal],['operations.delivery.retry',Scope::Global,null,['subscriber'=>'wrong','event'=>$id],$proposal],['other.operation',Scope::Global,null,$targets,$proposal]] as [$op,$scope,$space,$t,$p]){
            try{new MutationContext($id,'alice',$op,$scope,$space,$t,outboxRetry:$p);self::fail('Invalid retry context.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
    public function testInvalidDeliveryReferencesAreRejected():void
    {
        foreach([['wrong id',str_repeat('a',32),1],['valid.id','bad',1],['valid.id',str_repeat('a',32),0]]as[$s,$e,$r]){
            try{new OutboxRetryChange($s,$e,$r);self::fail('Invalid retry.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
