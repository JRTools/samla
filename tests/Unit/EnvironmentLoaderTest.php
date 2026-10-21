<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Samla\Core\Configuration\{AppConfig, EnvironmentLoader, OidcConfig};

final class EnvironmentLoaderTest extends TestCase
{
    private string $file;
    protected function setUp(): void { $this->file = tempnam(sys_get_temp_dir(), 'samla-env-'); }
    protected function tearDown(): void { if (is_file($this->file)) { unlink($this->file); } }
    private function load(string $text, array $process = []): array
    {
        file_put_contents($this->file, $text);
        return EnvironmentLoader::load($this->file, $process);
    }
    public function testMissingFileKeepsProcessConfiguration(): void
    {
        unlink($this->file);
        self::assertSame(['APP_ENV' => 'production'], EnvironmentLoader::load($this->file, ['APP_ENV' => 'production']));
    }
    public function testAssignmentsCommentsBomAndCrLf(): void
    {
        self::assertSame(['APP_ENV' => 'development', 'EMPTY' => '', 'COMMENT' => '', 'PORT' => '3306', 'LITERAL' => 'x#y'],
            $this->load("\xef\xbb\xbf# example\r\n  APP_ENV = development  # comment\r\nEMPTY=\r\nCOMMENT=# nothing\r\nexport PORT=3306\r\nLITERAL=x#y\r\n"));
    }
    public function testQuotedSecretsAndDoubleQuoteEscapesAreLiteral(): void
    {
        $text = <<<'ENV'
SINGLE='literal # value $HOME ${OTHER} C:\folder' # comment
DOUBLE="line\nnext\t\"quoted\"\\end\$literal"
EMPTY_SINGLE=''
EMPTY_DOUBLE=""
ENV;
        self::assertSame(['SINGLE' => 'literal # value $HOME ${OTHER} C:\folder',
            'DOUBLE' => "line\nnext\t\"quoted\"\\end\$literal", 'EMPTY_SINGLE' => '', 'EMPTY_DOUBLE' => ''], $this->load($text));
    }
    public function testNoShellPhpOrVariableExpansion(): void
    {
        $result = $this->load('REFERENCE=${OTHER}' . "\n" . 'COMMAND=$(id)' . "\n" . 'BACKTICK=`id`' . "\n" . "PHP='<?php echo 1; ?>'", ['OTHER' => 'not-expanded']);
        self::assertSame('${OTHER}', $result['REFERENCE']);
        self::assertSame('$(id)', $result['COMMAND']);
        self::assertSame('`id`', $result['BACKTICK']);
        self::assertSame('<?php echo 1; ?>', $result['PHP']);
    }
    public function testProcessValuesIncludingEmptyTakePrecedenceWithoutMutatingGlobals(): void
    {
        $before = getenv('SAMLA_ENV_TEST_SECRET');
        self::assertSame(['SAMLA_ENV_TEST_SECRET' => '', 'APP_ENV' => 'production', 'FILE_ONLY' => 'yes'],
            $this->load("SAMLA_ENV_TEST_SECRET=synthetic-secret\nAPP_ENV=development\nFILE_ONLY=yes", ['SAMLA_ENV_TEST_SECRET' => '', 'APP_ENV' => 'production']));
        self::assertSame($before, getenv('SAMLA_ENV_TEST_SECRET'));
    }
    public function testMalformedFilesAreRejectedWithoutLeakingValues(): void
    {
        foreach (["INVALID LINE synthetic-secret", "9KEY=synthetic-secret", "KEY=one\nKEY=synthetic-secret", "KEY='synthetic-secret", 'KEY="synthetic-secret',
            'KEY="synthetic-secret" trailing', 'KEY="synthetic-secret\q"', "KEY=unquoted synthetic-secret", "KEY=synthetic\0secret",
            "KEY=synthetic\x01secret", "<?php synthetic-secret ?>", "KEY='one\ntwo'"] as $text) {
            try { $this->load($text); self::fail('Malformed environment accepted'); }
            catch (\InvalidArgumentException $error) { self::assertStringNotContainsString('synthetic-secret', $error->getMessage()); }
        }
    }
    public function testDuplicateIsRejectedEvenWhenProcessOverridesIt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->load("APP_ENV=development\nAPP_ENV=production", ['APP_ENV' => 'production']);
    }
    public function testSizeBoundAndNonRegularFile(): void
    {
        self::assertSame(65534, strlen($this->load('K=' . str_repeat('x', 65534))['K']));
        try { $this->load('K=' . str_repeat('x', 65535)); self::fail('Oversize file accepted'); }
        catch (\InvalidArgumentException) { self::assertTrue(true); }
        $this->expectException(\RuntimeException::class);
        EnvironmentLoader::load(sys_get_temp_dir(), []);
    }
    public function testRepeatedLoadsDoNotKeepPreviousSecretsOrUseCurrentDirectory(): void
    {
        self::assertSame('first', $this->load('KEY=first')['KEY']);
        $cwd = getcwd();
        try {
            chdir(sys_get_temp_dir());
            self::assertSame('second', $this->load('KEY=second')['KEY']);
        } finally { chdir($cwd); }
    }
    public function testAutomatedTestGlobalsRemainProcessOnly(): void
    {
        $previous = getenv('APP_ENV');
        try {
            putenv('APP_ENV=test');
            self::assertSame(getenv(), EnvironmentLoader::fromGlobals());
        } finally { $previous === false ? putenv('APP_ENV') : putenv('APP_ENV=' . $previous); }
    }
    public function testSameFileValuesConfigureDatabaseOidcAndDevelopmentAccess(): void
    {
        $env = $this->load("APP_ENV=development\nDB_ENGINE=mariadb\nDB_NAME=samla_dev_local\nDB_USER=samla\nDB_PASSWORD='synthetic # db'\nAPP_GROUP_MAX_AGE_SECONDS=3600\nOIDC_ENABLED=1\nOIDC_ISSUER=https://identity.example\nOIDC_CLIENT_ID=client\nOIDC_CLIENT_SECRET='synthetic+oidc'\nOIDC_REDIRECT_URI=http://127.0.0.1:8080/auth/callback\nAPP_WEB_DEV_ACCESS=1\nAPP_DEV_ACTOR_ID=dev.admin");
        self::assertSame('synthetic # db', AppConfig::fromEnvironment($env)->database->password());
        self::assertSame('synthetic+oidc', OidcConfig::fromEnvironment($env)->clientSecret());
        self::assertSame('dev.admin', \Samla\Core\Web\DevelopmentAccess::actor($env,
            ['SERVER_NAME' => '127.0.0.1', 'HTTP_HOST' => '127.0.0.1:8080', 'REMOTE_ADDR' => '127.0.0.1'], 'cli-server'));
    }
    public function testExampleIsParsableAndDoesNotEnableDevelopmentOrOidcAccess(): void
    {
        $env = EnvironmentLoader::load(dirname(__DIR__, 2) . '/example.env', []);
        self::assertSame('production', $env['APP_ENV']);
        self::assertSame('0', $env['APP_WEB_DEV_ACCESS']);
        self::assertNull(OidcConfig::fromEnvironment($env));
        self::assertNull(AppConfig::fromEnvironment($env)->groupMaxAgeSeconds);
    }
}
