<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sabre\HTTP;
use Samla\Core\WebDav\{BufferedSapi, Kernel, ProtocolPaths, RequestIdentity};

final class WebDavTest extends TestCase
{
    public static function badPaths(): array
    { return array_map(static fn(string $path): array => [$path], ['/other/', '/dav//x','/dav/../x','/dav/%2e%2e/x','/dav/a%2fb','/dav/a%5cb','/dav/a%00','/dav/%ff','/dav/%xy','/dav/a?q=1','/dav/a#x']); }
    #[DataProvider('badPaths')]
    public function testRejectsAmbiguousPaths(string $path): void
    { $this->expectException(\InvalidArgumentException::class); Kernel::validatePath($path); }
    public function testNamesAreDecodedExactlyOnce(): void
    { Kernel::validatePath('/dav/space/%252f%20Gr%C3%BC%C3%9Fe.txt'); self::assertTrue(true); }
    public function testResourceResponseCopiesOnlyDeclaredRangeAndClosesInput(): void
    {
        $stream = fopen('php://temp','w+b'); fwrite($stream,'0123456789'); fseek($stream,3);
        $response = Kernel::adapt(new HTTP\Response(206,['Content-Length'=>'2'],$stream));
        ob_start(); ($response->stream)(); $actual = ob_get_clean();
        self::assertSame('34',$actual); self::assertFalse(is_resource($stream)); self::assertSame(206,$response->status);
    }
    public function testHeadClosesBodyWithoutSendingIt(): void
    {
        $stream=fopen('php://temp','w+b'); fwrite($stream,'body');
        $response=Kernel::adapt(new HTTP\Response(200,['Content-Length'=>'4'],$stream),true);
        self::assertSame('',$response->body); self::assertNull($response->stream); self::assertFalse(is_resource($stream));
    }
    public function testCallableBodyIsNotBufferedByAdapter(): void
    {
        $called=false; $response=Kernel::adapt(new HTTP\Response(206,[],static function () use (&$called): void {$called=true;echo 'part';}));
        self::assertFalse($called); ob_start(); ($response->stream)(); $body=ob_get_clean();
        self::assertTrue($called); self::assertSame('part',$body);
    }
    public function testPreconditionsCannotSendTheirOwnResponse(): void
    {
        ob_start(); BufferedSapi::sendResponse(new HTTP\Response(304,[],'hidden')); $body=ob_get_clean(); self::assertSame('',$body);
    }
    public function testRequestIdentityCannotBeRebound(): void
    { $identity=new RequestIdentity(); $identity->bind('alice',str_repeat('a',32)); $this->expectException(\LogicException::class); $identity->bind('bob',str_repeat('b',32)); }
    public function testDestinationAcceptsOnlyCanonicalOriginAndDavPaths():void
    {
        self::assertSame('/dav/a/x',ProtocolPaths::destination('https://samla.example/dav/a/x','https://samla.example'));
        self::assertSame('/dav/a/%252f',ProtocolPaths::destination('/dav/a/%252f','https://samla.example'));
        $this->expectException(\InvalidArgumentException::class);ProtocolPaths::destination('https://samla.example:444/dav/a/x','https://samla.example');
    }
    public function testOnlyPositiveFullyParsedIfTokensBecomeCoreProof():void
    {
        $server=new \Sabre\DAV\Server(new \Sabre\DAV\SimpleCollection(''),new BufferedSapi());$server->setBaseUri('/dav/');
        $token=str_repeat('a',32);$request=new HTTP\Request('PUT','/dav/a/x',['If'=>'(Not <opaquelocktoken:'.$token.'>)']);$request->setBaseUrl('/dav/');
        self::assertSame([],ProtocolPaths::proofs($request,$server,'https://samla.example'));
        $request->setHeader('If','<https://samla.example/dav/a/x> (<opaquelocktoken:'.$token.'> ["v1"])');
        self::assertSame([$token],ProtocolPaths::proofs($request,$server,'https://samla.example'));
        $request->setHeader('If','(<opaquelocktoken:'.$token.'>) ignored');
        $this->expectException(\InvalidArgumentException::class);ProtocolPaths::proofs($request,$server,'https://samla.example');
    }
}
