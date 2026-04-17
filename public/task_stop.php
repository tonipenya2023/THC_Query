<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';
require_once dirname(__DIR__) . '/src/TaskManager.php';

app_require_panel_auth();
app_start_session();

$id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
$redirect = is_string($_POST['redirect'] ?? null) ? $_POST['redirect'] : 'index.php';

if (!app_validate_csrf($csrfToken)) {
    header('Location: index.php?flash=' . urlencode('CSRF invalido'));
    exit;
}

$task = TaskManager::requestCancel($id);
if ($task === null) {
    header('Location: index.php?flash=' . urlencode('Tarea no encontrada'));
    exit;
}

$pid = isset($task['process_pid']) ? (int) $task['process_pid'] : 0;
if ($pid > 0) {
    exec('taskkill /PID ' . $pid . ' /T /F >NUL 2>&1');
} else {
    $taskIdArg = escapeshellarg($id);
    $ps = '$taskId=' . $taskIdArg . '; '
        . "Get-CimInstance Win32_Process -Filter \"Name = 'php.exe'\" | "
        . 'Where-Object { $_.CommandLine -like "*task_runner.php*" -and $_.CommandLine -like ("*" + $taskId + "*") } | '
        . 'ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }';
    exec('powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($ps) . ' >NUL 2>&1');
}

$taskAfter = TaskManager::read($id);
if ($taskAfter !== null && in_array((string) ($taskAfter['status'] ?? ''), ['queued', 'running'], true)) {
    $taskAfter['status'] = 'canceled';
    $taskAfter['finished_at'] = date(DATE_ATOM);
    $taskAfter['exit_code'] = 130;
    $taskAfter['process_pid'] = null;
    TaskManager::write($taskAfter);
}

$redirectTarget = str_starts_with($redirect, 'task_view.php') ? $redirect : 'index.php';
$message = 'Interrupcion solicitada para: ' . (string) ($task['label'] ?? $id);
$separator = str_contains($redirectTarget, '?') ? '&' : '?';
header('Location: ' . $redirectTarget . $separator . 'flash=' . urlencode($message));
exit;
