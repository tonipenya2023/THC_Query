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

$row = $pdo->query('SELECT COUNT(*) AS c FROM gpt.ui_preferences')->fetch();
echo 'count=' . (string) ($row['c'] ?? '0') . PHP_EOL;

foreach ($pdo->query("SELECT pref_key, LEFT(pref_value::text, 200) AS sample FROM gpt.ui_preferences ORDER BY pref_key LIMIT 50") as $pref) {
    echo (string) $pref['pref_key'] . ' => ' . (string) $pref['sample'] . PHP_EOL;
}
