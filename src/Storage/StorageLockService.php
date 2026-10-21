<?php
declare(strict_types=1);
namespace Samla\Core\Storage;
use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, MutationContext, MutationRunner, Scope, StorageLockChange};

final readonly class StorageLockService
{
    public function __construct(private \PDO $pdo, private AuthorizationChecker $authorization, private MutationRunner $mutations,
        private StorageWriteGuard $guard) {}
    private function authorize(string $actor, string $space, string $action = 'files.update'): void
    { if ($this->authorization->decide(new AuthorizationRequest($actor,$action,Scope::Space,$space))->decision !== Decision::Permit) { throw new AccessDenied(); } }
    public function acquire(string $actor, string $space, string $entry, bool $recursive = false, int $seconds = 3600, string $owner = '', ?string $token = null): array
    {
        if ($seconds < 1 || $seconds > 3600 || strlen($owner) > 4096 || preg_match('//u',$owner) !== 1) { throw new \InvalidArgumentException('Invalid lock request.'); }
        $token ??= bin2hex(random_bytes(32)); StorageWriteGuard::token($token); $id = bin2hex(random_bytes(16));
        $targets = ['lock'=>$id]; if ($entry !== '') { $targets['entry']=$entry; }
        $context = new MutationContext(bin2hex(random_bytes(16)),$actor,'storage.lock.acquire',Scope::Space,$space,$targets,
            storageLock:new StorageLockChange($recursive,$seconds));
        $this->mutations->run($context, function () use ($actor,$space,$entry,$recursive): void {
            $this->authorize($actor,$space); $this->guard->assertVisible($space,$entry);
            if ($this->guard->conflicts($space,$entry,$recursive) !== []) { throw new StorageLocked('Overlapping exclusive lock.'); }
        }, function () use ($id,$token,$space,$entry,$actor,$recursive,$owner,$seconds): bool {
            $q=$this->pdo->prepare('DELETE FROM storage_locks WHERE space_id = ? AND expires_at <= ?'); $q->execute([$space,$this->guard->now()]);
            $q=$this->pdo->prepare('SELECT COUNT(*) FROM storage_locks WHERE space_id = ?'); $q->execute([$space]);
            if ((int)$q->fetchColumn() >= 1000) { throw new \LengthException('Lock capacity reached.'); }
            $q=$this->pdo->prepare('INSERT INTO storage_locks (id, token, space_id, entry_id, actor_id, recursive_lock, owner_label, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $q->execute([$id,$token,$space,$entry,$actor,$recursive?1:0,$owner,$this->guard->now()+$seconds]); return true;
        });
        return $this->find($space,$token);
    }
    public function renew(string $actor, string $space, string $entry, string $token, int $seconds): array
    {
        if ($seconds < 1 || $seconds > 3600) { throw new \InvalidArgumentException('Invalid duration.'); }
        $lease=$this->find($space,$token);
        $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'storage.lock.renew',Scope::Space,$space,['lock'=>$lease['id']],
            storageLock:new StorageLockChange((bool)$lease['recursive_lock'],$seconds)),
            function () use ($actor,$space,$entry,$token,$lease): void { $this->assertOwned($actor,$space,$entry,$token,$lease['id']); },
            function () use ($lease,$seconds): bool {
                $q=$this->pdo->prepare('UPDATE storage_locks SET expires_at = ? WHERE id = ?'); $q->execute([$this->guard->now()+$seconds,$lease['id']]); return $q->rowCount() !== 0;
            });
        return $this->find($space,$token);
    }
    public function release(string $actor, string $space, string $entry, string $token): void
    {
        $lease=$this->find($space,$token);
        $this->mutations->run(new MutationContext(bin2hex(random_bytes(16)),$actor,'storage.lock.release',Scope::Space,$space,['lock'=>$lease['id']],
            storageLock:new StorageLockChange((bool)$lease['recursive_lock'],0)),
            function () use ($actor,$space,$entry,$token,$lease): void { $this->assertOwned($actor,$space,$entry,$token,$lease['id']); },
            function () use ($lease): bool { $q=$this->pdo->prepare('DELETE FROM storage_locks WHERE id = ?'); $q->execute([$lease['id']]); return $q->rowCount() === 1; });
    }
    public function listing(string $actor, string $space, string $entry, bool $children = false): array
    {
        $this->authorize($actor,$space,'files.list'); $this->guard->assertVisible($space,$entry);
        return $this->guard->conflicts($space,$entry,$children);
    }
    private function assertOwned(string $actor,string $space,string $entry,string $token,string $expectedId): void
    {
        $this->authorize($actor,$space); $this->guard->assertVisible($space,$entry); $lease=$this->find($space,$token);
        if ($lease['id'] !== $expectedId || $lease['actor_id'] !== $actor || $lease['entry_id'] !== $entry || (int)$lease['expires_at'] <= $this->guard->now()) { throw new AccessDenied(); }
    }
    private function find(string $space,string $token): array
    {
        StorageWriteGuard::token($token); $q=$this->pdo->prepare('SELECT * FROM storage_locks WHERE space_id = ? AND token = ?'); $q->execute([$space,$token]);
        $lease=$q->fetch(); if ($lease === false) { throw new AccessDenied(); } return $lease;
    }
}
