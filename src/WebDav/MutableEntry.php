<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;

trait MutableEntry
{
    public function getSpace(): string { return $this->space ?? throw new DAV\Exception\Forbidden(); }
    private function mutate(string $operation, ?string $name = null, string $parent = ''): void
    {
        $row = $this->snapshot();
        $this->files->change($this->actor, $this->getSpace(), $row['id'], $operation, $row['name'], $row['parent_id'], $name, $parent, (int) $row['revision']);
    }
    public function delete() { $this->mutate('trash'); }
    public function setName($name) { $this->mutate('rename', $name); }
    public function transfer(string $name, string $parent, bool $copy): void
    {
        if ($copy && $this->snapshot()['kind'] !== 'file') { throw new DAV\Exception\NotImplemented(); }
        $this->mutate($copy ? 'copy' : 'move', $name, $parent);
    }
}
