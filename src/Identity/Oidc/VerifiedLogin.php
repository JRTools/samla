<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

/** Only constructed from signature-verified claims by the trusted OIDC adapter. */
final readonly class VerifiedLogin
{
    public function __construct(public string $issuer, public string $subject, public string $name,
        public array $groups, public bool $externalGroups, public int $expiresAt) {
        if ($issuer === '' || strlen($issuer) > 1024 || $subject === '' || strlen($subject) > 255
            || preg_match('/[\x00-\x1f\x7f]/', $subject) || $name === '' || mb_strlen($name) > 255
            || !mb_check_encoding($name, 'UTF-8') || preg_match('/[\x00-\x1f\x7f]/', $name) || $expiresAt < 1) {
            throw new LoginRejected('Invalid verified identity.');
        }
        (new \Samla\Core\Identity\GroupMapper())->mapVerifiedClaim($groups, []);
    }
    public function __debugInfo(): array { return ['identity' => '[redacted]']; }
}
