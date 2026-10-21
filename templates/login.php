<?php use Samla\Core\Web\PageRenderer; $e = PageRenderer::escape(...); ?>
<section class="page-heading"><div><p class="eyebrow">WILLKOMMEN BEI SAMLA</p><h1>Alles an einem Ort.</h1><p>Melde dich mit deinem Organisationskonto an.</p></div></section>
<?php if (isset($data['loginError'])): ?><div class="notice" role="alert"><?= $e($data['loginError']) ?></div><?php endif ?>
<section class="panel auth-panel"><h2>Sicher anmelden</h2><p>Die Anmeldung erfolgt bei deinem zentralen Identitätsanbieter.</p>
<form method="post" action="/auth/login"><input type="hidden" name="csrf" value="<?= $e($data['csrf']) ?>"><button class="button" type="submit">Mit Organisationskonto anmelden</button></form></section>
