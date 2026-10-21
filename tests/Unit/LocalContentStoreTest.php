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
}
