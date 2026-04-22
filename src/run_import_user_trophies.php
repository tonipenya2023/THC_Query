<?php

declare(strict_types=1);

require_once __DIR__ . '/TrophyImporter.php';

$config = require __DIR__ . '/config.php';

$userId = isset($argv[1]) ? (int) $argv[1] : 0;
$pageSize = isset($argv[2]) ? (int) $argv[2] : 24;

if ($userId <= 0) {
    fwrite(STDERR, "Uso: php run_import_user_trophies.php <user_id> [page_size]\n");
    exit(1);
}

$importer = new TrophyImporter($config);
$count = $importer->importUser($userId, null, $pageSize);

fwrite(STDOUT, "Trofeos importados: {$count}\n");
