<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** Immutable proposed metadata, never an OS path or executable content. */
final readonly class StorageChange
{
    public function __construct(public ?string $name = null, public string $parent = '', public ?int $size = null, public ?string $sha256 = null)
    {
        if ($parent !== '' && !preg_match('/^[a-f0-9]{32}$/D', $parent)) { throw new \InvalidArgumentException('Invalid storage parent.'); }
        if ($name !== null && ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 255
            || trim($name) !== $name || in_array($name, ['.', '..'], true) || preg_match('/[\x00-\x1f\x7f\/\\\\]/u', $name))) {
            throw new \InvalidArgumentException('Invalid storage name.');
        }
        if (($size === null) !== ($sha256 === null) || ($size !== null && ($size < 0 || !preg_match('/^[a-f0-9]{64}$/D', $sha256)))) {
            throw new \InvalidArgumentException('Invalid content proposal.');
        }
    }
}
