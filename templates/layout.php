<?php
use Samla\Core\Web\PageRenderer;
$e = PageRenderer::escape(...);
$titles = ['home' => 'Übersicht', 'users' => 'Benutzer', 'groups' => 'Gruppen', 'error' => 'Zugriff', 'login' => 'Anmelden', 'account' => 'Mein Zugang', 'spaces' => 'Speicherbereiche', 'files' => 'Dateien'];
$caps = $data['caps'];
$titles += ['file-detail' => 'Datei verwalten', 'trash' => 'Papierkorb', 'app-passwords' => 'App-Passwörter'];
$titles['catalog-search']='Katalog durchsuchen';$titles['catalog-batch']='Tags gemeinsam bearbeiten';$titles['catalog']='Beschreibung und Tags';$titles['group-mappings']='IdP-Gruppen zuordnen';$titles['exports']='Meine Exporte';$titles['permissions']='Berechtigungen';$titles['space-administration']='Bereichsverwaltung';
$titles['versions'] = 'Dateiversionen';
$titles['text-editor'] = 'Text bearbeiten';
$titles['file-search'] = 'Dateien suchen';
$titles['worker']='Hintergrundaufgaben';
$titles['media-gallery']='Medien';$titles['media-detail']='Medium';$titles['media-rebuild']='Medienanalyse neu aufbauen';

$filePages = ['spaces','files','file-detail','trash','versions','text-editor','file-search','catalog','catalog-batch','catalog-search'];
$mediaPages = ['media-gallery','media-detail','media-rebuild'];
$signedIn = $data['actor'] !== null;
$accountName = $data['display_name'] ?? $data['name'] ?? 'Mein Konto';
$administration = ($caps['identity.users.read'] ?? false) || ($caps['identity.groups.read'] ?? false) || ($caps['access.profiles.manage'] ?? false) || ($caps['operations.worker.read'] ?? false);
?>
<!doctype html>
<html lang="de">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $e($titles[$page]) ?> · Samla</title><link rel="stylesheet" href="/assets/samla.css?v=45"><script src="/assets/workspace.js?v=45" defer></script></head>
<body class="<?= $signedIn ? 'signed-in' : 'signed-out' ?>"><a class="skip" href="#main">Zum Inhalt</a>
<div class="shell"><aside class="sidebar" aria-label="Hauptnavigation"><a class="brand" href="/"><span class="brand-icon" aria-hidden="true">S</span>Samla<span class="brand-dot">.</span></a><p class="brand-sub">JRTools · Dateien und Medien</p>
<?php if ($signedIn): ?>
<button class="button button-outline navigation-toggle" type="button" data-navigation-toggle aria-controls="site-navigation" aria-expanded="true" hidden>Menü</button>
<div id="site-navigation"><p class="nav-heading">ARBEITSPLATZ</p><nav aria-label="Arbeitsplatz">
<a href="/" <?= $page === 'home' ? 'aria-current="page"' : '' ?>>Start</a>
<a href="/files" <?= in_array($page, $filePages, true) ? 'aria-current="page"' : '' ?>>Dateien</a>
<a href="/media" <?= in_array($page, $mediaPages, true) ? 'aria-current="page"' : '' ?>>Medien</a>
<a href="/exports" <?= $page === 'exports' ? 'aria-current="page"' : '' ?>>Meine Exporte</a></nav>
<?php if ($administration): ?><p class="nav-heading navigation-section">VERWALTUNG</p><nav aria-label="Verwaltung">
<?php if ($caps['access.profiles.manage'] ?? false): ?><a href="/administration/spaces" <?= $page === 'space-administration' ? 'aria-current="page"' : '' ?>>Speicherbereiche</a><a href="/permissions" <?= $page === 'permissions' ? 'aria-current="page"' : '' ?>>Berechtigungen</a><?php endif ?>
<?php if ($caps['identity.users.read'] ?? false): ?><a href="/users" <?= $page === 'users' ? 'aria-current="page"' : '' ?>>Benutzer</a><?php endif ?>
<?php if ($caps['identity.groups.read'] ?? false): ?><a href="/groups" <?= $page === 'groups' ? 'aria-current="page"' : '' ?>>Gruppen</a><?php endif ?>
<?php if (($caps['identity.groups.mappings.manage'] ?? false) && ($caps['identity.groups.read'] ?? false)): ?><a href="/administration/group-mappings" <?= $page === 'group-mappings' ? 'aria-current="page"' : '' ?>>IdP-Zuordnungen</a><?php endif ?>
<?php if ($caps['operations.worker.read'] ?? false): ?><a href="/operations/worker" <?= $page === 'worker' ? 'aria-current="page"' : '' ?>>Hintergrundaufgaben</a><?php endif ?></nav><?php endif ?></div>
<?php endif ?>
<div class="sidebar-bottom">Ein Tool von JRTools.</div></aside>
<div class="workspace"><header class="topbar"><nav class="breadcrumbs" aria-label="Seitenpfad"><a href="/">Samla</a><?php if(in_array($page, $filePages, true) && $page !== 'spaces'): ?><span aria-hidden="true">/</span><a href="/files">Dateien</a><?php endif ?><span aria-hidden="true">/</span><span aria-current="page"><?= $e($titles[$page]) ?></span></nav>
<?php if ($signedIn && ($data['oidc'] ?? false)): ?><details class="account-menu"><summary><?= $e($accountName) ?></summary><div class="account-menu-content"><a href="/account" <?= in_array($page,['account','app-passwords'],true) ? 'aria-current="page"' : '' ?>>Mein Zugang</a><a href="/account/app-passwords">Geräte und App-Passwörter</a><form method="post" action="/auth/logout"><input type="hidden" name="csrf" value="<?= $e($data['csrf']) ?>"><button class="button button-outline" type="submit">Bei Samla abmelden</button></form></div></details><?php elseif($signedIn): ?><span>Entwicklungszugang</span><?php endif ?></header>
<?php if ($signedIn && !($data['oidc'] ?? false)): ?><div class="dev-notice">Lokaler Entwicklungszugang: nur auf dem Entwicklungsrechner.</div><?php endif ?>
<main id="main" tabindex="-1">
<?php if ($data['flash'] !== null): ?><div class="notice" role="status"><?= $e($data['flash']) ?></div><?php endif ?>
<?php if(isset($data['form_error'])): ?><div class="notice notice-error" role="alert" tabindex="-1" data-form-error><?= $e($data['form_error']) ?></div><?php endif ?>
<?php require __DIR__ . '/' . $page . '.php'; ?>
</main><footer>JRTools · Samla <span>Gemeinsam organisiert.</span></footer></div></div></body></html>
