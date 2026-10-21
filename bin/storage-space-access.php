<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';
use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Authorization\AuthorizationService;
use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\ConnectionFactory;
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\PluginApi\{AccessDenied, AuthorizationRequest, Decision, MutationContext, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
try {
    if (count($argv) !== 5) { throw new \InvalidArgumentException(); }
    [, $actor, $space, $group, $profile] = $argv;
    $profiles = ['read' => ['files.list','files.read'], 'contribute' => ['files.list','files.read','files.create'],
        'manage' => ['files.list','files.read','files.create','files.update','files.delete','files.restore'], 'history' => ['files.versions.read']];
    if (!isset($profiles[$profile])) { throw new \InvalidArgumentException(); }
    $config = AppConfig::fromGlobals(); if (!$config->maintenance) { throw new AccessDenied(); }
    $pdo = (new ConnectionFactory())->connect($config->database);
    $identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds); $roles = new PdoRoleRepository($pdo);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($roles, $identity)]);
    $authorization = new AuthorizationService($runtime, $identity); $role = 'samla.files.' . $profile; $permissions = $profiles[$profile];
    $guard = static function () use ($actor, $group, $space, $authorization, $pdo, $roles, $role, $permissions): void {
        foreach (['identity.users.read','identity.groups.memberships.manage'] as $action) {
            if ($authorization->decide(new AuthorizationRequest($actor, $action, Scope::Global))->decision !== Decision::Permit) { throw new AccessDenied(); }
        }
        $q = $pdo->prepare('SELECT 1 FROM storage_spaces WHERE id = ?'); $q->execute([$space]); if ($q->fetchColumn() === false) { throw new \InvalidArgumentException(); }
        $q = $pdo->prepare('SELECT 1 FROM identity_groups WHERE id = ?'); $q->execute([$group]); if ($q->fetchColumn() === false) { throw new \InvalidArgumentException(); }
        $old = $roles->find($role);
        if ($old !== null && ($old->scope !== Scope::Space || array_diff($old->permissions, $permissions) || array_diff($permissions, $old->permissions))) { throw new AccessDenied(); }
    };
    $changed = (new PdoMutationRunner($pdo, $runtime))->run(new MutationContext(bin2hex(random_bytes(16)), $actor,
        'storage.access.grant', Scope::Space, $space, ['group' => $group, 'role' => $role]), $guard,
        static function () use ($roles, $role, $permissions, $runtime, $group, $space): bool {
            $created = $roles->find($role) === null;
            if ($created) { $roles->saveInsideTransaction(new Role($role, Scope::Space, $permissions), $runtime); }
            return $roles->assignInsideTransaction(new RoleAssignment($group, $role, Scope::Space, $space)) || $created;
        });
    fwrite(STDOUT, $changed ? "Explicit space access granted.\n" : "Requested assignment already exists.\n");
} catch (\Throwable) { fwrite(STDERR, "Access setup failed. Usage: php bin/storage-space-access.php ACTOR_ID SPACE_ID GROUP_ID read|contribute|manage|history\nRequire maintenance and a fresh administrator. Existing reserved roles must match exactly.\n"); exit(1); }
