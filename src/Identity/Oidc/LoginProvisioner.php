<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

use Samla\Core\Identity\PdoIdentityRepository;
use Samla\PluginApi\{AccessDenied, MutationContext, MutationRunner, Scope};

/** Authentication proves issuer/subject; it never grants roles or removes local blocks. */
final readonly class LoginProvisioner
{
    public function __construct(private \PDO $pdo, private PdoIdentityRepository $identities, private MutationRunner $mutations) {}
    public function login(VerifiedLogin $login): string
    {
        $find = $this->pdo->prepare('SELECT id FROM identity_users WHERE issuer = ? AND subject = ?');
        $find->execute([$login->issuer, $login->subject]);
        $id = $find->fetchColumn();
        $id = $id === false ? 'oidc.' . substr(hash('sha256', pack('N', strlen($login->issuer)) . $login->issuer . $login->subject), 0, 59) : $id;
        $guard = function () use ($login, $id): void {
            if ($login->expiresAt <= time()) { throw new AccessDenied(); }
            $query = $this->pdo->prepare('SELECT issuer, subject, status FROM identity_users WHERE id = ?'); $query->execute([$id]);
            $row = $query->fetch();
            if ($row !== false && ($row['issuer'] !== $login->issuer || $row['subject'] !== $login->subject || $row['status'] !== 'active')) { throw new AccessDenied(); }
            if (!$login->externalGroups) {
                $query = $this->pdo->prepare('SELECT 1 FROM identity_group_mappings WHERE issuer = ? LIMIT 1'); $query->execute([$login->issuer]);
                if ($query->fetchColumn() !== false) { throw new AccessDenied(); }
                $query = $this->pdo->prepare("SELECT 1 FROM identity_memberships WHERE user_id = ? AND origin = 'external' LIMIT 1"); $query->execute([$id]);
                if ($query->fetchColumn() !== false) { throw new AccessDenied(); }
            }
        };
        $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)), $id, 'identity.login', Scope::Global, null, ['user' => $id]), $guard,
            function () use ($id, $login): bool {
                $query = $this->pdo->prepare('SELECT 1 FROM identity_users WHERE id = ?'); $query->execute([$id]);
                if ($query->fetchColumn() === false) { $this->identities->createUser($id, $login->issuer, $login->subject, $login->name); }
                else {
                    $query = $this->pdo->prepare('UPDATE identity_users SET display_name = ? WHERE id = ?'); $query->execute([$login->name, $id]);
                }
                $this->identities->replaceVerifiedGroupsInsideTransaction($id, $login->groups);
                return true;
            });
        return $id;
    }
}
