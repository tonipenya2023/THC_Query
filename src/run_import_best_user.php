<?php

declare(strict_types=1);

require_once __DIR__ . '/BestImporter.php';

$config = require __DIR__ . '/config.php';

$userId = isset($argv[1]) ? (int) $argv[1] : 0;
$seasonNo = isset($argv[2]) ? (int) $argv[2] : 0;

if ($userId <= 0) {
    fwrite(STDERR, "Uso: php run_import_best_user.php <user_id> [season_no]\n");
    exit(1);
}

try {
    $importer = new BestImporter($config);
    $count = $importer->importUser($userId, $seasonNo);
    fwrite(STDOUT, "Mejores marcas importadas para user_id={$userId}: {$count}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
