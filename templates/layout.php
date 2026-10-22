<?php
use Samla\Core\Web\PageRenderer;
$e = PageRenderer::escape(...);
$titles = ['home' => 'Übersicht', 'users' => 'Benutzer', 'groups' => 'Gruppen', 'error' => 'Zugriff', 'login' => 'Anmelden', 'account' => 'Mein Zugang', 'spaces' => 'Speicherbereiche', 'files' => 'Dateien'];
$caps = $data['caps'];
$titles += ['file-detail' => 'Datei verwalten', 'trash' => 'Papierkorb', 'app-passwords' => 'App-Passwörter'];
$titles['versions'] = 'Dateiversionen';
$titles['text-editor'] = 'Text bearbeiten';
$titles['file-search'] = 'Dateien suchen';
$titles['worker']='Hintergrundaufgaben';
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($titles[$page]) ?> · Samla</title><link rel="stylesheet" href="/assets/samla.css"></head>
<body><a class="skip" href="#main">Zum Inhalt</a>
<div class="shell"><aside class="sidebar" aria-label="Hauptnavigation"><a class="brand" href="/"><span class="brand-icon" aria-hidden="true">S</span>samla<span class="brand-dot">.</span></a><p class="brand-sub">Raum für alles, was zählt.</p>
<p class="nav-heading">ARBEITSPLATZ</p><nav>
<a href="/" <?= in_array($page, ['home','account'], true) ? 'aria-current="page"' : '' ?>><span aria-hidden="true">◈</span> Übersicht</a>
<?php if ($data['actor'] !== null): ?><a href="/files" <?= in_array($page, ['spaces','files'], true) ? 'aria-current="page"' : '' ?>>Dateien</a><?php endif ?>
<?php if (($data['oidc'] ?? false) && $data['actor'] !== null): ?><a href="/account">Mein Zugang</a><form class="logout" method="post" action="/auth/logout"><input type="hidden" name="csrf" value="<?= $e($data['csrf']) ?>"><button class="button button-outline" type="submit">Bei Samla abmelden</button></form><?php endif ?>
<?php if ($caps['identity.users.read'] ?? false): ?><a href="/users" <?= $page === 'users' ? 'aria-current="page"' : '' ?>><span aria-hidden="true">◎</span> Benutzer</a><?php endif ?>
<?php if ($caps['identity.groups.read'] ?? false): ?><a href="/groups" <?= $page === 'groups' ? 'aria-current="page"' : '' ?>><span aria-hidden="true">▦</span> Gruppen</a><?php endif ?>
<?php if ($caps['operations.worker.read'] ?? false): ?><a href="/operations/worker" <?= $page === 'worker' ? 'aria-current="page"' : '' ?>>Hintergrundaufgaben</a><?php endif ?>
</nav><div class="sidebar-bottom"><span class="small-mark" aria-hidden="true">S</span><div>Eine gemeinsame Basis.<small>Samla · Datei- und Medienplattform</small></div></div></aside>
<div class="workspace"><header class="topbar"><span>Deine Organisation <span class="crumb">/ <?= $e($titles[$page]) ?></span></span><?php if ($data['actor'] !== null): ?><span class="actor"><span class="avatar" aria-hidden="true">S</span><?= $e($data['actor']) ?></span><?php endif ?></header>
<?php if ($data['actor'] !== null && !($data['oidc'] ?? false)): ?><div class="dev-notice"><span class="status-dot" aria-hidden="true"></span> Lokaler Entwicklungszugang <span>Nur auf dem lokalen Entwicklungsrechner.</span></div><?php endif ?>
<main id="main" tabindex="-1">
<?php if ($data['flash'] !== null): ?><div class="notice" role="status"><?= $e($data['flash']) ?></div><?php endif ?>
<?php require __DIR__ . '/' . $page . '.php'; ?>
</main><footer>Samla <span>Gemeinsam organisiert.</span></footer></div></div></body></html>
