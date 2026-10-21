<?php
declare(strict_types=1);

namespace Samla\Tests\Unit;

use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\PluginApi\{AccessRestriction, AuthorizationProvider, AuthorizationRequest, AuthorizationResult, Decision, Permission, PluginRegistrar, Scope};
use Samla\Plugins\Rbac\{RbacPlugin, Role, RoleAssignment};
use Samla\Tests\Fixtures\{Extension, Identities, Roles};
use PHPUnit\Framework\TestCase;

final class AuthorizationTest extends TestCase
{
    private Identities $identities;
    private Roles $roles;
    protected function setUp(): void
    {
        $this->identities = new Identities();
        $this->roles = new Roles();
        $this->roles->roles['reader'] = new Role('reader', Scope::Space, ['files.read']);
        $this->roles->assignments[] = new RoleAssignment('editors', 'reader', Scope::Space, 'media');
    }
    private function request(string $action = 'files.read', string $space = 'media'): AuthorizationRequest
    { return new AuthorizationRequest('alice', $action, Scope::Space, $space); }
    private function service(array $extra = []): AuthorizationService
    {
        return new AuthorizationService(PluginRuntime::boot([
            new StoragePlugin(), new RbacPlugin($this->roles, $this->identities), ...$extra,
        ]), $this->identities);
    }
    public function testExplicitRoleAllowsRead(): void
    { self::assertSame(Decision::Permit, $this->service()->decide($this->request())->decision); }
    public function testMissingAssignmentDenies(): void
    {
        $this->roles->assignments = [];
        self::assertSame(Decision::Deny, $this->service()->decide($this->request())->decision);
    }
    public function testOtherSpaceDenies(): void
    { self::assertSame(Decision::Deny, $this->service()->decide($this->request(space: 'board'))->decision); }
    public function testRepositoryCannotReturnForeignGroupToGrantAccess(): void
    {
        $this->identities->groups = ['visitors'];
        self::assertSame(Decision::Deny, $this->service()->decide($this->request())->decision);
    }
    public function testLocalBlockTakesEffectWithoutRebuildingService(): void
    {
        $service = $this->service();
        self::assertSame(Decision::Permit, $service->decide($this->request())->decision);
        $this->identities->allowed = false;
        self::assertSame(Decision::Deny, $service->decide($this->request())->decision);
    }
    public function testMembershipRemovalTakesEffectImmediately(): void
    {
        $service = $this->service();
        $this->identities->groups = [];
        self::assertSame(Decision::Deny, $service->decide($this->request())->decision);
    }
    public function testUnknownActionDeniesEvenIfPresentInRole(): void
    {
        $this->roles->roles['reader'] = new Role('reader', Scope::Space, ['missing.action']);
        self::assertSame(Decision::Deny, $this->service()->decide($this->request('missing.action'))->decision);
    }
    public function testGlobalRoleDoesNotGrantSpaceAccess(): void
    {
        $this->roles->roles['admin'] = new Role('admin', Scope::Global, ['files.read']);
        $this->roles->assignments = [new RoleAssignment('editors', 'admin', Scope::Global)];
        self::assertSame(Decision::Deny, $this->service()->decide($this->request())->decision);
    }
    public function testRoleScopeMustMatchAssignment(): void
    {
        $this->roles->roles['reader'] = new Role('reader', Scope::Global, ['files.read']);
        self::assertSame(Decision::Deny, $this->service()->decide($this->request())->decision);
    }
    public function testRolesCombineWithinScope(): void
    {
        $this->roles->roles['writer'] = new Role('writer', Scope::Space, ['files.create']);
        $this->roles->assignments[] = new RoleAssignment('editors', 'writer', Scope::Space, 'media');
        self::assertSame(Decision::Permit, $this->service()->decide($this->request('files.create'))->decision);
        self::assertSame(Decision::Permit, $this->service()->decide($this->request())->decision);
    }
    public function testMissingProviderDenies(): void
    {
        $service = new AuthorizationService(PluginRuntime::boot([new StoragePlugin()]), $this->identities);
        self::assertSame('mandatory_provider_missing', $service->decide($this->request())->reason);
    }
    public function testNotApplicableMandatoryProviderDenies(): void
    {
        $plugin = new Extension('platform.rbac', static function (PluginRegistrar $r): void {
            $r->authorizationProvider(new class implements AuthorizationProvider {
                public function decide(AuthorizationRequest $r): AuthorizationResult { return new AuthorizationResult(Decision::NotApplicable, 'none'); }
            });
        });
        $service = new AuthorizationService(PluginRuntime::boot([new StoragePlugin(), $plugin]), $this->identities);
        self::assertSame(Decision::Deny, $service->decide($this->request())->decision);
    }
    private function restriction(Decision $decision, bool $throws = false): Extension
    {
        return new Extension('example.policy', static function (PluginRegistrar $r) use ($decision, $throws): void {
            $r->restriction(new class($decision, $throws) implements AccessRestriction {
                public function __construct(private Decision $decision, private bool $throws) {}
                public function decide(AuthorizationRequest $r): AuthorizationResult {
                    if ($this->throws) { throw new \RuntimeException('secret must not escape'); }
                    return new AuthorizationResult($this->decision, 'policy');
                }
            });
        });
    }
    public function testAdditionalDenyBlocksPermit(): void
    { self::assertSame(Decision::Deny, $this->service([$this->restriction(Decision::Deny)])->decide($this->request())->decision); }
    public function testAdditionalPermitCannotOverrideRbacDenial(): void
    {
        $this->roles->assignments = [];
        self::assertSame(Decision::Deny, $this->service([$this->restriction(Decision::Permit)])->decide($this->request())->decision);
    }
    public function testRestrictionExceptionDeniesWithoutLeakingDetails(): void
    {
        $result = $this->service([$this->restriction(Decision::Permit, true)])->decide($this->request());
        self::assertSame(Decision::Deny, $result->decision);
        self::assertSame('authorization_failed', $result->reason);
    }
    public function testProviderExceptionDenies(): void
    {
        $provider = new Extension('platform.rbac', static function (PluginRegistrar $r): void {
            $r->authorizationProvider(new class implements AuthorizationProvider {
                public function decide(AuthorizationRequest $r): AuthorizationResult { throw new \RuntimeException('db unavailable'); }
            });
        });
        $service = new AuthorizationService(PluginRuntime::boot([new StoragePlugin(), $provider]), $this->identities);
        self::assertSame('authorization_failed', $service->decide($this->request())->reason);
    }
    public function testNewPluginPermissionRequiresExplicitRoleAddition(): void
    {
        $extension = new Extension('example.metadata', static function (PluginRegistrar $r): void {
            $r->permission(new Permission('example.inspect', 'example.metadata', [Scope::Space]));
        });
        $service = $this->service([$extension]);
        self::assertSame(Decision::Deny, $service->decide($this->request('example.inspect'))->decision);
        $this->roles->roles['reader'] = new Role('reader', Scope::Space, ['files.read', 'example.inspect']);
        self::assertSame(Decision::Permit, $service->decide($this->request('example.inspect'))->decision);
        $withoutPlugin = $this->service();
        self::assertSame(Decision::Deny, $withoutPlugin->decide($this->request('example.inspect'))->decision);
    }
    public function testInvalidScopeCannotConstructRequest(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new AuthorizationRequest('alice', 'files.read', Scope::Global, 'media');
    }
    public function testWildcardRoleIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Role('admin', Scope::Space, ['*']);
    }
}
