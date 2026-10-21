<?php
declare(strict_types=1);

namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\{PdoMutationRunner, PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{GroupMembershipService, IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\PluginApi\{AccessDenied, AccessRestriction, AuthorizationRequest, AuthorizationResult, ChangeValidator, Decision, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacGroupMembershipPolicy, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class GroupMembershipTest extends TestCase
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
        foreach (['alice','bob'] as $user) {
            $this->identity->createUser($user, 'https://id.example', $user, $user);
            $this->identity->replaceVerifiedGroups($user, []);
        }
        foreach (['operators','managers','readers','unassigned'] as $group) { $this->identity->createGroup($group, $group); }
        $this->identity->addManualMembership('alice', 'operators');
        $this->identity->addManualMembership('alice', 'managers');
        $this->pdo->exec("INSERT INTO storage_spaces (id, name) VALUES ('media', 'Media'), ('board', 'Board')");
        $this->runtime = $this->boot();
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.status.manage', 'identity.groups.memberships.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('operators', 'operator', Scope::Global));
        $this->roles->save(new Role('manager', Scope::Space, ['rbac.assignments.manage', 'files.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('managers', 'manager', Scope::Space, 'media'));
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('readers', 'reader', Scope::Space, 'media'));
        $this->delegate('reader', 'media', ['files.read']);
    }
    private function boot(array $plugins = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($this->roles, $this->identity), ...$plugins]); }
    private function service(?PluginRuntime $runtime = null): GroupMembershipService
    {
        $runtime ??= $this->runtime;
        $auth = new AuthorizationService($runtime, $this->identity);
        $resources = new PdoResourceDirectory($this->pdo);
        return new GroupMembershipService($this->identity, $auth,
            new RbacGroupMembershipPolicy($this->roles, $this->identity, $auth, $runtime, $resources),
            $resources, new PdoMutationRunner($this->pdo, $runtime));
    }
    private function delegate(string $role, string $space, array $permissions): void
    {
        $q = $this->pdo->prepare('INSERT INTO rbac_delegations (grantor_group_id, role_id, space_id) VALUES (?, ?, ?)');
        $q->execute(['managers', $role, $space]);
        $q = $this->pdo->prepare('INSERT INTO rbac_delegation_permissions (grantor_group_id, role_id, space_id, permission_id) VALUES (?, ?, ?, ?)');
        foreach ($permissions as $permission) { $q->execute(['managers', $role, $space, $permission]); }
    }
    private function version(string $user = 'bob'): int
    {
        $q = $this->pdo->prepare('SELECT authorization_version FROM identity_users WHERE id = ?');
        $q->execute([$user]); return (int) $q->fetchColumn();
    }
    private function allowed(string $user, string $space): bool
    {
        return (new AuthorizationService($this->runtime, $this->identity))->decide(
            new AuthorizationRequest($user, 'files.read', Scope::Space, $space))->decision === Decision::Permit;
    }
    private function denied(\Closure $call): void
    {
        $before = $this->pdo->query('SELECT user_id, group_id, origin, source_group FROM identity_memberships ORDER BY user_id, group_id, origin, source_group')->fetchAll();
        try { $call(); self::fail('Expected denial.'); } catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame($before, $this->pdo->query('SELECT user_id, group_id, origin, source_group FROM identity_memberships ORDER BY user_id, group_id, origin, source_group')->fetchAll());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testGrantRevokeUpdatesEffectiveRightsVersionAuditAndEvents(): void
    {
        $before = $this->version();
        self::assertFalse($this->allowed('bob', 'media'));
        self::assertTrue($this->service()->grant('alice', 'bob', 'readers'));
        self::assertTrue($this->allowed('bob', 'media'));
        self::assertSame($before + 1, $this->version());
        self::assertTrue($this->service()->revoke('alice', 'bob', 'readers'));
        self::assertFalse($this->allowed('bob', 'media'));
        self::assertSame($before + 2, $this->version());
        $events = $this->pdo->query('SELECT operation, targets FROM audit_events ORDER BY operation')->fetchAll();
        self::assertSame(['identity.membership.grant', 'identity.membership.revoke'], array_column($events, 'operation'));
        self::assertSame(['user' => 'bob', 'group' => 'readers'], json_decode($events[0]['targets'], true));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testRepeatedGrantAndRevokeDoNotBumpVersionOrDuplicateEvents(): void
    {
        $service = $this->service(); $before = $this->version();
        self::assertTrue($service->grant('alice', 'bob', 'readers'));
        self::assertFalse($service->grant('alice', 'bob', 'readers'));
        self::assertTrue($service->revoke('alice', 'bob', 'readers'));
        self::assertFalse($service->revoke('alice', 'bob', 'readers'));
        self::assertSame($before + 2, $this->version());
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testExternalSourcesRemainAfterManualMembershipIsRemoved(): void
    {
        $this->pdo->exec("INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES ('https://id.example', 'one', 'readers'), ('https://id.example', 'two', 'readers')");
        $this->identity->replaceVerifiedGroups('bob', ['one','two']);
        self::assertTrue($this->service()->grant('alice', 'bob', 'readers'));
        self::assertTrue($this->service()->revoke('alice', 'bob', 'readers'));
        self::assertTrue($this->allowed('bob', 'media'));
        self::assertSame(['one','two'], $this->pdo->query("SELECT source_group FROM identity_memberships WHERE user_id = 'bob' ORDER BY source_group")->fetchAll(\PDO::FETCH_COLUMN));
        self::assertFalse($this->service()->revoke('alice', 'bob', 'readers'));
    }
    public function testMembershipChangesDoNotRefreshStaleClaimsOrUnblockTargets(): void
    {
        $this->identity->setBlocked('bob', true);
        $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'bob'");
        self::assertTrue($this->service()->grant('alice', 'bob', 'readers'));
        self::assertFalse($this->allowed('bob', 'media'));
        $row = $this->pdo->query("SELECT status, groups_confirmed_at FROM identity_users WHERE id = 'bob'")->fetch();
        self::assertSame('blocked', $row['status']); self::assertSame(1, (int) $row['groups_confirmed_at']);
    }
    public function testGlobalManagementPermissionIsNecessaryEvenWithDelegation(): void
    {
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.status.manage']), $this->runtime);
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
    }
    public function testScopedManagementPermissionIsNecessaryEvenWithGlobalPermissionAndRule(): void
    {
        $this->roles->save(new Role('manager', Scope::Space, ['files.read']), $this->runtime);
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
    }
    public function testHoldingTargetRightsDoesNotReplaceExplicitDelegation(): void
    {
        self::assertTrue($this->allowed('alice', 'media'));
        $this->pdo->exec('DELETE FROM rbac_delegation_permissions'); $this->pdo->exec('DELETE FROM rbac_delegations');
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
    }
    public function testEverySpaceOfTargetGroupMustBeDelegated(): void
    {
        $this->roles->assign(new RoleAssignment('readers', 'reader', Scope::Space, 'board'));
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
        $this->roles->assign(new RoleAssignment('managers', 'manager', Scope::Space, 'board'));
        $this->delegate('reader', 'board', ['files.read']);
        self::assertTrue($this->service()->grant('alice', 'bob', 'readers'));
        self::assertTrue($this->allowed('bob', 'board'));
    }
    public function testEveryRoleOfTargetGroupMustBeDelegated(): void
    {
        $this->roles->save(new Role('writer', Scope::Space, ['files.update']), $this->runtime);
        $this->roles->assign(new RoleAssignment('readers', 'writer', Scope::Space, 'media'));
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
    }
    public function testRoleGrowthCannotExpandMembershipDelegation(): void
    {
        $this->roles->save(new Role('reader', Scope::Space, ['files.read','files.update']), $this->runtime);
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
    }
    public function testRevocationRemainsPossibleAfterPermissionCeilingDrift(): void
    {
        $this->identity->addManualMembership('bob', 'readers');
        $this->roles->save(new Role('reader', Scope::Space, ['files.read','files.update']), $this->runtime);
        self::assertTrue($this->service()->revoke('alice', 'bob', 'readers'));
        self::assertFalse($this->allowed('bob', 'media'));
    }
    public function testGlobalGroupCannotBeUsedForSelfEscalationOrAdminRemoval(): void
    {
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'operators'));
        $this->denied(fn() => $this->service()->revoke('alice', 'alice', 'operators'));
    }
    public function testScopedAdministrativeGroupCannotBeEditedEvenWithRule(): void
    {
        $this->delegate('manager', 'media', ['rbac.assignments.manage','files.read']);
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'managers'));
        $this->denied(fn() => $this->service()->revoke('alice', 'alice', 'managers'));
    }
    public function testUnassignedGroupStillRequiresGlobalPermission(): void
    {
        $this->denied(fn() => $this->service()->grant('bob', 'bob', 'unassigned'));
        self::assertTrue($this->service()->grant('alice', 'bob', 'unassigned'));
        self::assertSame(['unassigned'], $this->identity->groupIds('bob'));
    }
    public function testBlockedAndStaleActorsCannotChangeMemberships(): void
    {
        $this->identity->setBlocked('alice', true);
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
        $this->identity->setBlocked('alice', false);
        $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'alice'");
        $this->denied(fn() => $this->service()->revoke('alice', 'bob', 'readers'));
    }
    public function testUnknownGroupAndMissingSpaceFailClosed(): void
    {
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'missing'));
        $this->pdo->exec("DELETE FROM storage_spaces WHERE id = 'media'");
        $this->denied(fn() => $this->service()->grant('alice', 'bob', 'readers'));
    }
    public function testUnknownUserDoesNotLeaveAuditOrTransaction(): void
    {
        try { $this->service()->grant('alice', 'missing', 'readers'); self::fail('Expected failure.'); }
        catch (\InvalidArgumentException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testPluginScopedRestrictionIsNotBypassedByGlobalMembershipPermission(): void
    {
        $runtime = $this->boot([new Extension('example.restrict', static function (PluginRegistrar $r): void {
            $r->restriction(new class implements AccessRestriction {
                public function decide(AuthorizationRequest $request): AuthorizationResult
                { return new AuthorizationResult($request->action === 'rbac.assignments.manage' ? Decision::Deny : Decision::NotApplicable, 'test restriction'); }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->grant('alice', 'bob', 'readers'));
    }
    public function testPluginVetoPreventsMembershipWrite(): void
    {
        $runtime = $this->boot([new Extension('example.veto', static function (PluginRegistrar $r): void {
            $r->validator(new class implements ChangeValidator {
                public function validate(MutationContext $context): void { throw new AccessDenied(); }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->grant('alice', 'bob', 'readers'));
    }
    public function testTargetRoleAssignmentsAreRecheckedAfterValidators(): void
    {
        $pdo = $this->pdo;
        $runtime = $this->boot([new Extension('example.recheck', static function (PluginRegistrar $r) use ($pdo): void {
            $r->validator(new class($pdo) implements ChangeValidator {
                public function __construct(private \PDO $pdo) {}
                public function validate(MutationContext $context): void
                { $this->pdo->exec("INSERT INTO rbac_assignments (group_id, role_id, scope, space_id) VALUES ('readers', 'operator', 'global', '')"); }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->grant('alice', 'bob', 'readers'));
        self::assertSame(1, (int) $this->pdo->query("SELECT COUNT(*) FROM rbac_assignments WHERE group_id = 'readers'")->fetchColumn());
    }
    public function testIndirectLossOfLastStatusAdministratorRollsBackMembershipAndVersion(): void
    {
        $this->identity->addManualMembership('alice', 'unassigned');
        $identity = $this->identity;
        $runtime = $this->boot([new Extension('example.continuity', static function (PluginRegistrar $r) use ($identity): void {
            $r->restriction(new class($identity) implements AccessRestriction {
                public function __construct(private PdoIdentityRepository $identity) {}
                public function decide(AuthorizationRequest $request): AuthorizationResult
                {
                    $deny = $request->action === 'identity.users.status.manage' && !in_array('unassigned', $this->identity->groupIds($request->actorId), true);
                    return new AuthorizationResult($deny ? Decision::Deny : Decision::NotApplicable, 'test continuity');
                }
            });
        })]);
        $before = $this->version('alice');
        $this->denied(fn() => $this->service($runtime)->revoke('alice', 'alice', 'unassigned'));
        self::assertSame($before, $this->version('alice'));
    }
    public function testAuditFailureRollsBackMembershipAndVersion(): void
    { $this->assertStorageFailureRollsBack('audit_events'); }
    public function testIndirectLossOfLastMembershipAdministratorIsAlsoRejected(): void
    {
        $this->identity->addManualMembership('alice', 'unassigned');
        $identity = $this->identity;
        $runtime = $this->boot([new Extension('example.membership-continuity', static function (PluginRegistrar $r) use ($identity): void {
            $r->restriction(new class($identity) implements AccessRestriction {
                public function __construct(private PdoIdentityRepository $identity) {}
                public function decide(AuthorizationRequest $request): AuthorizationResult
                {
                    $deny = $request->action === 'identity.groups.memberships.manage' && !in_array('unassigned', $this->identity->groupIds($request->actorId), true);
                    return new AuthorizationResult($deny ? Decision::Deny : Decision::NotApplicable, 'test membership continuity');
                }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->revoke('alice', 'alice', 'unassigned'));
    }
    public function testOutboxFailureRollsBackMembershipVersionAndAudit(): void
    { $this->assertStorageFailureRollsBack('outbox_events'); }
    public function testIndirectLossOfLastGroupDetailsAdministratorIsRejected(): void
    {
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.status.manage', 'identity.groups.memberships.manage', 'identity.groups.details.manage']), $this->runtime);
        $this->identity->addManualMembership('alice', 'unassigned');
        $identity = $this->identity;
        $runtime = $this->boot([new Extension('example.details-continuity', static function (PluginRegistrar $r) use ($identity): void {
            $r->restriction(new class($identity) implements AccessRestriction {
                public function __construct(private PdoIdentityRepository $identity) {}
                public function decide(AuthorizationRequest $request): AuthorizationResult
                {
                    $deny = $request->action === 'identity.groups.details.manage' && !in_array('unassigned', $this->identity->groupIds($request->actorId), true);
                    return new AuthorizationResult($deny ? Decision::Deny : Decision::NotApplicable, 'test details continuity');
                }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->revoke('alice', 'alice', 'unassigned'));
    }
    private function assertStorageFailureRollsBack(string $table): void
    {
        $before = $this->version();
        $this->pdo->exec("CREATE TRIGGER fail_membership_storage BEFORE INSERT ON " . $table . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test veto'");
        try {
            try { $this->service()->grant('alice', 'bob', 'readers'); self::fail('Expected failure.'); }
            catch (\PDOException) { self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame($before, $this->version()); self::assertSame([], $this->identity->groupIds('bob'));
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
        } finally { $this->pdo->exec('DROP TRIGGER fail_membership_storage'); }
    }
    public function testInternalWriterRequiresAnActiveTransaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->identity->setManualMembershipInsideTransaction('bob', 'readers', true);
    }
}
