<?php
declare(strict_types=1);
namespace Samla\Core\Web;

/** Machine endpoint; no browser session and no secret accepted in the URL. */
final readonly class WorkerController
{
    public function __construct(private string $tokenHash, private \Closure $factory)
    {
        if ($tokenHash !== '' && !preg_match('/^[a-f0-9]{64}$/D',$tokenHash)) { throw new \InvalidArgumentException('Invalid worker token hash.'); }
    }
    public function handle(Request $request,array $server): Response
    {
        if ($this->tokenHash === '') { return new Response(404); }
        if ($request->method !== 'POST') { return new Response(405,headers:['Allow'=>'POST']); }
        if ($request->query !== []) { return new Response(400); }
        $authorization=$server['HTTP_AUTHORIZATION']??null;
        if ($authorization !== null) {
            $token=is_string($authorization) && preg_match('/^Bearer ([a-f0-9]{64})$/D',$authorization,$matches)?$matches[1]:'';
        } else { $token=$request->post['token']??''; }
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token) || !hash_equals($this->tokenHash,hash('sha256',$token))) { return new Response(401,headers:['WWW-Authenticate'=>'Bearer']); }
        $worker=($this->factory)();
        $result=$worker->run();
        return new Response($result['busy']?409:200,json_encode($result+['queue'=>$worker->status()],JSON_THROW_ON_ERROR),['Content-Type'=>'application/json; charset=utf-8']);
    }
}
