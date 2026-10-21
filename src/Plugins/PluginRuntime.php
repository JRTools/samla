<?php
declare(strict_types=1);

namespace Samla\Core\Plugins;

use Samla\PluginApi\{AccessRestriction, AuthorizationProvider, ChangeValidator, Permission, PermissionRegistry, Plugin, PluginRegistrar};

final class PluginRuntime implements PermissionRegistry
{
    public const API_MAJOR = 0;
    private const MANDATORY_PROVIDER = 'platform.rbac';
    /** @var array<string, Permission> */
    private array $permissions = [];
    /** @var array<string, true> */
    private array $active = [];
    private ?AuthorizationProvider $provider = null;
    private bool $sealed = false;
    /** @var list<array{plugin: string, priority: int, index: int, restriction: AccessRestriction}> */
    private array $restrictions = [];
    /** @var list<array{plugin: string, priority: int, index: int, validator: ChangeValidator}> */
    private array $validators = [];

    private function __construct() {}

    /** Build atomically: a failed build must never be published for use. @param list<Plugin> $plugins */
    public static function boot(array $plugins): self
    {
        $runtime = new self();
        foreach ($plugins as $plugin) {
            $id = $plugin->id();
            if (!preg_match('/^[a-z][a-z0-9_-]*(\.[a-z][a-z0-9_-]*)+$/D', $id) || isset($runtime->active[$id])) {
                throw new \LogicException('Invalid or duplicate plugin ID.');
            }
            if ($plugin->apiMajor() !== self::API_MAJOR) { throw new \LogicException('Incompatible plugin API: ' . $id); }
            $runtime->active[$id] = true;
            $registrar = new class($runtime, $id) implements PluginRegistrar {
                private bool $open = true;
                public function __construct(private PluginRuntime $runtime, private string $owner) {}
                public function close(): void { $this->open = false; }
                private function check(): void { if (!$this->open) { throw new \LogicException('Registration is closed.'); } }
                public function permission(Permission $permission): void {
                    $this->check();
                    if ($permission->ownerPlugin !== $this->owner) { throw new \LogicException('Permission owner mismatch.'); }
                    $this->runtime->register($permission);
                }
                public function authorizationProvider(AuthorizationProvider $provider): void {
                    $this->check(); $this->runtime->setProvider($this->owner, $provider);
                }
                public function validator(ChangeValidator $validator, int $priority = 0): void {
                    $this->check(); $this->runtime->addValidator($this->owner, $validator, $priority);
                }
                public function restriction(AccessRestriction $restriction, int $priority = 0): void {
                    $this->check(); $this->runtime->addRestriction($this->owner, $restriction, $priority);
                }
            };
            try { $plugin->register($registrar); } finally { $registrar->close(); }
        }
        usort($runtime->restrictions, static fn(array $a, array $b): int =>
            ($b['priority'] <=> $a['priority']) ?: strcmp($a['plugin'], $b['plugin']) ?: ($a['index'] <=> $b['index']));
        usort($runtime->validators, static fn(array $a, array $b): int =>
            ($b['priority'] <=> $a['priority']) ?: strcmp($a['plugin'], $b['plugin']) ?: ($a['index'] <=> $b['index']));
        $runtime->sealed = true;
        return $runtime;
    }

    public function register(Permission $permission): void
    {
        if ($this->sealed) { throw new \LogicException('Registration is closed.'); }
        if (!isset($this->active[$permission->ownerPlugin])) { throw new \LogicException('Inactive permission owner.'); }
        if (isset($this->permissions[$permission->id])) { throw new \LogicException('Duplicate permission.'); }
        $this->permissions[$permission->id] = $permission;
    }
    public function find(string $id): ?Permission { return $this->permissions[$id] ?? null; }
    public function provider(): ?AuthorizationProvider { return $this->provider; }
    /** @return list<AccessRestriction> */
    public function restrictions(): array { return array_column($this->restrictions, 'restriction'); }
    public function setProvider(string $owner, AuthorizationProvider $provider): void
    {
        if ($this->sealed) { throw new \LogicException('Registration is closed.'); }
        if ($owner !== self::MANDATORY_PROVIDER || $this->provider !== null) { throw new \LogicException('Invalid authorization provider registration.'); }
        $this->provider = $provider;
    }
    public function addRestriction(string $owner, AccessRestriction $restriction, int $priority): void
    {
        if ($this->sealed) { throw new \LogicException('Registration is closed.'); }
        $this->restrictions[] = ['plugin' => $owner, 'priority' => $priority, 'index' => count($this->restrictions), 'restriction' => $restriction];
    }
    /** @return list<ChangeValidator> */
    public function validators(): array { return array_column($this->validators, 'validator'); }
    public function addValidator(string $owner, ChangeValidator $validator, int $priority): void
    {
        if ($this->sealed) { throw new \LogicException('Registration is closed.'); }
        $this->validators[] = ['plugin' => $owner, 'priority' => $priority, 'index' => count($this->validators), 'validator' => $validator];
    }
}
