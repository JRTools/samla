<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{MutationContext,Scope,StorageLockChange};
final class StorageLockChangeTest extends TestCase
{
    public function testTypedLockProposalContainsExtentAndBoundedDuration():void
    {$p=new StorageLockChange(true,60);$c=new MutationContext(str_repeat('a',32),'alice','storage.lock.acquire',Scope::Space,'a',['lock'=>str_repeat('b',32)],storageLock:$p);self::assertSame($p,$c->storageLock);self::assertTrue($p->recursive);self::assertSame(60,$p->seconds);}
    public function testLockOperationCannotOmitProposal():void
    {$this->expectException(\InvalidArgumentException::class);new MutationContext(str_repeat('a',32),'alice','storage.lock.acquire',Scope::Space,'a',['lock'=>str_repeat('b',32)]);}
    public function testDurationAndReleaseMustMatchOperation():void
    {
        foreach([-1,3601] as $seconds){try{new StorageLockChange(false,$seconds);self::fail('Invalid duration.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
        $this->expectException(\InvalidArgumentException::class);new MutationContext(str_repeat('a',32),'alice','storage.lock.release',Scope::Space,'a',['lock'=>str_repeat('b',32)],storageLock:new StorageLockChange(false,60));
    }
}
