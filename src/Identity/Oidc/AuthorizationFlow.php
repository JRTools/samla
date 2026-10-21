<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

use Samla\Core\Configuration\OidcConfig;

/** Protocol preparation only: this class never authenticates a user or writes groups. */
final readonly class AuthorizationFlow
{
    public function __construct(private OidcConfig $config, private ProviderMetadata $provider)
    {
        if ($provider->issuer !== $config->issuer) { throw new \InvalidArgumentException('Provider issuer mismatch.'); }
    }

    /** Replaces any older login attempt. Pending state belongs to the server session. */
    public function begin(#[\SensitiveParameter] ?LoginTransaction &$pending, int $now): string
    {
        $pending = new LoginTransaction(bin2hex(random_bytes(32)), bin2hex(random_bytes(32)), bin2hex(random_bytes(32)), $now, $this->config->binding());
        return $this->provider->authorizationEndpoint . '?' . http_build_query([
            'client_id' => $this->config->clientId, 'redirect_uri' => $this->config->redirectUri,
            'response_type' => 'code', 'response_mode' => 'query', 'scope' => $this->config->scope(),
            'state' => $pending->state(), 'nonce' => $pending->nonce(),
            'code_challenge' => $pending->challenge(), 'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Consume before any token request, including on errors. The caller must persist the
     * cleared session under its lock before network I/O. No callback value proves identity.
     */
    public function consume(#[\SensitiveParameter] array $query, #[\SensitiveParameter] ?LoginTransaction &$pending, int $now): AuthorizationCodeGrant
    {
        $transaction = $pending;
        $pending = null;
        if ($transaction === null || $now < $transaction->createdAt
            || $now - $transaction->createdAt >= LoginTransaction::LIFETIME_SECONDS
            || !hash_equals($this->config->binding(), $transaction->configurationBinding)) {
            throw new \InvalidArgumentException('Missing or expired login transaction.');
        }
        $state = $query['state'] ?? null;
        if (!is_string($state) || strlen($state) !== 64 || !hash_equals($transaction->state(), $state)) {
            throw new \InvalidArgumentException('Invalid login state.');
        }
        if (($this->provider->requiresResponseIssuer || array_key_exists('iss', $query))
            && ($query['iss'] ?? null) !== $this->config->issuer) {
            throw new \InvalidArgumentException('Invalid callback issuer.');
        }
        if (array_key_exists('error', $query)) { throw new \InvalidArgumentException('Identity provider rejected the login.'); }
        $code = $query['code'] ?? null;
        if (!is_string($code) || $code === '' || strlen($code) > 4096 || preg_match('/[\x00-\x20\x7f]/', $code)) {
            throw new \InvalidArgumentException('Invalid authorization code.');
        }
        return new AuthorizationCodeGrant($code, $transaction, $this->config);
    }
}
