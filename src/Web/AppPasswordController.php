<?php
declare(strict_types=1);
namespace Samla\Core\Web;
use Samla\Core\Identity\AppPasswordService;
use Samla\PluginApi\AccessDenied;

final readonly class AppPasswordController
{
    public function __construct(private AppPasswordService $passwords, private PageRenderer $renderer = new PageRenderer()) {}
    public function handle(Request $request, string $actor, string $csrf, array $data): Response
    {
        try {
            if ($request->path === '/account/app-passwords') {
                if ($request->method !== 'GET') { return new Response(405, headers: ['Allow' => 'GET']); }
            } elseif (in_array($request->path, ['/account/app-passwords/create','/account/app-passwords/revoke'], true)) {
                if ($request->method !== 'POST') { return new Response(405, headers: ['Allow' => 'POST']); }
                if (!preg_match('/^[a-f0-9]{64}$/D', $csrf) || !hash_equals($csrf, $request->field('csrf', 64))) { throw new AccessDenied(); }
                if ($request->path === '/account/app-passwords/revoke') {
                    $this->passwords->revoke($actor, $request->field('credential', 32));
                    return new Response(303, headers: ['Location' => '/account/app-passwords']);
                }
                $days = $request->field('days', 2); if (!ctype_digit($days)) { throw new \InvalidArgumentException(); }
                $data['new_password'] = $this->passwords->create($actor, $request->field('label', 1020), (int) $days);
            } else { return new Response(404); }
            $data['passwords'] = $this->passwords->listing($actor);
            return new Response(isset($data['new_password']) ? 201 : 200, $this->renderer->render('app-passwords', $data));
        } catch (AccessDenied) { return $this->error(403, 'Für diese Aktion ist ein aktiver Zugang mit aktuellem Gruppenstand erforderlich. Bitte erneut anmelden.', $data); }
        catch (\InvalidArgumentException|\LengthException) { return $this->error(400, 'Bitte Bezeichnung und Gültigkeit prüfen. Höchstens 20 aktive App-Passwörter sind erlaubt.', $data); }
    }
    private function error(int $status, string $message, array $data): Response
    { return new Response($status, $this->renderer->render('error', $data + ['status' => $status, 'error' => $message])); }
}
