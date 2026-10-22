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
use Samla\Core\Storage\{FileService,LocalContentStore,OrphanCollector,StorageLockService,StorageLocked,StoragePlugin,StorageWriteGuard};
use Samla\Core\Web\{FileController,Request};
use Samla\PluginApi\{AccessDenied,ChangeValidator,MutationConflict,MutationContext,PluginRegistrar,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin,Role,RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class FilePurgeTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identity;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private string $root;
    protected function setUp():void
    {
        $config=AppConfig::fromGlobals();if($config->environment!=='test'){throw new \RuntimeException();}
        $this->pdo=(new ConnectionFactory())->connect($config->database);(new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        $this->pdo->exec('DROP TRIGGER IF EXISTS test_purge_chunk_failure');
        foreach(['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces']as$table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->identity=new PdoIdentityRepository($this->pdo,3600);
        foreach(['alice','bob']as$user){$this->identity->createUser($user,'https://id.example',$user,$user);$this->identity->replaceVerifiedGroups($user,[]);}
        $this->identity->createGroup('editors','Editors');$this->identity->addManualMembership('alice','editors');$this->roles=new PdoRoleRepository($this->pdo);$this->runtime=$this->boot();
        $this->rights(false);$this->pdo->exec("INSERT INTO storage_spaces(id,name,quota_bytes) VALUES ('a','A',1048576),('b','B',1048576)");
        $this->roles->assign(new RoleAssignment('editors','editor',Scope::Space,'a'));
        $this->root=sys_get_temp_dir().'/samla-purge-'.bin2hex(random_bytes(8));mkdir($this->root,0700);
    }
    protected function tearDown():void
    {foreach(new \DirectoryIterator($this->root)as$f){if(!$f->isDot()){unlink($f->getPathname());}}rmdir($this->root);}
    private function boot(array $extra=[]):PluginRuntime
    {return PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin($this->roles,$this->identity),...$extra]);}
    private function rights(bool $purge=true,bool $restore=true):void
    {
        $permissions=['files.list','files.read','files.create','files.update','files.delete','files.versions.read'];
        if($restore){$permissions[]='files.restore';}if($purge){$permissions[]='files.purge';}
        $this->roles->save(new Role('editor',Scope::Space,$permissions),$this->runtime);
    }
    private function files(?PluginRuntime $runtime=null):FileService
    {$runtime??=$this->runtime;return new FileService($this->pdo,new AuthorizationService($runtime,$this->identity),new PdoMutationRunner($this->pdo,$runtime),new LocalContentStore($this->root));}
    private function upload(string $name='file',string $parent='',string $content='old'):string
    {$s=fopen('php://temp','w+b');fwrite($s,$content);rewind($s);try{return $this->files()->upload('alice','a',$parent,$name,$s);}finally{fclose($s);}}
    private function trash(string $id):int
    {$row=$this->files()->details('alice','a',$id);$this->files()->change('alice','a',$id,'trash',$row['name'],$row['parent_id']);return (int)$row['revision']+1;}
    public function testManageDoesNotImplicitlyGrantPurgeAndScopeRemainsEnforced():void
    {
        $id=$this->upload();$revision=$this->trash($id);
        try{$this->files()->purge('alice','a',$id,$revision);self::fail('Implicit purge right.');}catch(AccessDenied){self::assertTrue(true);}
        $this->rights();
        foreach([['bob','a'],['alice','b']]as[$actor,$space]){try{$this->files()->purge($actor,$space,$id,$revision);self::fail('Foreign purge.');}catch(AccessDenied){self::assertTrue(true);}}
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
    public function testActiveAndStaleEntriesCannotBePurged():void
    {
        $this->rights();$id=$this->upload();
        try{$this->files()->purge('alice','a',$id,1);self::fail('Active entry purged.');}catch(MutationConflict){self::assertTrue(true);}
        $revision=$this->trash($id);try{$this->files()->purge('alice','a',$id,$revision-1);self::fail('Stale purge.');}catch(MutationConflict){self::assertTrue(true);}
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
    public function testPurgeDropsHistoryAndQuotaButGcPreservesSharedBlob():void
    {
        $this->rights();$files=$this->files();$id=$this->upload();$s=fopen('php://temp','w+b');fwrite($s,'newer');rewind($s);
        try{$files->replace('alice','a',$id,1,$s);}finally{fclose($s);}
        $blob=$files->details('alice','a',$id)['blob_id'];$old=$this->pdo->query('SELECT blob_id FROM storage_versions')->fetchColumn();
        $copy=$files->change('alice','a',$id,'copy','file','','copy');$revision=$this->trash($id);
        $files->purge('alice','a',$id,$revision);self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
        self::assertSame(5,$files->listing('alice','a')['used']);self::assertFileExists($this->root.'/'.$old);self::assertFileExists($this->root.'/'.$blob);
        touch($this->root.'/'.$old,time()-90000);touch($this->root.'/'.$blob,time()-90000);
        (new OrphanCollector($this->pdo,new LocalContentStore($this->root),new PdoMutationRunner($this->pdo,$this->runtime)))->collect(true);
        self::assertFileDoesNotExist($this->root.'/'.$old);self::assertFileExists($this->root.'/'.$blob);
        $download=$files->download('alice','a',$copy);try{self::assertSame('newer',stream_get_contents($download['stream']));}finally{fclose($download['stream']);}
    }
    private function largeTree():array
    {
        $folder=$this->files()->createFolder('alice','a','','root');$values=[];$params=[];$ids=[$folder];
        for($i=1;$i<=500;++$i){$id=str_pad(dechex($i),32,'0',STR_PAD_LEFT);$ids[]=$id;$values[]="(?, 'a', ?, ?, 'folder', 1)";array_push($params,$id,$folder,'child.'.$i);}
        $this->pdo->prepare('INSERT INTO storage_entries(id,space_id,parent_id,name,kind,created_at) VALUES '.implode(',',$values))->execute($params);
        return [$folder,$this->trash($folder),$ids];
    }
    public function testWholeSubtreeAndBoundedOutboxChunksCommitTogether():void
    {
        $this->rights();[$folder,$revision,$ids]=$this->largeTree();$this->files()->purge('alice','a',$folder,$revision);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
        $events=$this->pdo->query("SELECT id,payload FROM outbox_events WHERE event_type='storage.entry.purge.completed' ORDER BY payload")->fetchAll();self::assertCount(2,$events);$seen=[];$eventIds=[];$batch=null;
        foreach($events as$event){$payload=json_decode($event['payload'],true);self::assertSame(2,$payload['chunk_count']);self::assertLessThan(65536,strlen($event['payload']));self::assertStringNotContainsString('child.',$event['payload']);$batch??=$payload['batch_id'];self::assertSame($batch,$payload['batch_id']);$seen=[...$seen,...$payload['purged_entries']];$eventIds[]=$event['id'];}
        sort($seen,SORT_STRING);sort($ids,SORT_STRING);self::assertSame($ids,$seen);self::assertCount(2,array_unique($eventIds));
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='storage.entry.purge'")->fetchColumn());
    }
    public function testFailureOfSecondOutboxChunkRollsBackEveryDeletedEntryAndFirstChunk():void
    {
        $this->rights();[$folder,$revision]=$this->largeTree();$before=(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        $this->pdo->exec("CREATE TRIGGER test_purge_chunk_failure BEFORE INSERT ON outbox_events FOR EACH ROW SET NEW.payload = IF(NEW.event_type='storage.entry.purge.completed' AND JSON_EXTRACT(NEW.payload,'$.chunk_index')=1,NULL,NEW.payload)");
        try{try{$this->files()->purge('alice','a',$folder,$revision);self::fail('Chunk failure ignored.');}catch(\PDOException){self::assertFalse($this->pdo->inTransaction());}}
        finally{$this->pdo->exec('DROP TRIGGER test_purge_chunk_failure');}
        self::assertSame(501,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type='storage.entry.purge.completed'")->fetchColumn());
    }
    public function testValidatorChangingADescendantInvalidatesTheEntirePlan():void
    {
        $this->rights();$folder=$this->files()->createFolder('alice','a','','root');$child=$this->upload('child',$folder);$revision=$this->trash($folder);$pdo=$this->pdo;
        $extension=new Extension('test.purge_race',static function(PluginRegistrar $r)use($pdo,$child):void{
            $r->validator(new class($pdo,$child) implements ChangeValidator{
                public function __construct(private \PDO $pdo,private string $child){}
                public function priority():int{return 0;}
                public function validate(MutationContext $context):void{if($context->storagePurge!==null){$this->pdo->prepare('UPDATE storage_entries SET revision=revision+1 WHERE id=?')->execute([$this->child]);}}
            });
        });
        $before=(int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
        try{$this->files($this->boot([$extension]))->purge('alice','a',$folder,$revision);self::fail('Stale subtree accepted.');}catch(MutationConflict){self::assertFalse($pdo->inTransaction());}
        self::assertSame(2,(int)$pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());self::assertSame(1,(int)$pdo->query("SELECT revision FROM storage_entries WHERE id='".$child."'")->fetchColumn());self::assertSame($before,(int)$pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testExplicitWebConfirmationCsrfAndSeparatePurgeOnlyUi():void
    {
        $id=$this->upload('<script>');$revision=$this->trash($id);$this->roles->save(new Role('editor',Scope::Space,['files.list','files.purge']),$this->runtime);$controller=new FileController($this->files());$csrf=str_repeat('a',64);$data=['actor'=>'alice','csrf'=>$csrf,'caps'=>[],'flash'=>null];
        self::assertSame(405,$controller->handle(new Request('GET','/files/purge'),'alice',$csrf,$data)->status);
        $page=$controller->handle(new Request('GET','/files/trash',query:['space'=>'a']),'alice',$csrf,$data);self::assertSame(200,$page->status);self::assertStringContainsString('/files/purge',$page->body);self::assertStringNotContainsString('Wiederherstellen',$page->body);self::assertStringContainsString('&lt;script&gt;',$page->body);
        $post=['space'=>'a','entry'=>$id,'parent'=>'','revision'=>(string)$revision,'confirm'=>'delete','csrf'=>'bad'];
        self::assertSame(403,$controller->handle(new Request('POST','/files/purge',post:$post),'alice',$csrf,$data)->status);$post['csrf']=$csrf;unset($post['confirm']);
        self::assertSame(400,$controller->handle(new Request('POST','/files/purge',post:$post),'alice',$csrf,$data)->status);$post['confirm']='delete';
        self::assertSame(303,$controller->handle(new Request('POST','/files/purge',post:$post),'alice',$csrf,$data)->status);self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
    public function testActiveRetainedLeaseBlocksPurgeUntilExpiry():void
    {
        $this->rights();$id=$this->upload();$locks=new StorageLockService($this->pdo,new AuthorizationService($this->runtime,$this->identity),new PdoMutationRunner($this->pdo,$this->runtime),new StorageWriteGuard($this->pdo));
        $lease=$locks->acquire('alice','a',$id);
        $this->pdo->prepare('UPDATE storage_entries SET trashed_at=?,revision=2 WHERE id=?')->execute([time(),$id]);
        try{$this->files()->purge('alice','a',$id,2);self::fail('Retained lock ignored.');}catch(StorageLocked){self::assertFalse($this->pdo->inTransaction());}
        $this->pdo->prepare('UPDATE storage_locks SET expires_at=1 WHERE id=?')->execute([$lease['id']]);$this->files()->purge('alice','a',$id,2);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
    public function testAlreadyTrashedChildCanBePurgedWithoutRestoringItsTrashedParent():void
    {
        $this->rights();$parent=$this->files()->createFolder('alice','a','','folder');$child=$this->upload('child',$parent);$revision=$this->trash($child);$this->trash($parent);
        $this->files()->purge('alice','a',$child,$revision);
        self::assertSame(1,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
        self::assertSame([$parent],array_column($this->files()->trashListing('alice','a')['rows'],'id'));
    }
}
