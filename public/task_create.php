<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';
require_once dirname(__DIR__) . '/src/TaskManager.php';
require_once dirname(__DIR__) . '/src/TaskCatalog.php';
require_once dirname(__DIR__) . '/src/TaskScheduleManager.php';

app_require_panel_auth();
app_start_session();

$action = is_string($_POST['action'] ?? null) ? trim($_POST['action']) : '';
$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;

if (!app_validate_csrf($csrfToken)) {
    header('Location: index.php?flash=' . urlencode('CSRF invalido'));
    exit;
}

$actions = TaskCatalog::all();
if (!isset($actions[$action])) {
    header('Location: index.php?flash=' . urlencode('Accion no valida'));
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
        header('Location: index.php?flash=' . urlencode('Esta tarea ya esta en ejecucion'));
        exit;
    }
}

$commandOverride = null;
$scheduleConfig = TaskScheduleManager::load();
if ($action === 'refresh_my_expeditions') {
    $authUser = app_auth_username() ?? '';
    $userId = app_player_user_id($authUser);
    if ($userId === null || $userId <= 0) {
        header('Location: index.php?flash=' . urlencode('No se encontro IdUsuario para el usuario actual'));
        exit;
    }
    $php = 'C:\\xampp\\php\\php.exe';
    $commandOverride = [$php, app_root() . '\\src\\run_import_user.php', (string) $userId, '40'];
}

if ($action === 'join_all_competitions') {
    $authUser = app_auth_username() ?? '';
    if ($authUser === '') {
        header('Location: index.php?flash=' . urlencode('No se encontro el usuario autenticado actual'));
        exit;
    }
    $php = 'C:\\xampp\\php\\php.exe';
    $commandOverride = [$php, app_root() . '\\src\\run_join_all_competitions.php', '--player=' . $authUser, '--skip-attempted'];
}

if ($action === 'scrape_kill_details') {
    $authUser = trim((string) (app_auth_username() ?? ''));
    $configuredPlayer = trim((string) (($scheduleConfig[$action]['player'] ?? '') ?: ''));
    $player = $configuredPlayer !== '' ? $configuredPlayer : $authUser;
    if ($player === '') {
        header('Location: index.php?flash=' . urlencode('No se encontro jugador configurado para el scraper de detalle de muertes'));
        exit;
    }
    $php = 'C:\\xampp\\php\\php.exe';
    $commandOverride = [$php, app_root() . '\\src\\run_scrape_kill_details.php', '--player=' . $player, '--cookie-player=' . $player, '--pending-only'];
}

$taskId = TaskManager::create($action, (string) $actions[$action]['label'], $commandOverride);
TaskManager::runAsync($taskId);

header('Location: index.php?flash=' . urlencode('Tarea lanzada: ' . (string) $actions[$action]['label']));
exit;
