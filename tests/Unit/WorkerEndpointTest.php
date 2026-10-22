<?php
declare(strict_types=1);
namespace Samla\Tests\Unit;
use PHPUnit\Framework\TestCase;
use Samla\Core\Web\{Request,WorkerController};

final class WorkerEndpointTest extends TestCase
{
    private function controller(string $hash, int &$calls): WorkerController
    {return new WorkerController($hash,static function()use(&$calls):object{++$calls;return new class{
        public function run():array{return ['busy'=>false,'processed'=>1,'delivered'=>1,'errors'=>0];}
        public function status():array{return ['pending'=>0];}
    };});}
    public function testDisabledMethodUrlAndInvalidCredentialsNeverConstructWorker(): void
    {
        $calls=0;$token=str_repeat('b',64);$controller=$this->controller(hash('sha256',$token),$calls);
        self::assertSame(404,$this->controller('',$calls)->handle(new Request('POST','/tasks/run'),[])->status);
        self::assertSame(405,$controller->handle(new Request('GET','/tasks/run'),[])->status);
        self::assertSame(400,$controller->handle(new Request('POST','/tasks/run',query:['token'=>$token]),[])->status);
        foreach([[],['token'=>[]],['token'=>'wrong']] as $post){self::assertSame(401,$controller->handle(new Request('POST','/tasks/run',post:$post),[])->status);}
        self::assertSame(401,$controller->handle(new Request('POST','/tasks/run',post:['token'=>$token]),['HTTP_AUTHORIZATION'=>'Bearer wrong'])->status);
        self::assertSame(0,$calls);
    }
    public function testBearerAndPostBodyBothWorkWithoutCookiesAndResponsesContainNoSecret(): void
    {
        $calls=0;$token=str_repeat('b',64);$controller=$this->controller(hash('sha256',$token),$calls);
        foreach([[[],['HTTP_AUTHORIZATION'=>'Bearer '.$token]],[['token'=>$token],[]]] as [$post,$headers]){
            $response=$controller->handle(new Request('POST','/tasks/run',post:$post),$headers);
            self::assertSame(200,$response->status);self::assertSame(1,json_decode($response->body,true)['delivered']);self::assertStringNotContainsString($token,$response->body);self::assertSame('no-store',$response->headers['Cache-Control']);
        }
        self::assertSame(2,$calls);
    }
    public function testMalformedConfiguredHashIsRejected(): void
    {$this->expectException(\InvalidArgumentException::class);$calls=0;$this->controller('plaintext-token',$calls);}
}
