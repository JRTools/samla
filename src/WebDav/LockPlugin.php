<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;
use Sabre\HTTP\{RequestInterface, ResponseInterface};
use Samla\Core\Storage\FileService;

/** Lease cleanup belongs to the same SQL transaction as the core deletion. */
final class LockPlugin extends \Sabre\DAV\Locks\Plugin
{
    public function __construct(DAV\Locks\Backend\BackendInterface $backend, private readonly FileService $files, private readonly string $actor)
    { parent::__construct($backend); }
    public function afterUnbind($path) {}
    public function httpLock(RequestInterface $request, ResponseInterface $response)
    {
        $body = $request->getBodyAsString(); if ($body !== '') { $this->validateBody($body); }
        $path = $request->getPath();
        if ($this->server->tree->nodeExists($path)) { return parent::httpLock($request, $response); }
        if ($body === '') { throw new DAV\Exception\BadRequest(); }
        $info = $this->parseLockRequest($body);
        if ($info->scope === DAV\Locks\LockInfo::SHARED) { throw new DAV\Exception\NotImplemented(); }
        if ($info->scope !== DAV\Locks\LockInfo::EXCLUSIVE) { throw new DAV\Exception\BadRequest(); }
        [$parentPath, $name] = \Sabre\Uri\split($path); $parent = $this->server->tree->getNodeForPath($parentPath);
        if (!$parent instanceof Directory) { throw new DAV\Exception\Conflict(); }
        $requested = $this->getTimeoutHeader(); $seconds = $requested <= 0 ? 3600 : min(3600, $requested);
        $recursive = $this->server->getHTTPDepth() === DAV\Server::DEPTH_INFINITY;
        $this->files->createLockedFile($this->actor, $parent->getSpace(), $parent->entry, $name, $info->token, $recursive, $seconds, (string) $info->owner);
        $info->uri = $path; $info->depth = $recursive ? DAV\Server::DEPTH_INFINITY : 0; $info->timeout = $seconds; $info->created = time();
        $response->setStatus(201); $response->setHeader('Content-Type', 'application/xml; charset=utf-8');
        $response->setHeader('Lock-Token', '<opaquelocktoken:' . $info->token . '>');
        $response->setBody($this->generateLockResponse($info)); return false;
    }
    private function validateBody(string $body): void
    {
        // The kernel has already bounded XML and rejected every DTD, including UTF-16 input.
        $document = new \DOMDocument();
        if (!$document->loadXML($body, LIBXML_NONET) || $document->documentElement?->namespaceURI !== 'DAV:'
            || $document->documentElement->localName !== 'lockinfo') { throw new DAV\Exception\BadRequest(); }
        $xpath = new \DOMXPath($document); $xpath->registerNamespace('d', 'DAV:');
        $scope = $xpath->query('/d:lockinfo/d:lockscope/*'); $type = $xpath->query('/d:lockinfo/d:locktype/*');
        if ((int) $xpath->evaluate('count(/d:lockinfo/d:lockscope)') !== 1 || (int) $xpath->evaluate('count(/d:lockinfo/d:locktype)') !== 1
            || $scope->length !== 1 || $type->length !== 1 || $scope->item(0)->namespaceURI !== 'DAV:'
            || !in_array($scope->item(0)->localName, ['exclusive','shared'], true) || $type->item(0)->namespaceURI !== 'DAV:'
            || $type->item(0)->localName !== 'write') { throw new DAV\Exception\BadRequest(); }
    }
    private function encoded(array $leases): array
    {
        return array_map(static function (DAV\Locks\LockInfo $info): DAV\Locks\LockInfo {
            $copy = clone $info; $copy->uri = implode('/', array_map('rawurlencode', explode('/', $info->uri))); return $copy;
        }, $leases);
    }
    protected function generateLockResponse(DAV\Locks\LockInfo $lockInfo)
    { return parent::generateLockResponse($this->encoded([$lockInfo])[0]); }
    public function propFind(DAV\PropFind $propFind, DAV\INode $node)
    {
        $propFind->handle('{DAV:}supportedlock', static fn(): array => ['{DAV:}lockentry' => [
            '{DAV:}lockscope' => ['{DAV:}exclusive' => null], '{DAV:}locktype' => ['{DAV:}write' => null]]]);
        $propFind->handle('{DAV:}lockdiscovery', fn(): DAV\Xml\Property\LockDiscovery => new DAV\Xml\Property\LockDiscovery($this->encoded($this->getLocks($propFind->getPath()))));
    }
}
