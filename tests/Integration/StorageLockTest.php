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
use Samla\Core\Storage\{FileService,LocalContentStore,StorageLocked,StorageLockService,StoragePlugin,StorageWriteGuard};
use Samla\Core\Web\{FileController,Request};
use Samla\PluginApi\{AccessDenied,ChangeValidator,MutationContext,PluginRegistrar,Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository,RbacPlugin,Role,RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class StorageLockTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identity;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private StorageWriteGuard $guard;
    private string $root;
    private int $now;
    protected function setUp(): void
    {
        $config=AppConfig::fromGlobals(); if($config->environment!=='test'){throw new \RuntimeException();}
        $this->pdo=(new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__.'/../../bootstrap/migrations.php');
        foreach(['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table){$this->pdo->exec('DELETE FROM '.$table);}
        $this->now=time();$this->identity=new PdoIdentityRepository($this->pdo,3600,fn():int=>$this->now);
        foreach(['alice','bob'] as $user){$this->identity->createUser($user,'https://id.example',$user,$user);$this->identity->replaceVerifiedGroups($user,[]);}
        $this->identity->createGroup('editors','Editors');$this->identity->addManualMembership('alice','editors');
        $this->roles=new PdoRoleRepository($this->pdo);$this->runtime=$this->boot();
        $this->roles->save(new Role('editor',Scope::Space,['files.list','files.read','files.create','files.update','files.delete','files.restore','files.versions.read']),$this->runtime);
        $this->pdo->exec("INSERT INTO storage_spaces (id,name,quota_bytes) VALUES ('a','A',1048576),('b','B',1048576)");
        $this->roles->assign(new RoleAssignment('editors','editor',Scope::Space,'a'));
        $this->root=sys_get_temp_dir().'/samla-locks-'.bin2hex(random_bytes(8));mkdir($this->root,0700);
        $this->guard=new StorageWriteGuard($this->pdo,fn():int=>$this->now);
    }
    protected function tearDown(): void
    {foreach(new \DirectoryIterator($this->root) as $f){if(!$f->isDot()){unlink($f->getPathname());}}rmdir($this->root);}
    private function boot(array $extra=[]):PluginRuntime
    {return PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin($this->roles,$this->identity),...$extra]);}
    private function files(array $tokens=[],?PluginRuntime $runtime=null):FileService
    {$runtime??=$this->runtime;return new FileService($this->pdo,new AuthorizationService($runtime,$this->identity),new PdoMutationRunner($this->pdo,$runtime),new LocalContentStore($this->root),$this->guard,$tokens);}
    private function locks(?PluginRuntime $runtime=null):StorageLockService
    {$runtime??=$this->runtime;return new StorageLockService($this->pdo,new AuthorizationService($runtime,$this->identity),new PdoMutationRunner($this->pdo,$runtime),$this->guard);}
    private function upload(string $name='file',string $parent='',array $tokens=[]):string
    {$s=fopen('php://temp','w+b');fwrite($s,'old');rewind($s);try{return $this->files($tokens)->upload('alice','a',$parent,$name,$s);}finally{fclose($s);}}
    private function replace(string $id,array $tokens=[],string $actor='alice'):void
    {$s=fopen('php://temp','w+b');fwrite($s,'new');rewind($s);try{$this->files($tokens)->replace($actor,'a',$id,1,$s);}finally{fclose($s);}}
    private function blocked(\Closure $call):void
    {$before=(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();try{$call();self::fail('Write lock ignored.');}catch(StorageLocked){self::assertFalse($this->pdo->inTransaction());}self::assertSame($before,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());}
    public function testFileLockBlocksBrowserWritesEvenForItsOwnerWithoutProof():void
    {
        $id=$this->upload();$lease=$this->locks()->acquire('alice','a',$id);
        $this->blocked(fn()=>$this->replace($id));
        $this->blocked(fn()=>$this->files()->change('alice','a',$id,'rename','file','','renamed'));
        $this->blocked(fn()=>$this->files()->change('alice','a',$id,'trash','file',''));
        $current=$this->files()->download('alice','a',$id);fclose($current['stream']);
        $this->replace($id,[$lease['token']]);self::assertSame(2,(int)$this->files()->details('alice','a',$id)['revision']);
    }
    public function testProofCannotBypassOwnerOrAuthorization():void
    {
        $id=$this->upload();$lease=$this->locks()->acquire('alice','a',$id);
        $this->identity->addManualMembership('bob','editors');$this->blocked(fn()=>$this->replace($id,[$lease['token']],'bob'));
        $this->identity->setBlocked('alice',true);
        $this->expectException(AccessDenied::class);$this->replace($id,[$lease['token']]);
    }
    public function testRecursiveLockProtectsDescendantsAndDestinationCreation():void
    {
        $folder=$this->files()->createFolder('alice','a','','Folder');$id=$this->upload('child',$folder);
        $lease=$this->locks()->acquire('alice','a',$folder,true);
        $this->blocked(fn()=>$this->replace($id));$this->blocked(fn()=>$this->upload('another',$folder));
        self::assertCount(1,$this->locks()->listing('alice','a',$id));
        $this->replace($id,[$lease['token']]);self::assertSame(2,(int)$this->files()->details('alice','a',$id)['revision']);
    }
    public function testDepthZeroCollectionProtectsMembershipButNotExistingChildContent():void
    {
        $folder=$this->files()->createFolder('alice','a','','Folder');$id=$this->upload('child',$folder);
        $this->locks()->acquire('alice','a',$folder,false);
        $this->replace($id);self::assertSame(2,(int)$this->files()->details('alice','a',$id)['revision']);
        $this->blocked(fn()=>$this->upload('another',$folder));
        $this->blocked(fn()=>$this->files()->change('alice','a',$id,'rename','child',$folder,'renamed'));
    }
    public function testLockedDescendantPreventsAncestorMoveRenameAndTrash():void
    {
        $folder=$this->files()->createFolder('alice','a','','Folder');$target=$this->files()->createFolder('alice','a','','Target');$id=$this->upload('child',$folder);
        $this->locks()->acquire('alice','a',$id);
        foreach(['rename','move','trash'] as $operation){$this->blocked(fn()=>$this->files()->change('alice','a',$folder,$operation,'Folder','',$operation==='rename'?'New':null,$target));}
    }
    public function testStableIdentifierKeepsLeaseAfterProvenRename():void
    {
        $id=$this->upload();$lease=$this->locks()->acquire('alice','a',$id);
        $this->files([$lease['token']])->change('alice','a',$id,'rename','file','','renamed');
        self::assertSame($id,$this->locks()->listing('alice','a',$id)[0]['entry_id']);
        $this->blocked(fn()=>$this->files()->change('alice','a',$id,'rename','renamed','','again'));
    }
    public function testOverlappingAcquisitionCannotUseAnotherLockOrSpace():void
    {
        $folder=$this->files()->createFolder('alice','a','','Folder');$id=$this->upload('child',$folder);$this->locks()->acquire('alice','a',$id);
        $this->blocked(fn()=>$this->locks()->acquire('alice','a',$folder,true));
        $this->locks()->acquire('alice','a',$folder,false);self::assertTrue(true);
        $this->expectException(AccessDenied::class);$this->locks()->acquire('alice','b',$id);
    }
    public function testRenewalReleaseAndExpiryArePersisted():void
    {
        $id=$this->upload();$lease=$this->locks()->acquire('alice','a',$id,seconds:10);$this->now+=5;
        $renewed=$this->locks()->renew('alice','a',$id,$lease['token'],30);self::assertSame($this->now+30,(int)$renewed['expires_at']);
        $this->now+=11;$this->blocked(fn()=>$this->replace($id));$this->locks()->release('alice','a',$id,$lease['token']);
        $lease=$this->locks()->acquire('alice','a',$id,seconds:1);$this->now+=2;$this->replace($id);self::assertSame([], $this->locks()->listing('alice','a',$id));
    }
    public function testForeignUnlockAndTrashedTargetsAreDenied():void
    {
        $id=$this->upload();$lease=$this->locks()->acquire('alice','a',$id);$this->identity->addManualMembership('bob','editors');
        try{$this->locks()->release('bob','a',$id,$lease['token']);self::fail('Foreign unlock accepted.');}catch(AccessDenied){self::assertTrue(true);}
        $this->locks()->release('alice','a',$id,$lease['token']);$this->files()->change('alice','a',$id,'trash','file','');
        $this->expectException(\InvalidArgumentException::class);$this->locks()->acquire('alice','a',$id);
    }
    public function testRootLockProtectsRootMembershipAndRecursiveChildren():void
    {
        $folder=$this->files()->createFolder('alice','a','','Folder');$lease=$this->locks()->acquire('alice','a','',true);
        $this->blocked(fn()=>$this->upload('root-file'));$this->blocked(fn()=>$this->upload('nested',$folder));
        $this->upload('proven',$folder,[$lease['token']]);self::assertTrue(true);
    }
    public function testPluginCanVetoLockAndTokensDoNotEnterAudit():void
    {
        $id=$this->upload();$runtime=$this->boot([new Extension('test.lock.veto',static function(PluginRegistrar $r):void{
            $r->validator(new class implements ChangeValidator{public function priority():int{return 0;}public function validate(MutationContext $c):void{if($c->operation==='storage.lock.acquire'){throw new AccessDenied();}}});
        })]);
        try{$this->locks($runtime)->acquire('alice','a',$id);self::fail('Veto ignored.');}catch(AccessDenied){self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());}
        $lease=$this->locks()->acquire('alice','a',$id,owner:'Private client label');$data=json_encode([$this->pdo->query('SELECT * FROM audit_events')->fetchAll(),$this->pdo->query('SELECT * FROM outbox_events')->fetchAll()]);
        self::assertStringNotContainsString($lease['token'],$data);self::assertStringNotContainsString('Private client label',$data);
    }
    public function testWebMutationReportsLockedStatus():void
    {
        $id=$this->upload();$this->locks()->acquire('alice','a',$id);$csrf=str_repeat('a',64);$controller=new FileController($this->files());
        $response=$controller->handle(new Request('POST','/files/change',post:['csrf'=>$csrf,'space'=>'a','entry'=>$id,'operation'=>'trash','expected_name'=>'file','expected_parent'=>'','parent'=>'']), 'alice',$csrf,['actor'=>'alice','csrf'=>$csrf,'caps'=>[],'flash'=>null]);
        self::assertSame(423,$response->status);self::assertStringContainsString('gesperrt',$response->body);
    }
    public function testLockInsertedByValidatorIsRecheckedBeforeTheWrite():void
    {
        $pdo=$this->pdo;$expires=$this->now+60;
        $runtime=$this->boot([new Extension('test.insert.lock',static function(PluginRegistrar $r)use($pdo,$expires):void{
            $r->validator(new class($pdo,$expires) implements ChangeValidator{
                public function __construct(private \PDO $pdo,private int $expires){}
                public function priority():int{return 0;}
                public function validate(MutationContext $c):void{
                    $q=$this->pdo->prepare("INSERT INTO storage_locks (id,token,space_id,entry_id,actor_id,recursive_lock,owner_label,expires_at) VALUES (?,?,'a','','bob',1,'',?)");
                    $q->execute([str_repeat('a',32),str_repeat('b',64),$this->expires]);
                }
            });
        })]);
        $this->blocked(fn()=>$this->files(runtime:$runtime)->createFolder('alice','a','','Blocked'));
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_locks')->fetchColumn());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
}
