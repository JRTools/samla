<?php
declare(strict_types=1);

namespace Samla\Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DependencyTest extends TestCase
{
    public function testCoreNeverImportsRbacInternalsOrQueriesItsTables(): void
    {
        $root = __DIR__ . '/../../src';
        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() !== 'php') { continue; }
            $source = file_get_contents($file->getPathname());
            self::assertStringNotContainsString('Samla\\Plugins\\Rbac', $source);
            self::assertDoesNotMatchRegularExpression('/\b(?:FROM|JOIN|UPDATE|INTO)\s+(?:rbac_[a-z_]+|roles|role_permissions|role_assignments|delegation_rules)\b/i', $source);
            $count++;
        }
        self::assertGreaterThan(0, $count);
    }
    public function testReferencePluginUsesOnlyPublicPlatformApi(): void
    {
        $source = file_get_contents(__DIR__ . '/../../examples/reference-plugin/ReferencePlugin.php');
        self::assertStringNotContainsString('Samla\\Core', $source);
        self::assertStringNotContainsString('Samla\\Plugins', $source);
    }
    public function testRbacNeverImportsInternalCoreClasses(): void
    {
        $files = glob(__DIR__ . '/../../plugins/rbac/src/*.php');
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            self::assertStringNotContainsString('Samla\\Core\\', file_get_contents($file));
        }
    }
}
