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
use Samla\Core\Storage\{FileService, LocalContentStore, StoragePlugin};
use Samla\Core\Web\{FileController, Request};
use Samla\PluginApi\{AccessDenied, ChangeValidator, MutationConflict, MutationContext, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class FileStorageTest extends TestCase
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
        $this->roles->save(new Role('editor', Scope::Space, ['files.list','files.read','files.create']), $this->runtime);
        $this->roles->assign(new RoleAssignment('editors', 'editor', Scope::Space, 'a'));
        $this->root = sys_get_temp_dir() . '/samla-files-' . bin2hex(random_bytes(8)); mkdir($this->root, 0700);
    }
    protected function tearDown(): void
    {
        foreach (new \DirectoryIterator($this->root) as $file) { if (!$file->isDot()) { unlink($file->getPathname()); } }
        rmdir($this->root);
    }
    private function boot(array $extra = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($this->roles, $this->identities), ...$extra]); }
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
    public function testNestedUploadRoundtripAndAuditOutbox(): void
    {
        $folder = $this->service()->createFolder('alice', 'a', '', 'Älmhult');
        $id = $this->upload('Bild.php', "<?php echo 'never execute';\0", $folder);
        $result = $this->service()->download('alice', 'a', $id);
        try { self::assertSame("<?php echo 'never execute';\0", stream_get_contents($result['stream'])); } finally { fclose($result['stream']); }
        self::assertSame('Bild.php', $result['row']['name']);
        self::assertSame(hash('sha256', "<?php echo 'never execute';\0"), $result['row']['sha256']);
        self::assertSame($id, $this->service()->listing('alice', 'a', $folder)['rows'][0]['id']);
        self::assertSame(['storage.file.upload','storage.folder.create'], $this->pdo->query('SELECT operation FROM audit_events ORDER BY operation')->fetchAll(\PDO::FETCH_COLUMN));
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM outbox_events')->fetchColumn());
    }
    public function testUnassignedSpacesNeverAppearAndGlobalAdminDoesNotImplyFileAccess(): void
    {
        $this->roles->save(new Role('global', Scope::Global, ['identity.users.read']), $this->runtime);
        $this->roles->assign(new RoleAssignment('editors', 'global', Scope::Global));
        self::assertSame(['a'], array_column($this->service()->spaces('alice')['rows'], 'id'));
        self::assertSame([], $this->service()->spaces('bob')['rows']);
        $this->expectException(AccessDenied::class); $this->service()->listing('alice', 'b');
    }
    public function testUnauthorizedUploadDoesNotConsumeInputOrCreateObjects(): void
    {
        $stream = fopen('php://temp', 'w+b'); fwrite($stream, 'secret'); rewind($stream);
        try { $this->service()->upload('bob', 'a', '', 'x', $stream); self::fail('Expected denial.'); }
        catch (AccessDenied) { self::assertSame(0, ftell($stream)); self::assertSame([], glob($this->root . '/*')); }
        finally { fclose($stream); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testParentMustBeFolderInTheSameSpace(): void
    {
        $file = $this->upload('file', 'data');
        $this->expectException(\InvalidArgumentException::class); $this->service()->createFolder('alice', 'a', $file, 'child');
    }
    public function testCrossSpaceDownloadIsDeniedEvenWithKnownIdentifier(): void
    {
        $id = $this->upload('file', 'data');
        $this->expectException(AccessDenied::class); $this->service()->download('alice', 'b', $id);
    }
    public function testExistingSiblingIsNeverOverwrittenAndRollbackHasNoExtraAudit(): void
    {
        $id = $this->upload('file', 'old');
        try { $this->upload('file', 'new'); self::fail('Expected conflict.'); } catch (MutationConflict) { self::assertFalse($this->pdo->inTransaction()); }
        $result = $this->service()->download('alice', 'a', $id);
        try { self::assertSame('old', stream_get_contents($result['stream'])); } finally { fclose($result['stream']); }
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testQuotaCannotBeExceededAndEmptyFilesAreSupported(): void
    {
        $this->upload('full', str_repeat('a', 100)); $this->upload('empty', '');
        try { $this->upload('overflow', 'a'); self::fail('Expected quota failure.'); } catch (\LengthException) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(100, $this->service()->listing('alice', 'a')['used']);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
    public function testPluginCanVetoPublicationAndAuditRollsBack(): void
    {
        $runtime = $this->boot([new Extension('test.veto', static function (PluginRegistrar $r): void {
            $r->validator(new class implements ChangeValidator {
                public function priority(): int { return 0; }
                public function validate(MutationContext $context): void { if ($context->operation === 'storage.file.upload') { throw new AccessDenied(); } }
            });
        })]);
        try { $this->upload('blocked', 'data', '', $this->service($runtime)); self::fail('Expected veto.'); } catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
        self::assertCount(1, glob($this->root . '/*')); // Safe orphan, never a visible file or uncertain deletion.
    }
    public function testGuardRunsAgainAfterValidatorRevokesMembership(): void
    {
        $pdo = $this->pdo;
        $runtime = $this->boot([new Extension('test.revoke', static function (PluginRegistrar $r) use ($pdo): void {
            $r->validator(new class($pdo) implements ChangeValidator {
                public function __construct(private \PDO $pdo) {}
                public function priority(): int { return 0; }
                public function validate(MutationContext $context): void { $this->pdo->exec('DELETE FROM identity_memberships'); }
            });
        })]);
        try { $this->service($runtime)->createFolder('alice', 'a', '', 'denied'); self::fail('Expected denial.'); } catch (AccessDenied) { self::assertFalse($this->pdo->inTransaction()); }
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_memberships')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM storage_entries')->fetchColumn());
    }
    public function testBlockingImmediatelyRevokesDownload(): void
    {
        $id = $this->upload('file', 'data'); $this->identities->setBlocked('alice', true);
        $this->expectException(AccessDenied::class); $this->service()->download('alice', 'a', $id);
    }
    public function testWebCsrfMethodsEscapingAndDownloadHeaders(): void
    {
        $controller = new FileController($this->service()); $csrf = str_repeat('a', 64);
        $data = ['actor' => 'alice', 'csrf' => $csrf, 'caps' => [], 'flash' => null];
        self::assertSame(405, $controller->handle(new Request('GET', '/files/folder'), 'alice', $csrf, $data)->status);
        self::assertSame(403, $controller->handle(new Request('POST', '/files/folder', post: ['csrf' => str_repeat('b', 64)]), 'alice', $csrf, $data)->status);
        $id = $this->upload('<script>.html', '<script>alert(1)</script>');
        $page = $controller->handle(new Request('GET', '/files', ['space' => 'a']), 'alice', $csrf, $data);
        self::assertSame(200, $page->status); self::assertStringContainsString('&lt;script&gt;.html', $page->body); self::assertStringNotContainsString('<script>', $page->body);
        $download = $controller->handle(new Request('GET', '/files/download', ['space' => 'a', 'entry' => $id]), 'alice', $csrf, $data);
        self::assertSame('application/octet-stream', $download->headers['Content-Type']);
        self::assertStringStartsWith('attachment;', $download->headers['Content-Disposition']);
        ob_start(); ($download->stream)(); self::assertSame('<script>alert(1)</script>', ob_get_clean());
    }
    public function testMissingContentFailsClosed(): void
    {
        $id = $this->upload('file', 'data'); foreach (glob($this->root . '/*') as $path) { unlink($path); }
        $this->expectException(\RuntimeException::class); $this->service()->download('alice', 'a', $id);
    }
}
