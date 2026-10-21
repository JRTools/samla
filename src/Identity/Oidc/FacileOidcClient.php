<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

use Facile\JoseVerifier\JWK\JwksProviderBuilder;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\Issuer;
use Facile\OpenIDClient\Issuer\Metadata\IssuerMetadata;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Session\AuthSession;
use Facile\OpenIDClient\Token\IdTokenVerifierBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Samla\Core\Configuration\OidcConfig;
use Psr\Http\Client\ClientInterface;

final readonly class FacileOidcClient
{
    public ProviderMetadata $metadata;
    public function __construct(private OidcConfig $config, private array $document, private ClientInterface $http)
    {
        $this->metadata = ProviderMetadata::fromDiscovery($config, $document);
        if (!in_array('RS256', $document['id_token_signing_alg_values_supported'] ?? [], true)) {
            throw new LoginRejected('Provider does not advertise RS256.');
        }
    }
    public static function discover(OidcConfig $config): self
    {
        $url = rtrim($config->issuer, '/') . '/.well-known/openid-configuration';
        $factory = new Psr17Factory();
        $response = (new OidcHttpClient([$url => ['GET']]))->sendRequest($factory->createRequest('GET', $url));
        $document = json_decode((string) $response->getBody(), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($document)) { throw new LoginRejected('Invalid provider metadata.'); }
        $metadata = ProviderMetadata::fromDiscovery($config, $document);
        return new self($config, $document, new OidcHttpClient([
            $metadata->tokenEndpoint => ['POST'], $metadata->jwksUri => ['GET'],
        ]));
    }
    public function redeem(#[\SensitiveParameter] AuthorizationCodeGrant $grant): VerifiedLogin
    {
        try {
            if ($grant->expectedIssuer() !== $this->config->issuer || $grant->expectedClientId() !== $this->config->clientId) {
                throw new LoginRejected();
            }
            $factory = new Psr17Factory();
            $keys = (new JwksProviderBuilder())->withJwksUri($this->metadata->jwksUri)
                ->withHttpClient($this->http)->withRequestFactory($factory)->build();
            $client = (new ClientBuilder())->setIssuer(new Issuer(IssuerMetadata::fromArray($this->document), $keys))
                ->setHttpClient($this->http)->setClientMetadata(ClientMetadata::fromArray([
                    'client_id' => $this->config->clientId, 'client_secret' => $this->config->clientSecret(),
                    'token_endpoint_auth_method' => 'client_secret_basic', 'redirect_uris' => [$this->config->redirectUri],
                    'response_types' => ['code'], 'id_token_signed_response_alg' => 'RS256',
                ]))->build();
            parse_str($grant->tokenBody(), $body);
            $session = AuthSession::fromArray(['nonce' => $grant->expectedNonce(), 'code_verifier' => $body['code_verifier']]);
            $tokens = (new AuthorizationServiceBuilder())->setHttpClient($this->http)->setRequestFactory($factory)->build()
                ->callback($client, ['code' => $body['code']], $this->config->redirectUri, $session);
            if ($tokens->getIdToken() === null) { throw new LoginRejected(); }
            // Re-verify with the access token so a supplied at_hash is checked by JOSE.
            $claims = (new IdTokenVerifierBuilder())->build($client)->withNonce($grant->expectedNonce())
                ->withAccessToken($tokens->getAccessToken())->verify($tokens->getIdToken());
            if (array_key_exists('at_hash', $claims) && $tokens->getAccessToken() === null) { throw new LoginRejected(); }
            return IdTokenPolicy::identity($claims, $this->config, $grant->expectedNonce(), time());
        } catch (\Throwable) { throw new LoginRejected('The identity provider response could not be verified.'); }
    }
    public function __debugInfo(): array { return ['client' => '[redacted]']; }
}
