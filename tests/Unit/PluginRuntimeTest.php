<?php
declare(strict_types=1);

namespace Samla\Tests\Unit;

use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\PluginApi\{AccessRestriction, AuthorizationProvider, AuthorizationRequest, AuthorizationResult, Decision, Permission, PluginRegistrar, Scope};
use Samla\Tests\Fixtures\Extension;
use PHPUnit\Framework\TestCase;

final class PluginRuntimeTest extends TestCase
{
    public function testDuplicatePluginRejected(): void
    {
        $this->expectException(\LogicException::class);
        PluginRuntime::boot([new StoragePlugin(), new StoragePlugin()]);
    }
    public function testIncompatiblePluginRejectedBeforeRegistration(): void
    {
        $this->expectException(\LogicException::class);
        PluginRuntime::boot([new Extension('example.future', static function (): void { throw new \RuntimeException('must not register'); }, 1)]);
    }
    public function testDuplicatePermissionRejected(): void
    {
        $this->expectException(\LogicException::class);
        PluginRuntime::boot([new Extension('example.one', static function (PluginRegistrar $r): void {
            $permission = new Permission('example.read', 'example.one', [Scope::Space]);
            $r->permission($permission); $r->permission($permission);
        })]);
    }
    public function testPermissionOwnerCannotBeImpersonated(): void
    {
        $this->expectException(\LogicException::class);
        PluginRuntime::boot([new Extension('example.one', static function (PluginRegistrar $r): void {
            $r->permission(new Permission('example.read', 'other.plugin', [Scope::Space]));
        })]);
    }
    public function testOtherPluginCannotReplaceMandatoryProvider(): void
    {
        $this->expectException(\LogicException::class);
        PluginRuntime::boot([new Extension('example.one', static function (PluginRegistrar $r): void {
            $r->authorizationProvider(new class implements AuthorizationProvider {
                public function decide(AuthorizationRequest $r): AuthorizationResult { return new AuthorizationResult(Decision::Permit, 'bad'); }
            });
        })]);
    }
    public function testRegistrarCannotBeUsedAfterBoot(): void
    {
        $saved = null;
        PluginRuntime::boot([new Extension('example.one', static function (PluginRegistrar $r) use (&$saved): void { $saved = $r; })]);
        $this->expectException(\LogicException::class);
        $saved->permission(new Permission('example.read', 'example.one', [Scope::Space]));
    }
    public function testRuntimeCannotBeMutatedAfterBoot(): void
    {
        $runtime = PluginRuntime::boot([new StoragePlugin()]);
        $this->expectException(\LogicException::class);
        $runtime->register(new Permission('files.delete', 'platform.storage', [Scope::Space]));
    }
    public function testRestrictionOrderIsDeterministic(): void
    {
        $seen = new \ArrayObject();
        $plugin = static function (string $id, int $priority) use ($seen): Extension {
            return new Extension($id, static function (PluginRegistrar $r) use ($id, $priority, $seen): void {
                $r->restriction(new class($id, $seen) implements AccessRestriction {
                    public function __construct(private string $id, private \ArrayObject $seen) {}
                    public function decide(AuthorizationRequest $r): AuthorizationResult {
                        $this->seen[] = $this->id;
                        return new AuthorizationResult(Decision::NotApplicable, 'none');
                    }
                }, $priority);
            });
        };
        $runtime = PluginRuntime::boot([$plugin('example.z', 0), $plugin('example.b', 10), $plugin('example.a', 10)]);
        foreach ($runtime->restrictions() as $restriction) { $restriction->decide(new AuthorizationRequest('a', 'files.read', Scope::Space, 's')); }
        self::assertSame(['example.a', 'example.b', 'example.z'], $seen->getArrayCopy());
    }
    public function testReferencePluginRegistersWithoutCoreChanges(): void
    {
        require_once __DIR__ . '/../../examples/reference-plugin/ReferencePlugin.php';
        $runtime = PluginRuntime::boot([new \Samla\Examples\ReferencePlugin()]);
        self::assertSame('example.reference', $runtime->find('example.inspect')->ownerPlugin);
        self::assertCount(1, $runtime->restrictions());
    }
}
