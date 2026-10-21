<?php
declare(strict_types=1);
namespace Samla\Core\Web;

/** Temporary local development adapter, never an authentication mechanism for deployed servers. */
final class DevelopmentAccess
{
    public static function actor(array $env, array $server, string $sapi): ?string
    {
        if (($env['APP_ENV'] ?? '') !== 'development' || ($env['APP_WEB_DEV_ACCESS'] ?? '') !== '1'
            || $sapi !== 'cli-server' || !str_starts_with($env['DB_NAME'] ?? '', 'samla_dev_')
            || !in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            || !in_array($server['SERVER_NAME'] ?? '', ['127.0.0.1', 'localhost', '::1', '[::1]'], true)
            || !preg_match('/\A(?:127\.0\.0\.1|localhost|\[::1\])(?::[0-9]{1,5})?\z/D', $server['HTTP_HOST'] ?? '')) { return null; }
        $actor = $env['APP_DEV_ACTOR_ID'] ?? '';
        return preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $actor) ? $actor : null;
    }
}
