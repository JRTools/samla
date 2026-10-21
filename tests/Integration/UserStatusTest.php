<?php
declare(strict_types=1);

namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository, UserStatusService};
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{AccessDenied, AccessRestriction, AuthorizationRequest, AuthorizationResult, ChangeValidator, Decision, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class UserStatusTest extends TestCase
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
        foreach (['alice','bob','carol'] as $user) {
            $this->identity->createUser($user, 'https://id.example', $user, $user);
            $this->identity->replaceVerifiedGroups($user, []);
        }
        $this->identity->createGroup('operators', 'Operators');
        $this->identity->addManualMembership('alice', 'operators');
        $this->runtime = $this->boot();
        $this->roles->save(new Role('status-operator', Scope::Global, ['identity.users.status.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('operators', 'status-operator', Scope::Global));
    }
    private function boot(array $plugins = []): PluginRuntime
    {
        return PluginRuntime::boot([new IdentityPlugin(), new RbacPlugin($this->roles, $this->identity), ...$plugins]);
    }
    private function service(?PluginRuntime $runtime = null): UserStatusService
    {
        $runtime ??= $this->runtime;
        return new UserStatusService($this->identity, new AuthorizationService($runtime, $this->identity), new PdoMutationRunner($this->pdo, $runtime));
    }
    private function version(string $user): int
    {
        $query = $this->pdo->prepare('SELECT authorization_version FROM identity_users WHERE id = ?');
        $query->execute([$user]); return (int) $query->fetchColumn();
    }
    private function denied(\Closure $call): void
    {
        try { $call(); self::fail('Expected denial.'); } catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testBlockAndUnblockImmediatelyChangeAuthorizationAndRecordEvents(): void
    {
        $before = $this->version('bob');
        self::assertTrue($this->service()->setBlocked('alice', 'bob', true));
        self::assertFalse($this->identity->isAccessAllowed('bob'));
        self::assertSame($before + 1, $this->version('bob'));
        self::assertTrue($this->service()->setBlocked('alice', 'bob', false));
        self::assertTrue($this->identity->isAccessAllowed('bob'));
        self::assertSame($before + 2, $this->version('bob'));
        $events = $this->pdo->query('SELECT operation, targets FROM audit_events ORDER BY operation')->fetchAll();
        self::assertSame(['identity.user.block','identity.user.unblock'], array_column($events, 'operation'));
        self::assertSame(['user' => 'bob'], json_decode($events[0]['targets'], true));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testNoopIsAuditedWithoutVersionBumpOrCompletionEvent(): void
    {
        $before = $this->version('bob');
        self::assertFalse($this->service()->setBlocked('alice', 'bob', false));
        self::assertSame($before, $this->version('bob'));
        self::assertSame(0, (int) $this->pdo->query('SELECT changed FROM audit_events')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testUnblockPreservesMembershipsAndDoesNotRefreshStaleGroups(): void
    {
        $this->identity->addManualMembership('bob', 'operators');
        $this->identity->setBlocked('bob', true);
        $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'bob'");
        self::assertTrue($this->service()->setBlocked('alice', 'bob', false));
        self::assertFalse($this->identity->isAccessAllowed('bob'));
        self::assertSame(1, (int) $this->pdo->query("SELECT groups_confirmed_at FROM identity_users WHERE id = 'bob'")->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM identity_memberships WHERE user_id = 'bob' AND origin = 'manual'")->fetchColumn());
    }
    public function testUserWithoutExplicitPermissionCannotBlock(): void
    { $this->denied(fn() => $this->service()->setBlocked('bob', 'carol', true)); }
    public function testBlockedActorCannotUnblockItself(): void
    {
        $this->identity->setBlocked('alice', true);
        $this->denied(fn() => $this->service()->setBlocked('alice', 'alice', false));
    }
    public function testStaleActorCannotAdministerUsers(): void
    {
        $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'alice'");
        $this->denied(fn() => $this->service()->setBlocked('alice', 'bob', true));
    }
    public function testLastUsableStatusAdministratorCannotBeBlocked(): void
    {
        $before = $this->version('alice');
        $this->denied(fn() => $this->service()->setBlocked('alice', 'alice', true));
        self::assertTrue($this->identity->isAccessAllowed('alice'));
        self::assertSame($before, $this->version('alice'));
    }
    public function testSelfBlockIsAllowedWithAnotherUsableStatusAdministrator(): void
    {
        $this->identity->addManualMembership('bob', 'operators');
        self::assertTrue($this->service()->setBlocked('alice', 'alice', true));
        self::assertFalse($this->identity->isAccessAllowed('alice'));
        self::assertTrue($this->service()->setBlocked('bob', 'alice', false));
    }
    public function testLastConfiguredMembershipAdministratorCannotBeBlocked(): void
    {
        $this->identity->createGroup('membership-operators', 'Membership operators');
        $this->identity->addManualMembership('bob', 'membership-operators');
        $this->roles->save(new Role('membership-operator', Scope::Global, ['identity.groups.memberships.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('membership-operators', 'membership-operator', Scope::Global));
        $before = $this->version('bob');
        $this->denied(fn() => $this->service()->setBlocked('alice', 'bob', true));
        self::assertSame($before, $this->version('bob'));
        $this->identity->addManualMembership('carol', 'membership-operators');
        self::assertTrue($this->service()->setBlocked('alice', 'bob', true));
    }
    public function testStaleSecondAdministratorDoesNotSatisfyRecoveryInvariant(): void
    {
        $this->identity->addManualMembership('bob', 'operators');
        $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'bob'");
        $this->denied(fn() => $this->service()->setBlocked('alice', 'alice', true));
    }
    public function testLastConfiguredGroupDetailsAdministratorCannotBeBlocked(): void
    {
        $this->identity->createGroup('details-operators', 'Details operators');
        $this->identity->addManualMembership('bob', 'details-operators');
        $this->roles->save(new Role('details-operator', Scope::Global, ['identity.groups.details.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('details-operators', 'details-operator', Scope::Global));
        $this->denied(fn() => $this->service()->setBlocked('alice', 'bob', true));
        $this->identity->addManualMembership('carol', 'details-operators');
        self::assertTrue($this->service()->setBlocked('alice', 'bob', true));
    }
    public function testLastConfiguredMappingAdministratorCannotBeBlocked(): void
    {
        $this->identity->createGroup('mapping-operators', 'Mapping operators');
        $this->identity->addManualMembership('bob', 'mapping-operators');
        $this->roles->save(new Role('mapping-operator', Scope::Global, ['identity.groups.mappings.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('mapping-operators', 'mapping-operator', Scope::Global));
        $this->denied(fn() => $this->service()->setBlocked('alice', 'bob', true));
        $this->identity->addManualMembership('carol', 'mapping-operators');
        self::assertTrue($this->service()->setBlocked('alice', 'bob', true));
    }
    public function testBlockedSecondAdministratorDoesNotSatisfyRecoveryInvariant(): void
    {
        $this->identity->addManualMembership('bob', 'operators'); $this->identity->setBlocked('bob', true);
        $this->denied(fn() => $this->service()->setBlocked('alice', 'alice', true));
    }
    public function testPluginRestrictedSecondAdministratorDoesNotSatisfyRecoveryInvariant(): void
    {
        $this->identity->addManualMembership('bob', 'operators');
        $runtime = $this->boot([new Extension('example.restrict', static function (PluginRegistrar $r): void {
            $r->restriction(new class implements AccessRestriction {
                public function decide(AuthorizationRequest $request): AuthorizationResult
                { return new AuthorizationResult($request->actorId === 'bob' ? Decision::Deny : Decision::NotApplicable, 'test restriction'); }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->setBlocked('alice', 'alice', true));
    }
    public function testValidatorVetoPreventsWrites(): void
    {
        $runtime = $this->boot([new Extension('example.veto', static function (PluginRegistrar $r): void {
            $r->validator(new class implements ChangeValidator {
                public function validate(MutationContext $context): void { throw new AccessDenied(); }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->setBlocked('alice', 'bob', true));
        self::assertTrue($this->identity->isAccessAllowed('bob'));
    }
    public function testAuthorizationIsRecheckedAfterValidators(): void
    {
        $pdo = $this->pdo;
        $runtime = $this->boot([new Extension('example.recheck', static function (PluginRegistrar $r) use ($pdo): void {
            $r->validator(new class($pdo) implements ChangeValidator {
                public function __construct(private \PDO $pdo) {}
                public function validate(MutationContext $context): void
                { $this->pdo->exec("DELETE FROM identity_memberships WHERE user_id = 'alice'"); }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->setBlocked('alice', 'bob', true));
        self::assertSame(['operators'], $this->identity->groupIds('alice'));
    }
    public function testOutboxFailureRollsBackStatusVersionAndAudit(): void
    {
        $before = $this->version('bob');
        $this->pdo->exec("CREATE TRIGGER fail_status_outbox BEFORE INSERT ON outbox_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test veto'");
        try {
            try { $this->service()->setBlocked('alice', 'bob', true); self::fail('Expected failure.'); }
            catch (\PDOException) { self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame($before, $this->version('bob'));
            self::assertTrue($this->identity->isAccessAllowed('bob'));
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        } finally { $this->pdo->exec('DROP TRIGGER fail_status_outbox'); }
    }
    public function testUnknownTargetDoesNotLeaveAuditOrTransaction(): void
    {
        try { $this->service()->setBlocked('alice', 'missing', true); self::fail('Expected unknown user.'); }
        catch (\InvalidArgumentException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testInternalWriterRequiresExplicitTransaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->identity->setBlockedInsideTransaction('bob', true);
    }
}
