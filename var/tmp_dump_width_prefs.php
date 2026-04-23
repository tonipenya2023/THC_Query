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

$sql = "SELECT pref_key, LEFT(pref_value::text, 500) AS sample
        FROM gpt.ui_preferences
        WHERE pref_key LIKE 'thc_table_column_widths_v1%'
           OR pref_key = 'thc_table_column_widths_v1'
        ORDER BY pref_key";

foreach ($pdo->query($sql) as $row) {
    echo (string) $row['pref_key'] . PHP_EOL;
    echo (string) $row['sample'] . PHP_EOL . PHP_EOL;
}
