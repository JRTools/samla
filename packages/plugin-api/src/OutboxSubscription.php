<?php
declare(strict_types=1);
namespace Samla\PluginApi;

final readonly class OutboxSubscription
{
    public array $eventTypes;
    public function __construct(public string $id, array $eventTypes, public OutboxHandler $handler, public bool $replayExisting = false)
    {
        if (!preg_match('/^[a-z][a-z0-9_.]{1,95}$/D', $id) || $eventTypes === [] || count($eventTypes) > 16) { throw new \InvalidArgumentException('Invalid subscription.'); }
        $copy = [];
        foreach ($eventTypes as $type) {
            if (!is_string($type) || !preg_match('/^[a-z][a-z0-9_.]{1,159}$/D', $type) || isset($copy[$type])) { throw new \InvalidArgumentException('Invalid event type.'); }
            $copy[(string) $type] = true;
        }
        $this->eventTypes = array_keys($copy);
    }
}
