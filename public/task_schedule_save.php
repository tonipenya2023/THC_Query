<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';
require_once dirname(__DIR__) . '/src/TaskScheduleManager.php';

app_require_panel_auth();
app_start_session();

if (!app_is_admin_user()) {
    header('Location: index.php?flash=' . urlencode('Solo admin puede modificar tareas programadas'));
    exit;
}

$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
if (!app_validate_csrf($csrfToken)) {
    header('Location: index.php?flash=' . urlencode('CSRF invalido'));
    exit;
}

$action = is_string($_POST['action'] ?? null) ? trim($_POST['action']) : '';
$enabled = isset($_POST['enabled']) && (string) $_POST['enabled'] === '1';
$intervalMin = (int) ($_POST['interval_min'] ?? 180);
$intervalMin = max(1, min(10080, $intervalMin));

$ok = TaskScheduleManager::updateAction($action, $enabled, $intervalMin);
if (!$ok) {
    header('Location: index.php?flash=' . urlencode('Tarea programada no valida'));
    exit;
}

header('Location: index.php?flash=' . urlencode('Tarea programada actualizada'));
exit;
