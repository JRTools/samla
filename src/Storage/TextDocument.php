<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

/** Bounded plain UTF-8 source. It is never interpreted as markup or code. */
final class TextDocument
{
    public const MAX_BYTES = 65536;
    public static function supports(string $name): bool
    { return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['txt','md','markdown','json','yaml','yml','csv','tsv','log','ini','conf','xml','html','css','js','ts','php'], true); }
    public static function validate(string $text): void
    {
        if (strlen($text) > self::MAX_BYTES) { throw new \LengthException('Text editor capacity exceeded.'); }
        if (!mb_check_encoding($text, 'UTF-8') || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text)) {
            throw new \InvalidArgumentException('The editor requires plain UTF-8 text.');
        }
    }
    public static function normalize(string $text): string
    { $text = str_replace(["\r\n", "\r"], "\n", $text); self::validate($text); return $text; }
}
