<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';

use Samla\Core\Administration\PdoMutationRunner;
use Samla\Core\Configuration\{AppConfig, OidcConfig};
use Samla\Core\Database\ConnectionFactory;
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use Samla\PluginApi\{MutationContext, Scope};

try {
    $id = $argv[1] ?? '';
    if (count($argv) !== 2 || !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $id)) { throw new \RuntimeException(); }
    $app = AppConfig::fromGlobals(); $oidc = OidcConfig::fromGlobals();
    if (!$app->maintenance || $oidc === null || $app->groupMaxAgeSeconds === null) { throw new \RuntimeException(); }
    $pdo = (new ConnectionFactory())->connect($app->database);
    $identity = new PdoIdentityRepository($pdo, $app->groupMaxAgeSeconds); $roles = new PdoRoleRepository($pdo);
    $plugins = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($roles, $identity)]);
    $permissions = ['identity.users.read', 'identity.groups.read', 'identity.users.status.manage',
        'identity.groups.memberships.manage', 'identity.groups.details.manage', 'identity.groups.mappings.manage'];
    $guard = static function () use ($pdo, $identity, $oidc, $id, $roles, $permissions): void {
        $query = $pdo->prepare('SELECT issuer FROM identity_users WHERE id = ?'); $query->execute([$id]);
        if ($query->fetchColumn() !== $oidc->issuer || !$identity->isAccessAllowed($id)) { throw new \RuntimeException(); }
        $query = $pdo->prepare('SELECT 1 FROM identity_memberships WHERE group_id = ? AND user_id <> ? LIMIT 1');
        $query->execute(['samla.administrators', $id]);
        if ($query->fetchColumn() !== false) { throw new \RuntimeException(); }
        $existing = $roles->find('samla.administrator');
        if ($existing !== null && ($existing->scope !== Scope::Global || array_diff($existing->permissions, $permissions)
            || array_diff($permissions, $existing->permissions))) { throw new \RuntimeException(); }
        foreach ($roles->assignmentsFor(['samla.administrators']) as $assignment) {
            if ($assignment->roleId !== 'samla.administrator' || $assignment->scope !== Scope::Global) { throw new \RuntimeException(); }
        }
    };
    (new PdoMutationRunner($pdo, $plugins))->run(new MutationContext(bin2hex(random_bytes(16)), $id, 'identity.bootstrap', Scope::Global, null, ['user' => $id]), $guard,
        static function () use ($pdo, $identity, $roles, $plugins, $permissions, $id): bool {
            $query = $pdo->prepare('SELECT 1 FROM identity_groups WHERE id = ?'); $query->execute(['samla.administrators']);
            $changed = $roles->find('samla.administrator') === null;
            if ($query->fetchColumn() === false) { $identity->createGroup('samla.administrators', 'Administration'); $changed = true; }
            $roles->saveInsideTransaction(new Role('samla.administrator', Scope::Global, $permissions), $plugins);
            $assigned = $roles->assignInsideTransaction(new RoleAssignment('samla.administrators', 'samla.administrator', Scope::Global));
            $member = $identity->setManualMembershipInsideTransaction($id, 'samla.administrators', true);
            return $changed || $assigned || $member;
        });
    fwrite(STDOUT, "Initial Samla administrator configured. Disable maintenance before using the web UI.\n");
} catch (\Throwable) {
    fwrite(STDERR, "Bootstrap failed. Require maintenance, a fresh active OIDC account, configured group freshness and an unused initial administrator group.\n");
    exit(1);
}
