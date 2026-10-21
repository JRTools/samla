<?php
declare(strict_types=1);
namespace Samla\Core\Web;

final readonly class Response
{
    public array $headers;
    public function __construct(public int $status, public string $body = '', array $headers = [], public ?string $flash = null, public ?\Closure $stream = null)
    {
        $this->headers = $headers + [
            'Content-Type' => 'text/html; charset=utf-8', 'Cache-Control' => 'no-store',
            'Content-Security-Policy' => "default-src 'none'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer', 'X-Frame-Options' => 'DENY',
        ];
    }
}
