<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;
use Samla\Core\Storage\FileService;
use Samla\PluginApi\AccessDenied;

final class File extends DAV\File implements StoredNode
{
    use MutableEntry;
    public function __construct(private readonly FileService $files, private readonly string $actor,
        public readonly string $space, private readonly array $row) {}
    public function getName() { return $this->row['name']; }
    public function snapshot(): array { return $this->row; }
    public function getSize() { return (int) $this->row['size_bytes']; }
    public function getETag() { return '"' . $this->row['id'] . '-' . $this->row['revision'] . '"'; }
    // created_at is not a modification timestamp. Omit Last-Modified until tracked explicitly.
    public function getContentType() { return 'application/octet-stream'; }
    public function requireRead(): void
    { if (!$this->files->allowed($this->actor, 'files.read', $this->space)) { throw new AccessDenied(); } }
    public function get()
    { return $this->files->download($this->actor, $this->space, $this->row['id'], (int) $this->row['revision'])['stream']; }
    public function put($data)
    {
        $this->files->replace($this->actor, $this->space, $this->row['id'], (int) $this->row['revision'], $data);
        return '"' . $this->row['id'] . '-' . ((int) $this->row['revision'] + 1) . '"';
    }
}
