<?php

declare(strict_types=1);

require_once __DIR__ . '/Importer.php';

$config = require __DIR__ . '/config.php';

$userId = isset($argv[1]) ? (int) $argv[1] : 0;
$pageSize = isset($argv[2]) ? (int) $argv[2] : 40;
$force = in_array('--force', $argv, true);

if ($userId <= 0) {
    fwrite(STDERR, "Uso: php run_import_user.php <user_id> [page_size] [--force]\n");
    exit(1);
}

if ($pageSize <= 0) {
    $pageSize = 40;
}

$importer = new Importer($config);
$offset = 0;
$processed = 0;
$imported = 0;
$skipped = 0;
$errors = 0;
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'import_user_errors.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

while (true) {
    try {
        $page = $importer->fetchExpeditionListPage($userId, $offset, $pageSize);
    } catch (Throwable $e) {
        $message = "[ERROR] pagina offset={$offset}: {$e->getMessage()}\n";
        fwrite(STDERR, $message);
        file_put_contents($logFile, $message, FILE_APPEND);
        $errors++;
        break;
    }

    if ($page === []) {
        break;
    }

    foreach ($page as $item) {
        $expeditionId = isset($item['id']) ? (int) $item['id'] : 0;

        if ($expeditionId <= 0) {
            $errors++;
            $message = "[ERROR] offset={$offset}: expedition_id invalido\n";
            fwrite(STDERR, $message);
            file_put_contents($logFile, $message, FILE_APPEND);
            continue;
        }

        if (!$force && $importer->expeditionExists($expeditionId)) {
            $skipped++;
            fwrite(STDOUT, "[SKIP] {$expeditionId} ya existe\n");
            continue;
        }

        try {
            $importer->importExpedition($userId, $expeditionId);
            $processed++;
            $imported++;
            fwrite(STDOUT, "[OK] {$expeditionId}\n");
        } catch (Throwable $e) {
            $processed++;
            $errors++;
            $message = "[ERROR] {$expeditionId}: {$e->getMessage()}\n";
            fwrite(STDERR, $message);
            file_put_contents($logFile, $message, FILE_APPEND);
        }
    }

    if (count($page) < $pageSize) {
        break;
    }

    $offset += $pageSize;
}

fwrite(STDOUT, "\nResumen\n");
fwrite(STDOUT, "Importadas: {$imported}\n");
fwrite(STDOUT, "Saltadas por ya existente: {$skipped}\n");
fwrite(STDOUT, "Errores: {$errors}\n");
