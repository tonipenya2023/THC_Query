<?php
declare(strict_types=1);

$config = require dirname(__DIR__) . '/src/config.php';
$pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$stmt = $pdo->prepare("DELETE FROM gpt.ui_preferences WHERE pref_key = 'thc_table_column_widths_v1'");
$stmt->execute();
echo 'deleted=' . (string) $stmt->rowCount() . PHP_EOL;
