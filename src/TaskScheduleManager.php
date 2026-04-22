<?php

declare(strict_types=1);

require_once __DIR__ . '/web_bootstrap.php';
require_once __DIR__ . '/TaskManager.php';
require_once __DIR__ . '/TaskCatalog.php';

final class TaskScheduleManager
{
    private const FILE_NAME = 'task_schedules.json';
    private const DEFAULT_INTERVAL_MIN = 180;
    private const RUN_EVERY_SEC = 60;

    /**
     * @return array<string,array{enabled:bool,interval_min:int}>
     */
    public static function load(): array
    {
        $file = self::filePath();
        $stored = [];
        if (is_file($file)) {
            $raw = (string) @file_get_contents($file);
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $stored = $json;
            }
        }

        $defaults = self::defaults();
        foreach ($defaults as $action => $cfg) {
            $row = $stored[$action] ?? null;
            if (!is_array($row)) {
                $stored[$action] = $cfg;
                continue;
            }
            $stored[$action] = [
                'enabled' => (bool) ($row['enabled'] ?? $cfg['enabled']),
                'interval_min' => max(1, (int) ($row['interval_min'] ?? $cfg['interval_min'])),
            ];
        }

        return $stored;
    }

    /**
     * @param array<string,array{enabled:bool,interval_min:int}> $map
     */
    public static function save(array $map): void
    {
        $out = [];
        $defaults = self::defaults();
        foreach ($defaults as $action => $cfg) {
            $row = $map[$action] ?? $cfg;
            $out[$action] = [
                'enabled' => (bool) ($row['enabled'] ?? false),
                'interval_min' => max(1, (int) ($row['interval_min'] ?? self::DEFAULT_INTERVAL_MIN)),
            ];
        }

        @file_put_contents(self::filePath(), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function updateAction(string $action, bool $enabled, int $intervalMin): bool
    {
        $defs = self::defaults();
        if (!isset($defs[$action])) {
            return false;
        }

        $all = self::load();
        $all[$action] = [
            'enabled' => $enabled,
            'interval_min' => max(1, $intervalMin),
        ];
        self::save($all);
        return true;
    }

    /**
     * @return array<string,array{label:string,enabled:bool,interval_min:int}>
     */
    public static function forPanel(): array
    {
        $catalog = TaskCatalog::all();
        $cfg = self::load();
        $out = [];
        foreach (self::importActions() as $action) {
            if (!isset($catalog[$action])) {
                continue;
            }
            $out[$action] = [
                'label' => (string) ($catalog[$action]['label'] ?? $action),
                'enabled' => (bool) ($cfg[$action]['enabled'] ?? false),
                'interval_min' => (int) ($cfg[$action]['interval_min'] ?? self::DEFAULT_INTERVAL_MIN),
            ];
        }
        return $out;
    }

    public static function runDueTasks(bool $isAdmin, ?string $authUser): void
    {
        if (!self::acquireTickLock()) {
            return;
        }

        $catalog = TaskCatalog::all();
        $cfg = self::load();
        $history = TaskManager::list(500);
        $allowedForAll = TaskCatalog::nonAdminRunnableActions();

        foreach (self::importActions() as $action) {
            $row = $cfg[$action] ?? null;
            if (!is_array($row) || !(bool) ($row['enabled'] ?? false)) {
                continue;
            }
            if (!isset($catalog[$action])) {
                continue;
            }
            if (!$isAdmin && !in_array($action, $allowedForAll, true)) {
                continue;
            }
            if (self::hasQueuedOrRunning($history, $action)) {
                continue;
            }

            $intervalSec = max(60, ((int) ($row['interval_min'] ?? self::DEFAULT_INTERVAL_MIN)) * 60);
            $lastTs = self::lastExecutionTs($history, $action);
            if ($lastTs !== null && (time() - $lastTs) < $intervalSec) {
                continue;
            }

            $commandOverride = null;
            if ($action === 'refresh_my_expeditions') {
                $user = is_string($authUser) ? trim($authUser) : '';
                $uid = $user !== '' ? app_player_user_id($user) : null;
                if ($uid === null || $uid <= 0) {
                    continue;
                }
                $php = 'C:\\xampp\\php\\php.exe';
                $commandOverride = [$php, app_root() . '\\src\\run_import_user.php', (string) $uid, '40'];
            }

            if ($action === 'join_all_competitions') {
                $user = is_string($authUser) ? trim($authUser) : '';
                if ($user === '') {
                    continue;
                }
                $php = 'C:\\xampp\\php\\php.exe';
                $commandOverride = [$php, app_root() . '\\src\\run_join_all_competitions.php', '--player=' . $user, '--skip-attempted'];
            }

            $taskId = TaskManager::create($action, (string) $catalog[$action]['label'], $commandOverride);
            TaskManager::runAsync($taskId);
        }
    }

    private static function acquireTickLock(): bool
    {
        $file = app_root() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'task_schedule_tick.json';
        $now = time();
        $last = 0;
        if (is_file($file)) {
            $raw = (string) @file_get_contents($file);
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $last = (int) ($json['ts'] ?? 0);
            }
        }
        if ($last > 0 && ($now - $last) < self::RUN_EVERY_SEC) {
            return false;
        }
        @file_put_contents($file, json_encode(['ts' => $now], JSON_PRETTY_PRINT));
        return true;
    }

    /**
     * @param array<int,array<string,mixed>> $history
     */
    private static function hasQueuedOrRunning(array $history, string $action): bool
    {
        foreach ($history as $task) {
            if ((string) ($task['action'] ?? '') !== $action) {
                continue;
            }
            $status = (string) ($task['status'] ?? '');
            if (in_array($status, ['queued', 'running'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $history
     */
    private static function lastExecutionTs(array $history, string $action): ?int
    {
        foreach ($history as $task) {
            if ((string) ($task['action'] ?? '') !== $action) {
                continue;
            }
            $raw = (string) ($task['finished_at'] ?? $task['started_at'] ?? $task['created_at'] ?? '');
            if ($raw === '') {
                continue;
            }
            $ts = strtotime($raw);
            if ($ts !== false) {
                return $ts;
            }
        }
        return null;
    }

    /**
     * @return array<string,array{enabled:bool,interval_min:int}>
     */
    private static function defaults(): array
    {
        $defaults = [];
        foreach (self::importActions() as $action) {
            $defaults[$action] = [
                'enabled' => in_array($action, ['refresh_competitions', 'refresh_my_expeditions', 'refresh_public_all'], true),
                'interval_min' => self::DEFAULT_INTERVAL_MIN,
            ];
        }
        return $defaults;
    }

    /**
     * @return array<int,string>
     */
    private static function importActions(): array
    {
        $catalog = TaskCatalog::all();
        $out = [];
        foreach (array_keys($catalog) as $action) {
            if (str_starts_with((string) $action, 'refresh_') || $action === 'join_all_competitions') {
                $out[] = (string) $action;
            }
        }
        sort($out);
        return $out;
    }

    private static function filePath(): string
    {
        return app_root() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . self::FILE_NAME;
    }
}





