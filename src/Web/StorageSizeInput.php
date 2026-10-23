<?php
declare(strict_types=1);
namespace Samla\Core\Web;

/** Exact decimal conversion, deliberately without floating point or rounding. */
final class StorageSizeInput
{
    public const UNITS = ['GB'=>1000000000,'GiB'=>1073741824,'MB'=>1000000,'MiB'=>1048576,'KiB'=>1024,'B'=>1];
    public const MAX_BYTES = 1099511627776;

    public static function parse(string $value, string $unit): int
    {
        if (!isset(self::UNITS[$unit]) || !preg_match('/^(0|[1-9][0-9]{0,12})(?:[.,]([0-9]{1,6}))?$/D', $value, $m)) {
            throw new \InvalidArgumentException('Invalid storage size.');
        }
        $factor = self::UNITS[$unit]; $whole = (int) $m[1];
        if ($whole > intdiv(self::MAX_BYTES, $factor)) { throw new \InvalidArgumentException('Storage size too large.'); }
        $fraction = $m[2] ?? ''; $scale = 10 ** strlen($fraction);
        $numerator = (int) ($fraction === '' ? '0' : $fraction) * $factor;
        if ($numerator % $scale !== 0) { throw new \InvalidArgumentException('Fraction of a byte.'); }
        $bytes = $whole * $factor + intdiv($numerator, $scale);
        if ($bytes < 1 || $bytes > self::MAX_BYTES) { throw new \InvalidArgumentException('Storage size outside limits.'); }
        return $bytes;
    }

    /** An arbitrary existing byte quota round-trips without losing a single byte. */
    public static function display(int $bytes): array
    {
        foreach (['GiB','GB','MiB','MB','KiB','B'] as $unit) {
            if ($bytes >= self::UNITS[$unit] && $bytes % self::UNITS[$unit] === 0) {
                return ['value'=>(string) intdiv($bytes,self::UNITS[$unit]), 'unit'=>$unit];
            }
        }
        return ['value'=>(string)$bytes, 'unit'=>'B'];
    }
}
