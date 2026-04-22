<?php

declare(strict_types=1);

app_enable_html_output_normalization();

function app_env(string $name): ?string
{
    $value = getenv($name);
    if ($value === false) {
        return null;
    }

    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

function app_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

function app_pdo(): PDO
{
    static $pdo;
    if ($pdo === null) {
        $config = app_config();
        $pdo = new PDO(
            $config['db']['dsn'],
            $config['db']['user'],
            $config['db']['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }
    return $pdo;
}

function app_root(): string
{
    return dirname(__DIR__);
}

function logs_dir(): string
{
    $dir = app_root() . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function task_logs_dir(): string
{
    $dir = logs_dir() . DIRECTORY_SEPARATOR . 'tasks';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function sessions_dir(): string
{
    $dir = logs_dir() . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function tasks_dir(): string
{
    $dir = app_root() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'tasks';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir;
}

function app_migrate_runtime_artifacts(): void
{
    $root = app_root();
    $legacyVarDir = $root . DIRECTORY_SEPARATOR . 'var';
    if (!is_dir($legacyVarDir)) {
        return;
    }

    $moves = [
        [$legacyVarDir . DIRECTORY_SEPARATOR . '*.log', logs_dir()],
        [$legacyVarDir . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR . '*.log', task_logs_dir()],
        [$legacyVarDir . DIRECTORY_SEPARATOR . 'sess_*', sessions_dir()],
    ];

    foreach ($moves as [$pattern, $targetDir]) {
        $matches = glob($pattern) ?: [];
        foreach ($matches as $source) {
            if (!is_file($source)) {
                continue;
            }

            $target = $targetDir . DIRECTORY_SEPARATOR . basename($source);
            if (realpath($source) === realpath($target)) {
                continue;
            }

            if (!is_file($target)) {
                @rename($source, $target);
                continue;
            }

            @unlink($source);
        }
    }
}

function h(?string $value): string
{
    $normalized = app_normalize_display_text((string) ($value ?? ''));
    return htmlspecialchars($normalized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_fix_text_encoding(string $value): string
{
    if ($value === '') {
        return '';
    }

    $best = $value;
    $bestScore = app_mojibake_score($best);

    $candidate = @iconv('UTF-8', 'Windows-1252//IGNORE', $value);
    if (is_string($candidate) && $candidate !== '') {
        $score = app_mojibake_score($candidate);
        if ($score < $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    if ($bestScore > 0) {
        $candidate = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $best);
        if (is_string($candidate) && $candidate !== '') {
            $score = app_mojibake_score($candidate);
            if ($score < $bestScore) {
                $best = $candidate;
            }
        }
    }

    return $best;
}

function app_mojibake_score(string $value): int
{
    if ($value === '') {
        return 0;
    }

    $score = 0;
    $patterns = [
        '/\x{00C2}/u',
        '/\x{00C3}/u',
        '/\x{00C6}/u',
        '/\x{00E2}/u',
        '/\x{0192}/u',
        '/\x{20AC}/u',
        '/\x{2122}/u',
        '/A(?:\x{0192}|\x{00A2}|\x{20AC}|\x{2122}|\x{00A1}|\x{00BF}|\x{00BA}|\x{00B1}|\x{00B3}|\x{00A9})/u',
    ];
    foreach ($patterns as $pattern) {
        $count = preg_match_all($pattern, $value, $matches);
        if ($count === false) {
            continue;
        }
        if ($count > 0) {
            $score += count($matches[0]);
        }
    }

    return $score;
}

function app_strip_vowel_accents(string $value): string
{
    if ($value === '') {
        return '';
    }

    $map = [
        "\xC3\x81" => 'A', // A
        "\xC3\x80" => 'A', // A
        "\xC3\x82" => 'A', // A
        "\xC3\x84" => 'A', // A
        "\xC3\x83" => 'A', // A
        "\xC3\xA1" => 'a', // a
        "\xC3\xA0" => 'a', // a
        "\xC3\xA2" => 'a', // a
        "\xC3\xA4" => 'a', // a
        "\xC3\xA3" => 'a', // a
        "\xC3\x89" => 'E', // E
        "\xC3\x88" => 'E', // E
        "\xC3\x8A" => 'E', // E
        "\xC3\x8B" => 'E', // E
        "\xC3\xA9" => 'e', // e
        "\xC3\xA8" => 'e', // e
        "\xC3\xAA" => 'e', // e
        "\xC3\xAB" => 'e', // e
        "\xC3\x8D" => 'I', // I
        "\xC3\x8C" => 'I', // I
        "\xC3\x8E" => 'I', // I
        "\xC3\x8F" => 'I', // I
        "\xC3\xAD" => 'i', // i
        "\xC3\xAC" => 'i', // i
        "\xC3\xAE" => 'i', // i
        "\xC3\xAF" => 'i', // i
        "\xC3\x93" => 'O', // O
        "\xC3\x92" => 'O', // O
        "\xC3\x94" => 'O', // O
        "\xC3\x96" => 'O', // O
        "\xC3\x95" => 'O', // O
        "\xC3\xB3" => 'o', // o
        "\xC3\xB2" => 'o', // o
        "\xC3\xB4" => 'o', // o
        "\xC3\xB6" => 'o', // o
        "\xC3\xB5" => 'o', // o
        "\xC3\x9A" => 'U', // U
        "\xC3\x99" => 'U', // U
        "\xC3\x9B" => 'U', // U
        "\xC3\x9C" => 'U', // U
        "\xC3\xBA" => 'u', // u
        "\xC3\xB9" => 'u', // u
        "\xC3\xBB" => 'u', // u
        "\xC3\xBC" => 'u', // u
        "\xC3\x91" => 'N', // N
        "\xC3\xB1" => 'n', // n
        "\xC3\x87" => 'C', // C
        "\xC3\xA7" => 'c', // c
    ];

    return strtr($value, $map);
}

function app_strip_accent_entities(string $value): string
{
    if ($value === '') {
        return '';
    }

    $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $map = [
        '&Aacute;' => 'A', '&aacute;' => 'a',
        '&Eacute;' => 'E', '&eacute;' => 'e',
        '&Iacute;' => 'I', '&iacute;' => 'i',
        '&Oacute;' => 'O', '&oacute;' => 'o',
        '&Uacute;' => 'U', '&uacute;' => 'u',
        '&Ntilde;' => 'N', '&ntilde;' => 'n',
        '&Uuml;' => 'U', '&uuml;' => 'u',
    ];

    return strtr($decoded, $map);
}

function app_strip_mojibake_artifacts(string $value): string
{
    if ($value === '') {
        return '';
    }

    $value = preg_replace(
        '/A(?:\x{0192}|\x{00A2}|\x{20AC}|\x{2122}|\x{00A1}|\x{00BF}|\x{00BA}|\x{00B1}|\x{00B3}|\x{00A9}|\x{00AD}|\x{2018}|\x{2019}|\x{201C}|\x{201D}|\x{2020}|\x{2021}|\x{2039}|\x{203A})/u',
        '',
        $value
    ) ?? $value;

    $value = preg_replace(
        '/[\x{00C2}\x{00C3}\x{00C6}\x{00E2}\x{0192}\x{20AC}\x{2122}\x{201A}\x{201E}\x{2026}\x{02C6}\x{2030}\x{0160}\x{2039}\x{0152}\x{017D}\x{2018}\x{2019}\x{201C}\x{201D}\x{2022}\x{2013}\x{2014}\x{02DC}\x{0161}\x{203A}\x{0153}\x{017E}\x{0178}]/u',
        '',
        $value
    ) ?? $value;

    return $value;
}

function app_normalize_display_text(string $value): string
{
    if ($value === '') {
        return '';
    }

    $value = str_replace(["\xEF\xBB\xBF", "\0"], '', $value);
    $value = app_fix_text_encoding($value);
    $value = app_strip_accent_entities($value);
    $value = app_strip_vowel_accents($value);
    $value = app_strip_mojibake_artifacts($value);
    $value = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '', $value) ?? '';
    $value = preg_replace('/ {2,}/', ' ', $value) ?? $value;

    return $value;
}

function app_enable_html_output_normalization(): void
{
    return;
}
function app_query_all(string $sql, array $params = []): array
{
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function app_query_one(string $sql, array $params = []): ?array
{
    $stmt = app_pdo()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    app_migrate_runtime_artifacts();
    session_save_path(sessions_dir());
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function app_csrf_token(): string
{
    app_start_session();

    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function app_validate_csrf(?string $token): bool
{
    app_start_session();

    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? null;
    return is_string($sessionToken) && hash_equals($sessionToken, $token);
}

function app_require_panel_auth(): void
{
    $expectedUser = app_env('THC_PANEL_USER');
    $expectedPass = app_env('THC_PANEL_PASS');
    $defaultUsersPass = app_env('THC_PANEL_DEFAULT_PASS') ?? 'thcgpt';

    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? null;
    $providedPass = $_SERVER['PHP_AUTH_PW'] ?? null;

    if (!is_string($providedUser) || !is_string($providedPass)) {
        header('WWW-Authenticate: Basic realm="THC Query Panel"');
        http_response_code(401);
        echo 'Acceso no autorizado';
        exit;
    }

    $providedUser = trim($providedUser);
    $providedPass = (string) $providedPass;

    $storedHash = app_password_hash_for_user($providedUser);
    $storedAllowed = is_string($storedHash) && $storedHash !== '' && password_verify($providedPass, $storedHash);

    $adminAllowed = is_string($expectedUser)
        && is_string($expectedPass)
        && hash_equals($expectedUser, $providedUser)
        && hash_equals($expectedPass, $providedPass);

    $playerAllowed = hash_equals($defaultUsersPass, $providedPass) && app_panel_player_exists($providedUser);

    if ($storedAllowed || $adminAllowed || $playerAllowed) {
        return;
    }

    header('WWW-Authenticate: Basic realm="THC Query Panel"');
    http_response_code(401);
    echo 'Acceso no autorizado';
    exit;
}

function app_password_store_file(): string
{
    return app_root() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'panel_passwords.json';
}

function app_password_store_load(): array
{
    $file = app_password_store_file();
    if (!is_file($file)) {
        return [];
    }

    $raw = @file_get_contents($file);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }

    $out = [];
    foreach ($data as $user => $hash) {
        if (!is_string($user) || !is_string($hash)) {
            continue;
        }
        $u = trim($user);
        $h = trim($hash);
        if ($u === '' || $h === '') {
            continue;
        }
        $out[mb_strtolower($u, 'UTF-8')] = $h;
    }

    return $out;
}

function app_password_store_save(array $map): void
{
    $file = app_password_store_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function app_password_hash_for_user(string $username): ?string
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    $map = app_password_store_load();
    $key = mb_strtolower($username, 'UTF-8');
    return isset($map[$key]) && is_string($map[$key]) ? $map[$key] : null;
}

function app_verify_login_password(string $username, string $password): bool
{
    if ($username === '' || $password === '') {
        return false;
    }

    $storedHash = app_password_hash_for_user($username);
    if (is_string($storedHash) && $storedHash !== '' && password_verify($password, $storedHash)) {
        return true;
    }

    $expectedUser = app_env('THC_PANEL_USER');
    $expectedPass = app_env('THC_PANEL_PASS');
    if (is_string($expectedUser) && is_string($expectedPass) && hash_equals($expectedUser, $username) && hash_equals($expectedPass, $password)) {
        return true;
    }

    $defaultUsersPass = app_env('THC_PANEL_DEFAULT_PASS') ?? 'thcgpt';
    if (app_panel_player_exists($username) && hash_equals($defaultUsersPass, $password)) {
        return true;
    }

    return false;
}

function app_set_user_password(string $username, string $newPassword): void
{
    $username = trim($username);
    if ($username === '') {
        throw new InvalidArgumentException('Usuario vacio');
    }
    if ($newPassword === '') {
        throw new InvalidArgumentException('Contrasena vacia');
    }

    $map = app_password_store_load();
    $map[mb_strtolower($username, 'UTF-8')] = password_hash($newPassword, PASSWORD_DEFAULT);
    app_password_store_save($map);
}

function app_auth_username(): ?string
{
    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? null;
    if (!is_string($providedUser)) {
        return null;
    }
    $providedUser = trim($providedUser);
    return $providedUser === '' ? null : $providedUser;
}

function app_is_admin_user(): bool
{
    $expectedUser = app_env('THC_PANEL_USER');
    $providedUser = app_auth_username();
    if ($expectedUser === null || $providedUser === null) {
        return false;
    }
    return hash_equals($expectedUser, $providedUser);
}

function app_player_user_id(string $playerName): ?int
{
    if ($playerName === '') {
        return null;
    }

    $queries = [
        'SELECT user_id FROM gpt.tab_usuarios WHERE LOWER(player_name) = LOWER(:player_name) LIMIT 1',
        'SELECT user_id FROM gpt.users WHERE LOWER(player_name) = LOWER(:player_name) LIMIT 1',
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = app_pdo()->prepare($sql);
            $stmt->execute([':player_name' => $playerName]);
            $value = $stmt->fetchColumn();
            if ($value !== false && is_numeric((string) $value)) {
                return (int) $value;
            }
        } catch (Throwable) {
            continue;
        }
    }

    return null;
}

function app_panel_player_exists(string $playerName): bool
{
    if ($playerName === '') {
        return false;
    }

    $queries = [
        'SELECT 1 FROM gpt.tab_usuarios WHERE LOWER(player_name) = LOWER(:player_name) LIMIT 1',
        'SELECT 1 FROM users WHERE LOWER(player_name) = LOWER(:player_name) LIMIT 1',
        'SELECT 1 FROM gpt.users_public WHERE LOWER(player_name) = LOWER(:player_name) LIMIT 1',
        'SELECT 1 FROM gpt.user_public_stats WHERE LOWER(player_name) = LOWER(:player_name) LIMIT 1',
        'SELECT 1 FROM gpt.est_profiles WHERE LOWER(player_name) = LOWER(:player_name) LIMIT 1',
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = app_pdo()->prepare($sql);
            $stmt->execute([':player_name' => $playerName]);
            if ($stmt->fetchColumn() !== false) {
                return true;
            }
        } catch (Throwable) {
            continue;
        }
    }

    return false;
}

function app_theme(): string
{
    static $theme = null;
    if ($theme !== null) {
        return $theme;
    }

    $allowed = ['sober', 'gaming', 'arctic', 'graphite', 'midnight', 'ember', 'skyline', 'terminal', 'missions'];
    $fromGet = $_GET['theme'] ?? null;
    if (is_string($fromGet) && in_array($fromGet, $allowed, true)) {
        $theme = $fromGet;
        setcookie('thc_theme', $theme, [
            'expires' => time() + 31536000,
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => false,
        ]);
        return $theme;
    }

    $fromCookie = $_COOKIE['thc_theme'] ?? null;
    if (is_string($fromCookie) && in_array($fromCookie, $allowed, true)) {
        $theme = $fromCookie;
        return $theme;
    }

    $theme = 'sober';
    return $theme;
}

function app_font(): string
{
    static $font = null;
    if ($font !== null) {
        return $font;
    }

    $allowed = ['system', 'modern', 'classic', 'serif', 'mono'];
    $fromGet = $_GET['font'] ?? null;
    if (is_string($fromGet) && in_array($fromGet, $allowed, true)) {
        $font = $fromGet;
        setcookie('thc_font', $font, [
            'expires' => time() + 31536000,
            'path' => '/',
            'samesite' => 'Lax',
            'httponly' => false,
        ]);
        return $font;
    }

    $fromCookie = $_COOKIE['thc_font'] ?? null;
    if (is_string($fromCookie) && in_array($fromCookie, $allowed, true)) {
        $font = $fromCookie;
        return $font;
    }

    $font = 'system';
    return $font;
}

