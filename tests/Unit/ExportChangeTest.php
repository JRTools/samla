<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{ExportChange,MutationContext,PluginRegistrar,Scope,WorkerTask};
use Samla\Tests\Fixtures\Extension;
final class ExportChangeTest extends TestCase
{
    public function testExactImmutableSourcePlanMatchesOperationJobAndSpace():void
    {
        $entry=str_repeat('a',32);$revision=1;$job=str_repeat('b',32);$change=new ExportChange($job,'space','request',[$entry=>&$revision]);$revision=2;
        self::assertSame([$entry=>1],$change->entryRevisions);
        $context=new MutationContext(str_repeat('c',32),'alice','exports.request',Scope::Space,'space',['export'=>$job],exportChange:$change);self::assertSame($change,$context->exportChange);
        foreach([
            fn()=>new MutationContext(str_repeat('c',32),'alice','exports.request',Scope::Space,'other',['export'=>$job],exportChange:$change),
            fn()=>new MutationContext(str_repeat('c',32),'alice','exports.remove',Scope::Space,'space',['export'=>$job],exportChange:$change),
            fn()=>new ExportChange($job,'space','request',[]),fn()=>new ExportChange($job,'space','retry',[$entry=>1]),
            fn()=>new ExportChange($job,'space','request',[$entry=>'1']),fn()=>new ExportChange('../bad','space','remove',[],1),
        ]as$make){try{$make();self::fail('Expected rejected export binding.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function testWorkerTasksAreOwnerBoundLimitedAndSealed():void
    {
        foreach(['foreign.task','example.tasks.one']as$id){
            try{PluginRuntime::boot([new Extension('example.tasks',static function(PluginRegistrar $r)use($id):void{$r->workerTask(new WorkerTask($id,static fn()=>null));$r->workerTask(new WorkerTask($id,static fn()=>null));})]);self::fail('Expected owner/duplicate rejection.');}catch(\LogicException){self::assertTrue(true);}
        }
        try{PluginRuntime::boot([new Extension('example.tasks',static function(PluginRegistrar $r):void{for($i=0;$i<9;++$i){$r->workerTask(new WorkerTask('example.tasks.t'.$i,static fn()=>null));}})]);self::fail('Expected bounded tasks.');}catch(\LogicException){self::assertTrue(true);}
        $registrar=null;$runtime=PluginRuntime::boot([new Extension('example.tasks',static function(PluginRegistrar $r)use(&$registrar):void{$registrar=$r;$r->workerTask(new WorkerTask('example.tasks.one',static fn()=>null));})]);self::assertCount(1,$runtime->workerTasks());
        try{$registrar->workerTask(new WorkerTask('example.tasks.two',static fn()=>null));self::fail('Expected sealed task registration.');}catch(\LogicException){self::assertTrue(true);}
    }
}
