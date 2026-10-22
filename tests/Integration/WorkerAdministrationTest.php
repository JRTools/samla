<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\{FileService, LocalContentStore, OrphanCollector, StoragePlugin};
use Samla\Core\Web\{FileController, Request};
use Samla\PluginApi\{AccessDenied, ChangeValidator, MutationConflict, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class WorkerAdministrationTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identities;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private string $root;
    protected function setUp(): void
    {
        $config = AppConfig::fromGlobals();
        if ($config->environment !== 'test') { throw new \RuntimeException('Isolated test database required.'); }
        $this->pdo = (new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__ . '/../../bootstrap/migrations.php');
        foreach (['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table) { $this->pdo->exec('DELETE FROM ' . $table); }
        $this->identities = new PdoIdentityRepository($this->pdo, 3600);
        foreach (['alice', 'bob'] as $user) { $this->identities->createUser($user, 'https://id.example', $user, $user); $this->identities->replaceVerifiedGroups($user, []); }
        $this->identities->createGroup('editors', 'Editors'); $this->identities->addManualMembership('alice', 'editors');
        $this->roles = new PdoRoleRepository($this->pdo); $this->runtime = $this->boot();
        $this->pdo->exec("INSERT INTO storage_spaces (id, name, quota_bytes) VALUES ('a', 'Media', 100), ('b', 'Private', 100)");
        $this->roles->save(new Role('editor', Scope::Space, ['files.list','files.read','files.create','files.update','files.delete','files.restore','files.versions.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('editors', 'editor', Scope::Space, 'a'));
        $this->root = sys_get_temp_dir() . '/samla-files-' . bin2hex(random_bytes(8)); mkdir($this->root, 0700);
    }
    protected function tearDown(): void
    {
        foreach (new \DirectoryIterator($this->root) as $file) { if (!$file->isDot()) { unlink($file->getPathname()); } }
        rmdir($this->root);
    }
    private function boot(array $extra = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($this->roles, $this->identities), new \Samla\Core\Background\WorkerPlugin(),new \Samla\Core\Background\ReceiptPlugin($this->pdo), ...$extra]); }
    private function service(?PluginRuntime $runtime = null): FileService
    {
        $runtime ??= $this->runtime;
        return new FileService($this->pdo, new AuthorizationService($runtime, $this->identities), new PdoMutationRunner($this->pdo, $runtime), new LocalContentStore($this->root));
    }
    private function upload(string $name, string $content, string $parent = '', ?FileService $service = null): string
    {
        $stream = fopen('php://temp', 'w+b'); fwrite($stream, $content); rewind($stream);
        try { return ($service ?? $this->service())->upload('alice', 'a', $parent, $name, $stream); } finally { fclose($stream); }
    }
    private function rights(bool $retry=true):void
    {
        $permissions=['operations.worker.read'];if($retry){$permissions[]='operations.worker.retry';}
        $this->roles->save(new Role('worker-admin',Scope::Global,$permissions),$this->runtime);$this->roles->assign(new RoleAssignment('editors','worker-admin',Scope::Global));
    }
    private function admin(?PluginRuntime $runtime=null):\Samla\Core\Background\WorkerAdministration
    {$runtime??=$this->runtime;return new \Samla\Core\Background\WorkerAdministration($this->pdo,new AuthorizationService($runtime,$this->identities),new PdoMutationRunner($this->pdo,$runtime),$runtime);}
    private function failed():array
    {
        $this->upload('file.txt','test');(new \Samla\Core\Background\OutboxWorker($this->pdo,$this->runtime))->run();
        $this->pdo->exec("UPDATE outbox_deliveries SET status='failed',attempts=8,revision=revision+1");
        return $this->pdo->query('SELECT subscriber_id,event_id,revision FROM outbox_deliveries LIMIT 1')->fetch();
    }
    public function testExplicitRightsAndCurrentIdentityProtectDiagnostics():void
    {
        $this->failed();try{$this->admin()->listing('alice');self::fail('Implicit administration right.');}catch(AccessDenied){self::assertTrue(true);}
        $this->rights(false);$listing=$this->admin()->listing('alice');self::assertCount(1,$listing['deliveries']);self::assertFalse($listing['can_retry']);self::assertArrayNotHasKey('payload',$listing['deliveries'][0]);
        foreach(['bob','alice']as$actor){if($actor==='alice'){$this->pdo->exec("UPDATE identity_users SET status='blocked' WHERE id='alice'");}try{$this->admin()->listing($actor);self::fail('Unauthorized status access.');}catch(AccessDenied){self::assertTrue(true);}}
    }
    public function testRetryIsRevisionBoundAuditedAndDoesNotRunTheHandler():void
    {
        $row=$this->failed();$this->rights();self::assertTrue($this->admin()->retry('alice',$row['subscriber_id'],$row['event_id'],(int)$row['revision']));
        $current=$this->pdo->query('SELECT status,attempts,revision FROM outbox_deliveries LIMIT 1')->fetch();self::assertSame('pending',$current['status']);self::assertSame(0,(int)$current['attempts']);self::assertSame((int)$row['revision']+1,(int)$current['revision']);
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='operations.delivery.retry'")->fetchColumn());
        self::assertSame(1,(int)$this->pdo->query("SELECT COUNT(*) FROM outbox_events WHERE event_type='operations.delivery.retry.completed'")->fetchColumn());
        try{$this->admin()->retry('alice',$row['subscriber_id'],$row['event_id'],(int)$row['revision']);self::fail('Stale retry accepted.');}catch(MutationConflict){self::assertTrue(true);}
        // A new failed cycle also cannot be retried by the old form.
        $this->pdo->exec("UPDATE outbox_deliveries SET status='failed',attempts=8,revision=revision+1");
        try{$this->admin()->retry('alice',$row['subscriber_id'],$row['event_id'],(int)$row['revision']);self::fail('ABA retry accepted.');}catch(MutationConflict){self::assertTrue(true);}
    }
    public function testReadOnlyAndInactiveSubscriberCannotBeRetried():void
    {
        $row=$this->failed();$this->rights(false);try{$this->admin()->retry('alice',$row['subscriber_id'],$row['event_id'],(int)$row['revision']);self::fail('Read-only retry.');}catch(AccessDenied){self::assertTrue(true);}
        $this->rights();$disabled=PluginRuntime::boot([new IdentityPlugin(),new \Samla\Core\Background\WorkerPlugin(),new RbacPlugin($this->roles,$this->identities)]);
        self::assertFalse($this->admin($disabled)->listing('alice')['deliveries'][0]['active']);
        try{$this->admin($disabled)->retry('alice',$row['subscriber_id'],$row['event_id'],(int)$row['revision']);self::fail('Disabled subscriber retried.');}catch(AccessDenied){self::assertTrue(true);}
    }
    public function testValidatorReadRevocationRollsBackTheRetryAndItsOwnChange():void
    {
        $row=$this->failed();$this->rights();$roles=$this->roles;$runtime=$this->runtime;
        $extension=new Extension('test.worker_revoke',static function(PluginRegistrar $r)use($roles,$runtime):void{
            $r->validator(new class($roles,$runtime)implements ChangeValidator{
                public function __construct(private PdoRoleRepository $roles,private PluginRuntime $runtime){}
                public function priority():int{return 0;}
                public function validate(MutationContext $c):void{if($c->outboxRetry!==null){$this->roles->saveInsideTransaction(new Role('worker-admin',Scope::Global,['operations.worker.retry']),$this->runtime);}}
            });
        });
        try{$this->admin($this->boot([$extension]))->retry('alice',$row['subscriber_id'],$row['event_id'],(int)$row['revision']);self::fail('Revoked read ignored.');}catch(AccessDenied){self::assertFalse($this->pdo->inTransaction());}
        self::assertSame('failed',$this->pdo->query('SELECT status FROM outbox_deliveries LIMIT 1')->fetchColumn());self::assertCount(1,$this->admin()->listing('alice')['deliveries']);
        self::assertSame(0,(int)$this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation='operations.delivery.retry'")->fetchColumn());
    }
    public function testAuditFailureRollsBackResetAndRevision():void
    {
        $row=$this->failed();$this->rights();$this->pdo->exec("CREATE TRIGGER test_worker_retry_audit BEFORE INSERT ON audit_events FOR EACH ROW SET NEW.targets=IF(NEW.operation='operations.delivery.retry',NULL,NEW.targets)");
        try{try{$this->admin()->retry('alice',$row['subscriber_id'],$row['event_id'],(int)$row['revision']);self::fail('Audit failure ignored.');}catch(\PDOException){self::assertFalse($this->pdo->inTransaction());}}
        finally{$this->pdo->exec('DROP TRIGGER test_worker_retry_audit');}
        $current=$this->pdo->query('SELECT status,revision FROM outbox_deliveries LIMIT 1')->fetch();self::assertSame('failed',$current['status']);self::assertSame((int)$row['revision'],(int)$current['revision']);
    }
    public function testWebNavigationCsrfMethodAndFilterRules():void
    {
        $row=$this->failed();$this->rights();$csrf=str_repeat('a',64);$controller=(require __DIR__.'/../../bootstrap/web.php')($this->pdo,AppConfig::fromEnvironment(array_replace(\Samla\Core\Configuration\EnvironmentLoader::fromGlobals(),['APP_GROUP_MAX_AGE_SECONDS'=>'3600'])));
        $page=$controller->handle(new Request('GET','/operations/worker',query:['status'=>'failed']),'alice',$csrf);
        self::assertSame(200,$page->status);self::assertStringContainsString('Hintergrundaufgaben',$page->body);self::assertStringContainsString('/operations/worker/retry',$page->body);self::assertStringNotContainsString('actor_id',$page->body);
        self::assertSame(405,$controller->handle(new Request('GET','/operations/worker/retry'),'alice',$csrf)->status);
        self::assertSame(400,$controller->handle(new Request('GET','/operations/worker',query:['status'=>[]]),'alice',$csrf)->status);
        $post=['subscriber'=>$row['subscriber_id'],'event'=>$row['event_id'],'revision'=>(string)$row['revision'],'csrf'=>'bad'];
        self::assertSame(403,$controller->handle(new Request('POST','/operations/worker/retry',post:$post),'alice',$csrf)->status);$post['csrf']=$csrf;
        self::assertSame(303,$controller->handle(new Request('POST','/operations/worker/retry',post:$post),'alice',$csrf)->status);
        self::assertSame(409,$controller->handle(new Request('POST','/operations/worker/retry',post:$post),'alice',$csrf)->status);
    }
    public function testListingPaginatesAndEscapesTypesWithoutReadingPayloads():void
    {
        $row=$this->failed();$this->rights(false);
        for($i=0;$i<104;++$i){$id=bin2hex(random_bytes(16));$this->pdo->prepare('INSERT INTO outbox_events(id,event_type,payload,occurred_at) VALUES (?,?,?,1)')->execute([$id,'<script>','secret-payload']);$this->pdo->prepare("INSERT INTO outbox_deliveries(subscriber_id,event_id,status) VALUES (?,?,'failed')")->execute([$row['subscriber_id'],$id]);}
        $first=$this->admin()->listing('alice',status:'failed');$second=$this->admin()->listing('alice',$first['next'],'failed');self::assertCount(100,$first['deliveries']);self::assertCount(5,$second['deliveries']);self::assertNull($second['next']);
        self::assertCount(105,array_unique(array_column([...$first['deliveries'],...$second['deliveries']],'event_id')));
        $controller=(require __DIR__.'/../../bootstrap/web.php')($this->pdo,AppConfig::fromEnvironment(array_replace(\Samla\Core\Configuration\EnvironmentLoader::fromGlobals(),['APP_GROUP_MAX_AGE_SECONDS'=>'3600'])));$page=$controller->handle(new Request('GET','/operations/worker'),'alice',str_repeat('a',64));
        self::assertStringContainsString('&lt;script&gt;',$page->body);self::assertStringNotContainsString('secret-payload',$page->body);self::assertStringNotContainsString('<script>',$page->body);self::assertStringNotContainsString('/operations/worker/retry',$page->body);
    }
}
