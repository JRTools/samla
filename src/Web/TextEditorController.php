<?php
declare(strict_types=1);
namespace Samla\Core\Web;

use Samla\Core\Storage\{FileService,StorageLocked,TextDocument,TextEditorService};
use Samla\PluginApi\{AccessDenied,MutationConflict};

final readonly class TextEditorController
{
    public function __construct(private FileService $files, private PageRenderer $renderer) {}
    public function handle(Request $request, string $actor, string $csrf, array $data): Response
    {
        $editor = new TextEditorService($this->files);
        if ($request->path === '/files/edit') {
            if ($request->method !== 'GET') { return new Response(405, headers: ['Allow' => 'GET']); }
            foreach (['space' => 64, 'entry' => 32] as $key => $limit) {
                $value = $request->query[$key] ?? null;
                if (!is_string($value) || strlen($value) > $limit || !preg_match('/^[a-zA-Z0-9_.:-]+$/D', $value)) { throw new \InvalidArgumentException(); }
                $data[$key . '_id'] = $value;
            }
            try { $document = $editor->open($actor, $data['space_id'], $data['entry_id']); }
            catch (\LengthException) { return new Response(413, $this->renderer->render('error', $data + ['status' => 413, 'error' => 'Der Texteditor öffnet Dateien bis 64 KiB. Der normale Download bleibt verfügbar.'])); }
            catch (\InvalidArgumentException) { return new Response(400, $this->renderer->render('error', $data + ['status' => 400, 'error' => 'Diese Datei kann nicht als unterstützter UTF-8-Text geöffnet werden. Verwende den normalen Download.'])); }
            $data['text'] = $document['text']; $data['revision'] = $document['entry']['revision'];
            $data['name'] = $document['entry']['name']; $data['can_update'] = $document['can_update'];
            return new Response(200, $this->renderer->render('text-editor', $data));
        }
        if ($request->method !== 'POST') { return new Response(405, headers: ['Allow' => 'POST']); }
        if (!preg_match('/^[a-f0-9]{64}$/D', $csrf) || !hash_equals($csrf, $request->field('csrf', 64))) { throw new AccessDenied(); }
        $text = TextDocument::normalize($request->field('text', 2 * TextDocument::MAX_BYTES));
        // Export only the submitted draft. No stored content is read or changed.
        if ($request->path === '/files/edit/draft') {
            return new Response(200, $text, ['Content-Type' => 'text/plain; charset=utf-8', 'Content-Disposition' => 'attachment; filename="samla-entwurf.txt"', 'Content-Length' => (string) strlen($text)]);
        }
        $space = $request->field('space', 64); $entry = $request->field('entry', 32);
        $revision = $request->field('revision', 18);
        if (!ctype_digit($revision) || (int) $revision < 1) { throw new \InvalidArgumentException(); }
        try {
            $editor->save($actor, $space, $entry, (int) $revision, $text);
            return new Response(303, headers: ['Location' => '/files/edit?' . http_build_query(['space' => $space, 'entry' => $entry])], flash: 'Der Text wurde als neue Dateiversion gespeichert.');
        } catch (MutationConflict) { $status = 409; $message = 'Die Datei wurde inzwischen geändert. Dein Entwurf bleibt hier erhalten. Lade ihn herunter, bevor du den aktuellen Stand öffnest.'; }
        catch (StorageLocked) { $status = 423; $message = 'Die Datei ist für eine andere Bearbeitung gesperrt. Dein Entwurf bleibt hier erhalten und kann heruntergeladen werden.'; }
        catch (\LengthException) { $status = 413; $message = 'Die Speicherquote oder Versionsgrenze verhindert das Speichern. Dein Entwurf bleibt hier erhalten und kann heruntergeladen werden.'; }
        $data['space_id'] = $space; $data['entry_id'] = $entry; $data['revision'] = $revision;
        $data['text'] = $text; $data['name'] = 'Ungespeicherter Entwurf'; $data['can_update'] = true; $data['editor_error'] = $message;
        return new Response($status, $this->renderer->render('text-editor', $data));
    }
}
