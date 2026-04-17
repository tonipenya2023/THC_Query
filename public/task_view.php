<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';
require_once dirname(__DIR__) . '/src/TaskManager.php';
require_once dirname(__DIR__) . '/src/TaskCatalog.php';

app_require_panel_auth();
$theme = app_theme();
$flash = is_string($_GET['flash'] ?? null) ? $_GET['flash'] : null;

$id = $_GET['id'] ?? '';
$task = TaskManager::read($id);
if ($task === null) {
    http_response_code(404);
    echo 'Tarea no encontrada';
    exit;
}

$log = is_file($task['log_file']) ? (string) file_get_contents($task['log_file']) : '';
$action = is_string($task['action'] ?? null) ? $task['action'] : '';
$catalog = TaskCatalog::all();
$command = isset($catalog[$action]) ? implode(' ', $catalog[$action]['command']) : '(accion no disponible)';
$cssVersion = (string) @filemtime(__DIR__ . '/style.css');
$isInterruptible = in_array((string) ($task['status'] ?? ''), ['queued', 'running'], true);
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarea <?= h($task['label']) ?></title>
    <link rel="stylesheet" href="style.css?v=<?= h($cssVersion) ?>">
</head>
<body class="task-page theme-<?= h($theme) ?>">
    <p><a href="index.php?theme=<?= h($theme) ?>">Volver</a></p>
    <?php if ($flash): ?>
        <div class="flash"><?= h($flash) ?></div>
    <?php endif; ?>
    <h1><?= h($task['label']) ?></h1>
    <p>Estado: <strong><?= h($task['status']) ?></strong></p>
    <?php if ($isInterruptible): ?>
        <form method="post" action="task_stop.php" class="task-stop-form">
            <input type="hidden" name="csrf_token" value="<?= h(app_csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= h((string) $task['id']) ?>">
            <input type="hidden" name="redirect" value="task_view.php?id=<?= urlencode((string) $task['id']) ?>">
            <button type="submit" class="stop-btn">Interrumpir proceso</button>
        </form>
    <?php endif; ?>
    <p>Creada: <?= h((string) $task['created_at']) ?></p>
    <p>Inicio: <?= h((string) $task['started_at']) ?></p>
    <p>Fin: <?= h((string) $task['finished_at']) ?></p>
    <h2>Comando</h2>
    <pre><?= h($command) ?></pre>
    <h2>Log</h2>
    <pre><?= h($log) ?></pre>
</body>
</html>
