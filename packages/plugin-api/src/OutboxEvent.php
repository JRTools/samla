<?php
declare(strict_types=1);
namespace Samla\PluginApi;

/** A committed event, never an authorization grant for a later user operation. */
final readonly class OutboxEvent
{
    public array $payload;
    public function __construct(public string $id, public string $type, public int $occurredAt, array $payload)
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !preg_match('/^[a-z][a-z0-9_.]{1,159}$/D', $type) || $occurredAt < 0) { throw new \InvalidArgumentException('Invalid event.'); }
        // Break caller-owned references and retain only bounded JSON values.
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($json) > 65535) { throw new \LengthException('Event payload limit exceeded.'); }
        $this->payload = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
    }
}
