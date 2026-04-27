<?php

declare(strict_types=1);

require_once __DIR__ . '/web_bootstrap.php';

final class TaskManager
{
    private const TASK_ID_PATTERN = '/^\d{8}_\d{6}_[a-f0-9]{8}$/';

    public static function create(string $action, string $label, ?array $command = null): string
    {
        $id = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $logFile = self::taskLogPath($id, $label, $action);
        $task = [
            'id' => $id,
            'action' => $action,
            'label' => $label,
            'status' => 'queued',
            'created_at' => date(DATE_ATOM),
            'started_at' => null,
            'finished_at' => null,
            'exit_code' => null,
            'log_file' => $logFile,
            'cancel_requested' => false,
            'cancel_requested_at' => null,
            'cancel_reason' => null,
            'process_pid' => null,
        ];
        if (is_array($command) && $command !== []) {
            $task['command'] = array_values(array_map(static fn ($v): string => (string) $v, $command));
        }
        self::write($task);
        return $id;
    }

    public static function read(string $id): ?array
    {
        if (!self::isValidTaskId($id)) {
            return null;
        }

        $file = self::taskJsonPath($id);
        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return null;
        }

        if (($data['id'] ?? null) !== $id || !self::isValidTaskId((string) ($data['id'] ?? ''))) {
            return null;
        }

