<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Storage\FileService;
use Samla\PluginApi\{AccessDenied, MutationConflict};

final readonly class FileController
{
    public function __construct(private FileService $files, private PageRenderer $renderer = new PageRenderer()) {}
    public function handle(Request $request, string $actor, string $csrf, array $data): Response
    {
        try {
            if (in_array($request->path, ['/files/folder', '/files/upload', '/files/change', '/files/replace', '/files/version/restore'], true)) {
                if ($request->method !== 'POST') { return new Response(405, headers: ['Allow' => 'POST']); }
                if (!preg_match('/^[a-f0-9]{64}$/D', $csrf) || !hash_equals($csrf, $request->field('csrf', 64))) { throw new AccessDenied(); }
                $space = $request->field('space', 64); $parent = $request->field('parent', 32);
                if ($request->path === '/files/version/restore') {
                    $this->files->restoreVersion($actor, $space, $request->field('entry', 32), $request->field('version', 32), $this->revision($request));
                }
                elseif ($request->path === '/files/change') {
                    $this->files->change($actor, $space, $request->field('entry', 32), $request->field('operation', 12),
                        $request->field('expected_name', 1020), $request->field('expected_parent', 32),
                        ($request->post['name'] ?? '') === '' ? null : $request->field('name', 1020), $parent);
                    $parent = $request->field('expected_parent', 32);
                }
                elseif ($request->path === '/files/folder') { $this->files->createFolder($actor, $space, $parent, $request->field('name', 1020)); }
                else {
                    $file = $request->files['file'] ?? null;
                    if (!is_array($file) || !is_int($file['error'] ?? null) || $file['error'] !== UPLOAD_ERR_OK
                        || !is_string($file['name'] ?? null) || !is_string($file['tmp_name'] ?? null)
                        || !is_uploaded_file($file['tmp_name'])) { throw new \InvalidArgumentException('Invalid HTTP upload.'); }
                    $source = fopen($file['tmp_name'], 'rb');
                    try {
                        if ($request->path === '/files/replace') { $this->files->replace($actor, $space, $request->field('entry', 32), $this->revision($request), $source); }
                        else { $this->files->upload($actor, $space, $parent, $file['name'], $source); }
                    }
                    finally { if (is_resource($source)) { fclose($source); } }
                }
                return new Response(303, headers: ['Location' => '/files?' . http_build_query(['space' => $space, 'parent' => $parent])], flash: 'Die Dateiablage wurde aktualisiert.');
            }
            if (!in_array($request->path, ['/files', '/files/download', '/files/detail', '/files/trash', '/files/versions', '/files/version/download'], true)) { return new Response(404); }
            if ($request->method !== 'GET') { return new Response(405, headers: ['Allow' => 'GET']); }
            $space = $this->query($request, 'space', 64);
            if ($request->path === '/files/versions') {
                $data += $this->files->versions($actor, $space, $this->query($request, 'entry', 32), $request->after());
                $data['space_id'] = $space; $data['can_update'] = $this->files->allowed($actor, 'files.update', $space);
                return new Response(200, $this->renderer->render('versions', $data));
            }
            if ($request->path === '/files/trash') {
                $data += $this->files->trashListing($actor, $space, $request->after());
                return new Response(200, $this->renderer->render('trash', $data));
            }
            if ($request->path === '/files/detail') {
                $data['entry'] = $this->files->details($actor, $space, $this->query($request, 'entry', 32)); $data['space_id'] = $space;
                $data['destinations'] = $this->files->folderChoices($actor, $space);
                foreach (['update','delete','restore','read','create','versions.read'] as $action) { $data['file_caps'][$action] = $this->files->allowed($actor, 'files.' . $action, $space); }
                return new Response(200, $this->renderer->render('file-detail', $data));
            }
            if (in_array($request->path, ['/files/download','/files/version/download'], true)) {
                $download = $request->path === '/files/download'
                    ? $this->files->download($actor, $space, $this->query($request, 'entry', 32))
                    : $this->files->downloadVersion($actor, $space, $this->query($request, 'entry', 32), $this->query($request, 'version', 32));
                $stream = $download['stream']; $row = $download['row'];
                return new Response(200, headers: [
                    'Content-Type' => 'application/octet-stream', 'Content-Length' => (string) $row['size_bytes'],
                    'Content-Disposition' => "attachment; filename=\"download\"; filename*=UTF-8''" . rawurlencode($row['name']),
                ], stream: static function () use ($stream): void { try { fpassthru($stream); } finally { fclose($stream); } });
            }
            if ($space === '') { $data += $this->files->spaces($actor, $request->after()); $page = 'spaces'; }
            else { $data += $this->files->listing($actor, $space, $this->query($request, 'parent', 32), $request->after()); $page = 'files'; }
            return new Response(200, $this->renderer->render($page, $data));
        } catch (AccessDenied) { return $this->error(403, 'Für diesen Speicherbereich fehlt die Berechtigung oder dein Gruppenstand ist nicht mehr aktuell.', $data); }
        catch (\Samla\Core\Storage\StorageLocked) { return $this->error(423, 'Der Eintrag oder sein Ziel ist gerade für eine Bearbeitung gesperrt. Bitte die Sperre im verwendeten Client freigeben oder ihren Ablauf abwarten.', $data); }
        catch (\Samla\Core\Storage\StorageUnavailable) { return $this->error(503, 'Der private Dateispeicher ist noch nicht eingerichtet oder für PHP nicht beschreibbar. Bitte die Administration um Prüfung bitten.', $data); }
        catch (MutationConflict) { return $this->error(409, 'Der Eintrag wurde inzwischen geändert oder der Name ist bereits vergeben. Bitte neu laden. Es wurde nichts überschrieben.', $data); }
        catch (\LengthException) { return $this->error(413, 'Uploadgrenze, Speicherquote oder Versionskapazität überschritten. Vorhandene Versionen bleiben erhalten.', $data); }
        catch (\InvalidArgumentException) { return $this->error(400, 'Bitte prüfe Dateiname, Zielordner und die Uploadgrenzen des Servers.', $data); }
    }
    private function query(Request $request, string $key, int $limit): string
    {
        $value = $request->query[$key] ?? '';
        if (!is_string($value) || strlen($value) > $limit || ($value !== '' && !preg_match('/^[a-zA-Z0-9_.:-]+$/D', $value))) { throw new \InvalidArgumentException('Invalid reference.'); }
        return $value;
    }
    private function revision(Request $request): int
    {
        $value = $request->field('revision', 18);
        if (!ctype_digit($value) || (int) $value < 1) { throw new \InvalidArgumentException('Invalid revision.'); }
        return (int) $value;
    }
    private function error(int $status, string $message, array $data): Response
    { return new Response($status, $this->renderer->render('error', $data + ['status' => $status, 'error' => $message])); }
}
