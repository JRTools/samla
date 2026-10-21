<?php use Samla\Core\Web\PageRenderer; $e = PageRenderer::escape(...); ?>
<section class="page-heading"><div><p class="eyebrow">MEIN ZUGANG</p><h1>Willkommen, <?= $e($data['name']) ?>.</h1><p>Du bist mit deinem Organisationskonto angemeldet.</p></div></section>
<section class="panel auth-panel"><h2>Dein Konto</h2><p>Deine Samla-Kennung:</p><p><code><?= $e($data['actor']) ?></code></p>
<?php if (!$data['fresh']): ?><p>Die Gruppenfrischerichtlinie ist noch nicht eingerichtet oder der bestätigte Gruppenstand ist abgelaufen. Verwaltungszugriffe bleiben gesperrt.</p><?php endif ?>
<?php if (!($data['caps']['identity.users.read'] ?? false) && !($data['caps']['identity.groups.read'] ?? false)): ?><p>Für dieses Konto sind noch keine Verwaltungsrechte freigegeben. Wende dich mit deiner Samla-Kennung an die Administration.</p><?php else: ?><p>Die freigegebenen Verwaltungsfunktionen findest du in der Navigation.</p><?php endif ?>
<p>„Bei Samla abmelden“ beendet diese Samla-Sitzung. Deine zentrale Anmeldung beim Identitätsanbieter bleibt bestehen.</p></section>
<p><a class="button button-outline" href="/account/app-passwords">App-Passwörter verwalten</a></p>
