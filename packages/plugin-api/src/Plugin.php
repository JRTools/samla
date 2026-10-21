<?php
declare(strict_types=1);

namespace Samla\PluginApi;

interface Plugin
{
    public function id(): string;
    /** Exact supported API major; pre-1.0 API remains experimental. */
    public function apiMajor(): int;
    public function register(PluginRegistrar $registrar): void;
}
