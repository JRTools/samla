<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

use Samla\Core\Configuration\OidcConfig;

/** Untrusted code, NOT an identity. Only a future verified OIDC adapter may redeem it. */
final readonly class AuthorizationCodeGrant
{
    public function __construct(
        #[\SensitiveParameter] private string $code,
        #[\SensitiveParameter] private LoginTransaction $transaction,
        #[\SensitiveParameter] private OidcConfig $config,
    ) {}
    /** Sensitive wire values for a server-side adapter; never log this result. */
    public function tokenBody(): string
    {
        return http_build_query(['grant_type' => 'authorization_code', 'code' => $this->code,
            'redirect_uri' => $this->config->redirectUri, 'code_verifier' => $this->transaction->verifier()], '', '&', PHP_QUERY_RFC3986);
    }
    /** OAuth Basic percent-encodes each credential before Base64 encoding. */
    public function authorizationHeader(): string
    {
        return 'Basic ' . base64_encode(urlencode($this->config->clientId) . ':' . urlencode($this->config->clientSecret()));
    }
    public function expectedNonce(): string { return $this->transaction->nonce(); }
    public function expectedIssuer(): string { return $this->config->issuer; }
    /** A ZITADEL project audience never replaces the ID token's client audience. */
    public function expectedClientId(): string { return $this->config->clientId; }
    public function __debugInfo(): array { return ['authorizationCode' => '[redacted]']; }
}
