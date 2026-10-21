<?php
declare(strict_types=1);
namespace Samla\Core\WebDav;

/** Request-local authenticated selector. Never contains the credential secret. */
final class RequestIdentity
{
    private ?string $actor = null;
    private ?string $credential = null;
    public array $lockTokens = [];
    public function bind(string $actor, string $credential): void
    {
        if ($this->actor !== null) { throw new \LogicException('Identity already bound.'); }
        $this->actor = $actor; $this->credential = $credential;
    }
    public function actor(): string { return $this->actor ?? throw new \LogicException('Unauthenticated DAV request.'); }
    public function credential(): ?string { return $this->credential; }
}
