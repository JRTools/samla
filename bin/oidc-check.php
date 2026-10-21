<?php
declare(strict_types=1);

// Read-only preflight. Does not send client credentials or authenticate a user.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__) . '/vendor/autoload.php';

use Samla\Core\Configuration\OidcConfig;
use Samla\Core\Identity\Oidc\ProviderMetadata;

try {
    $config = OidcConfig::fromGlobals();
    if ($config === null) { throw new \RuntimeException('OIDC is disabled.'); }
    $context = stream_context_create(['http' => [
        'method' => 'GET', 'timeout' => 10, 'follow_location' => 0, 'max_redirects' => 0,
        'ignore_errors' => true, 'header' => "Accept: application/json\r\nCache-Control: no-cache\r\n",
    ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $stream = @fopen(rtrim($config->issuer, '/') . '/.well-known/openid-configuration', 'rb', false, $context);
    if ($stream === false) { throw new \RuntimeException('Discovery unavailable.'); }
    try {
        $metadata = stream_get_meta_data($stream);
        $headers = $metadata['wrapper_data'] ?? [];
        $body = stream_get_contents($stream, 65537);
        if (!is_array($headers) || !preg_match('/^HTTP\/\S+ 200(?: |$)/D', $headers[0] ?? '')
            || !is_string($body) || strlen($body) > 65536) {
            throw new \RuntimeException('Discovery failed.');
        }
    } finally { fclose($stream); }
    $document = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($document)) { throw new \RuntimeException('Invalid discovery document.'); }
    ProviderMetadata::fromDiscovery($config, $document);
    fwrite(STDOUT, "OIDC configuration and discovery: OK\nAuthorization Code + PKCE S256; token authentication: client_secret_basic.\nNo login, token validation or production readiness is established by this check.\n");
} catch (\Throwable) {
    // No raw exception, response, configuration or credential output.
    fwrite(STDERR, "OIDC preflight failed. Check the OIDC environment and provider discovery.\n");
    exit(1);
}
