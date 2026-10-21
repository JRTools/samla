<?php
declare(strict_types=1);

namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner, Transaction};
use Samla\Core\Identity\{GroupDetailsService, IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\PluginApi\{AccessDenied, AccessRestriction, AuthorizationRequest, AuthorizationResult, ChangeValidator, Decision, GroupDetailsChange, MutationConflict, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class GroupDetailsTest extends TestCase
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
        $this->identity->createGroup('operators', 'Operators');
        $this->identity->createGroup('readers', 'Readers');
        $this->identity->addManualMembership('alice', 'operators');
        $this->runtime = $this->boot();
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.status.manage', 'identity.groups.memberships.manage', 'identity.groups.details.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('operators', 'operator', Scope::Global));
    }
    private function boot(array $plugins = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($this->roles, $this->identity), ...$plugins]); }
    private function service(?PluginRuntime $runtime = null): GroupDetailsService
    {
        $runtime ??= $this->runtime;
        return new GroupDetailsService($this->identity, new AuthorizationService($runtime, $this->identity), new PdoMutationRunner($this->pdo, $runtime));
    }
    private function groupName(string $id): string|false
    {
        $q = $this->pdo->prepare('SELECT name FROM identity_groups WHERE id = ?'); $q->execute([$id]); return $q->fetchColumn();
    }
    private function denied(\Closure $call): void
    {
        try { $call(); self::fail('Expected denial.'); } catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testCreationUsesGeneratedIdsWithoutImplicitMembershipOrRoles(): void
    {
        $id = $this->service()->create('alice', 'Team Älmhult');
        self::assertMatchesRegularExpression('/^grp\.[a-f0-9]{32}$/D', $id);
        self::assertSame('Team Älmhult', $this->groupName($id));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_memberships')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM rbac_assignments')->fetchColumn());
        $audit = $this->pdo->query('SELECT * FROM audit_events')->fetch();
        self::assertSame('identity.group.create', $audit['operation']);
        self::assertSame(['group' => $id], json_decode($audit['targets'], true));
        self::assertSame($audit['id'], $this->pdo->query('SELECT id FROM outbox_events')->fetchColumn());
    }
    public function testNamesAreLabelsAndCanBeDuplicated(): void
    {
        $one = $this->service()->create('alice', 'Media');
        $two = $this->service()->create('alice', 'Media');
        self::assertNotSame($one, $two);
        self::assertSame('Media', $this->groupName($one)); self::assertSame('Media', $this->groupName($two));
    }
    public function testRenamePreservesIdentifiersMembershipsMappingsRolesAndIdentityVersions(): void
    {
        $this->pdo->exec("INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES ('https://id.example', 'press', 'readers')");
        $this->identity->replaceVerifiedGroups('bob', ['press']);
        $this->identity->addManualMembership('bob', 'readers');
        $this->pdo->exec("INSERT INTO storage_spaces (id, name) VALUES ('media', 'Media')");
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('readers', 'reader', Scope::Space, 'media'));
        $tables = ['identity_users','identity_memberships','identity_group_mappings','identity_external_groups','rbac_assignments'];
        $before = [];
        foreach ($tables as $table) { $before[$table] = $this->pdo->query('SELECT * FROM ' . $table)->fetchAll(); }
        self::assertTrue($this->service()->rename('alice', 'readers', 'Readers', 'Presse und Medien'));
        foreach ($tables as $table) { self::assertSame($before[$table], $this->pdo->query('SELECT * FROM ' . $table)->fetchAll()); }
        self::assertSame('Presse und Medien', $this->groupName('readers'));
        $auth = new AuthorizationService($this->runtime, $this->identity);
        self::assertSame(Decision::Permit, $auth->decide(new AuthorizationRequest('bob', 'files.read', Scope::Space, 'media'))->decision);
    }
    public function testExactNoopIsAuditedWithoutCompletionEvent(): void
    {
        self::assertFalse($this->service()->rename('alice', 'readers', 'Readers', 'Readers'));
        self::assertSame(0, (int) $this->pdo->query('SELECT changed FROM audit_events')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testStaleExpectedNameCannotOverwriteANewerRename(): void
    {
        self::assertTrue($this->service()->rename('alice', 'readers', 'Readers', 'Press'));
        try { $this->service()->rename('alice', 'readers', 'Readers', 'Board'); self::fail('Expected conflict.'); }
        catch (MutationConflict) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame('Press', $this->groupName('readers'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testExpectedNameIsComparedExactlyRatherThanByDatabaseCollation(): void
    {
        try { $this->service()->rename('alice', 'readers', 'readers', 'Press'); self::fail('Expected conflict.'); }
        catch (MutationConflict) { self::assertSame('Readers', $this->groupName('readers')); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testCreateAndRenameRequireTheirOwnExplicitPermission(): void
    {
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.status.manage','identity.groups.memberships.manage']), $this->runtime);
        $this->denied(fn() => $this->service()->create('alice', 'Press'));
        $this->denied(fn() => $this->service()->rename('alice', 'readers', 'Readers', 'Press'));
    }
    public function testBlockedAndStaleActorsCannotChangeGroupDetails(): void
    {
        $this->identity->setBlocked('alice', true);
        $this->denied(fn() => $this->service()->create('alice', 'Press'));
        $this->identity->setBlocked('alice', false);
        $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'alice'");
        $this->denied(fn() => $this->service()->rename('alice', 'readers', 'Readers', 'Press'));
    }
    public function testValidatorCanInspectNamesAndVetoBeforeAnyWrite(): void
    {
        $validator = new class implements ChangeValidator {
            public ?MutationContext $seen = null;
            public function validate(MutationContext $context): void { $this->seen = $context; throw new AccessDenied(); }
        };
        $runtime = $this->boot([new Extension('example.names', static function (PluginRegistrar $r) use ($validator): void { $r->validator($validator); })]);
        $this->denied(fn() => $this->service($runtime)->rename('alice', 'readers', 'Readers', 'Protected Name'));
        self::assertSame('Protected Name', $validator->seen->groupDetails->name);
        self::assertSame('Readers', $validator->seen->groupDetails->expectedName);
        self::assertSame('Readers', $this->groupName('readers'));
    }
    public function testNamesAreExcludedFromAuditAndOutbox(): void
    {
        $id = $this->service()->create('alice', 'Private Label');
        self::assertTrue($this->service()->rename('alice', $id, 'Private Label', 'Another Private Label'));
        foreach ($this->pdo->query('SELECT targets FROM audit_events') as $row) { self::assertStringNotContainsString('Private Label', $row['targets']); }
        foreach ($this->pdo->query('SELECT payload FROM outbox_events') as $row) { self::assertStringNotContainsString('Private Label', $row['payload']); }
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
        $this->denied(fn() => $this->service($runtime)->create('alice', 'Press'));
        self::assertSame(['operators'], $this->identity->groupIds('alice'));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_groups')->fetchColumn());
    }
    public function testRenameCannotIndirectlyRemoveLastPreviouslyUsableAdministrator(): void
    {
        $pdo = $this->pdo;
        $runtime = $this->boot([new Extension('example.continuity', static function (PluginRegistrar $r) use ($pdo): void {
            $r->restriction(new class($pdo) implements AccessRestriction {
                public function __construct(private \PDO $pdo) {}
                public function decide(AuthorizationRequest $request): AuthorizationResult
                {
                    $changed = $this->pdo->query("SELECT name FROM identity_groups WHERE id = 'operators'")->fetchColumn() !== 'Operators';
                    return new AuthorizationResult($changed ? Decision::Deny : Decision::NotApplicable, 'test name restriction');
                }
            });
        })]);
        $this->denied(fn() => $this->service($runtime)->rename('alice', 'operators', 'Operators', 'Board'));
        self::assertSame('Operators', $this->groupName('operators'));
    }
    public function testUnknownTargetDoesNotLeaveAuditOrTransaction(): void
    {
        try { $this->service()->rename('alice', 'missing', 'Unknown', 'Board'); self::fail('Expected unknown group.'); }
        catch (\InvalidArgumentException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testInvalidInputDoesNotStartASuccessfulMutation(): void
    {
        try { $this->service()->create('alice', "Invalid\nName"); self::fail('Expected invalid name.'); }
        catch (\InvalidArgumentException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_groups')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testUnicodeDatabaseLimitIsAccepted(): void
    {
        $name = str_repeat('😀', 255);
        $id = $this->service()->create('alice', $name);
        self::assertSame($name, $this->groupName($id));
    }
    public function testAuditFailureRollsBackNewGroup(): void
    { $this->storageFailure('audit_events', true); }
    public function testOutboxFailureRollsBackRenameAndAudit(): void
    { $this->storageFailure('outbox_events', false); }
    private function storageFailure(string $table, bool $create): void
    {
        $this->pdo->exec("CREATE TRIGGER fail_group_details BEFORE INSERT ON " . $table . " FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test veto'");
        try {
            try {
                if ($create) { $this->service()->create('alice', 'Press'); }
                else { $this->service()->rename('alice', 'readers', 'Readers', 'Press'); }
                self::fail('Expected failure.');
            } catch (\PDOException) { self::assertFalse($this->pdo->inTransaction()); }
            self::assertSame('Readers', $this->groupName('readers'));
            self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_groups')->fetchColumn());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
        } finally { $this->pdo->exec('DROP TRIGGER fail_group_details'); }
    }
    public function testInternalWriterRequiresExplicitTransaction(): void
    {
        $this->expectException(\LogicException::class);
        $this->identity->changeGroupDetailsInsideTransaction('readers', new GroupDetailsChange('Press', 'Readers'));
    }
    public function testInternalCreateCannotReuseAnExistingIdentifier(): void
    {
        try {
            Transaction::run($this->pdo, fn(): bool => $this->identity->changeGroupDetailsInsideTransaction('readers', new GroupDetailsChange('Board')));
            self::fail('Expected conflict.');
        } catch (MutationConflict) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame('Readers', $this->groupName('readers'));
    }
}
