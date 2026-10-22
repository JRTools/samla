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

final class TextEditorTest extends TestCase
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
        foreach(['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table){$this->pdo->exec('DELETE FROM '.$table);}
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
    {return PluginRuntime::boot([new IdentityPlugin(),new StoragePlugin(),new RbacPlugin($this->roles,$this->identity),...$extra]);}
    private function rights(array $permissions=['files.list','files.read','files.create','files.update','files.versions.read']): void
    {$this->roles->save(new Role('editor',Scope::Space,$permissions),$this->runtime);}
    private function files(?PluginRuntime $runtime=null): FileService
    {$runtime??=$this->runtime;return new FileService($this->pdo,new AuthorizationService($runtime,$this->identity),new PdoMutationRunner($this->pdo,$runtime),new LocalContentStore($this->root));}
    private function upload(string $text='initial',string $name='notes.md'): string
    {$s=fopen('php://temp','w+b');fwrite($s,$text);rewind($s);try{return $this->files()->upload('alice','a','',$name,$s);}finally{fclose($s);}}
    private function request(string $path,string $entry,?array $post=null,string $actor='alice'): \Samla\Core\Web\Response
    {
        return (new FileController($this->files()))->handle(new Request($post===null?'GET':'POST',$path,query:['space'=>'a','entry'=>$entry],post:$post===null?[]:$post+['space'=>'a','entry'=>$entry,'revision'=>'1','csrf'=>$this->csrf]),$actor,$this->csrf,['actor'=>$actor,'csrf'=>$this->csrf,'caps'=>[],'flash'=>null]);
    }
    public function testSourceIsEscapedReadonlyAndCannotBeSavedWithoutUpdate(): void
    {
        $id=$this->upload("\n</textarea><script>alert(1)</script>");$this->rights(['files.list','files.read']);
        $page=$this->request('/files/edit',$id);self::assertSame(200,$page->status);self::assertStringContainsString(' readonly',$page->body);self::assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;',$page->body);self::assertStringNotContainsString('<script>',$page->body);
        self::assertSame(403,$this->request('/files/edit/save',$id,['text'=>'not allowed'])->status);
        self::assertSame(403,$this->request('/files/edit',$id,actor:'bob')->status);
        $this->rights(['files.list','files.update']);self::assertSame(403,$this->request('/files/edit',$id)->status);self::assertSame(403,$this->request('/files/edit/save',$id,['text'=>'blind overwrite'])->status);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
    }
    public function testSaveCreatesHistoryAndConcurrentDraftCanBeRecoveredWithoutOverwriting(): void
    {
        $id=$this->upload();$response=$this->request('/files/edit/save',$id,['text'=>"# First\r\nLine"]);self::assertSame(303,$response->status);
        $download=$this->files()->download('alice','a',$id);try{self::assertSame("# First\nLine",stream_get_contents($download['stream']));}finally{fclose($download['stream']);}
        $draft='</textarea>my unsaved draft';$conflict=$this->request('/files/edit/save',$id,['text'=>$draft]);self::assertSame(409,$conflict->status);self::assertStringContainsString('&lt;/textarea&gt;my unsaved draft',$conflict->body);self::assertStringContainsString('/files/edit/draft',$conflict->body);
        $copy=$this->request('/files/edit/draft',$id,['text'=>$draft]);self::assertSame(200,$copy->status);self::assertSame($draft,$copy->body);self::assertSame('attachment; filename="samla-entwurf.txt"',$copy->headers['Content-Disposition']);
        $versions=$this->files()->versions('alice','a',$id);self::assertCount(1,$versions['versions']);
        $old=$this->files()->downloadVersion('alice','a',$id,$versions['versions'][0]['id']);try{self::assertSame('initial',stream_get_contents($old['stream']));}finally{fclose($old['stream']);}
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type='storage.file.replace.completed'")->fetchColumn());
    }
    public function testInvalidFormatEncodingControlsAndOversizeNeverChangeContent(): void
    {
        foreach([['binary','picture.png',400],["\xff",'bad.txt',400],["a\0b",'nul.txt',400],[str_repeat('a',65537),'large.md',413]] as [$text,$name,$status]){
            $id=$this->upload($text,$name);self::assertSame($status,$this->request('/files/edit',$id)->status);
        }
        $id=$this->upload();foreach(["\xff","a\0b",str_repeat('a',65537)] as $text){self::assertContains($this->request('/files/edit/save',$id,['text'=>$text])->status,[400,413]);}
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
    }
    public function testCsrfAndMethodRulesProtectBothSavingAndDraftDownload(): void
    {
        $id=$this->upload();foreach(['/files/edit/save','/files/edit/draft'] as $path){self::assertSame(405,$this->request($path,$id)->status);self::assertSame(403,$this->request($path,$id,['csrf'=>'bad','text'=>'draft'])->status);}
        self::assertSame(405,$this->request('/files/edit',$id,['text'=>'draft'])->status);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
    }
    public function testLockAndQuotaFailuresRetainDraftWithoutProducingAVersion(): void
    {
        $id=$this->upload();$locks=new StorageLockService($this->pdo,new AuthorizationService($this->runtime,$this->identity),new PdoMutationRunner($this->pdo,$this->runtime),new StorageWriteGuard($this->pdo));
        $lease=$locks->acquire('alice','a',$id);$response=$this->request('/files/edit/save',$id,['text'=>'retained draft']);self::assertSame(423,$response->status);self::assertStringContainsString('retained draft',$response->body);
        $locks->release('alice','a',$id,$lease['token']);$this->pdo->exec('UPDATE storage_spaces SET quota_bytes=7');
        $response=$this->request('/files/edit/save',$id,['text'=>'retained draft']);self::assertSame(413,$response->status);self::assertStringContainsString('retained draft',$response->body);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());
    }
    public function testReadRevokedByAValidatorIsRecheckedBeforeVersionCommit(): void
    {
        $id=$this->upload();$pdo=$this->pdo;$roles=$this->roles;$runtime=$this->runtime;
        $extension=new Extension('test.editor_read_revoke',static function(PluginRegistrar $r)use($roles,$runtime):void{
            $r->validator(new class($roles,$runtime) implements ChangeValidator{
                public function __construct(private PdoRoleRepository $roles,private PluginRuntime $runtime){}
                public function priority():int{return 0;}
                public function validate(MutationContext $context):void{if($context->operation==='storage.file.replace'){$this->roles->saveInsideTransaction(new Role('editor',Scope::Space,['files.list','files.update']),$this->runtime);}}
            });
        });
        try{(new TextEditorService($this->files($this->boot([$extension]))))->save('alice','a',$id,1,'must rollback');self::fail('Revoked read accepted.');}catch(\Samla\PluginApi\AccessDenied){self::assertFalse($pdo->inTransaction());}
        self::assertSame(0,(int)$pdo->query('SELECT COUNT(*) FROM storage_versions')->fetchColumn());self::assertSame(1,(int)$pdo->query('SELECT revision FROM storage_entries')->fetchColumn());
    }
    public function testMaximumSizeTextareaCrLfSubmissionAndEmptySave(): void
    {
        $id=$this->upload();$response=$this->request('/files/edit/save',$id,['text'=>str_repeat("\r\n",65536)]);self::assertSame(303,$response->status);
        self::assertSame(65536,(int)$this->files()->details('alice','a',$id)['size_bytes']);
        self::assertSame(303,$this->request('/files/edit/save',$id,['revision'=>'2','text'=>''])->status);self::assertSame(0,(int)$this->files()->details('alice','a',$id)['size_bytes']);
    }
}
