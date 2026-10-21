<?php $row = $data['entry']; $rights = $data['file_caps']; ?>
<div class="page-heading"><div><p class="eyebrow">DATEIABLAGE</p><h1><?= $e($row['name']) ?></h1><p class="lead"><?= $row['kind'] === 'folder' ? 'Ordner' : 'Datei' ?></p></div><a class="button button-outline" href="<?= $e('/files?' . http_build_query(['space' => $data['space_id'], 'parent' => $row['parent_id']])) ?>">Zurück zum Ordner</a></div>
<section class="panel"><div class="panel-heading"><h2>Eintrag verwalten</h2></div><div class="group-list">
<?php
$actions = [];
if ($rights['update']) { $actions['rename'] = 'Umbenennen'; }
if ($rights['update'] && $rights['create'] && $rights['delete']) { $actions['move'] = 'Verschieben'; }
if ($row['kind'] === 'file' && $rights['read'] && $rights['create']) { $actions['copy'] = 'Datei kopieren'; }
if ($rights['delete']) { $actions['trash'] = 'In den Papierkorb'; }
?>
<?php foreach ($actions as $action => $label): ?><form class="group-row" method="post" action="/files/change"><div><h3><?= $e($label) ?></h3><input type="hidden" name="csrf" value="<?= $e($data['csrf']) ?>"><input type="hidden" name="space" value="<?= $e($data['space_id']) ?>"><input type="hidden" name="entry" value="<?= $e($row['id']) ?>"><input type="hidden" name="operation" value="<?= $e($action) ?>"><input type="hidden" name="expected_name" value="<?= $e($row['name']) ?>"><input type="hidden" name="expected_parent" value="<?= $e($row['parent_id']) ?>">
<?php if (in_array($action, ['rename','copy'], true)): ?><label for="name-<?= $e($action) ?>">Neuer Name</label><input id="name-<?= $e($action) ?>" name="name" value="<?= $e($row['name']) ?>" required maxlength="255"><?php endif ?>
<?php if (in_array($action, ['move','copy'], true)): ?><label for="parent-<?= $e($action) ?>">Zielordner</label><select id="parent-<?= $e($action) ?>" name="parent"><?php foreach ($data['destinations'] as $folderId => $folderName): ?><?php if ($folderId === $row['id']) { continue; } ?><option value="<?= $e($folderId) ?>"><?= $e($folderName) ?></option><?php endforeach ?></select><p class="field-note">Die Auswahl zeigt bis zu 100 Ordner dieses Bereichs.</p><?php else: ?><input type="hidden" name="parent" value="<?= $e($row['parent_id']) ?>"><?php endif ?>
</div><button class="button button-outline" type="submit"><?= $e($label) ?></button></form><?php endforeach ?>
<?php if ($actions === []): ?><p class="empty">Für diesen Eintrag sind keine Änderungen freigegeben.</p><?php endif ?></div></section>
