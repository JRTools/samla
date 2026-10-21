<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Samla\Core\Configuration\OidcConfig;
use Samla\Core\Identity\Oidc\{AuthorizationFlow, LoginTransaction, ProviderMetadata};

final class OidcFlowTest extends TestCase
{
    private function env(): array
    {
        return ['OIDC_ENABLED' => '1', 'APP_ENV' => 'development', 'OIDC_ISSUER' => 'https://identity.example',
            'OIDC_CLIENT_ID' => 'client', 'OIDC_CLIENT_SECRET' => 'synthetic:secret+',
            'OIDC_REDIRECT_URI' => 'http://127.0.0.1:8080/auth/callback', 'OIDC_ZITADEL_PROJECT_ID' => '123'];
    }
    private function config(array $override = []): OidcConfig { return OidcConfig::fromEnvironment($override + $this->env()); }
    private function document(): array
    {
        return ['issuer' => 'https://identity.example', 'authorization_endpoint' => 'https://identity.example/authorize',
            'token_endpoint' => 'https://identity.example/token', 'jwks_uri' => 'https://identity.example/keys',
            'response_types_supported' => ['code'], 'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic']];
    }
    private function flow(?OidcConfig $config = null, array $override = []): AuthorizationFlow
    {
        $config ??= $this->config();
        return new AuthorizationFlow($config, ProviderMetadata::fromDiscovery($config, $override + $this->document()));
    }
    public function testDisabledByDefaultAndStrictOptIn(): void
    {
        self::assertNull(OidcConfig::fromEnvironment([]));
        self::assertNull(OidcConfig::fromEnvironment(['OIDC_ENABLED' => '0', 'OIDC_ISSUER' => 'invalid']));
        $this->expectException(\InvalidArgumentException::class);
        OidcConfig::fromEnvironment(['OIDC_ENABLED' => 'true']);
    }
    public function testLocalCallbackRequiresDevelopmentOrTest(): void
    {
        self::assertSame('http://127.0.0.1:8080/auth/callback', $this->config()->redirectUri);
        self::assertNotNull($this->config(['APP_ENV' => 'test']));
        $this->expectException(\InvalidArgumentException::class);
        $this->config(['APP_ENV' => 'production']);
    }
    public function testProductionHttpsCallback(): void
    {
        self::assertSame('https://samla.example/auth/callback', $this->config(['APP_ENV' => 'production',
            'OIDC_REDIRECT_URI' => 'https://samla.example/auth/callback'])->redirectUri);
    }
    public function testUnsafeOrIncompleteConfigurationIsRejectedWithoutSecretOutput(): void
    {
        foreach ([['OIDC_CLIENT_SECRET' => ''], ['OIDC_CLIENT_ID' => ''], ['OIDC_ISSUER' => 'http://identity.example'],
            ['OIDC_ISSUER' => 'https://user:synthetic-password@identity.example'], ['OIDC_ISSUER' => 'https://identity.example?x=1'],
            ['OIDC_REDIRECT_URI' => 'http://remote.example/auth/callback'], ['OIDC_REDIRECT_URI' => 'http://127.0.0.1:8080/auth/callback/'],
            ['OIDC_REDIRECT_URI' => 'https://samla.example/auth/callback#fragment'], ['OIDC_REDIRECT_URI' => 'https://samla.example/auth/callback?x=1'],
            ['OIDC_REDIRECT_URI' => "https://samla.example/auth/callback\r\nX-Test: bad"],
            ['OIDC_TOKEN_AUTH_METHOD' => 'none'], ['OIDC_TOKEN_AUTH_METHOD' => 'client_secret_post'],
            ['OIDC_ZITADEL_PROJECT_ID' => '123:aud evil'], ['APP_ENV' => 'typo']] as $override) {
            try { $this->config($override); self::fail('Unsafe configuration accepted'); }
            catch (\InvalidArgumentException $error) {
                self::assertStringNotContainsString('synthetic-password', $error->getMessage());
                self::assertStringNotContainsString('synthetic:secret+', $error->getMessage());
            }
        }
    }
    public function testDiscoveryRequiresExactIssuerAndAdvertisedProtocol(): void
    {
        foreach ([['issuer' => 'https://identity.example/'], ['authorization_endpoint' => 'http://identity.example/authorize'],
            ['token_endpoint' => 'https://elsewhere.example/token'], ['jwks_uri' => 'https://identity.example/keys?key=untrusted'],
            ['response_types_supported' => ['token']], ['code_challenge_methods_supported' => ['plain']],
            ['token_endpoint_auth_methods_supported' => ['none']], ['authorization_response_iss_parameter_supported' => 'true']] as $override) {
            try { $this->flow(null, $override); self::fail('Unsafe discovery accepted'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        self::assertInstanceOf(AuthorizationFlow::class, $this->flow());
    }
    public function testAuthorizationUsesPkceAndNonceWithIndependentRandomness(): void
    {
        $pending = null; $url = $this->flow()->begin($pending, 1000);
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        self::assertSame('https://identity.example/authorize', strtok($url, '?'));
        self::assertSame('code', $params['response_type']);
        self::assertSame('query', $params['response_mode']);
        self::assertSame('S256', $params['code_challenge_method']);
        self::assertSame($pending->state(), $params['state']);
        self::assertSame($pending->nonce(), $params['nonce']);
        self::assertSame($pending->challenge(), $params['code_challenge']);
        self::assertSame(43, strlen($params['code_challenge']));
        self::assertNotSame($pending->nonce(), $pending->state());
        self::assertNotSame($pending->nonce(), $pending->verifier());
        self::assertStringNotContainsString($pending->verifier(), $url);
        self::assertStringNotContainsString('synthetic', $url);
        self::assertSame('openid profile email urn:zitadel:iam:org:project:id:123:aud', $params['scope']);
    }
    public function testPkceMatchesIndependentSha256Vector(): void
    {
        $transaction = new LoginTransaction(str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64), 1000, str_repeat('d', 64));
        self::assertSame('UrZBnSe9f1R87juS-MF6kIuKSWAey-wWHlAw3h3-ngo', $transaction->challenge());
    }
    public function testNewAttemptReplacesPreviousAndOldCallbackCannotBeUsed(): void
    {
        $flow = $this->flow(); $pending = null;
        $flow->begin($pending, 1000); $oldState = $pending->state();
        $flow->begin($pending, 1001);
        self::assertNotSame($oldState, $pending->state());
        try { $flow->consume(['state' => $oldState, 'code' => 'code'], $pending, 1002); self::fail('Old callback accepted'); }
        catch (\InvalidArgumentException) { self::assertNull($pending); }
    }
    public function testCallbackProducesOnlyAnUnverifiedGrantAndCannotReplay(): void
    {
        $flow = $this->flow(); $pending = null; $flow->begin($pending, 1000);
        $transaction = $pending;
        $query = ['state' => $pending->state(), 'code' => 'synthetic-code+/:'];
        $grant = $flow->consume($query, $pending, 1001);
        self::assertNull($pending);
        self::assertSame('https://identity.example', $grant->expectedIssuer());
        self::assertSame('client', $grant->expectedClientId());
        self::assertSame($transaction->nonce(), $grant->expectedNonce());
        parse_str($grant->tokenBody(), $body);
        self::assertSame('authorization_code', $body['grant_type']);
        self::assertSame($query['code'], $body['code']);
        self::assertSame($transaction->verifier(), $body['code_verifier']);
        self::assertSame($this->config()->redirectUri, $body['redirect_uri']);
        self::assertArrayNotHasKey('client_secret', $body);
        self::assertSame('client:synthetic%3Asecret%2B', base64_decode(substr($grant->authorizationHeader(), 6), true));
        $this->expectException(\InvalidArgumentException::class);
        $flow->consume($query, $pending, 1002);
    }
    public function testExpiredAndFutureTransactionsAreConsumed(): void
    {
        foreach ([999, 1300, 1301] as $now) {
            $flow = $this->flow(); $pending = null; $flow->begin($pending, 1000);
            try { $flow->consume(['state' => $pending->state(), 'code' => 'code'], $pending, $now); self::fail('Invalid time accepted'); }
            catch (\InvalidArgumentException) { self::assertNull($pending); }
        }
    }
    public function testInvalidCallbackValuesAreConsumedWithoutProviderErrorReflection(): void
    {
        foreach ([['state' => ['x']], ['state' => 'wrong'], ['code' => ['code']], ['code' => ''], ['code' => str_repeat('a', 4097)],
            ['code' => "code\r\n"], ['error' => 'access_denied', 'error_description' => 'SENSITIVE PROVIDER DETAIL'],
            ['iss' => 'https://evil.example']] as $override) {
            $flow = $this->flow(); $pending = null; $flow->begin($pending, 1000);
            try { $flow->consume($override + ['state' => $pending->state(), 'code' => 'code'], $pending, 1001); self::fail('Bad callback accepted'); }
            catch (\InvalidArgumentException $error) {
                self::assertNull($pending);
                self::assertStringNotContainsString('SENSITIVE PROVIDER DETAIL', $error->getMessage());
            }
        }
    }
    public function testIssuerResponseIsRequiredWhenAdvertised(): void
    {
        $flow = $this->flow(null, ['authorization_response_iss_parameter_supported' => true]);
        $pending = null; $flow->begin($pending, 1000);
        try { $flow->consume(['state' => $pending->state(), 'code' => 'code'], $pending, 1001); self::fail('Missing issuer accepted'); }
        catch (\InvalidArgumentException) { self::assertNull($pending); }
        $flow->begin($pending, 1000);
        self::assertSame('client', $flow->consume(['state' => $pending->state(), 'code' => 'code',
            'iss' => 'https://identity.example'], $pending, 1001)->expectedClientId());
    }
    public function testConfigurationChangeInvalidatesPendingTransaction(): void
    {
        foreach ([['OIDC_CLIENT_ID' => 'new-client'], ['OIDC_REDIRECT_URI' => 'https://samla.example/auth/callback'],
            ['OIDC_ZITADEL_PROJECT_ID' => '456']] as $override) {
            $pending = null; $this->flow()->begin($pending, 1000);
            try { $this->flow($this->config($override))->consume(['state' => $pending->state(), 'code' => 'code'], $pending, 1001); self::fail('Changed configuration accepted'); }
            catch (\InvalidArgumentException) { self::assertNull($pending); }
        }
    }
    public function testNoProjectScopeByDefault(): void
    {
        self::assertSame('openid profile email', $this->config(['OIDC_ZITADEL_PROJECT_ID' => ''])->scope());
    }
    public function testDebugOutputRedactsCredentialsAndTransactionMaterial(): void
    {
        $config = $this->config(); $flow = $this->flow(); $pending = null; $flow->begin($pending, 1000);
        $state = $pending->state(); $nonce = $pending->nonce(); $verifier = $pending->verifier();
        $grant = $flow->consume(['state' => $state, 'code' => 'synthetic-code'], $pending, 1001);
        ob_start(); var_dump($config, $flow, $grant); $dump = ob_get_clean();
        foreach (['synthetic:secret+', 'synthetic-code', $state, $nonce, $verifier] as $value) { self::assertStringNotContainsString($value, $dump); }
        self::assertStringContainsString('[redacted]', $dump);
    }
}
