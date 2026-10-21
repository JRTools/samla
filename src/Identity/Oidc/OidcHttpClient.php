<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Exact trusted endpoint/method allowlist; never follows remote redirects. */
final readonly class OidcHttpClient implements ClientInterface
{
    public function __construct(private array $endpoints, private HttpClientInterface $transport = new NativeHttpClient()) {}
    public function sendRequest(#[\SensitiveParameter] RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri(); $method = $request->getMethod();
        if (!in_array($method, $this->endpoints[$url] ?? [], true)) { throw new OidcTransportError('OIDC endpoint is not allowed.'); }
        try {
            $response = $this->transport->request($method, $url, [
                'headers' => $request->getHeaders(), 'body' => (string) $request->getBody(),
                'timeout' => 10, 'max_duration' => 15, 'max_redirects' => 0, 'verify_peer' => true, 'verify_host' => true, 'buffer' => false,
                'on_progress' => static function (int $downloaded, int $size): void {
                    if ($downloaded > 262144 || $size > 262144) { throw new OidcTransportError('OIDC response is too large.'); }
                },
            ]);
            $status = $response->getStatusCode();
            if ($status !== 200 && !($method === 'POST' && $status === 400)) { throw new OidcTransportError('OIDC endpoint failed.'); }
            $body = '';
            foreach ($this->transport->stream($response) as $chunk) {
                if ($chunk->isTimeout()) { throw new OidcTransportError('OIDC response timed out.'); }
                $part = $chunk->getContent();
                if (strlen($part) > 262144 - strlen($body)) { throw new OidcTransportError('OIDC response is too large.'); }
                $body .= $part;
            }
            return new Response($status, ['Content-Type' => 'application/json'], $body);
        } catch (\Throwable) {
            if (isset($response)) { $response->cancel(); }
            throw new OidcTransportError('OIDC request failed.');
        }
    }
}
