<div class="page-heading"><div><p class="eyebrow">TEXTANSICHT</p><h1><?= $e($data['name']) ?></h1><p class="lead">Revision <?= $e($data['revision']) ?> · UTF-8 · maximal 64 KiB</p></div><a href="<?= $e('/files/detail?' . http_build_query(['space' => $data['space_id'], 'entry' => $data['entry_id']])) ?>">Zur Datei</a></div>
<section class="panel auth-panel">
<?php if (isset($data['editor_error'])): ?><p role="alert"><?= $e($data['editor_error']) ?></p><p><a target="_blank" rel="noopener" href="<?= $e('/files/edit?' . http_build_query(['space' => $data['space_id'], 'entry' => $data['entry_id']])) ?>">Aktuellen Stand in neuem Tab öffnen</a></p><?php endif ?>
<p>Der Inhalt wird als Text angezeigt. Beim Speichern werden Zeilenumbrüche auf LF vereinheitlicht. Frühere Inhalte bleiben als Version erhalten.</p><p class="field-note">Ein geöffneter Entwurf lässt sich in unterstützten Browsern auch ohne Verbindung herunterladen. Er wird dadurch noch nicht auf dem Server gespeichert.</p>
<form method="post" action="/files/edit/save">
<input type="hidden" name="csrf" value="<?= $e($data['csrf']) ?>"><input type="hidden" name="space" value="<?= $e($data['space_id']) ?>"><input type="hidden" name="entry" value="<?= $e($data['entry_id']) ?>"><input type="hidden" name="revision" value="<?= $e($data['revision']) ?>">
<label for="editor-text">Dateiinhalt</label><textarea class="text-editor" id="editor-text" name="text" data-draft-name="<?= $e($data['name']) ?>" rows="24" spellcheck="false"<?= $data['can_update'] ? '' : ' readonly' ?>><?= "\n" . $e($data['text']) ?></textarea>
<?php if ($data['can_update']): ?><button class="button" type="submit">Neue Version speichern</button><?php endif ?>
<button class="button button-outline" type="submit" formaction="/files/edit/draft" data-local-draft>Entwurf herunterladen</button>
</form></section>

