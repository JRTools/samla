<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Media\MediaPlugin;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{MediaField,MediaProvider,MediaResult,MediaSource,MediaValue,PluginRegistrar};
use Samla\Tests\Fixtures\Extension;
final class MediaContractTest extends TestCase
{
    private function provider(string $id):MediaProvider
    {return new class($id)implements MediaProvider{public function __construct(private string $name){}public function id():string{return $this->name;}public function version():string{return '1';}public function extract(MediaSource $s):MediaResult{return new MediaResult('unsupported');}};}
    public function testSourceChecksHashAndGivesIndependentPrivateStreams():void
    {
        $s=new MediaSource('source',hash('sha256','source'));$a=$s->open();$b=$s->open();try{self::assertSame('so',fread($a,2));self::assertSame('source',stream_get_contents($b));}finally{fclose($a);fclose($b);}
        $this->expectException(\InvalidArgumentException::class);new MediaSource('source',str_repeat('0',64));
    }
    public function testSensitiveFieldsAndTypedBoundsCannotBeImplicit():void
    {
        foreach([fn()=>new MediaField('test.field','Secret',sensitive:true),fn()=>new MediaField('test.field','Date','date'),fn()=>new MediaValue('test.field','source',str_repeat('x',1025),null),fn()=>new MediaResult('unknown'),fn()=>new MediaResult('ready',array_fill(0,33,new MediaValue('test.field','source',1,1)))]as$make){try{$make();self::fail('Invalid media contract accepted.');}catch(\InvalidArgumentException){self::assertTrue(true);}}
    }
    public function testOwnerPermissionDuplicatesAndClosedRegistrarsAreEnforced():void
    {
        foreach(['foreign.field','test.media.field']as$id){try{PluginRuntime::boot([new MediaPlugin(),new Extension('test.media',static function(PluginRegistrar $r)use($id):void{$f=new MediaField($id,'Field');$r->mediaField($f);$r->mediaField($f);})]);self::fail('Invalid owner/duplicate accepted.');}catch(\LogicException){self::assertTrue(true);}}
        try{PluginRuntime::boot([new Extension('test.media',static fn(PluginRegistrar $r)=>$r->mediaField(new MediaField('test.media.field','Field')))]);self::fail('Unknown field permission accepted.');}catch(\LogicException){self::assertTrue(true);}
        $saved=null;$a=$this->provider('test.media.a');$b=$this->provider('test.media.b');$runtime=PluginRuntime::boot([new Extension('test.media',static function(PluginRegistrar $r)use(&$saved,$a,$b):void{$saved=$r;$r->mediaProvider($b,10);$r->mediaProvider($a,10);})]);self::assertSame([$a,$b],$runtime->mediaProviders());
        $this->expectException(\LogicException::class);$saved->mediaProvider($this->provider('test.media.c'));
    }
}
