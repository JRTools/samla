<?php
declare(strict_types=1);
namespace Samla\Core\Configuration;

/** Trusted configuration only. No request-derived issuer or callback. */
final readonly class OidcConfig
{
    private function __construct(
        public string $issuer,
        public string $clientId,
        #[\SensitiveParameter] private string $clientSecret,
        public string $redirectUri,
        public ?string $zitadelProjectId,
        public ?string $groupsClaim,
    ) {}

    public static function fromGlobals(): ?self { return self::fromEnvironment(EnvironmentLoader::fromGlobals()); }

    /** @param array<string, string> $env */
    public static function fromEnvironment(#[\SensitiveParameter] array $env): ?self
    {
        $enabled = $env['OIDC_ENABLED'] ?? '0';
        if (!in_array($enabled, ['0', '1'], true)) { throw new \InvalidArgumentException('Invalid OIDC enable flag.'); }
        if ($enabled === '0') { return null; }
        $environment = $env['APP_ENV'] ?? 'production';
        if (!in_array($environment, ['production', 'development', 'test'], true)) {
            throw new \InvalidArgumentException('Invalid APP_ENV.');
        }
        $required = static function (string $name) use ($env): string {
            $value = $env[$name] ?? null;
            if (!is_string($value) || $value === '' || strlen($value) > 4096 || preg_match('/[\x00-\x20\x7f]/', $value)) {
                throw new \InvalidArgumentException('Missing or invalid OIDC configuration: ' . $name);
            }
            return $value;
        };
        $issuer = $required('OIDC_ISSUER');
        self::url($issuer, false);
        $redirect = $required('OIDC_REDIRECT_URI');
        self::url($redirect, $environment !== 'production');
        if (parse_url($redirect, PHP_URL_PATH) !== '/auth/callback') {
            throw new \InvalidArgumentException('OIDC callback path must be /auth/callback.');
        }
        $client = $required('OIDC_CLIENT_ID');
        if (strlen($client) > 255) { throw new \InvalidArgumentException('Invalid OIDC client ID.'); }
        if (($env['OIDC_TOKEN_AUTH_METHOD'] ?? 'client_secret_basic') !== 'client_secret_basic') {
            throw new \InvalidArgumentException('Only client_secret_basic is supported.');
        }
        $project = $env['OIDC_ZITADEL_PROJECT_ID'] ?? '';
        if (!is_string($project) || ($project !== '' && !preg_match('/^[0-9]{1,64}$/D', $project))) {
            throw new \InvalidArgumentException('Invalid ZITADEL project ID.');
        }
        $groups = $env['OIDC_GROUPS_CLAIM'] ?? '';
        if (!is_string($groups) || ($groups !== '' && !preg_match('/^[A-Za-z_][A-Za-z0-9_:.-]{0,127}$/D', $groups))) {
            throw new \InvalidArgumentException('Invalid OIDC groups claim.');
        }
        return new self($issuer, $client, $required('OIDC_CLIENT_SECRET'), $redirect, $project === '' ? null : $project, $groups === '' ? null : $groups);
    }

    /** A local HTTP exception applies only to the development/test callback. */
    public static function url(string $url, bool $allowLocalHttp): void
    {
        $parts = parse_url($url);
        if ($parts === false || strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
            || !isset($parts['host'], $parts['scheme']) || $parts['host'] === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && ($parts['port'] < 1 || $parts['port'] > 65535))
            || ($parts['scheme'] !== 'https' && !($allowLocalHttp && $parts['scheme'] === 'http'
                && in_array($parts['host'], ['127.0.0.1', 'localhost', '[::1]'], true)))) {
            throw new \InvalidArgumentException('Invalid OIDC URL.');
        }
    }

    public function scope(): string
    {
        return 'openid profile email' . ($this->zitadelProjectId === null ? ''
            : ' urn:zitadel:iam:org:project:id:' . $this->zitadelProjectId . ':aud');
    }
    public function clientSecret(): string { return $this->clientSecret; }
    public function binding(): string
    {
        return hash('sha256', json_encode([$this->issuer, $this->clientId, $this->redirectUri, $this->scope(), $this->groupsClaim], JSON_THROW_ON_ERROR));
    }
    public function __debugInfo(): array { return ['issuer' => $this->issuer, 'credentials' => '[redacted]']; }
}
