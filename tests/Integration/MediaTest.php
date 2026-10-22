<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory,MigrationRunner};
use Samla\Core\Identity\{IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService,LocalContentStore,StorageLockService,StoragePlugin,StorageWriteGuard,TextEditorService};
use Samla\Core\Web\{FileController,Request};
use Samla\PluginApi\{ChangeValidator,MutationContext,PluginRegistrar,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin,Role,RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class MediaTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identity;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private string $root;
    private string $csrf;
    protected function setUp(): void
    {
        $config=AppConfig::fromGlobals(); if($config->environment!=='test'){throw new \RuntimeException();}
        $this->pdo=(new ConnectionFactory())->connect($config->database); (new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        foreach(['media_assets','outbox_deliveries','outbox_receipts','outbox_subscribers','outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->identity=new PdoIdentityRepository($this->pdo,3600);
        foreach(['alice','bob'] as $user){$this->identity->createUser($user,'https://id.example',$user,$user);$this->identity->replaceVerifiedGroups($user,[]);}
        $this->identity->createGroup('editors','Editors');$this->identity->addManualMembership('alice','editors');
        $this->roles=new PdoRoleRepository($this->pdo);$this->runtime=$this->boot();$this->rights();
        $this->pdo->exec("INSERT INTO storage_spaces(id,name,quota_bytes) VALUES ('a','A',1048576),('b','B',1048576)");
        $this->roles->assign(new RoleAssignment('editors','editor',Scope::Space,'a'));
        $this->root=sys_get_temp_dir().'/samla-editor-'.bin2hex(random_bytes(8));mkdir($this->root,0700);$this->csrf=str_repeat('a',64);
    }
    protected function tearDown(): void
    {foreach(new \DirectoryIterator($this->root) as $f){if(!$f->isDot()){unlink($f->getPathname());}}rmdir($this->root);}
    private function boot(array $extra=[]): PluginRuntime
    {return PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new \Samla\Core\Media\MediaPlugin(function($event):void{$this->pipeline()->handle($event);},function():void{(new \Samla\Core\Media\MediaCacheCollector($this->pdo))->collect();}),new RbacPlugin($this->roles,$this->identity),...$extra]);}
    private function rights(array $permissions=['files.list','files.read','files.create','files.update','files.versions.read','files.delete','files.restore','media.previews.read','media.metadata.read','media.process']): void
    {$this->roles->save(new Role('editor',Scope::Space,$permissions),$this->runtime);}
    private function files(?PluginRuntime $runtime=null): FileService
    {$runtime??=$this->runtime;return new FileService($this->pdo,new AuthorizationService($runtime,$this->identity),new PdoMutationRunner($this->pdo,$runtime),new LocalContentStore($this->root));}
    private function upload(string $text='initial',string $name='notes.md'): string
    {$s=fopen('php://temp','w+b');fwrite($s,$text);rewind($s);try{return $this->files()->upload('alice','a','',$name,$s);}finally{fclose($s);}}
    private function media(?PluginRuntime $runtime=null): \Samla\Core\Media\MediaService
    {$runtime??=$this->runtime;return new \Samla\Core\Media\MediaService($this->pdo,$this->files($runtime),new AuthorizationService($runtime,$this->identity),new PdoMutationRunner($this->pdo,$runtime),$runtime);}
    private function pipeline(?PluginRuntime $runtime=null): \Samla\Core\Media\MediaPipeline
    {return new \Samla\Core\Media\MediaPipeline($this->pdo,$runtime??$this->runtime,new LocalContentStore($this->root));}
    private function page(string $path,string $id,?array $post=null,string $actor='alice'): \Samla\Core\Web\Response
    {return (new \Samla\Core\Web\MediaController($this->media()))->handle(new Request($post===null?'GET':'POST',$path,query:['space'=>'a','entry'=>$id,'revision'=>'1'],post:$post===null?[]:$post+['space'=>'a','entry'=>$id,'revision'=>'1','csrf'=>$this->csrf]),$actor,$this->csrf,['actor'=>$actor,'csrf'=>$this->csrf,'caps'=>[],'flash'=>null]);}
    public function testWorkerAutomaticallyBuildsPrivateVersionBoundPreviewAndPreservesOriginal():void
    {
        $png=\Samla\Tests\Fixtures\MediaImages::png();$id=$this->upload($png,'<photo>.jpg');self::assertSame('pending',$this->media()->details('alice','a',$id)['media_status']);
        $worker=new \Samla\Core\Background\OutboxWorker($this->pdo,$this->runtime);self::assertSame(1,$worker->run()['delivered']);
        $detail=$this->media()->details('alice','a',$id);self::assertSame('ready',$detail['media_status']);self::assertCount(6,$detail['metadata']);self::assertSame('image/png',$detail['metadata'][0]['normalized']);
        $preview=$this->page('/media/preview',$id);self::assertSame(200,$preview->status);self::assertSame('image/png',$preview->headers['Content-Type']);self::assertSame("\x89PNG",substr($preview->body,0,4));
        $download=$this->files()->download('alice','a',$id);try{self::assertSame($png,stream_get_contents($download['stream']));}finally{fclose($download['stream']);}
        self::assertSame(200,$this->page('/media',$id)->status);$html=$this->page('/media/detail',$id);self::assertSame(200,$html->status);self::assertStringContainsString('&lt;photo&gt;.jpg',$html->body);self::assertStringNotContainsString('<photo>',$html->body);
        $stream=fopen('php://temp','w+b');fwrite($stream,\Samla\Tests\Fixtures\MediaImages::jpeg(6));rewind($stream);try{$this->files()->replace('alice','a',$id,1,$stream);}finally{fclose($stream);}
        self::assertSame(409,$this->page('/media/preview',$id)->status);self::assertSame('pending',$this->media()->details('alice','a',$id)['media_status']);self::assertSame(1,$worker->run()['delivered']);self::assertNotSame($preview->body,$this->media()->preview('alice','a',$id,2));self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());
    }
    public function testEveryReadRechecksRightsAndHiddenParentsAndOriginalAccessIsSeparate():void
    {
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'photo.png');$this->pipeline()->processEntry($id);
        self::assertSame(403,$this->page('/media/preview',$id,actor:'bob')->status);
        $this->rights(['files.list','media.previews.read']);$detail=$this->media()->details('alice','a',$id);self::assertSame([],$detail['metadata']);self::assertFalse($detail['can_download']);self::assertTrue($detail['has_preview']);self::assertSame(200,$this->page('/media/preview',$id)->status);self::assertStringNotContainsString('/files/download',$this->page('/media/detail',$id)->body);
        $this->rights(['files.list','media.metadata.read']);self::assertSame(403,$this->page('/media/preview',$id)->status);self::assertCount(6,$this->media()->details('alice','a',$id)['metadata']);
        $this->rights();$folder=$this->files()->createFolder('alice','a','','hidden');$this->files()->change('alice','a',$id,'move','photo.png','',parent:$folder);$this->files()->change('alice','a',$folder,'trash','hidden','');self::assertSame(400,$this->page('/media/preview',$id)->status);
    }
    public function testRefreshIsCsrfRevisionAndPermissionProtectedDeduplicatedAndDoesNotProcessInline():void
    {
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'photo.png');self::assertSame(404,$this->page('/media/preview',$id)->status);
        self::assertSame(405,$this->page('/media/refresh',$id)->status);self::assertSame(403,$this->page('/media/refresh',$id,['csrf'=>'bad'])->status);self::assertSame(409,$this->page('/media/refresh',$id,['revision'=>'2'])->status);
        self::assertSame(303,$this->page('/media/refresh',$id,[])->status);self::assertSame(303,$this->page('/media/refresh',$id,[])->status);self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type='media.asset.refresh.completed'")->fetchColumn());
        $this->rights(['files.list','media.previews.read']);self::assertSame(403,$this->page('/media/refresh',$id,[])->status);
    }
    public function testUnavailableProviderIsRetriedAndCacheIsIdempotent():void
    {
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'photo.png');$calls=0;$provider=new class($calls) implements \Samla\PluginApi\MediaProvider{
            public function __construct(private mixed &$calls){}public function id():string{return 'test.media.provider';}public function version():string{return '1';}
            public function extract(\Samla\PluginApi\MediaSource $s):\Samla\PluginApi\MediaResult{++$this->calls;return new \Samla\PluginApi\MediaResult($this->calls===1?'unavailable':'unsupported');}
        };
        $runtime=$this->boot([new Extension('test.media',static fn(PluginRegistrar $r)=>$r->mediaProvider($provider))]);
        try{$this->pipeline($runtime)->processEntry($id);self::fail('Unavailable provider acknowledged.');}catch(\RuntimeException){self::assertSame(1,$calls);}
        self::assertSame('partial',$this->media($runtime)->details('alice','a',$id)['media_status']);$this->pipeline($runtime)->processEntry($id);$this->pipeline($runtime)->processEntry($id);self::assertSame(2,$calls);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());
    }
    public function testSensitiveFieldsUseCurrentAdditionalPermissionAndEscapedSources():void
    {
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'photo.png');
        $runtime=$this->boot([new Extension('test.media',static function(PluginRegistrar $r):void{
            $r->permission(new \Samla\PluginApi\Permission('test.media.private','test.media',[Scope::Space]));$r->mediaField(new \Samla\PluginApi\MediaField('test.media.secret','Private',sensitive:true,readPermission:'test.media.private'));
            $r->mediaProvider(new class implements \Samla\PluginApi\MediaProvider{public function id():string{return 'test.media.provider';}public function version():string{return '1';}public function extract(\Samla\PluginApi\MediaSource $s):\Samla\PluginApi\MediaResult{return new \Samla\PluginApi\MediaResult('ready',[new \Samla\PluginApi\MediaValue('test.media.secret','<script>','secret','secret')]);}});
        })]);$this->pipeline($runtime)->processEntry($id);self::assertCount(6,$this->media($runtime)->details('alice','a',$id)['metadata']);
        $this->roles->save(new Role('editor',Scope::Space,['files.list','media.metadata.read','test.media.private']),$runtime);self::assertCount(7,$this->media($runtime)->details('alice','a',$id)['metadata']);
        $this->pdo->exec("UPDATE identity_users SET status='blocked' WHERE id='alice'");try{$this->media($runtime)->details('alice','a',$id);self::fail('Blocked actor accessed metadata.');}catch(\Samla\PluginApi\AccessDenied){self::assertTrue(true);}
    }
    public function testRefreshPermissionRevokedByValidatorRollsBackRequestAuditAndEvent():void
    {
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'photo.png');$roles=$this->roles;$base=$this->runtime;
        $runtime=$this->boot([new Extension('test.media',static function(PluginRegistrar $r)use($roles,$base):void{
            $r->validator(new class($roles,$base)implements ChangeValidator{
                public function __construct(private PdoRoleRepository $roles,private PluginRuntime $base){}public function priority():int{return 0;}
                public function validate(MutationContext $c):void{if($c->operation==='media.asset.refresh'){$this->roles->saveInsideTransaction(new Role('editor',Scope::Space,['files.list','media.previews.read']),$this->base);}}
            });
        })]);
        try{$this->media($runtime)->refresh('alice','a',$id,1);self::fail('Revoked process right accepted.');}catch(\Samla\PluginApi\AccessDenied){self::assertFalse($this->pdo->inTransaction());}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_refresh_requests')->fetchColumn());self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='media.asset.refresh'")->fetchColumn());self::assertTrue($this->media()->refresh('alice','a',$id,1));
    }
    public function testCopiesReuseProjectionAndUnreferencedSourceIsNotPersisted():void
    {
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'photo.png');$this->pipeline()->processEntry($id);$copy=$this->files()->change('alice','a',$id,'copy','photo.png','',name:'copy.png');$this->pipeline()->processEntry($copy);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());self::assertSame('ready',$this->media()->details('alice','a',$copy)['media_status']);
        $pdo=$this->pdo;$runtime=$this->boot([new Extension('test.media',static function(PluginRegistrar $r)use($pdo):void{
            $r->mediaProvider(new class($pdo)implements \Samla\PluginApi\MediaProvider{
                public function __construct(private \PDO $pdo){}public function id():string{return 'test.media.purge';}public function version():string{return '1';}
                public function extract(\Samla\PluginApi\MediaSource $s):\Samla\PluginApi\MediaResult{$this->pdo->exec('DELETE FROM storage_entries');return new \Samla\PluginApi\MediaResult('unsupported');}
            });
        })]);$this->pipeline($runtime)->processEntry($id);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());
    }

    public function testRebuildPagesAreBoundedAndFanOutIntoIndividuallyProcessedIdempotentTasks():void
    {
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'source.png');$row=$this->files()->details('alice','a',$id);
        $q=$this->pdo->prepare("INSERT INTO storage_entries(id,space_id,parent_id,name,kind,blob_id,size_bytes,sha256,created_at) VALUES (?,'a','',?,'file',?,?,?,?)");
        for($i=1;$i<=104;++$i){$q->execute([str_pad(dechex($i),32,'0',STR_PAD_LEFT),'copy '.$i.'.png',$row['blob_id'],$row['size_bytes'],$row['sha256'],time()]);}
        $page=$this->media()->rebuildPlan('alice','a');self::assertCount(100,$page['plan']);self::assertNotNull($page['next']);$second=$this->media()->rebuildPlan('alice','a',$page['next']);self::assertCount(5,$second['plan']);self::assertNull($second['next']);
        $this->media()->rebuild('alice','a',new \Samla\PluginApi\MediaBatchRefreshChange($page['plan']));self::assertSame(100,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type='media.asset.refresh.completed'")->fetchColumn());self::assertSame(100,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='media.asset.refresh'")->fetchColumn());self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());self::assertSame([],$this->media()->rebuildPlan('alice','a')['plan']);
        $worker=new \Samla\Core\Background\OutboxWorker($this->pdo,$this->runtime);self::assertSame(50,$worker->run()['delivered']);self::assertSame(50,$worker->run()['delivered']);self::assertSame(1,$worker->run()['delivered']);self::assertSame(0,$worker->run()['processed']);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());
        $payload=json_decode($this->pdo->query("SELECT payload FROM outbox_events WHERE event_type='media.asset.refresh.completed' LIMIT 1")->fetchColumn(),true,16,JSON_THROW_ON_ERROR);self::assertArrayHasKey('batch_id',$payload);self::assertSame(1,$payload['requested_revision']);self::assertStringNotContainsString('source.png',json_encode($payload));
    }
    public function testRebuildRejectsMixedConflictForeignSpaceAndRecentRequestsAtomically():void
    {
        $a=$this->upload('one','one.txt');$b=$this->upload('two','two.txt');$before=(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn();
        foreach([[$a=>1,$b=>2],[$a=>1,str_repeat('e',32)=>1]]as$plan){try{$this->media()->rebuild('alice','a',new \Samla\PluginApi\MediaBatchRefreshChange($plan));self::fail('Invalid batch accepted.');}catch(\Samla\PluginApi\MutationConflict|\InvalidArgumentException){self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_refresh_requests')->fetchColumn());}}
        self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());$this->media()->refresh('alice','a',$b,1);
        try{$this->media()->rebuild('alice','a',new \Samla\PluginApi\MediaBatchRefreshChange([$a=>1,$b=>1]));self::fail('Recent request accepted.');}catch(\Samla\PluginApi\MutationConflict){self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM media_refresh_requests')->fetchColumn());}
        $this->rights(['files.list','media.previews.read']);try{$this->media()->rebuildPlan('alice','a');self::fail('Missing processing right accepted.');}catch(\Samla\PluginApi\AccessDenied){self::assertTrue(true);}
    }
    public function testRebuildControllerValidatesEntireFormBeforeWritingAndEscapesNames():void
    {
        $id=$this->upload('data','<script>.txt');$page=$this->page('/media/rebuild',$id);self::assertSame(200,$page->status);self::assertStringContainsString('&lt;script&gt;.txt',$page->body);
        $form=['plan'=>json_encode([$id=>1]),'after'=>''];self::assertSame(403,$this->page('/media/rebuild',$id,$form+['csrf'=>'bad'])->status);
        foreach([['plan'=>'broken'],['after'=>'not a cursor'],['plan'=>'[]'],['plan'=>'{"bad":1}']]as$invalid){self::assertSame(400,$this->page('/media/rebuild',$id,$invalid+$form)->status);}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_refresh_requests')->fetchColumn());self::assertSame(303,$this->page('/media/rebuild',$id,$form)->status);self::assertSame(409,$this->page('/media/rebuild',$id,$form)->status);
    }
    public function testPurgeWorkerRemovesOnlyUnreferencedDerivativesKeepingCopiesTrashAndHistory():void
    {
        $this->rights(['files.list','files.read','files.create','files.update','files.delete','files.restore','files.purge','media.previews.read','media.process']);
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::png(),'photo.png');$this->pipeline()->processEntry($id);$copy=$this->files()->change('alice','a',$id,'copy','photo.png','',name:'copy.png');
        $this->files()->change('alice','a',$id,'trash','photo.png','');self::assertSame(0,(new \Samla\Core\Media\MediaCacheCollector($this->pdo))->collect());$this->files()->purge('alice','a',$id,2);
        $worker=new \Samla\Core\Background\OutboxWorker($this->pdo,$this->runtime);self::assertSame(0,$worker->run()['errors']);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());
        $stream=fopen('php://temp','w+b');fwrite($stream,'new content');rewind($stream);try{$this->files()->replace('alice','a',$copy,1,$stream);}finally{fclose($stream);}
        self::assertSame(0,(new \Samla\Core\Media\MediaCacheCollector($this->pdo))->collect());self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
        $this->files()->change('alice','a',$copy,'trash','copy.png','');$this->files()->purge('alice','a',$copy,3);self::assertSame(0,$worker->run()['errors']);self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_assets')->fetchColumn());self::assertSame(0,(new \Samla\Core\Media\MediaCacheCollector($this->pdo))->collect());
    }

    public function testBatchValidatorRevocationAndAuditFailureCannotLeavePartialRequests():void
    {
        $id=$this->upload('one','one.txt');$proposal=new \Samla\PluginApi\MediaBatchRefreshChange([$id=>1]);$roles=$this->roles;$base=$this->runtime;
        $runtime=$this->boot([new Extension('test.media',static function(PluginRegistrar $r)use($roles,$base):void{
            $r->validator(new class($roles,$base)implements ChangeValidator{
                public function __construct(private PdoRoleRepository $roles,private PluginRuntime $base){}public function priority():int{return 0;}
                public function validate(MutationContext $c):void{if($c->mediaBatchRefresh!==null){$this->roles->saveInsideTransaction(new Role('editor',Scope::Space,['files.list']),$this->base);}}
            });
        })]);
        try{$this->media($runtime)->rebuild('alice','a',$proposal);self::fail('Revoked processing permission accepted.');}catch(\Samla\PluginApi\AccessDenied){self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_refresh_requests')->fetchColumn());}
        $this->pdo->exec("CREATE TRIGGER test_media_batch_audit BEFORE INSERT ON audit_events FOR EACH ROW SET NEW.operation=IF(NEW.operation='media.batch.refresh',NULL,NEW.operation)");
        try{try{$this->media()->rebuild('alice','a',$proposal);self::fail('Audit failure ignored.');}catch(\PDOException){self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_refresh_requests')->fetchColumn());self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type='media.asset.refresh.completed'")->fetchColumn());}}
        finally{$this->pdo->exec('DROP TRIGGER test_media_batch_audit');}
    }

    public function testSecondBatchChildFailureRollsBackFirstChildAllAuditAndRequests():void
    {
        $a=$this->upload('a','a.txt');$b=$this->upload('b','b.txt');$audit=(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();$events=(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn();
        $this->pdo->exec("CREATE TRIGGER test_media_batch_child BEFORE INSERT ON outbox_events FOR EACH ROW SET NEW.payload=IF(NEW.event_type='media.asset.refresh.completed' AND JSON_EXTRACT(NEW.payload,'$.batch_index')=1,NULL,NEW.payload)");
        try{try{$this->media()->rebuild('alice','a',new \Samla\PluginApi\MediaBatchRefreshChange([$a=>1,$b=>1]));self::fail('Partial fanout accepted.');}catch(\PDOException){self::assertFalse($this->pdo->inTransaction());}}
        finally{$this->pdo->exec('DROP TRIGGER test_media_batch_child');}
        self::assertSame($audit,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());self::assertSame($events,(int)$this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM media_refresh_requests')->fetchColumn());
    }

    public function testEmbeddedMetadataIsEscapedAndPromptFieldsNeedAdditionalCurrentPermission():void
    {
        $xml=\Samla\Tests\Fixtures\MediaImages::xmp('<dc:title>&lt;script&gt;title</dc:title><i:DigitalSourceType rdf:resource="http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia"/><i:AIPromptInformation>secret prompt</i:AIPromptInformation><i:AIPromptWriterName>secret writer</i:AIPromptWriterName>');
        $id=$this->upload(\Samla\Tests\Fixtures\MediaImages::pngText('XML:com.adobe.xmp',$xml,true),'photo.png');$this->pipeline()->processEntry($id);$page=$this->page('/media/detail',$id);self::assertSame(200,$page->status);self::assertStringContainsString('&lt;script&gt;title',$page->body);self::assertStringNotContainsString('secret prompt',$page->body);self::assertStringNotContainsString('secret writer',$page->body);self::assertStringContainsString('Als KI-generiert gekennzeichnet',$page->body);
        $this->rights(['files.list','media.metadata.read','media.sensitive.read']);$page=$this->page('/media/detail',$id);self::assertStringContainsString('secret prompt',$page->body);self::assertStringContainsString('secret writer',$page->body);self::assertStringNotContainsString('/media/preview?',$page->body);
        $this->rights(['files.list','media.previews.read']);self::assertSame([],$this->media()->details('alice','a',$id)['metadata']);self::assertStringNotContainsString('secret prompt',$this->page('/media/detail',$id)->body);
    }
    public function testReplacingContentReextractsOriginAndDoesNotReuseOldAiMarkers():void
    {
        $xml=\Samla\Tests\Fixtures\MediaImages::xmp('<i:DigitalSourceType>http://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia</i:DigitalSourceType>');$id=$this->upload(\Samla\Tests\Fixtures\MediaImages::pngText('XML:com.adobe.xmp',$xml,true),'photo.png');$this->pipeline()->processEntry($id);
        $values=array_column($this->media()->details('alice','a',$id)['metadata'],null,'field');self::assertSame('Als mit KI bearbeitet gekennzeichnet',$values['platform.media.declared_origin']['normalized']);
        $stream=fopen('php://temp','w+b');fwrite($stream,\Samla\Tests\Fixtures\MediaImages::png());rewind($stream);try{$this->files()->replace('alice','a',$id,1,$stream);}finally{fclose($stream);}$this->pipeline()->processEntry($id);
        $values=array_column($this->media()->details('alice','a',$id)['metadata'],null,'field');self::assertArrayNotHasKey('platform.media.digital_source_type',$values);self::assertSame('Unbekannt',$values['platform.media.declared_origin']['normalized']);self::assertSame(409,$this->page('/media/preview',$id)->status);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
    }
    public function testOriginalKeepsEmbeddedPrivateDataWhileProtectedPreviewStripsIt():void
    {
        $bytes=\Samla\Tests\Fixtures\MediaImages::pngText('parameters','secret prompt');$id=$this->upload($bytes,'photo.png');$this->pipeline()->processEntry($id);$preview=$this->media()->preview('alice','a',$id,1);self::assertStringNotContainsString('secret prompt',$preview);self::assertStringNotContainsString('parameters',$preview);
        $download=$this->files()->download('alice','a',$id);try{self::assertSame($bytes,stream_get_contents($download['stream']));}finally{fclose($download['stream']);}
        $values=array_column($this->media()->details('alice','a',$id)['metadata'],null,'field');self::assertSame('Unbekannt',$values['platform.media.declared_origin']['normalized']);
    }

}
