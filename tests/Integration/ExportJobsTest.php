<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;
use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Background\{OutboxWorker,WorkerPlugin};
use Samla\Core\Configuration\{AppConfig,ExportConfig};
use Samla\Core\Database\{ConnectionFactory,MigrationRunner};
use Samla\Core\Export\{ExportCollector,ExportPlugin,ExportService,ZipArchiveStore};
use Samla\Core\Identity\{IdentityPlugin,PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService,LocalContentStore,StoragePlugin};
use Samla\Core\Web\{ExportController,Request};
use Samla\PluginApi\{AccessDenied,ChangeValidator,ExportMetadataProvider,ExportMetadataValue,ExportSource,MediaField,MutationConflict,MutationContext,OutboxEvent,Permission,PluginRegistrar,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin,Role,RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class ExportJobsTest extends TestCase
{
    private \PDO $pdo;private PdoIdentityRepository $identity;private PdoRoleRepository $roles;private PluginRuntime $runtime;
    private ExportService $exports;private FileService $files;private ZipArchiveStore $store;private string $root;private string $csrf;
    protected function setUp():void
    {
        $config=AppConfig::fromGlobals();if($config->environment!=='test'){throw new \RuntimeException('Isolated test DB required.');}
        $this->pdo=(new ConnectionFactory())->connect($config->database);(new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        foreach(['outbox_deliveries','outbox_subscribers','outbox_events','audit_events','export_jobs','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces']as$table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->identity=new PdoIdentityRepository($this->pdo,3600);$this->roles=new PdoRoleRepository($this->pdo);
        foreach(['alice','bob']as$user){$this->identity->createUser($user,'https://id.example',$user,ucfirst($user));$this->identity->replaceVerifiedGroups($user,[]);}
        $this->identity->createGroup('team','Team');foreach(['alice','bob']as$user){$this->identity->addManualMembership($user,'team');}
        $this->pdo->exec("INSERT INTO storage_spaces(id,name,quota_bytes) VALUES('space','Dateien <Team>',1000000000),('other','Andere Dateien',1000000000)");
        $this->root=sys_get_temp_dir().'/samla-export-jobs-'.bin2hex(random_bytes(10));mkdir($this->root,0700);$this->csrf=str_repeat('a',64);$this->boot();
        $this->roles->save(new Role('file.operator',Scope::Space,['files.list','files.read','files.create','files.update','files.delete','files.restore','files.export']),$this->runtime);
        $this->roles->assign(new RoleAssignment('team','file.operator',Scope::Space,'space'));
    }
    protected function tearDown():void
    {
        $it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as$file){if($file->isDir()&&!$file->isLink()){rmdir($file->getPathname());}else{unlink($file->getPathname());}}rmdir($this->root);
    }
    private function boot(array $extensions=[],?ExportConfig $config=null):void
    {
        $config??=new ExportConfig();$this->store=new ZipArchiveStore($this->root,$config);
        $exports=new ExportPlugin(fn(OutboxEvent $event)=>$this->exports->handle($event),fn()=>(new ExportCollector($this->pdo,$this->store))->collect());
        $this->runtime=PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new WorkerPlugin(),new RbacPlugin($this->roles,$this->identity),$exports,...$extensions]);
        $auth=new AuthorizationService($this->runtime,$this->identity);$mutations=new PdoMutationRunner($this->pdo,$this->runtime);
        $this->files=new FileService($this->pdo,$auth,$mutations,new LocalContentStore($this->root,200000000));
        $this->exports=new ExportService($this->pdo,$this->files,$auth,$this->identity,$mutations,$this->runtime,$this->store,$config);
    }
    private function file(string $name='hello.txt',string $bytes='hello'):string
    {$s=fopen('php://temp','w+b');fwrite($s,$bytes);rewind($s);try{return $this->files->upload('alice','space','',$name,$s);}finally{fclose($s);}}
    private function requestJob(string $entry):string
    {return $this->exports->request('alice','space',[$entry=>(int)$this->files->details('alice','space',$entry)['revision']]);}
    private function job(string $id):array
    {$q=$this->pdo->prepare('SELECT * FROM export_jobs WHERE id=?');$q->execute([$id]);return $q->fetch()?:[];}
    private function deliver(string $id):void
    {$this->exports->handle(new OutboxEvent(str_repeat('a',32),'exports.request.completed',time(),['id'=>str_repeat('a',32),'targets'=>['export'=>$id]]));}
    public function testWorkerCreatesRealPrivatePackageAndRepeatedDeliveryIsIdempotent():void
    {
        $entry=$this->file();$id=$this->requestJob($entry);self::assertSame('pending',$this->job($id)['status']);
        $worker=new OutboxWorker($this->pdo,$this->runtime);self::assertSame(0,$worker->run()['errors']);self::assertSame('ready',$this->job($id)['status']);
        $revision=$this->job($id)['revision'];$sha=$this->job($id)['archive_sha256'];$this->deliver($id);self::assertSame($revision,$this->job($id)['revision']);self::assertSame($sha,$this->job($id)['archive_sha256']);
        $download=$this->exports->download('alice',$id);self::assertSame($sha,hash('sha256',stream_get_contents($download['stream'])));fclose($download['stream']);
        $zip=new \ZipArchive();$zip->open($this->root.'/.exports/'.$id.'.zip');$manifest=json_decode($zip->getFromName('manifest.json'),true,32,JSON_THROW_ON_ERROR);
        self::assertSame($entry,$manifest['files'][0]['entry_id']);self::assertSame('hello',$zip->getFromName($manifest['files'][0]['archive_path']));$zip->close();
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='exports.request'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type='exports.request.completed'")->fetchColumn());
    }
    public function testOtherOwnerCannotListDownloadOrRemoveEvenWithReadAndExportRights():void
    {
        $entry=$this->file();$id=$this->requestJob($entry);$this->deliver($id);self::assertSame([],$this->exports->listing('bob'));
        foreach([fn()=>$this->exports->download('bob',$id),fn()=>$this->exports->change('bob',$id,(int)$this->job($id)['revision'],'remove')]as$call){try{$call();self::fail('Expected owner guard.');}catch(AccessDenied){self::assertTrue(true);}}
        self::assertSame('ready',$this->job($id)['status']);
    }
    public function testSourceChangesOrRevokedAccessPreventWorkAndReadyDownloads():void
    {
        $entry=$this->file();$id=$this->requestJob($entry);$this->deliver($id);
        $this->identity->setBlocked('alice',true);try{$this->exports->download('alice',$id);self::fail('Expected blocked access.');}catch(AccessDenied){self::assertTrue(true);}$this->identity->setBlocked('alice',false);
        $this->pdo->exec("DELETE FROM rbac_assignments WHERE group_id='team'");try{$this->exports->download('alice',$id);self::fail('Expected revoked access.');}catch(AccessDenied){self::assertTrue(true);}
        $this->roles->assign(new RoleAssignment('team','file.operator',Scope::Space,'space'));
        $s=fopen('php://temp','w+b');fwrite($s,'changed');rewind($s);$this->files->replace('alice','space',$entry,1,$s);fclose($s);
        try{$this->exports->download('alice',$id);self::fail('Expected source version conflict.');}catch(MutationConflict){self::assertTrue(true);}
        $next=$this->requestJob($entry);$this->pdo->exec("DELETE FROM rbac_assignments WHERE group_id='team'");$this->deliver($next);
        self::assertSame('failed',$this->job($next)['status']);self::assertSame('AccessChanged',$this->job($next)['error_code']);self::assertFileDoesNotExist($this->root.'/.exports/'.$next.'.zip');
    }
    public function testExpiredAndCancelledArtifactsAreCollectedByAnOtherwiseIdleWorker():void
    {
        $entry=$this->file();$id=$this->requestJob($entry);$this->deliver($id);$this->pdo->prepare('UPDATE export_jobs SET expires_at=1 WHERE id=?')->execute([$id]);
        try{$this->exports->download('alice',$id);self::fail('Expected expiry.');}catch(MutationConflict){self::assertTrue(true);}
        $worker=new OutboxWorker($this->pdo,$this->runtime);self::assertSame(0,$worker->run()['errors']);self::assertSame([],$this->job($id));self::assertFileDoesNotExist($this->root.'/.exports/'.$id.'.zip');self::assertFileDoesNotExist($this->root.'/.exports/'.$id.'.lock');
        $id=$this->requestJob($entry);$this->deliver($id);$this->exports->change('alice',$id,(int)$this->job($id)['revision'],'remove');self::assertSame([],$this->job($id));
        self::assertFileExists($this->root.'/'.$this->files->details('alice','space',$entry)['blob_id']);self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
    public function testReservationOwnerCapacityAndSelectionRevisionsAreEnforcedAtomically():void
    {
        $entry=$this->file();$this->boot(config:new ExportConfig(maxBytes:4));try{$this->requestJob($entry);self::fail('Expected source size bound.');}catch(\LengthException){self::assertTrue(true);}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM export_jobs')->fetchColumn());
        $this->boot(config:new ExportConfig(reservedBytes:1));try{$this->requestJob($entry);self::fail('Expected disk reservation bound.');}catch(\LengthException){self::assertTrue(true);}
        $this->boot();try{$this->exports->request('alice','space',[$entry=>2]);self::fail('Expected exact source revision.');}catch(MutationConflict){self::assertTrue(true);}
        for($i=0;$i<5;++$i){$this->requestJob($entry);}try{$this->requestJob($entry);self::fail('Expected bounded owner queue.');}catch(\LengthException){self::assertTrue(true);}
        self::assertSame(5,(int)$this->pdo->query('SELECT COUNT(*) FROM export_jobs')->fetchColumn());
    }
    public function testPluginVetoLeavesNoJobOrReservationAndChangedProvidersInvalidatePackages():void
    {
        $entry=$this->file();$this->boot([new Extension('example.policy',static function(PluginRegistrar $r):void{$r->validator(new class implements ChangeValidator{
            public function validate(MutationContext $context):void{if($context->exportChange!==null){throw new AccessDenied();}}
        });})]);
        try{$this->requestJob($entry);self::fail('Expected export proposal veto.');}catch(AccessDenied){self::assertTrue(true);}self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM export_jobs')->fetchColumn());
        $this->boot();$id=$this->requestJob($entry);$this->deliver($id);
        $this->boot([new Extension('example.metadata',static function(PluginRegistrar $r):void{$r->exportProvider(new class implements ExportMetadataProvider{
            public function id():string{return 'example.metadata.extra';}public function version():string{return '1';}public function metadata(ExportSource $source):array{return [];}
        });})]);
        try{$this->exports->download('alice',$id);self::fail('Expected changed provider signature.');}catch(MutationConflict){self::assertTrue(true);}
    }
    public function testFailedSourceCanBeRetriedExplicitlyAndCorruptedArchivesAreNeverServed():void
    {
        $entry=$this->file();$id=$this->requestJob($entry);$blob=$this->files->details('alice','space',$entry)['blob_id'];rename($this->root.'/'.$blob,$this->root.'/held');$this->deliver($id);
        self::assertSame('failed',$this->job($id)['status']);rename($this->root.'/held',$this->root.'/'.$blob);
        $revision=(int)$this->job($id)['revision'];$this->exports->change('alice',$id,$revision,'retry');try{$this->exports->change('alice',$id,$revision,'retry');self::fail('Expected stale retry rejection.');}catch(MutationConflict){self::assertTrue(true);}
        $this->deliver($id);self::assertSame('ready',$this->job($id)['status']);$path=$this->root.'/.exports/'.$id.'.zip';$s=fopen($path,'r+b');fwrite($s,'evil');fclose($s);
        try{$this->exports->download('alice',$id);self::fail('Expected archive hash mismatch.');}catch(\RuntimeException){self::assertTrue(true);}
    }
    public function testHttpControllerRejectsTamperingAndStreamsAuthenticatedZipWithoutExternalNavigation():void
    {
        $entry=$this->file();$controller=new ExportController($this->exports);$base=['actor'=>'alice','csrf'=>$this->csrf,'caps'=>[],'flash'=>null,'oidc'=>true];
        $post=['csrf'=>$this->csrf,'space'=>'space','entries'=>[$entry.':1']];
        self::assertSame(403,$controller->handle(new Request('POST','/exports/create',post:array_replace($post,['csrf'=>'bad'])),'alice',$this->csrf,$base)->status);
        foreach([['entries'=>[$entry.':1',$entry.':1']],['entries'=>'invalid'],['entries'=>[['nested']]],['entries'=>[$entry.':0']]]as$override){self::assertSame(400,$controller->handle(new Request('POST','/exports/create',post:array_replace($post,$override)),'alice',$this->csrf,$base)->status);}
        self::assertSame(303,$controller->handle(new Request('POST','/exports/create',post:$post),'alice',$this->csrf,$base)->status);$id=$this->exports->listing('alice')[0]['id'];$this->deliver($id);
        $response=$controller->handle(new Request('GET','/exports/download',['export'=>$id]),'alice',$this->csrf,$base);self::assertSame(200,$response->status);self::assertSame('application/zip',$response->headers['Content-Type']);self::assertStringStartsWith('attachment;',$response->headers['Content-Disposition']);
        ob_start();($response->stream)();$bytes=ob_get_clean();self::assertSame((int)$response->headers['Content-Length'],strlen($bytes));self::assertStringStartsWith('PK',$bytes);
        self::assertSame(405,$controller->handle(new Request('GET','/exports/remove'),'alice',$this->csrf,$base)->status);
        self::assertSame(200,$controller->handle(new Request('GET','/exports'),'alice',$this->csrf,$base)->status);
    }
    public function testExportedMetadataPoliciesAreRecheckedAndSensitiveManifestFieldsNeedSeparateExportGrant():void
    {
        $this->boot([new Extension('example.export',static function(PluginRegistrar $r):void{
            foreach(['read','secret']as$p){$r->permission(new Permission('example.export.'.$p,'example.export',[Scope::Space]));}
            $r->exportProvider(new class implements ExportMetadataProvider{
                public function id():string{return 'example.export.fields';}public function version():string{return '1';}
                public function metadata(ExportSource $source):array{return [
                    new ExportMetadataValue(new MediaField('example.export.title','Titel',readPermission:'example.export.read',exportable:true),'Öffentlich'),
                    new ExportMetadataValue(new MediaField('example.export.prompt','Prompt',sensitive:true,readPermission:'example.export.secret',exportable:true),'Privater Prompt'),
                ];}
            });
        })]);
        $this->roles->save(new Role('metadata',Scope::Space,['example.export.read','example.export.secret']),$this->runtime);$this->roles->assign(new RoleAssignment('team','metadata',Scope::Space,'space'));
        $entry=$this->file();$id=$this->requestJob($entry);$this->deliver($id);
        $zip=new \ZipArchive();$zip->open($this->root.'/.exports/'.$id.'.zip');$manifest=$zip->getFromName('manifest.json');self::assertStringContainsString('Öffentlich',$manifest);self::assertStringNotContainsString('Privater Prompt',$manifest);$zip->close();
        $this->roles->save(new Role('metadata',Scope::Space,['example.export.secret']),$this->runtime);
        try{$this->exports->download('alice',$id);self::fail('Expected revoked field policy.');}catch(AccessDenied){self::assertTrue(true);}
        $this->roles->save(new Role('metadata',Scope::Space,['example.export.read','example.export.secret','files.export.sensitive']),$this->runtime);
        $next=$this->requestJob($entry);$this->deliver($next);$zip=new \ZipArchive();$zip->open($this->root.'/.exports/'.$next.'.zip');self::assertStringContainsString('Privater Prompt',$zip->getFromName('manifest.json'));$zip->close();
        $this->roles->save(new Role('metadata',Scope::Space,['example.export.read','example.export.secret']),$this->runtime);
        try{$this->exports->download('alice',$next);self::fail('Expected revoked sensitive export.');}catch(AccessDenied){self::assertTrue(true);}
    }
    public function testFailedHousekeepingRollsBackOpenTransactionsAndDoesNotSkipExportDeliveries():void
    {
        $pdo=$this->pdo;$this->boot([new Extension('example.tasks',static function(PluginRegistrar $r)use($pdo):void{
            $r->workerTask(new \Samla\PluginApi\WorkerTask('example.tasks.fail',static function()use($pdo):void{$pdo->beginTransaction();$pdo->exec("UPDATE storage_spaces SET name='must rollback' WHERE id='space'");}));
        })]);
        $entry=$this->file();$id=$this->requestJob($entry);$result=(new OutboxWorker($this->pdo,$this->runtime))->run();
        self::assertSame(1,$result['errors']);self::assertSame('ready',$this->job($id)['status']);self::assertFalse($this->pdo->inTransaction());
        self::assertSame('Dateien <Team>',$pdo->query("SELECT name FROM storage_spaces WHERE id='space'")->fetchColumn());
    }

}
