<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Samla\Core\Storage\{EntryName, LocalContentStore};

final class LocalContentStoreTest extends TestCase
{
    public static function invalidNames(): array
    { return array_map(static fn(string $s): array => [$s], ['', '.', '..', '../a', 'a/b', 'a\\b', "a\0b", "a\nb", ' trailing', 'trailing ', str_repeat('a', 256), "\xff"]); }
    #[DataProvider('invalidNames')]
    public function testInvalidNamesCannotEnterTheLogicalTree(string $name): void
    { $this->expectException(\InvalidArgumentException::class); EntryName::validate($name); }
    public function testContentRootCannotBePublicOrRelativeOrSymlinked(): void
    {
        foreach (['relative', '/', __DIR__ . '/../../public', '/tmp/../tmp'] as $root) {
            try { new LocalContentStore($root); self::fail('Invalid root accepted.'); } catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
        $root = sys_get_temp_dir() . '/samla-link-' . bin2hex(random_bytes(8)); symlink(sys_get_temp_dir(), $root);
        try { $this->expectException(\InvalidArgumentException::class); new LocalContentStore($root); } finally { unlink($root); }
    }
    public function testOversizeUploadLeavesNoStagingOrPublishedObject(): void
    {
        $root = sys_get_temp_dir() . '/samla-large-' . bin2hex(random_bytes(8)); mkdir($root, 0700);
        $stream = fopen('php://temp', 'w+b');
        for ($i = 0; $i < 257; ++$i) { fwrite($stream, str_repeat('x', 65536)); } rewind($stream);
        try {
            try { (new LocalContentStore($root))->publish($stream); self::fail('Oversize accepted.'); } catch (\LengthException) { self::assertSame([], array_values(array_diff(scandir($root), ['.', '..']))); }
        } finally { fclose($stream); rmdir($root); }
    }
    public function testHostingSandboxDoesNotProbeForbiddenAncestorsAndStillRejectsLinks(): void
    {
        $allowed = sys_get_temp_dir() . '/samla-basedir-' . bin2hex(random_bytes(8)); mkdir($allowed, 0700);
        $root = $allowed . '/private'; mkdir($root, 0700); symlink($root, $allowed . '/link');
        $source = file_get_contents(__DIR__ . '/../../src/Storage/LocalContentStore.php');
        // Run the real PHP implementation in a separate process because open_basedir cannot be widened again.
        mkdir($allowed . '/src'); mkdir($allowed . '/src/Storage'); mkdir($allowed . '/public');
        $script = $allowed . '/src/Storage/probe.php';
        // Keep namespace and strict_types in their original position, then execute the probe.
        file_put_contents($script, $source . "\nset_error_handler(static function (\$s, \$m) { throw new \\RuntimeException(\$m); });\n"
            . '$store = new LocalContentStore($argv[1]); echo "ROOT_OK\\n";'
            . 'try { new LocalContentStore($argv[2]); echo "BAD_LINK\\n"; } catch (\\InvalidArgumentException) { echo "LINK_REJECTED\\n"; }'
            . 'try { new LocalContentStore("/outside/storage"); echo "BAD_ROOT\\n"; } catch (\\InvalidArgumentException) { echo "ROOT_REJECTED\\n"; }');
        $pipes = [];
        // PHP_BINARY needs the same loader on extracted PHP 8.5 runtimes; /proc/self/exe resolves that binary.
        $php = PHP_BINARY;
        $command = [$php, '-n', '-d', 'open_basedir=' . $allowed, $script, $root, $allowed . '/link'];
        $loader = dirname($php) . '/../lib/x86_64-linux-gnu/ld-linux-x86-64.so.2';
        if (PHP_VERSION_ID >= 80500 && is_file($loader)) { $command = [$loader, '--library-path', dirname($loader), ...$command]; }
        try {
            $process = proc_open($command, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
            self::assertIsResource($process);
            $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $err . $out);
            self::assertSame("ROOT_OK\nLINK_REJECTED\nROOT_REJECTED\n", $out); self::assertSame('', $err);
        } finally { unlink($script); unlink($allowed . '/link'); rmdir($allowed . '/src/Storage'); rmdir($allowed . '/src'); rmdir($allowed . '/public'); rmdir($root); rmdir($allowed); }
    }
}
