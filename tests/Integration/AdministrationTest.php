<?php
declare(strict_types=1);

namespace Samla\Tests\Integration;

use Samla\Core\Administration\{PdoMutationRunner, PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\PdoIdentityRepository;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\PluginApi\{AccessDenied, ChangeValidator, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment, RoleAssignmentService};
use Samla\Tests\Fixtures\Extension;
use PHPUnit\Framework\TestCase;

final class AdministrationTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identity;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private int $now = 2000000000;
    protected function setUp(): void
    {
        $config = AppConfig::fromGlobals();
        if ($config->environment !== 'test') { throw new \RuntimeException('APP_ENV=test is required.'); }
        $this->pdo = (new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__ . '/../../bootstrap/migrations.php');
        foreach (['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table) {
            $this->pdo->exec('DELETE FROM ' . $table);
        }
        $this->identity = new PdoIdentityRepository($this->pdo, 3600, fn(): int => $this->now);
        $this->roles = new PdoRoleRepository($this->pdo);
        $this->identity->createUser('admin', 'https://id.example', 'admin', 'Admin');
        $this->identity->createUser('bob', 'https://id.example', 'bob', 'Bob');
        foreach (['managers','readers'] as $group) { $this->identity->createGroup($group, $group); }
        $this->identity->addManualMembership('admin', 'managers');
        $this->identity->addManualMembership('bob', 'readers');
        $this->identity->replaceVerifiedGroups('admin', []); $this->identity->replaceVerifiedGroups('bob', []);
        $this->pdo->exec("INSERT INTO storage_spaces (id, name) VALUES ('media', 'Media'), ('board', 'Board')");
        $this->runtime = PluginRuntime::boot([new StoragePlugin(), new RbacPlugin($this->roles, $this->identity)]);
        $this->roles->save(new Role('manager', Scope::Space, ['rbac.assignments.manage']), $this->runtime);
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('managers', 'manager', Scope::Space, 'media'));
        $this->pdo->exec("INSERT INTO rbac_delegations (grantor_group_id, role_id, space_id) VALUES ('managers', 'reader', 'media')");
        $this->pdo->exec("INSERT INTO rbac_delegation_permissions (grantor_group_id, role_id, space_id, permission_id) VALUES ('managers', 'reader', 'media', 'files.read')");
    }
    private function assignment(string $space = 'media', string $group = 'readers', string $role = 'reader'): RoleAssignment
    { return new RoleAssignment($group, $role, Scope::Space, $space); }
    private function service(array $plugins = []): RoleAssignmentService
    {
        $runtime = $plugins === [] ? $this->runtime : PluginRuntime::boot([new StoragePlugin(), new RbacPlugin($this->roles, $this->identity), ...$plugins]);
        return new RoleAssignmentService($this->roles, $this->identity, new AuthorizationService($runtime, $this->identity), $runtime,
            new PdoResourceDirectory($this->pdo), new PdoMutationRunner($this->pdo, $runtime));
    }
    private function tableRows(string $table): int { return (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn(); }
    private function denied(\Closure $call): void
    {
        try { $call(); self::fail('Mutation should be denied'); }
        catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, $this->tableRows('audit_events'));
        self::assertSame(0, $this->tableRows('outbox_events'));
    }
    public function testGrantRevokeAndAuditAreAtomicAndExplainable(): void
    {
        $service = $this->service();
        self::assertTrue($service->grant('admin', $this->assignment()));
        $auth = new AuthorizationService($this->runtime, $this->identity);
        self::assertSame(\Samla\PluginApi\Decision::Permit, $auth->decide(new \Samla\PluginApi\AuthorizationRequest('bob', 'files.read', Scope::Space, 'media'))->decision);
        $audit = $this->pdo->query('SELECT * FROM audit_events')->fetch();
        self::assertSame('admin', $audit['actor_id']);
        self::assertSame(['group' => 'readers', 'role' => 'reader'], json_decode($audit['targets'], true));
        self::assertSame($audit['id'], $this->pdo->query('SELECT id FROM outbox_events')->fetchColumn());
        self::assertTrue($service->revoke('admin', $this->assignment()));
        self::assertSame(\Samla\PluginApi\Decision::Deny, $auth->decide(new \Samla\PluginApi\AuthorizationRequest('bob', 'files.read', Scope::Space, 'media'))->decision);
        self::assertSame(2, $this->tableRows('audit_events')); self::assertSame(2, $this->tableRows('outbox_events'));
    }
    public function testIdempotentGrantAndRevokeDoNotEmitDuplicateChangeEvents(): void
    {
        $service = $this->service();
        self::assertTrue($service->grant('admin', $this->assignment()));
        self::assertFalse($service->grant('admin', $this->assignment()));
        self::assertSame(2, $this->tableRows('audit_events')); self::assertSame(1, $this->tableRows('outbox_events'));
        self::assertTrue($service->revoke('admin', $this->assignment()));
        self::assertFalse($service->revoke('admin', $this->assignment()));
        self::assertSame(4, $this->tableRows('audit_events')); self::assertSame(2, $this->tableRows('outbox_events'));
    }
    public function testManagementPermissionAloneDoesNotAllowDelegation(): void
    {
        $this->pdo->exec('DELETE FROM rbac_delegation_permissions'); $this->pdo->exec('DELETE FROM rbac_delegations');
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment()));
    }
    public function testDelegationRuleAloneDoesNotAllowAdministration(): void
    {
        $this->roles->save(new Role('manager', Scope::Space, []), $this->runtime);
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment()));
    }
    public function testActorBlockAndStaleGroupsDenyMutation(): void
    {
        $this->identity->setBlocked('admin', true);
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment()));
        $this->identity->setBlocked('admin', false); $this->now += 3601;
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment()));
    }
    public function testOtherSpaceAndUnknownResourcesAreDenied(): void
    {
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment('board')));
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment(group: 'missing')));
        $this->pdo->exec("DELETE FROM storage_spaces WHERE id = 'media'");
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment()));
    }
    public function testRoleGrowthCannotExceedExplicitDelegationCeiling(): void
    {
        $this->roles->save(new Role('reader', Scope::Space, ['files.read','files.create']), $this->runtime);
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment()));
    }
    public function testExistingAssignmentCanBeRevokedAfterRoleOutgrowsCeiling(): void
    {
        $this->roles->assign($this->assignment());
        $this->roles->save(new Role('reader', Scope::Space, ['files.read','files.create']), $this->runtime);
        self::assertTrue($this->service()->revoke('admin', $this->assignment()));
    }
    public function testGlobalAndAdministrativeAssignmentsAreOutsideThisService(): void
    {
        $this->denied(fn() => $this->service()->grant('admin', new RoleAssignment('readers', 'reader', Scope::Global)));
        $this->denied(fn() => $this->service()->revoke('admin', $this->assignment(group: 'managers', role: 'manager')));
        $this->pdo->exec("INSERT INTO rbac_delegations (grantor_group_id, role_id, space_id) VALUES ('managers', 'manager', 'media')");
        $this->pdo->exec("INSERT INTO rbac_delegation_permissions (grantor_group_id, role_id, space_id, permission_id) VALUES ('managers', 'manager', 'media', 'rbac.assignments.manage')");
        $this->denied(fn() => $this->service()->grant('admin', $this->assignment(role: 'manager')));
    }
    private function validator(\Closure $call, string $id = 'example.validation', int $priority = 0): Extension
    {
        return new Extension($id, static function (PluginRegistrar $r) use ($call, $priority): void {
            $r->validator(new class($call) implements ChangeValidator {
                public function __construct(private \Closure $call) {}
                public function validate(MutationContext $context): void { ($this->call)($context); }
            }, $priority);
        });
    }
    public function testPluginVetoLeavesNoChangeAuditOrCompletionEvent(): void
    {
        $plugin = $this->validator(static function (): void { throw new \DomainException('Organization rule'); });
        try { $this->service([$plugin])->grant('admin', $this->assignment()); self::fail('Veto ignored'); }
        catch (\DomainException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(1, $this->tableRows('rbac_assignments')); self::assertSame(0, $this->tableRows('audit_events')); self::assertSame(0, $this->tableRows('outbox_events'));
    }
    public function testAuthorizationIsRepeatedAfterValidators(): void
    {
        // Test-only simulation of a change in security preconditions. Trusted validators must not mutate state.
        $pdo = $this->pdo;
        $plugin = $this->validator(static function () use ($pdo): void { $pdo->exec("DELETE FROM identity_memberships WHERE user_id = 'admin'"); });
        $this->denied(fn() => $this->service([$plugin])->grant('admin', $this->assignment()));
        self::assertSame(['managers'], $this->identity->groupIds('admin'));
    }
    public function testValidatorsUseDeterministicOrderAndImmutableContexts(): void
    {
        $seen = new \ArrayObject();
        $a = $this->validator(static function (MutationContext $context) use ($seen): void { $seen[] = 'a'; self::assertSame('admin', $context->actorId); self::assertSame('media', $context->spaceId); }, 'example.a', 10);
        $b = $this->validator(static function () use ($seen): void { $seen[] = 'b'; }, 'example.b', 10);
        $c = $this->validator(static function () use ($seen): void { $seen[] = 'c'; }, 'example.c', 0);
        self::assertTrue($this->service([$c,$b,$a])->grant('admin', $this->assignment()));
        self::assertSame(['a','b','c'], $seen->getArrayCopy());
    }
    public function testOutboxFailureRollsBackAssignmentAndAudit(): void
    {
        $this->pdo->exec("CREATE TRIGGER test_fail_outbox BEFORE INSERT ON outbox_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'");
        try {
            try { $this->service()->grant('admin', $this->assignment()); self::fail('Outbox failure ignored'); }
            catch (\PDOException) { self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame(1, $this->tableRows('rbac_assignments')); self::assertSame(0, $this->tableRows('audit_events')); self::assertSame(0, $this->tableRows('outbox_events'));
        } finally { $this->pdo->exec('DROP TRIGGER test_fail_outbox'); }
    }
    public function testMissingAdministrationLockFailsClosed(): void
    {
        $this->pdo->exec('DELETE FROM administration_lock');
        try {
            try { $this->service()->grant('admin', $this->assignment()); self::fail('Missing lock ignored'); }
            catch (\RuntimeException) { self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame(1, $this->tableRows('rbac_assignments')); self::assertSame(0, $this->tableRows('audit_events'));
        } finally { $this->pdo->exec('INSERT INTO administration_lock (id) VALUES (1)'); }
    }
    public function testPrivateWriterRequiresTransaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->roles->assignInsideTransaction($this->assignment());
    }
    public function testAuditFailureRollsBackTheAssignment(): void
    {
        $this->pdo->exec("CREATE TRIGGER test_fail_audit BEFORE INSERT ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'");
        try {
            try { $this->service()->grant('admin', $this->assignment()); self::fail('Audit failure ignored'); }
            catch (\PDOException) { self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame(1, $this->tableRows('rbac_assignments')); self::assertSame(0, $this->tableRows('audit_events')); self::assertSame(0, $this->tableRows('outbox_events'));
        } finally { $this->pdo->exec('DROP TRIGGER test_fail_audit'); }
    }
    public function testConcurrentAdministrationLockBlocksMutation(): void
    {
        $other = (new ConnectionFactory())->connect(AppConfig::fromGlobals()->database);
        $other->beginTransaction();
        $other->query('SELECT id FROM administration_lock WHERE id = 1 FOR UPDATE')->fetchAll();
        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        try {
            try { $this->service()->grant('admin', $this->assignment()); self::fail('Concurrent lock ignored'); }
            catch (\PDOException $error) { self::assertSame(1205, (int) $error->errorInfo[1]); self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame(1, $this->tableRows('rbac_assignments')); self::assertSame(0, $this->tableRows('audit_events'));
        } finally { $other->rollBack(); }
    }
    public function testReferencePluginAddsAnActualAssignmentValidator(): void
    {
        require_once __DIR__ . '/../../examples/reference-plugin/ReferencePlugin.php';
        $this->identity->createGroup('example.blocked', 'Example policy target');
        try { $this->service([new \Samla\Examples\ReferencePlugin()])->grant('admin', $this->assignment(group: 'example.blocked')); self::fail('Reference validator ignored'); }
        catch (\DomainException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(1, $this->tableRows('rbac_assignments')); self::assertSame(0, $this->tableRows('audit_events'));
    }
}
