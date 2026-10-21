<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Samla\Core\Configuration\AppConfig;
use Samla\Core\Database\{ConnectionFactory, MigrationRunner};
use Samla\Core\Identity\{IdentityPlugin, PdoIdentityRepository};
use Samla\Core\Plugins\PluginRuntime;
use Samla\Core\Storage\StoragePlugin;
use Samla\Plugins\Rbac\{PdoRoleRepository, RbacPlugin, Role, RoleAssignment};
use Samla\PluginApi\Scope;

$config = AppConfig::fromGlobals();
if (PHP_SAPI !== 'cli' || $config->environment !== 'development' || !str_starts_with($config->database->database, 'samla_dev_') || $config->groupMaxAgeSeconds === null) {
    throw new RuntimeException('Seed requires development, an explicit freshness policy and a samla_dev_* database.');
}
$pdo = (new ConnectionFactory())->connect($config->database);
(new MigrationRunner($pdo))->migrate(require dirname(__DIR__) . '/bootstrap/migrations.php');
$identity = new PdoIdentityRepository($pdo, $config->groupMaxAgeSeconds);
foreach (['dev.admin' => 'Samla Administration', 'dev.member' => 'Alex · Team Öffentlichkeitsarbeit'] as $id => $name) {
    $query = $pdo->prepare('SELECT issuer, subject FROM identity_users WHERE id = ?'); $query->execute([$id]); $existing = $query->fetch();
    if ($existing === false) { $identity->createUser($id, 'urn:samla:local-development', $id, $name); }
    elseif ($existing['issuer'] !== 'urn:samla:local-development' || $existing['subject'] !== $id) { throw new RuntimeException('Reserved development identity collision.'); }
    // Synthetic local development snapshot, not an OIDC verification path. No status is reset.
    $identity->replaceVerifiedGroups($id, []);
}
foreach (['dev.operators' => 'Administration', 'dev.team' => 'Öffentlichkeitsarbeit'] as $id => $name) {
    $q = $pdo->prepare('SELECT 1 FROM identity_groups WHERE id = ?'); $q->execute([$id]);
    if ($q->fetchColumn() === false) { $identity->createGroup($id, $name); }
}
$roles = new PdoRoleRepository($pdo);
$runtime = PluginRuntime::boot([new IdentityPlugin(), new StoragePlugin(), new RbacPlugin($roles, $identity)]);
$permissions = ['identity.users.read','identity.groups.read','identity.users.status.manage','identity.groups.memberships.manage','identity.groups.details.manage','identity.groups.mappings.manage'];
$existing = $roles->find('dev.administrator');
if ($existing !== null && ($existing->scope !== Scope::Global || array_diff($existing->permissions, $permissions) !== [] || array_diff($permissions, $existing->permissions) !== [])) {
    throw new RuntimeException('Reserved development role has different permissions.');
}
$roles->save(new Role('dev.administrator', Scope::Global, $permissions), $runtime);
$identity->addManualMembership('dev.admin', 'dev.operators'); $identity->addManualMembership('dev.member', 'dev.team');
$roles->assign(new RoleAssignment('dev.operators', 'dev.administrator', Scope::Global));
echo "Local development fixtures ready. Actor: dev.admin\n";
