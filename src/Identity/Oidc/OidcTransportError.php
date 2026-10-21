<?php
declare(strict_types=1);
namespace Samla\Core\Identity\Oidc;

final class OidcTransportError extends \RuntimeException implements \Psr\Http\Client\ClientExceptionInterface {}
