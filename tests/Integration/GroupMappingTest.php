<?php
declare(strict_types=1);

namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\{PdoMutationRunner, PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{GroupMappingService, IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\PluginApi\{AccessDenied, AccessRestriction, AuthorizationRequest, AuthorizationResult, ChangeValidator, Decision, GroupMappingChange, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacGroupMappingPolicy, RbacGroupMembershipPolicy, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class GroupMappingTest extends TestCase
{
    private const ISSUER = 'https://id.example';
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
            $this->identity->createUser($user, $user === 'carol' ? 'https://other.example' : self::ISSUER, $user, $user);
            $this->identity->replaceVerifiedGroups($user, $user === 'alice' ? [] : ['press']);
        }
        foreach (['operators','managers','readers','unassigned'] as $group) { $this->identity->createGroup($group, $group); }
        $this->identity->addManualMembership('alice', 'operators'); $this->identity->addManualMembership('alice', 'managers');
        $this->pdo->exec("INSERT INTO storage_spaces (id, name) VALUES ('media', 'Media'), ('board', 'Board')");
        $this->runtime = $this->boot();
        $this->roles->save(new Role('operator', Scope::Global, ['identity.groups.read','identity.users.status.manage','identity.groups.memberships.manage','identity.groups.details.manage','identity.groups.mappings.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('operators', 'operator', Scope::Global));
        $this->roles->save(new Role('manager', Scope::Space, ['rbac.assignments.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('managers', 'manager', Scope::Space, 'media'));
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('readers', 'reader', Scope::Space, 'media'));
        $this->delegate('media');
    }
    private function boot(array $plugins = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($this->roles, $this->identity), ...$plugins]); }
    private function service(?PluginRuntime $runtime = null): GroupMappingService
    {
        $runtime ??= $this->runtime;
        $auth = new AuthorizationService($runtime, $this->identity); $resources = new PdoResourceDirectory($this->pdo);
        $delegation = new RbacGroupMembershipPolicy($this->roles, $this->identity, $auth, $runtime, $resources);
        return new GroupMappingService($this->identity, $auth, new RbacGroupMappingPolicy($delegation), $resources, new PdoMutationRunner($this->pdo, $runtime));
    }
    private function delegate(string $space): void
    {
        $q = $this->pdo->prepare('INSERT INTO rbac_delegations (grantor_group_id, role_id, space_id) VALUES (?, ?, ?)');
        $q->execute(['managers', 'reader', $space]);
        $q = $this->pdo->prepare('INSERT INTO rbac_delegation_permissions (grantor_group_id, role_id, space_id, permission_id) VALUES (?, ?, ?, ?)');
        $q->execute(['managers', 'reader', $space, 'files.read']);
    }
    private function version(string $user = 'bob'): int
    { $q = $this->pdo->prepare('SELECT authorization_version FROM identity_users WHERE id = ?'); $q->execute([$user]); return (int) $q->fetchColumn(); }
    private function allowed(string $user = 'bob'): bool
    {
        return (new AuthorizationService($this->runtime, $this->identity))->decide(new AuthorizationRequest($user, 'files.read', Scope::Space, 'media'))->decision === Decision::Permit;
    }
    private function denied(\Closure $call): void
    {
        $before = $this->pdo->query('SELECT * FROM identity_memberships ORDER BY user_id, group_id, origin, source_group')->fetchAll();
        try { $call(); self::fail('Expected denial.'); } catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame($before, $this->pdo->query('SELECT * FROM identity_memberships ORDER BY user_id, group_id, origin, source_group')->fetchAll());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testGrantAndRevokeImmediatelyReconcileExistingSnapshotsAndVersions(): void
    {
        $before = $this->version(); $carol = $this->version('carol');
        self::assertFalse($this->allowed());
        self::assertTrue($this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertTrue($this->allowed()); self::assertFalse($this->allowed('carol'));
        self::assertSame($before + 1, $this->version()); self::assertSame($carol, $this->version('carol'));
        self::assertTrue($this->service()->revoke('alice', self::ISSUER, 'press', 'readers'));
        self::assertFalse($this->allowed()); self::assertSame($before + 2, $this->version());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
        self::assertSame(['press'], $this->pdo->query("SELECT external_id FROM identity_external_groups WHERE user_id = 'bob'")->fetchAll(\PDO::FETCH_COLUMN));
    }
    public function testOtherExternalSourcesAndManualMembershipRemain(): void
    {
        $this->identity->replaceVerifiedGroups('bob', ['press','board']);
        $this->service()->grant('alice', self::ISSUER, 'press', 'readers');
        $this->service()->grant('alice', self::ISSUER, 'board', 'readers');
        $this->identity->addManualMembership('bob', 'readers');
        self::assertTrue($this->service()->revoke('alice', self::ISSUER, 'press', 'readers'));
        self::assertTrue($this->allowed());
        self::assertSame(['board'], $this->pdo->query("SELECT source_group FROM identity_memberships WHERE user_id = 'bob' AND origin = 'external'")->fetchAll(\PDO::FETCH_COLUMN));
        self::assertTrue($this->service()->revoke('alice', self::ISSUER, 'board', 'readers'));
        self::assertTrue($this->allowed());
        self::assertSame(['manual'], $this->pdo->query("SELECT origin FROM identity_memberships WHERE user_id = 'bob'")->fetchAll(\PDO::FETCH_COLUMN));
    }
    public function testAllMatchingUsersAndOpaqueBinaryGroupIdentifiersAreReconciled(): void
    {
        $this->identity->createUser('dave', self::ISSUER, 'dave', 'Dave');
        $external = "\xff0";
        $this->identity->replaceVerifiedGroups('bob', [$external]); $this->identity->replaceVerifiedGroups('dave', [$external]);
        $bob = $this->version(); $dave = $this->version('dave');
        self::assertTrue($this->service()->grant('alice', self::ISSUER, $external, 'readers'));
        self::assertTrue($this->allowed()); self::assertTrue($this->allowed('dave'));
        self::assertSame($bob + 1, $this->version()); self::assertSame($dave + 1, $this->version('dave'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
        self::assertIsArray(json_decode($this->pdo->query('SELECT payload FROM outbox_events')->fetchColumn(), true, flags: JSON_THROW_ON_ERROR));
    }
    public function testNoopDoesNotIncreaseVersionsOrEmitDuplicateEvents(): void
    {
        $service = $this->service(); $before = $this->version();
        self::assertTrue($service->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertFalse($service->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertTrue($service->revoke('alice', self::ISSUER, 'press', 'readers'));
        self::assertFalse($service->revoke('alice', self::ISSUER, 'press', 'readers'));
        self::assertSame($before + 2, $this->version());
        self::assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testMappingAppliesToFutureVerifiedSnapshotsToo(): void
    {
        $before = $this->version();
        self::assertTrue($this->service()->grant('alice', self::ISSUER, 'future', 'readers'));
        self::assertSame($before, $this->version()); self::assertFalse($this->allowed());
        $this->identity->replaceVerifiedGroups('bob', ['future']);
        self::assertTrue($this->allowed());
    }
    public function testNoExistingMembersDoesNotBypassDelegation(): void
    {
        $this->pdo->exec('DELETE FROM rbac_delegation_permissions'); $this->pdo->exec('DELETE FROM rbac_delegations');
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'future', 'readers'));
    }
    public function testGlobalMappingPermissionIsNecessary(): void
    {
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.status.manage','identity.groups.memberships.manage','identity.groups.details.manage']), $this->runtime);
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
    }
    public function testScopedManagementPermissionIsNecessary(): void
    {
        $this->roles->save(new Role('manager', Scope::Space, []), $this->runtime);
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
    }
    public function testEveryTargetRoleAndSpaceMustBeDelegated(): void
    {
        $this->roles->assign(new RoleAssignment('readers', 'reader', Scope::Space, 'board'));
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
        $this->roles->assign(new RoleAssignment('managers', 'manager', Scope::Space, 'board')); $this->delegate('board');
        $this->roles->save(new Role('writer', Scope::Space, ['files.update']), $this->runtime);
        $this->roles->assign(new RoleAssignment('readers', 'writer', Scope::Space, 'media'));
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
    }
    public function testRoleGrowthDoesNotExpandDelegationButRevocationRemainsPossible(): void
    {
        $this->identity->replaceVerifiedGroups('bob', []);
        $this->pdo->exec("INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES ('https://id.example', 'press', 'readers')");
        $this->identity->replaceVerifiedGroups('bob', ['press']);
        $this->roles->save(new Role('reader', Scope::Space, ['files.read','files.update']), $this->runtime);
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'future', 'readers'));
        self::assertTrue($this->service()->revoke('alice', self::ISSUER, 'press', 'readers'));
        self::assertFalse($this->allowed());
    }
    public function testAdministrativeGroupsAreExcludedEvenWithoutCurrentMatches(): void
    {
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'future', 'operators'));
        $this->denied(fn() => $this->service()->revoke('alice', self::ISSUER, 'future', 'managers'));
    }
    public function testBlockedAndStaleActorsCannotEditMappings(): void
    {
        $this->identity->setBlocked('alice', true);
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
        $this->identity->setBlocked('alice', false); $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'alice'");
        $this->denied(fn() => $this->service()->revoke('alice', self::ISSUER, 'press', 'readers'));
    }
    public function testReconciliationDoesNotUnblockOrRefreshStaleTargets(): void
    {
        $this->identity->setBlocked('bob', true); $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'bob'");
        self::assertTrue($this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
        $row = $this->pdo->query("SELECT status, groups_confirmed_at FROM identity_users WHERE id = 'bob'")->fetch();
        self::assertSame('blocked', $row['status']); self::assertSame(1, (int) $row['groups_confirmed_at']);
        self::assertFalse($this->allowed());
        self::assertSame(['readers'], $this->pdo->query("SELECT group_id FROM identity_memberships WHERE user_id = 'bob'")->fetchAll(\PDO::FETCH_COLUMN));
    }
    public function testIssuerAndExternalIdentifiersAreComparedExactly(): void
    {
        self::assertTrue($this->service()->grant('alice', self::ISSUER, 'Press', 'readers'));
        self::assertFalse($this->allowed());
        self::assertTrue($this->service()->grant('alice', self::ISSUER . '/', 'press', 'readers'));
        self::assertFalse($this->allowed());
    }
    public function testOneSourceCanMapToSeveralGroupsWithoutRemovingTheOtherEdge(): void
    {
        self::assertTrue($this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertTrue($this->service()->grant('alice', self::ISSUER, 'press', 'unassigned'));
        self::assertTrue($this->service()->revoke('alice', self::ISSUER, 'press', 'readers'));
        self::assertSame(['unassigned'], $this->identity->groupIds('bob'));
    }
    public function testDriftIsRepairedEvenWhenTheMappingEdgeAlreadyExists(): void
    {
        $this->pdo->exec("INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES ('https://id.example', 'press', 'readers')");
        $before = $this->version();
        self::assertTrue($this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertTrue($this->allowed()); self::assertSame($before + 1, $this->version());
        self::assertFalse($this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
    }
    public function testOrphanedSourceIsRemovedWithoutTouchingManualMembership(): void
    {
        $this->identity->addManualMembership('bob', 'readers');
        $this->pdo->exec("INSERT INTO identity_memberships (user_id, group_id, origin, source_group) VALUES ('bob', 'readers', 'external', 'orphan')");
        self::assertTrue($this->service()->revoke('alice', self::ISSUER, 'orphan', 'readers'));
        self::assertSame(['manual'], $this->pdo->query("SELECT origin FROM identity_memberships WHERE user_id = 'bob'")->fetchAll(\PDO::FETCH_COLUMN));
    }
    public function testValidatorSeesProposalButAuditAndOutboxContainOnlyMappingFingerprint(): void
    {
        $validator = new class implements ChangeValidator {
            public ?MutationContext $seen = null;
            public function validate(MutationContext $context): void { $this->seen = $context; }
        };
        $runtime = $this->boot([new Extension('example.mapping', static function (PluginRegistrar $r) use ($validator): void { $r->validator($validator); })]);
        self::assertTrue($this->service($runtime)->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertSame('press', $validator->seen->groupMapping->externalId);
        $expected = (new GroupMappingChange(self::ISSUER, 'press', 'readers', true))->mappingId();
        $audit = $this->pdo->query('SELECT targets FROM audit_events')->fetchColumn();
        self::assertSame(['group' => 'readers', 'mapping' => $expected], json_decode($audit, true));
        $payload = $this->pdo->query('SELECT payload FROM outbox_events')->fetchColumn();
        self::assertStringNotContainsString(self::ISSUER, $audit . $payload);
        self::assertStringNotContainsString('press', $audit . $payload);
    }
    public function testValidatorVetoLeavesMappingAndProjectionUnchanged(): void
    {
        $runtime = $this->boot([new Extension('example.veto', static function (PluginRegistrar $r): void {
            $r->validator(new class implements ChangeValidator { public function validate(MutationContext $context): void { throw new AccessDenied(); } });
        })]);
        $this->denied(fn() => $this->service($runtime)->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_group_mappings')->fetchColumn());
    }
    public function testDelegationIsRecheckedAfterValidators(): void
    {
        $pdo = $this->pdo;
        $runtime = $this->boot([new Extension('example.recheck', static function (PluginRegistrar $r) use ($pdo): void {
            $r->validator(new class($pdo) implements ChangeValidator {
                public function __construct(private \PDO $pdo) {}
                public function validate(MutationContext $context): void { $this->pdo->exec('DELETE FROM rbac_delegation_permissions'); $this->pdo->exec('DELETE FROM rbac_delegations'); }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->grant('alice', self::ISSUER, 'press', 'readers'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM rbac_delegations')->fetchColumn());
    }
    public function testLastUsableMappingAdministratorIsPreservedAfterSourceRemoval(): void
    {
        $this->pdo->exec("INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES ('https://id.example', 'staff', 'unassigned')");
        $this->identity->replaceVerifiedGroups('alice', ['staff']);
        $identity = $this->identity;
        $runtime = $this->boot([new Extension('example.continuity', static function (PluginRegistrar $r) use ($identity): void {
            $r->restriction(new class($identity) implements AccessRestriction {
                public function __construct(private PdoIdentityRepository $identity) {}
                public function decide(AuthorizationRequest $request): AuthorizationResult
                {
                    $deny = $request->action === 'identity.groups.mappings.manage' && !in_array('unassigned', $this->identity->groupIds($request->actorId), true);
                    return new AuthorizationResult($deny ? Decision::Deny : Decision::NotApplicable, 'test continuity');
                }
            });
        })]);
        $before = $this->version('alice');
        $this->denied(fn() => $this->service($runtime)->revoke('alice', self::ISSUER, 'staff', 'unassigned'));
        self::assertSame($before, $this->version('alice'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_group_mappings')->fetchColumn());
    }
    public function testAuditFailureRollsBackEdgeProjectionsAndVersions(): void
    { $this->storageFailure('audit_events'); }
    public function testOutboxFailureRollsBackEdgeProjectionsVersionsAndAudit(): void
    { $this->storageFailure('outbox_events'); }
    private function storageFailure(string $table): void
    {
        $before = $this->version();
        $this->pdo->exec("CREATE TRIGGER fail_mapping_storage BEFORE INSERT ON " . $table . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test veto'");
        try {
            try { $this->service()->grant('alice', self::ISSUER, 'press', 'readers'); self::fail('Expected storage failure.'); }
            catch (\PDOException) { self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame($before, $this->version()); self::assertFalse($this->allowed());
            foreach (['identity_group_mappings','audit_events','outbox_events'] as $t) { self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn()); }
        } finally { $this->pdo->exec('DROP TRIGGER fail_mapping_storage'); }
    }
    public function testVerifiedSnapshotRefreshUsesTheSameAdministrationLock(): void
    {
        $other = (new ConnectionFactory())->connect(AppConfig::fromGlobals()->database);
        $other->beginTransaction(); $other->query('SELECT id FROM administration_lock WHERE id = 1 FOR UPDATE')->fetchAll();
        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 1'); $before = $this->version();
        try {
            try { $this->identity->replaceVerifiedGroups('bob', ['new']); self::fail('Expected lock conflict.'); }
            catch (\PDOException $error) { self::assertSame(1205, $error->errorInfo[1]); }
            self::assertFalse($this->pdo->inTransaction()); self::assertSame($before, $this->version());
        } finally { $other->rollBack(); }
    }
    public function testUnknownGroupAndMissingSpaceFailClosed(): void
    {
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'press', 'missing'));
        $this->pdo->exec("DELETE FROM storage_spaces WHERE id = 'media'");
        $this->denied(fn() => $this->service()->grant('alice', self::ISSUER, 'press', 'readers'));
    }
    public function testInternalWriterRequiresAnExplicitTransaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->identity->changeGroupMappingInsideTransaction(new GroupMappingChange(self::ISSUER, 'press', 'readers', true));
    }
    private function controller(?string $claim='groups'): \Samla\Core\Web\GroupMappingController
    {
        $auth=new AuthorizationService($this->runtime,$this->identity);
        return new \Samla\Core\Web\GroupMappingController($this->service(),new \Samla\Core\Administration\AdministrationQueryService($this->pdo,$auth),self::ISSUER,$claim);
    }
    private function mappingRequest(string $method='GET',array $post=[],array $query=['group'=>'readers'],string $actor='alice',?string $claim='groups'): \Samla\Core\Web\Response
    {
        $csrf=str_repeat('a',64);
        return $this->controller($claim)->handle(new \Samla\Core\Web\Request($method,$method==='GET'?'/administration/group-mappings':'/administration/group-mappings/change',$query,$post),$actor,$csrf,
            ['actor'=>$actor,'csrf'=>$csrf,'caps'=>[],'flash'=>null,'oidc'=>true]);
    }
    private function mappingPost(array $changes=[]):array
    {return array_replace(['csrf'=>str_repeat('a',64),'group'=>'readers','operation'=>'grant','external'=>'press','confirm'=>'1'],$changes);}
    public function testMappingUiUsesNamesAndConfiguredIssuerAndPreservesOtherSources():void
    {
        $this->pdo->exec("UPDATE identity_groups SET name='Redaktion <Team>' WHERE id='readers'");
        $page=$this->mappingRequest();self::assertSame(200,$page->status);
        self::assertStringContainsString('Redaktion &lt;Team&gt;</option>',$page->body);
        self::assertStringContainsString('name="external"',$page->body);
        self::assertSame(303,$this->mappingRequest('POST',$this->mappingPost(['issuer'=>'https://attacker.example']))->status);
        self::assertTrue($this->allowed());
        self::assertSame(self::ISSUER,$this->pdo->query('SELECT issuer FROM identity_group_mappings')->fetchColumn());
        $edge=(new GroupMappingChange(self::ISSUER,'press','readers',false))->mappingId();
        $this->identity->addManualMembership('bob','readers');
        self::assertSame(303,$this->mappingRequest('POST',$this->mappingPost(['operation'=>'revoke','mapping'=>$edge]))->status);
        self::assertTrue($this->allowed());
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM identity_group_mappings')->fetchColumn());
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testMappingUiManualClaimModeCannotBreakNextLoginButCanRemoveExistingEdges():void
    {
        $page=$this->mappingRequest(claim:null);self::assertSame(200,$page->status);
        self::assertStringContainsString('OIDC_GROUPS_CLAIM',$page->body);self::assertStringNotContainsString('name="external"',$page->body);
        self::assertSame(409,$this->mappingRequest('POST',$this->mappingPost(),claim:null)->status);
        $this->service()->grant('alice',self::ISSUER,'press','readers');
        $edge=(new GroupMappingChange(self::ISSUER,'press','readers',false))->mappingId();
        self::assertSame(303,$this->mappingRequest('POST',$this->mappingPost(['operation'=>'revoke','mapping'=>$edge]),claim:null)->status);
        self::assertFalse($this->allowed());
    }
    public function testMappingUiRejectsUnauthorizedActorsCsrfAndMalformedSelections():void
    {
        self::assertSame(403,$this->mappingRequest(actor:'bob')->status);
        self::assertSame(403,$this->mappingRequest('POST',$this->mappingPost(['csrf'=>str_repeat('b',64)]))->status);
        foreach([['confirm'=>'0'],['group'=>['readers']],['external'=>"a\n"],['external'=>''],['external'=>str_repeat('x',257)],['external'=>"\xff"],['operation'=>'edit']]as$change){
            self::assertSame(400,$this->mappingRequest('POST',$this->mappingPost($change))->status);
        }
        self::assertSame(400,$this->mappingRequest(query:['group'=>['readers']])->status);
        self::assertSame(0,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testMappingUiProtectsAdministrativeGroupsAndEscapesExternalNames():void
    {
        self::assertSame(403,$this->mappingRequest('POST',$this->mappingPost(['group'=>'operators']))->status);
        self::assertSame(303,$this->mappingRequest('POST',$this->mappingPost(['external'=>'<script>bad</script>']))->status);
        $page=$this->mappingRequest();self::assertStringContainsString('&lt;script&gt;bad&lt;/script&gt;',$page->body);
        self::assertStringNotContainsString('<script>bad</script>',$page->body);
    }
    public function testMappingExpectedEdgeStateRejectsStaleFormsAtomically():void
    {
        $this->service()->grant('alice',self::ISSUER,'press','readers',false);
        self::assertSame(409,$this->mappingRequest('POST',$this->mappingPost())->status);
        $this->service()->revoke('alice',self::ISSUER,'press','readers',true);
        try{$this->service()->revoke('alice',self::ISSUER,'press','readers',true);self::fail('Stale edge accepted.');}
        catch(\Samla\PluginApi\MutationConflict){self::assertFalse($this->pdo->inTransaction());}
        self::assertSame(2,(int)$this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testMappingUiHonorsBoundedGroupAndEdgeChoicesAndMethods():void
    {
        $controller=$this->controller();$data=['actor'=>'alice','csrf'=>str_repeat('a',64),'caps'=>[],'flash'=>null];
        $response=$controller->handle(new \Samla\Core\Web\Request('POST','/administration/group-mappings'),'alice',str_repeat('a',64),$data);
        self::assertSame(405,$response->status);self::assertSame('GET',$response->headers['Allow']);
        $q=$this->pdo->prepare('INSERT INTO identity_group_mappings(issuer,external_id,internal_id) VALUES(?,?,?)');
        for($i=0;$i<100;$i++){$q->execute([self::ISSUER,'edge'.$i,'readers']);}
        self::assertSame(413,$this->mappingRequest('POST',$this->mappingPost())->status);
        try{$this->service()->grant('alice',self::ISSUER,'extra','readers',false);self::fail('Transactional mapping limit ignored.');}
        catch(\LengthException){self::assertFalse($this->pdo->inTransaction());}
        self::assertSame(100,(int)$this->pdo->query('SELECT COUNT(*) FROM identity_group_mappings')->fetchColumn());
        $q->execute([self::ISSUER,'overflow','readers']);self::assertSame(413,$this->mappingRequest()->status);
    }

}
