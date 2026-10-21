<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
require dirname(__DIR__) . '/vendor/autoload.php';

use Samla\Core\Configuration\{AppConfig, EnvironmentLoader, OidcConfig};
use Samla\Core\Database\ConnectionFactory;
use Samla\Core\Web\{DevelopmentAccess, OidcOrigin, PageRenderer, Request, Response};

$renderer = new PageRenderer();
$data = ['actor' => null, 'csrf' => '', 'caps' => [], 'flash' => null];
try {
    $environment = EnvironmentLoader::fromGlobals();
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $actor = DevelopmentAccess::actor($environment, $_SERVER, PHP_SAPI);
    if ($path === '/dav' || str_starts_with((string) $path, '/dav/')) {
        $config = AppConfig::fromEnvironment($environment); $oidc = OidcConfig::fromEnvironment($environment);
        if ($config->maintenance || $oidc === null) { $response = new Response(503); }
        elseif (!OidcOrigin::accepts($oidc, $_SERVER, PHP_SAPI)) { $response = new Response(400); }
        elseif ($path === '/dav') { $response = new Response(308, headers: ['Location' => '/dav/']); }
        else {
            $response = (require dirname(__DIR__) . '/bootstrap/dav.php')((new ConnectionFactory())->connect($config->database), $config)
                ->handle(\Sabre\HTTP\Sapi::getRequest(), (string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        }
    } elseif ($actor === null) {
        $oidc = OidcConfig::fromEnvironment($environment);
        if ($oidc === null) {
            $response = new Response(503, $renderer->render('error', $data + ['status' => 503, 'error' => 'Die Anmeldung für diese Installation ist noch nicht eingerichtet.']));
        } elseif (!OidcOrigin::accepts($oidc, $_SERVER, PHP_SAPI)) {
            $response = new Response(400, $renderer->render('error', $data + ['status' => 400, 'error' => 'Bitte verwende die konfigurierte Samla-Adresse über die vorgesehene Verbindung.']));
        } else {
            $config = AppConfig::fromEnvironment($environment);
            if ($config->maintenance) { throw new \RuntimeException('Maintenance is active.'); }
            if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > (in_array(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), ['/files/upload', '/files/replace'], true) ? $config->maxUploadBytes + 1048576 : 16384)) { $response = new Response(413); }
            else { $response = (require dirname(__DIR__) . '/bootstrap/oidc-web.php')($config, $oidc)->handle(Request::fromGlobals()); }
        }
    } else {
        $config = AppConfig::fromEnvironment($environment);
        if ($config->maintenance) { throw new \RuntimeException('Maintenance is active.'); }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > (in_array(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), ['/files/upload', '/files/replace'], true) ? $config->maxUploadBytes + 1048576 : 16384)) {
            $response = new Response(413, $renderer->render('error', $data + ['status' => 413, 'error' => 'Die Formulardaten sind zu groß.']));
        } else {
            ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1');
            session_name('samla_dev');
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => false, 'path' => '/']);
            if (!session_start()) { throw new \RuntimeException('Session unavailable.'); }
            if (($_SESSION['actor'] ?? null) !== $actor) {
                $_SESSION = ['actor' => $actor]; session_regenerate_id(true);
            }
            $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
            $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
            $controller = (require dirname(__DIR__) . '/bootstrap/web.php')((new ConnectionFactory())->connect($config->database), $config);
            $response = $controller->handle(Request::fromGlobals(), $actor, $_SESSION['csrf'], $flash);
            if ($response->flash !== null) { $_SESSION['flash'] = $response->flash; }
            session_write_close();
        }
    }
} catch (\InvalidArgumentException) {
    $response = new Response(400, $renderer->render('error', $data + ['status' => 400, 'error' => 'Die Anfrage ist ungültig.']));
} catch (\Throwable $error) {
    error_log(sprintf('Samla web request failed: %s in %s:%d', get_class($error), $error->getFile(), $error->getLine()));
    $response = new Response(503, $renderer->render('error', $data + ['status' => 503, 'error' => 'Samla ist gerade nicht verfügbar. Bitte versuche es später erneut.']));
}
http_response_code($response->status);
foreach ($response->headers as $name => $value) { header($name . ': ' . $value); }
echo $response->body;
if ($response->stream !== null) { ($response->stream)(); }
