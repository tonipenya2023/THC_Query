<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$expeditionId = isset($argv[1]) ? (int) $argv[1] : 0;

if ($expeditionId <= 0) {
    fwrite(STDERR, "Uso: php verify_import.php <expedition_id>\n");
    exit(1);
}

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $tables = [
        'gpt.exp_expeditions' => 'expedition_id',
        'gpt.exp_stats' => 'expedition_id',
        'gpt.exp_animal_stats' => 'expedition_id',
        'gpt.exp_weapon_stats' => 'expedition_id',
        'gpt.exp_collectables' => 'expedition_id',
        'gpt.exp_antler_collectables' => 'expedition_id',
        'gpt.exp_kills' => 'expedition_id',
        'gpt.exp_hits' => 'expedition_id',
        'gpt.exp_payloads' => 'expedition_id',
    ];

    foreach ($tables as $table => $column) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = :expedition_id");
        $stmt->execute([':expedition_id' => $expeditionId]);
        $count = $stmt->fetchColumn();
        fwrite(STDOUT, "{$table}: {$count}\n");
    }

    $stmt = $pdo->prepare(
        'SELECT expedition_id, user_id, reserve_id, map_id, start_ts, end_ts
         FROM gpt.exp_expeditions
         WHERE expedition_id = :expedition_id'
    );
    $stmt->execute([':expedition_id' => $expeditionId]);
    $row = $stmt->fetch();

    if ($row !== false) {
        fwrite(STDOUT, "\nCabecera de expedición:\n");
        foreach ($row as $key => $value) {
            fwrite(STDOUT, "{$key}: " . ($value === null ? 'NULL' : (string) $value) . "\n");
        }
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error verificando importación: {$e->getMessage()}\n");
    exit(1);
}
