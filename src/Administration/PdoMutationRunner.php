<?php
declare(strict_types=1);

namespace Samla\Core\Administration;

use Samla\Core\Database\Transaction;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{MutationContext, MutationRunner};

final readonly class PdoMutationRunner implements MutationRunner
{
    public function __construct(private \PDO $pdo, private PluginRuntime $plugins) {}
    public function run(MutationContext $context, \Closure $guard, \Closure $write): bool
    {
        return Transaction::run($this->pdo, function () use ($context, $guard, $write): bool {
            $lock = $this->pdo->query('SELECT id FROM administration_lock WHERE id = 1 FOR UPDATE');
            if ($lock->fetchColumn() === false) { throw new \RuntimeException('Administration lock is missing.'); }
            $guard();
            foreach ($this->plugins->validators() as $validator) { $validator->validate($context); }
            $guard();
            $changed = $write();
            if (!is_bool($changed)) { throw new \LogicException('Mutation must return whether state changed.'); }
            $time = time();
            $targets = json_encode($context->targets, JSON_THROW_ON_ERROR);
            $audit = $this->pdo->prepare('INSERT INTO audit_events (id, actor_id, operation, scope, space_id, targets, changed, occurred_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $audit->execute([$context->id, $context->actorId, $context->operation, $context->scope->value, $context->spaceId, $targets, $changed ? 1 : 0, $time]);
            if ($changed) {
                $payload = json_encode(['id' => $context->id, 'actor_id' => $context->actorId, 'operation' => $context->operation,
                    'scope' => $context->scope->value, 'space_id' => $context->spaceId, 'targets' => $context->targets], JSON_THROW_ON_ERROR);
                $event = $this->pdo->prepare('INSERT INTO outbox_events (id, event_type, payload, occurred_at) VALUES (?, ?, ?, ?)');
                $event->execute([$context->id, $context->operation . '.completed', $payload, $time]);
            }
            return $changed;
        });
    }
}
