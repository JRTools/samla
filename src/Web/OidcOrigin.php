<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Configuration\OidcConfig;

final class OidcOrigin
{
    public static function accepts(OidcConfig $config, array $server, string $sapi): bool
    {
        $expected = parse_url($config->redirectUri);
        $host = $server['HTTP_HOST'] ?? '';
        if (!is_string($host) || preg_match('/[\/\\\\\s@?#]/', $host)) { return false; }
        $actual = parse_url($expected['scheme'] . '://' . $host);
        if ($actual === false || strtolower($actual['host'] ?? '') !== strtolower($expected['host'])
            || ($actual['port'] ?? ($expected['scheme'] === 'https' ? 443 : 80)) !== ($expected['port'] ?? ($expected['scheme'] === 'https' ? 443 : 80))) { return false; }
        if ($expected['scheme'] === 'https') { return in_array($server['HTTPS'] ?? '', ['on', '1'], true); }
        return $sapi === 'cli-server' && in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && in_array($server['SERVER_NAME'] ?? '', ['127.0.0.1', 'localhost', '::1', '[::1]'], true);
    }
}
