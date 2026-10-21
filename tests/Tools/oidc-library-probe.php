<?php
declare(strict_types=1);

// Optional isolated dependency probe. No application configuration or real credentials.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$autoload = $argv[1] ?? '';
if (!is_file($autoload)) {
    fwrite(STDERR, "Usage: php tests/Tools/oidc-library-probe.php /absolute/evaluation/vendor/autoload.php\n");
    exit(2);
}
require $autoload;

use Facile\JoseVerifier\JWK\{JwksProviderInterface, MemoryJwksProvider};
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\Issuer;
use Facile\OpenIDClient\Issuer\Metadata\IssuerMetadata;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Session\AuthSession;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Symfony\Component\HttpClient\{NativeHttpClient, Psr18Client};

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
function check(bool $condition, string $name): void {
    if (!$condition) { throw new RuntimeException('Probe failed: ' . $name); }
}
function b64(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
$key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$wrongKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
$public = openssl_pkey_get_details($key)['rsa'];
$jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'probe-key', 'use' => 'sig', 'alg' => 'RS256', 'n' => b64($public['n']), 'e' => b64($public['e'])]]];
$nonce = str_repeat('a', 64); $verifier = str_repeat('b', 64);
$base = ['iss' => 'https://id.example', 'sub' => 'synthetic-subject', 'aud' => 'synthetic-client',
    'iat' => time() - 5, 'exp' => time() + 120, 'nonce' => $nonce];
$sign = static function (array $claims, string $alg = 'RS256', ?OpenSSLAsymmetricKey $signingKey = null, string $kid = 'probe-key') use ($key): string {
    $wire = b64(json_encode(['alg' => $alg, 'kid' => $kid], JSON_THROW_ON_ERROR)) . '.' . b64(json_encode($claims, JSON_THROW_ON_ERROR));
    if ($alg === 'none') { return $wire . '.'; }
    openssl_sign($wire, $signature, $signingKey ?? $key, $alg === 'RS512' ? OPENSSL_ALGO_SHA512 : OPENSSL_ALGO_SHA256);
    return $wire . '.' . b64($signature);
};
$http = new class implements ClientInterface {
    public string $jwt = '';
    public bool $omitIdToken = false;
    public bool $oauthError = false;
    public array $requests = [];
    public function sendRequest(RequestInterface $request): ResponseInterface {
        $this->requests[] = $request;
        return new Response($this->oauthError ? 400 : 200, ['Content-Type' => 'application/json'], json_encode(
            $this->oauthError ? ['error' => 'invalid_grant'] : (['access_token' => 'synthetic-access', 'token_type' => 'Bearer', 'expires_in' => 120]
                + ($this->omitIdToken ? [] : ['id_token' => $this->jwt])), JSON_THROW_ON_ERROR));
    }
};
$metadata = IssuerMetadata::fromArray(['issuer' => 'https://id.example', 'authorization_endpoint' => 'https://id.example/authorize',
    'token_endpoint' => 'https://id.example/token', 'jwks_uri' => 'https://id.example/keys', 'response_types_supported' => ['code'],
    'subject_types_supported' => ['public'], 'id_token_signing_alg_values_supported' => ['RS256']]);
$makeClient = static fn(JwksProviderInterface $keys) => (new ClientBuilder())
    ->setIssuer(new Issuer($metadata, $keys))->setClientMetadata(ClientMetadata::fromArray([
        'client_id' => 'synthetic-client', 'client_secret' => 'synthetic:secret+', 'token_endpoint_auth_method' => 'client_secret_basic',
        'redirect_uris' => ['http://127.0.0.1:8080/auth/callback'], 'response_types' => ['code'], 'id_token_signed_response_alg' => 'RS256']))
    ->setHttpClient($http)->build();
