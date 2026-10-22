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
                $base = ['actor_id' => $context->actorId, 'operation' => $context->operation,
                    'scope' => $context->scope->value, 'space_id' => $context->spaceId, 'targets' => $context->targets];
                $chunks = $context->storagePurge === null ? [[]] : array_chunk(array_keys($context->storagePurge->entryRevisions), 500);
                $event = $this->pdo->prepare('INSERT INTO outbox_events (id, event_type, payload, occurred_at) VALUES (?, ?, ?, ?)');
                foreach ($chunks as $index => $entries) {
                    $id = $index === 0 ? $context->id : substr(hash('sha256', $context->id . ':' . $index), 0, 32);
                    $data = ['id' => $id, ...$base];
                    if ($context->storagePurge !== null) { $data += ['batch_id' => $context->id, 'chunk_index' => $index, 'chunk_count' => count($chunks), 'purged_entries' => $entries]; }
                    $payload = json_encode($data, JSON_THROW_ON_ERROR);
                    $event->execute([$id, $context->operation . '.completed', $payload, $time]);
                }
                if($context->catalogBatch!==null){
                    foreach($context->catalogBatch->changedEntries()as$entry){
                        $id=substr(hash('sha256',$context->id.':catalog:'.$entry),0,32);$operation='catalog.entry.tags.'.$context->catalogBatch->operation;
                        $entryTargets=['entry'=>$entry,'tag'=>$context->catalogBatch->tagId,'batch'=>$context->catalogBatch->signature()];
                        $audit->execute([$id,$context->actorId,$operation,$context->scope->value,$context->spaceId,json_encode($entryTargets,JSON_THROW_ON_ERROR),1,$time]);
                        $payload=json_encode(['id'=>$id,'actor_id'=>$context->actorId,'operation'=>$operation,'scope'=>$context->scope->value,
                            'space_id'=>$context->spaceId,'targets'=>$entryTargets,'batch_id'=>$context->id],JSON_THROW_ON_ERROR);
                        $event->execute([$id,$operation.'.completed',$payload,$time]);
                    }
                }
                if($context->mediaBatchRefresh!==null){
                    $batchIndex=0;
                    foreach($context->mediaBatchRefresh->entryRevisions as$entry=>$revision){
                        $id=substr(hash('sha256',$context->id.':'.$entry),0,32);
                        $payload=json_encode(['id'=>$id,'actor_id'=>$context->actorId,'operation'=>'media.asset.refresh','scope'=>$context->scope->value,
                            'space_id'=>$context->spaceId,'targets'=>['entry'=>$entry],'batch_id'=>$context->id,'batch_index'=>$batchIndex++,'batch_count'=>count($context->mediaBatchRefresh->entryRevisions),'requested_revision'=>$revision],JSON_THROW_ON_ERROR);
                        $audit->execute([$id,$context->actorId,'media.asset.refresh',$context->scope->value,$context->spaceId,json_encode(['entry'=>$entry,'batch'=>$context->id],JSON_THROW_ON_ERROR),1,$time]);
                        $event->execute([$id,'media.asset.refresh.completed',$payload,$time]);
                    }
                }
            }
            return $changed;
        });
    }
}
