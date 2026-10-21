<?php
declare(strict_types=1);

// Trusted composition root: plugins contribute public SchemaMigration values.
return [
    ...require __DIR__ . '/../migrations/identity.php',
    ...require __DIR__ . '/../migrations/administration.php',
    ...require __DIR__ . '/../migrations/storage.php',
    ...require __DIR__ . '/../migrations/files.php',
    ...require __DIR__ . '/../migrations/file-lifecycle.php',
    ...require __DIR__ . '/../plugins/rbac/migrations.php',
];
