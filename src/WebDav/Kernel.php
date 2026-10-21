<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;
use Sabre\HTTP;
use Samla\Core\Identity\AppPasswordService;
use Samla\Core\Storage\{FileService, StorageLockService, StorageLocked, StorageUnavailable};
use Samla\Core\Web\Response;
use Samla\PluginApi\{AccessDenied, MutationConflict};

/** One instance per HTTP request. No cookies, login redirects or OIDC bearer tokens. */
final class Kernel
{
    private bool $used = false;
    public function __construct(private readonly AppPasswordService $passwords, private readonly RequestIdentity $identity,
        private readonly FileService $files, private readonly StorageLockService $locks,
        private readonly int $maxBytes = 16777216, private readonly string $origin = 'https://samla.invalid') {}
    public function handle(#[\SensitiveParameter] HTTP\RequestInterface $request, string $clientAddress): Response
    {
        $staged = null;
        try {
            if ($this->used) { throw new \LogicException('DAV kernel is request-local.'); }
            $this->used = true;
            $header = $request->getHeader('Authorization') ?? '';
            if (strlen($header) > 1024 || !preg_match('/^Basic ([A-Za-z0-9+\/=]+)$/Di', $header, $m)) { return $this->unauthenticated(); }
            $decoded = base64_decode($m[1], true);
            if (!is_string($decoded) || !str_contains($decoded, ':')) { return $this->unauthenticated(); }
            [$actor, $password] = explode(':', $decoded, 2);
            if (!$this->passwords->authenticate($actor, $password, $clientAddress)) { return $this->unauthenticated(); }
            $this->identity->bind($actor, substr($password, 6, 32)); unset($password, $decoded, $m, $header);
            $uri = $request->getUrl(); self::validatePath($uri);
            $method = $request->getMethod();
            $allowed = 'OPTIONS, PROPFIND, GET, HEAD, PUT, MKCOL, MOVE, COPY, DELETE, LOCK, UNLOCK';
            if (!in_array($method, explode(', ', $allowed), true)) {
                return $this->error(405, ['Allow' => $allowed]);
            }
            if ($method === 'PROPFIND' && !in_array($request->getHeader('Depth'), ['0','1'], true)) { return $this->error(403); }
            if ($method !== 'PUT') { $this->validateXmlBody($request); }
            $root = new Directory($this->files, $actor);
            $server = new DAV\Server($root, new BufferedSapi());
            DAV\Server::$exposeVersion = false; $server->debugExceptions = false;
            $server->setBaseUri('/dav/'); $request->setBaseUrl('/dav/');
            $request->removeHeader('X-Sabre-Original-Method');
            $server->addPlugin(new LockPlugin(new LockBackend($root, $this->files, $this->locks, $actor), $this->files, $actor));
            $response = new HTTP\Response(); $server->httpRequest = $request; $server->httpResponse = $response;
            $server->on('beforeUnbind', static function (string $path) use ($method, $request): void {
                if ($method === 'COPY' || ($method === 'MOVE' && $path !== $request->getPath())) { throw new DAV\Exception\Conflict(); }
            }, 1);
            $this->identity->lockTokens = ProtocolPaths::proofs($request, $server, $this->origin);
            if (strlen($request->getHeader('Lock-Token') ?? '') > 256) { throw new \InvalidArgumentException(); }
            if (in_array($method, ['MOVE','COPY'], true)) {
                if (!in_array($request->getHeader('Overwrite'), [null,'T','F'], true)
                    || ($method === 'COPY' && !in_array($request->getHeader('Depth'), [null,'0','infinity'], true))) { throw new \InvalidArgumentException(); }
                $destination = ProtocolPaths::destination($request->getHeader('Destination') ?? '', $this->origin);
                $request->setHeader('Destination', $this->origin . $destination);
                // Use one core mutation rather than Sabre's delete-then-transfer fallback.
                $destinationPath = rawurldecode(substr($destination, 5));
                $targetEntry = $server->tree->nodeExists($destinationPath) ? $server->tree->getNodeForPath($destinationPath) : null;
                if ($targetEntry !== null && $request->getHeader('Overwrite') === 'F') { return $this->error(412); }
                $source = $server->tree->getNodeForPath($request->getPath());
                [$targetParent, $targetName] = \Sabre\Uri\split($destinationPath);
                $target = $server->tree->getNodeForPath($targetParent);
                if (!$source instanceof StoredNode || !$target instanceof Directory || $source->getSpace() !== $target->getSpace()) { return $this->error(403); }
                if ($method === 'COPY' && !$source instanceof File) { return $this->error(501); }
                $sourceRow = $source->snapshot();
                $targetRow = $targetEntry instanceof StoredNode ? $targetEntry->snapshot() : null;
                if ($targetRow !== null && ($targetRow['id'] === $sourceRow['id'] || $targetRow['kind'] !== $sourceRow['kind'])) { return $this->error(409); }
                if ($method === 'MOVE' && !in_array($request->getHeader('Depth'), [null, 'infinity'], true)) { throw new \InvalidArgumentException(); }
                $server->on('method:' . $method, function (HTTP\RequestInterface $request, HTTP\ResponseInterface $response) use ($actor, $method, $source, $sourceRow, $targetRow, $target, $targetName): bool {
                    $this->files->change($actor, $source->getSpace(), $sourceRow['id'], $method === 'MOVE' ? 'move' : 'copy',
                        $sourceRow['name'], $sourceRow['parent_id'], $targetName, $target->entry, (int) $sourceRow['revision'],
                        $targetRow['id'] ?? null, $targetRow === null ? null : (int) $targetRow['revision']);
                    $response->setStatus($targetRow === null ? 201 : 204); $response->setHeader('Content-Length', '0'); return false;
                }, 50);
            }
            if ($method === 'LOCK') {
                if (!in_array($request->getHeader('Depth'), [null,'0','infinity'], true)) { throw new \InvalidArgumentException(); }
            }
            if ($method === 'PUT') {
                [$parentPath] = \Sabre\Uri\split($request->getPath());
                $parentNode = $server->tree->getNodeForPath($parentPath);
                if (!$parentNode instanceof Directory) { throw new DAV\Exception\Conflict(); }
                $action = $server->tree->nodeExists($request->getPath()) ? 'files.update' : 'files.create';
                if (!$this->files->allowed($actor, $action, $parentNode->getSpace())) { throw new AccessDenied(); }
                $staged = $this->boundedPut($request);
            }
            // Apply read rights before conditional GET/HEAD can return metadata or a 304 response.
            if (in_array($method, ['GET','HEAD'], true)) {
                $node = $server->tree->getNodeForPath($request->getPath());
                if ($node instanceof File) { $node->requireRead(); }
            }
            $server->invokeMethod($request, $response, false);
            if (isset($node) && $node instanceof File) {
                $response->setHeader('Content-Disposition', "attachment; filename*=UTF-8''" . rawurlencode($node->getName()));
            }
            if ($method === 'OPTIONS') {
                $response->setHeader('Allow', $allowed);
                $response->setHeader('DAV', '1, 2');
            }
            return self::adapt($response, $method === 'HEAD');
        } catch (AccessDenied) { return $this->error(403); }
        catch (StorageLocked) { return $this->error(423); }
        catch (MutationConflict) { return $this->error(412); }
        catch (StorageUnavailable) { return $this->error(503); }
        catch (\LengthException) { return $this->error(413); }
        catch (\InvalidArgumentException) { return $this->error(400); }
        catch (\Sabre\Xml\ParseException) { return $this->error(400); }
        catch (DAV\Exception $error) { return $this->error($error->getHTTPCode()); }
        catch (\Throwable $error) {
            error_log(sprintf('Samla DAV failed: %s in %s:%d', get_class($error), $error->getFile(), $error->getLine()));
            return $this->error(503);
        }
        finally { if (is_resource($staged)) { fclose($staged); } }
    }
    public static function validatePath(string $path): void
    {
        if (strlen($path) > 4096 || !str_starts_with($path, '/dav/') || strpbrk($path, '?#') !== false
            || str_contains($path, '//') || preg_match('/%(?![A-Fa-f0-9]{2})/', $path)) { throw new \InvalidArgumentException('Invalid DAV path.'); }
        foreach (explode('/', substr($path, 5)) as $part) {
            $name = rawurldecode($part);
            if ($name === '.' || $name === '..' || preg_match('/[\x00-\x1f\x7f\/\\\\]/', $name) || preg_match('//u', $name) !== 1) {
                throw new \InvalidArgumentException('Invalid DAV path component.');
            }
        }
    }
    private function validateXmlBody(HTTP\RequestInterface $request): void
    {
        $body = $request->getBody();
        if (is_resource($body)) { $body = stream_get_contents($body, 65537); }
        if ($body === null) { $body = ''; }
        if (!is_string($body)) { throw new \InvalidArgumentException(); }
        if (strlen($body) > 65536) { throw new \LengthException(); }
        if ($body !== '') {
            if (!in_array($request->getMethod(), ['PROPFIND','LOCK'], true)) { throw new \InvalidArgumentException(); }
            $old = libxml_use_internal_errors(true);
            try {
                $xml = new \XMLReader();
                if (!$xml->XML($body, null, LIBXML_NONET)) { throw new \InvalidArgumentException(); }
                $xml->setParserProperty(\XMLReader::LOADDTD, false); $xml->setParserProperty(\XMLReader::SUBST_ENTITIES, false);
                try { while ($xml->read()) { if ($xml->nodeType === \XMLReader::DOC_TYPE) { throw new \InvalidArgumentException(); } } }
                finally { $xml->close(); }
                if (libxml_get_errors() !== []) { throw new \InvalidArgumentException(); }
            } finally { libxml_clear_errors(); libxml_use_internal_errors($old); }
        }
        $request->setBody($body);
    }
    /** Bounded disk-spooled stream also prevents Finder's library fallback from copying an unbounded body. */
    private function boundedPut(HTTP\RequestInterface $request)
    {
        $length = $request->getHeader('Content-Length'); $expected = $request->getHeader('X-Expected-Entity-Length');
        foreach ([$length, $expected] as $value) {
            if ($value !== null && (!ctype_digit($value) || strlen($value) > 15)) { throw new \InvalidArgumentException(); }
            if ($value !== null && (int) $value > $this->maxBytes) { throw new \LengthException(); }
        }
        if ($request->getHeader('Content-Range') !== null) { throw new \InvalidArgumentException(); }
        $source = $request->getBodyAsStream(); $temp = fopen('php://temp/maxmemory:1048576', 'w+b'); $size = 0;
        try {
            while (!feof($source)) {
                $chunk = fread($source, 65536); if ($chunk === false || ($chunk === '' && !feof($source))) { throw new \RuntimeException(); }
                $size += strlen($chunk); if ($size > $this->maxBytes) { throw new \LengthException(); }
                if (fwrite($temp, $chunk) !== strlen($chunk)) { throw new \RuntimeException(); }
            }
            if (($expected !== null && (int) $expected > 0 && $size === 0)
                || ($expected === null && $length !== null && (int) $length !== $size)) { throw new \InvalidArgumentException(); }
            rewind($temp); $request->setBody($temp); $request->removeHeader('X-Expected-Entity-Length');
            $request->setHeader('Content-Length', (string) $size); return $temp;
        } catch (\Throwable $error) { fclose($temp); throw $error; }
    }
    private function unauthenticated(): Response
    { return $this->error(401, ['WWW-Authenticate' => 'Basic realm="Samla", charset="UTF-8"']); }
    private function error(int $code, array $headers = []): Response
    {
        return new Response($code, '<?xml version="1.0" encoding="utf-8"?><d:error xmlns:d="DAV:"><d:responsedescription>Samla request failed.</d:responsedescription></d:error>',
            $headers + ['Content-Type' => 'application/xml; charset=utf-8']);
    }
    public static function adapt(HTTP\ResponseInterface $response, bool $head = false): Response
    {
        $headers = []; foreach ($response->getHeaders() as $name => $values) { $headers[$name] = implode(', ', $values); }
        $body = $response->getBody();
        if ($head) { if (is_resource($body)) { fclose($body); } return new Response($response->getStatus(), '', $headers); }
        if (is_resource($body)) {
            $length = $response->getHeader('Content-Length');
            return new Response($response->getStatus(), '', $headers, stream: static function () use ($body, $length): void {
                $output = fopen('php://output', 'wb');
                try {
                    if ($length === null) { stream_copy_to_stream($body, $output); }
                    else { stream_copy_to_stream($body, $output, (int) $length); }
                } finally { fclose($body); fclose($output); }
            });
        }
        if (is_callable($body)) { return new Response($response->getStatus(), '', $headers, stream: \Closure::fromCallable($body)); }
        return new Response($response->getStatus(), (string) $body, $headers);
    }
}
