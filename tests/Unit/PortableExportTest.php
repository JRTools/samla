<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Configuration\ExportConfig;
use Samla\Core\Export\{ExportBudgetExceeded,ExportMetadata,ZipArchiveStore};
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{AuthorizationChecker,AuthorizationRequest,AuthorizationResult,Decision,ExportMetadataProvider,ExportMetadataValue,ExportSource,MediaField,Permission,PluginRegistrar,Scope};
use Samla\Tests\Fixtures\Extension;

final class PortableExportTest extends TestCase
{
    private string $root;
    protected function setUp():void{$this->root=sys_get_temp_dir().'/samla-export-'.bin2hex(random_bytes(12));mkdir($this->root,0700);}
    protected function tearDown():void
    {
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as$file){if($file->isDir()&&!$file->isLink()){rmdir($file->getPathname());}else{unlink($file->getPathname());}}rmdir($this->root);
    }
    private function source(string $name='hallo.txt',string $content='hello',string $id='a'):ExportSource
    {return new ExportSource('alice','space',str_repeat($id,32),2,$name,strlen($content),hash('sha256',$content));}
    private function noMetadata():\Closure{return static fn()=>['values'=>[],'required'=>['files.read']];}
    public function testNativePackagePreservesOriginalsAndHasPortableNamesManifestHashesAndPrivateFiles():void
    {
        self::assertTrue(ZipArchiveStore::available(),'Native ZIP with cancellation must be installed in this verification runtime.');
        $store=new ZipArchiveStore($this->root);$id=str_repeat('c',32);$a=$this->source('CON.txt');$b=$this->source('../CON.txt','hello','b');
        $result=$store->locked($id,fn()=>$store->build($id,[$b,$a],static function(){ $s=fopen('php://temp','w+b');fwrite($s,'hello');rewind($s);return $s;},$this->noMetadata()));
        $input=$store->open($id,$result['size']);self::assertSame($result['sha256'],hash('sha256',stream_get_contents($input)));fclose($input);
        $zip=new \ZipArchive();self::assertTrue($zip->open($this->root.'/.exports/'.$id.'.zip')===true);self::assertSame(3,$zip->numFiles);
        $manifest=json_decode($zip->getFromName('manifest.json'),true,32,JSON_THROW_ON_ERROR);self::assertSame('samla-export',$manifest['format']);self::assertSame(1,$manifest['version']);
        foreach($manifest['files']as$file){self::assertSame('hello',$zip->getFromName($file['archive_path']));self::assertSame(hash('sha256','hello'),$file['sha256']);self::assertStringNotContainsString('..',$file['archive_path']);self::assertStringNotContainsString(':',$file['archive_path']);}
        self::assertStringEndsWith('/_CON.txt',$manifest['files'][0]['archive_path']);self::assertSame('CON.txt',$manifest['files'][0]['original_name']);$zip->close();
        self::assertSame(0700,fileperms($this->root.'/.exports')&0777);self::assertSame(0600,fileperms($this->root.'/.exports/'.$id.'.zip')&0777);
        self::assertDirectoryDoesNotExist($this->root.'/.exports/'.$id.'.work');
        $store->locked($id,function()use($store,$id):void{$store->discard($id);});self::assertFileDoesNotExist($this->root.'/.exports/'.$id.'.zip');
    }
    public function testWrongSourceHashAndTimeoutCannotPublishAnArchiveAndCleanStaging():void
    {
        $id=str_repeat('c',32);$store=new ZipArchiveStore($this->root);
        try{$store->locked($id,fn()=>$store->build($id,[$this->source()],static fn()=>fopen('php://temp','w+b'),$this->noMetadata()));self::fail('Expected integrity failure.');}catch(\RuntimeException){self::assertTrue(true);}
        self::assertFileDoesNotExist($this->root.'/.exports/'.$id.'.zip');self::assertDirectoryDoesNotExist($this->root.'/.exports/'.$id.'.work');
        $tick=0;$store=new ZipArchiveStore($this->root,new ExportConfig(seconds:1),static function()use(&$tick):int{return ++$tick*1000000000;});
        try{$store->locked($id,fn()=>$store->build($id,[$this->source()],static fn()=>fopen('php://temp','w+b'),$this->noMetadata()));self::fail('Expected timeout.');}catch(ExportBudgetExceeded){self::assertTrue(true);}
        self::assertFileDoesNotExist($this->root.'/.exports/'.$id.'.zip');self::assertDirectoryDoesNotExist($this->root.'/.exports/'.$id.'.work');
    }
    public function testNativeCloseCancellationLeavesNoPublishedOrStagedPackage():void
    {
        $id=str_repeat('c',32);$calls=0;
        $store=new ZipArchiveStore($this->root,new ExportConfig(seconds:1),static function()use(&$calls):int{return ++$calls>=5?1000000000:0;});
        try{$store->locked($id,fn()=>$store->build($id,[$this->source()],static function(){$s=fopen('php://temp','w+b');fwrite($s,'hello');rewind($s);return $s;},$this->noMetadata()));self::fail('Expected native cancellation.');}
        catch(\RuntimeException){self::assertGreaterThanOrEqual(5,$calls);}
        self::assertFileDoesNotExist($this->root.'/.exports/'.$id.'.zip');self::assertDirectoryDoesNotExist($this->root.'/.exports/'.$id.'.work');
    }
    public function testLargeVideoIsStreamedWithoutLoadingItsBytesIntoPhpMemory():void
    {
        $size=128*1024*1024;$path=$this->root.'/video';$f=fopen($path,'xb');ftruncate($f,$size);fclose($f);
        $source=new ExportSource('alice','space',str_repeat('a',32),1,'video.mp4',$size,hash_file('sha256',$path));
        $store=new ZipArchiveStore($this->root,new ExportConfig(seconds:30));$id=str_repeat('c',32);$before=memory_get_peak_usage(true);
        $result=$store->locked($id,fn()=>$store->build($id,[$source],static fn()=>fopen($path,'rb'),$this->noMetadata()));
        self::assertLessThan(8*1024*1024,memory_get_peak_usage(true)-$before);self::assertGreaterThan($size,$result['size']);
        $zip=new \ZipArchive();$zip->open($this->root.'/.exports/'.$id.'.zip');$stream=$zip->getStream(ZipArchiveStore::archivePath($source));$hash=hash_init('sha256');hash_update_stream($hash,$stream);
        self::assertSame($source->sha256,hash_final($hash));fclose($stream);$zip->close();
    }
    public function testBoundsDuplicateSourcesUnsafeJobsAndSymlinksFailClosed():void
    {
        $store=new ZipArchiveStore($this->root,new ExportConfig(maxBytes:4));$id=str_repeat('c',32);
        try{$store->locked($id,fn()=>$store->build($id,[$this->source()],static fn()=>null,$this->noMetadata()));self::fail('Expected bounded bytes.');}catch(\LengthException){self::assertTrue(true);}
        try{$store->build($id,[],static fn()=>null,$this->noMetadata());self::fail('Expected held lock.');}catch(\LogicException){self::assertTrue(true);}
        try{$store->locked('../outside',static fn()=>true);self::fail('Expected safe identifier.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        $store=new ZipArchiveStore($this->root);
        try{$store->locked($id,fn()=>$store->build($id,[$this->source(),$this->source()],static fn()=>null,$this->noMetadata()));self::fail('Expected unique files.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        symlink($this->root.'/other',$this->root.'/.exports/'.$id.'.zip');
        try{$store->locked($id,fn()=>$store->discard($id));self::fail('Expected rejected link.');}catch(\RuntimeException){self::assertTrue(true);}
    }
    public function testMetadataNeedsExplicitExportOptInCurrentReadRightsAndSensitiveExportGrant():void
    {
        $provider=new class implements ExportMetadataProvider{
            public function id():string{return 'example.metadata.export';}public function version():string{return '1.0';}
            public function metadata(ExportSource $source):array{return [
                new ExportMetadataValue(new MediaField('example.metadata.title','Titel',readPermission:'example.metadata.read',exportable:true),'Titel'),
                new ExportMetadataValue(new MediaField('example.metadata.private','Privat',readPermission:'example.metadata.read'),'nicht exportierbar'),
                new ExportMetadataValue(new MediaField('example.metadata.prompt','Prompt',sensitive:true,readPermission:'example.metadata.sensitive',exportable:true),'Privater Prompt'),
            ];}
        };
        $runtime=PluginRuntime::boot([new Extension('example.metadata',static function(PluginRegistrar $r)use($provider):void{
            foreach(['read','sensitive']as$p){$r->permission(new Permission('example.metadata.'.$p,'example.metadata',[Scope::Space]));}$r->exportProvider($provider);
        })]);
        $allow=['example.metadata.read'];$checker=new class($allow)implements AuthorizationChecker{
            public function __construct(public array $allow){}public function decide(AuthorizationRequest $r):AuthorizationResult{return new AuthorizationResult(in_array($r->action,$this->allow,true)?Decision::Permit:Decision::Deny,'fixture');}
        };
        $collector=new ExportMetadata($runtime,$checker);$result=$collector->collect($this->source());self::assertCount(1,$result['values']);self::assertSame('example.metadata.title',$result['values'][0]['field']);
        $checker->allow[]='example.metadata.sensitive';self::assertCount(1,$collector->collect($this->source())['values']);
        $checker->allow[]='files.export.sensitive';$result=$collector->collect($this->source());self::assertCount(2,$result['values']);self::assertContains('files.export.sensitive',$result['required']);
        $checker->allow=[];self::assertSame([],$collector->collect($this->source())['values']);self::assertSame(64,strlen(ExportMetadata::signature($runtime)));
    }
    public function testProviderRegistrationRejectsForeignIdsDuplicatesAndLateChanges():void
    {
        $make=static fn(string $id,string $version='1'):ExportMetadataProvider=>new class($id,$version)implements ExportMetadataProvider{
            public function __construct(private string $identifier,private string $v){}public function id():string{return $this->identifier;}public function version():string{return $this->v;}public function metadata(ExportSource $source):array{return [];}
        };
        foreach([['foreign.provider','1',false],['example.metadata.export','bad version',false],['example.metadata.export','1',true]]as[$id,$version,$twice]){
            try{PluginRuntime::boot([new Extension('example.metadata',static function(PluginRegistrar $r)use($make,$id,$version,$twice):void{$r->exportProvider($make($id,$version));if($twice){$r->exportProvider($make($id,$version));}})]);self::fail('Expected registration rejection.');}
            catch(\LogicException){self::assertTrue(true);}
        }
        $registrar=null;$runtime=PluginRuntime::boot([new Extension('example.metadata',static function(PluginRegistrar $r)use(&$registrar,$make):void{$registrar=$r;$r->exportProvider($make('example.metadata.export'));})]);
        self::assertCount(1,$runtime->exportProviders());
        try{$registrar->exportProvider($make('example.metadata.other'));self::fail('Expected sealed registration.');}catch(\LogicException){self::assertTrue(true);}
    }
    public function testExportConfigIsExplicitBoundedAndRejectsMalformedEnvironment():void
    {
        self::assertSame(100,ExportConfig::fromEnvironment([])->maxFiles);
        self::assertSame(1000000000,ExportConfig::fromEnvironment(['APP_EXPORT_MAX_BYTES'=>'1000000000'])->maxBytes);
        foreach([['APP_EXPORT_MAX_FILES'=>'101'],['APP_EXPORT_SECONDS'=>'31'],['APP_EXPORT_MAX_BYTES'=>'-1'],['APP_EXPORT_LIFETIME_SECONDS'=>'0'],['APP_EXPORT_RESERVED_BYTES'=>['1']]]as$env){
            try{ExportConfig::fromEnvironment($env);self::fail('Expected bounded configuration.');}catch(\InvalidArgumentException){self::assertTrue(true);}
        }
    }
}
