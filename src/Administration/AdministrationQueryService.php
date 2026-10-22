<?php
declare(strict_types=1);
namespace Samla\Core\Administration;

use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, Scope};

/** Guarded, bounded directory queries; no issuer/subject or external claim payloads are exposed. */
final readonly class AdministrationQueryService
{
    public function __construct(private \PDO $pdo, private AuthorizationChecker $authorization) {}
    public function users(string $actorId, ?string $after = null): array
    {
        $this->guard($actorId, 'identity.users.read', $after);
        $query = $this->pdo->prepare('SELECT id, display_name, status FROM identity_users WHERE id > ? ORDER BY id LIMIT 51');
        $query->execute([$after ?? '']); return $this->page($query->fetchAll());
    }
    public function groups(string $actorId, ?string $after = null): array
    {
        $this->guard($actorId, 'identity.groups.read', $after);
        $query = $this->pdo->prepare('SELECT g.id, g.name, (SELECT COUNT(DISTINCT m.user_id) FROM identity_memberships m WHERE m.group_id = g.id) AS members FROM identity_groups g WHERE g.id > ? ORDER BY g.id LIMIT 51');
        $query->execute([$after ?? '']); return $this->page($query->fetchAll());
    }
    public function userChoices(string $actorId):array
    {
        $this->guard($actorId,'identity.users.read',null);
        $rows=$this->pdo->query('SELECT id,display_name,status FROM identity_users ORDER BY display_name,id LIMIT 1001')->fetchAll();
        if(count($rows)>1000){throw new \LengthException('User selection limit.');}
        return $rows;
    }
    private function guard(string $actorId, string $action, ?string $after): void
    {
        if ($this->authorization->decide(new AuthorizationRequest($actorId, $action, Scope::Global))->decision !== Decision::Permit) { throw new AccessDenied(); }
        if ($after !== null && !preg_match('/^[a-zA-Z0-9_.:-]{1,64}$/D', $after)) { throw new \InvalidArgumentException('Invalid directory cursor.'); }
    }
    private function page(array $rows): array
    {
        $more = count($rows) > 50; $rows = array_slice($rows, 0, 50);
        return ['rows' => $rows, 'next' => $more ? $rows[array_key_last($rows)]['id'] : null];
    }
}
