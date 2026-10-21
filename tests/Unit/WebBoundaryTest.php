<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Samla\Core\Web\{DevelopmentAccess, PageRenderer, Request, Response};

final class WebBoundaryTest extends TestCase
{
    private function env(): array
    { return ['APP_ENV' => 'development', 'APP_WEB_DEV_ACCESS' => '1', 'APP_DEV_ACTOR_ID' => 'dev.admin', 'DB_NAME' => 'samla_dev_local']; }
    private function server(): array
    { return ['REMOTE_ADDR' => '127.0.0.1', 'SERVER_NAME' => '127.0.0.1', 'HTTP_HOST' => '127.0.0.1:8080']; }
    public function testExplicitLocalDevelopmentConfigurationSelectsOnlyTheServerActor(): void
    {
        self::assertSame('dev.admin', DevelopmentAccess::actor($this->env(), $this->server() + ['HTTP_X_ACTOR_ID' => 'other'], 'cli-server'));
    }
    public function testDevelopmentAdapterRejectsProductionTestOrMissingOptIn(): void
    {
        foreach ([['APP_ENV' => 'production'], ['APP_ENV' => 'test'], ['APP_WEB_DEV_ACCESS' => '0'], ['APP_DEV_ACTOR_ID' => ''], ['DB_NAME' => 'production']] as $override) {
            self::assertNull(DevelopmentAccess::actor(array_replace($this->env(), $override), $this->server(), 'cli-server'));
        }
        self::assertNull(DevelopmentAccess::actor([], $this->server(), 'cli-server'));
    }
    public function testRemoteClientsBroadBindingsWrongHostAndForwardedHeadersCannotEnableAccess(): void
    {
        foreach ([['REMOTE_ADDR' => '192.0.2.1'], ['SERVER_NAME' => '0.0.0.0'], ['HTTP_HOST' => 'evil.example'], ['HTTP_HOST' => '127.0.0.1.evil.example'], ['HTTP_HOST' => '127.0.0.1:80@evil.example']] as $override) {
            self::assertNull(DevelopmentAccess::actor($this->env(), array_replace($this->server(), $override) + ['HTTP_X_FORWARDED_FOR' => '127.0.0.1'], 'cli-server'));
        }
        self::assertNull(DevelopmentAccess::actor($this->env(), $this->server(), 'fpm-fcgi'));
    }
    public function testArrayAndOversizedFormFieldsAreRejected(): void
    {
        foreach ([['csrf' => ['x']], ['csrf' => str_repeat('a', 65)]] as $post) {
            try { (new Request('POST', '/', post: $post))->field('csrf', 64); self::fail('Expected invalid form input.'); }
            catch (\InvalidArgumentException) { self::assertTrue(true); }
        }
    }
    public function testUnexpectedCursorAndTemplatePathsAreRejected(): void
    {
        try { (new Request('GET', '/', ['after' => ['x']]))->after(); self::fail('Expected invalid cursor.'); }
        catch (\InvalidArgumentException) { self::assertTrue(true); }
        $this->expectException(\InvalidArgumentException::class);
        (new PageRenderer())->render('../../config/environment.example', []);
    }
    public function testHtmlEscapingAndSecurityHeaders(): void
    {
        self::assertSame('&lt;img src=x onerror=&quot;x&quot;&gt;', PageRenderer::escape('<img src=x onerror="x">'));
        $response = new Response(200, 'test');
        self::assertSame('no-store', $response->headers['Cache-Control']);
        self::assertStringContainsString("frame-ancestors 'none'", $response->headers['Content-Security-Policy']);
        self::assertSame('nosniff', $response->headers['X-Content-Type-Options']);
    }
}
