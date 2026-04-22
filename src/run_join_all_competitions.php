<?php

declare(strict_types=1);

require_once __DIR__ . '/CompetitionJoiner.php';

$config = require __DIR__ . '/config.php';

$playerName = '';
$skipAttempted = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with((string) $arg, '--player=')) {
        $playerName = trim((string) substr((string) $arg, 9));
        continue;
    }
    if ((string) $arg === '--skip-attempted') {
        $skipAttempted = true;
    }
}

if ($playerName === '' && isset($argv[1]) && !str_starts_with((string) $argv[1], '--')) {
    $playerName = trim((string) $argv[1]);
}

if ($playerName === '') {
    fwrite(STDERR, "Error: Debes indicar el jugador con --player=nombre\n");
    exit(1);
}

try {
    $joiner = new CompetitionJoiner($config);
    $result = $joiner->joinAllAvailable($playerName, $skipAttempted);

    fwrite(STDOUT, "Jugador: {$result['player']}\n");
    fwrite(STDOUT, "Competiciones revisadas: {$result['total']}\n");
    fwrite(STDOUT, "Altas nuevas: {$result['joined']}\n");
    fwrite(STDOUT, "Ya inscritas: {$result['already']}\n");
    fwrite(STDOUT, "Fallidas: {$result['failed']}\n");

    foreach ($result['details'] as $row) {
        fwrite(
            STDOUT,
            '[' . (string) ($row['status'] ?? '') . '] '
            . (string) ($row['competition_id'] ?? '') . ' '
            . (string) ($row['competition_name'] ?? '') . ' ('
            . (string) ($row['method'] ?? '') . ' '
            . (string) ($row['param'] ?? '') . ')' . "\n"
        );
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}
