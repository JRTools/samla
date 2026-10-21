<?php
declare(strict_types=1);

namespace Samla\PluginApi;

final class AccessDenied extends \RuntimeException
{
    public function __construct() { parent::__construct('Not authorized.'); }
}
