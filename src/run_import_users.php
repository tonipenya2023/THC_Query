<?php

declare(strict_types=1);

require_once __DIR__ . '/Importer.php';

$config = require __DIR__ . '/config.php';

$pageSize = 40;
$force = in_array('--force', $argv, true);
$onlyPlayer = null;
$onlyUserId = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--player=')) {
        $onlyPlayer = substr($arg, 9);
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

$importer = new Importer($config);
$logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
$logFile = $logDir . DIRECTORY_SEPARATOR . 'import_users_errors.log';

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

$usersProcessed = 0;
$usersWithErrors = 0;
$expImported = 0;
$expSkipped = 0;
$expErrors = 0;

foreach ($users as $user) {
    $userId = (int) $user['user_id'];
    $playerName = (string) ($user['player_name'] ?? '');
    $offset = 0;

    fwrite(STDOUT, "\nUsuario {$playerName} ({$userId})\n");

    while (true) {
        try {
            $page = $importer->fetchExpeditionListPage($userId, $offset, $pageSize);
        } catch (Throwable $e) {
            $message = "[ERROR] usuario={$userId} offset={$offset}: {$e->getMessage()}\n";
            fwrite(STDERR, $message);
            file_put_contents($logFile, $message, FILE_APPEND);
            $usersWithErrors++;
            $expErrors++;
            break;
        }

        if ($page === []) {
            break;
        }

        foreach ($page as $item) {
            $expeditionId = isset($item['id']) ? (int) $item['id'] : 0;

            if ($expeditionId <= 0) {
                $message = "[ERROR] usuario={$userId} offset={$offset}: expedition_id invalido\n";
                fwrite(STDERR, $message);
                file_put_contents($logFile, $message, FILE_APPEND);
                $expErrors++;
                continue;
            }

            if (!$force && $importer->expeditionExists($expeditionId)) {
                fwrite(STDOUT, "[SKIP EXP] {$expeditionId} ya existe\n");
                $expSkipped++;
                continue;
            }

            try {
                $importer->importExpedition($userId, $expeditionId);
                $expImported++;
                fwrite(STDOUT, "[OK] {$expeditionId}\n");
            } catch (Throwable $e) {
                $message = "[ERROR] usuario={$userId} expedition_id={$expeditionId}: {$e->getMessage()}\n";
                fwrite(STDERR, $message);
                file_put_contents($logFile, $message, FILE_APPEND);
                $expErrors++;
            }
        }

        if (count($page) < $pageSize) {
            break;
        }

        $offset += $pageSize;
    }

    $usersProcessed++;
}

fwrite(STDOUT, "\nResumen global\n");
fwrite(STDOUT, "Usuarios procesados: {$usersProcessed}\n");
fwrite(STDOUT, "Usuarios con error de pagina: {$usersWithErrors}\n");
fwrite(STDOUT, "Expediciones importadas: {$expImported}\n");
fwrite(STDOUT, "Expediciones saltadas por ya existente: {$expSkipped}\n");
fwrite(STDOUT, "Errores de expedicion: {$expErrors}\n");
