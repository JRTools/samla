<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\PluginApi\{MediaBatchRefreshChange,MutationContext,Scope};
final class MediaBatchRefreshChangeTest extends TestCase
{
    public function testProposalCopiesReferencesSortsAndBindsExactContext():void
    {
        $id=str_repeat('a',32);$version=1;$change=new MediaBatchRefreshChange([$id=>&$version]);$version=2;self::assertSame(1,$change->entryRevisions[$id]);
        $context=new MutationContext($id,'alice','media.batch.refresh',Scope::Space,'a',['batch'=>$change->signature()],mediaBatchRefresh:$change);self::assertSame($change,$context->mediaBatchRefresh);
        foreach([['media.batch.refresh',Scope::Global,null,['batch'=>$change->signature()],$change],['media.batch.refresh',Scope::Space,'a',['batch'=>'wrong'],$change],['other.operation',Scope::Space,'a',['batch'=>$change->signature()],$change],['media.batch.refresh',Scope::Space,'a',['batch'=>$change->signature()],null]]as[$op,$scope,$space,$targets,$proposal]){
            try{new MutationContext($id,'alice',$op,$scope,$space,$targets,mediaBatchRefresh:$proposal);self::fail('Invalid batch context.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function testBatchesRejectInvalidReferencesEmptyAndMoreThanHundredEntries():void
    {
        $id=str_repeat('a',32);$large=[];for($i=0;$i<101;++$i){$large[str_pad(dechex($i),32,'0',STR_PAD_LEFT)]=1;}
        foreach([[],['bad'=>1],[$id=>0],[$id=>'1'],[$id=>1.5],$large]as$plan){try{new MediaBatchRefreshChange($plan);self::fail('Invalid batch accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
}
