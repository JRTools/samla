<?php if($caps['access.profiles.manage']??false): ?><p><a class="button button-outline" href="/administration/spaces">Speicherbereiche verwalten</a> <a class="button button-outline" href="/permissions">Gruppenrechte verwalten</a></p><?php endif ?>
<div class="page-heading"><div><p class="eyebrow">DATEIABLAGE</p><h1>Deine Speicherbereiche.</h1><p class="lead">Gemeinsame Dateien mit ausdrücklich vergebenen Zugriffsrechten.</p></div></div>
<section class="panel"><div class="panel-heading"><h2>Verfügbare Bereiche</h2></div><div class="group-list">
<?php foreach ($data['rows'] as $row): ?><article class="group-row"><div><h3><a href="/files?space=<?= $e(rawurlencode($row['id'])) ?>"><?= $e($row['name']) ?></a></h3><p class="subtle">Quote: <?= $e(number_format((int) $row['quota_bytes'] / 1048576, 0, ',', '.')) ?> MiB</p></div></article><?php endforeach ?>
<?php if ($data['rows'] === []): ?><p class="empty">Auf dieser Seite sind keine für dich freigegebenen Speicherbereiche vorhanden.</p><?php endif ?>
</div><?php if ($data['next'] !== null): ?><div class="panel-foot"><a href="/files?after=<?= $e(rawurlencode($data['next'])) ?>">Weitere Bereiche</a></div><?php endif ?></section>
