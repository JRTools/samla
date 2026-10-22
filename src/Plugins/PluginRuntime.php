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
    /** @var array<string, \Samla\PluginApi\OutboxSubscription> */
    private array $subscribers = [];
    private array $workerTasks=[];
    private array $exportProviders=[];
    private array $mediaFields=[];
    private array $mediaProviders=[];

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
                public function outboxSubscriber(\Samla\PluginApi\OutboxSubscription $subscription): void {
                    $this->check(); $this->runtime->addSubscriber($this->owner, $subscription);
                }
                public function workerTask(\Samla\PluginApi\WorkerTask $task):void
                {$this->check();$this->runtime->addWorkerTask($this->owner,$task);}
                public function exportProvider(\Samla\PluginApi\ExportMetadataProvider $provider):void
                {$this->check();$this->runtime->addExportProvider($this->owner,$provider);}
                public function mediaField(\Samla\PluginApi\MediaField $field):void
                {$this->check();$this->runtime->addMediaField($this->owner,$field);}
                public function mediaProvider(\Samla\PluginApi\MediaProvider $provider,int $priority=0):void
                {$this->check();$this->runtime->addMediaProvider($this->owner,$provider,$priority);}
            };
            try { $plugin->register($registrar); } finally { $registrar->close(); }
        }
        usort($runtime->restrictions, static fn(array $a, array $b): int =>
            ($b['priority'] <=> $a['priority']) ?: strcmp($a['plugin'], $b['plugin']) ?: ($a['index'] <=> $b['index']));
        usort($runtime->validators, static fn(array $a, array $b): int =>
            ($b['priority'] <=> $a['priority']) ?: strcmp($a['plugin'], $b['plugin']) ?: ($a['index'] <=> $b['index']));
        foreach($runtime->mediaFields as$entry){$field=$entry['field'];$permission=$runtime->find($field->readPermission);
            if($permission===null||!in_array(\Samla\PluginApi\Scope::Space,$permission->scopes,true)
                ||($permission->ownerPlugin!==$entry['owner']&&($field->sensitive||$field->readPermission!=='media.metadata.read'))){throw new \LogicException('Invalid field permission.');}}
        uasort($runtime->mediaProviders,static fn(array $a,array $b):int=>($b['priority']<=>$a['priority'])?:strcmp($a['provider']->id(),$b['provider']->id()));
        ksort($runtime->workerTasks,SORT_STRING);
        ksort($runtime->exportProviders,SORT_STRING);
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
    public function addSubscriber(string $owner, \Samla\PluginApi\OutboxSubscription $subscription): void
    {
        if ($this->sealed || !isset($this->active[$owner]) || !str_starts_with($subscription->id, $owner . '.')
            || isset($this->subscribers[$subscription->id]) || count($this->subscribers) >= 32) { throw new \LogicException('Invalid outbox subscriber registration.'); }
        $this->subscribers[$subscription->id] = $subscription;
    }
    /** @return array<string, \Samla\PluginApi\OutboxSubscription> */
    public function subscribers(): array { return $this->subscribers; }
    public function addWorkerTask(string $owner,\Samla\PluginApi\WorkerTask $task):void
    {
        if($this->sealed||!isset($this->active[$owner])||!str_starts_with($task->id,$owner.'.')||isset($this->workerTasks[$task->id])||count($this->workerTasks)>=8){throw new \LogicException('Invalid worker task registration.');}
        $this->workerTasks[$task->id]=$task;
    }
    /** @return list<\Samla\PluginApi\WorkerTask> */
    public function workerTasks():array{return array_values($this->workerTasks);}
    public function addExportProvider(string $owner,\Samla\PluginApi\ExportMetadataProvider $provider):void
    {
        $id=$provider->id();$version=$provider->version();
        if($this->sealed||!isset($this->active[$owner])||!str_starts_with($id,$owner.'.')
            ||!preg_match('/^[a-z][a-z0-9_.]{1,95}$/D',$id)||!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$version)
            ||isset($this->exportProviders[$id])||count($this->exportProviders)>=8){throw new \LogicException('Invalid export provider.');}
        $this->exportProviders[$id]=['owner'=>$owner,'provider'=>$provider];
    }
    /** @return list<array{owner:string,provider:\Samla\PluginApi\ExportMetadataProvider}> */
    public function exportProviders():array{return array_values($this->exportProviders);}
    public function addMediaField(string $owner,\Samla\PluginApi\MediaField $field):void
    {
        if($this->sealed||!isset($this->active[$owner])||!str_starts_with($field->id,$owner.'.')||isset($this->mediaFields[$field->id])||count($this->mediaFields)>=64){throw new \LogicException('Invalid media field registration.');}
        $this->mediaFields[$field->id]=['owner'=>$owner,'field'=>$field];
    }
    public function addMediaProvider(string $owner,\Samla\PluginApi\MediaProvider $provider,int $priority):void
    {
        $id=$provider->id();$version=$provider->version();
        if($this->sealed||!isset($this->active[$owner])||!preg_match('/^[a-z][a-z0-9_.]{1,95}$/D',$id)||!str_starts_with($id,$owner.'.')||isset($this->mediaProviders[$id])||count($this->mediaProviders)>=8
            ||!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D',$version)||abs($priority)>1000){throw new \LogicException('Invalid media provider registration.');}
        $this->mediaProviders[$id]=['owner'=>$owner,'provider'=>$provider,'priority'=>$priority];
    }
    /** @return array<string,\Samla\PluginApi\MediaField> */
    public function mediaFields():array{return array_map(static fn(array $entry)=>$entry['field'],$this->mediaFields);}
    /** @return list<\Samla\PluginApi\MediaProvider> */
    public function mediaProviders():array{return array_values(array_column($this->mediaProviders,'provider'));}
}
