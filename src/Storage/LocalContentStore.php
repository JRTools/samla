<?php
declare(strict_types=1);
namespace Samla\Core\Storage;

/** Private immutable objects. Database publication follows successful file publication. */
final readonly class LocalContentStore
{
    public const MAX_BYTES = 16777216;
    public string $root;
    public function __construct(string $root)
    {
        if (!str_starts_with($root, '/') || str_contains($root, "\0")) { throw new \InvalidArgumentException('Absolute storage root required.'); }
        // A hosting sandbox may prohibit stat() on ancestors above its allowed root.
        // Treat that server-configured boundary as trusted and inspect only within it.
        $boundary = null;
        $restriction = (string) ini_get('open_basedir');
        if ($restriction !== '') {
            foreach (explode(PATH_SEPARATOR, $restriction) as $base) {
                if ($base === '.') { $base = (string) getcwd(); }
                $base = rtrim($base, '/');
                if ($base !== '' && str_starts_with($base, '/') && ($root === $base || str_starts_with($root, $base . '/'))
                    && ($boundary === null || strlen($base) > strlen($boundary))) { $boundary = $base; }
            }
            if ($boundary === null && !in_array('/', explode(PATH_SEPARATOR, $restriction), true)) {
                throw new \InvalidArgumentException('Storage is outside the hosting sandbox.');
            }
        }
        // Reject symlinks inside the trusted sandbox; unrestricted processes check all ancestors.
        $path = '';
        foreach (explode('/', $root) as $part) {
            if ($part === '') { continue; }
            if ($part === '.' || $part === '..') { throw new \InvalidArgumentException('Noncanonical storage root.'); }
            $path .= '/' . $part;
            if (($boundary === null || $path === $boundary || str_starts_with($path, $boundary . '/')) && is_link($path)) {
                throw new \InvalidArgumentException('Storage symlinks are not supported.');
            }
        }
        $public = realpath(__DIR__ . '/../../public');
        if ($path === '') { throw new \InvalidArgumentException('Storage cannot be the filesystem root.'); }
        if ($public !== false && ($path === $public || str_starts_with($path, $public . '/'))) {
            throw new \InvalidArgumentException('Storage must be outside the public directory.');
        }
        $this->root = $path;
    }
    /** @param resource $source @return array{id:string,size:int,sha256:string} */
    public function publish($source): array
    {
        if (!is_resource($source) || get_resource_type($source) !== 'stream') { throw new \InvalidArgumentException('A readable stream is required.'); }
        if (!is_dir($this->root) || !is_writable($this->root) || is_link($this->root)) { throw new StorageUnavailable('Private storage is unavailable.'); }
        $id = bin2hex(random_bytes(32)); $temporary = $this->root . '/.stage.' . $id;
        $output = @fopen($temporary, 'xb');
        if ($output === false) { throw new \RuntimeException('Cannot stage content.'); }
        $size = 0; $hash = hash_init('sha256');
        try {
            if (!chmod($temporary, 0600)) { throw new \RuntimeException('Cannot protect content.'); }
            while (!feof($source)) {
                $chunk = fread($source, 65536);
                if ($chunk === false || ($chunk === '' && !feof($source))) { throw new \RuntimeException('Content read failed.'); }
                $size += strlen($chunk);
                if ($size > self::MAX_BYTES) { throw new \LengthException('Upload exceeds 16 MiB.'); }
                hash_update($hash, $chunk);
                $offset = 0;
                while ($offset < strlen($chunk)) {
                    $written = fwrite($output, substr($chunk, $offset));
                    if ($written === false || $written === 0) { throw new \RuntimeException('Content write failed.'); }
                    $offset += $written;
                }
            }
            if (!fflush($output) || !fsync($output)) { throw new \RuntimeException('Content flush failed.'); }
            fclose($output); $output = null;
            if (!rename($temporary, $this->root . '/' . $id)) { throw new \RuntimeException('Content publication failed.'); }
            return ['id' => $id, 'size' => $size, 'sha256' => hash_final($hash)];
        } finally {
            if (is_resource($output)) { fclose($output); }
            if (is_file($temporary)) { unlink($temporary); }
        }
    }
    /** @return resource */
    public function open(string $id, int $size)
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $id)) { throw new \RuntimeException('Invalid object reference.'); }
        $path = $this->root . '/' . $id;
        if (is_link($path) || !is_file($path)) { throw new \RuntimeException('Content is missing.'); }
        $stream = @fopen($path, 'rb');
        if ($stream === false) { throw new \RuntimeException('Cannot read content.'); }
        $stat = fstat($stream);
        if ($stat === false || $stat['size'] !== $size) { fclose($stream); throw new \RuntimeException('Content size mismatch.'); }
        return $stream;
    }
}
