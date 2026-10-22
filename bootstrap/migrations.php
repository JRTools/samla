<?php
declare(strict_types=1);

// Trusted composition root: plugins contribute public SchemaMigration values.
return [
    ...require __DIR__ . '/../migrations/identity.php',
    ...require __DIR__ . '/../migrations/app-passwords.php',
    ...require __DIR__ . '/../migrations/administration.php',
    ...require __DIR__ . '/../migrations/outbox-delivery.php',
    ...require __DIR__ . '/../migrations/outbox-revision.php',
    ...require __DIR__ . '/../migrations/storage.php',
    ...require __DIR__ . '/../migrations/files.php',
    ...require __DIR__ . '/../migrations/file-lifecycle.php',
    ...require __DIR__ . '/../migrations/file-versions.php',
    ...require __DIR__ . '/../migrations/storage-locks.php',
    ...require __DIR__ . '/../migrations/storage-active-names.php',
    ...require __DIR__ . '/../migrations/media.php',
    ...require __DIR__ . '/../migrations/exports.php',
    ...require __DIR__ . '/../migrations/catalog.php',
    ...require __DIR__ . '/../migrations/personal-spaces.php',
    ...require __DIR__ . '/../migrations/media-cache-indexes.php',
    ...require __DIR__ . '/../plugins/rbac/migrations.php',
];
