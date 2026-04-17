<?php

declare(strict_types=1);

require_once __DIR__ . '/UserPublicStatsImporter.php';

$config = require __DIR__ . '/config.php';

$hostname = $argv[1] ?? '';

if (trim($hostname) === '') {
    fwrite(STDERR, "Uso: php run_import_user_public_stats.php <hostname>\n");
    exit(1);
}

try {
    $importer = new UserPublicStatsImporter($config);
    $importer->importByHostname($hostname);
    fwrite(STDOUT, "Perfil publico importado para {$hostname}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
