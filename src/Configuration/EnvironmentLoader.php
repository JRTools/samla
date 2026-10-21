<?php
declare(strict_types=1);
namespace Samla\Core\Configuration;

/** Literal configuration data, never PHP or shell code. No process-global mutations. */
final class EnvironmentLoader
{
    public static function fromGlobals(): array
    {
        $process = getenv();
        // Automated tests never inherit a deployment's .env or production credentials.
        if (($process['APP_ENV'] ?? null) === 'test') { return $process; }
        return self::load(dirname(__DIR__, 2) . '/.env', $process);
    }

    /** @param array<string, string> $process @return array<string, string> */
    public static function load(string $file, #[\SensitiveParameter] array $process): array
    {
        if (!file_exists($file) && !is_link($file)) { return $process; }
        if (!is_file($file)) { throw new \RuntimeException('Environment file is not a regular file.'); }
        $stream = @fopen($file, 'rb');
        if ($stream === false) { throw new \RuntimeException('Environment file is not readable.'); }
        try { $content = stream_get_contents($stream, 65537); }
        finally { fclose($stream); }
        if (!is_string($content) || strlen($content) > 65536 || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $content)) {
            throw new \InvalidArgumentException('Invalid environment file.');
        }
        if (str_starts_with($content, "\xef\xbb\xbf")) { $content = substr($content, 3); }
        $values = [];
        foreach (preg_split('/\r\n|\n|\r/', $content) as $index => $line) {
            $line = trim($line, " \t");
            if ($line === '' || str_starts_with($line, '#')) { continue; }
            if (!preg_match('/^(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=[ \t]*(.*)$/D', $line, $matches)
                || array_key_exists($matches[1], $values)) {
                throw new \InvalidArgumentException('Invalid environment assignment at line ' . ($index + 1) . '.');
            }
            $values[$matches[1]] = self::value($matches[2], $index + 1);
        }
        // Even an explicitly empty process value overrides the file.
        return $process + $values;
    }

    private static function value(#[\SensitiveParameter] string $raw, int $line): string
    {
        $invalid = static function () use ($line): never {
            throw new \InvalidArgumentException('Invalid environment value at line ' . $line . '.');
        };
        if ($raw === '' || $raw[0] === '#') { return ''; }
        $quote = $raw[0];
        if ($quote !== "'" && $quote !== '"') {
            $value = preg_split('/[ \t]+#/', $raw, 2)[0];
            $value = rtrim($value, " \t");
            if (preg_match('/[\s\x00-\x1f\x7f\x22\x27]/', $value)) { $invalid(); }
            return $value;
        }
        $value = '';
        for ($i = 1, $length = strlen($raw); $i < $length; ++$i) {
            $character = $raw[$i];
            if ($character === $quote) {
                $tail = ltrim(substr($raw, $i + 1), " \t");
                if ($tail !== '' && $tail[0] !== '#') { $invalid(); }
                return $value;
            }
            if ($quote === '"' && $character === '\\') {
                if (++$i >= $length) { $invalid(); }
                $escapes = ['n' => "\n", 'r' => "\r", 't' => "\t", '\\' => '\\', '"' => '"', '$' => '$'];
                if (!array_key_exists($raw[$i], $escapes)) { $invalid(); }
                $character = $escapes[$raw[$i]];
            }
            $value .= $character;
        }
        $invalid();
    }
}
