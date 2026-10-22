<?php
declare(strict_types=1);

namespace Samla\PluginApi;

/** Exact, bounded mapping edge. Opaque issuer/group bytes are for validation, not audit payloads. */
final readonly class GroupMappingChange
{
    public function __construct(public string $issuer, public string $externalId, public string $groupId, public bool $grant, public ?bool $expectedPresent = null)
    {
        if ($issuer === '' || strlen($issuer) > 1024 || $externalId === '' || strlen($externalId) > 256
            || !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $groupId)) {
            throw new \InvalidArgumentException('Invalid mapping identifiers.');
        }
    }
    public function mappingId(): string
    {
        // Length framing prevents ambiguous concatenations and works for arbitrary opaque bytes.
        return hash('sha256', pack('N', strlen($this->issuer)) . $this->issuer
            . pack('N', strlen($this->externalId)) . $this->externalId
            . pack('N', strlen($this->groupId)) . $this->groupId);
    }
}
