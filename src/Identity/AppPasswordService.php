<?php
declare(strict_types=1);
namespace Samla\Core\Identity;

use Samla\PluginApi\{AccessDenied, GroupDetailsChange, IdentityReader, MutationContext, MutationRunner, Scope};

/** Own-account credentials. Actor is the verified browser identity, never a form-supplied user. */
final readonly class AppPasswordService
{
    public function __construct(private \PDO $pdo, private IdentityReader $identity, private MutationRunner $mutations,
        private ?\Closure $clock = null) {}
    private function now(): int { return $this->clock === null ? time() : ($this->clock)(); }
    private function requireOwner(string $actor): void
    { if (!$this->identity->isAccessAllowed($actor)) { throw new AccessDenied(); } }
    /** Secret is returned exactly once. It is never included in audit or outbox. */
    public function create(string $actor, string $label, int $days = 30): string
    {
        $label = (new GroupDetailsChange($label))->name;
        if ($days < 1 || $days > 90) { throw new \InvalidArgumentException('Credential validity must be between 1 and 90 days.'); }
        $this->requireOwner($actor); $id = bin2hex(random_bytes(16)); $secret = bin2hex(random_bytes(32));
        $hash = password_hash($secret, PASSWORD_DEFAULT); $now = $this->now();
        $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)), $actor, 'identity.app_password.create', Scope::Global, null, ['credential' => $id]),
            function () use ($actor): void {
                $this->requireOwner($actor);
                $q = $this->pdo->prepare('SELECT COUNT(*) FROM identity_app_passwords WHERE user_id = ? AND revoked_at IS NULL AND expires_at > ?'); $q->execute([$actor, $this->now()]);
                if ((int) $q->fetchColumn() >= 20) { throw new \LengthException('At most 20 active app passwords.'); }
            }, function () use ($id, $actor, $label, $hash, $now, $days): bool {
                $q = $this->pdo->prepare('INSERT INTO identity_app_passwords (id, user_id, label, password_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
                $q->execute([$id, $actor, $label, $hash, $now, $now + $days * 86400]); return true;
            });
        return 'samla_' . $id . '.' . $secret;
    }
    public function listing(string $actor): array
    {
        $this->requireOwner($actor);
        $q = $this->pdo->prepare('SELECT id, label, created_at, expires_at, revoked_at FROM identity_app_passwords WHERE user_id = ? ORDER BY (revoked_at IS NULL AND expires_at > ?) DESC, created_at DESC, id DESC LIMIT 100'); $q->execute([$actor, $this->now()]);
        return $q->fetchAll();
    }
    public function revoke(string $actor, string $id): bool
    {
        return $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)), $actor, 'identity.app_password.revoke', Scope::Global, null, ['credential' => $id]),
            function () use ($actor, $id): void {
                $this->requireOwner($actor);
                $q = $this->pdo->prepare('SELECT 1 FROM identity_app_passwords WHERE id = ? AND user_id = ?'); $q->execute([$id, $actor]);
                if ($q->fetchColumn() === false) { throw new AccessDenied(); }
            }, function () use ($actor, $id): bool {
                $q = $this->pdo->prepare('UPDATE identity_app_passwords SET revoked_at = ? WHERE id = ? AND user_id = ? AND revoked_at IS NULL'); $q->execute([$this->now(), $id, $actor]);
                return $q->rowCount() === 1;
            });
    }
    /** Request-local transports use this selector check again during resource authorization. */
    public function isCredentialActive(string $actor, string $id): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !$this->identity->isAccessAllowed($actor)) { return false; }
        $q = $this->pdo->prepare('SELECT 1 FROM identity_app_passwords WHERE id = ? AND user_id = ? AND revoked_at IS NULL AND expires_at > ?');
        $q->execute([$id, $actor, $this->now()]);
        return $q->fetchColumn() !== false;
    }
    /** Validate only the app credential. Callers must separately authorize every requested resource operation. */
    public function authenticate(string $actor, #[\SensitiveParameter] string $password, string $clientAddress): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $actor) || strlen($clientAddress) > 128) { return false; }
        if (!$this->identity->isAccessAllowed($actor)) { return false; }
        $now = $this->now(); $bucket = hash('sha256', $actor . "\0" . $clientAddress); $window = intdiv($now, 60) * 60;
        // Atomic reservation bounds password hashing even for concurrent failed requests.
        $q = $this->pdo->prepare('INSERT INTO identity_app_auth_limits (bucket, window_start, failures) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE failures = IF(window_start = VALUES(window_start), failures + 1, 1), window_start = VALUES(window_start)');
        $q->execute([$bucket, $window]);
        $q = $this->pdo->prepare('SELECT failures FROM identity_app_auth_limits WHERE bucket = ?'); $q->execute([$bucket]);
        if ((int) $q->fetchColumn() > 10) { return false; }
        if (!preg_match('/^samla_([a-f0-9]{32})\.([a-f0-9]{64})$/D', $password, $matches)) { return false; }
        $q = $this->pdo->prepare('SELECT password_hash FROM identity_app_passwords WHERE id = ? AND user_id = ? AND revoked_at IS NULL AND expires_at > ?');
        $q->execute([$matches[1], $actor, $now]); $hash = $q->fetchColumn();
        $valid = is_string($hash) && password_verify($matches[2], $hash) && $this->identity->isAccessAllowed($actor);
        if ($valid) { $q = $this->pdo->prepare('DELETE FROM identity_app_auth_limits WHERE bucket = ?'); $q->execute([$bucket]); }
        // Bound retained buckets to a short operating window; no client address is stored in plaintext.
        $q = $this->pdo->prepare('DELETE FROM identity_app_auth_limits WHERE window_start < ? LIMIT 100'); $q->execute([$now - 3600]);
        return $valid;
    }
}
