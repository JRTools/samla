<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Administration\AdministrationQueryService;
use Samla\Core\Identity\{GroupDetailsService, GroupMembershipService, UserStatusService};
use Samla\PluginApi\{AccessDenied, AuthorizationChecker, AuthorizationRequest, Decision, MutationConflict, Scope};

final readonly class AdminController
{
    public function __construct(private AdministrationQueryService $directory, private AuthorizationChecker $authorization,
        private UserStatusService $users, private GroupDetailsService $groups, private GroupMembershipService $memberships,
        private PageRenderer $renderer = new PageRenderer(), private ?FileController $files = null,private ?WorkerAdministrationController $worker=null,private ?MediaController $media=null,private ?PermissionController $permissions=null,private ?SpaceAdministrationController $spaces=null) {}

    /** actorId and session token come only from the trusted entry point, never request fields. */
    public function capabilities(string $actorId): array
    {
        $caps = [];
        foreach (['identity.users.read','identity.groups.read','identity.users.status.manage','identity.groups.details.manage','identity.groups.memberships.manage','operations.worker.read','operations.worker.retry'] as $action) {
            $caps[$action] = $this->authorization->decide(new AuthorizationRequest($actorId, $action, Scope::Global))->decision === Decision::Permit;
        }
        $caps['access.profiles.manage']=$this->permissions?->canManage($actorId)??false;
        return $caps;
    }
    public function handle(Request $request, string $actorId, string $csrf, ?string $flash = null, bool $oidc = false): Response
    {
        $caps = $this->capabilities($actorId);
        $data = ['actor' => $actorId, 'csrf' => $csrf, 'caps' => $caps, 'flash' => $flash, 'oidc' => $oidc];
        if (str_starts_with($request->path, '/files') && $this->files !== null) { return $this->files->handle($request, $actorId, $csrf, $data); }
        if (str_starts_with($request->path, '/media') && $this->media!==null) {return $this->media->handle($request,$actorId,$csrf,$data);}
        if (str_starts_with($request->path, '/operations/worker') && $this->worker!==null) {return $this->worker->handle($request,$actorId,$csrf,$data);}
        if(str_starts_with($request->path,'/permissions')&&$this->permissions!==null){return $this->permissions->handle($request,$actorId,$csrf,$data);}
        if(str_starts_with($request->path,'/administration/spaces')&&$this->spaces!==null){return $this->spaces->handle($request,$actorId,$csrf,$data);}
        try {
            $posts = ['/users/status','/groups/create','/groups/rename','/memberships/change'];
            if (in_array($request->path, $posts, true)) {
                if ($request->method !== 'POST') { return $this->error(405, 'Diese Aktion benötigt ein Formular.', $data, ['Allow' => 'POST']); }
                if (!preg_match('/^[a-f0-9]{64}$/D', $csrf) || !hash_equals($csrf, $request->field('csrf', 64))) { throw new AccessDenied(); }
                return $this->mutate($request, $actorId);
            }
            if (!in_array($request->path, ['/','/users','/groups'], true)) { return $this->error(404, 'Diese Seite wurde nicht gefunden.', $data); }
            if ($request->method !== 'GET') { return $this->error(405, 'Diese Seite kann nur angezeigt werden.', $data, ['Allow' => 'GET']); }
            if ($request->path === '/users') { $data += $this->directory->users($actorId, $request->after()); $page = 'users'; }
            elseif ($request->path === '/groups') { $data += $this->directory->groups($actorId, $request->after()); $page = 'groups'; if($caps['identity.groups.memberships.manage']&&$caps['identity.users.read']){$data['user_choices']=$this->directory->userChoices($actorId);} }
            else {
                if (!$caps['identity.users.read'] && !$caps['identity.groups.read']) { throw new AccessDenied(); }
                $page = 'home';
            }
            return new Response(200, $this->renderer->render($page, $data));
        } catch (AccessDenied) { return $this->error(403, 'Für diese Aktion fehlt die Berechtigung oder die Sicherheitsprüfung ist fehlgeschlagen.', $data); }
        catch (MutationConflict) { return $this->error(409, 'Die Gruppe wurde inzwischen geändert. Bitte lade die Gruppenliste erneut.', $data); }
        catch (\LengthException) { return $this->error(413, 'Die Benutzerauswahl überschreitet 1000 Einträge.', $data); }
        catch (\InvalidArgumentException) { return $this->error(400, 'Die Eingabe ist ungültig. Bitte prüfe Kennungen und Namen.', $data); }
    }
    private function mutate(Request $request, string $actor): Response
    {
        switch ($request->path) {
            case '/users/status':
                $status = $request->field('status', 8);
                if (!in_array($status, ['active','blocked'], true)) { throw new \InvalidArgumentException('Invalid status.'); }
                $changed = $this->users->setBlocked($actor, $request->field('user', 64), $status === 'blocked');
                $location = '/users'; break;
            case '/groups/create':
                $this->groups->create($actor, $request->field('name', 1020)); $changed = true; $location = '/groups'; break;
            case '/groups/rename':
                $changed = $this->groups->rename($actor, $request->field('group', 64), $request->field('expected_name', 1020), $request->field('name', 1020));
                $location = '/groups'; break;
            case '/memberships/change':
                $operation = $request->field('operation', 8);
                if (!in_array($operation, ['grant','revoke'], true)) { throw new \InvalidArgumentException('Invalid operation.'); }
                $changed = $operation === 'grant'
                    ? $this->memberships->grant($actor, $request->field('user', 64), $request->field('group', 64))
                    : $this->memberships->revoke($actor, $request->field('user', 64), $request->field('group', 64));
                $location = '/groups'; break;
            default: throw new \LogicException('Unknown mutation.');
        }
        return new Response(303, headers: ['Location' => $location], flash: $changed ? 'Die Änderung wurde gespeichert.' : 'Der gewünschte Zustand besteht bereits.');
    }
    private function error(int $status, string $message, array $data, array $headers = []): Response
    { return new Response($status, $this->renderer->render('error', $data + ['error' => $message, 'status' => $status]), $headers); }
}
