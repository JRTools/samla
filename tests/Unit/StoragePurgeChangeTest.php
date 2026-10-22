<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{MutationContext,Scope,StorageChange,StoragePurgeChange};

final class StoragePurgeChangeTest extends TestCase
{
    public function testCanonicalImmutableRevisionPlanAndContext():void
    {
        $a=str_repeat('a',32);$b=str_repeat('b',32);$p=new StoragePurgeChange([$b=>2,$a=>1]);
        self::assertSame([$a=>1,$b=>2],$p->entryRevisions);
        $context=new MutationContext(str_repeat('c',32),'alice','storage.entry.purge',Scope::Space,'a',['entry'=>$a],storageChange:new StorageChange('name'),storagePurge:$p);
        self::assertSame($p,$context->storagePurge);
        $numeric=str_repeat('0',29).'120';$exponent=str_repeat('0',29).'1e0';
        self::assertSame([$numeric,$exponent],array_keys((new StoragePurgeChange([$exponent=>1,$numeric=>2]))->entryRevisions));
        $revision=2;$entries=[$a=>&$revision];$snapshot=new StoragePurgeChange($entries);$revision=3;
        self::assertSame(2,$snapshot->entryRevisions[$a]);
    }
    public function testInvalidOrUnboundedPlansCannotBeConstructed():void
    {
        foreach([[],['unknown'=>1],[str_repeat('a',32)=>0],[str_repeat('a',32)=>'1']]as$entries){
            try{new StoragePurgeChange($entries);self::fail('Invalid purge plan.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        }
        $entries=[];for($i=0;$i<10001;++$i){$entries[str_pad(dechex($i),32,'0',STR_PAD_LEFT)]=1;}
        $this->expectException(\InvalidArgumentException::class);new StoragePurgeChange($entries);
    }
    public function testRootMustBeIncludedAndPurgeProposalCannotBeReusedForOtherOperations():void
    {
        $a=str_repeat('a',32);$b=str_repeat('b',32);$p=new StoragePurgeChange([$b=>1]);
        foreach(['storage.entry.purge','storage.entry.trash']as$operation){
            try{new MutationContext(str_repeat('c',32),'alice',$operation,Scope::Space,'a',['entry'=>$a],storageChange:new StorageChange('name'),storagePurge:$p);self::fail('Mismatched plan.');}
            catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
