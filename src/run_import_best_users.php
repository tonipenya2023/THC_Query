<?php

declare(strict_types=1);

require_once __DIR__ . '/BestImporter.php';

$config = require __DIR__ . '/config.php';

$onlyPlayer = null;
$onlyUserId = null;
$seasonNo = 0;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--player=')) {
        $onlyPlayer = substr($arg, 9);
        continue;
    }

    if (str_starts_with($arg, '--user-id=')) {
        $onlyUserId = (int) substr($arg, 10);
        continue;
    }

    if (str_starts_with($arg, '--season=')) {
        $seasonNo = (int) substr($arg, 9);
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

$sql = 'SELECT user_id, player_name FROM gpt.tab_usuarios';
$params = [];
$where = [];

if ($onlyPlayer !== null && $onlyPlayer !== '') {
    $where[] = 'player_name = :player_name';
    $params[':player_name'] = $onlyPlayer;
}

if ($onlyUserId !== null && $onlyUserId > 0) {
    $where[] = 'user_id = :user_id';
    $params[':user_id'] = $onlyUserId;
}

if ($where !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY player_name, user_id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

if ($users === []) {
    fwrite(STDERR, "No hay usuarios que cumplan el filtro.\n");
    exit(1);
}

$importer = new BestImporter($config);
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'best_import_errors.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$processedUsers = 0;
$processedRows = 0;
$errors = 0;

foreach ($users as $user) {
    $userId = (int) $user['user_id'];
    $playerName = (string) ($user['player_name'] ?? '');

    try {
        $count = $importer->importUser($userId, $seasonNo);
        $processedUsers++;
        $processedRows += $count;
        fwrite(STDOUT, "[OK] {$playerName} ({$userId}): {$count}\n");
    } catch (Throwable $e) {
        $errors++;
        $message = "[ERROR] {$playerName} ({$userId}): {$e->getMessage()}\n";
        fwrite(STDERR, $message);
        file_put_contents($logFile, $message, FILE_APPEND);
    }
}

fwrite(STDOUT, "\nResumen\n");
fwrite(STDOUT, "Usuarios procesados: {$processedUsers}\n");
fwrite(STDOUT, "Filas importadas: {$processedRows}\n");
fwrite(STDOUT, "Errores: {$errors}\n");
