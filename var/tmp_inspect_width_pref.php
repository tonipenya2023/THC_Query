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

$sql = "SELECT pref_key, pref_value::text AS value_text
        FROM gpt.ui_preferences
        WHERE pref_key = 'thc_table_column_widths_v1'";
$rows = $pdo->query($sql)->fetchAll();

echo 'rows=' . count($rows) . PHP_EOL;
foreach ($rows as $idx => $row) {
    echo 'row#' . ($idx + 1) . PHP_EOL;
    echo (string) $row['pref_key'] . PHP_EOL;
    echo (string) $row['value_text'] . PHP_EOL . PHP_EOL;
}
