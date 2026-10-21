<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

use Samla\Core\Configuration\OidcConfig;

final readonly class ProviderMetadata
{
    private function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $jwksUri,
        public bool $requiresResponseIssuer,
    ) {}

    public static function fromDiscovery(OidcConfig $config, array $document): self
    {
        if (($document['issuer'] ?? null) !== $config->issuer) {
            throw new \InvalidArgumentException('OIDC discovery issuer mismatch.');
        }
        foreach (['response_types_supported' => 'code', 'code_challenge_methods_supported' => 'S256',
            'token_endpoint_auth_methods_supported' => 'client_secret_basic'] as $key => $required) {
            if (!is_array($document[$key] ?? null) || !in_array($required, $document[$key], true)) {
                throw new \InvalidArgumentException('OIDC provider does not advertise the required flow.');
            }
        }
        $endpoints = [];
        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $key) {
            $value = $document[$key] ?? null;
            if (!is_string($value)) { throw new \InvalidArgumentException('Missing OIDC endpoint.'); }
            OidcConfig::url($value, false);
            if (self::origin($value) !== self::origin($config->issuer)) {
                throw new \InvalidArgumentException('Cross-origin OIDC endpoints require an explicit future policy.');
            }
            $endpoints[] = $value;
        }
        $requiresIssuer = $document['authorization_response_iss_parameter_supported'] ?? false;
        if (!is_bool($requiresIssuer)) { throw new \InvalidArgumentException('Invalid OIDC issuer-response capability.'); }
        return new self($config->issuer, ...[...$endpoints, $requiresIssuer]);
    }

    private static function origin(string $url): array
    {
        return [strtolower((string) parse_url($url, PHP_URL_HOST)), parse_url($url, PHP_URL_PORT) ?? 443];
    }
}
