<?php
declare(strict_types=1);

namespace Samla\Tests\Unit;

use Samla\Core\Configuration\{AppConfig, DatabaseConfig};
use Samla\Core\Database\ServerVersion;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    private function env(): array
    {
        return ['DB_ENGINE' => 'mysql', 'DB_NAME' => 'platform', 'DB_USER' => 'app', 'DB_PASSWORD' => 'secret',
            'TEST_DB_ENGINE' => 'mysql', 'TEST_DB_NAME' => 'media_platform_test_unit', 'TEST_DB_USER' => 'test', 'TEST_DB_PASSWORD' => 'test-secret'];
    }
    public function testProductionIsDefaultAndNoFreshnessPolicyIsInvented(): void
    {
        $config = AppConfig::fromEnvironment($this->env());
        self::assertSame('production', $config->environment);
        self::assertNull($config->groupMaxAgeSeconds);
        self::assertFalse($config->maintenance);
    }
    public function testTestModeUsesOnlyExplicitTestCredentials(): void
    {
        $config = AppConfig::fromEnvironment(['APP_ENV' => 'test'] + $this->env());
        self::assertSame('test', $config->database->user);
        self::assertSame('test-secret', $config->database->password());
        self::assertStringContainsString('dbname=media_platform_test_unit', $config->database->dsn());
    }
    public function testTestModeCannotFallBackToProduction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AppConfig::fromEnvironment(['APP_ENV' => 'test', 'DB_ENGINE' => 'mysql', 'DB_NAME' => 'platform', 'DB_USER' => 'app', 'DB_PASSWORD' => 'secret']);
    }
    public function testUnsafeTestNamesAndSameProductionNameAreRejected(): void
    {
        foreach (['platform', 'media_platform_test_unit'] as $name) {
            $env = ['APP_ENV' => 'test', 'TEST_DB_NAME' => $name, 'DB_NAME' => $name] + $this->env();
            try { AppConfig::fromEnvironment($env); self::fail('Unsafe database accepted'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
    public function testInvalidEnvironmentPortAndPolicyAreRejected(): void
    {
        foreach ([['APP_ENV' => 'unknown'], ['DB_PORT' => '3306;unix_socket=/tmp/a'], ['DB_PORT' => '0'], ['APP_GROUP_MAX_AGE_SECONDS' => '0'], ['APP_MAINTENANCE' => 'true']] as $override) {
            try { AppConfig::fromEnvironment($override + $this->env()); self::fail('Invalid config accepted'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
    public function testDsnInjectionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DatabaseConfig('mysql', 'localhost;dbname=production', 3306, 'test', 'app', 'secret');
    }
    public function testDebugOutputRedactsSecrets(): void
    {
        $database = AppConfig::fromEnvironment($this->env())->database;
        ob_start(); var_dump($database); $dump = ob_get_clean();
        self::assertStringNotContainsString('secret', $dump);
        self::assertStringContainsString('[redacted]', $dump);
    }
    public function testSupportedVersionFamilies(): void
    {
        ServerVersion::assertSupported('mysql', '8.0.46-0ubuntu0.24.04.3');
        ServerVersion::assertSupported('mariadb', '11.8.6-MariaDB');
        ServerVersion::assertSupported('mariadb', '5.5.5-11.8.6-MariaDB');
        self::assertTrue(true);
    }
    public function testOtherVersionFamiliesAndEngineMismatchAreRejected(): void
    {
        foreach ([['mysql', '8.4.1'], ['mariadb', '10.11.8-MariaDB'], ['mysql', '11.8.6-MariaDB'], ['mariadb', '11.8.6']] as [$engine, $version]) {
            try { ServerVersion::assertSupported($engine, $version); self::fail('Unsupported server accepted'); }
            catch (\RuntimeException) { self::assertTrue(true); }
        }
    }
}
