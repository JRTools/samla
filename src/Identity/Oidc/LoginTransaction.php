<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

/** Store only in the server session. Never in a cookie, URL or application log. */
final readonly class LoginTransaction
{
    public const LIFETIME_SECONDS = 300;

    public function __construct(
        #[\SensitiveParameter] private string $state,
        #[\SensitiveParameter] private string $nonce,
        #[\SensitiveParameter] private string $verifier,
        public int $createdAt,
        public string $configurationBinding,
    ) {
        foreach ([$state, $nonce, $verifier, $configurationBinding] as $value) {
            if (!preg_match('/^[a-f0-9]{64}$/D', $value)) { throw new \InvalidArgumentException('Invalid login transaction.'); }
        }
        if ($createdAt < 0) { throw new \InvalidArgumentException('Invalid login time.'); }
    }
    public function state(): string { return $this->state; }
    public function nonce(): string { return $this->nonce; }
    public function verifier(): string { return $this->verifier; }
    public function challenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->verifier, true)), '+/', '-_'), '=');
    }
    public function __debugInfo(): array { return ['createdAt' => $this->createdAt, 'transaction' => '[redacted]']; }
}
