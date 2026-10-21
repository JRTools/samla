<?php
declare(strict_types=1);

namespace Samla\Tests\Fixtures;
use Samla\PluginApi\{Plugin, PluginRegistrar};
final readonly class Extension implements Plugin
{
    public function __construct(private string $name, private \Closure $setup, private int $major = 0) {}
    public function id(): string { return $this->name; }
    public function apiMajor(): int { return $this->major; }
    public function register(PluginRegistrar $registrar): void { ($this->setup)($registrar); }
}
