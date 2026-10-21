<?php
declare(strict_types=1);
// Optional isolated evaluation only. Does not add SabreDAV to Samla's dependencies or routes.
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new \ErrorException($message, 0, $severity, $file, $line); });
$autoload = getenv('SAMLA_DAV_PROBE_AUTOLOAD');
if (!is_string($autoload) || !is_file($autoload)) { throw new \RuntimeException('Set SAMLA_DAV_PROBE_AUTOLOAD to an isolated evaluation autoloader.'); }
require $autoload;

use Sabre\DAV;
use Sabre\HTTP;

$file = new class extends DAV\File {
    private string $content = 'hello';
    public function getName() { return 'hello.txt'; }
    public function get() { return $this->content; }
    public function put($data) { $this->content = is_resource($data) ? stream_get_contents($data) : $data; return $this->getETag(); }
    public function getSize() { return strlen($this->content); }
    public function getETag() { return '"' . hash('sha256', $this->content) . '"'; }
};
$backend = new class extends DAV\Locks\Backend\AbstractBackend {
    public array $locks = [];
    public function getLocks($uri, $returnChildLocks) { return array_values(array_filter($this->locks, static fn($lock): bool => $lock->uri === $uri)); }
    public function lock($uri, DAV\Locks\LockInfo $info) { $info->uri = $uri; $this->locks[$info->token] = clone $info; return true; }
    public function unlock($uri, DAV\Locks\LockInfo $info) { unset($this->locks[$info->token]); return true; }
};
$auth = new class extends DAV\Auth\Backend\AbstractBasic {
    protected function validateUserPass($username, $password) { return $username === 'probe' && $password === 'synthetic-app-password'; }
};
$request = static function (string $method, string $path, array $headers = [], string $body = '') use ($file, $auth, $backend): HTTP\Response {
    // Like PHP-FPM, each request gets a fresh server and authentication plugin.
    $server = new DAV\Server(new DAV\SimpleCollection('root', [$file])); $server->setBaseUri('/dav/');
    $server->addPlugin(new DAV\Auth\Plugin($auth)); $server->addPlugin(new DAV\Locks\Plugin($backend));
    $request = new HTTP\Request($method, $path, $headers + ['Authorization' => 'Basic ' . base64_encode('probe:synthetic-app-password')], $body);
    $request->setBaseUrl('/dav/');
    $response = new HTTP\Response(); $server->httpRequest = $request; $server->httpResponse = $response;
    $server->invokeMethod($request, $response, false); return $response;
};
$check = static function (bool $condition): void { if (!$condition) { throw new \RuntimeException('DAV evaluation assertion failed.'); } };
$response = $request('PROPFIND', '/dav/', ['Depth' => '1']);
$check($response->getStatus() === 207 && str_contains($response->getBodyAsString(), 'hello.txt'));
$response = $request('GET', '/dav/hello.txt'); $check($response->getStatus() === 200 && $response->getBodyAsString() === 'hello');
$response = $request('OPTIONS', '/dav/'); $check($response->getStatus() === 200 && str_contains($response->getHeader('DAV'), '2'));
$response = $request('LOCK', '/dav/hello.txt', ['Content-Type' => 'application/xml', 'Timeout' => 'Second-60'],
    '<d:lockinfo xmlns:d="DAV:"><d:lockscope><d:exclusive/></d:lockscope><d:locktype><d:write/></d:locktype></d:lockinfo>');
$check($response->getStatus() === 200); $token = $response->getHeader('Lock-Token');
try { $request('PUT', '/dav/hello.txt', [], 'blocked'); throw new \RuntimeException('Missing lock token accepted.'); }
catch (DAV\Exception\Locked) { $check(true); }
$response = $request('PUT', '/dav/hello.txt', ['If' => '(' . $token . ')'], 'updated'); $check($response->getStatus() === 204);
$response = $request('UNLOCK', '/dav/hello.txt', ['Lock-Token' => $token]); $check($response->getStatus() === 204 && $backend->locks === []);
try { $request('GET', '/dav/hello.txt', ['Authorization' => 'Basic ' . base64_encode('probe:wrong')]); throw new \RuntimeException('Bad authentication accepted.'); }
catch (DAV\Exception\NotAuthenticated) { $check(true); }
fwrite(STDOUT, 'SabreDAV ' . DAV\Version::VERSION . ': PROPFIND, GET, OPTIONS, Basic, LOCK, locked PUT, token PUT and UNLOCK: OK' . "\n");
