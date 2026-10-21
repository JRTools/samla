<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

final class EntryName
{
    public static function validate(string $name): string
    {
        if ($name === '' || !mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > 255
            || trim($name) !== $name || in_array($name, ['.', '..'], true)
            || preg_match('/[\x00-\x1f\x7f\/\\\\]/u', $name)) {
            throw new \InvalidArgumentException('Invalid entry name.');
        }
        return $name;
    }
}
