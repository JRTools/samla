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
use Samla\Core\Storage\{EntryName, StoragePlugin};
use Samla\PluginApi\{AccessDenied, AuthorizationRequest, Decision, MutationContext, Scope};
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};

try {
    if (count($argv) !== 5) { throw new \InvalidArgumentException(); }
    [, $actor, $group, $name, $quota] = $argv;
    foreach ([$actor, $group] as $id) { if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $id)) { throw new \InvalidArgumentException(); } }
    EntryName::validate($name);
    if (!ctype_digit($quota) || strlen($quota) > 12 || (int) $quota < 1 || (int) $quota > 1099511627776) { throw new \InvalidArgumentException(); }
    $config = AppConfig::fromGlobals(); if (!$config->maintenance) { throw new AccessDenied(); }
    $pdo = (new ConnectionFactory())->connect($config->database);
    $identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds); $roles = new PdoRoleRepository($pdo);
    $runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($roles, $identity)]);
    $authorization = new AuthorizationService($runtime, $identity);
    $space = 'space.' . bin2hex(random_bytes(16)); $role = 'files.' . bin2hex(random_bytes(16));
    $guard = static function () use ($actor, $group, $authorization, $pdo): void {
        foreach (['identity.users.read', 'identity.groups.memberships.manage'] as $action) {
            if ($authorization->decide(new AuthorizationRequest($actor, $action, Scope::Global))->decision !== Decision::Permit) { throw new AccessDenied(); }
        }
        $query = $pdo->prepare('SELECT 1 FROM identity_groups WHERE id = ?'); $query->execute([$group]);
        if ($query->fetchColumn() === false) { throw new \InvalidArgumentException(); }
    };
    (new PdoMutationRunner($pdo, $runtime))->run(new MutationContext(bin2hex(random_bytes(16)), $actor,
        'storage.space.create', Scope::Space, $space, ['group' => $group, 'role' => $role]), $guard,
        static function () use ($pdo, $space, $name, $quota, $role, $roles, $runtime, $group): bool {
            $query = $pdo->prepare('INSERT INTO storage_spaces (id, name, quota_bytes) VALUES (?, ?, ?)'); $query->execute([$space, $name, (int) $quota]);
            $roles->saveInsideTransaction(new Role($role, Scope::Space, ['files.list', 'files.read', 'files.create']), $runtime);
            $roles->assignInsideTransaction(new RoleAssignment($group, $role, Scope::Space, $space));
            return true;
        });
    fwrite(STDOUT, "Storage space created: " . $space . "\nExplicit group access: list, read, create.\n");
} catch (\Throwable) {
    fwrite(STDERR, "Space creation failed. Usage: php bin/storage-space-create.php ACTOR_ID GROUP_ID NAME QUOTA_BYTES\nRequire maintenance, a fresh administrator, an existing group and quota between 1 byte and 1 TiB.\n");
    exit(1);
}
