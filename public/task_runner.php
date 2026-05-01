<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';
require_once dirname(__DIR__) . '/src/TaskManager.php';
require_once dirname(__DIR__) . '/src/TaskCatalog.php';

$id = is_string($argv[1] ?? null) ? $argv[1] : '';
$task = TaskManager::read($id);
if ($task === null) {
    exit(1);
}

$action = is_string($task['action'] ?? null) ? $task['action'] : '';
$catalog = TaskCatalog::all();
if (!isset($catalog[$action]) && !is_array($task['command'] ?? null)) {
    $task['status'] = 'error';
    $task['finished_at'] = date(DATE_ATOM);
    $task['exit_code'] = 1;
    TaskManager::write($task);
    exit(1);
}

$task['status'] = 'running';
$task['started_at'] = date(DATE_ATOM);
$task['process_pid'] = null;
TaskManager::write($task);

$task = TaskManager::read($id) ?? $task;
if ((bool) ($task['cancel_requested'] ?? false)) {
    $task['status'] = 'canceled';
    $task['finished_at'] = date(DATE_ATOM);
    $task['exit_code'] = 130;
    $task['process_pid'] = null;
    TaskManager::write($task);
    exit(130);
}

$commandRaw = is_array($task['command'] ?? null) && ($task['command'] ?? []) !== []
    ? $task['command']
    : ($catalog[$action]['command'] ?? []);

if (!is_array($commandRaw) || $commandRaw === []) {
    $task['status'] = 'error';
    $task['finished_at'] = date(DATE_ATOM);
    $task['exit_code'] = 1;
    $task['process_pid'] = null;
    TaskManager::write($task);
    exit(1);
}

$command = array_map(
    static fn (string $part): string => '"' . str_replace('"', '\"', $part) . '"',
    array_map(static fn ($v): string => (string) $v, $commandRaw)
);
$cmd = implode(' ', $command);
$logFile = TaskManager::taskLogPath(
    $id,
    (string) ($task['label'] ?? ''),
    (string) ($task['action'] ?? '')
);
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['file', $logFile, 'a'],
    2 => ['file', $logFile, 'a'],
];
$process = proc_open($cmd, $descriptors, $pipes, app_root());

if (!is_resource($process)) {
    $task['status'] = 'error';
    $task['finished_at'] = date(DATE_ATOM);
    $task['exit_code'] = 1;
    $task['process_pid'] = null;
    TaskManager::write($task);
    exit(1);
}

if (isset($pipes[0]) && is_resource($pipes[0])) {
    fclose($pipes[0]);
}

$status = proc_get_status($process);
$childPid = isset($status['pid']) ? (int) $status['pid'] : null;
$runnerPid = function_exists('getmypid') ? getmypid() : null;
$pid = is_int($runnerPid) && $runnerPid > 0 ? $runnerPid : $childPid;
TaskManager::setProcessPid($id, $pid);

$exitCode = 1;
$wasCanceled = false;
while (true) {
    $status = proc_get_status($process);
    if (!(bool) ($status['running'] ?? false)) {
        $exitCode = (int) ($status['exitcode'] ?? 1);
        break;
    }

    $latestTask = TaskManager::read($id);
    if ($latestTask !== null && (bool) ($latestTask['cancel_requested'] ?? false)) {
        $wasCanceled = true;
        if ($childPid !== null && $childPid > 0) {
            exec('taskkill /PID ' . $childPid . ' /T /F >NUL 2>&1');
        }
        @proc_terminate($process);
        break;
    }

    usleep(250000);
}

$closeCode = proc_close($process);
if (!$wasCanceled && $closeCode >= 0) {
    $exitCode = $closeCode;
}

$task = TaskManager::read($id) ?? $task;
$task['status'] = $wasCanceled ? 'canceled' : ($exitCode === 0 ? 'done' : 'error');
$task['finished_at'] = date(DATE_ATOM);
$task['exit_code'] = $exitCode;
$task['process_pid'] = null;
TaskManager::write($task);

exit($wasCanceled ? 130 : $exitCode);
