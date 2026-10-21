<?php
use Samla\Core\Web\PageRenderer;
$e = PageRenderer::escape(...);
$titles = ['home' => 'Übersicht', 'users' => 'Benutzer', 'groups' => 'Gruppen', 'error' => 'Zugriff'];
$caps = $data['caps'];
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($titles[$page]) ?> · Samla</title><link rel="stylesheet" href="/assets/samla.css"></head>
<body><a class="skip" href="#main">Zum Inhalt</a>
<div class="shell"><aside class="sidebar" aria-label="Hauptnavigation"><a class="brand" href="/"><span class="brand-icon" aria-hidden="true">S</span>samla<span class="brand-dot">.</span></a><p class="brand-sub">Raum für alles, was zählt.</p>
<p class="nav-heading">ARBEITSPLATZ</p><nav>
<a href="/" <?= $page === 'home' ? 'aria-current="page"' : '' ?>><span aria-hidden="true">◈</span> Übersicht</a>
<?php if ($caps['identity.users.read'] ?? false): ?><a href="/users" <?= $page === 'users' ? 'aria-current="page"' : '' ?>><span aria-hidden="true">◎</span> Benutzer</a><?php endif ?>
<?php if ($caps['identity.groups.read'] ?? false): ?><a href="/groups" <?= $page === 'groups' ? 'aria-current="page"' : '' ?>><span aria-hidden="true">▦</span> Gruppen</a><?php endif ?>
</nav><div class="sidebar-bottom"><span class="small-mark" aria-hidden="true">S</span><div>Eine gemeinsame Basis.<small>Samla · Datei- und Medienplattform</small></div></div></aside>
<div class="workspace"><header class="topbar"><span>Deine Organisation <span class="crumb">/ <?= $e($titles[$page]) ?></span></span><?php if ($data['actor'] !== null): ?><span class="actor"><span class="avatar" aria-hidden="true">S</span><?= $e($data['actor']) ?></span><?php endif ?></header>
<?php if ($data['actor'] !== null): ?><div class="dev-notice"><span class="status-dot" aria-hidden="true"></span> Lokaler Entwicklungszugang <span>Die produktive Anmeldung ist noch nicht eingerichtet.</span></div><?php endif ?>
<main id="main" tabindex="-1">
<?php if ($data['flash'] !== null): ?><div class="notice" role="status"><?= $e($data['flash']) ?></div><?php endif ?>
<?php require __DIR__ . '/' . $page . '.php'; ?>
</main><footer>Samla <span>Gemeinsam organisiert.</span></footer></div></div></body></html>
