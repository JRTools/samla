<?php
declare(strict_types=1);

namespace Samla\Tests\Integration;

use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner, Transaction};
use Samla\Core\Identity\PdoIdentityRepository;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Core\Authorization\AuthorizationService;
use Samla\PluginApi\{AuthorizationRequest, Decision, SchemaMigration, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identities;
    private PdoRoleRepository $roles;
    private int $now = 2000000000;
    private function migrations(): array
    {
        return [...require __DIR__ . '/../../migrations/identity.php', ...require __DIR__ . '/../../plugins/rbac/migrations.php'];
    }
    protected function setUp(): void
    {
        // Deliberately fail instead of skipping when integration configuration is absent.
        $config = AppConfig::fromGlobals();
        if ($config->environment !== 'test') { throw new \RuntimeException('Integration tests require APP_ENV=test.'); }
        $this->pdo = (new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate($this->migrations());
        // Only inside the explicitly validated media_platform_test_* database.
        foreach (['rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups'] as $table) {
            $this->pdo->exec('DELETE FROM ' . $table);
        }
        $this->identities = new PdoIdentityRepository($this->pdo, 3600, fn(): int => $this->now);
        $this->roles = new PdoRoleRepository($this->pdo);
        $this->identities->createUser('alice', 'https://id.example', 'subject', 'Alice');
        $this->identities->createGroup('editors', 'Editors');
        $insert = $this->pdo->prepare('INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES (?, ?, ?)');
        $insert->execute(['https://id.example', 'board', 'editors']);
        $insert->execute(['https://id.example', 'press', 'editors']);
    }
    public function testMigrationsAreRecordedAndSecondRunDoesNothing(): void
    {
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM schema_migrations WHERE status = 'applied'")->fetchColumn());
        self::assertSame([], (new MigrationRunner($this->pdo))->migrate($this->migrations()));
    }
    public function testVerifiedSnapshotPreservesRemainingSourceAndManualMembership(): void
    {
        $this->identities->replaceVerifiedGroups('alice', ['board', 'press']);
        self::assertSame(['editors'], $this->identities->groupIds('alice'));
        $this->identities->replaceVerifiedGroups('alice', ['press']);
        self::assertSame(['editors'], $this->identities->groupIds('alice'));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_memberships')->fetchColumn());
        $this->identities->addManualMembership('alice', 'editors');
        $this->identities->addManualMembership('alice', 'editors');
        $this->identities->replaceVerifiedGroups('alice', []);
        self::assertSame(['editors'], $this->identities->groupIds('alice'));
        self::assertSame('manual', $this->pdo->query('SELECT origin FROM identity_memberships')->fetchColumn());
    }
    public function testMalformedClaimDoesNotDestroyPreviousMemberships(): void
    {
        $this->identities->replaceVerifiedGroups('alice', ['board']);
        try { $this->identities->replaceVerifiedGroups('alice', [7]); self::fail('Claim accepted'); }
        catch (\InvalidArgumentException) { self::assertSame(['editors'], $this->identities->groupIds('alice')); }
        self::assertSame(['board'], $this->pdo->query('SELECT external_id FROM identity_external_groups')->fetchAll(\PDO::FETCH_COLUMN));
    }
    public function testBlockedStaleFutureAndUnconfirmedUsersCannotAccess(): void
    {
        self::assertFalse($this->identities->isAccessAllowed('alice'));
        $this->identities->replaceVerifiedGroups('alice', ['board']);
        self::assertTrue($this->identities->isAccessAllowed('alice'));
        $this->identities->setBlocked('alice', true);
        self::assertFalse($this->identities->isAccessAllowed('alice'));
        self::assertSame([], $this->identities->groupIds('alice'));
        $this->identities->setBlocked('alice', false);
        $this->now += 3601;
        self::assertFalse($this->identities->isAccessAllowed('alice'));
        $this->now -= 3602;
        self::assertFalse($this->identities->isAccessAllowed('alice'));
        self::assertFalse((new PdoIdentityRepository($this->pdo, null))->isAccessAllowed('alice'));
    }
    public function testIdentityKeyIsExactAndUnique(): void
    {
        $this->identities->createUser('bob', 'https://id.example', 'Subject', 'Bob');
        $this->identities->createUser('carol', 'https://id.example', 'subject ', 'Carol');
        $this->expectException(\PDOException::class);
        $this->identities->createUser('duplicate', 'https://id.example', 'subject', 'Duplicate');
    }
    public function testMappingsAreIsolatedByIssuer(): void
    {
        $this->identities->createUser('bob', 'https://other.example', 'subject', 'Bob');
        $this->identities->replaceVerifiedGroups('bob', ['board']);
        self::assertSame([], $this->identities->groupIds('bob'));
    }
    public function testPersistentRbacAndBlockWorkThroughCentralService(): void
    {
        $this->identities->replaceVerifiedGroups('alice', ['board']);
        $runtime = PluginRuntime::boot([new StoragePlugin(), new RbacPlugin($this->roles, $this->identities)]);
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $runtime);
        $assignment = new RoleAssignment('editors', 'reader', Scope::Space, 'media');
        $this->roles->assign($assignment); $this->roles->assign($assignment);
        $service = new AuthorizationService($runtime, $this->identities);
        $request = new AuthorizationRequest('alice', 'files.read', Scope::Space, 'media');
        self::assertSame(Decision::Permit, $service->decide($request)->decision);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM rbac_assignments')->fetchColumn());
        self::assertSame(Decision::Deny, $service->decide(new AuthorizationRequest('alice', 'files.read', Scope::Space, 'other'))->decision);
        $this->roles->save(new Role('reader', Scope::Space, []), $runtime);
        self::assertSame(Decision::Deny, $service->decide($request)->decision);
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $runtime);
        $this->identities->setBlocked('alice', true);
        self::assertSame(Decision::Deny, $service->decide($request)->decision);
    }
    public function testUnknownPermissionAndScopeChangeOfAssignedRoleAreRejected(): void
    {
        $runtime = PluginRuntime::boot([new StoragePlugin(), new RbacPlugin($this->roles, $this->identities)]);
        try { $this->roles->save(new Role('reader', Scope::Space, ['missing.action']), $runtime); self::fail('Unknown permission accepted'); }
        catch (\InvalidArgumentException) { self::assertNull($this->roles->find('reader')); }
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $runtime);
        $this->roles->assign(new RoleAssignment('editors', 'reader', Scope::Space, 'media'));
        try { $this->roles->save(new Role('reader', Scope::Global, ['rbac.roles.manage']), $runtime); self::fail('Scope change accepted'); }
        catch (\LogicException) { self::assertSame(Scope::Space, $this->roles->find('reader')->scope); }
        self::assertSame(['files.read'], $this->roles->find('reader')->permissions);
    }
    public function testForeignKeyErrorRollsBackTransaction(): void
    {
        try { $this->identities->addManualMembership('alice', 'missing'); self::fail('Missing group accepted'); }
        catch (\PDOException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_memberships')->fetchColumn());
    }
    public function testNestedTransactionIsRejectedWithoutCommittingOuterTransaction(): void
    {
        $this->pdo->beginTransaction();
        try { Transaction::run($this->pdo, static fn() => null); self::fail('Nested transaction accepted'); }
        catch (\LogicException) { self::assertTrue($this->pdo->inTransaction()); }
        finally { $this->pdo->rollBack(); }
    }
    public function testMigrationFailureRemainsVisibleAndBlocksRetry(): void
    {
        $failing = new SchemaMigration('test.fixture', 'failure', ['CREATE TABLE migration_failure_probe (id INT)', 'INVALID SQL STATEMENT']);
        try {
            try { (new MigrationRunner($this->pdo))->migrate([...$this->migrations(), $failing]); self::fail('Invalid migration accepted'); }
            catch (\RuntimeException) { self::assertSame('failed', $this->pdo->query("SELECT status FROM schema_migrations WHERE owner = 'test.fixture'")->fetchColumn()); }
            try { (new MigrationRunner($this->pdo))->migrate([...$this->migrations(), $failing]); self::fail('Dirty migration retried'); }
            catch (\RuntimeException $error) { self::assertStringContainsString('unfinished', $error->getMessage()); }
        } finally {
            $this->pdo->exec('DROP TABLE IF EXISTS migration_failure_probe');
            $this->pdo->exec("DELETE FROM schema_migrations WHERE owner = 'test.fixture'");
        }
    }
    public function testMigrationChecksumDriftBlocksExecution(): void
    {
        $original = $this->migrations()[0];
        $changed = new SchemaMigration($original->owner, $original->id, ['SELECT 1']);
        $this->expectException(\RuntimeException::class);
        (new MigrationRunner($this->pdo))->migrate([$changed, $this->migrations()[1]]);
    }
    public function testAnotherConnectionCannotRunMigrationWhileLockHeld(): void
    {
        $other = (new ConnectionFactory())->connect(AppConfig::fromGlobals()->database);
        $name = 'mp_schema_' . substr(hash('sha256', AppConfig::fromGlobals()->database->database), 0, 40);
        $query = $other->prepare('SELECT GET_LOCK(?, 0)'); $query->execute([$name]);
        self::assertSame(1, (int) $query->fetchColumn());
        try {
            try { (new MigrationRunner($this->pdo))->migrate($this->migrations()); self::fail('Concurrent migration accepted'); }
            catch (\RuntimeException $error) { self::assertStringContainsString('lock unavailable', $error->getMessage()); }
        } finally { $query = $other->prepare('SELECT RELEASE_LOCK(?)'); $query->execute([$name]); }
    }
    public function testRoleUpdateRollsBackIfPermissionInsertFails(): void
    {
        $runtime = PluginRuntime::boot([new StoragePlugin(), new RbacPlugin($this->roles, $this->identities)]);
        $this->roles->save(new Role('reader', Scope::Space, ['files.read']), $runtime);
        $this->pdo->exec("CREATE TRIGGER test_fail_role_permission BEFORE INSERT ON rbac_role_permissions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'test failure'");
        try {
            try {
                $this->roles->save(new Role('reader', Scope::Space, ['files.create']), $runtime);
                self::fail('Database failure was ignored');
            } catch (\PDOException) {
                self::assertFalse($this->pdo->inTransaction());
                self::assertSame(['files.read'], $this->roles->find('reader')->permissions);
            }
        } finally { $this->pdo->exec('DROP TRIGGER test_fail_role_permission'); }
    }
}
