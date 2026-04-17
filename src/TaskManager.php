<?php

declare(strict_types=1);

require_once __DIR__ . '/web_bootstrap.php';

final class TaskManager
{
    private const TASK_ID_PATTERN = '/^\d{8}_\d{6}_[a-f0-9]{8}$/';

    public static function create(string $action, string $label, ?array $command = null): string
    {
        $id = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $task = [
            'id' => $id,
            'action' => $action,
            'label' => $label,
            'status' => 'queued',
            'created_at' => date(DATE_ATOM),
            'started_at' => null,
            'finished_at' => null,
            'exit_code' => null,
            'log_file' => self::taskLogPath($id),
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

        $data['log_file'] = self::taskLogPath($id);
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
        $task['log_file'] = self::taskLogPath($id);

        $file = self::taskJsonPath($id);
        file_put_contents($file, json_encode($task, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function list(int $limit = 30): array
    {
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

    public static function taskLogPath(string $id): string
    {
        if (!self::isValidTaskId($id)) {
            throw new InvalidArgumentException('Task ID invalido.');
        }

        return task_logs_dir() . DIRECTORY_SEPARATOR . $id . '.log';
    }

    private static function taskJsonPath(string $id): string
    {
        if (!self::isValidTaskId($id)) {
            throw new InvalidArgumentException('Task ID invalido.');
        }

        return tasks_dir() . DIRECTORY_SEPARATOR . $id . '.json';
    }

    private static function isValidTaskId(string $id): bool
    {
        return preg_match(self::TASK_ID_PATTERN, $id) === 1;
    }
}
