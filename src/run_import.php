<?php

declare(strict_types=1);

require_once __DIR__ . '/Importer.php';

$config = require __DIR__ . '/config.php';

$userId = isset($argv[1]) ? (int) $argv[1] : 0;
$expeditionId = isset($argv[2]) ? (int) $argv[2] : 0;

if ($userId <= 0 || $expeditionId <= 0) {
    fwrite(STDERR, "Uso: php run_import.php <user_id> <expedition_id>\n");
    exit(1);
}

try {
    $importer = new Importer($config);
    $importer->importExpedition($userId, $expeditionId);
    fwrite(STDOUT, "Importación completada para expedition_id={$expeditionId}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
