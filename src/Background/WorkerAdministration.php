<?php
declare(strict_types=1);
namespace Samla\Core\Background;
use Samla\Core\Plugins\PluginRuntime;
use Samla\PluginApi\{AccessDenied,AuthorizationChecker,AuthorizationRequest,Decision,MutationConflict,MutationContext,MutationRunner,OutboxRetryChange,Scope};

final readonly class WorkerAdministration
{
    public function __construct(private \PDO $pdo,private AuthorizationChecker $authorization,private MutationRunner $mutations,private PluginRuntime $runtime){}
    private function authorize(string $actor,string $action):void
    {if($this->authorization->decide(new AuthorizationRequest($actor,$action,Scope::Global))->decision!==Decision::Permit){throw new AccessDenied();}}
    public function listing(string $actor,?string $after=null,string $status='all'):array
    {
        $this->authorize($actor,'operations.worker.read');
        if(!in_array($status,['all','pending','processing','delivered','failed'],true)){throw new \InvalidArgumentException('Invalid delivery status.');}
        $subscriber='';$event='';
        if($after!==null){$parts=explode(':',$after);if(count($parts)!==2){throw new \InvalidArgumentException();}[$subscriber,$event]=$parts;new OutboxRetryChange($subscriber,$event,1);}
        $where=$status==='all'?'':' AND d.status=?';
        $q=$this->pdo->prepare('SELECT d.subscriber_id,d.event_id,d.status,d.attempts,d.available_at,d.lease_until,d.error_class,d.revision,e.event_type,e.occurred_at FROM outbox_deliveries d JOIN outbox_events e ON e.id=d.event_id WHERE (d.subscriber_id>? OR (d.subscriber_id=? AND d.event_id>?))'.$where.' ORDER BY d.subscriber_id,d.event_id LIMIT 101');
        $args=[$subscriber,$subscriber,$event];if($status!=='all'){$args[]=$status;}$q->execute($args);$rows=$q->fetchAll();$next=null;
        if(count($rows)>100){array_pop($rows);$last=$rows[99];$next=$last['subscriber_id'].':'.$last['event_id'];}
        $active=$this->runtime->subscribers();foreach($rows as&$row){$row['active']=isset($active[$row['subscriber_id']]);}unset($row);
        $counts=['pending'=>0,'processing'=>0,'delivered'=>0,'failed'=>0];
        foreach($this->pdo->query('SELECT status,COUNT(*) AS total FROM outbox_deliveries GROUP BY status')as$row){$counts[$row['status']]=(int)$row['total'];}
        return ['deliveries'=>$rows,'next'=>$next,'filter'=>$status,'queue_counts'=>$counts,'can_retry'=>$this->authorization->decide(new AuthorizationRequest($actor,'operations.worker.retry',Scope::Global))->decision===Decision::Permit];
    }
    public function retry(string $actor,string $subscriber,string $event,int $revision):bool
    {
        $proposal=new OutboxRetryChange($subscriber,$event,$revision);
        $context=new MutationContext(bin2hex(random_bytes(16)),$actor,'operations.delivery.retry',Scope::Global,null,
            ['subscriber'=>hash('sha256',$subscriber),'event'=>$event],outboxRetry:$proposal);
        return $this->mutations->run($context,function()use($actor,$proposal):void{
            $this->authorize($actor,'operations.worker.read');$this->authorize($actor,'operations.worker.retry');
            if(!isset($this->runtime->subscribers()[$proposal->subscriberId])){throw new AccessDenied();}
            $q=$this->pdo->prepare('SELECT status,revision FROM outbox_deliveries WHERE subscriber_id=? AND event_id=? FOR UPDATE');$q->execute([$proposal->subscriberId,$proposal->eventId]);$row=$q->fetch();
            if($row===false||$row['status']!=='failed'||(int)$row['revision']!==$proposal->expectedRevision){throw new MutationConflict();}
        },function()use($proposal):bool{
            $q=$this->pdo->prepare("UPDATE outbox_deliveries SET status='pending',attempts=0,available_at=0,lease_until=0,lease_token=NULL,error_class=NULL,revision=revision+1 WHERE subscriber_id=? AND event_id=? AND status='failed' AND revision=?");
            $q->execute([$proposal->subscriberId,$proposal->eventId,$proposal->expectedRevision]);if($q->rowCount()!==1){throw new MutationConflict();}return true;
        });
    }
}
