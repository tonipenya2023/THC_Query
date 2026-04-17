<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';
require_once dirname(__DIR__) . '/src/TaskManager.php';
require_once dirname(__DIR__) . '/src/TaskCatalog.php';

app_require_panel_auth();
app_start_session();

$action = is_string($_POST['action'] ?? null) ? trim($_POST['action']) : '';
$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;

if (!app_validate_csrf($csrfToken)) {
    header('Location: index.php?flash=' . urlencode('CSRF inválido'));
    exit;
}

$actions = TaskCatalog::all();
if (!isset($actions[$action])) {
    header('Location: index.php?flash=' . urlencode('Acción no válida'));
    exit;
}

$isAdmin = app_is_admin_user();
if (!TaskCatalog::canRunAction($action, $isAdmin)) {
    header('Location: index.php?flash=' . urlencode('No tienes permiso para ejecutar esa tarea'));
    exit;
}

foreach (TaskManager::list(500) as $task) {
    if ((string) ($task['action'] ?? '') !== $action) {
        continue;
    }
    $status = (string) ($task['status'] ?? '');
    if (in_array($status, ['queued', 'running'], true)) {
        header('Location: index.php?flash=' . urlencode('Esta tarea ya está en ejecución'));
        exit;
    }
}

$commandOverride = null;
if ($action === 'refresh_my_expeditions') {
    $authUser = app_auth_username() ?? '';
    $userId = app_player_user_id($authUser);
    if ($userId === null || $userId <= 0) {
        header('Location: index.php?flash=' . urlencode('No se encontró IdUsuario para el usuario actual'));
        exit;
    }
    $php = 'C:\\xampp\\php\\php.exe';
    $commandOverride = [$php, app_root() . '\\src\\run_import_user.php', (string) $userId, '40'];
}

$taskId = TaskManager::create($action, (string) $actions[$action]['label'], $commandOverride);
TaskManager::runAsync($taskId);

header('Location: index.php?flash=' . urlencode('Tarea lanzada: ' . (string) $actions[$action]['label']));
exit;
