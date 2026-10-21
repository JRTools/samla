<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;
use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{AppPasswordService, IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Core\Web\{AppPasswordController, Request};
use Samla\PluginApi\{AccessDenied, ChangeValidator, MutationContext, PluginRegistrar};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin};
use Samla\Tests\Fixtures\Extension;

final class AppPasswordTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identity;
    private PluginRuntime $runtime;
    private int $now = 2000000000;
    protected function setUp(): void
    {
        $config = AppConfig::fromGlobals(); if ($config->environment !== 'test') { throw new \RuntimeException(); }
        $this->pdo = (new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__ . '/../../bootstrap/migrations.php');
        foreach (['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces','identity_app_auth_limits'] as $table) { $this->pdo->exec('DELETE FROM ' . $table); }
        $this->identity = new PdoIdentityRepository($this->pdo, 3600, fn(): int => $this->now);
        foreach (['alice','bob'] as $user) { $this->identity->createUser($user, 'https://id.example', $user, $user); $this->identity->replaceVerifiedGroups($user, []); }
        $this->runtime = $this->boot();
    }
    private function boot(array $extra = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin(new PdoRoleRepository($this->pdo), $this->identity), ...$extra]); }
    private function service(?PluginRuntime $runtime = null): AppPasswordService
    { return new AppPasswordService($this->pdo, $this->identity, new PdoMutationRunner($this->pdo, $runtime ?? $this->runtime), fn(): int => $this->now); }
    public function testSecretIsHashedAndNotPresentInAuditOrLists(): void
    {
        $service = $this->service(); $password = $service->create('alice', 'Laptop');
        self::assertMatchesRegularExpression('/^samla_[a-f0-9]{32}\.[a-f0-9]{64}$/D', $password);
        $row = $this->pdo->query('SELECT * FROM identity_app_passwords')->fetch(); $secret = explode('.', $password)[1];
        self::assertNotSame($secret, $row['password_hash']); self::assertTrue(password_verify($secret, $row['password_hash']));
        self::assertTrue($service->authenticate('alice', $password, '127.0.0.1'));
        self::assertFalse($service->authenticate('bob', $password, '127.0.0.1'));
        self::assertSame([], $service->listing('bob'));
        $persisted = json_encode([$service->listing('alice'), $this->pdo->query('SELECT * FROM audit_events')->fetchAll(), $this->pdo->query('SELECT * FROM outbox_events')->fetchAll()]);
        self::assertStringNotContainsString($password, $persisted); self::assertStringNotContainsString($secret, $persisted);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testRevocationIsImmediateAndIdempotent(): void
    {
        $service = $this->service(); $password = $service->create('alice', 'Laptop'); $id = $service->listing('alice')[0]['id'];
        self::assertTrue($service->revoke('alice', $id)); self::assertFalse($service->revoke('alice', $id));
        self::assertFalse($service->authenticate('alice', $password, '127.0.0.1'));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testAnotherAccountCannotRevokeOrEnumerate(): void
    {
        $service = $this->service(); $service->create('alice', 'Laptop'); $id = $service->listing('alice')[0]['id'];
        $this->expectException(AccessDenied::class); $service->revoke('bob', $id);
    }
    public function testBlockedOrStaleIdentityCannotAuthenticate(): void
    {
        $service = $this->service(); $password = $service->create('alice', 'Laptop');
        $this->identity->setBlocked('alice', true); self::assertFalse($service->authenticate('alice', $password, '127.0.0.1'));
        $this->identity->setBlocked('alice', false); $this->now += 3601;
        self::assertFalse($service->authenticate('alice', $password, '127.0.0.1'));
        $this->expectException(AccessDenied::class); $service->create('alice', 'No');
    }
    public function testExpiryIsIndependentOfFreshLogin(): void
    {
        $service = $this->service(); $password = $service->create('alice', 'One day', 1); $this->now += 86400;
        $this->identity->replaceVerifiedGroups('alice', []);
        self::assertFalse($service->authenticate('alice', $password, '127.0.0.1'));
    }
    public function testMalformedAndRepeatedWrongAttemptsAreBounded(): void
    {
        $service = $this->service(); $password = $service->create('alice', 'Laptop');
        for ($i = 0; $i < 10; ++$i) { self::assertFalse($service->authenticate('alice', 'invalid', '127.0.0.1')); }
        self::assertFalse($service->authenticate('alice', $password, '127.0.0.1'));
        $this->now += 60; self::assertTrue($service->authenticate('alice', $password, '127.0.0.1'));
        $before = (int) $this->pdo->query('SELECT COUNT(*) FROM identity_app_auth_limits')->fetchColumn();
        self::assertFalse($service->authenticate('unknown', $password, '127.0.0.1'));
        self::assertSame($before, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_app_auth_limits')->fetchColumn());
    }
    public function testPluginVetoRollsBackCredentialAndAudit(): void
    {
        $runtime = $this->boot([new Extension('test.veto', static function (PluginRegistrar $r): void {
            $r->validator(new class implements ChangeValidator {
                public function priority(): int { return 0; }
                public function validate(MutationContext $context): void { throw new AccessDenied(); }
            });
        })]);
        try { $this->service($runtime)->create('alice', 'Blocked'); self::fail('Veto ignored.'); } catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_app_passwords')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testOneTimeWebDisplayCsrfAndEscaping(): void
    {
        $controller = new AppPasswordController($this->service()); $csrf = str_repeat('a', 64); $data = ['actor' => 'alice', 'csrf' => $csrf, 'caps' => [], 'flash' => null, 'oidc' => true];
        self::assertSame(405, $controller->handle(new Request('GET','/account/app-passwords/create'), 'alice', $csrf, $data)->status);
        self::assertSame(403, $controller->handle(new Request('POST','/account/app-passwords/create', post: ['csrf'=>'bad']), 'alice', $csrf, $data)->status);
        $response = $controller->handle(new Request('POST','/account/app-passwords/create', post: ['csrf'=>$csrf,'label'=>'<script>','days'=>'30']), 'alice', $csrf, $data);
        self::assertSame(201, $response->status); self::assertSame('no-store', $response->headers['Cache-Control']);
        preg_match('/samla_[a-f0-9]{32}\.[a-f0-9]{64}/', $response->body, $matches); self::assertNotEmpty($matches);
        $page = $controller->handle(new Request('GET','/account/app-passwords'), 'alice', $csrf, $data);
        self::assertStringNotContainsString($matches[0], $page->body); self::assertStringContainsString('&lt;script&gt;', $page->body); self::assertStringNotContainsString('<script>', $page->body);
    }
}
