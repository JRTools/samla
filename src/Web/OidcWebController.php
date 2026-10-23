<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Configuration\{AppConfig, OidcConfig};
use Samla\Core\Identity\PdoIdentityRepository;
use Samla\Core\Identity\Oidc\{AuthorizationFlow, FacileOidcClient, LoginProvisioner, LoginRejected, LoginTransaction};
use Samla\PluginApi\AccessDenied;

/** Trusted HTTP composition. All token I/O takes place after consuming session state. */
final readonly class OidcWebController
{
    public function __construct(private OidcConfig $config, private \PDO $pdo, private PdoIdentityRepository $identities,
        private LoginProvisioner $provisioner, private AdminController $admin, private PageRenderer $renderer = new PageRenderer(),
        private ?AppPasswordController $appPasswords = null, private ?\Closure $afterLogin = null) {}

    public function handle(Request $request): Response
    {
        $secure = parse_url($this->config->redirectUri, PHP_URL_SCHEME) === 'https';
        ini_set('session.use_strict_mode', '1'); ini_set('session.use_only_cookies', '1'); ini_set('session.use_trans_sid', '0');
        session_name($secure ? '__Host-samla' : 'samla_oidc_dev');
        session_set_cookie_params(['lifetime' => 0, 'domain' => '', 'httponly' => true, 'samesite' => 'Lax', 'secure' => $secure, 'path' => '/']);
        if (!session_start()) { throw new \RuntimeException('Session unavailable.'); }
        $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
        try {
            if ($request->path === '/auth/callback') { return $this->callback($request); }
            if ($request->path === '/auth/logout') {
                if ($request->method !== 'POST') { return $this->page('error', 405, ['status' => 405, 'error' => 'Abmelden benötigt ein Formular.'], ['Allow' => 'POST']); }
                if (!$this->csrf($request)) { return $this->page('error', 403, ['status' => 403, 'error' => 'Die Sicherheitsprüfung ist fehlgeschlagen.']); }
                $_SESSION = [];
                if (!session_destroy()) { session_write_close(); throw new \RuntimeException('Session deletion failed.'); }
                setcookie(session_name(), '', ['expires' => 1, 'path' => '/', 'httponly' => true, 'secure' => $secure, 'samesite' => 'Lax']);
                return new Response(303, headers: ['Location' => '/auth/login']);
            }
            if ($request->path === '/auth/login') {
                if ($request->method === 'POST') {
                    if (!$this->csrf($request)) { return $this->page('error', 403, ['status' => 403, 'error' => 'Die Sicherheitsprüfung ist fehlgeschlagen.']); }
                    unset($_SESSION['identity'], $_SESSION['exchange']);
                    if (!session_regenerate_id(true)) { throw new \RuntimeException('Session renewal failed.'); }
                    $provider = FacileOidcClient::discover($this->config);
                    $pending = null; $url = (new AuthorizationFlow($this->config, $provider->metadata))->begin($pending, time());
                    $_SESSION['pending'] = $pending; $_SESSION['provider'] = $provider->metadata;
                    return new Response(303, headers: ['Location' => $url] + $this->loginHeaders());
                }
                if ($request->method !== 'GET') { return new Response(405, headers: ['Allow' => 'GET, POST']); }
                return $this->page('login', 200);
            }
            $session = $_SESSION['identity'] ?? null;
            if (!is_array($session) || !is_int($session['expires'] ?? null) || $session['expires'] <= time()
                || ($session['binding'] ?? null) !== $this->config->binding() || !is_string($session['id'] ?? null)) {
                unset($_SESSION['identity']);
                return $request->path === '/' && $request->method === 'GET' ? $this->page('login', 200)
                    : new Response(303, headers: ['Location' => '/auth/login']);
            }
            $query = $this->pdo->prepare("SELECT display_name, status FROM identity_users WHERE id = ?"); $query->execute([$session['id']]);
            $user = $query->fetch();
            if ($user === false || $user['status'] !== 'active') {
                unset($_SESSION['identity']);
                return $this->page('error', 403, ['status' => 403, 'error' => 'Dieser Zugang ist gesperrt.']);
            }
            if (str_starts_with($request->path, '/account/app-passwords') && $this->appPasswords !== null) {
                return $this->appPasswords->handle($request, $session['id'], $_SESSION['csrf'], ['actor' => $session['id'],
                    'csrf' => $_SESSION['csrf'], 'caps' => $this->admin->capabilities($session['id']), 'flash' => null, 'oidc' => true, 'display_name' => $user['display_name']]);
            }
            if ($request->path === '/account') {
                if ($request->method !== 'GET') { return new Response(405, headers: ['Allow' => 'GET']); }
                // Own account is available to authenticated users before role assignment.
                return $this->page('account', 200, ['actor' => $session['id'], 'name' => $user['display_name'],
                    'fresh' => $this->identities->isAccessAllowed($session['id']), 'caps' => $this->admin->capabilities($session['id'])]);
            }
            $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
            $response = $this->admin->handle($request, $session['id'], $_SESSION['csrf'], $flash, true, $user['display_name']);
            if ($response->flash !== null) { $_SESSION['flash'] = $response->flash; }
            return $response;
        } finally { if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); } }
    }
    private function callback(Request $request): Response
    {
        if ($request->method !== 'GET') { return new Response(405, headers: ['Allow' => 'GET']); }
        try {
            $pending = $_SESSION['pending'] ?? null; $metadata = $_SESSION['provider'] ?? null;
            unset($_SESSION['pending'], $_SESSION['provider']);
            if (!$pending instanceof LoginTransaction || !$metadata instanceof \Samla\Core\Identity\Oidc\ProviderMetadata) { throw new LoginRejected(); }
            // Do not discover/fetch anything on an invalid callback. Persist consumption first.
            $ticket = bin2hex(random_bytes(16)); $_SESSION['exchange'] = $ticket;
            if (!session_write_close()) { throw new \RuntimeException('Session persistence failed.'); }
            $grant = (new AuthorizationFlow($this->config, $metadata))->consume($request->query, $pending, time());
            $provider = FacileOidcClient::discover($this->config);
            $login = $provider->redeem($grant);
            if (!session_start()) { throw new \RuntimeException('Session unavailable.'); }
            if (($_SESSION['exchange'] ?? null) !== $ticket) { throw new LoginRejected(); }
            unset($_SESSION['exchange']);
            $id = $this->provisioner->login($login);
            if($this->afterLogin!==null){($this->afterLogin)($id);}
            $_SESSION = [];
            if (!session_regenerate_id(true)) { throw new \RuntimeException('Session renewal failed.'); }
            $_SESSION['identity'] = ['id' => $id, 'binding' => $this->config->binding(), 'expires' => min($login->expiresAt, time() + 3600)];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            return new Response(303, headers: ['Location' => '/account']);
        } catch (LoginRejected|\InvalidArgumentException|AccessDenied) {
            if (session_status() === PHP_SESSION_ACTIVE && isset($ticket) && ($_SESSION['exchange'] ?? null) === $ticket) { unset($_SESSION['exchange']); }
            return $this->page('login', 401, ['loginError' => 'Die Anmeldung konnte nicht bestätigt werden. Bitte starte sie erneut.']);
        }
    }
    private function csrf(Request $request): bool
    {
        $value = $request->post['csrf'] ?? null;
        return is_string($value) && strlen($value) === 64 && hash_equals($_SESSION['csrf'], $value);
    }
    private function page(string $page, int $status, array $data = [], array $headers = []): Response
    {
        if ($page === 'login') { $headers += $this->loginHeaders(); }
        return new Response($status, $this->renderer->render($page, $data + ['actor' => null, 'csrf' => $_SESSION['csrf'] ?? '',
            'caps' => [], 'flash' => null, 'oidc' => true]), $headers);
    }
    private function loginHeaders(): array
    {
        $parts = parse_url($this->config->issuer);
        $origin = 'https://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        return ['Content-Security-Policy' => "default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; form-action 'self' " . $origin . "; base-uri 'none'; frame-ancestors 'none'"];
    }
}

