<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\DAV;
use Sabre\HTTP;
use Samla\Core\Storage\StorageWriteGuard;

final class ProtocolPaths
{
    public static function destination(string $value, string $origin): string
    {
        if (strlen($value) > 4096 || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) { throw new \InvalidArgumentException(); }
        if (str_starts_with($value, '/')) { Kernel::validatePath($value); return $value; }
        $parts = parse_url($value); $base = parse_url($origin);
        if ($parts === false || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || strtolower($parts['scheme'] ?? '') !== strtolower($base['scheme']) || strtolower($parts['host'] ?? '') !== strtolower($base['host'])
            || ($parts['port'] ?? (($parts['scheme'] ?? '') === 'https' ? 443 : 80)) !== ($base['port'] ?? ($base['scheme'] === 'https' ? 443 : 80))) { throw new \InvalidArgumentException(); }
        $path = $parts['path'] ?? ''; Kernel::validatePath($path); return $path;
    }
    public static function proofs(HTTP\RequestInterface $request, DAV\Server $server, string $origin): array
    {
        $header = $request->getHeader('If'); if ($header === null) { return []; }
        if (strlen($header) > 8192 || trim($header) === '') { throw new \InvalidArgumentException(); }
        // Consume the whole grammar, rather than accepting regex matches inside malformed input.
        $offset = 0;
        $pattern = '~\G[ \t]*(?:<([^<>\r\n]+)>[ \t])?((?:\((?:Not[ \t])?(?:<[^<>\r\n]+>(?:[ \t]?\[[^\]\r\n]+\])?|\[[^\]\r\n]+\])\)[ \t]*)+)~';
        while ($offset < strlen($header)) {
            if (!preg_match($pattern, $header, $m, 0, $offset)) { throw new \InvalidArgumentException(); }
            if (($m[1] ?? '') !== '') { self::destination($m[1], $origin); }
            $offset += strlen($m[0]);
        }
        $tokens = [];
        foreach ($server->getIfConditions($request) as $condition) {
            foreach ($condition['tokens'] as $proof) {
                if (!$proof['negate'] && str_starts_with($proof['token'], 'opaquelocktoken:')) {
                    $token = substr($proof['token'], 16); StorageWriteGuard::token($token); $tokens[] = $token;
                    if (count($tokens) > 32) { throw new \LengthException(); }
                }
            }
        }
        return array_values(array_unique($tokens));
    }
}
