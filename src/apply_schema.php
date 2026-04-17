<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
$ddlDir = dirname(__DIR__) . '/ddl';
$sqlFiles = glob($ddlDir . '/*.sql');
if ($sqlFiles === false || $sqlFiles === []) {
    fwrite(STDERR, "No hay ficheros SQL en: {$ddlDir}\n");
    exit(1);
}
sort($sqlFiles, SORT_NATURAL | SORT_FLAG_CASE);

try {
    $pdo = new PDO(
        $config['db']['dsn'],
        $config['db']['user'],
        $config['db']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]
    );

    foreach ($sqlFiles as $sqlFile) {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException("No se pudo leer el fichero SQL: {$sqlFile}");
        }
        $pdo->exec($sql);
    }

    fwrite(STDOUT, "Esquema y vistas aplicados correctamente.\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Error aplicando esquema: {$e->getMessage()}\n");
    exit(1);
}
