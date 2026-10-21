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
    public function testLockedCreationRequiresContentAndLockProposalTogether():void
    {
        $content=new \Samla\PluginApi\StorageChange('new','',0,hash('sha256',''));$lock=new StorageLockChange(false,60);
        $context=new MutationContext(str_repeat('a',32),'alice','storage.file.create_locked',Scope::Space,'a',['entry'=>str_repeat('b',32),'lock'=>str_repeat('c',32)],storageChange:$content,storageLock:$lock);
        self::assertSame($content,$context->storageChange);self::assertSame($lock,$context->storageLock);
        foreach([[null,$lock],[$content,null],[$content,new StorageLockChange(false,0)]]as[$file,$lease]){
            try{new MutationContext(str_repeat('a',32),'alice','storage.file.create_locked',Scope::Space,'a',['entry'=>str_repeat('b',32),'lock'=>str_repeat('c',32)],storageChange:$file,storageLock:$lease);self::fail('Partial proposal accepted.');}
            catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
