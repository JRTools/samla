<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
interface StoredNode
{
    public function snapshot(): array;
    public function getSpace(): string;
    public function transfer(string $name, string $parent, bool $copy): void;
}
