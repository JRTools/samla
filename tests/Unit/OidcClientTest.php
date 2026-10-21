<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Samla\Core\Configuration\OidcConfig;
use Samla\Core\Identity\Oidc\{AuthorizationFlow, FacileOidcClient, IdTokenPolicy, LoginRejected, OidcHttpClient, OidcTransportError};
use Samla\Core\Web\OidcOrigin;
use Nyholm\Psr7\{Request, Response};
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OidcClientTest extends TestCase
{
    private function config(array $extra = []): OidcConfig
    {
        return OidcConfig::fromEnvironment($extra + ['APP_ENV' => 'development', 'OIDC_ENABLED' => '1',
            'OIDC_ISSUER' => 'https://id.example', 'OIDC_CLIENT_ID' => 'client', 'OIDC_CLIENT_SECRET' => 'synthetic:secret+',
            'OIDC_REDIRECT_URI' => 'http://127.0.0.1:8080/auth/callback']);
    }
    private function document(): array
    {
        return ['issuer' => 'https://id.example', 'authorization_endpoint' => 'https://id.example/authorize',
            'token_endpoint' => 'https://id.example/token', 'jwks_uri' => 'https://id.example/keys',
            'response_types_supported' => ['code'], 'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'], 'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic']];
    }
    private function claims(): array
    {
        return ['iss' => 'https://id.example', 'sub' => 'subject', 'name' => 'Test User', 'aud' => 'client',
            'nonce' => str_repeat('a', 64), 'iat' => time() - 1, 'exp' => time() + 300];
    }
    public function testApplicationPolicyRequiresNonceAzpTypesAndExplicitGroups(): void
    {
        $base = $this->claims();
        foreach ([['nonce' => null], ['nonce' => 'wrong'], ['azp' => 'other'], ['aud' => ['client','project']],
            ['aud' => ['client', 3]], ['exp' => (string) (time() + 300)], ['exp' => time() - 1], ['iat' => time() + 300],
            ['sub' => []], ['sub' => ''], ['name' => "bad\nname"], ['iss' => 'https://other.example']] as $change) {
            try { IdTokenPolicy::identity($change + $base, $this->config(), $base['nonce'], time()); self::fail('Bad claims accepted'); }
            catch (LoginRejected) { self::assertTrue(true); }
        }
        $identity = IdTokenPolicy::identity(['aud' => ['client', 'project'], 'azp' => 'client'] + $base, $this->config(), $base['nonce'], time());
        self::assertSame('subject', $identity->subject); self::assertFalse($identity->externalGroups);
        $this->expectException(LoginRejected::class);
        IdTokenPolicy::identity($base, $this->config(['OIDC_GROUPS_CLAIM' => 'groups']), $base['nonce'], time());
    }
    public function testGroupsAreStrictAndManualModeDoesNotInterpretUnconfiguredClaims(): void
    {
        $claims = ['groups' => ['team']] + $this->claims();
        self::assertSame([], IdTokenPolicy::identity($claims, $this->config(), $claims['nonce'], time())->groups);
        self::assertSame(['team'], IdTokenPolicy::identity($claims, $this->config(['OIDC_GROUPS_CLAIM' => 'groups']), $claims['nonce'], time())->groups);
        foreach ([null, 'team', [3], ['nested' => 'team']] as $groups) {
            try { IdTokenPolicy::identity(['groups' => $groups] + $claims, $this->config(['OIDC_GROUPS_CLAIM' => 'groups']), $claims['nonce'], time()); self::fail('Bad group claim'); }
            catch (\InvalidArgumentException|LoginRejected) { self::assertTrue(true); }
        }
    }
    public function testTransportEnforcesExactEndpointStatusAndSize(): void
    {
        $transport = new MockHttpClient(new MockResponse('{"ok":true}'));
        $client = new OidcHttpClient(['https://id.example/keys' => ['GET']], $transport);
        self::assertSame(200, $client->sendRequest(new Request('GET', 'https://id.example/keys'))->getStatusCode());
        foreach ([new Request('POST', 'https://id.example/keys'), new Request('GET', 'https://evil.example/keys')] as $request) {
            try { $client->sendRequest($request); self::fail('Wrong endpoint allowed'); }
            catch (OidcTransportError) { self::assertTrue(true); }
        }
        foreach ([new MockResponse('', ['http_code' => 302]), new MockResponse(str_repeat('x', 262145))] as $response) {
            $client = new OidcHttpClient(['https://id.example/keys' => ['GET']], new MockHttpClient($response));
            try { $client->sendRequest(new Request('GET', 'https://id.example/keys')); self::fail('Bad response accepted'); }
            catch (OidcTransportError $e) { self::assertSame('OIDC request failed.', $e->getMessage()); }
        }
    }
    public function testCanonicalOriginAndHttpsAreRequiredWithoutForwardedTrust(): void
    {
        $config = $this->config(['APP_ENV' => 'production', 'OIDC_REDIRECT_URI' => 'https://samla.example/auth/callback']);
        self::assertTrue(OidcOrigin::accepts($config, ['HTTP_HOST' => 'samla.example', 'HTTPS' => 'on'], 'fpm-fcgi'));
        self::assertTrue(OidcOrigin::accepts($config, ['HTTP_HOST' => 'samla.example:443', 'HTTPS' => 'on'], 'fpm-fcgi'));
        foreach ([['HTTP_HOST' => 'evil.example', 'HTTPS' => 'on'], ['HTTP_HOST' => 'samla.example', 'HTTP_X_FORWARDED_PROTO' => 'https'],
            ['HTTP_HOST' => 'samla.example:444', 'HTTPS' => 'on'], ['HTTP_HOST' => 'samla.example/path', 'HTTPS' => 'on']] as $server) {
            self::assertFalse(OidcOrigin::accepts($config, $server, 'fpm-fcgi'));
        }
    }
    public function testAdapterVerifiesRealRsaTokensAndRejectsIncompleteOrForgedResponses(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048]); $rsa = openssl_pkey_get_details($key)['rsa'];
        $encode = static fn(string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $jwks = ['keys' => [['kty' => 'RSA', 'kid' => 'test', 'use' => 'sig', 'alg' => 'RS256', 'n' => $encode($rsa['n']), 'e' => $encode($rsa['e'])]]];
        $http = new class($jwks) implements ClientInterface {
            public string $jwt = ''; public bool $omit = false; public array $requests = [];
            public function __construct(private array $keys) {}
            public function sendRequest(RequestInterface $request): ResponseInterface {
                $this->requests[] = $request;
                return new Response(200, ['Content-Type' => 'application/json'], json_encode(
                    str_ends_with((string) $request->getUri(), '/keys') ? $this->keys
                    : ['access_token' => 'access', 'token_type' => 'Bearer'] + ($this->omit ? [] : ['id_token' => $this->jwt]), JSON_THROW_ON_ERROR));
            }
        };
        $adapter = new FacileOidcClient($this->config(), $this->document(), $http);
        $pending = null; (new AuthorizationFlow($this->config(), $adapter->metadata))->begin($pending, time());
        $nonce = $pending->nonce(); $verifier = $pending->verifier();
        $grant = (new AuthorizationFlow($this->config(), $adapter->metadata))->consume(['state' => $pending->state(), 'code' => 'code'], $pending, time());
        $sign = static function (array $claims) use ($encode, $key): string {
            $body = $encode('{"alg":"RS256","kid":"test"}') . '.' . $encode(json_encode($claims, JSON_THROW_ON_ERROR));
            openssl_sign($body, $signature, $key, OPENSSL_ALGO_SHA256); return $body . '.' . $encode($signature);
        };
        $base = ['nonce' => $nonce] + $this->claims();
        $http->jwt = $sign($base);
        self::assertSame('subject', $adapter->redeem($grant)->subject);
        parse_str((string) $http->requests[0]->getBody(), $body);
        self::assertSame($verifier, $body['code_verifier']); self::assertArrayNotHasKey('client_secret', $body);
        self::assertSame('Basic ' . base64_encode('client:synthetic%3Asecret%2B'), $http->requests[0]->getHeaderLine('Authorization'));
        $missingNonce = $base; unset($missingNonce['nonce']);
        foreach ([$sign($missingNonce), $sign(['at_hash' => 'wrong'] + $base), $sign(['azp' => 'other'] + $base),
            $sign(['aud' => ['client','project']] + $base), $sign(['exp' => time() - 100] + $base), 'malformed'] as $bad) {
            $http->jwt = $bad;
            try { $adapter->redeem($grant); self::fail('Bad token accepted'); }
            catch (LoginRejected $e) { self::assertSame('The identity provider response could not be verified.', $e->getMessage()); }
        }
        $http->omit = true;
        $this->expectException(LoginRejected::class); $adapter->redeem($grant);
    }
}
