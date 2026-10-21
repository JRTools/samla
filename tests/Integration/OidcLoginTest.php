<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Identity\Oidc\{LoginProvisioner, VerifiedLogin};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin};
use Samla\PluginApi\{AccessDenied, ChangeValidator, MutationContext, PluginRegistrar};
use Samla\Tests\Fixtures\Extension;

final class OidcLoginTest extends TestCase
{
    private \PDO $pdo; private PdoIdentityRepository $identity;
    protected function setUp(): void
    {
        $config = AppConfig::fromGlobals();
        if ($config->environment !== 'test') { throw new \RuntimeException('Isolated test environment required.'); }
        $this->pdo = (new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__ . '/../../bootstrap/migrations.php');
        foreach (['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles',
            'identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table) { $this->pdo->exec('DELETE FROM ' . $table); }
        $this->identity = new PdoIdentityRepository($this->pdo, 3600);
    }
    private function provisioner(array $extra = []): LoginProvisioner
    {
        $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin(new PdoRoleRepository($this->pdo), $this->identity), ...$extra]);
        return new LoginProvisioner($this->pdo, $this->identity, new PdoMutationRunner($this->pdo, $runtime));
    }
    private function login(bool $external = false, array $groups = [], string $name = 'Test User'): VerifiedLogin
    { return new VerifiedLogin('https://id.example', 'subject', $name, $groups, $external, time() + 300); }
    public function testNewLoginIsStableAuditedAndDoesNotCreateAnyRoles(): void
    {
        $id = $this->provisioner()->login($this->login());
        self::assertSame(64, strlen($id)); self::assertTrue($this->identity->isAccessAllowed($id));
        self::assertSame($id, $this->provisioner()->login($this->login(name: 'Renamed')));
        self::assertSame('Renamed', $this->pdo->query('SELECT display_name FROM identity_users')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM rbac_assignments')->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query("SELECT COUNT(*) FROM audit_events WHERE operation = 'identity.login'")->fetchColumn());
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
        self::assertStringNotContainsString('subject', $this->pdo->query('SELECT payload FROM outbox_events LIMIT 1')->fetchColumn());
    }
    public function testVerifiedGroupsSynchronizeAndPreserveManualMembership(): void
    {
        $this->identity->createUser('existing', 'https://id.example', 'subject', 'Existing');
        $this->identity->createGroup('manual', 'Manual'); $this->identity->createGroup('external', 'External');
        $this->identity->addManualMembership('existing', 'manual');
        $this->pdo->exec("INSERT INTO identity_group_mappings VALUES ('https://id.example', 'team', 'external')");
        self::assertSame('existing', $this->provisioner()->login($this->login(true, ['team'])));
        self::assertSame(['external','manual'], $this->identity->groupIds('existing'));
        $this->provisioner()->login($this->login(true, []));
        self::assertSame(['manual'], $this->identity->groupIds('existing'));
    }
    public function testManualModeRefusesExternalMappingsWithoutChangingExistingSnapshots(): void
    {
        $this->identity->createGroup('external', 'External');
        $this->pdo->exec("INSERT INTO identity_group_mappings VALUES ('https://id.example', 'team', 'external')");
        try { $this->provisioner()->login($this->login()); self::fail('Mapping silently ignored'); }
        catch (AccessDenied) { self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_users')->fetchColumn()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testBlockedAccountIsNeverUnblockedByLogin(): void
    {
        $id = $this->provisioner()->login($this->login()); $this->identity->setBlocked($id, true);
        try { $this->provisioner()->login($this->login()); self::fail('Blocked account signed in'); }
        catch (AccessDenied) { self::assertFalse($this->identity->isAccessAllowed($id)); }
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testPluginVetoRollsBackProvisioningAndAudit(): void
    {
        $extension = new Extension('test.login', static function (PluginRegistrar $registrar): void {
            $registrar->validator(new class implements ChangeValidator {
                public function validate(MutationContext $context): void { if ($context->operation === 'identity.login') { throw new AccessDenied(); } }
            });
        });
        try { $this->provisioner([$extension])->login($this->login()); self::fail('Veto ignored'); }
        catch (AccessDenied) { self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_users')->fetchColumn()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testExpiredVerifiedIdentityCannotBeProvisioned(): void
    {
        $this->expectException(AccessDenied::class);
        $this->provisioner()->login(new VerifiedLogin('https://id.example', 'subject', 'User', [], false, time() - 1));
    }
}
