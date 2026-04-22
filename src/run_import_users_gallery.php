<?php

declare(strict_types=1);

require_once __DIR__ . '/GalleryImporter.php';

$config = require __DIR__ . '/config.php';

$pageSize = 24;
$onlyPlayer = null;
$onlyUserId = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--player=')) {
        $onlyPlayer = trim(substr($arg, 9));
        continue;
    }

    if (str_starts_with($arg, '--user-id=')) {
        $onlyUserId = (int) substr($arg, 10);
        continue;
    }

    if (str_starts_with($arg, '--page-size=')) {
        $pageSize = max(1, (int) substr($arg, 12));
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

$importer = new GalleryImporter($config);
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'import_users_gallery_errors.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$usersProcessed = 0;
$usersWithErrors = 0;
$photosImported = 0;

foreach ($users as $user) {
    $userId = (int) ($user['user_id'] ?? 0);
    $playerName = trim((string) ($user['player_name'] ?? ''));

    if ($userId <= 0 || $playerName === '') {
        continue;
    }

    fwrite(STDOUT, "\nUsuario {$playerName} ({$userId})\n");

    try {
        $count = $importer->importUser($userId, $playerName, $pageSize);
        $photosImported += $count;
        fwrite(STDOUT, "[OK] {$count} imagenes\n");
    } catch (Throwable $e) {
        $usersWithErrors++;
        $message = "[ERROR] usuario={$userId} player={$playerName}: {$e->getMessage()}\n";
        fwrite(STDERR, $message);
        file_put_contents($logFile, $message, FILE_APPEND);
    }

    $usersProcessed++;
}

fwrite(STDOUT, "\nResumen global\n");
fwrite(STDOUT, "Usuarios procesados: {$usersProcessed}\n");
fwrite(STDOUT, "Usuarios con error: {$usersWithErrors}\n");
fwrite(STDOUT, "Imagenes importadas: {$photosImported}\n");
