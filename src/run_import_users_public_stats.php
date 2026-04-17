<?php

declare(strict_types=1);

require_once __DIR__ . '/UserPublicStatsImporter.php';

$config = require __DIR__ . '/config.php';
$onlyPlayer = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--player=')) {
        $onlyPlayer = substr($arg, 9);
    }
}

$pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$sql = 'SELECT player_name FROM gpt.tab_usuarios';
$params = [];
if ($onlyPlayer !== null && $onlyPlayer !== '') {
    $sql .= ' WHERE player_name = :player_name';
    $params[':player_name'] = $onlyPlayer;
}
$sql .= ' ORDER BY player_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$players = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($players === []) {
    fwrite(STDERR, "No hay jugadores que cumplan el filtro.\n");
    exit(1);
}

$importer = new UserPublicStatsImporter($config);
$ok = 0;
$errors = 0;
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'user_public_stats_errors.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

foreach ($players as $player) {
    try {
        $importer->importByHostname((string) $player);
        $ok++;
        fwrite(STDOUT, "[OK] {$player}\n");
    } catch (Throwable $e) {
        $errors++;
        $message = "[ERROR] {$player}: {$e->getMessage()}\n";
        fwrite(STDERR, $message);
        file_put_contents($logFile, $message, FILE_APPEND);
    }
}

fwrite(STDOUT, "\nResumen\n");
fwrite(STDOUT, "OK: {$ok}\n");
fwrite(STDOUT, "Errores: {$errors}\n");
