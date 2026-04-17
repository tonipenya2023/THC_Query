<?php

declare(strict_types=1);

require_once __DIR__ . '/Importer.php';

$config = require __DIR__ . '/config.php';

$csvPath = $argv[1] ?? '';
$force = in_array('--force', $argv, true);

if ($csvPath === '') {
    fwrite(STDERR, "Uso: php run_import_csv.php <csv_path> [--force]\n");
    exit(1);
}

if (!is_file($csvPath)) {
    $relativePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $csvPath;
    if (is_file($relativePath)) {
        $csvPath = $relativePath;
    }
}

if (!is_file($csvPath)) {
    fwrite(STDERR, "No existe el CSV: {$csvPath}\n");
    exit(1);
}

$handle = fopen($csvPath, 'r');

if ($handle === false) {
    fwrite(STDERR, "No se pudo abrir el CSV: {$csvPath}\n");
    exit(1);
}

$header = fgetcsv($handle);

if ($header === false) {
    fclose($handle);
    fwrite(STDERR, "El CSV esta vacio.\n");
    exit(1);
}

$normalizedHeader = array_map(
    static fn ($value): string => strtolower(trim((string) $value)),
    $header
);

if ($normalizedHeader !== ['user_id', 'expedition_id']) {
    fclose($handle);
    fwrite(STDERR, "Cabecera no valida. Se esperaba: user_id,expedition_id\n");
    exit(1);
}

$importer = new Importer($config);
$processed = 0;
$imported = 0;
$skipped = 0;
$errors = 0;
$lineNumber = 1;
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'import_errors.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

while (($row = fgetcsv($handle)) !== false) {
    $lineNumber++;

    if (count($row) < 2) {
        if (count(array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
            continue;
        }

        $errors++;
        fwrite(STDERR, "[Linea {$lineNumber}] Faltan columnas\n");
        file_put_contents($logFile, "[Linea {$lineNumber}] Faltan columnas\n", FILE_APPEND);
        continue;
    }

    $userId = (int) trim((string) $row[0]);
    $expeditionId = (int) trim((string) $row[1]);

    if ($userId <= 0 || $expeditionId <= 0) {
        $errors++;
        fwrite(STDERR, "[Linea {$lineNumber}] Datos invalidos\n");
        file_put_contents($logFile, "[Linea {$lineNumber}] Datos invalidos\n", FILE_APPEND);
        continue;
    }

    $processed++;

    if (!$force && $importer->expeditionExists($expeditionId)) {
        $skipped++;
        fwrite(STDOUT, "[SKIP] {$expeditionId} ya existe\n");
        continue;
    }

    try {
        $importer->importExpedition($userId, $expeditionId);
        $imported++;
        fwrite(STDOUT, "[OK] {$expeditionId}\n");
    } catch (Throwable $e) {
        $errors++;
        $message = "[ERROR] {$expeditionId}: {$e->getMessage()}\n";
        fwrite(STDERR, $message);
        file_put_contents($logFile, $message, FILE_APPEND);
    }
}

fclose($handle);

fwrite(STDOUT, "\nResumen\n");
fwrite(STDOUT, "Procesadas: {$processed}\n");
fwrite(STDOUT, "Importadas: {$imported}\n");
fwrite(STDOUT, "Saltadas: {$skipped}\n");
fwrite(STDOUT, "Errores: {$errors}\n");
