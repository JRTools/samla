<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

use Samla\Core\Configuration\OidcConfig;

/** Application checks AFTER library signature validation, never a JWT decoder. */
final class IdTokenPolicy
{
    public static function identity(#[\SensitiveParameter] array $claims, OidcConfig $config,
        #[\SensitiveParameter] string $nonce, int $now): VerifiedLogin {
        $aud = $claims['aud'] ?? null;
        $audiences = is_string($aud) ? [$aud] : $aud;
        if (($claims['iss'] ?? null) !== $config->issuer || !is_array($audiences) || !array_is_list($audiences)
            || $audiences === [] || array_filter($audiences, static fn($a) => !is_string($a) || $a === '') !== []
            || !in_array($config->clientId, $audiences, true)
            || (array_key_exists('azp', $claims) && $claims['azp'] !== $config->clientId)
            || (count($audiences) > 1 && ($claims['azp'] ?? null) !== $config->clientId)
            || !is_int($claims['iat'] ?? null) || !is_int($claims['exp'] ?? null)
            || $claims['iat'] > $now || $claims['iat'] < 0 || $claims['exp'] <= $now || $claims['exp'] <= $claims['iat']
            || (isset($claims['nbf']) && (!is_int($claims['nbf']) || $claims['nbf'] > $now))
            || !is_string($claims['nonce'] ?? null) || !hash_equals($nonce, $claims['nonce'])
            || !is_string($claims['sub'] ?? null)) { throw new LoginRejected('ID token policy rejected the login.'); }
        $groups = $config->groupsClaim === null ? [] : ($claims[$config->groupsClaim] ?? null);
        if (!is_array($groups)) { throw new LoginRejected('Configured groups claim is missing or invalid.'); }
        $name = $claims['name'] ?? $claims['preferred_username'] ?? $claims['sub'];
        if (!is_string($name)) { throw new LoginRejected('Invalid display name.'); }
        return new VerifiedLogin($config->issuer, $claims['sub'], $name, $groups, $config->groupsClaim !== null, $claims['exp']);
    }
}
