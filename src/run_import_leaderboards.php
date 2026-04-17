<?php

declare(strict_types=1);

require_once __DIR__ . '/GlobalLeaderboardsImporter.php';

$config = require __DIR__ . '/config.php';

$types = ['score', 'range'];
$limit = 100;
$onlySpeciesId = null;
$speciesSource = 'auto';

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--type=')) {
        $raw = strtolower(trim(substr($arg, 7)));
        if (in_array($raw, ['score', 'range'], true)) {
            $types = [$raw];
        } elseif ($raw === 'both') {
            $types = ['score', 'range'];
        }
    }

    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(500, (int) substr($arg, 8)));
    }

    if (str_starts_with($arg, '--species-id=')) {
        $candidate = (int) substr($arg, 13);
        if ($candidate > 0) {
            $onlySpeciesId = $candidate;
        }
    }

    if (str_starts_with($arg, '--species-source=')) {
        $candidate = strtolower(trim(substr($arg, 17)));
        if (in_array($candidate, ['auto', 'summary', 'table'], true)) {
            $speciesSource = $candidate;
        }
    }
}

try {
    $importer = new GlobalLeaderboardsImporter($config);
    $result = $importer->importAll($types, $limit, $onlySpeciesId, $speciesSource);

    fwrite(STDOUT, "Import leaderboard completado\n");
    fwrite(STDOUT, "Snapshot: {$result['snapshot_at']}\n");
    fwrite(STDOUT, "Especies: {$result['species']}\n");
    fwrite(STDOUT, "Origen especies: {$speciesSource}\n");
    fwrite(STDOUT, "Filas score: {$result['score_rows']}\n");
    fwrite(STDOUT, "Filas range: {$result['range_rows']}\n");
    fwrite(STDOUT, "Errores por especie: {$result['errors']}\n");
    if (!empty($result['error_samples']) && is_array($result['error_samples'])) {
        fwrite(STDOUT, "Muestras de error:\n");
        foreach ($result['error_samples'] as $line) {
            fwrite(STDOUT, " - {$line}\n");
        }
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
