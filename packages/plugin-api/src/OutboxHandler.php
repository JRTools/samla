<?php
declare(strict_types=1);
namespace Samla\PluginApi;

interface OutboxHandler
{
    /** At-least-once delivery. Deduplicate effects using event.id and the subscription ID. */
    public function handle(OutboxEvent $event): void;
}
