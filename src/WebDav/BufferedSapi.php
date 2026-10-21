<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;
use Sabre\HTTP\{Request, ResponseInterface, Sapi};

/** Sabre preconditions must not send headers or bodies outside Samla's response path. */
final class BufferedSapi extends Sapi
{
    public static function getRequest(): Request { return new Request('GET', '/'); }
    public static function sendResponse(ResponseInterface $response) {}
}
