<?php
declare(strict_types=1);

namespace Samla\PluginApi;

final readonly class AuthorizationResult
{
    public function __construct(public Decision $decision, public string $reason) {}
    public static function deny(string $reason): self { return new self(Decision::Deny, $reason); }
}
