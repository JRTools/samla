<?php
declare(strict_types=1);
namespace Samla\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Samla\Core\Administration\{AdministrationQueryService, PdoMutationRunner, PdoResourceDirectory};
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{GroupDetailsService, GroupMembershipService, IdentityPlugin, PdoIdentityRepository, UserStatusService};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Core\Web\{AdminController, Request};
use Samla\PluginApi\{AccessDenied, AccessRestriction, AuthorizationRequest, AuthorizationResult, Decision, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacGroupMembershipPolicy, RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\Extension;

final class WebAdministrationTest extends TestCase
{
    private \PDO $pdo;
    private PdoIdentityRepository $identity;
    private PdoRoleRepository $roles;
    private PluginRuntime $runtime;
    private string $csrf;
    protected function setUp(): void
    {
        $config = AppConfig::fromGlobals();
        if ($config->environment !== 'test') { throw new \RuntimeException('APP_ENV=test is required.'); }
        $this->pdo = (new ConnectionFactory())->connect($config->database);
        (new MigrationRunner($this->pdo))->migrate(require __DIR__ . '/../../bootstrap/migrations.php');
        foreach (['outbox_events','audit_events','rbac_delegation_permissions','rbac_delegations','rbac_assignments','rbac_role_permissions','rbac_roles','identity_memberships','identity_external_groups','identity_group_mappings','identity_users','identity_groups','storage_spaces'] as $table) { $this->pdo->exec('DELETE FROM ' . $table); }
        $this->identity = new PdoIdentityRepository($this->pdo, 3600);
        $this->roles = new PdoRoleRepository($this->pdo); $this->csrf = str_repeat('a', 64);
        foreach (['admin','bob'] as $user) {
            $this->identity->createUser($user, 'https://id.example', $user, $user === 'bob' ? '<script>alert("x")</script>' : 'Admin');
            $this->identity->replaceVerifiedGroups($user, []);
        }
        $this->identity->createGroup('operators', 'Operators'); $this->identity->createGroup('team', 'Team');
        $this->identity->addManualMembership('admin', 'operators');
        $this->runtime = $this->boot();
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.read','identity.groups.read','identity.users.status.manage','identity.groups.details.manage','identity.groups.memberships.manage','identity.groups.mappings.manage']), $this->runtime);
        $this->roles->assign(new RoleAssignment('operators', 'operator', Scope::Global));
    }
    private function boot(array $plugins = []): PluginRuntime
    { return PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($this->roles, $this->identity), ...$plugins]); }
    private function controller(?PluginRuntime $runtime = null): AdminController
    {
        $runtime ??= $this->runtime; $auth = new AuthorizationService($runtime, $this->identity);
        $resources = new PdoResourceDirectory($this->pdo); $mutations = new PdoMutationRunner($this->pdo, $runtime);
        return new AdminController(new AdministrationQueryService($this->pdo, $auth), $auth,
            new UserStatusService($this->identity, $auth, $mutations), new GroupDetailsService($this->identity, $auth, $mutations),
            new GroupMembershipService($this->identity, $auth, new RbacGroupMembershipPolicy($this->roles, $this->identity, $auth, $runtime, $resources), $resources, $mutations));
    }
    private function post(string $path, array $post, string $actor = 'admin'): \Samla\Core\Web\Response
    { return $this->controller()->handle(new Request('POST', $path, post: $post + ['csrf' => $this->csrf]), $actor, $this->csrf); }
    public function testOverviewUsersAndGroupsRenderWithRealDirectoryData(): void
    {
        foreach (['/' => 'Dateien und Medien.', '/users' => 'Benutzerverzeichnis', '/groups' => 'Gruppenverzeichnis'] as $path => $label) {
            $response = $this->controller()->handle(new Request('GET', $path), 'admin', $this->csrf);
            self::assertSame(200, $response->status); self::assertStringContainsString($label, $response->body);
            self::assertStringContainsString('Lokaler Entwicklungszugang', $response->body);
        }
    }
    public function testNamesAreEscapedInTablesAndForms(): void
    {
        $this->identity->createGroup('hostile', '<img src=x onerror="x">');
        $users = $this->controller()->handle(new Request('GET', '/users'), 'admin', $this->csrf);
        $groups = $this->controller()->handle(new Request('GET', '/groups'), 'admin', $this->csrf);
        self::assertStringNotContainsString('<script>', $users->body);
        self::assertStringContainsString('&lt;script&gt;', $users->body);
        self::assertStringNotContainsString('<img src=x', $groups->body);
        self::assertStringContainsString('&lt;img src=x onerror=&quot;x&quot;&gt;', $groups->body);
    }
    public function testForgedCsrfCannotChangeAnything(): void
    {
        $response = $this->post('/groups/create', ['name' => 'Attack', 'csrf' => str_repeat('b', 64)]);
        self::assertSame(403, $response->status);
        self::assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM identity_groups')->fetchColumn());
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testSuccessfulCreationUsesPostRedirectGetAndServerActor(): void
    {
        $response = $this->post('/groups/create', ['name' => 'Presse', 'actor' => 'bob']);
        self::assertSame(303, $response->status); self::assertSame('/groups', $response->headers['Location']); self::assertNotNull($response->flash);
        self::assertSame('admin', $this->pdo->query('SELECT actor_id FROM audit_events')->fetchColumn());
        $page = $this->controller()->handle(new Request('GET', '/groups'), 'admin', $this->csrf, $response->flash);
        self::assertStringContainsString('Presse', $page->body); self::assertStringContainsString('Die Änderung wurde gespeichert.', $page->body);
    }
    public function testRenameAndStaleRenameConflictUseDomainService(): void
    {
        self::assertSame(303, $this->post('/groups/rename', ['group' => 'team','expected_name' => 'Team','name' => 'Press'])->status);
        self::assertSame(409, $this->post('/groups/rename', ['group' => 'team','expected_name' => 'Team','name' => 'Board'])->status);
        self::assertSame('Press', $this->pdo->query("SELECT name FROM identity_groups WHERE id = 'team'")->fetchColumn());
    }
    public function testStatusFormsApplyBlocksAndPreserveLastAdministrator(): void
    {
        self::assertSame(303, $this->post('/users/status', ['user' => 'bob','status' => 'blocked'])->status);
        self::assertFalse($this->identity->isAccessAllowed('bob'));
        self::assertSame(403, $this->post('/users/status', ['user' => 'admin','status' => 'blocked'])->status);
        self::assertTrue($this->identity->isAccessAllowed('admin'));
    }
    public function testMembershipFormsUseDelegationAndProtectAdministrativeGroups(): void
    {
        self::assertSame(303, $this->post('/memberships/change', ['group' => 'team','user' => 'bob','operation' => 'grant'])->status);
        self::assertSame(['team'], $this->identity->groupIds('bob'));
        self::assertSame(403, $this->post('/memberships/change', ['group' => 'operators','user' => 'bob','operation' => 'grant'])->status);
        self::assertSame(303, $this->post('/memberships/change', ['group' => 'team','user' => 'bob','operation' => 'revoke'])->status);
        self::assertSame([], $this->identity->groupIds('bob'));
    }
    public function testReadPermissionsAreExplicitAndDoNotFollowFromManagePermissions(): void
    {
        $this->roles->save(new Role('operator', Scope::Global, ['identity.groups.details.manage']), $this->runtime);
        self::assertSame(403, $this->controller()->handle(new Request('GET', '/groups'), 'admin', $this->csrf)->status);
        self::assertSame(403, $this->controller()->handle(new Request('GET', '/users'), 'admin', $this->csrf)->status);
    }
    public function testReadonlyUsersDoNotReceiveMutationFormsAndCannotSubmitForgedOnes(): void
    {
        $this->roles->save(new Role('operator', Scope::Global, ['identity.users.read','identity.groups.read']), $this->runtime);
        $page = $this->controller()->handle(new Request('GET', '/groups'), 'admin', $this->csrf);
        self::assertSame(200, $page->status); self::assertStringNotContainsString('<form', $page->body);
        self::assertSame(403, $this->post('/groups/create', ['name' => 'Press'])->status);
    }
    public function testPluginRestrictionCanDenyDirectoryReads(): void
    {
        $runtime = $this->boot([new Extension('example.directory', static function (PluginRegistrar $r): void {
            $r->restriction(new class implements AccessRestriction {
                public function decide(AuthorizationRequest $request): AuthorizationResult
                { return new AuthorizationResult($request->action === 'identity.users.read' ? Decision::Deny : Decision::NotApplicable, 'test directory'); }
            });
        })]);
        self::assertSame(403, $this->controller($runtime)->handle(new Request('GET', '/users'), 'admin', $this->csrf)->status);
    }
    public function testBlockedStaleAndUnknownActorsCannotReadTheDirectory(): void
    {
        self::assertSame(403, $this->controller()->handle(new Request('GET', '/users'), 'missing', $this->csrf)->status);
        $this->identity->setBlocked('admin', true);
        self::assertSame(403, $this->controller()->handle(new Request('GET', '/users'), 'admin', $this->csrf)->status);
        $this->identity->setBlocked('admin', false); $this->pdo->exec("UPDATE identity_users SET groups_confirmed_at = 1 WHERE id = 'admin'");
        self::assertSame(403, $this->controller()->handle(new Request('GET', '/groups'), 'admin', $this->csrf)->status);
    }
    public function testWrongMethodsUnknownRoutesAndInvalidFieldsDoNotMutate(): void
    {
        self::assertSame(405, $this->controller()->handle(new Request('GET', '/groups/create'), 'admin', $this->csrf)->status);
        self::assertSame(405, $this->post('/users', [])->status);
        self::assertSame(404, $this->controller()->handle(new Request('GET', '/missing'), 'admin', $this->csrf)->status);
        self::assertSame(400, $this->post('/users/status', ['user' => 'bob','status' => 'delete'])->status);
        self::assertSame(400, $this->post('/groups/create', ['name' => ['array']])->status);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM audit_events')->fetchColumn());
    }
    public function testDirectoryPagesAreBoundedStableAndDoNotDiscloseIdentityClaims(): void
    {
        for ($i = 0; $i < 55; $i++) { $this->identity->createUser(sprintf('u.%02d', $i), 'https://private-issuer.example', 'private-subject-' . $i, 'Person ' . $i); }
        $auth = new AuthorizationService($this->runtime, $this->identity); $directory = new AdministrationQueryService($this->pdo, $auth);
        $one = $directory->users('admin'); $two = $directory->users('admin', $one['next']);
        self::assertCount(50, $one['rows']); self::assertCount(7, $two['rows']); self::assertNull($two['next']);
        self::assertSame([], array_intersect(array_column($one['rows'], 'id'), array_column($two['rows'], 'id')));
        $html = $this->controller()->handle(new Request('GET', '/users'), 'admin', $this->csrf)->body;
        self::assertStringNotContainsString('private-issuer', $html); self::assertStringNotContainsString('private-subject', $html);
        self::assertStringContainsString('/users?after=', $html);
    }
    public function testMemberCountDeduplicatesManualAndExternalOrigins(): void
    {
        $this->identity->addManualMembership('bob', 'team');
        $this->pdo->exec("INSERT INTO identity_group_mappings (issuer, external_id, internal_id) VALUES ('https://id.example','press','team')");
        $this->identity->replaceVerifiedGroups('bob', ['press']);
        $directory = new AdministrationQueryService($this->pdo, new AuthorizationService($this->runtime, $this->identity));
        $rows = array_column($directory->groups('admin')['rows'], null, 'id');
        self::assertSame(1, (int) $rows['team']['members']);
    }
}
