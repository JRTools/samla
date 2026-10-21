<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;
use Sabre\HTTP;
use Samla\Core\Identity\AppPasswordService;
use Samla\Core\Storage\{FileService, StorageUnavailable};
use Samla\Core\Web\Response;
use Samla\PluginApi\{AccessDenied, MutationConflict};

/** One instance per HTTP request. No cookies, login redirects or OIDC bearer tokens. */
final class Kernel
{
    private bool $used = false;
    public function __construct(private readonly AppPasswordService $passwords, private readonly RequestIdentity $identity,
        private readonly FileService $files) {}
    public function handle(#[\SensitiveParameter] HTTP\RequestInterface $request, string $clientAddress): Response
    {
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
            // Writing is enabled only once the shared lock and atomic overwrite adapters are complete.
            if (!in_array($method, ['OPTIONS','PROPFIND','GET','HEAD'], true)) {
                return $this->error(405, ['Allow' => 'OPTIONS, PROPFIND, GET, HEAD']);
            }
            if ($method === 'PROPFIND' && !in_array($request->getHeader('Depth'), ['0','1'], true)) { return $this->error(403); }
            $this->validateXmlBody($request);
            $server = new DAV\Server(new Directory($this->files, $actor), new BufferedSapi());
            DAV\Server::$exposeVersion = false; $server->debugExceptions = false;
            $server->setBaseUri('/dav/'); $request->setBaseUrl('/dav/');
            $request->removeHeader('X-Sabre-Original-Method');
            $response = new HTTP\Response(); $server->httpRequest = $request; $server->httpResponse = $response;
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
                $response->setHeader('Allow', 'OPTIONS, PROPFIND, GET, HEAD');
                $response->setHeader('DAV', '1');
            }
            return self::adapt($response, $method === 'HEAD');
        } catch (AccessDenied) { return $this->error(403); }
        catch (MutationConflict) { return $this->error(412); }
        catch (StorageUnavailable) { return $this->error(503); }
        catch (\LengthException) { return $this->error(413); }
        catch (\InvalidArgumentException) { return $this->error(400); }
        catch (DAV\Exception $error) { return $this->error($error->getHTTPCode()); }
        catch (\Throwable $error) {
            error_log(sprintf('Samla DAV failed: %s in %s:%d', get_class($error), $error->getFile(), $error->getLine()));
            return $this->error(503);
        }
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
            if ($request->getMethod() !== 'PROPFIND') { throw new \InvalidArgumentException(); }
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