$client = $makeClient(new MemoryJwksProvider($jwks));
$session = AuthSession::fromArray(['nonce' => $nonce, 'code_verifier' => $verifier]);
$service = (new AuthorizationServiceBuilder())->setHttpClient($http)->setRequestFactory(new Psr17Factory())->build();
$callback = static fn($chosen = null) => $service->callback($chosen ?? $client, ['code' => 'synthetic-code'], 'http://127.0.0.1:8080/auth/callback', $session);
$http->jwt = $sign($base); $token = $callback();
check($token->claims()['sub'] === 'synthetic-subject', 'valid signed token');
$request = $http->requests[0]; parse_str((string) $request->getBody(), $body);
check($request->getMethod() === 'POST' && (string) $request->getUri() === 'https://id.example/token', 'token endpoint');
check($request->getHeaderLine('Authorization') === 'Basic ' . base64_encode('synthetic-client:synthetic%3Asecret%2B'), 'Basic encoding');
check($body === ['grant_type' => 'authorization_code', 'code' => 'synthetic-code', 'redirect_uri' => 'http://127.0.0.1:8080/auth/callback', 'code_verifier' => $verifier], 'exact code, callback and PKCE request');

$rejected = 0;
$cases = [
    'signature' => $sign($base, 'RS256', $wrongKey),
    'issuer' => $sign(['iss' => 'https://evil.example'] + $base),
    'audience' => $sign(['aud' => 'project-only'] + $base),
    'expiry' => $sign(['exp' => time() - 120] + $base),
    'future-iat' => $sign(['iat' => time() + 120] + $base),
    'future-nbf' => $sign(['nbf' => time() + 120] + $base),
    'wrong-nonce' => $sign(['nonce' => 'different'] + $base),
    'unapproved-algorithm' => $sign($base, 'RS512'),
    'unsigned' => $sign($base, 'none'),
    'unknown-kid' => $sign($base, 'RS256', null, 'unknown'),
    'malformed' => 'malformed',
];
foreach (['iss', 'sub', 'aud', 'exp', 'iat'] as $claim) {
    $claims = $base; unset($claims[$claim]); $cases['missing-' . $claim] = $sign($claims);
}
foreach ($cases as $name => $jwt) {
    $http->jwt = $jwt;
    try { $callback(); } catch (Throwable) { ++$rejected; continue; }
    throw new RuntimeException('Invalid token accepted: ' . $name);
}
check($rejected === 16, 'all invalid tokens rejected');
$http->jwt = $sign(['aud' => ['synthetic-client', 'project']] + $base);
check($callback()->claims()['aud'] === ['synthetic-client', 'project'], 'multiple audiences');

$rotating = new class($jwks) implements JwksProviderInterface {
    public int $reloads = 0;
    public function __construct(private array $keys) {}
    public function getJwks(): array { return $this->reloads === 0 ? ['keys' => []] : $this->keys; }
    public function reload(): JwksProviderInterface { ++$this->reloads; return $this; }
};
$http->jwt = $sign($base);
check($callback($makeClient($rotating))->claims()['sub'] === 'synthetic-subject' && $rotating->reloads === 1, 'key rotation');
$http->oauthError = true;
try { $callback(); throw new LogicException('OAuth error accepted'); }
catch (Facile\OpenIDClient\Exception\OAuth2Exception) {}
$http->oauthError = false;

// Record integration obligations: the library deliberately supports OAuth as well
// as OIDC and therefore must not be treated as the entire application policy.
$http->omitIdToken = true;
check($callback()->getIdToken() === null, 'adapter must require an ID token');
$http->omitIdToken = false;
$claims = $base; unset($claims['nonce']); $http->jwt = $sign($claims);
check(!array_key_exists('nonce', $callback()->claims()), 'adapter must require nonce presence');
$http->jwt = $sign(['azp' => 'other-client'] + $base);
check($callback()->claims()['azp'] === 'other-client', 'adapter must enforce azp');
$http->jwt = $sign(['at_hash' => 'invalid-access-token-hash'] + $base);
check($callback()->claims()['at_hash'] === 'invalid-access-token-hash', 'adapter must bind at_hash when present');

// Instantiate the concrete already-transitive HTTP implementation explicitly.
new Psr18Client(new NativeHttpClient(['max_redirects' => 0, 'timeout' => 10, 'max_duration' => 15, 'verify_peer' => true, 'verify_host' => true]), new Psr17Factory(), new Psr17Factory());
echo json_encode(['php' => PHP_VERSION, 'valid_signature' => true, 'invalid_tokens_rejected' => $rejected,
    'basic_pkce_request' => true, 'key_rotation' => true, 'oauth_error_rejected' => true,
    'adapter_obligations' => ['require_id_token', 'require_nonce', 'validate_azp', 'validate_at_hash_when_present'], 'warnings_or_deprecations' => 0], JSON_THROW_ON_ERROR), PHP_EOL;
