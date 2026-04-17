<?php

declare(strict_types=1);

require_once __DIR__ . '/CompetitionImporter.php';

$config = require __DIR__ . '/config.php';

try {
    $importer = new CompetitionImporter($config);
    $count = $importer->importAll();
    fwrite(STDOUT, "Competiciones importadas: {$count}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
