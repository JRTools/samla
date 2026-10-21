<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;
use Samla\Core\Storage\FileService;

/** Stable space IDs in URLs, human folder names below each space. */
final class Directory extends DAV\Collection implements DAV\IProperties, DAV\IMoveTarget, DAV\ICopyTarget, StoredNode
{
    use MutableEntry;
    public function __construct(private readonly FileService $files, private readonly string $actor,
        public readonly ?string $space = null, public readonly string $entry = '', private readonly string $name = '',
        private readonly ?string $displayName = null) {}
    public function getName() { return $this->name; }
    public function snapshot(): array
    {
        if ($this->entry === '') { throw new DAV\Exception\Forbidden(); }
        return $this->files->details($this->actor, $this->getSpace(), $this->entry);
    }
    public function createFile($name, $data = null)
    {
        $id = $this->files->upload($this->actor, $this->getSpace(), $this->entry, $name, $data);
        return '"' . $id . '-1"';
    }
    public function createDirectory($name)
    { $this->files->createFolder($this->actor, $this->getSpace(), $this->entry, $name); }
    public function moveInto($targetName, $sourcePath, DAV\INode $sourceNode)
    { return $this->receive($targetName, $sourceNode, false); }
    public function copyInto($targetName, $sourcePath, DAV\INode $sourceNode, $depth)
    { return $this->receive($targetName, $sourceNode, true); }
    private function receive(string $name, DAV\INode $source, bool $copy): bool
    {
        if (!$source instanceof StoredNode || $source->getSpace() !== $this->getSpace()) { throw new DAV\Exception\Forbidden(); }
        $source->transfer($name, $this->entry, $copy); return true;
    }
    public function getChild($name)
    {
        if ($this->space === null) {
            $after = null; $scanned = 0;
            do {
                $page = $this->files->spaces($this->actor, $after);
                foreach ($page['rows'] as $row) { if ($row['id'] === $name) { return new self($this->files, $this->actor, $name, '', $name, $row['name']); } }
                $after = $page['next'];
                $scanned += 100;
                if ($scanned >= 10000 && $after !== null) { throw new DAV\Exception\ServiceUnavailable(); }
            } while ($after !== null);
        } else {
            $row = $this->files->child($this->actor, $this->space, $this->entry, $name);
            if ($row !== null) { return $this->node($row); }
        }
        throw new DAV\Exception\NotFound();
    }
    private function node(array $row): DAV\INode
    {
        return $row['kind'] === 'folder'
            ? new self($this->files, $this->actor, $this->space, $row['id'], $row['name'])
            : new File($this->files, $this->actor, $this->space, $row);
    }
    public function getChildren()
    {
        $nodes = []; $after = null; $scanned = 0;
        do {
            $page = $this->space === null ? $this->files->spaces($this->actor, $after)
                : $this->files->listing($this->actor, $this->space, $this->entry, $after);
            foreach ($page['rows'] as $row) {
                $nodes[] = $this->space === null
                    ? new self($this->files, $this->actor, $row['id'], '', $row['id'], $row['name']) : $this->node($row);
            }
            $after = $page['next']; $scanned += 100;
            if ($scanned >= 10000 && $after !== null) { throw new DAV\Exception\ServiceUnavailable(); }
        } while ($after !== null);
        return $nodes;
    }
    public function getProperties($properties) { return ['{DAV:}displayname' => $this->displayName ?? $this->name]; }
    public function propPatch(DAV\PropPatch $propPatch) { /* Properties are read-only. */ }
}