        $label = (string) ($data['label'] ?? '');
        $action = (string) ($data['action'] ?? '');
        $expectedLogFile = self::taskLogPath($id, $label, $action);
        $storedLogFile = is_string($data['log_file'] ?? null) ? (string) $data['log_file'] : '';
        if ($storedLogFile !== '' && is_file($storedLogFile)) {
            $data['log_file'] = $storedLogFile;
        } else {
            $data['log_file'] = $expectedLogFile;
        }
        $data['cancel_requested'] = (bool) ($data['cancel_requested'] ?? false);
        $data['cancel_requested_at'] = $data['cancel_requested_at'] ?? null;
        $data['cancel_reason'] = $data['cancel_reason'] ?? null;
        $data['process_pid'] = isset($data['process_pid']) ? (int) $data['process_pid'] : null;
        return $data;
    }

    public static function write(array $task): void
    {
        $id = (string) ($task['id'] ?? '');
        if (!self::isValidTaskId($id)) {
            throw new InvalidArgumentException('Task ID invalido.');
        }

        $task['id'] = $id;
        $task['log_file'] = self::taskLogPath(
            $id,
            (string) ($task['label'] ?? ''),
            (string) ($task['action'] ?? '')
        );

        $file = self::taskJsonPath($id);
        file_put_contents($file, json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function list(int $limit = 30): array
    {
        self::recoverStaleTasks();

        $files = glob(tasks_dir() . DIRECTORY_SEPARATOR . '*.json') ?: [];
        rsort($files);
        $tasks = [];
        foreach (array_slice($files, 0, $limit) as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                $tasks[] = $data;
            }
        }
        return $tasks;
    }

    public static function requestCancel(string $id, string $reason = 'Solicitud de usuario'): ?array
    {
        $task = self::read($id);
        if ($task === null) {
            return null;
        }

        $status = (string) ($task['status'] ?? '');
        if (in_array($status, ['done', 'error', 'canceled'], true)) {
            return $task;
        }

        $task['cancel_requested'] = true;
        $task['cancel_requested_at'] = date(DATE_ATOM);
        $task['cancel_reason'] = $reason;
        if ($status === 'queued') {
            $task['status'] = 'canceled';
            $task['finished_at'] = date(DATE_ATOM);
            $task['exit_code'] = 130;
            $task['process_pid'] = null;
        }

        self::write($task);
        return $task;
    }

    public static function setProcessPid(string $id, ?int $pid): void
    {
        $task = self::read($id);
        if ($task === null) {
            return;
        }
        $task['process_pid'] = $pid;
        self::write($task);
    }

    public static function runAsync(string $id): void
    {
        if (!self::isValidTaskId($id)) {
            throw new InvalidArgumentException('Task ID invalido.');
        }

        $php = 'C:\\xampp\\php\\php.exe';
        $launcher = app_root() . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'task_runner.php';
        $cmd = 'cmd /c start "" /B "' . $php . '" "' . $launcher . '" "' . $id . '"';
        pclose(popen($cmd, 'r'));
    }

    public static function taskLogPath(string $id, string $label = '', string $action = ''): string
    {
        if (!self::isValidTaskId($id)) {
            throw new InvalidArgumentException('Task ID invalido.');
        }

        $base = self::taskLogBasename($label, $action);
        return task_logs_dir() . DIRECTORY_SEPARATOR . $base . '__' . $id . '.log';
    }

    private static function taskLogBasename(string $label, string $action): string
    {
        $source = trim($label) !== '' ? $label : $action;
        $source = trim(app_normalize_display_text($source));
        if ($source === '') {
            return 'task';
        }

        $source = mb_strtolower($source, 'UTF-8');
        $source = preg_replace('/[^a-z0-9]+/i', '_', $source) ?? $source;
        $source = trim($source, '_');

        return $source !== '' ? $source : 'task';
    }

    private static function taskJsonPath(string $id): string
    {
        if (!self::isValidTaskId($id)) {
            throw new InvalidArgumentException('Task ID invalido.');
        }

        return tasks_dir() . DIRECTORY_SEPARATOR . $id . '.json';
    }

    /**
     * Recorre todas las tareas en estado 'running' o 'queued' y marca como 'error'
     * aquellas cuyo proceso ya no existe en el sistema o que llevan demasiado tiempo
     * sin actualizar su estado (más de 2 horas).
     */
    public static function recoverStaleTasks(): void
    {
        $files = glob(tasks_dir() . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $staleThresholdSec = 7200; // 2 horas sin actualización → tarea zombi

        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }

            $status = (string) ($data['status'] ?? '');
            if (!in_array($status, ['running', 'queued'], true)) {
                continue;
            }

            $isStale = false;

            // Comprobar si el PID registrado sigue vivo
            $pid = isset($data['process_pid']) ? (int) $data['process_pid'] : null;
            if ($pid !== null && $pid > 0) {
                if (!self::isProcessAlive($pid)) {
                    $isStale = true;
                }
            }

            // Comprobar antigüedad del archivo como red de seguridad
            // (cubre tareas sin PID o cuyo runner nunca llegó a registrarlo)
            if (!$isStale) {
                $refTime = $data['started_at'] ?? $data['created_at'] ?? null;
                if ($refTime !== null) {
                    $ts = strtotime((string) $refTime);
                    if ($ts !== false && (time() - $ts) > $staleThresholdSec) {
                        $isStale = true;
                    }
                }
            }

            if (!$isStale) {
                continue;
            }

            $data['status'] = 'error';
            $data['finished_at'] = $data['finished_at'] ?? date(DATE_ATOM);
            $data['exit_code'] = $data['exit_code'] ?? -1;
            $data['cancel_reason'] = $data['cancel_reason'] ?? 'Proceso terminado inesperadamente (recuperación automática)';

            $id = (string) ($data['id'] ?? '');
            if (self::isValidTaskId($id)) {
                self::write($data);
            }
        }
    }

    /**
     * Comprueba si un PID sigue activo en Windows usando tasklist.
     * Devuelve false si el proceso no existe o si no se puede determinar.
     */
    private static function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        // Windows: tasklist /FI filtra por PID; si devuelve el PID en la salida, el proceso existe
        $output = [];
        exec('tasklist /FI "PID eq ' . $pid . '" /NH /FO CSV 2>NUL', $output);
        foreach ($output as $line) {
            // tasklist devuelve líneas CSV como "php.exe","1234",...
            if (str_contains($line, '"' . $pid . '"')) {
                return true;
            }
        }
        return false;
    }

    private static function isValidTaskId(string $id): bool
    {
        return preg_match(self::TASK_ID_PATTERN, $id) === 1;
    }
}
