<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';
require_once dirname(__DIR__) . '/src/TaskManager.php';
require_once dirname(__DIR__) . '/src/TaskCatalog.php';
require_once dirname(__DIR__) . '/src/TaskScheduleManager.php';

app_require_panel_auth();
app_start_session();

$view = $_GET['view'] ?? 'dashboard';
$flash = $_GET['flash'] ?? null;
$theme = app_theme();
TaskScheduleManager::runDueTasks(app_is_admin_user(), app_auth_username());

function menu_link(string $key, string $label, string $current): string
{
    $class = $key === $current ? 'nav-link active' : 'nav-link';
    return '<a class="' . $class . '" href="?view=' . urlencode($key) . '&theme=' . urlencode(app_theme()) . '">' . h($label) . '</a>';
}

function theme_link(string $themeName, string $label, string $currentView, string $activeTheme): string
{
    $class = $themeName === $activeTheme ? 'theme-chip active' : 'theme-chip';
    return '<a class="' . $class . '" href="?view=' . urlencode($currentView) . '&theme=' . urlencode($themeName) . '">' . h($label) . '</a>';
}

function query_text(string $key): ?string
{
    $value = $_GET[$key] ?? null;
    if (!is_string($value)) {
        return null;
    }

    $value = trim($value);
    return $value === '' ? null : $value;
}

function query_int(string $key): ?int
{
    $value = $_GET[$key] ?? null;
    if ($value === null) {
        return null;
    }

    $raw = trim((string) $value);
    if ($raw === '' || preg_match('/^-?\d+$/', $raw) !== 1) {
        return null;
    }

    return (int) $raw;
}

function query_list(string $key): array
{
    $value = $_GET[$key] ?? null;
    $items = [];

    if (is_array($value)) {
        $items = $value;
    } elseif (is_string($value)) {
        $items = [$value];
    }

    $out = [];
    foreach ($items as $item) {
        if (!is_string($item)) {
            continue;
        }
        $s = trim($item);
        if ($s !== '') {
            $out[$s] = true;
        }
    }

    return array_keys($out);
}

function default_player_filter(array $playerNames, string $paramKey = 'player_name'): array
{
    if ($playerNames !== []) {
        return $playerNames;
    }
    if (array_key_exists($paramKey, $_GET)) {
        return $playerNames;
    }
    if (is_reset_requested() || app_is_admin_user()) {
        return $playerNames;
    }

    // Por defecto no restringir por jugador: mostrar todos.
    return $playerNames;
}

function gender_code_from_value(mixed $genderValue): ?string
{
    $raw = strtolower(trim((string) $genderValue));
    if ($raw === '' || $raw === '-1' || $raw === 'null') {
        return null;
    }
    if (in_array($raw, ['0', 'm', 'male', 'macho'], true)) {
        return 'M';
    }
    if (in_array($raw, ['1', 'f', 'female', 'hembra'], true)) {
        return 'F';
    }
    return null;
}

function species_icon_key(string $value): string
{
    $value = trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
    $patterns = [
        '/[\x{00E1}\x{00E0}\x{00E4}\x{00E2}\x{00E3}]/u' => 'a',
        '/[\x{00E9}\x{00E8}\x{00EB}\x{00EA}]/u' => 'e',
        '/[\x{00ED}\x{00EC}\x{00EF}\x{00EE}]/u' => 'i',
        '/[\x{00F3}\x{00F2}\x{00F6}\x{00F4}\x{00F5}]/u' => 'o',
        '/[\x{00FA}\x{00F9}\x{00FC}\x{00FB}]/u' => 'u',
        '/[\x{00F1}]/u' => 'n',
    ];
    foreach ($patterns as $pattern => $replacement) {
        $value = preg_replace($pattern, $replacement, $value) ?? $value;
    }
    $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;
    return trim($value);
}

function species_icon_map(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }
    $map = [];
    try {
        $rows = app_query_all('SELECT especie, especie_es, icono_m, icono_f FROM gpt.tab_especies');
    } catch (Throwable) {
        $rows = [];
    }
    foreach ($rows as $row) {
        $iconM = trim((string) ($row['icono_m'] ?? ''));
        $iconF = trim((string) ($row['icono_f'] ?? ''));
        if ($iconM === '' && $iconF === '') {
            continue;
        }
        foreach ([(string) ($row['especie_es'] ?? ''), (string) ($row['especie'] ?? '')] as $name) {
            $key = species_icon_key($name);
            if ($key === '') {
                continue;
            }
            $map[$key] = ['m' => $iconM, 'f' => $iconF];
        }
    }
    return $map;
}

function gender_species_icon_html(string $speciesName, mixed $genderValue): string
{
    $key = species_icon_key($speciesName);
    if ($key === '') {
        return '';
    }
    $map = species_icon_map();
    if (!isset($map[$key])) {
        return '';
    }
    $code = gender_code_from_value($genderValue);
    if ($code === null) {
        return '';
    }
    $src = $code === 'M' ? (string) ($map[$key]['m'] ?? '') : (string) ($map[$key]['f'] ?? '');
    if ($src === '') {
        return '';
    }
    $alt = $code === 'M' ? 'Macho' : 'Hembra';
    return '<img class="species-gender-icon" src="' . h($src) . '" alt="' . h($alt) . '" title="' . h($alt) . '" loading="lazy" decoding="async" onerror="this.remove()">';
}

function species_icons_pair_html(string $speciesName): string
{
    $iconM = gender_species_icon_html($speciesName, 'M');
    $iconF = gender_species_icon_html($speciesName, 'F');
    if ($iconM === '' && $iconF === '') {
        return '';
    }
    return '<span class="species-icons-pair"><span class="species-icon-slot">M ' . $iconM . '</span><span class="species-icon-slot">F ' . $iconF . '</span></span>';
}

function query_raw(string $key): string
{
    $value = $_GET[$key] ?? '';
    return is_string($value) ? $value : '';
}

function query_date(string $key): ?string
{
    $value = query_text($key);
    if ($value === null) {
        return null;
    }

    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
}

function query_datetime_local(string $key): ?string
{
    $value = query_text($key);
    if ($value === null) {
        return null;
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function raw_to_datetime_local_value(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable($raw);
        return $dt->format('Y-m-d\TH:i');
    } catch (Throwable) {
        return '';
    }
}

function order_selected_keys(array $selectedKeys, string $orderPrefix): array
{
    $ranked = [];
    foreach (array_values($selectedKeys) as $idx => $key) {
        $raw = query_int($orderPrefix . $key);
        $ranked[] = [
            'key' => $key,
            'order' => $raw !== null ? $raw : ($idx + 1),
            'idx' => $idx,
        ];
    }

    usort(
        $ranked,
        static fn (array $a, array $b): int => ($a['order'] <=> $b['order']) ?: ($a['idx'] <=> $b['idx'])
    );

    return array_map(static fn (array $r): string => (string) $r['key'], $ranked);
}

function persistent_selected_columns(string $sessionKey, array $defs, string $paramPrefix, array $defaultCols): array
{
    $selected = [];
    $hasChoice = false;
    foreach ($defs as $key => $_def) {
        $paramName = $paramPrefix . $key;
        if (!array_key_exists($paramName, $_GET)) {
            continue;
        }
        $hasChoice = true;
        $raw = $_GET[$paramName];
        if ((is_string($raw) && $raw === '1') || $raw === 1) {
            $selected[] = $key;
        }
    }

    if ($hasChoice) {
        if ($selected === []) {
            $selected = array_values(array_filter($defaultCols, static fn (string $k): bool => isset($defs[$k])));
        }
        if ($selected === []) {
            $selected = array_slice(array_keys($defs), 0, 8);
        }
        $_SESSION[$sessionKey] = $selected;
        return $selected;
    }

    $stored = $_SESSION[$sessionKey] ?? null;
    if (is_array($stored)) {
        $stored = array_values(array_filter($stored, static fn ($k): bool => is_string($k) && isset($defs[$k])));
        if ($stored !== []) {
            return $stored;
        }
    }

    $selected = array_values(array_filter($defaultCols, static fn (string $k): bool => isset($defs[$k])));
    if ($selected === []) {
        $selected = array_slice(array_keys($defs), 0, 8);
    }
    $_SESSION[$sessionKey] = $selected;
    return $selected;
}

function query_sort(string $defaultKey, string $defaultDir, array $allowed): array
{
    $key = query_text('sort') ?? $defaultKey;
    $dir = strtolower(query_text('dir') ?? $defaultDir);

    if (!isset($allowed[$key])) {
        $key = $defaultKey;
    }

    if (!in_array($dir, ['asc', 'desc'], true)) {
        $dir = strtolower($defaultDir) === 'asc' ? 'asc' : 'desc';
    }

    return [$key, $dir];
}

function query_sort_param(string $sortParam, string $dirParam, string $defaultKey, string $defaultDir, array $allowed): array
{
    $key = query_text($sortParam) ?? $defaultKey;
    $dir = strtolower(query_text($dirParam) ?? $defaultDir);

    if (!isset($allowed[$key])) {
        $key = $defaultKey;
    }
    if (!in_array($dir, ['asc', 'desc'], true)) {
        $dir = strtolower($defaultDir) === 'asc' ? 'asc' : 'desc';
    }

    return [$key, $dir];
}

function query_page(): int
{
    $page = query_int('page');
    return $page !== null && $page > 0 ? $page : 1;
}

function query_page_size(int $default = 100): int
{
    $raw = query_int('page_size');
    $allowed = [50, 100, 200, 500];

    if ($raw === null || !in_array($raw, $allowed, true)) {
        return $default;
    }

    return $raw;
}

function is_csv_export_requested(): bool
{
    return query_raw('export_csv') === '1';
}

function is_reset_requested(): bool
{
    return query_raw('reset') === '1';
}

function sort_link(string $columnKey, string $label, string $currentKey, string $currentDir): string
{
    $query = $_GET;
    unset($query['export'], $query['export_csv']);
    $query['sort'] = $columnKey;
    $query['dir'] = $currentKey === $columnKey && $currentDir === 'asc' ? 'desc' : 'asc';
    $query['page'] = 1;

    $indicator = '';
    if ($currentKey === $columnKey) {
        $indicator = $currentDir === 'asc' ? ' ^' : ' v';
    }

    return '<a class="th-sort" href="?' . h(http_build_query($query)) . '">' . h($label . $indicator) . '</a>';
}

function sort_link_param(string $sortParam, string $dirParam, string $columnKey, string $label, string $currentKey, string $currentDir): string
{
    $query = $_GET;
    unset($query['export'], $query['export_csv']);
    $query[$sortParam] = $columnKey;
    $query[$dirParam] = $currentKey === $columnKey && $currentDir === 'asc' ? 'desc' : 'asc';
    $query['page'] = 1;

    $indicator = '';
    if ($currentKey === $columnKey) {
        $indicator = $currentDir === 'asc' ? ' ^' : ' v';
    }

    return '<a class="th-sort" href="?' . h(http_build_query($query)) . '">' . h($label . $indicator) . '</a>';
}

function pagination_link(int $targetPage): string
{
    $query = $_GET;
    unset($query['export'], $query['export_csv']);
    $query['page'] = max(1, $targetPage);
    return '?' . h(http_build_query($query));
}

function current_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url((string) $uri, PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

function render_pagination(int $page, int $pageSize, int $totalRows): void
{
    $totalPages = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $totalPages);

    echo '<div class="table-pagination">';
    echo '<span class="pager-info">Pagina ' . h((string) $page) . ' de ' . h((string) $totalPages) . ' (' . h((string) $totalRows) . ' filas)</span>';

    if ($page > 1) {
        echo '<a class="btn-link" href="' . pagination_link($page - 1) . '">Anterior</a>';
    }

    if ($page < $totalPages) {
        echo '<a class="btn-link" href="' . pagination_link($page + 1) . '">Siguiente</a>';
    }

    echo '<form class="pager-jump" method="get">';
    foreach ($_GET as $key => $value) {
        if ($key === 'page') {
            continue;
        }
        if (is_array($value)) {
            continue;
        }
        echo '<input type="hidden" name="' . h((string) $key) . '" value="' . h((string) $value) . '">';
    }
    echo '<label for="page_jump">Pagina</label>';
    echo '<input id="page_jump" type="number" name="page" min="1" max="' . h((string) $totalPages) . '" value="' . h((string) $page) . '">';
    echo '<button type="submit">Ir</button>';
    echo '</form>';

    echo '</div>';
}

function csv_stream(string $filename, array $headers, array $rows, array $columns): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        http_response_code(500);
        echo 'No se pudo generar el CSV.';
        exit;
    }

    fputcsv($out, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $column) {
            $line[] = isset($row[$column]) ? (string) $row[$column] : '';
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

function reserve_name_suggestions(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }

    try {
        $rows = app_query_all(
            "SELECT DISTINCT reserve_name
             FROM gpt.exp_expeditions
             WHERE reserve_name IS NOT NULL AND reserve_name <> ''
             ORDER BY reserve_name"
        );
    } catch (Throwable) {
        $values = [];
        return $values;
    }
    $values = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['reserve_name'] ?? ''));
        if ($name !== '') {
            $values[] = $name;
        }
    }
    return $values;
}

function species_catalog(): array
{
    static $rows = null;
    if ($rows !== null) {
        return $rows;
    }

    try {
        $rows = app_query_all(
            "SELECT id_especie, especie, especie_es
             FROM gpt.tab_especies
             ORDER BY id_especie"
        );
    } catch (Throwable) {
        $rows = [];
    }

    return $rows;
}

function species_suggestions(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }

    $rows = species_catalog();
    $set = [];
    foreach ($rows as $row) {
        $id = trim((string) ($row['id_especie'] ?? ''));
        $es = trim((string) ($row['especie'] ?? ''));
        $esEs = trim((string) ($row['especie_es'] ?? ''));
        if ($id !== '') {
            $set[$id] = true;
        }
        if ($es !== '') {
            $set[$es] = true;
        }
        if ($esEs !== '') {
            $set[$esEs] = true;
        }
    }
    $values = array_map(
        static fn ($v): string => (string) $v,
        array_keys($set)
    );
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    return $values;
}

function species_es_name_suggestions(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }

    $set = [];
    foreach (species_catalog() as $row) {
        $esEs = trim((string) ($row['especie_es'] ?? ''));
        $es = trim((string) ($row['especie'] ?? ''));
        if ($esEs !== '') {
            $set[$esEs] = true;
        } elseif ($es !== '') {
            $set[$es] = true;
        }
    }
    $values = array_keys($set);
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);
    return $values;
}

function species_key_normalized(string $value): string
{
    $value = trim(function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value));
    $value = strtr($value, [
        '?' => 'a', '?' => 'a', '?' => 'a', '?' => 'a',
        '?' => 'e', '?' => 'e', '?' => 'e', '?' => 'e',
        '?' => 'i', '?' => 'i', '?' => 'i', '?' => 'i',
        '?' => 'o', '?' => 'o', '?' => 'o', '?' => 'o',
        '?' => 'u', '?' => 'u', '?' => 'u', '?' => 'u',
        '?' => 'n',
    ]);
    return preg_replace('/\s+/', ' ', $value) ?? $value;
}

function ppft_parse_number(mixed $value): ?float
{
    if ($value === null) {
        return null;
    }
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    $raw = str_replace(',', '.', $raw);
    $raw = preg_replace('/[^0-9.\-]/', '', $raw) ?? '';
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    return (float) $raw;
}

function ppft_thresholds(): array
{
    static $map = null;
    if (is_array($map)) {
        return $map;
    }

    $map = [];
    $sourceTable = null;
    foreach (['gpt.tab_especiesppft', 'gpt.tab_speciesftpp', 'gpt.tab_especiesppft_stg'] as $candidate) {
        try {
            app_query_one('SELECT 1 FROM ' . $candidate . ' LIMIT 1');
            $sourceTable = $candidate;
            break;
        } catch (Throwable) {
            continue;
        }
    }
    if ($sourceTable === null) {
        return $map;
    }

    try {
        $rows = app_query_all('SELECT especie, foto, tax FROM ' . $sourceTable);
    } catch (Throwable) {
        return $map;
    }

    foreach ($rows as $row) {
        $name = trim((string) ($row['especie'] ?? ''));
        if ($name === '') {
            continue;
        }
        $key = species_key_normalized($name);
        if ($key === '') {
            continue;
        }
        $map[$key] = [
            'foto' => ppft_parse_number($row['foto'] ?? null),
            'tax' => ppft_parse_number($row['tax'] ?? null),
        ];
    }

    return $map;
}

function player_name_suggestions(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }

    try {
        $rows = app_query_all(
            "SELECT player_name
             FROM (
                 SELECT player_name FROM gpt.tab_usuarios
                 UNION
                 SELECT player_name FROM gpt.user_public_stats
             ) p
             WHERE player_name IS NOT NULL AND player_name <> ''
             ORDER BY player_name
             LIMIT 5000"
        );
    } catch (Throwable) {
        $values = [];
        return $values;
    }

    $values = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['player_name'] ?? ''));
        if ($name !== '') {
            $values[] = $name;
        }
    }
    return $values;
}

function redirect_to(array $params): void
{
    header('Location: ?' . http_build_query($params));
    exit;
}

function quote_ident(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

function format_datetime_display(mixed $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable((string) $value);
        return $dt->format('d/m/Y H:i:s');
    } catch (Throwable) {
        return (string) $value;
    }
}

function advanced_presets_file(): string
{
    return app_root() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'advanced_presets.json';
}

function advanced_presets_load(): array
{
    $file = advanced_presets_file();
    if (!is_file($file)) {
        return [];
    }

    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        return [];
    }

    $result = [];
    foreach ($data as $name => $preset) {
        if (!is_string($name) || !is_array($preset)) {
            continue;
        }
        $result[$name] = $preset;
    }

    ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
    return $result;
}

function advanced_presets_save(array $presets): void
{
    $file = advanced_presets_file();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    ksort($presets, SORT_NATURAL | SORT_FLAG_CASE);
    file_put_contents($file, json_encode($presets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function advanced_preset_name(?string $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $name = trim($value);
    if ($name === '' || mb_strlen($name) > 60) {
        return null;
    }

    return $name;
}

function gpt_tables(): array
{
    static $tables = null;
    if (is_array($tables)) {
        return $tables;
    }

    $rows = app_query_all(
        "SELECT table_name
         FROM information_schema.tables
         WHERE table_schema = 'gpt' AND table_type = 'BASE TABLE'
         ORDER BY table_name"
    );

    $tables = [];
    foreach ($rows as $row) {
        $name = (string) ($row['table_name'] ?? '');
        if ($name !== '') {
            $tables[] = $name;
        }
    }

    return $tables;
}

function gpt_table_columns(string $table): array
{
    $rows = app_query_all(
        "SELECT column_name, data_type
         FROM information_schema.columns
         WHERE table_schema = 'gpt' AND table_name = :table_name
         ORDER BY ordinal_position",
        [':table_name' => $table]
    );

    $columns = [];
    foreach ($rows as $row) {
        $name = (string) ($row['column_name'] ?? '');
        $type = (string) ($row['data_type'] ?? '');
        if ($name !== '') {
            $columns[$name] = $type;
        }
    }

    return $columns;
}

function expedition_join_column_defs(): array
{
    static $defs = null;
    if ($defs !== null) {
        return $defs;
    }

    $tableMeta = [
        'exp_expeditions' => ['alias' => 'e', 'tag' => 'exp'],
        'exp_kills' => ['alias' => 'k', 'tag' => 'kill'],
        'exp_hits' => ['alias' => 'h', 'tag' => 'hit'],
    ];

    $rows = app_query_all(
        "SELECT table_name, column_name, ordinal_position
         FROM information_schema.columns
         WHERE table_schema = 'gpt'
           AND table_name IN ('exp_expeditions', 'exp_kills', 'exp_hits')
         ORDER BY CASE table_name
             WHEN 'exp_expeditions' THEN 1
             WHEN 'exp_kills' THEN 2
             WHEN 'exp_hits' THEN 3
             ELSE 4
         END, ordinal_position"
    );

    $defs = [];
    foreach ($rows as $row) {
        $tableName = (string) ($row['table_name'] ?? '');
        $columnName = (string) ($row['column_name'] ?? '');
        if ($tableName === '' || $columnName === '' || !isset($tableMeta[$tableName])) {
            continue;
        }

        $alias = $tableMeta[$tableName]['alias'];
        $key = $alias . '_' . $columnName;
        $expr = $alias . '.' . quote_ident($columnName);
        $labelMap = [
            'expedition_id' => 'IdExpedicion',
            'user_id' => 'IdUsuario',
            'species_id' => 'IdEspecie',
            'player_name' => 'Jugador',
            'reserve_id' => 'IdReserva',
            'reserve_name' => 'Reserva',
            'start_at' => 'Inicio',
            'end_at' => 'Fin',
            'score' => 'Puntuacion',
        ];
        $label = $labelMap[$columnName] ?? $columnName;

        $defs[$key] = [
            'expr' => $expr,
            'sort' => $expr,
            'label' => $label,
        ];
    }

    return $defs;
}

function render_advanced(): void
{
    $presets = advanced_presets_load();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $csrf = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
        if (!app_validate_csrf($csrf)) {
            redirect_to([
                'view' => 'advanced',
                'theme' => app_theme(),
                'flash' => 'CSRF invalido',
            ]);
        }

        $action = is_string($_POST['preset_action'] ?? null) ? $_POST['preset_action'] : '';
        $selectedPreset = advanced_preset_name($_POST['preset_name_select'] ?? null);

        if ($action === 'apply' && $selectedPreset !== null && isset($presets[$selectedPreset])) {
            $preset = $presets[$selectedPreset];
            redirect_to([
                'view' => 'advanced',
                'theme' => app_theme(),
                'table' => (string) ($preset['table'] ?? ''),
                'column' => (string) ($preset['column'] ?? ''),
                'op' => (string) ($preset['op'] ?? 'contains'),
                'value' => (string) ($preset['value'] ?? ''),
                'sort' => (string) ($preset['sort'] ?? ''),
                'dir' => (string) ($preset['dir'] ?? 'desc'),
                'page_size' => (string) ($preset['page_size'] ?? '100'),
                'flash' => 'Preset aplicado: ' . $selectedPreset,
            ]);
        }

        if ($action === 'delete' && $selectedPreset !== null && isset($presets[$selectedPreset])) {
            unset($presets[$selectedPreset]);
            advanced_presets_save($presets);
            redirect_to([
                'view' => 'advanced',
                'theme' => app_theme(),
                'flash' => 'Preset eliminado: ' . $selectedPreset,
            ]);
        }

        if ($action === 'save') {
            $newPresetName = advanced_preset_name($_POST['preset_name'] ?? null);
            if ($newPresetName === null) {
                redirect_to([
                    'view' => 'advanced',
                    'theme' => app_theme(),
                    'flash' => 'Nombre de preset invalido',
                ]);
            }

            $presets[$newPresetName] = [
                'table' => (string) ($_POST['curr_table'] ?? ''),
                'column' => (string) ($_POST['curr_column'] ?? ''),
                'op' => (string) ($_POST['curr_op'] ?? 'contains'),
                'value' => (string) ($_POST['curr_value'] ?? ''),
                'sort' => (string) ($_POST['curr_sort'] ?? ''),
                'dir' => (string) ($_POST['curr_dir'] ?? 'desc'),
                'page_size' => (string) ($_POST['curr_page_size'] ?? '100'),
            ];
            advanced_presets_save($presets);
            redirect_to([
                'view' => 'advanced',
                'theme' => app_theme(),
                'table' => (string) ($_POST['curr_table'] ?? ''),
                'column' => (string) ($_POST['curr_column'] ?? ''),
                'op' => (string) ($_POST['curr_op'] ?? 'contains'),
                'value' => (string) ($_POST['curr_value'] ?? ''),
                'sort' => (string) ($_POST['curr_sort'] ?? ''),
                'dir' => (string) ($_POST['curr_dir'] ?? 'desc'),
                'page_size' => (string) ($_POST['curr_page_size'] ?? '100'),
                'flash' => 'Preset guardado: ' . $newPresetName,
            ]);
        }
    }

    $tables = gpt_tables();
    if ($tables === []) {
        echo '<section class="card"><h2>Consulta Avanzada</h2><p>No hay tablas en el esquema gpt.</p></section>';
        return;
    }

    $selectedTable = query_text('table');
    if ($selectedTable === null || !in_array($selectedTable, $tables, true)) {
        $selectedTable = $tables[0];
    }

    $columns = gpt_table_columns($selectedTable);
    $columnNames = array_keys($columns);
    if ($columnNames === []) {
        echo '<section class="card"><h2>Consulta Avanzada</h2><p>La tabla no tiene columnas consultables.</p></section>';
        return;
    }

    $selectedColumn = query_text('column');
    if ($selectedColumn === null || !isset($columns[$selectedColumn])) {
        $selectedColumn = $columnNames[0];
    }

    $operator = query_text('op') ?? 'contains';
    $allowedOps = ['contains', 'eq', 'starts', 'ends', 'gt', 'lt', 'is_null', 'not_null'];
    if (!in_array($operator, $allowedOps, true)) {
        $operator = 'contains';
    }

    $value = query_text('value');
    $page = query_page();
    $pageSize = query_page_size(100);

    $selectedType = $columns[$selectedColumn];
    $numericTypes = ['smallint', 'integer', 'bigint', 'numeric', 'real', 'double precision', 'decimal'];
    $dateTypes = ['timestamp without time zone', 'timestamp with time zone', 'date'];
    $booleanTypes = ['boolean'];

    $selectCols = implode(
        ', ',
        array_map(
            static fn (string $col): string => quote_ident($col),
            $columnNames
        )
    );

    $tableSql = quote_ident('gpt') . '.' . quote_ident($selectedTable);
    $columnSql = quote_ident($selectedColumn);

    $where = [];
    $params = [];

    if ($operator === 'is_null') {
        $where[] = $columnSql . ' IS NULL';
    } elseif ($operator === 'not_null') {
        $where[] = $columnSql . ' IS NOT NULL';
    } elseif ($value !== null) {
        if (in_array($selectedType, $numericTypes, true) && in_array($operator, ['eq', 'gt', 'lt'], true) && is_numeric($value)) {
            $cmp = $operator === 'eq' ? '=' : ($operator === 'gt' ? '>' : '<');
            $where[] = $columnSql . ' ' . $cmp . ' :f_value_num';
            $params[':f_value_num'] = (float) $value;
        } elseif (in_array($selectedType, $dateTypes, true) && in_array($operator, ['eq', 'gt', 'lt'], true) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1) {
            $cmp = $operator === 'eq' ? '=' : ($operator === 'gt' ? '>' : '<');
            $where[] = 'CAST(' . $columnSql . ' AS DATE) ' . $cmp . ' :f_value_date';
            $params[':f_value_date'] = substr($value, 0, 10);
        } elseif (in_array($selectedType, $booleanTypes, true) && $operator === 'eq') {
            $normalized = strtolower($value);
            if (in_array($normalized, ['true', '1', 'si', 'yes'], true)) {
                $where[] = $columnSql . ' = :f_value_bool';
                $params[':f_value_bool'] = true;
            } elseif (in_array($normalized, ['false', '0', 'no'], true)) {
                $where[] = $columnSql . ' = :f_value_bool';
                $params[':f_value_bool'] = false;
            }
        } else {
            if ($operator === 'eq') {
                $where[] = 'CAST(' . $columnSql . ' AS TEXT) ILIKE :f_value';
                $params[':f_value'] = $value;
            } elseif ($operator === 'starts') {
                $where[] = 'CAST(' . $columnSql . ' AS TEXT) ILIKE :f_value';
                $params[':f_value'] = $value . '%';
            } elseif ($operator === 'ends') {
                $where[] = 'CAST(' . $columnSql . ' AS TEXT) ILIKE :f_value';
                $params[':f_value'] = '%' . $value;
            } elseif ($operator === 'gt') {
                $where[] = 'CAST(' . $columnSql . ' AS TEXT) > :f_value';
                $params[':f_value'] = $value;
            } elseif ($operator === 'lt') {
                $where[] = 'CAST(' . $columnSql . ' AS TEXT) < :f_value';
                $params[':f_value'] = $value;
            } else {
                $where[] = 'CAST(' . $columnSql . ' AS TEXT) ILIKE :f_value';
                $params[':f_value'] = '%' . $value . '%';
            }
        }
    }

    $sortable = [];
    foreach ($columnNames as $name) {
        $sortable[$name] = quote_ident($name);
    }
    [$sortKey, $sortDir] = query_sort($columnNames[0], 'desc', $sortable);

    $sql = 'SELECT ' . $selectCols . ' FROM ' . $tableSql;
    $countSql = 'SELECT COUNT(*) AS c FROM ' . $tableSql;
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir);

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $fmtDelta = static function ($v): string {
            if ($v === null || $v === '') {
                return '';
            }
            $n = (float) $v;
            if ($n > 0) {
                return '+' . (string) $v;
            }
            return (string) $v;
        };
        foreach ($rows as &$r) {
            $r['rank_delta'] = $fmtDelta($r['rank_delta'] ?? null);
            $r['score_delta'] = $fmtDelta($r['score_delta'] ?? null);
            $r['distance_delta'] = $fmtDelta($r['distance_delta'] ?? null);
        }
        unset($r);
        csv_stream(
            'Tablas_Clasificacion_Historico.csv',
            ['Snapshot', 'Comparado Con', 'Tipo', 'IdEspecie', 'Especie', 'Rank', 'Rank Prev', 'Delta Rank', 'Delta Puntuacion', 'Delta Distancia', 'IdUsuario', 'Jugador', 'Puntuacion', 'Distancia'],
            $rows,
            ['snapshot_at', 'compare_snapshot_at', 'leaderboard_type', 'species_id', 'species_name_es', 'rank_pos', 'prev_rank', 'rank_delta', 'score_delta', 'distance_delta', 'user_id', 'player_name', 'display_score', 'display_distance']
        );
    }

    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    $sql .= ' LIMIT :_limit OFFSET :_offset';
    $rows = app_query_all(
        $sql,
        $params + [
            ':_limit' => $pageSize,
            ':_offset' => $offset,
        ]
    );

    echo '<section class="card"><h2>Consulta Avanzada</h2>';
    echo '<form class="preset-actions" method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
    echo '<select name="preset_name_select">';
    echo '<option value="">Selecciona preset</option>';
    foreach (array_keys($presets) as $presetName) {
        echo '<option value="' . h($presetName) . '">' . h($presetName) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit" name="preset_action" value="apply">Aplicar</button>';
    echo '<button type="submit" name="preset_action" value="delete">Eliminar</button>';
    echo '</form>';

    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="advanced">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="theme" value="' . h(app_theme()) . '">';

    echo '<select name="table" onchange="this.form.submit()">';
    foreach ($tables as $table) {
        echo '<option value="' . h($table) . '"' . ($table === $selectedTable ? ' selected' : '') . '>' . h($table) . '</option>';
    }
    echo '</select>';

    echo '<select name="column">';
    foreach ($columnNames as $col) {
        echo '<option value="' . h($col) . '"' . ($col === $selectedColumn ? ' selected' : '') . '>' . h($col) . '</option>';
    }
    echo '</select>';

    echo '<select name="op">';
    echo '<option value="contains"' . ($operator === 'contains' ? ' selected' : '') . '>contiene</option>';
    echo '<option value="eq"' . ($operator === 'eq' ? ' selected' : '') . '>igual</option>';
    echo '<option value="starts"' . ($operator === 'starts' ? ' selected' : '') . '>empieza por</option>';
    echo '<option value="ends"' . ($operator === 'ends' ? ' selected' : '') . '>termina en</option>';
    echo '<option value="gt"' . ($operator === 'gt' ? ' selected' : '') . '>mayor que</option>';
    echo '<option value="lt"' . ($operator === 'lt' ? ' selected' : '') . '>menor que</option>';
    echo '<option value="is_null"' . ($operator === 'is_null' ? ' selected' : '') . '>es NULL</option>';
    echo '<option value="not_null"' . ($operator === 'not_null' ? ' selected' : '') . '>no es NULL</option>';
    echo '</select>';

    echo '<input type="text" name="value" placeholder="Valor del filtro" value="' . h(query_raw('value')) . '">';

    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';

    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=advanced&table=' . urlencode($selectedTable) . '&theme=' . urlencode(app_theme()) . '&reset=1">Limpiar</a>';
    echo '</form>';

    echo '<form class="preset-save" method="post">';
    echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
    echo '<input type="hidden" name="preset_action" value="save">';
    echo '<input type="hidden" name="curr_table" value="' . h($selectedTable) . '">';
    echo '<input type="hidden" name="curr_column" value="' . h($selectedColumn) . '">';
    echo '<input type="hidden" name="curr_op" value="' . h($operator) . '">';
    echo '<input type="hidden" name="curr_value" value="' . h((string) ($value ?? '')) . '">';
    echo '<input type="hidden" name="curr_sort" value="' . h($sortKey) . '">';
    echo '<input type="hidden" name="curr_dir" value="' . h($sortDir) . '">';
    echo '<input type="hidden" name="curr_page_size" value="' . h((string) $pageSize) . '">';
    echo '<input type="text" name="preset_name" placeholder="Nombre del preset">';
    echo '<button type="submit">Guardar Preset</button>';
    echo '</form>';

    echo '<div class="advanced-note">Tabla: <strong>' . h($selectedTable) . '</strong> - Campo: <strong>' . h($selectedColumn) . '</strong> (' . h($selectedType) . ')</div>';

    echo '<table><thead><tr>';
    foreach ($columnNames as $col) {
        echo '<th>' . sort_link($col, $col, $sortKey, $sortDir) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($columnNames as $col) {
            $cell = $row[$col] ?? null;
            echo '<td>' . h($cell === null ? '' : (string) $cell) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_dashboard(): void
{
    $isAdmin = app_is_admin_user();
    $counts = [
        'Expediciones' => app_query_one('SELECT COUNT(*) AS c FROM gpt.exp_expeditions')['c'] ?? 0,
        'Mejores Marcas' => app_query_one('SELECT COUNT(*) AS c FROM gpt.best_personal_records')['c'] ?? 0,
        'Perfiles EST' => app_query_one('SELECT COUNT(*) AS c FROM gpt.est_profiles')['c'] ?? 0,
        'Competiciones' => app_query_one('SELECT COUNT(*) AS c FROM gpt.comp_competitions')['c'] ?? 0,
        'Tablas de Clasificacion' => app_query_one('SELECT COUNT(*) AS c FROM gpt.clas_rankings_latest')['c'] ?? 0,
    ];

    $tasks = TaskManager::list(8);
    $latestCompetitions = app_query_all(
        'SELECT c.competition_id, t.type_name, c.start_at, c.end_at, c.entrants, c.updated_at
         FROM gpt.comp_competitions c
         LEFT JOIN gpt.comp_types t ON t.competition_type_id = c.competition_type_id
         ORDER BY c.updated_at DESC, c.competition_id DESC
         LIMIT 8'
    );

    echo '<section class="grid cards">';
    foreach ($counts as $label => $value) {
        echo '<article class="card stat"><div class="stat-value">' . h((string) $value) . '</div><div class="stat-label">' . h($label) . '</div></article>';
    }
    echo '</section>';

    echo '<section class="card">';
    echo '<h2>Procesos</h2>';
    echo '<form class="action-grid" method="post" action="task_create.php">';
    echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
    echo '<button name="action" value="refresh_competitions">Actualizar Competiciones</button>';
    echo '<button name="action" value="refresh_my_expeditions">Actualizar mis Expediciones</button>';
    if ($isAdmin) {
        echo '<button name="action" value="refresh_leaderboards">Actualizar Tablas Clasificacion</button>';
        echo '<button name="action" value="refresh_best_all">Actualizar Mejores Marcas Usuarios</button>';
        echo '<button name="action" value="refresh_public_all">Actualizar Estadisticas Usuarios</button>';
        echo '<button name="action" value="refresh_expeditions_all_users">Actualizar expediciones de todos</button>';
        echo '<button name="action" value="scrape_kill_urls">Scraper URLs de Muertes</button>';
        echo '<button name="action" value="export_best_xml">Generar XML de Mejores Marcas</button>';
    }
    echo '</form>';
    echo '</section>';

    $taskHistory = TaskManager::list(500);
    $scheduledTasks = TaskScheduleManager::forPanel();
    echo '<section class="card">';
    echo '<h2>Tareas programadas</h2>';
    echo '<table><thead><tr><th>Tarea</th><th>Activa</th><th>Cada (min)</th><th>&Uacute;ltima ejecuci&oacute;n</th><th>Estado</th><th>Ejecutar</th>' . ($isAdmin ? '<th>Guardar</th>' : '') . '</tr></thead><tbody>';
    foreach ($scheduledTasks as $action => $def) {
        $last = null;
        $isBusy = false;
        foreach ($taskHistory as $t) {
            if ((string) ($t['action'] ?? '') !== (string) $action) {
                continue;
            }
            if ($last === null) {
                $last = $t;
            }
            $st = (string) ($t['status'] ?? '');
            if (in_array($st, ['queued', 'running'], true)) {
                $isBusy = true;
            }
        }
        $lastAt = '-';
        $lastStatus = '-';
        if (is_array($last)) {
            $lastAt = (string) ($last['finished_at'] ?? $last['started_at'] ?? $last['created_at'] ?? '-');
            $lastStatus = (string) ($last['status'] ?? '-');
        }

        $canRun = TaskCatalog::canRunAction((string) $action, $isAdmin);

        echo '<tr>';
        if ($isAdmin) {
            echo '<form method="post" action="task_schedule_save.php">';
            echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
            echo '<input type="hidden" name="action" value="' . h((string) $action) . '">';
        }
        echo '<td>' . h((string) $def['label']) . '</td>';
        if ($isAdmin) {
            $checked = ((bool) ($def['enabled'] ?? false)) ? ' checked' : '';
            echo '<td><label><input type="checkbox" name="enabled" value="1"' . $checked . '> Si</label></td>';
            echo '<td><input type="number" name="interval_min" min="1" max="10080" value="' . h((string) ($def['interval_min'] ?? 180)) . '" style="width:92px"></td>';
        } else {
            echo '<td>' . (((bool) ($def['enabled'] ?? false)) ? 'Si' : 'No') . '</td>';
            echo '<td>' . h((string) ($def['interval_min'] ?? 180)) . '</td>';
        }
        echo '<td>' . h($lastAt) . '</td>';
        echo '<td>' . h($lastStatus) . '</td>';

        echo '<td>';
        if (!$canRun) {
            echo '<span class="muted">Solo admin</span>';
        } elseif ($isBusy) {
            echo '<span class="muted">En ejecucion</span>';
        } else {
            echo '<form method="post" action="task_create.php" class="task-stop-form">';
            echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
            echo '<input type="hidden" name="action" value="' . h((string) $action) . '">';
            echo '<button type="submit">Ejecutar</button>';
            echo '</form>';
        }
        echo '</td>';

        if ($isAdmin) {
            echo '<td><button type="submit">Guardar</button></td>';
            echo '</form>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</section>';

echo '<section class="split">';
    echo '<article class="card">';
    echo '<h2>Tareas recientes</h2>';
    echo '<table><thead><tr><th>Etiqueta</th><th>Estado</th><th>Creada</th><th>Log</th><th>Acci&oacute;n</th></tr></thead><tbody>';
    foreach ($tasks as $task) {
        $taskId = (string) ($task['id'] ?? '');
        $taskStatus = (string) ($task['status'] ?? '');
        echo '<tr>';
        echo '<td>' . h((string) ($task['label'] ?? '')) . '</td>';
        echo '<td>' . h($taskStatus) . '</td>';
        echo '<td>' . h((string) ($task['created_at'] ?? '')) . '</td>';
        echo '<td><a href="?view=logs&log=' . urlencode($taskId . '.log') . '">ver</a></td>';
        echo '<td>';
        if (in_array($taskStatus, ['queued', 'running'], true)) {
            echo '<form method="post" action="task_stop.php" class="task-stop-form">';
            echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
            echo '<input type="hidden" name="id" value="' . h($taskId) . '">';
            echo '<input type="hidden" name="redirect" value="index.php">';
            echo '<button type="submit" class="stop-btn">Interrumpir</button>';
            echo '</form>';
        } else {
            echo '<span class="muted">-</span>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</article>';

    echo '<article class="card">';
    echo '<h2>Competiciones</h2>';
    echo '<table><thead><tr><th>ID</th><th>Nombre</th><th>Entrants</th><th>Actualizada</th></tr></thead><tbody>';
    foreach ($latestCompetitions as $row) {
        echo '<tr>';
        echo '<td>' . h((string) ($row['competition_id'] ?? '')) . '</td>';
        echo '<td>' . h((string) ($row['type_name'] ?? '')) . '</td>';
        echo '<td>' . h((string) ($row['entrants'] ?? '')) . '</td>';
        echo '<td>' . h((string) ($row['updated_at'] ?? '')) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</article>';
    echo '</section>';
}

function render_expeditions(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);
    $isReset = is_reset_requested();
    $allDefs = expedition_join_column_defs();
    $columnDefs = [];
    foreach ($allDefs as $key => $def) {
        if (str_starts_with($key, 'e_')) {
            $columnDefs[$key] = $def;
        }
    }
    $defaultCols = [
        'e_expedition_id',
        'e_user_id',
        'e_player_name',
        'e_reserve_name',
        'e_reserve_id',
        'e_start_at',
        'e_end_at',
    ];

    $selectedCols = persistent_selected_columns('exp_cols', $columnDefs, 'col_', $defaultCols);
    $dragOrderRaw = query_text('col_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedCols, true)));
        foreach ($selectedCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCols = $ordered;
        }
    }
    $speciesColumnDefs = [
        'species_id' => 'IdEspecie',
        'species_name' => 'Especie',
        'species_icons' => 'Iconos M/F',
    ];
    $defaultSpeciesCols = ['species_id', 'species_name', 'species_icons'];
    $selectedSpeciesCols = [];
    $hasSpeciesChoice = false;
    foreach ($speciesColumnDefs as $key => $_label) {
        if (array_key_exists('cscol_' . $key, $_GET)) {
            $hasSpeciesChoice = true;
            $raw = $_GET['cscol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedSpeciesCols[] = $key;
            }
        }
    }
    if (!$hasSpeciesChoice || $selectedSpeciesCols === []) {
        $selectedSpeciesCols = $defaultSpeciesCols;
    }
    $speciesOrderRaw = query_text('cscol_order');
    if ($speciesOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $speciesOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedSpeciesCols, true)));
        foreach ($selectedSpeciesCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedSpeciesCols = $ordered;
        }
    }
    $rewardColumnDefs = [
        'prize_position' => 'Puesto',
        'reward_position' => 'N',
        'reward_type' => 'Tipo',
        'reward_define' => 'Define',
        'reward_amount' => 'Cantidad',
    ];
    $defaultRewardCols = ['prize_position', 'reward_position', 'reward_type', 'reward_define', 'reward_amount'];
    $selectedRewardCols = [];
    $hasRewardChoice = false;
    foreach ($rewardColumnDefs as $key => $_label) {
        if (array_key_exists('crcol_' . $key, $_GET)) {
            $hasRewardChoice = true;
            $raw = $_GET['crcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedRewardCols[] = $key;
            }
        }
    }
    if (!$hasRewardChoice || $selectedRewardCols === []) {
        $selectedRewardCols = $defaultRewardCols;
    }
    $rewardOrderRaw = query_text('crcol_order');
    if ($rewardOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $rewardOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedRewardCols, true)));
        foreach ($selectedRewardCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedRewardCols = $ordered;
        }
    }
    $animalColumnDefs = [
        'species_id' => 'IdEspecie',
        'species_name' => 'Especie',
        'kills' => 'Muertes',
        'ethical_kills' => 'Eticos',
    ];
    $defaultAnimalCols = ['species_id', 'species_name', 'kills', 'ethical_kills'];
    $selectedAnimalCols = [];
    $hasAnimalChoice = false;
    foreach ($animalColumnDefs as $key => $_label) {
        if (array_key_exists('pacol_' . $key, $_GET)) {
            $hasAnimalChoice = true;
            $raw = $_GET['pacol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedAnimalCols[] = $key;
            }
        }
    }
    if (!$hasAnimalChoice || $selectedAnimalCols === []) {
        $selectedAnimalCols = $defaultAnimalCols;
    }
    $animalDragOrderRaw = query_text('pacol_order');
    if ($animalDragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $animalDragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedAnimalCols, true)));
        foreach ($selectedAnimalCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedAnimalCols = $ordered;
        }
    }

    $weaponColumnDefs = [
        'weapon_id' => 'Weapon',
        'ammo_id' => 'Ammo',
        'shots' => 'Shots',
        'hits' => 'Disparos',
        'kills' => 'Muertes',
    ];
    $defaultWeaponCols = ['weapon_id', 'ammo_id', 'shots', 'hits', 'kills'];
    $selectedWeaponCols = [];
    $hasWeaponChoice = false;
    foreach ($weaponColumnDefs as $key => $_label) {
        if (array_key_exists('pwcol_' . $key, $_GET)) {
            $hasWeaponChoice = true;
            $raw = $_GET['pwcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedWeaponCols[] = $key;
            }
        }
    }
    if (!$hasWeaponChoice || $selectedWeaponCols === []) {
        $selectedWeaponCols = $defaultWeaponCols;
    }
    $weaponDragOrderRaw = query_text('pwcol_order');
    if ($weaponDragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $weaponDragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedWeaponCols, true)));
        foreach ($selectedWeaponCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedWeaponCols = $ordered;
        }
    }
    $selectedCols = order_selected_keys($selectedCols, 'ord_col_');

    $killColumnDefs = [
        'kill_id' => 'IdMuerte',
        'player_name' => 'Jugador',
        'species_name' => 'Especie',
        'species_name_es' => 'Especie ES',
        'species_id' => 'IdEspecie',
        'weight' => 'Peso (kg)',
        'gender' => 'Genero',
        'ethical' => 'Etico',
        'hit_min_distance' => 'Distancia (m)',
        'score' => 'Puntuacion',
        'harvest_value' => 'Harvest',
        'trophy_integrity' => 'Integridad (%)',
        'confirm_at' => 'Confirmado',
        'hits_count' => 'Disparos',
        'hits_avg_distance' => 'Dist Avg (m)',
        'hits_max_distance' => 'Dist Max (m)',
        'hits_organs' => 'Organos',
        'hit_details' => 'Detalle disparos',
    ];
    $killNumericCols = [
        'species_id' => true,
        'weight' => true,
        'hit_min_distance' => true,
        'score' => true,
        'harvest_value' => true,
        'trophy_integrity' => true,
        'hits_count' => true,
        'hits_avg_distance' => true,
        'hits_max_distance' => true,
    ];
    $defaultKillCols = ['kill_id', 'player_name', 'species_name', 'species_name_es', 'hit_min_distance', 'score', 'harvest_value', 'trophy_integrity', 'weight', 'ethical', 'hits_count', 'hit_details'];
    $selectedKillCols = persistent_selected_columns('exp_kill_cols', $killColumnDefs, 'kcol_', $defaultKillCols);
    $killDragOrderRaw = query_text('kcol_order');
    if ($killDragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $killDragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedKillCols, true)));
        foreach ($selectedKillCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedKillCols = $ordered;
        }
    }
    $selectedKillCols = order_selected_keys($selectedKillCols, 'ord_kcol_');

    $hitColumnDefs = [
        'hit_index' => 'Hit',
        'player_name' => 'Jugador',
        'user_id' => 'IdUsuario',
        'distance' => 'Distancia (m)',
        'weapon_id' => 'Weapon',
        'ammo_id' => 'Ammo',
        'organ' => 'Organo',
    ];
    $hitNumericCols = [
        'hit_index' => true,
        'user_id' => true,
        'distance' => true,
        'weapon_id' => true,
        'ammo_id' => true,
        'organ' => true,
    ];
    $defaultHitCols = ['hit_index', 'player_name', 'distance', 'weapon_id', 'ammo_id', 'organ'];
    $selectedHitCols = persistent_selected_columns('exp_hit_cols', $hitColumnDefs, 'hcol_', $defaultHitCols);
    $hitDragOrderRaw = query_text('hcol_order');
    if ($hitDragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $hitDragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedHitCols, true)));
        foreach ($selectedHitCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedHitCols = $ordered;
        }
    }
    $selectedHitCols = order_selected_keys($selectedHitCols, 'ord_hcol_');

    $killSortDefs = [
        'kill_id' => 'k.kill_id',
        'player_name' => 'k.player_name',
        'species_name' => 'COALESCE(s.especie_es, s.especie, k.species_name)',
        'species_name_es' => 'COALESCE(s.especie_es, s.especie, k.species_name)',
        'species_id' => 'COALESCE(k.species_id, -1)',
        'weight' => 'COALESCE(k.weight, -1)',
        'gender' => 'COALESCE(k.gender, -1)',
        'ethical' => 'COALESCE(k.ethical::int, -1)',
        'hit_min_distance' => 'COALESCE(hs.min_distance_raw, -1)',
        'score' => 'COALESCE(k.score, -1)',
        'harvest_value' => 'COALESCE(k.harvest_value, -1)',
        'trophy_integrity' => 'COALESCE(k.trophy_integrity, -1)',
        'confirm_at' => 'COALESCE(k.confirm_at, k.created_at)',
        'hits_count' => 'COALESCE(hs.hits_count, -1)',
        'hits_avg_distance' => 'COALESCE(hs.avg_distance_raw, -1)',
        'hits_max_distance' => 'COALESCE(hs.max_distance_raw, -1)',
        'hits_organs' => 'COALESCE(hs.organs, \'\')',
    ];
    [$killSortKey, $killSortDir] = query_sort_param('k_sort', 'k_dir', 'kill_id', 'desc', $killSortDefs);

    $hitSortDefs = [
        'hit_index' => 'hit_index',
        'player_name' => 'player_name',
        'user_id' => 'COALESCE(user_id, -1)',
        'distance' => 'COALESCE(distance, -1)',
        'weapon_id' => 'COALESCE(weapon_id, -1)',
        'ammo_id' => 'COALESCE(ammo_id, -1)',
        'organ' => 'COALESCE(organ, -1)',
    ];
    [$hitSortKey, $hitSortDir] = query_sort_param('h_sort', 'h_dir', 'hit_index', 'asc', $hitSortDefs);
    $openExpSet = [];
    foreach (query_list('open_exp') as $raw) {
        if (preg_match('/^\d+$/', (string) $raw) === 1) {
            $openExpSet[(int) $raw] = true;
        }
    }
    $openKillSet = [];
    foreach (query_list('open_kill') as $raw) {
        if (preg_match('/^\d+$/', (string) $raw) === 1) {
            $openKillSet[(int) $raw] = true;
        }
    }

    $expeditionId = query_int('expedition_id');
    $userId = query_int('user_id');
    $playerNames = default_player_filter(query_list('player_name'), 'player_name');
    $reserveNames = query_list('reserve_name');
    $startAt = query_datetime_local('start_at');
    $endAt = query_datetime_local('end_at');
    $killId = query_int('kill_id');
    $hitIndex = query_int('hit_index');
    $killPlayers = [];
    $killSpeciesNames = query_list('kill_species_name');
    if ($killSpeciesNames === []) {
        $fallback = query_text('species_name');
        if ($fallback !== null) {
            $killSpeciesNames = [$fallback];
        }
    }
    $killGender = query_int('kill_gender');
    $killEthical = query_text('kill_ethical');
    $killScoreMin = query_text('kill_score_min');
    $killScoreMax = query_text('kill_score_max');
    $killDistanceMin = query_text('kill_distance_min');
    $killDistanceMax = query_text('kill_distance_max');
    $killWeightMin = query_text('kill_weight_min');
    $killWeightMax = query_text('kill_weight_max');
    $killIntegrityMin = query_text('kill_integrity_min');
    $killIntegrityMax = query_text('kill_integrity_max');
    $killHarvestMin = query_text('kill_harvest_min');
    $killHarvestMax = query_text('kill_harvest_max');
    $markFilter = query_text('mark_filter') ?? '';
    if (!in_array($markFilter, ['', 'any', 'mmp', 'mmd'], true)) {
        $markFilter = '';
    }
    $photoTaxFilter = strtolower((string) (query_text('photo_tax_filter') ?? ''));
    if (!in_array($photoTaxFilter, ['', 'ft', 'f', 't'], true)) {
        $photoTaxFilter = '';
    }
    if ($markFilter === '' && query_raw('mm_only') === '1') {
        $markFilter = 'any';
    }
    $hitWeaponId = query_int('hit_weapon_id');
    $hitAmmoId = query_int('hit_ammo_id');
    $hitOrgan = query_int('hit_organ');
    $expDurationMin = query_text('exp_duration_min');
    $expDurationMax = query_text('exp_duration_max');

    $startAtFrom = query_date('date_from') ?? query_date('start_at_from');
    $endAtTo = query_date('date_to') ?? query_date('end_at_to');
    if ($isReset) {
        $expeditionId = null;
        $userId = null;
        $playerNames = [];
        $reserveNames = [];
        $startAt = null;
        $endAt = null;
        $killId = null;
        $hitIndex = null;
        $killPlayers = [];
        $killSpeciesNames = [];
        $killGender = null;
        $killEthical = null;
        $killScoreMin = null;
        $killScoreMax = null;
        $killDistanceMin = null;
        $killDistanceMax = null;
        $killWeightMin = null;
        $killWeightMax = null;
        $killIntegrityMin = null;
        $killIntegrityMax = null;
        $killHarvestMin = null;
        $killHarvestMax = null;
        $markFilter = '';
        $photoTaxFilter = '';
        $hitWeaponId = null;
        $hitAmmoId = null;
        $hitOrgan = null;
        $expDurationMin = null;
        $expDurationMax = null;
        $startAtFrom = null;
        $endAtTo = null;
        $page = 1;
    }

    $ppftBySpecies = ppft_thresholds();
    $where = [];
    $params = [];

    if ($expeditionId !== null) {
        $where[] = 'e.expedition_id = :expedition_id';
        $params[':expedition_id'] = $expeditionId;
    }
    if ($userId !== null) {
        $where[] = 'e.user_id = :user_id';
        $params[':user_id'] = $userId;
    }
    if ($playerNames !== []) {
        $parts = [];
        foreach ($playerNames as $idx => $name) {
            $ph = ':player_name_' . $idx;
            $parts[] = '(e.player_name = ' . $ph . ' OR EXISTS (
                SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.player_name = ' . $ph . '
            ))';
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($reserveNames !== []) {
        $parts = [];
        foreach ($reserveNames as $idx => $name) {
            $ph = ':reserve_name_' . $idx;
            $parts[] = 'e.reserve_name = ' . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($startAt !== null) {
        $where[] = 'e.start_at >= :start_at';
        $params[':start_at'] = $startAt;
    }
    if ($endAt !== null) {
        $where[] = 'e.end_at <= :end_at';
        $params[':end_at'] = $endAt;
    }
    if ($killId !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.kill_id = :kill_id)';
        $params[':kill_id'] = $killId;
    }
    if ($hitIndex !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.expedition_id = e.expedition_id AND hf.hit_index = :hit_index)';
        $params[':hit_index'] = $hitIndex;
    }
    if ($killSpeciesNames !== []) {
        $parts = [];
        foreach ($killSpeciesNames as $idx => $name) {
            $ph = ':kill_species_name_exp_' . $idx;
            $parts[] = 'EXISTS (
                SELECT 1
                FROM gpt.exp_kills kf
                LEFT JOIN gpt.tab_especies sf ON sf.id_especie = kf.species_id
                WHERE kf.expedition_id = e.expedition_id
                  AND (
                      sf.especie = ' . $ph . '
                      OR sf.especie_es = ' . $ph . '
                      OR kf.species_name = ' . $ph . '
                  )
            )';
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($killGender !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.gender = :kill_gender_exp)';
        $params[':kill_gender_exp'] = $killGender;
    }
    if ($killEthical === '1' || $killEthical === '0') {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.ethical = :kill_ethical_exp)';
        $params[':kill_ethical_exp'] = $killEthical === '1';
    }
    if ($killScoreMin !== null && is_numeric($killScoreMin)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.score >= :kill_score_min_exp)';
        $params[':kill_score_min_exp'] = $killScoreMin;
    }
    if ($killScoreMax !== null && is_numeric($killScoreMax)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.score <= :kill_score_max_exp)';
        $params[':kill_score_max_exp'] = $killScoreMax;
    }
    if ($killDistanceMin !== null && is_numeric($killDistanceMin)) {
        $where[] = 'EXISTS (
            SELECT 1
            FROM gpt.exp_hits hf
            JOIN gpt.exp_kills kf ON kf.kill_id = hf.kill_id
            WHERE kf.expedition_id = e.expedition_id
            GROUP BY hf.kill_id
            HAVING MIN(hf.distance) >= :kill_distance_min_exp_raw
        )';
        $params[':kill_distance_min_exp_raw'] = (float) $killDistanceMin * 1000.0;
    }
    if ($killDistanceMax !== null && is_numeric($killDistanceMax)) {
        $where[] = 'EXISTS (
            SELECT 1
            FROM gpt.exp_hits hf
            JOIN gpt.exp_kills kf ON kf.kill_id = hf.kill_id
            WHERE kf.expedition_id = e.expedition_id
            GROUP BY hf.kill_id
            HAVING MIN(hf.distance) <= :kill_distance_max_exp_raw
        )';
        $params[':kill_distance_max_exp_raw'] = (float) $killDistanceMax * 1000.0;
    }
    if ($killWeightMin !== null && is_numeric($killWeightMin)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.weight >= :kill_weight_min_exp)';
        $params[':kill_weight_min_exp'] = (float) $killWeightMin * 1000.0;
    }
    if ($killWeightMax !== null && is_numeric($killWeightMax)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.weight <= :kill_weight_max_exp)';
        $params[':kill_weight_max_exp'] = (float) $killWeightMax * 1000.0;
    }
    if ($killIntegrityMin !== null && is_numeric($killIntegrityMin)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.trophy_integrity >= :kill_integrity_min_exp)';
        $params[':kill_integrity_min_exp'] = (float) $killIntegrityMin;
    }
    if ($killIntegrityMax !== null && is_numeric($killIntegrityMax)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.trophy_integrity <= :kill_integrity_max_exp)';
        $params[':kill_integrity_max_exp'] = (float) $killIntegrityMax;
    }
    if ($killHarvestMin !== null && is_numeric($killHarvestMin)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.harvest_value >= :kill_harvest_min_exp)';
        $params[':kill_harvest_min_exp'] = $killHarvestMin;
    }
    if ($killHarvestMax !== null && is_numeric($killHarvestMax)) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_kills kf WHERE kf.expedition_id = e.expedition_id AND kf.harvest_value <= :kill_harvest_max_exp)';
        $params[':kill_harvest_max_exp'] = $killHarvestMax;
    }
    if ($photoTaxFilter !== '') {
        $speciesThresholdById = [];
        try {
            $speciesRows = app_query_all('SELECT id_especie, especie, especie_es FROM gpt.tab_especies');
            foreach ($speciesRows as $srow) {
                $sid = $srow['id_especie'] ?? null;
                if (!is_numeric((string) $sid)) {
                    continue;
                }
                $matched = null;
                foreach ([(string) ($srow['especie_es'] ?? ''), (string) ($srow['especie'] ?? '')] as $nameCandidate) {
                    $skey = species_key_normalized($nameCandidate);
                    if ($skey !== '' && isset($ppftBySpecies[$skey])) {
                        $matched = $ppftBySpecies[$skey];
                        break;
                    }
                }
                if ($matched === null) {
                    continue;
                }
                $foto = $matched['foto'] ?? null;
                $tax = $matched['tax'] ?? null;
                if ($photoTaxFilter === 't') {
                    if (!is_numeric((string) $tax)) {
                        continue;
                    }
                } elseif ($photoTaxFilter === 'f') {
                    if (!is_numeric((string) $foto)) {
                        continue;
                    }
                } else {
                    if (!is_numeric((string) $foto) && !is_numeric((string) $tax)) {
                        continue;
                    }
                }
                $speciesThresholdById[(int) $sid] = [
                    'foto' => is_numeric((string) $foto) ? (float) $foto : null,
                    'tax' => is_numeric((string) $tax) ? (float) $tax : null,
                ];
            }
        } catch (Throwable) {
            $speciesThresholdById = [];
        }

        if ($speciesThresholdById === []) {
            $where[] = '1 = 0';
        } else {
            $parts = [];
            $idx = 0;
            foreach ($speciesThresholdById as $sid => $th) {
                $sidPh = ':pt_sid_' . $idx;
                $params[$sidPh] = (int) $sid;
                if ($photoTaxFilter === 't') {
                    $taxPh = ':pt_tax_' . $idx;
                    $params[$taxPh] = (float) ($th['tax'] ?? 0.0);
                    $parts[] = '(kf.species_id = ' . $sidPh . ' AND kf.score >= ' . $taxPh . ')';
                } elseif ($photoTaxFilter === 'f') {
                    $fotoPh = ':pt_foto_' . $idx;
                    $params[$fotoPh] = (float) ($th['foto'] ?? 0.0);
                    if (is_numeric((string) ($th['tax'] ?? null))) {
                        $taxPh = ':pt_taxmax_' . $idx;
                        $params[$taxPh] = (float) $th['tax'];
                        $parts[] = '(kf.species_id = ' . $sidPh . ' AND kf.score >= ' . $fotoPh . ' AND kf.score < ' . $taxPh . ')';
                    } else {
                        $parts[] = '(kf.species_id = ' . $sidPh . ' AND kf.score >= ' . $fotoPh . ')';
                    }
                } else {
                    $inner = [];
                    if (is_numeric((string) ($th['foto'] ?? null))) {
                        $fotoPh = ':pt_foto_' . $idx;
                        $params[$fotoPh] = (float) $th['foto'];
                        $inner[] = 'kf.score >= ' . $fotoPh;
                    }
                    if (is_numeric((string) ($th['tax'] ?? null))) {
                        $taxPh = ':pt_tax_' . $idx;
                        $params[$taxPh] = (float) $th['tax'];
                        $inner[] = 'kf.score >= ' . $taxPh;
                    }
                    if ($inner !== []) {
                        $parts[] = '(kf.species_id = ' . $sidPh . ' AND (' . implode(' OR ', $inner) . '))';
                    }
                }
                $idx++;
            }

            if ($parts === []) {
                $where[] = '1 = 0';
            } else {
                $where[] = 'EXISTS (
                    SELECT 1
                    FROM gpt.exp_kills kf
                    WHERE kf.expedition_id = e.expedition_id
                      AND (' . implode(' OR ', $parts) . ')
                )';
            }
        }
    }
    if ($markFilter !== '') {
        $scoreMarkCondition = 'kf.score IS NOT NULL
              AND bpr.best_score_value IS NOT NULL
              AND kf.score >= (bpr.best_score_value - 0.0001)';
        $distanceMarkCondition = 'bpr.best_distance_m IS NOT NULL
              AND COALESCE(
                    (SELECT MIN(hf.distance)::numeric / 1000.0 FROM gpt.exp_hits hf WHERE hf.kill_id = kf.kill_id),
                    -1
              ) >= (bpr.best_distance_m - 0.0005)';
        $markCondition = $scoreMarkCondition . ' OR ' . $distanceMarkCondition;
        if ($markFilter === 'mmp') {
            $markCondition = $scoreMarkCondition;
        } elseif ($markFilter === 'mmd') {
            $markCondition = $distanceMarkCondition;
        }
        $where[] = 'EXISTS (
            SELECT 1
            FROM gpt.exp_kills kf
            JOIN gpt.best_personal_records bpr
              ON bpr.user_id = kf.user_id
             AND bpr.species_id = kf.species_id
            WHERE kf.expedition_id = e.expedition_id
              AND (' . $markCondition . ')
        )';
    }
    if ($hitWeaponId !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.expedition_id = e.expedition_id AND hf.weapon_id = :hit_weapon_id_exp)';
        $params[':hit_weapon_id_exp'] = $hitWeaponId;
    }
    if ($hitAmmoId !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.expedition_id = e.expedition_id AND hf.ammo_id = :hit_ammo_id_exp)';
        $params[':hit_ammo_id_exp'] = $hitAmmoId;
    }
    if ($hitOrgan !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.expedition_id = e.expedition_id AND hf.organ = :hit_organ_exp)';
        $params[':hit_organ_exp'] = $hitOrgan;
    }
    $expDurationExpr = "COALESCE(
        (SELECT st.duration::numeric FROM gpt.exp_stats st WHERE st.expedition_id = e.expedition_id LIMIT 1),
        EXTRACT(EPOCH FROM (COALESCE(e.end_at, e.updated_at, e.created_at) - COALESCE(e.start_at, e.created_at)))
    )";
    if ($expDurationMin !== null && is_numeric($expDurationMin)) {
        $where[] = $expDurationExpr . ' >= :exp_duration_min';
        $params[':exp_duration_min'] = (float) $expDurationMin * 60.0;
    }
    if ($expDurationMax !== null && is_numeric($expDurationMax)) {
        $where[] = $expDurationExpr . ' <= :exp_duration_max';
        $params[':exp_duration_max'] = (float) $expDurationMax * 60.0;
    }
    if ($startAtFrom !== null) {
        $where[] = 'CAST(e.start_at AS DATE) >= :start_at_from';
        $params[':start_at_from'] = $startAtFrom;
    }
    if ($endAtTo !== null) {
        $where[] = 'CAST(e.end_at AS DATE) <= :end_at_to';
        $params[':end_at_to'] = $endAtTo;
    }

    $sortable = [];
    foreach ($selectedCols as $key) {
        $sortable[$key] = $columnDefs[$key]['sort'];
    }
    $defaultSort = in_array('e_expedition_id', $selectedCols, true) ? 'e_expedition_id' : $selectedCols[0];
    [$sortKey, $sortDir] = query_sort($defaultSort, 'desc', $sortable);

    $selectParts = [];
    foreach ($selectedCols as $key) {
        $selectParts[] = $columnDefs[$key]['expr'] . ' AS ' . quote_ident($key);
    }
    $selectSql = implode(', ', $selectParts) . ', e.expedition_id AS _exp_id';

    $sql = 'SELECT ' . $selectSql . '
            FROM gpt.exp_expeditions e';
    $countSql = 'SELECT COUNT(*) AS c
                 FROM gpt.exp_expeditions e';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir);

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $csvHeaders = [];
        foreach ($selectedCols as $key) {
            $csvHeaders[] = $columnDefs[$key]['label'];
        }
        csv_stream(
            'expediciones.csv',
            $csvHeaders,
            $rows,
            $selectedCols
        );
    }

    $sql .= ' LIMIT :_limit OFFSET :_offset';

    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    echo '<section class="card"><h2>Expediciones</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="expeditions">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="col_order" value="' . h(query_raw('col_order')) . '">';
    echo '<input type="hidden" name="kcol_order" value="' . h(query_raw('kcol_order')) . '">';
    echo '<input type="hidden" name="hcol_order" value="' . h(query_raw('hcol_order')) . '">';
    echo '<input type="hidden" name="k_sort" value="' . h($killSortKey) . '">';
    echo '<input type="hidden" name="k_dir" value="' . h($killSortDir) . '">';
    echo '<input type="hidden" name="h_sort" value="' . h($hitSortKey) . '">';
    echo '<input type="hidden" name="h_dir" value="' . h($hitSortDir) . '">';
    echo '<input type="text" name="expedition_id" data-col-target="e_expedition_id" placeholder="ID" value="' . h(query_raw('expedition_id')) . '">';
    echo '<input type="text" name="user_id" data-col-target="e_user_id" placeholder="IdUsuario" value="' . h(query_raw('user_id')) . '">';
    echo '<select name="player_name[]" data-col-target="e_player_name">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<select name="reserve_name[]" data-col-target="e_reserve_name">';
    echo '<option value="">Reserva (todas)</option>';
    foreach (reserve_name_suggestions() as $name) {
        $selected = in_array($name, $reserveNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="datetime-local" name="start_at" data-col-target="e_start_at" title="Inicio desde fecha/hora" value="' . h(raw_to_datetime_local_value(query_raw('start_at'))) . '">';
    echo '<input type="datetime-local" name="end_at" data-col-target="e_end_at" title="Fin hasta fecha/hora" value="' . h(raw_to_datetime_local_value(query_raw('end_at'))) . '">';
    echo '<input type="text" name="kill_id" data-col-target="k_kill_id" placeholder="Kill ID" value="' . h(query_raw('kill_id')) . '">';
    echo '<input type="text" name="hit_index" data-col-target="h_hit_index" placeholder="Hit Index" value="' . h(query_raw('hit_index')) . '">';
    echo '<select name="kill_species_name[]" data-col-target="k_species_name">';
    echo '<option value="">Nombre especie (todas)</option>';
    foreach (species_es_name_suggestions() as $value) {
        $selected = in_array($value, $killSpeciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="kill_gender" data-col-target="k_gender" placeholder="Kill gender" value="' . h(query_raw('kill_gender')) . '">';
    echo '<select name="kill_ethical" data-col-target="k_ethical"><option value="">Kill etico</option><option value="1"' . (query_raw('kill_ethical') === '1' ? ' selected' : '') . '>Si</option><option value="0"' . (query_raw('kill_ethical') === '0' ? ' selected' : '') . '>No</option></select>';
    echo '<input type="text" name="kill_score_min" data-col-target="k_score" placeholder="Kill Puntuacion" value="' . h(query_raw('kill_score_min')) . '">';
    echo '<input type="text" name="kill_score_max" data-col-target="k_score" placeholder="Kill Puntuacion" value="' . h(query_raw('kill_score_max')) . '">';
    echo '<input type="text" name="kill_distance_min" data-col-target="k_hit_min_distance" placeholder="Kill distancia min (m)" value="' . h(query_raw('kill_distance_min')) . '">';
    echo '<input type="text" name="kill_distance_max" data-col-target="k_hit_min_distance" placeholder="Kill distancia max (m)" value="' . h(query_raw('kill_distance_max')) . '">';
    echo '<input type="text" name="kill_weight_min" data-col-target="k_weight" placeholder="Kill peso min (kg)" value="' . h(query_raw('kill_weight_min')) . '">';
    echo '<input type="text" name="kill_weight_max" data-col-target="k_weight" placeholder="Kill peso max (kg)" value="' . h(query_raw('kill_weight_max')) . '">';
    echo '<input type="text" name="kill_integrity_min" data-col-target="k_trophy_integrity" placeholder="Kill integridad min (%)" value="' . h(query_raw('kill_integrity_min')) . '">';
    echo '<input type="text" name="kill_integrity_max" data-col-target="k_trophy_integrity" placeholder="Kill integridad max (%)" value="' . h(query_raw('kill_integrity_max')) . '">';
    echo '<input type="text" name="kill_harvest_min" data-col-target="k_harvest_value" placeholder="Kill harvest min" value="' . h(query_raw('kill_harvest_min')) . '">';
    echo '<input type="text" name="kill_harvest_max" data-col-target="k_harvest_value" placeholder="Kill harvest max" value="' . h(query_raw('kill_harvest_max')) . '">';
    echo '<select name="mark_filter"><option value="">Marca (todas)</option><option value="any"' . ($markFilter === 'any' ? ' selected' : '') . '>MM (MMP o MMD)</option><option value="mmp"' . ($markFilter === 'mmp' ? ' selected' : '') . '>Solo MMP</option><option value="mmd"' . ($markFilter === 'mmd' ? ' selected' : '') . '>Solo MMD</option></select>';
    echo '<select name="photo_tax_filter"><option value="">Todas</option><option value="ft"' . ($photoTaxFilter === 'ft' ? ' selected' : '') . '>Foto o Tax</option><option value="f"' . ($photoTaxFilter === 'f' ? ' selected' : '') . '>Foto</option><option value="t"' . ($photoTaxFilter === 't' ? ' selected' : '') . '>Tax</option></select>';
    echo '<input type="text" name="hit_weapon_id" data-col-target="h_weapon_id" placeholder="Hit weapon_id" value="' . h(query_raw('hit_weapon_id')) . '">';
    echo '<input type="text" name="hit_ammo_id" data-col-target="h_ammo_id" placeholder="Hit ammo_id" value="' . h(query_raw('hit_ammo_id')) . '">';
    echo '<input type="text" name="hit_organ" data-col-target="h_organ" placeholder="Hit organ" value="' . h(query_raw('hit_organ')) . '">';
    echo '<input type="text" name="exp_duration_min" placeholder="Duracion" value="' . h(query_raw('exp_duration_min')) . '">';
    echo '<input type="text" name="exp_duration_max" placeholder="Duracion" value="' . h(query_raw('exp_duration_max')) . '">';
    echo '<input type="date" name="date_from" data-col-target="e_start_at" title="Fecha inicio" value="' . h(query_raw('date_from') !== '' ? query_raw('date_from') : query_raw('start_at_from')) . '">';
    echo '<input type="date" name="date_to" data-col-target="e_end_at" title="Fecha final" value="' . h(query_raw('date_to') !== '' ? query_raw('date_to') : query_raw('end_at_to')) . '">';
    echo '<details class="filter-details visible-columns" data-col-prefix="col_" data-order-field="col_order"><summary>Columnas Expediciones</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="col_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="kcol_" data-order-field="kcol_order"><summary>Columnas de Muertes</summary><div class="visible-row">';
    foreach ($killColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedKillCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="kcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultKillCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="hcol_" data-order-field="hcol_order"><summary>Columnas de Disparos</summary><div class="visible-row">';
    foreach ($hitColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedHitCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="hcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultHitCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=expeditions&reset=1">Limpiar</a>';
    echo '</form>';

    $rows = app_query_all(
        $sql,
        $params + [
            ':_limit' => $pageSize,
            ':_offset' => $offset,
        ]
    );
    if ($rows === []) {
        echo '<p class="muted">Sin resultados con los filtros actuales.</p>';
    }

    $killsByExpedition = [];
    $expeditionIds = [];
    foreach ($rows as $row) {
        if (isset($row['_exp_id']) && is_numeric((string) $row['_exp_id'])) {
            $expeditionIds[] = (int) $row['_exp_id'];
        }
    }
    $expeditionIds = array_values(array_unique($expeditionIds));

    $hitsByKill = [];
    $bestScoreByUserSpecies = [];
    $bestDistanceByUserSpecies = [];
    $bestScoreKillByUserSpecies = [];
    $bestDistanceKillByUserSpecies = [];
    if ($expeditionIds !== []) {
        $inParts = [];
        $killParams = [];
        foreach ($expeditionIds as $idx => $id) {
            $ph = ':exp_' . $idx;
            $inParts[] = $ph;
            $killParams[$ph] = $id;
        }

        $killSql = 'SELECT k.expedition_id, k.kill_id, k.user_id, k.player_name, k.species_id, k.species_name,
                           COALESCE(s.especie_es, s.especie, k.species_name) AS species_name_es,
                           k.weight, k.gender, k.texture, k.ethical, k.wound_time, k.harvest_value,
                           k.trophy_integrity, k.score, k.score_type, k.confirm_at,
                           COALESCE(hs.hits_count, 0) AS hits_count,
                           hs.min_distance AS hit_min_distance,
                           hs.avg_distance AS hits_avg_distance,
                           hs.max_distance AS hits_max_distance,
                           hs.organs AS hits_organs
                    FROM gpt.exp_kills k
                    LEFT JOIN gpt.tab_especies s ON s.id_especie = k.species_id
                    LEFT JOIN (
                        SELECT h.kill_id,
                               COUNT(*) AS hits_count,
                               MIN(h.distance) AS min_distance_raw,
                               AVG(h.distance)::numeric AS avg_distance_raw,
                               MAX(h.distance) AS max_distance_raw,
                               ROUND((MIN(h.distance)::numeric / 1000.0), 3) AS min_distance,
                               ROUND((AVG(h.distance)::numeric / 1000.0), 3) AS avg_distance,
                               ROUND((MAX(h.distance)::numeric / 1000.0), 3) AS max_distance,
                               STRING_AGG(DISTINCT h.organ::text, \', \' ORDER BY h.organ::text) AS organs
                        FROM gpt.exp_hits h
                        GROUP BY h.kill_id
                    ) hs ON hs.kill_id = k.kill_id
                    WHERE k.expedition_id IN (' . implode(', ', $inParts) . ')';

        if ($killId !== null) {
            $killSql .= ' AND k.kill_id = :kill_id_filter';
            $killParams[':kill_id_filter'] = $killId;
        }
        if ($hitIndex !== null) {
            $killSql .= ' AND EXISTS (
                SELECT 1
                FROM gpt.exp_hits hf
                WHERE hf.kill_id = k.kill_id
                  AND hf.hit_index = :hit_index_filter
            )';
            $killParams[':hit_index_filter'] = $hitIndex;
        }
        if ($killSpeciesNames !== []) {
            $parts = [];
            foreach ($killSpeciesNames as $idx => $name) {
                $ph = ':kill_species_name_' . $idx;
                $parts[] = 'EXISTS (
                    SELECT 1
                    FROM gpt.tab_especies ks
                    WHERE ks.id_especie = k.species_id
                      AND (
                          ks.especie = ' . $ph . '
                          OR ks.especie_es = ' . $ph . '
                          OR k.species_name = ' . $ph . '
                      )
                )';
                $killParams[$ph] = $name;
            }
            $killSql .= ' AND (' . implode(' OR ', $parts) . ')';
        }
        if ($killGender !== null) {
            $killSql .= ' AND k.gender = :kill_gender';
            $killParams[':kill_gender'] = $killGender;
        }
        if ($killEthical === '1' || $killEthical === '0') {
            $killSql .= ' AND k.ethical = :kill_ethical';
            $killParams[':kill_ethical'] = $killEthical === '1';
        }
        if ($killScoreMin !== null && is_numeric($killScoreMin)) {
            $killSql .= ' AND k.score >= :kill_score_min';
            $killParams[':kill_score_min'] = $killScoreMin;
        }
        if ($killScoreMax !== null && is_numeric($killScoreMax)) {
            $killSql .= ' AND k.score <= :kill_score_max';
            $killParams[':kill_score_max'] = $killScoreMax;
        }
        if ($killDistanceMin !== null && is_numeric($killDistanceMin)) {
            $killSql .= ' AND COALESCE((SELECT MIN(hf.distance) FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id), 0) >= :kill_distance_min_raw';
            $killParams[':kill_distance_min_raw'] = (float) $killDistanceMin * 1000.0;
        }
        if ($killDistanceMax !== null && is_numeric($killDistanceMax)) {
            $killSql .= ' AND COALESCE((SELECT MIN(hf.distance) FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id), 0) <= :kill_distance_max_raw';
            $killParams[':kill_distance_max_raw'] = (float) $killDistanceMax * 1000.0;
        }
        if ($killWeightMin !== null && is_numeric($killWeightMin)) {
            $killSql .= ' AND k.weight >= :kill_weight_min';
            $killParams[':kill_weight_min'] = (float) $killWeightMin * 1000.0;
        }
        if ($killWeightMax !== null && is_numeric($killWeightMax)) {
            $killSql .= ' AND k.weight <= :kill_weight_max';
            $killParams[':kill_weight_max'] = (float) $killWeightMax * 1000.0;
        }
        if ($killIntegrityMin !== null && is_numeric($killIntegrityMin)) {
            $killSql .= ' AND k.trophy_integrity >= :kill_integrity_min';
            $killParams[':kill_integrity_min'] = (float) $killIntegrityMin;
        }
        if ($killIntegrityMax !== null && is_numeric($killIntegrityMax)) {
            $killSql .= ' AND k.trophy_integrity <= :kill_integrity_max';
            $killParams[':kill_integrity_max'] = (float) $killIntegrityMax;
        }
        if ($killHarvestMin !== null && is_numeric($killHarvestMin)) {
            $killSql .= ' AND k.harvest_value >= :kill_harvest_min';
            $killParams[':kill_harvest_min'] = $killHarvestMin;
        }
        if ($killHarvestMax !== null && is_numeric($killHarvestMax)) {
            $killSql .= ' AND k.harvest_value <= :kill_harvest_max';
            $killParams[':kill_harvest_max'] = $killHarvestMax;
        }
        if ($markFilter !== '') {
            $scoreMarkCondition = 'k.score IS NOT NULL
                  AND bpr.best_score_value IS NOT NULL
                  AND k.score >= (bpr.best_score_value - 0.0001)';
            $distanceMarkCondition = 'bpr.best_distance_m IS NOT NULL
                  AND COALESCE(
                        (SELECT MIN(hf.distance)::numeric / 1000.0 FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id),
                        -1
                  ) >= (bpr.best_distance_m - 0.0005)';
            $markCondition = $scoreMarkCondition . ' OR ' . $distanceMarkCondition;
            if ($markFilter === 'mmp') {
                $markCondition = $scoreMarkCondition;
            } elseif ($markFilter === 'mmd') {
                $markCondition = $distanceMarkCondition;
            }
            $killSql .= ' AND EXISTS (
                SELECT 1
                FROM gpt.best_personal_records bpr
                WHERE bpr.user_id = k.user_id
                  AND bpr.species_id = k.species_id
                  AND (' . $markCondition . ')
            )';
        }
        if ($hitWeaponId !== null) {
            $killSql .= ' AND EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id AND hf.weapon_id = :hit_weapon_id)';
            $killParams[':hit_weapon_id'] = $hitWeaponId;
        }
        if ($hitAmmoId !== null) {
            $killSql .= ' AND EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id AND hf.ammo_id = :hit_ammo_id)';
            $killParams[':hit_ammo_id'] = $hitAmmoId;
        }
        if ($hitOrgan !== null) {
            $killSql .= ' AND EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id AND hf.organ = :hit_organ)';
            $killParams[':hit_organ'] = $hitOrgan;
        }

        $killSql .= ' ORDER BY k.expedition_id DESC, ' . $killSortDefs[$killSortKey] . ' ' . strtoupper($killSortDir) . ', k.kill_id DESC';
        $killRows = app_query_all($killSql, $killParams);
        $killIds = [];
        foreach ($killRows as $killRow) {
            $expId = (int) ($killRow['expedition_id'] ?? 0);
            if ($expId <= 0) {
                continue;
            }
            $killsByExpedition[$expId][] = $killRow;
            $killIdRow = (int) ($killRow['kill_id'] ?? 0);
            if ($killIdRow > 0) {
                $killIds[] = $killIdRow;
            }
        }

        $killIds = array_values(array_unique($killIds));
        $userSet = [];
        $speciesSet = [];
        foreach ($killRows as $killRow) {
            $uid = $killRow['user_id'] ?? null;
            $sid = $killRow['species_id'] ?? null;
            if (is_numeric((string) $uid) && is_numeric((string) $sid)) {
                $userSet[(int) $uid] = true;
                $speciesSet[(int) $sid] = true;
            }
        }
        if ($userSet !== [] && $speciesSet !== []) {
            $userIn = [];
            $speciesIn = [];
            $bestParams = [];
            $i = 0;
            foreach (array_keys($userSet) as $uid) {
                $ph = ':bu_' . $i++;
                $userIn[] = $ph;
                $bestParams[$ph] = (int) $uid;
            }
            $i = 0;
            foreach (array_keys($speciesSet) as $sid) {
                $ph = ':bs_' . $i++;
                $speciesIn[] = $ph;
                $bestParams[$ph] = (int) $sid;
            }
            try {
                $bestRows = app_query_all(
                    'SELECT user_id, species_id, best_score_value, best_distance_m, best_score_animal_id, best_distance_animal_id
                     FROM gpt.best_personal_records
                     WHERE user_id IN (' . implode(', ', $userIn) . ')
                       AND species_id IN (' . implode(', ', $speciesIn) . ')
                       AND (best_score_value IS NOT NULL OR best_distance_m IS NOT NULL)',
                    $bestParams
                );
                foreach ($bestRows as $brow) {
                    $uid = (int) ($brow['user_id'] ?? 0);
                    $sid = (int) ($brow['species_id'] ?? 0);
                    if ($uid <= 0 || $sid < 0) {
                        continue;
                    }
                    $scoreBest = $brow['best_score_value'] ?? null;
                    if (is_numeric((string) $scoreBest)) {
                        $bestScoreByUserSpecies[$uid . ':' . $sid] = (float) $scoreBest;
                    }
                    $distanceBest = $brow['best_distance_m'] ?? null;
                    if (is_numeric((string) $distanceBest)) {
                        $bestDistanceByUserSpecies[$uid . ':' . $sid] = (float) $distanceBest;
                    }
                    $scoreKillId = $brow['best_score_animal_id'] ?? null;
                    if (is_numeric((string) $scoreKillId)) {
                        $bestScoreKillByUserSpecies[$uid . ':' . $sid] = (int) $scoreKillId;
                    }
                    $distanceKillId = $brow['best_distance_animal_id'] ?? null;
                    if (is_numeric((string) $distanceKillId)) {
                        $bestDistanceKillByUserSpecies[$uid . ':' . $sid] = (int) $distanceKillId;
                    }
                }
            } catch (Throwable) {
                $bestScoreByUserSpecies = [];
                $bestDistanceByUserSpecies = [];
                $bestScoreKillByUserSpecies = [];
                $bestDistanceKillByUserSpecies = [];
            }
        }
        if ($killIds !== []) {
            $hitInParts = [];
            $hitParams = [];
            foreach ($killIds as $idx => $id) {
                $ph = ':kill_' . $idx;
                $hitInParts[] = $ph;
                $hitParams[$ph] = $id;
            }

            $hitSql = 'SELECT kill_id, hit_index, user_id, player_name, distance, weapon_id, ammo_id, organ
                       FROM gpt.exp_hits
                       WHERE kill_id IN (' . implode(', ', $hitInParts) . ')';
            if ($hitIndex !== null) {
                $hitSql .= ' AND hit_index = :hit_index_exact';
                $hitParams[':hit_index_exact'] = $hitIndex;
            }
            if ($hitWeaponId !== null) {
                $hitSql .= ' AND weapon_id = :hit_weapon_id_exact';
                $hitParams[':hit_weapon_id_exact'] = $hitWeaponId;
            }
            if ($hitAmmoId !== null) {
                $hitSql .= ' AND ammo_id = :hit_ammo_id_exact';
                $hitParams[':hit_ammo_id_exact'] = $hitAmmoId;
            }
            if ($hitOrgan !== null) {
                $hitSql .= ' AND organ = :hit_organ_exact';
                $hitParams[':hit_organ_exact'] = $hitOrgan;
            }
            $hitSql .= ' ORDER BY kill_id DESC, ' . $hitSortDefs[$hitSortKey] . ' ' . strtoupper($hitSortDir) . ', hit_index ASC';

            $hitRows = app_query_all($hitSql, $hitParams);
            foreach ($hitRows as $hitRow) {
                $killIdRow = (int) ($hitRow['kill_id'] ?? 0);
                if ($killIdRow <= 0) {
                    continue;
                }
                $hitsByKill[$killIdRow][] = $hitRow;
            }
        }
    }

    $totalExpedicionesVista = $totalRows;
    $totalMuertesVista = 0;
    $totalFotoVista = 0;
    $totalTaxVista = 0;

    $expFilterSql = 'SELECT e.expedition_id FROM gpt.exp_expeditions e';
    if ($where !== []) {
        $expFilterSql .= ' WHERE ' . implode(' AND ', $where);
    }

    $allKillSql = 'SELECT k.expedition_id, k.score, k.species_name, COALESCE(s.especie_es, s.especie, k.species_name) AS species_name_es
                   FROM gpt.exp_kills k
                   LEFT JOIN gpt.tab_especies s ON s.id_especie = k.species_id
                   WHERE k.expedition_id IN (' . $expFilterSql . ')';
    $allKillParams = $params;

    if ($killId !== null) {
        $allKillSql .= ' AND k.kill_id = :kill_id_total';
        $allKillParams[':kill_id_total'] = $killId;
    }
    if ($hitIndex !== null) {
        $allKillSql .= ' AND EXISTS (
            SELECT 1
            FROM gpt.exp_hits hf
            WHERE hf.kill_id = k.kill_id
              AND hf.hit_index = :hit_index_total
        )';
        $allKillParams[':hit_index_total'] = $hitIndex;
    }
    if ($killSpeciesNames !== []) {
        $parts = [];
        foreach ($killSpeciesNames as $idx => $name) {
            $ph = ':kill_species_name_total_' . $idx;
            $parts[] = 'EXISTS (
                SELECT 1
                FROM gpt.tab_especies ks
                WHERE ks.id_especie = k.species_id
                  AND (
                      ks.especie = ' . $ph . '
                      OR ks.especie_es = ' . $ph . '
                      OR k.species_name = ' . $ph . '
                  )
            )';
            $allKillParams[$ph] = $name;
        }
        $allKillSql .= ' AND (' . implode(' OR ', $parts) . ')';
    }
    if ($killGender !== null) {
        $allKillSql .= ' AND k.gender = :kill_gender_total';
        $allKillParams[':kill_gender_total'] = $killGender;
    }
    if ($killEthical === '1' || $killEthical === '0') {
        $allKillSql .= ' AND k.ethical = :kill_ethical_total';
        $allKillParams[':kill_ethical_total'] = $killEthical === '1';
    }
    if ($killScoreMin !== null && is_numeric($killScoreMin)) {
        $allKillSql .= ' AND k.score >= :kill_score_min_total';
        $allKillParams[':kill_score_min_total'] = $killScoreMin;
    }
    if ($killScoreMax !== null && is_numeric($killScoreMax)) {
        $allKillSql .= ' AND k.score <= :kill_score_max_total';
        $allKillParams[':kill_score_max_total'] = $killScoreMax;
    }
    if ($killDistanceMin !== null && is_numeric($killDistanceMin)) {
        $allKillSql .= ' AND COALESCE((SELECT MIN(hf.distance) FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id), 0) >= :kill_distance_min_total_raw';
        $allKillParams[':kill_distance_min_total_raw'] = (float) $killDistanceMin * 1000.0;
    }
    if ($killDistanceMax !== null && is_numeric($killDistanceMax)) {
        $allKillSql .= ' AND COALESCE((SELECT MIN(hf.distance) FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id), 0) <= :kill_distance_max_total_raw';
        $allKillParams[':kill_distance_max_total_raw'] = (float) $killDistanceMax * 1000.0;
    }
    if ($killWeightMin !== null && is_numeric($killWeightMin)) {
        $allKillSql .= ' AND k.weight >= :kill_weight_min_total';
        $allKillParams[':kill_weight_min_total'] = (float) $killWeightMin * 1000.0;
    }
    if ($killWeightMax !== null && is_numeric($killWeightMax)) {
        $allKillSql .= ' AND k.weight <= :kill_weight_max_total';
        $allKillParams[':kill_weight_max_total'] = (float) $killWeightMax * 1000.0;
    }
    if ($killIntegrityMin !== null && is_numeric($killIntegrityMin)) {
        $allKillSql .= ' AND k.trophy_integrity >= :kill_integrity_min_total';
        $allKillParams[':kill_integrity_min_total'] = (float) $killIntegrityMin;
    }
    if ($killIntegrityMax !== null && is_numeric($killIntegrityMax)) {
        $allKillSql .= ' AND k.trophy_integrity <= :kill_integrity_max_total';
        $allKillParams[':kill_integrity_max_total'] = (float) $killIntegrityMax;
    }
    if ($killHarvestMin !== null && is_numeric($killHarvestMin)) {
        $allKillSql .= ' AND k.harvest_value >= :kill_harvest_min_total';
        $allKillParams[':kill_harvest_min_total'] = $killHarvestMin;
    }
    if ($killHarvestMax !== null && is_numeric($killHarvestMax)) {
        $allKillSql .= ' AND k.harvest_value <= :kill_harvest_max_total';
        $allKillParams[':kill_harvest_max_total'] = $killHarvestMax;
    }
    if ($hitWeaponId !== null) {
        $allKillSql .= ' AND EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id AND hf.weapon_id = :hit_weapon_id_total)';
        $allKillParams[':hit_weapon_id_total'] = $hitWeaponId;
    }
    if ($hitAmmoId !== null) {
        $allKillSql .= ' AND EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id AND hf.ammo_id = :hit_ammo_id_total)';
        $allKillParams[':hit_ammo_id_total'] = $hitAmmoId;
    }
    if ($hitOrgan !== null) {
        $allKillSql .= ' AND EXISTS (SELECT 1 FROM gpt.exp_hits hf WHERE hf.kill_id = k.kill_id AND hf.organ = :hit_organ_total)';
        $allKillParams[':hit_organ_total'] = $hitOrgan;
    }

    $allKillRowsForTotals = app_query_all($allKillSql, $allKillParams);
    $killsPerExpTotals = [];
    $expHasPhotoTotals = [];
    $expHasTaxTotals = [];
    foreach ($allKillRowsForTotals as $krowForCount) {
        $eid = (int) ($krowForCount['expedition_id'] ?? 0);
        if ($eid <= 0) {
            continue;
        }
        if (!isset($killsPerExpTotals[$eid])) {
            $killsPerExpTotals[$eid] = 0;
        }
        $killsPerExpTotals[$eid]++;

        $scoreRaw = $krowForCount['score'] ?? null;
        if ($scoreRaw === null || $scoreRaw === '' || !is_numeric((string) $scoreRaw)) {
            continue;
        }
        $scoreNumeric = (float) $scoreRaw;
        $speciesCandidates = [
            (string) ($krowForCount['species_name_es'] ?? ''),
            (string) ($krowForCount['species_name'] ?? ''),
        ];
        foreach ($speciesCandidates as $candidate) {
            $skey = species_key_normalized($candidate);
            if ($skey === '' || !isset($ppftBySpecies[$skey])) {
                continue;
            }
            $thresholds = $ppftBySpecies[$skey];
            $tax = $thresholds['tax'] ?? null;
            $foto = $thresholds['foto'] ?? null;
            if ($tax !== null && $scoreNumeric >= (float) $tax) {
                $totalTaxVista++;
                $expHasTaxTotals[$eid] = true;
                break;
            }
            if ($foto !== null && $scoreNumeric >= (float) $foto) {
                $totalFotoVista++;
                $expHasPhotoTotals[$eid] = true;
            }
            break;
        }
    }

    $expIdsForTotals = array_keys($killsPerExpTotals);
    if ($photoTaxFilter === 't') {
        $expIdsForTotals = array_values(array_filter($expIdsForTotals, static fn (int $eid): bool => isset($expHasTaxTotals[$eid])));
    } elseif ($photoTaxFilter === 'f') {
        $expIdsForTotals = array_values(array_filter($expIdsForTotals, static fn (int $eid): bool => isset($expHasPhotoTotals[$eid])));
    } elseif ($photoTaxFilter === 'ft') {
        $expIdsForTotals = array_values(array_filter($expIdsForTotals, static fn (int $eid): bool => isset($expHasPhotoTotals[$eid]) || isset($expHasTaxTotals[$eid])));
    }
    if ($photoTaxFilter !== '') {
        $totalExpedicionesVista = count($expIdsForTotals);
    }
    $expSetForTotals = array_flip($expIdsForTotals);
    if ($photoTaxFilter === '') {
        $totalMuertesVista = count($allKillRowsForTotals);
    } else {
        foreach ($killsPerExpTotals as $eid => $killCount) {
            if (isset($expSetForTotals[$eid])) {
                $totalMuertesVista += (int) $killCount;
            }
        }
    }

    echo '<div class="table-head-wrap">';
    echo '<div class="totals-inline-right">';
    echo '<span><strong>Total de Expediciones:</strong> <span class="totals-value-red">' . h((string) $totalExpedicionesVista) . '</span></span>';
    echo '<span><strong>Total de Muertes:</strong> <span class="totals-value-red">' . h((string) $totalMuertesVista) . '</span></span>';
    echo '<span><strong>Foto/Taxidermia:</strong> <span class="totals-value-red">' . h((string) $totalFotoVista) . 'F ' . h((string) $totalTaxVista) . 'T</span></span>';
    echo '</div>';

    echo '<table><thead><tr>';
    foreach ($selectedCols as $key) {
        echo '<th data-col-key="' . h($key) . '">' . sort_link($key, $columnDefs[$key]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '<th>Muertes</th>';
    echo '</tr></thead><tbody>';
    $isKillMmp = static function (array $krow) use ($bestScoreByUserSpecies, $bestScoreKillByUserSpecies): bool {
        $score = $krow['score'] ?? null;
        $killId = $krow['kill_id'] ?? null;
        $uid = $krow['user_id'] ?? null;
        $sid = $krow['species_id'] ?? null;
        if (!is_numeric((string) $uid) || !is_numeric((string) $sid)) {
            return false;
        }
        $key = ((int) $uid) . ':' . ((int) $sid);
        if (isset($bestScoreKillByUserSpecies[$key]) && is_numeric((string) $killId)) {
            return ((int) $killId) === ((int) $bestScoreKillByUserSpecies[$key]);
        }
        if (!is_numeric((string) $score) || !isset($bestScoreByUserSpecies[$key])) {
            return false;
        }
        return ((float) $score) >= (((float) $bestScoreByUserSpecies[$key]) - 0.0001);
    };
    $isKillMmd = static function (array $krow) use ($bestDistanceByUserSpecies, $bestDistanceKillByUserSpecies): bool {
        $distanceM = $krow['hit_min_distance'] ?? null;
        $killId = $krow['kill_id'] ?? null;
        $uid = $krow['user_id'] ?? null;
        $sid = $krow['species_id'] ?? null;
        if (!is_numeric((string) $uid) || !is_numeric((string) $sid)) {
            return false;
        }
        $key = ((int) $uid) . ':' . ((int) $sid);
        if (isset($bestDistanceKillByUserSpecies[$key]) && is_numeric((string) $killId)) {
            return ((int) $killId) === ((int) $bestDistanceKillByUserSpecies[$key]);
        }
        if (!is_numeric((string) $distanceM) || !isset($bestDistanceByUserSpecies[$key])) {
            return false;
        }
        return ((float) $distanceM) >= (((float) $bestDistanceByUserSpecies[$key]) - 0.0005);
    };
    $isKillBestMark = static function (array $krow) use ($isKillMmp, $isKillMmd): bool {
        return $isKillMmp($krow) || $isKillMmd($krow);
    };
    $killPhotoTaxBadge = static function (array $krow) use ($ppftBySpecies): string {
        $scoreRaw = $krow['score'] ?? null;
        if ($scoreRaw === null || $scoreRaw === '' || !is_numeric((string) $scoreRaw)) {
            return '';
        }
        $scoreNumeric = (float) $scoreRaw;
        $speciesCandidates = [
            (string) ($krow['species_name_es'] ?? ''),
            (string) ($krow['species_name'] ?? ''),
        ];
        foreach ($speciesCandidates as $candidate) {
            $skey = species_key_normalized($candidate);
            if ($skey === '' || !isset($ppftBySpecies[$skey])) {
                continue;
            }
            $thresholds = $ppftBySpecies[$skey];
            $tax = $thresholds['tax'] ?? null;
            $foto = $thresholds['foto'] ?? null;
            if ($tax !== null && $scoreNumeric >= (float) $tax) {
                return 'T';
            }
            if ($foto !== null && $scoreNumeric >= (float) $foto) {
                return 'F';
            }
            return '';
        }
        return '';
    };
    $combineBadges = static function (array $parts): string {
        $parts = array_values(array_filter($parts, static fn ($p) => is_string($p) && $p !== ''));
        if ($parts === []) {
            return '';
        }
        return implode('/', $parts);
    };
    foreach ($rows as $row) {
        $expId = (int) ($row['_exp_id'] ?? 0);
        $killRows = $killsByExpedition[$expId] ?? [];
        $expBadgeColor = null;
        $expHasPhotoOrTax = false;
        $expHasPhoto = false;
        $expHasTax = false;
        $expHasMmp = false;
        $expHasMmd = false;
        $expHasBestMark = false;
        if ($killRows !== []) {
            foreach ($killRows as $krowForExp) {
                if (!$expHasMmp && $isKillMmp($krowForExp)) {
                    $expHasMmp = true;
                }
                if (!$expHasMmd && $isKillMmd($krowForExp)) {
                    $expHasMmd = true;
                }
                if (!$expHasBestMark && $isKillBestMark($krowForExp)) {
                    $expHasBestMark = true;
                }
                $ptBadge = $killPhotoTaxBadge($krowForExp);
                if ($ptBadge === 'T') {
                    $expHasTax = true;
                    $expHasPhotoOrTax = true;
                    $expBadgeColor = '#1f9d55';
                } elseif ($ptBadge === 'F') {
                    $expHasPhoto = true;
                    $expHasPhotoOrTax = true;
                    if ($expBadgeColor === null) {
                        $expBadgeColor = '#d97706';
                    }
                }
                $scoreRaw = $krowForExp['score'] ?? null;
                if ($scoreRaw === null || $scoreRaw === '' || !is_numeric((string) $scoreRaw)) {
                    continue;
                }
                $scoreNumeric = (float) $scoreRaw;
                $speciesCandidates = [
                    (string) ($krowForExp['species_name_es'] ?? ''),
                    (string) ($krowForExp['species_name'] ?? ''),
                ];
                foreach ($speciesCandidates as $candidate) {
                    $skey = species_key_normalized($candidate);
                    if ($skey === '' || !isset($ppftBySpecies[$skey])) {
                        continue;
                    }
                    $thresholds = $ppftBySpecies[$skey];
                    $tax = $thresholds['tax'] ?? null;
                    $foto = $thresholds['foto'] ?? null;
                    if ($tax !== null && $scoreNumeric >= (float) $tax) {
                        $expBadgeColor = '#1f9d55';
                        $expHasPhotoOrTax = true;
                        break 2;
                    }
                    if ($expBadgeColor === null && $foto !== null && $scoreNumeric >= (float) $foto) {
                        $expBadgeColor = '#d97706';
                        $expHasPhotoOrTax = true;
                    }
                    break;
                }
            }
        }
        if ($photoTaxFilter === 't' && !$expHasTax) {
            continue;
        }
        if ($photoTaxFilter === 'f' && !$expHasPhoto) {
            continue;
        }
        if ($photoTaxFilter === 'ft' && !($expHasPhoto || $expHasTax)) {
            continue;
        }
        if ($markFilter === 'mmd' && !$expHasMmd) {
            continue;
        }
        if ($markFilter === 'mmp' && !$expHasMmp) {
            continue;
        }
        if ($markFilter === 'any' && !$expHasBestMark) {
            continue;
        }
        echo '<tr>';
        foreach ($selectedCols as $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['e_start_at', 'e_end_at'], true)) {
                $value = format_datetime_display($value);
            }
            $mainCellStyle = '';
            if ($key === 'e_expedition_id' && $expBadgeColor !== null) {
                $mainCellStyle = ' style="color:' . h($expBadgeColor) . ';font-weight:700"';
            }
            $cellText = h($value === null ? '' : (string) $value);
            $expPlayerRaw = trim((string) ($row['e_player_name'] ?? ($killRows[0]['player_name'] ?? '')));
            $expPlayerSlug = $expPlayerRaw !== '' ? rawurlencode(strtolower($expPlayerRaw)) : '';
            if ($key === 'e_expedition_id' && $expHasBestMark && $cellText !== '') {
                $badge = 'MM';
                if ($expHasMmp && $expHasMmd) {
                    $badge = 'MMP/MMD';
                } elseif ($expHasMmp) {
                    $badge = 'MMP';
                } elseif ($expHasMmd) {
                    $badge = 'MMD';
                }
                $pt = $expHasTax ? 'T' : ($expHasPhoto ? 'F' : '');
                $badge = $combineBadges([$badge, $pt]);
                $cellText = '<span style="color:#c53030;font-weight:700">' . h($badge) . '</span> ' . $cellText;
            } elseif ($key === 'e_expedition_id' && $cellText !== '' && ($expHasTax || $expHasPhoto)) {
                $pt = $expHasTax ? 'T' : 'F';
                $cellText = '<span style="color:#c53030;font-weight:700">' . h($pt) . '</span> ' . $cellText;
            }
            if ($key === 'e_expedition_id' && $cellText !== '' && $expPlayerSlug !== '') {
                $expeditionIdValue = trim((string) ($row['e_expedition_id'] ?? ''));
                if ($expeditionIdValue !== '') {
                    $expUrl = 'https://www.thehunter.com/#profile/' . $expPlayerSlug . '/expedition/' . rawurlencode($expeditionIdValue);
                    $cellText = '<a class="record-link record-link-exp" href="' . h($expUrl) . '" target="_blank" rel="noopener noreferrer">' . $cellText . '</a>';
                }
            }
            echo '<td data-col-key="' . h($key) . '"' . $mainCellStyle . '>' . $cellText . '</td>';
        }
        echo '<td>';
        if ($killRows === []) {
            echo '<span class="muted">Sin muertes</span>';
        } else {
            $expOpenAttr = (isset($openExpSet[$expId]) || $expHasPhotoOrTax || $expHasMmp || $expHasMmd) ? ' open' : '';
            echo '<details class="exp-kills-details" data-exp-id="' . h((string) $expId) . '"' . $expOpenAttr . '><summary>Ver muertes (' . h((string) count($killRows)) . ')</summary>';
            echo '<table><thead><tr>';
            foreach ($selectedKillCols as $colKey) {
                $thStyle = isset($killNumericCols[$colKey]) ? ' style="text-align:right"' : '';
                $label = $killColumnDefs[$colKey] ?? $colKey;
                if ($colKey === 'hit_details' || !isset($killSortDefs[$colKey])) {
                    echo '<th data-col-key="k_' . h($colKey) . '"' . $thStyle . '>' . h($label) . '</th>';
                } else {
                    echo '<th data-col-key="k_' . h($colKey) . '"' . $thStyle . '>' . sort_link_param('k_sort', 'k_dir', $colKey, $label, $killSortKey, $killSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($killRows as $krow) {
                $currentKillId = (int) ($krow['kill_id'] ?? 0);
                $hitRows = $hitsByKill[$currentKillId] ?? [];
                echo '<tr>';
                foreach ($selectedKillCols as $colKey) {
                    if ($colKey === 'hit_details') {
                        echo '<td>';
                        if ($hitRows === []) {
                            echo '<span class="muted">Sin disparos</span>';
                        } else {
                            $killOpenAttr = isset($openKillSet[$currentKillId]) ? ' open' : '';
                            echo '<details class="kill-hits-details" data-kill-id="' . h((string) $currentKillId) . '"' . $killOpenAttr . '><summary>Ver disparos (' . h((string) count($hitRows)) . ')</summary>';
                            echo '<table><thead><tr>';
                            foreach ($selectedHitCols as $hcolKey) {
                                $hitThStyle = isset($hitNumericCols[$hcolKey]) ? ' style="text-align:right"' : '';
                                $hlabel = $hitColumnDefs[$hcolKey] ?? $hcolKey;
                                if (!isset($hitSortDefs[$hcolKey])) {
                                    echo '<th data-col-key="h_' . h($hcolKey) . '"' . $hitThStyle . '>' . h($hlabel) . '</th>';
                                } else {
                                    echo '<th data-col-key="h_' . h($hcolKey) . '"' . $hitThStyle . '>' . sort_link_param('h_sort', 'h_dir', $hcolKey, $hlabel, $hitSortKey, $hitSortDir) . '</th>';
                                }
                            }
                            echo '</tr></thead><tbody>';
                            foreach ($hitRows as $hrow) {
                                echo '<tr>';
                                foreach ($selectedHitCols as $hcolKey) {
                                    $value = $hrow[$hcolKey] ?? '';
                                    if ($hcolKey === 'player_name' && ($value === null || $value === '')) {
                                        $value = $hrow['user_id'] ?? '';
                                    }
                                    if ($hcolKey === 'distance' && $value !== null && $value !== '' && is_numeric((string) $value)) {
                                        $value = number_format(((float) $value) / 1000, 3, '.', '');
                                    }
                                    $hitTdStyle = isset($hitNumericCols[$hcolKey]) ? ' style="text-align:right"' : '';
                                    echo '<td data-col-key="h_' . h($hcolKey) . '"' . $hitTdStyle . '>' . h($value === null ? '' : (string) $value) . '</td>';
                                }
                                echo '</tr>';
                            }
                            echo '</tbody></table>';
                            echo '</details>';
                        }
                        echo '</td>';
                        continue;
                    }

                    $value = $krow[$colKey] ?? '';
                    $scoreCellColor = null;
                    if ($colKey === 'species_name' && ($value === null || $value === '')) {
                        $value = $krow['species_id'] ?? '';
                    }
                    if ($colKey === 'species_name_es' && ($value === null || $value === '')) {
                        $value = $krow['species_name'] ?? ($krow['species_id'] ?? '');
                    }
                    if ($colKey === 'hits_count' && ($value === null || $value === '')) {
                        $value = '0';
                    }
                    if ($value !== null && $value !== '' && is_numeric((string) $value)) {
                        if ($colKey === 'score') {
                            $scoreNumeric = (float) $value;
                            $speciesCandidates = [
                                (string) ($krow['species_name_es'] ?? ''),
                                (string) ($krow['species_name'] ?? ''),
                            ];
                            foreach ($speciesCandidates as $candidate) {
                                $skey = species_key_normalized($candidate);
                                if ($skey === '' || !isset($ppftBySpecies[$skey])) {
                                    continue;
                                }
                                $thresholds = $ppftBySpecies[$skey];
                                $tax = $thresholds['tax'] ?? null;
                                $foto = $thresholds['foto'] ?? null;
                                if ($tax !== null && $scoreNumeric >= (float) $tax) {
                                    $scoreCellColor = '#1f9d55';
                                } elseif ($foto !== null && $scoreNumeric >= (float) $foto) {
                                    $scoreCellColor = '#d97706';
                                }
                                break;
                            }
                            $value = number_format($scoreNumeric, 4, '.', '');
                        } elseif ($colKey === 'harvest_value') {
                            $value = number_format((float) $value, 3, '.', '');
                        } elseif ($colKey === 'trophy_integrity') {
                            $value = number_format((float) $value, 2, '.', '');
                        } elseif ($colKey === 'weight') {
                            $value = number_format(((float) $value) / 1000, 3, '.', '');
                        }
                    }
                    if ($colKey === 'confirm_at') {
                        $value = format_datetime_display($value);
                    }
                    $killTdStyle = isset($killNumericCols[$colKey]) ? ' style="text-align:right"' : '';
                    if ($colKey === 'score' && $scoreCellColor !== null) {
                        $killTdStyle = ' style="text-align:right;color:' . h($scoreCellColor) . ';font-weight:700"';
                    }
                    if ($colKey === 'gender') {
                        $genderText = '';
                        if ((string) $value === '0') {
                            $genderText = 'M';
                        } elseif ((string) $value === '1') {
                            $genderText = 'F';
                        } else {
                            $genderText = ($value === null ? '' : (string) $value);
                        }
                        echo '<td data-col-key="k_' . h($colKey) . '"' . $killTdStyle . '>' . h($genderText) . '</td>';
                        continue;
                    }
                    if ($colKey === 'ethical') {
                        $isEthical = in_array((string) $value, ['1', 'true', 't'], true);
                        $icon = $isEthical ? '&#10004;' : '&#10008;';
                        $color = $isEthical ? '#1f9d55' : '#c53030';
                        echo '<td data-col-key="k_' . h($colKey) . '"' . $killTdStyle . '><span style="color:' . h($color) . ';font-weight:700">' . $icon . '</span></td>';
                        continue;
                    }
                    $killText = h($value === null ? '' : (string) $value);
                    if ($colKey === 'kill_id' && $killText !== '' && $isKillBestMark($krow)) {
                        $killBadge = 'MM';
                        if ($isKillMmp($krow) && $isKillMmd($krow)) {
                            $killBadge = 'MMP/MMD';
                        } elseif ($isKillMmp($krow)) {
                            $killBadge = 'MMP';
                        } elseif ($isKillMmd($krow)) {
                            $killBadge = 'MMD';
                        }
                        $killText = '<span style="color:#c53030;font-weight:700">' . h($killBadge) . '</span> ' . $killText;
                    }
                    if ($colKey === 'score' && $killText !== '') {
                        $scoreBadge = '';
                        if ($isKillMmp($krow)) {
                            $scoreBadge = 'MMP';
                        }
                        $pt = $killPhotoTaxBadge($krow);
                        $scoreBadge = $combineBadges([$scoreBadge, $pt]);
                        if ($scoreBadge !== '') {
                            $killText = '<span style="color:#c53030;font-weight:700">' . h($scoreBadge) . '</span> ' . $killText;
                        }
                    }
                    if ($colKey === 'hit_min_distance' && $killText !== '' && $isKillMmd($krow)) {
                        $killText = '<span style="color:#c53030;font-weight:700">MMD</span> ' . $killText;
                    }
                    if (in_array($colKey, ['species_name', 'species_name_es'], true) && $killText !== '') {
                        $iconSpeciesName = (string) ($krow['species_name_es'] ?? $krow['species_name'] ?? '');
                        $killText = gender_species_icon_html($iconSpeciesName, $krow['gender'] ?? null) . $killText;
                    }
                    if ($colKey === 'kill_id' && $killText !== '') {
                        $killPlayerRaw = trim((string) ($krow['player_name'] ?? ($row['e_player_name'] ?? '')));
                        if ($killPlayerRaw !== '') {
                            $killPlayerSlug = rawurlencode(strtolower($killPlayerRaw));
                            $killIdValue = trim((string) ($krow['kill_id'] ?? ''));
                            if ($killIdValue !== '') {
                                $killUrl = 'https://www.thehunter.com/#profile/' . $killPlayerSlug . '/score/' . rawurlencode($killIdValue);
                                $killText = '<a class="record-link record-link-kill" href="' . h($killUrl) . '" target="_blank" rel="noopener noreferrer">' . $killText . '</a>';
                            }
                        }
                    }
                    echo '<td data-col-key="k_' . h($colKey) . '"' . $killTdStyle . '>' . $killText . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</details>';
        }
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_best(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);
    $globalRank = query_int('global_rank');
    $playerNames = default_player_filter(query_list('player_name'), 'player_name');
    $hunterScore = query_int('hunter_score');
    $speciesNames = query_list('species_name_es');
    $bestScore = query_text('best_score_value');
    $bestDistance = query_text('best_distance_m');

    $columnDefs = [
        'global_rank' => ['expr' => 'e.global_rank', 'sort' => 'COALESCE(e.global_rank, 999999)', 'label' => 'Rank'],
        'player_name' => ['expr' => 'b.player_name', 'sort' => 'b.player_name', 'label' => 'Jugador'],
        'hunter_score' => ['expr' => 'e.hunter_score', 'sort' => 'COALESCE(e.hunter_score, -1)', 'label' => 'Hunter Score'],
        'species_name_es' => ['expr' => 'b.species_name_es', 'sort' => 'b.species_name_es', 'label' => 'Especie'],
        'best_score_value' => ['expr' => 'b.best_score_value', 'sort' => 'COALESCE(b.best_score_value, -1)', 'label' => 'Mejor Puntuacion'],
        'best_distance_m' => ['expr' => 'b.best_distance_m', 'sort' => 'COALESCE(b.best_distance_m, -1)', 'label' => 'Best Distance'],
    ];
    $defaultCols = ['global_rank', 'player_name', 'hunter_score', 'species_name_es', 'best_score_value', 'best_distance_m'];
    $selectedCols = [];
    $hasChoice = false;
    foreach ($columnDefs as $key => $_def) {
        if (array_key_exists('bcol_' . $key, $_GET)) {
            $hasChoice = true;
            $raw = $_GET['bcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedCols[] = $key;
            }
        }
    }
    if (!$hasChoice || $selectedCols === []) {
        $selectedCols = $defaultCols;
    }
    $dragOrderRaw = query_text('bcol_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedCols, true)));
        foreach ($selectedCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCols = $ordered;
        }
    }

    $where = [];
    $params = [];

    if ($globalRank !== null) {
        $where[] = 'e.global_rank = :global_rank';
        $params[':global_rank'] = $globalRank;
    }
    if ($playerNames !== []) {
        $parts = [];
        foreach ($playerNames as $idx => $name) {
            $ph = ':player_name_' . $idx;
            $parts[] = 'b.player_name = ' . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($hunterScore !== null) {
        $where[] = 'e.hunter_score = :hunter_score';
        $params[':hunter_score'] = $hunterScore;
    }
    if ($speciesNames !== []) {
        $parts = [];
        foreach ($speciesNames as $idx => $name) {
            $ph = ':species_name_es_' . $idx;
            $parts[] = 'b.species_name_es = ' . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($bestScore !== null) {
        $where[] = 'CAST(b.best_score_value AS TEXT) ILIKE :best_score_value';
        $params[':best_score_value'] = '%' . $bestScore . '%';
    }
    if ($bestDistance !== null) {
        $where[] = 'CAST(b.best_distance_m AS TEXT) ILIKE :best_distance_m';
        $params[':best_distance_m'] = '%' . $bestDistance . '%';
    }

    $sortable = [];
    foreach ($selectedCols as $key) {
        $sortable[$key] = $columnDefs[$key]['sort'];
    }
    $defaultSort = in_array('global_rank', $selectedCols, true) ? 'global_rank' : $selectedCols[0];
    [$sortKey, $sortDir] = query_sort($defaultSort, 'asc', $sortable);

    $selectParts = [];
    foreach ($selectedCols as $key) {
        $selectParts[] = $columnDefs[$key]['expr'] . ' AS ' . quote_ident($key);
    }

    $sql = 'SELECT ' . implode(', ', $selectParts) . '
            FROM gpt.best_personal_records b
            LEFT JOIN gpt.est_profiles e ON e.user_id = b.user_id';
    $countSql = 'SELECT COUNT(*) AS c
                 FROM gpt.best_personal_records b
                 LEFT JOIN gpt.est_profiles e ON e.user_id = b.user_id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', b.player_name ASC, b.species_name_es ASC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $headers = array_map(static fn (string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('best_records.csv', $headers, $rows, $selectedCols);
    }

    $topBySpecies = [];
    $topRows = app_query_all(
        'SELECT b.species_name_es,
                MAX(COALESCE(b.best_score_value, -1)) AS top_score,
                MAX(COALESCE(b.best_distance_m, -1)) AS top_distance
         FROM gpt.best_personal_records b
         GROUP BY b.species_name_es'
    );
    foreach ($topRows as $trow) {
        $speciesKey = trim((string) ($trow['species_name_es'] ?? ''));
        if ($speciesKey === '') {
            continue;
        }
        $topBySpecies[$speciesKey] = [
            'score' => is_numeric((string) ($trow['top_score'] ?? null)) ? (float) $trow['top_score'] : null,
            'distance' => is_numeric((string) ($trow['top_distance'] ?? null)) ? (float) $trow['top_distance'] : null,
        ];
    }

    $topPCount = 0;
    $topDCount = 0;
    $topCountSql = 'SELECT b.species_name_es, b.best_score_value, b.best_distance_m
                    FROM gpt.best_personal_records b
                    LEFT JOIN gpt.est_profiles e ON e.user_id = b.user_id';
    if ($where !== []) {
        $topCountSql .= ' WHERE ' . implode(' AND ', $where);
    }
    foreach (app_query_all($topCountSql, $params) as $crow) {
        $speciesKey = trim((string) ($crow['species_name_es'] ?? ''));
        if ($speciesKey === '' || !isset($topBySpecies[$speciesKey])) {
            continue;
        }
        $tops = $topBySpecies[$speciesKey];
        $score = $crow['best_score_value'] ?? null;
        $distance = $crow['best_distance_m'] ?? null;
        if (is_numeric((string) $score) && is_numeric((string) ($tops['score'] ?? null)) && ((float) $score >= ((float) $tops['score'] - 0.0001))) {
            $topPCount++;
        }
        if (is_numeric((string) $distance) && is_numeric((string) ($tops['distance'] ?? null)) && ((float) $distance >= ((float) $tops['distance'] - 0.0005))) {
            $topDCount++;
        }
    }

    $sql .= ' LIMIT :_limit OFFSET :_offset';

    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    echo '<section class="card"><h2>Mejores Marcas</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="best">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="bcol_order" value="' . h(query_raw('bcol_order')) . '">';
    echo '<input type="text" name="global_rank" placeholder="Rank" value="' . h(query_raw('global_rank')) . '">';
    echo '<select name="player_name[]">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="hunter_score" placeholder="Hunter Score" value="' . h(query_raw('hunter_score')) . '">';
    echo '<select name="species_name_es[]">';
    echo '<option value="">Especie (todas)</option>';
    foreach (species_es_name_suggestions() as $name) {
        $selected = in_array($name, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="best_score_value" placeholder="Mejor Puntuacion" value="' . h(query_raw('best_score_value')) . '">';
    echo '<input type="text" name="best_distance_m" placeholder="Best Distance" value="' . h(query_raw('best_distance_m')) . '">';
    echo '<details class="filter-details visible-columns" data-col-prefix="bcol_" data-order-field="bcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="bcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=best&reset=1">Limpiar</a>';
    echo '</form>';

    $rows = app_query_all(
        $sql,
        $params + [
            ':_limit' => $pageSize,
            ':_offset' => $offset,
        ]
    );
    echo '<div class="table-head-wrap">';
    echo '<div class="totals-inline-right">';
    echo '<span><strong>N? Top\'s D:</strong> <span class="totals-value-red">' . h((string) $topDCount) . '</span></span>';
    echo '<span><strong>N? Top\'s P:</strong> <span class="totals-value-red">' . h((string) $topPCount) . '</span></span>';
    echo '</div>';
    echo '<table><thead><tr>';
    foreach ($selectedCols as $key) {
        echo '<th>' . sort_link($key, $columnDefs[$key]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $speciesKey = trim((string) ($row['species_name_es'] ?? ''));
        $tops = $topBySpecies[$speciesKey] ?? ['score' => null, 'distance' => null];
        $score = $row['best_score_value'] ?? null;
        $distance = $row['best_distance_m'] ?? null;
        $isTopScore = is_numeric((string) $score) && is_numeric((string) $tops['score']) && ((float) $score >= ((float) $tops['score'] - 0.0001));
        $isTopDistance = is_numeric((string) $distance) && is_numeric((string) $tops['distance']) && ((float) $distance >= ((float) $tops['distance'] - 0.0005));

        $rowClass = ($isTopScore || $isTopDistance) ? ' class="best-species-row"' : '';
        echo '<tr' . $rowClass . '>';
        foreach ($selectedCols as $key) {
            $cell = h((string) ($row[$key] ?? ''));
            if ($key === 'best_score_value' && $isTopScore && $cell !== '') {
                $cell = '<span class="best-species-badge">TOP P</span> ' . $cell;
                echo '<td class="best-species-score">' . $cell . '</td>';
                continue;
            }
            if ($key === 'best_distance_m' && $isTopDistance && $cell !== '') {
                $cell = '<span class="best-species-badge">TOP D</span> ' . $cell;
                echo '<td class="best-species-distance">' . $cell . '</td>';
                continue;
            }
            if ($key === 'species_name_es' && ($isTopScore || $isTopDistance) && $cell !== '') {
                $tags = [];
                if ($isTopScore) {
                    $tags[] = 'P';
                }
                if ($isTopDistance) {
                    $tags[] = 'D';
                }
                $cell = '<span class="best-species-tag">[' . h(implode('/', $tags)) . ']</span> ' . $cell;
            }
            echo '<td>' . $cell . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_species_ppft(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);

    $tableCandidates = ['gpt.tab_especiesppft', 'gpt.tab_speciesftpp', 'gpt.tab_especiesppft_stg'];
    $sourceTable = null;
    foreach ($tableCandidates as $candidate) {
        $reg = app_query_one('SELECT to_regclass(:tbl) AS t', [':tbl' => $candidate]);
        if (!empty($reg['t'])) {
            $sourceTable = $candidate;
            break;
        }
    }
    if ($sourceTable === null) {
        echo '<section class="card"><h2>Tabla Especies PPFT</h2><p class="muted">No existe ninguna tabla fuente (gpt.tab_especiesppft / gpt.tab_speciesftpp / gpt.tab_especiesppft_stg).</p></section>';
        return;
    }

    [$srcSchema, $srcTable] = str_contains($sourceTable, '.') ? explode('.', $sourceTable, 2) : ['gpt', $sourceTable];
    $rawCols = app_query_all(
        "SELECT column_name
         FROM information_schema.columns
         WHERE table_schema = :schema AND table_name = :table
         ORDER BY ordinal_position",
        [':schema' => $srcSchema, ':table' => $srcTable]
    );
    $cols = [];
    foreach ($rawCols as $crow) {
        $name = trim((string) ($crow['column_name'] ?? ''));
        if ($name !== '') {
            $cols[strtolower($name)] = $name;
        }
    }
    if ($cols === []) {
        echo '<section class="card"><h2>Tabla Especies PPFT</h2><p class="muted">La tabla existe pero no tiene columnas visibles.</p></section>';
        return;
    }

    $findCol = static function (array $candidates) use ($cols): ?string {
        foreach ($candidates as $candidate) {
            $k = strtolower($candidate);
            if (isset($cols[$k])) {
                return $cols[$k];
            }
        }
        return null;
    };

    $mapped = [
        'especie' => $findCol(['especie', 'species', 'animal']),
        'foto_min' => $findCol(['foto_min', 'foto']),
        'foto_max' => $findCol(['foto_max']),
        'tax_min' => $findCol(['tax_min', 'tax']),
        'tax_max' => $findCol(['tax_max']),
        'peso_max_kg' => $findCol(['peso_max_kg', 'peso_max', 'peso']),
        'score_min' => $findCol(['score_min', 'score min', 'scoremin']),
        'score_max' => $findCol(['score_max', 'score max', 'scoremax']),
        'fuente' => $findCol(['fuente', 'source']),
        'updated_at' => $findCol(['updated_at', 'fecha_actualizacion']),
    ];

    $expr = static function (?string $col, string $fallback = "''"): string {
        if ($col === null) {
            return $fallback;
        }
        return 't.' . quote_ident($col);
    };

    $numExpr = static function (?string $col) use ($expr): string {
        if ($col === null) {
            return 'NULL';
        }
        $raw = $expr($col, 'NULL') . '::text';
        $clean = "REGEXP_REPLACE(REPLACE(" . $raw . ", ',', '.'), '[^0-9\.\-]', '', 'g')";
        return "(CASE WHEN " . $clean . " ~ '^-?[0-9]+(\.[0-9]+)?$' THEN (" . $clean . ")::double precision ELSE NULL END)";
    };

    $columnDefs = [
        'especie' => ['expr' => $expr($mapped['especie']), 'sort' => $expr($mapped['especie']), 'label' => 'Especie'],
        'foto_min' => ['expr' => $expr($mapped['foto_min'], 'NULL'), 'sort' => 'COALESCE(' . $numExpr($mapped['foto_min']) . ', -1)', 'label' => 'Foto Min'],
        'foto_max' => ['expr' => $expr($mapped['foto_max'], 'NULL'), 'sort' => 'COALESCE(' . $numExpr($mapped['foto_max']) . ', -1)', 'label' => 'Foto Max'],
        'tax_min' => ['expr' => $expr($mapped['tax_min'], 'NULL'), 'sort' => 'COALESCE(' . $numExpr($mapped['tax_min']) . ', -1)', 'label' => 'Tax Min'],
        'tax_max' => ['expr' => $expr($mapped['tax_max'], 'NULL'), 'sort' => 'COALESCE(' . $numExpr($mapped['tax_max']) . ', -1)', 'label' => 'Tax Max'],
        'peso_max_kg' => ['expr' => $expr($mapped['peso_max_kg'], 'NULL'), 'sort' => 'COALESCE(' . $numExpr($mapped['peso_max_kg']) . ', -1)', 'label' => 'Peso Max (kg)'],
        'score_min' => ['expr' => $expr($mapped['score_min'], 'NULL'), 'sort' => 'COALESCE(' . $numExpr($mapped['score_min']) . ', -1)', 'label' => 'Puntuacion'],
        'score_max' => ['expr' => $expr($mapped['score_max'], 'NULL'), 'sort' => 'COALESCE(' . $numExpr($mapped['score_max']) . ', -1)', 'label' => 'Puntuacion'],
        'fuente' => ['expr' => $expr($mapped['fuente']), 'sort' => $expr($mapped['fuente']), 'label' => 'Fuente'],
        'updated_at' => ['expr' => $expr($mapped['updated_at'], 'NULL'), 'sort' => $expr($mapped['updated_at'], 'NULL'), 'label' => 'Actualizado'],
    ];

    $defaultCols = ['especie', 'foto_min', 'foto_max', 'tax_min', 'tax_max', 'peso_max_kg', 'score_min', 'score_max', 'fuente', 'updated_at'];
    $selectedCols = [];
    $hasChoice = false;
    foreach ($columnDefs as $key => $_def) {
        if (array_key_exists('spcol_' . $key, $_GET)) {
            $hasChoice = true;
            $raw = $_GET['spcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedCols[] = $key;
            }
        }
    }
    if (!$hasChoice || $selectedCols === []) {
        $selectedCols = $defaultCols;
    }
    $dragOrderRaw = query_text('spcol_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedCols, true)));
        foreach ($selectedCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCols = $ordered;
        }
    }

    $especie = query_text('especie');
    $fotoMin = query_text('foto_min');
    $fotoMax = query_text('foto_max');
    $taxMin = query_text('tax_min');
    $taxMax = query_text('tax_max');
    $pesoMaxKg = query_text('peso_max_kg');
    $scoreMin = query_text('score_min');
    $scoreMax = query_text('score_max');

    $where = [];
    $params = [];
    if ($especie !== null && $mapped['especie'] !== null) {
        $where[] = 'CAST(' . $expr($mapped['especie']) . ' AS TEXT) ILIKE :especie';
        $params[':especie'] = '%' . $especie . '%';
    }
    if ($fotoMin !== null && is_numeric($fotoMin) && $mapped['foto_min'] !== null) {
        $where[] = 'COALESCE(' . $numExpr($mapped['foto_min']) . ', -1) >= :foto_min';
        $params[':foto_min'] = (float) $fotoMin;
    }
    if ($fotoMax !== null && is_numeric($fotoMax) && $mapped['foto_max'] !== null) {
        $where[] = 'COALESCE(' . $numExpr($mapped['foto_max']) . ', -1) <= :foto_max';
        $params[':foto_max'] = (float) $fotoMax;
    }
    if ($taxMin !== null && is_numeric($taxMin) && $mapped['tax_min'] !== null) {
        $where[] = 'COALESCE(' . $numExpr($mapped['tax_min']) . ', -1) >= :tax_min';
        $params[':tax_min'] = (float) $taxMin;
    }
    if ($taxMax !== null && is_numeric($taxMax) && $mapped['tax_max'] !== null) {
        $where[] = 'COALESCE(' . $numExpr($mapped['tax_max']) . ', -1) <= :tax_max';
        $params[':tax_max'] = (float) $taxMax;
    }
    if ($pesoMaxKg !== null && is_numeric($pesoMaxKg) && $mapped['peso_max_kg'] !== null) {
        $where[] = 'COALESCE(' . $numExpr($mapped['peso_max_kg']) . ', -1) <= :peso_max_kg';
        $params[':peso_max_kg'] = (float) $pesoMaxKg;
    }
    if ($scoreMin !== null && is_numeric($scoreMin) && $mapped['score_min'] !== null) {
        $where[] = 'COALESCE(' . $numExpr($mapped['score_min']) . ', -1) >= :score_min';
        $params[':score_min'] = (float) $scoreMin;
    }
    if ($scoreMax !== null && is_numeric($scoreMax) && $mapped['score_max'] !== null) {
        $where[] = 'COALESCE(' . $numExpr($mapped['score_max']) . ', -1) <= :score_max';
        $params[':score_max'] = (float) $scoreMax;
    }

    $sortable = [];
    foreach ($selectedCols as $key) {
        $sortable[$key] = $columnDefs[$key]['sort'];
    }
    $defaultSort = in_array('especie', $selectedCols, true) ? 'especie' : $selectedCols[0];
    [$sortKey, $sortDir] = query_sort($defaultSort, 'asc', $sortable);

    $selectParts = [];
    foreach ($selectedCols as $key) {
        $selectParts[] = $columnDefs[$key]['expr'] . ' AS ' . quote_ident($key);
    }
    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM ' . $sourceTable . ' t';
    $countSql = 'SELECT COUNT(*) AS c FROM ' . $sourceTable . ' t';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir);

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $headers = array_map(static fn (string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('tab_especiesppft.csv', $headers, $rows, $selectedCols);
    }

    $sql .= ' LIMIT :_limit OFFSET :_offset';
    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;
    $rows = app_query_all($sql, $params + [':_limit' => $pageSize, ':_offset' => $offset]);

    echo '<section class="card"><h2>Tabla Especies PPFT</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="species_ppft">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="spcol_order" value="' . h(query_raw('spcol_order')) . '">';
    echo '<input type="text" name="especie" placeholder="Especie" value="' . h(query_raw('especie')) . '">';
    echo '<input type="text" name="foto_min" placeholder="Foto min" value="' . h(query_raw('foto_min')) . '">';
    echo '<input type="text" name="foto_max" placeholder="Foto max" value="' . h(query_raw('foto_max')) . '">';
    echo '<input type="text" name="tax_min" placeholder="Tax min" value="' . h(query_raw('tax_min')) . '">';
    echo '<input type="text" name="tax_max" placeholder="Tax max" value="' . h(query_raw('tax_max')) . '">';
    echo '<input type="text" name="peso_max_kg" placeholder="Peso max (kg)" value="' . h(query_raw('peso_max_kg')) . '">';
    echo '<input type="text" name="score_min" placeholder="Puntuacion" value="' . h(query_raw('score_min')) . '">';
    echo '<input type="text" name="score_max" placeholder="Puntuacion" value="' . h(query_raw('score_max')) . '">';
    echo '<details class="filter-details visible-columns" data-col-prefix="spcol_" data-order-field="spcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="spcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=species_ppft&reset=1">Limpiar</a>';
    echo '</form>';

    echo '<table><thead><tr>';
    foreach ($selectedCols as $key) {
        echo '<th>' . sort_link($key, $columnDefs[$key]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($selectedCols as $key) {
            $value = $row[$key] ?? '';
            if ($key === 'updated_at') {
                $value = format_datetime_display($value);
            }
            echo '<td>' . h($value === null ? '' : (string) $value) . '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_profiles(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);

    $columnDefs = [
        'user_id' => ['expr' => 'p.user_id', 'sort' => 'p.user_id', 'label' => 'IdUsuario'],
        'global_rank' => ['expr' => 'p.global_rank', 'sort' => 'COALESCE(p.global_rank, 999999)', 'label' => 'Rank'],
        'player_name' => ['expr' => 'p.player_name', 'sort' => 'p.player_name', 'label' => 'Jugador'],
        'hunter_score' => ['expr' => 'p.hunter_score', 'sort' => 'COALESCE(p.hunter_score, -1)', 'label' => 'Hunter Score'],
        'duration' => ['expr' => 'p.duration', 'sort' => 'COALESCE(p.duration, -1)', 'label' => 'Duracion'],
        'distance' => ['expr' => '(p.distance::numeric / 1000.0)', 'sort' => 'COALESCE(p.distance, -1)', 'label' => 'Distancia (km)'],
    ];
    $defaultCols = ['global_rank', 'player_name', 'hunter_score', 'duration', 'distance'];
    $selectedCols = [];
    $hasChoice = false;
    foreach ($columnDefs as $key => $_def) {
        if (array_key_exists('pcol_' . $key, $_GET)) {
            $hasChoice = true;
            $raw = $_GET['pcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedCols[] = $key;
            }
        }
    }
    if (!$hasChoice || $selectedCols === []) {
        $selectedCols = $defaultCols;
    }
    $dragOrderRaw = query_text('pcol_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedCols, true)));
        foreach ($selectedCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCols = $ordered;
        }
    }

    $animalColumnDefs = [
        'species_id' => 'IdEspecie',
        'species_name' => 'Especie',
        'kills' => 'Muertes',
        'ethical_kills' => 'Eticos',
    ];
    $defaultAnimalCols = ['species_id', 'species_name', 'kills', 'ethical_kills'];
    $selectedAnimalCols = [];
    $hasAnimalChoice = false;
    foreach ($animalColumnDefs as $key => $_label) {
        if (array_key_exists('pacol_' . $key, $_GET)) {
            $hasAnimalChoice = true;
            $raw = $_GET['pacol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedAnimalCols[] = $key;
            }
        }
    }
    if (!$hasAnimalChoice || $selectedAnimalCols === []) {
        $selectedAnimalCols = $defaultAnimalCols;
    }
    $dragAnimalOrderRaw = query_text('pacol_order');
    if ($dragAnimalOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragAnimalOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedAnimalCols, true)));
        foreach ($selectedAnimalCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedAnimalCols = $ordered;
        }
    }
    $selectedAnimalCols = order_selected_keys($selectedAnimalCols, 'ord_pacol_');

    $weaponColumnDefs = [
        'weapon_id' => 'Weapon',
        'ammo_id' => 'Ammo',
        'shots' => 'Shots',
        'hits' => 'Disparos',
        'kills' => 'Muertes',
    ];
    $defaultWeaponCols = ['weapon_id', 'ammo_id', 'shots', 'hits', 'kills'];
    $selectedWeaponCols = [];
    $hasWeaponChoice = false;
    foreach ($weaponColumnDefs as $key => $_label) {
        if (array_key_exists('pwcol_' . $key, $_GET)) {
            $hasWeaponChoice = true;
            $raw = $_GET['pwcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedWeaponCols[] = $key;
            }
        }
    }
    if (!$hasWeaponChoice || $selectedWeaponCols === []) {
        $selectedWeaponCols = $defaultWeaponCols;
    }
    $dragWeaponOrderRaw = query_text('pwcol_order');
    if ($dragWeaponOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragWeaponOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedWeaponCols, true)));
        foreach ($selectedWeaponCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedWeaponCols = $ordered;
        }
    }
    $selectedWeaponCols = order_selected_keys($selectedWeaponCols, 'ord_pwcol_');

    $collectColumnDefs = [
        'collectable_id' => 'Collectable ID',
        'collected' => 'Collected',
        'max_value' => 'Max',
        'sum_value' => 'Suma',
        'max_id' => 'Max ID',
    ];
    $defaultCollectCols = ['collectable_id', 'collected', 'max_value', 'sum_value', 'max_id'];
    $selectedCollectCols = [];
    $hasCollectChoice = false;
    foreach ($collectColumnDefs as $key => $_label) {
        if (array_key_exists('pccol_' . $key, $_GET)) {
            $hasCollectChoice = true;
            $raw = $_GET['pccol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedCollectCols[] = $key;
            }
        }
    }
    if (!$hasCollectChoice || $selectedCollectCols === []) {
        $selectedCollectCols = $defaultCollectCols;
    }
    $dragCollectOrderRaw = query_text('pccol_order');
    if ($dragCollectOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragCollectOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedCollectCols, true)));
        foreach ($selectedCollectCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCollectCols = $ordered;
        }
    }
    $selectedCollectCols = order_selected_keys($selectedCollectCols, 'ord_pccol_');

    $missionColumnDefs = [
        'mission_id' => 'Mission ID',
        'mission_value' => 'Valor',
        'points' => 'Puntos',
    ];
    $defaultMissionCols = ['mission_id', 'mission_value', 'points'];
    $selectedMissionCols = [];
    $hasMissionChoice = false;
    foreach ($missionColumnDefs as $key => $_label) {
        if (array_key_exists('pmcol_' . $key, $_GET)) {
            $hasMissionChoice = true;
            $raw = $_GET['pmcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedMissionCols[] = $key;
            }
        }
    }
    if (!$hasMissionChoice || $selectedMissionCols === []) {
        $selectedMissionCols = $defaultMissionCols;
    }
    $dragMissionOrderRaw = query_text('pmcol_order');
    if ($dragMissionOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragMissionOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedMissionCols, true)));
        foreach ($selectedMissionCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedMissionCols = $ordered;
        }
    }
    $selectedMissionCols = order_selected_keys($selectedMissionCols, 'ord_pmcol_');

    $animalSortDefs = [
        'species_id' => 'COALESCE(a.species_id, -1)',
        'species_name' => 'COALESCE(s.especie_es, s.especie, a.species_id::text)',
        'kills' => 'COALESCE(a.kills, -1)',
        'ethical_kills' => 'COALESCE(a.ethical_kills, -1)',
    ];
    [$animalSortKey, $animalSortDir] = query_sort_param('pa_sort', 'pa_dir', 'kills', 'desc', $animalSortDefs);

    $weaponSortDefs = [
        'weapon_id' => 'COALESCE(w.weapon_id, -1)',
        'ammo_id' => 'COALESCE(w.ammo_id, -1)',
        'shots' => '(COALESCE(w.hits, 0) + COALESCE(w.misses, 0))',
        'hits' => 'COALESCE(w.hits, -1)',
        'kills' => 'COALESCE(w.kills, -1)',
    ];
    [$weaponSortKey, $weaponSortDir] = query_sort_param('pw_sort', 'pw_dir', 'kills', 'desc', $weaponSortDefs);

    $collectSortDefs = [
        'collectable_id' => 'COALESCE(c.collectable_id, -1)',
        'collected' => 'COALESCE(c.collected, -1)',
        'max_value' => 'COALESCE(c.max_value, -1)',
        'sum_value' => 'COALESCE(c.sum_value, -1)',
        'max_id' => 'COALESCE(c.max_id, -1)',
    ];
    [$collectSortKey, $collectSortDir] = query_sort_param('pc_sort', 'pc_dir', 'collectable_id', 'asc', $collectSortDefs);

    $missionPointsExpr = "CASE WHEN COALESCE(m.raw_json->>'points','') ~ '^-?[0-9]+(\\.[0-9]+)?$' THEN (m.raw_json->>'points')::numeric ELSE NULL END";
    $missionSortDefs = [
        'mission_id' => 'COALESCE(m.mission_id, -1)',
        'mission_value' => 'COALESCE(m.mission_value, -1)',
        'points' => 'COALESCE((' . $missionPointsExpr . '), -1)',
    ];
    [$missionSortKey, $missionSortDir] = query_sort_param('pm_sort', 'pm_dir', 'mission_id', 'asc', $missionSortDefs);

    $globalRank = query_int('global_rank');
    $playerNames = default_player_filter(query_list('player_name'), 'player_name');
    $hunterScore = query_int('hunter_score');
    $duration = query_text('duration');
    $distance = query_text('distance');
    $animalSpeciesTerm = query_text('animal_species_id');
    $animalKillsMin = query_int('animal_kills_min');
    $weaponId = query_int('weapon_id');
    $weaponAmmoId = query_int('weapon_ammo_id');
    $weaponKillsMin = query_int('weapon_kills_min');

    $autoRecommended = false;
    $hasExplicitProfileFilters = false;
    $profileFilterKeys = [
        'global_rank',
        'player_name',
        'hunter_score',
        'duration',
        'distance',
        'animal_species_id',
        'animal_kills_min',
        'weapon_id',
        'weapon_ammo_id',
        'weapon_kills_min',
    ];
    foreach ($profileFilterKeys as $filterKey) {
        if (!array_key_exists($filterKey, $_GET)) {
            continue;
        }
        $raw = $_GET[$filterKey];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $hasExplicitProfileFilters = true;
                    break 2;
                }
            }
            continue;
        }
        if (is_string($raw) && trim($raw) !== '') {
            $hasExplicitProfileFilters = true;
            break;
        }
    }
    if (!$hasExplicitProfileFilters && !is_reset_requested() && !app_is_admin_user()) {
        $authUser = app_auth_username();
        if (is_string($authUser) && $authUser !== '') {
            $playerNames = [$authUser];
            if ($animalKillsMin === null) {
                $animalKillsMin = 1;
            }
            if ($weaponKillsMin === null) {
                $weaponKillsMin = 1;
            }
            $autoRecommended = true;
        }
    }

    $where = [];
    $params = [];
    if ($globalRank !== null) {
        $where[] = 'p.global_rank = :global_rank';
        $params[':global_rank'] = $globalRank;
    }
    if ($playerNames !== []) {
        $parts = [];
        foreach ($playerNames as $idx => $name) {
            $ph = ':player_name_' . $idx;
            $parts[] = 'p.player_name = ' . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($hunterScore !== null) {
        $where[] = 'p.hunter_score = :hunter_score';
        $params[':hunter_score'] = $hunterScore;
    }
    if ($duration !== null) {
        $where[] = 'CAST(p.duration AS TEXT) ILIKE :duration';
        $params[':duration'] = '%' . $duration . '%';
    }
    if ($distance !== null) {
        $where[] = 'CAST((p.distance::numeric / 1000.0) AS TEXT) ILIKE :distance';
        $params[':distance'] = '%' . $distance . '%';
    }
    if ($animalSpeciesTerm !== null) {
        $where[] = 'EXISTS (
            SELECT 1
            FROM gpt.est_animal_stats a
            LEFT JOIN gpt.tab_especies s ON s.id_especie = a.species_id
            WHERE a.user_id = p.user_id
              AND (
                  CAST(a.species_id AS TEXT) ILIKE :animal_species_id
                  OR s.especie ILIKE :animal_species_id
                  OR s.especie_es ILIKE :animal_species_id
              )
        )';
        $params[':animal_species_id'] = '%' . $animalSpeciesTerm . '%';
    }
    if ($animalKillsMin !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.est_animal_stats a WHERE a.user_id = p.user_id AND COALESCE(a.kills, 0) >= :animal_kills_min)';
        $params[':animal_kills_min'] = $animalKillsMin;
    }
    if ($weaponId !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.est_weapon_stats w WHERE w.user_id = p.user_id AND w.weapon_id = :weapon_id)';
        $params[':weapon_id'] = $weaponId;
    }
    if ($weaponAmmoId !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.est_weapon_stats w WHERE w.user_id = p.user_id AND w.ammo_id = :weapon_ammo_id)';
        $params[':weapon_ammo_id'] = $weaponAmmoId;
    }
    if ($weaponKillsMin !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.est_weapon_stats w WHERE w.user_id = p.user_id AND COALESCE(w.kills, 0) >= :weapon_kills_min)';
        $params[':weapon_kills_min'] = $weaponKillsMin;
    }

    $sortable = [];
    foreach ($selectedCols as $key) {
        $sortable[$key] = $columnDefs[$key]['sort'];
    }
    $defaultSort = in_array('global_rank', $selectedCols, true) ? 'global_rank' : $selectedCols[0];
    [$sortKey, $sortDir] = query_sort($defaultSort, 'asc', $sortable);

    $selectParts = [];
    foreach ($selectedCols as $key) {
        $selectParts[] = $columnDefs[$key]['expr'] . ' AS ' . quote_ident($key);
    }
    if (!in_array('user_id', $selectedCols, true)) {
        $selectParts[] = 'p.user_id AS "__user_id"';
    }
    $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM gpt.est_profiles p';
    $countSql = 'SELECT COUNT(*) AS c FROM gpt.est_profiles p';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', p.player_name ASC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $headers = array_map(static fn (string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('estadisticas_publicas.csv', $headers, $rows, $selectedCols);
    }

    $sql .= ' LIMIT :_limit OFFSET :_offset';
    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    echo '<section class="card"><h2>Estadisticas Publicas</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="profiles">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="pcol_order" value="' . h(query_raw('pcol_order')) . '">';
    echo '<input type="hidden" name="pacol_order" value="' . h(query_raw('pacol_order')) . '">';
    echo '<input type="hidden" name="pwcol_order" value="' . h(query_raw('pwcol_order')) . '">';
    echo '<input type="hidden" name="pccol_order" value="' . h(query_raw('pccol_order')) . '">';
    echo '<input type="hidden" name="pmcol_order" value="' . h(query_raw('pmcol_order')) . '">';
    echo '<input type="hidden" name="pa_sort" value="' . h($animalSortKey) . '">';
    echo '<input type="hidden" name="pa_dir" value="' . h($animalSortDir) . '">';
    echo '<input type="hidden" name="pw_sort" value="' . h($weaponSortKey) . '">';
    echo '<input type="hidden" name="pw_dir" value="' . h($weaponSortDir) . '">';
    echo '<input type="hidden" name="pc_sort" value="' . h($collectSortKey) . '">';
    echo '<input type="hidden" name="pc_dir" value="' . h($collectSortDir) . '">';
    echo '<input type="hidden" name="pm_sort" value="' . h($missionSortKey) . '">';
    echo '<input type="hidden" name="pm_dir" value="' . h($missionSortDir) . '">';
    echo '<input type="text" name="global_rank" placeholder="Rank" value="' . h(query_raw('global_rank')) . '">';
    echo '<select name="player_name[]">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="hunter_score" placeholder="Hunter Score" value="' . h(query_raw('hunter_score')) . '">';
    echo '<input type="text" name="duration" placeholder="Duracion" value="' . h(query_raw('duration')) . '">';
    echo '<input type="text" name="distance" placeholder="Distancia (km)" value="' . h(query_raw('distance')) . '">';
    echo '<input type="text" name="animal_species_id" placeholder="Animal species_id" value="' . h(query_raw('animal_species_id')) . '">';
    echo '<input type="text" name="animal_kills_min" placeholder="Animal kills min" value="' . h($animalKillsMin !== null ? (string) $animalKillsMin : query_raw('animal_kills_min')) . '">';
    echo '<input type="text" name="weapon_id" placeholder="Weapon ID" value="' . h(query_raw('weapon_id')) . '">';
    echo '<input type="text" name="weapon_ammo_id" placeholder="Weapon ammo ID" value="' . h(query_raw('weapon_ammo_id')) . '">';
    echo '<input type="text" name="weapon_kills_min" placeholder="Weapon kills min" value="' . h($weaponKillsMin !== null ? (string) $weaponKillsMin : query_raw('weapon_kills_min')) . '">';
    if ($autoRecommended) {
        echo '<span class="muted">Filtro recomendado activo segun tus estadisticas de caza.</span>';
    }
    echo '<details class="filter-details visible-columns" data-col-prefix="pcol_" data-order-field="pcol_order"><summary>Columnas Estadisticas</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="pcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="pacol_" data-order-field="pacol_order"><summary>Columnas Estadisticas Especies</summary><div class="visible-row">';
    foreach ($animalColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedAnimalCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="pacol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultAnimalCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="pwcol_" data-order-field="pwcol_order"><summary>Columnas Estadisticas Armas</summary><div class="visible-row">';
    foreach ($weaponColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedWeaponCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="pwcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultWeaponCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="pccol_" data-order-field="pccol_order"><summary>Columnas Estadisticas Coleccionables</summary><div class="visible-row">';
    foreach ($collectColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedCollectCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="pccol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCollectCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="pmcol_" data-order-field="pmcol_order"><summary>Columnas Estadisticas Misiones</summary><div class="visible-row">';
    foreach ($missionColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedMissionCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="pmcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultMissionCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=profiles&reset=1">Limpiar</a>';
    echo '<datalist id="species_options">';
    foreach (species_suggestions() as $value) {
        echo '<option value="' . h($value) . '"></option>';
    }
    echo '</datalist>';
    echo '</form>';

    $rows = app_query_all($sql, $params + [':_limit' => $pageSize, ':_offset' => $offset]);
    $userIds = [];
    foreach ($rows as $row) {
        $uidRaw = $row['user_id'] ?? $row['__user_id'] ?? null;
        if ($uidRaw !== null && is_numeric((string) $uidRaw)) {
            $userIds[] = (int) $uidRaw;
        }
    }
    $userIds = array_values(array_unique($userIds));
    $animalsByUser = [];
    $weaponsByUser = [];
    $collectablesByUser = [];
    $missionsByUser = [];
    if ($userIds !== []) {
        $inParts = [];
        $subParams = [];
        foreach ($userIds as $idx => $id) {
            $ph = ':u_' . $idx;
            $inParts[] = $ph;
            $subParams[$ph] = $id;
        }
        $animalSql = 'SELECT a.user_id, a.species_id, COALESCE(s.especie_es, s.especie, a.species_id::text) AS species_name, a.kills, a.ethical_kills
                      FROM gpt.est_animal_stats a
                      LEFT JOIN gpt.tab_especies s ON s.id_especie = a.species_id
                      WHERE a.user_id IN (' . implode(', ', $inParts) . ')
                      ORDER BY a.user_id, ' . $animalSortDefs[$animalSortKey] . ' ' . strtoupper($animalSortDir) . ', a.species_id';
        foreach (app_query_all($animalSql, $subParams) as $arow) {
            $animalsByUser[(int) $arow['user_id']][] = $arow;
        }
        $weaponSql = 'SELECT w.user_id, w.weapon_id, w.ammo_id,
                             (COALESCE(w.hits, 0) + COALESCE(w.misses, 0)) AS shots,
                             w.hits, w.kills
                      FROM gpt.est_weapon_stats w
                      WHERE w.user_id IN (' . implode(', ', $inParts) . ')
                      ORDER BY w.user_id, ' . $weaponSortDefs[$weaponSortKey] . ' ' . strtoupper($weaponSortDir) . ', w.weapon_id, w.ammo_id';
        foreach (app_query_all($weaponSql, $subParams) as $wrow) {
            $weaponsByUser[(int) $wrow['user_id']][] = $wrow;
        }
        $collectSql = 'SELECT c.user_id, c.collectable_id, c.collected, c.max_value, c.sum_value, c.max_id
                       FROM gpt.est_collectables c
                       WHERE c.user_id IN (' . implode(', ', $inParts) . ')
                       ORDER BY c.user_id, ' . $collectSortDefs[$collectSortKey] . ' ' . strtoupper($collectSortDir) . ', c.collectable_id';
        foreach (app_query_all($collectSql, $subParams) as $crow) {
            $collectablesByUser[(int) $crow['user_id']][] = $crow;
        }
        $missionSql = 'SELECT m.user_id, m.mission_id, m.mission_value, ' . $missionPointsExpr . ' AS points
                       FROM gpt.est_daily_missions m
                       WHERE m.user_id IN (' . implode(', ', $inParts) . ')
                       ORDER BY m.user_id, ' . $missionSortDefs[$missionSortKey] . ' ' . strtoupper($missionSortDir) . ', m.mission_id';
        foreach (app_query_all($missionSql, $subParams) as $mrow) {
            $missionsByUser[(int) $mrow['user_id']][] = $mrow;
        }
    }

    echo '<table><thead><tr>';
    foreach ($selectedCols as $key) {
        echo '<th>' . sort_link($key, $columnDefs[$key]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '<th>Detalle</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $uid = (int) ($row['user_id'] ?? $row['__user_id'] ?? 0);
        echo '<tr>';
        foreach ($selectedCols as $key) {
            $value = $row[$key] ?? '';
            if ($key === 'distance' && $value !== null && $value !== '' && is_numeric((string) $value)) {
                $value = number_format((float) $value, 3, '.', '');
            }
            echo '<td>' . h((string) $value) . '</td>';
        }
        echo '<td>';
        echo '<div class="subtable-panels stats-parallel">';
        echo '<details class="stats-animal"><summary>Animal stats (' . h((string) count($animalsByUser[$uid] ?? [])) . ')</summary>';
        if (($animalsByUser[$uid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table><thead><tr>';
            foreach ($selectedAnimalCols as $colKey) {
                if (!isset($animalSortDefs[$colKey])) {
                    echo '<th>' . h($animalColumnDefs[$colKey] ?? $colKey) . '</th>';
                } else {
                    echo '<th>' . sort_link_param('pa_sort', 'pa_dir', $colKey, $animalColumnDefs[$colKey] ?? $colKey, $animalSortKey, $animalSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($animalsByUser[$uid] as $arow) {
                echo '<tr>';
                foreach ($selectedAnimalCols as $colKey) {
                    echo '<td>' . h((string) ($arow[$colKey] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</details>';
        echo '<details class="stats-weapon"><summary>Weapon stats (' . h((string) count($weaponsByUser[$uid] ?? [])) . ')</summary>';
        if (($weaponsByUser[$uid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table><thead><tr>';
            foreach ($selectedWeaponCols as $colKey) {
                if (!isset($weaponSortDefs[$colKey])) {
                    echo '<th>' . h($weaponColumnDefs[$colKey] ?? $colKey) . '</th>';
                } else {
                    echo '<th>' . sort_link_param('pw_sort', 'pw_dir', $colKey, $weaponColumnDefs[$colKey] ?? $colKey, $weaponSortKey, $weaponSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($weaponsByUser[$uid] as $wrow) {
                echo '<tr>';
                foreach ($selectedWeaponCols as $colKey) {
                    echo '<td>' . h((string) ($wrow[$colKey] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</details>';
        echo '<details class="stats-collect"><summary>Coleccionables (' . h((string) count($collectablesByUser[$uid] ?? [])) . ')</summary>';
        if (($collectablesByUser[$uid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table><thead><tr>';
            foreach ($selectedCollectCols as $colKey) {
                if (!isset($collectSortDefs[$colKey])) {
                    echo '<th>' . h($collectColumnDefs[$colKey] ?? $colKey) . '</th>';
                } else {
                    echo '<th>' . sort_link_param('pc_sort', 'pc_dir', $colKey, $collectColumnDefs[$colKey] ?? $colKey, $collectSortKey, $collectSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($collectablesByUser[$uid] as $crow) {
                echo '<tr>';
                foreach ($selectedCollectCols as $colKey) {
                    echo '<td>' . h((string) ($crow[$colKey] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</details>';
        echo '<details class="stats-mission"><summary>Misiones diarias (' . h((string) count($missionsByUser[$uid] ?? [])) . ')</summary>';
        if (($missionsByUser[$uid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table><thead><tr>';
            foreach ($selectedMissionCols as $colKey) {
                if (!isset($missionSortDefs[$colKey])) {
                    echo '<th>' . h($missionColumnDefs[$colKey] ?? $colKey) . '</th>';
                } else {
                    echo '<th>' . sort_link_param('pm_sort', 'pm_dir', $colKey, $missionColumnDefs[$colKey] ?? $colKey, $missionSortKey, $missionSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($missionsByUser[$uid] as $mrow) {
                echo '<tr>';
                foreach ($selectedMissionCols as $colKey) {
                    echo '<td>' . h((string) ($mrow[$colKey] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</details>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_competitions(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);
    $compTypeCols = gpt_table_columns('comp_types');
    $hasTypeAttempts = isset($compTypeCols['attempts']);
    $hasTypePointType = isset($compTypeCols['point_type']);
    $pointTypeIsBoolean = (($compTypeCols['point_type'] ?? '') === 'boolean');
    $hasTypeDescriptionEs = isset($compTypeCols['description_es']);
    $attemptsExpr = $hasTypeAttempts
        ? 't.attempts'
        : "CASE WHEN COALESCE(t.raw_json->>'attempts','') ~ '^-?[0-9]+$' THEN (t.raw_json->>'attempts')::int ELSE NULL END";
    $pointTypeExpr = $hasTypePointType
        ? 't.point_type'
        : "CASE WHEN COALESCE(t.raw_json->>'point_type','') ~ '^-?[0-9]+$' THEN (t.raw_json->>'point_type')::int ELSE NULL END";
    $descriptionEsExpr = $hasTypeDescriptionEs
        ? 't.description_es'
        : "COALESCE(t.raw_json->>'description_es','')";

    $columnDefs = [
        'competition_id' => ['expr' => 'c.competition_id', 'sort' => 'c.competition_id', 'label' => 'ID'],
        'competition_type_id' => ['expr' => 'c.competition_type_id', 'sort' => 'c.competition_type_id', 'label' => 'Type ID'],
        'type_name' => ['expr' => 't.type_name', 'sort' => 't.type_name', 'label' => 'Nombre'],
        'entrants' => ['expr' => 'c.entrants', 'sort' => 'COALESCE(c.entrants, -1)', 'label' => 'Entrants'],
        'finished' => ['expr' => 'c.finished', 'sort' => 'c.finished', 'label' => 'Finalizada'],
        'start_at' => ['expr' => 'c.start_at', 'sort' => 'c.start_at', 'label' => 'Inicio'],
        'end_at' => ['expr' => 'c.end_at', 'sort' => 'c.end_at', 'label' => 'Fin'],
    ];
    $defaultCols = ['competition_id', 'type_name', 'entrants', 'finished', 'start_at', 'end_at'];
    $selectedCols = [];
    $hasChoice = false;
    foreach ($columnDefs as $key => $_def) {
        if (array_key_exists('ccol_' . $key, $_GET)) {
            $hasChoice = true;
            $raw = $_GET['ccol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedCols[] = $key;
            }
        }
    }
    if (!$hasChoice || $selectedCols === []) {
        $selectedCols = $defaultCols;
    }
    $dragOrderRaw = query_text('ccol_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedCols, true)));
        foreach ($selectedCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCols = $ordered;
        }
    }

    $speciesColumnDefs = [
        'species_id' => 'IdEspecie',
        'species_name' => 'Especie',
    ];
    $defaultSpeciesCols = ['species_id', 'species_name'];
    $selectedSpeciesCols = [];
    $hasSpeciesChoice = false;
    foreach ($speciesColumnDefs as $key => $_label) {
        if (array_key_exists('cscol_' . $key, $_GET)) {
            $hasSpeciesChoice = true;
            $raw = $_GET['cscol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedSpeciesCols[] = $key;
            }
        }
    }
    if (!$hasSpeciesChoice || $selectedSpeciesCols === []) {
        $selectedSpeciesCols = $defaultSpeciesCols;
    }
    $dragSpeciesOrderRaw = query_text('cscol_order');
    if ($dragSpeciesOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragSpeciesOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedSpeciesCols, true)));
        foreach ($selectedSpeciesCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedSpeciesCols = $ordered;
        }
    }
    $selectedSpeciesCols = order_selected_keys($selectedSpeciesCols, 'ord_cscol_');

    $rewardColumnDefs = [
        'prize_position' => 'Puesto',
        'reward_position' => 'Posicion',
        'reward_type' => 'Tipo',
        'reward_define' => 'Define',
        'reward_amount' => 'Cantidad',
    ];
    $defaultRewardCols = ['prize_position', 'reward_position', 'reward_type', 'reward_define', 'reward_amount'];
    $selectedRewardCols = [];
    $hasRewardChoice = false;
    foreach ($rewardColumnDefs as $key => $_label) {
        if (array_key_exists('crcol_' . $key, $_GET)) {
            $hasRewardChoice = true;
            $raw = $_GET['crcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedRewardCols[] = $key;
            }
        }
    }
    if (!$hasRewardChoice || $selectedRewardCols === []) {
        $selectedRewardCols = $defaultRewardCols;
    }
    $dragRewardOrderRaw = query_text('crcol_order');
    if ($dragRewardOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragRewardOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedRewardCols, true)));
        foreach ($selectedRewardCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedRewardCols = $ordered;
        }
    }
    $selectedRewardCols = order_selected_keys($selectedRewardCols, 'ord_crcol_');

    $speciesSortDefs = [
        'species_id' => 'COALESCE(ts.species_id, -1)',
        'species_name' => 'COALESCE(s.especie_es, s.especie, ts.species_id::text)',
    ];
    [$speciesSortKey, $speciesSortDir] = query_sort_param('cs_sort', 'cs_dir', 'species_name', 'asc', $speciesSortDefs);

    $rewardSortDefs = [
        'prize_position' => 'COALESCE(tr.prize_position, -1)',
        'reward_position' => 'COALESCE(tr.reward_position, -1)',
        'reward_type' => 'COALESCE(tr.reward_type, \'\')',
        'reward_define' => 'COALESCE(tr.reward_define, \'\')',
        'reward_amount' => 'COALESCE(tr.reward_amount, -1)',
    ];
    [$rewardSortKey, $rewardSortDir] = query_sort_param('cr_sort', 'cr_dir', 'prize_position', 'asc', $rewardSortDefs);

    $competitionId = query_int('competition_id');
    $typeName = query_text('type_name');
    $entrants = query_int('entrants');
    $finished = query_text('finished');
    if ($finished === null && !is_reset_requested()) {
        $finished = 'false';
    }
    $startAt = query_datetime_local('start_at');
    $endAt = query_datetime_local('end_at');
    $speciesNames = query_list('species_name_es');
    $rewardType = query_text('reward_type');
    $rewardDefine = query_text('reward_define');
    $prizePosition = query_int('prize_position');
    $attempts = query_int('attempts');
    $pointTypeRaw = query_text('point_type');
    $autoRecommendedSpecies = false;

    if ($speciesNames === [] && !array_key_exists('species_name_es', $_GET) && !is_reset_requested() && !app_is_admin_user()) {
        $authUser = app_auth_username();
        if (is_string($authUser) && $authUser !== '') {
            $recommended = recommended_species_for_player($authUser, 12);
            if ($recommended !== []) {
                $speciesNames = $recommended;
                $autoRecommendedSpecies = true;
            }
        }
    }

    $where = [];
    $params = [];
    if ($competitionId !== null) {
        $where[] = 'c.competition_id = :competition_id';
        $params[':competition_id'] = $competitionId;
    }
    if ($typeName !== null) {
        $where[] = 't.type_name ILIKE :type_name';
        $params[':type_name'] = '%' . $typeName . '%';
    }
    if ($entrants !== null) {
        $where[] = 'c.entrants = :entrants';
        $params[':entrants'] = $entrants;
    }
    if ($finished === 'true') {
        $where[] = 'c.finished IS TRUE';
    } elseif ($finished === 'false') {
        $where[] = 'c.finished IS FALSE';
    }
    if ($startAt !== null) {
        $where[] = 'c.start_at >= :start_at';
        $params[':start_at'] = $startAt;
    }
    if ($endAt !== null) {
        $where[] = 'c.end_at <= :end_at';
        $params[':end_at'] = $endAt;
    }
    if ($speciesNames !== []) {
        $parts = [];
        foreach ($speciesNames as $idx => $name) {
            $ph = ':species_name_es_' . $idx;
            $parts[] = 'EXISTS (
                SELECT 1
                FROM gpt.comp_type_species ts
                LEFT JOIN gpt.tab_especies s ON s.id_especie = ts.species_id
                WHERE ts.competition_type_id = c.competition_type_id
                  AND (s.especie_es = ' . $ph . ' OR s.especie = ' . $ph . ')
            )';
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($rewardType !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.comp_type_rewards tr WHERE tr.competition_type_id = c.competition_type_id AND tr.reward_type ILIKE :reward_type)';
        $params[':reward_type'] = '%' . $rewardType . '%';
    }
    if ($rewardDefine !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.comp_type_rewards tr WHERE tr.competition_type_id = c.competition_type_id AND tr.reward_define ILIKE :reward_define)';
        $params[':reward_define'] = '%' . $rewardDefine . '%';
    }
    if ($prizePosition !== null) {
        $where[] = 'EXISTS (SELECT 1 FROM gpt.comp_type_rewards tr WHERE tr.competition_type_id = c.competition_type_id AND tr.prize_position = :prize_position)';
        $params[':prize_position'] = $prizePosition;
    }
    if ($attempts !== null) {
        $where[] = $attemptsExpr . ' = :attempts';
        $params[':attempts'] = $attempts;
    }
    if ($pointTypeRaw !== null) {
        if ($pointTypeIsBoolean) {
            $pt = strtolower(trim($pointTypeRaw));
            if (in_array($pt, ['1', 'true', 'si', 'yes'], true)) {
                $where[] = $pointTypeExpr . ' IS TRUE';
            } elseif (in_array($pt, ['0', 'false', 'no'], true)) {
                $where[] = $pointTypeExpr . ' IS FALSE';
            }
        } elseif (preg_match('/^-?[0-9]+$/', $pointTypeRaw) === 1) {
            $where[] = $pointTypeExpr . ' = :point_type';
            $params[':point_type'] = (int) $pointTypeRaw;
        }
    }

    $sortable = [];
    foreach ($selectedCols as $key) {
        $sortable[$key] = $columnDefs[$key]['sort'];
    }
    $defaultSort = in_array('start_at', $selectedCols, true) ? 'start_at' : $selectedCols[0];
    [$sortKey, $sortDir] = query_sort($defaultSort, 'desc', $sortable);

    $selectParts = [];
    foreach ($selectedCols as $key) {
        $selectParts[] = $columnDefs[$key]['expr'] . ' AS ' . quote_ident($key);
    }
    if (!in_array('competition_type_id', $selectedCols, true)) {
        $selectParts[] = 'c.competition_type_id AS "__type_id"';
    }

    $sql = 'SELECT ' . implode(', ', $selectParts) . '
            FROM gpt.comp_competitions c
            LEFT JOIN gpt.comp_types t ON t.competition_type_id = c.competition_type_id';
    $countSql = 'SELECT COUNT(*) AS c
                 FROM gpt.comp_competitions c
                 LEFT JOIN gpt.comp_types t ON t.competition_type_id = c.competition_type_id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', c.competition_id DESC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $headers = array_map(static fn (string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('competiciones.csv', $headers, $rows, $selectedCols);
    }

    $sql .= ' LIMIT :_limit OFFSET :_offset';
    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    echo '<section class="card"><h2>Competiciones</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="competitions">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="ccol_order" value="' . h(query_raw('ccol_order')) . '">';
    echo '<input type="hidden" name="cscol_order" value="' . h(query_raw('cscol_order')) . '">';
    echo '<input type="hidden" name="crcol_order" value="' . h(query_raw('crcol_order')) . '">';
    echo '<input type="hidden" name="cs_sort" value="' . h($speciesSortKey) . '">';
    echo '<input type="hidden" name="cs_dir" value="' . h($speciesSortDir) . '">';
    echo '<input type="hidden" name="cr_sort" value="' . h($rewardSortKey) . '">';
    echo '<input type="hidden" name="cr_dir" value="' . h($rewardSortDir) . '">';
    echo '<input type="text" name="competition_id" placeholder="ID" value="' . h(query_raw('competition_id')) . '">';
    echo '<input type="text" name="type_name" placeholder="Nombre tipo" value="' . h(query_raw('type_name')) . '">';
    echo '<input type="text" name="entrants" placeholder="Entrants" value="' . h(query_raw('entrants')) . '">';
    $finishedUi = query_raw('finished');
    if ($finishedUi === '' && $finished === 'false') {
        $finishedUi = 'false';
    }
    echo '<select name="finished"><option value="">Finalizada</option><option value="true"' . ($finishedUi === 'true' ? ' selected' : '') . '>Si</option><option value="false"' . ($finishedUi === 'false' ? ' selected' : '') . '>No</option></select>';
    echo '<input type="datetime-local" name="start_at" title="Inicio desde" value="' . h(raw_to_datetime_local_value(query_raw('start_at'))) . '">';
    echo '<input type="datetime-local" name="end_at" title="Fin hasta" value="' . h(raw_to_datetime_local_value(query_raw('end_at'))) . '">';
    echo '<select name="species_name_es[]">';
    echo '<option value="">Especie ES (todas)</option>';
    foreach (species_es_name_suggestions() as $name) {
        $selected = in_array($name, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    if ($autoRecommendedSpecies) {
        echo '<span class="muted">Filtro recomendado activo segun tus estadisticas de caza.</span>';
    }
    echo '<input type="text" name="reward_type" placeholder="Reward type" value="' . h(query_raw('reward_type')) . '">';
    echo '<input type="text" name="reward_define" placeholder="Reward define" value="' . h(query_raw('reward_define')) . '">';
    echo '<input type="text" name="prize_position" placeholder="Puesto premio" value="' . h(query_raw('prize_position')) . '">';
    echo '<input type="text" name="attempts" placeholder="Attempts" value="' . h(query_raw('attempts')) . '">';
    echo '<input type="text" name="point_type" placeholder="Point type" value="' . h(query_raw('point_type')) . '">';
    echo '<details class="filter-details visible-columns" data-col-prefix="ccol_" data-order-field="ccol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="ccol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="cscol_" data-order-field="cscol_order"><summary>Columnas Estados Especies</summary><div class="visible-row">';
    foreach ($speciesColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedSpeciesCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="cscol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultSpeciesCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="crcol_" data-order-field="crcol_order"><summary>Columnas Premios</summary><div class="visible-row">';
    foreach ($rewardColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedRewardCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="crcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultRewardCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=competitions&reset=1">Limpiar</a>';
    echo '</form>';

    $rows = app_query_all($sql, $params + [':_limit' => $pageSize, ':_offset' => $offset]);
    $typeIds = [];
    foreach ($rows as $row) {
        $typeRaw = $row['competition_type_id'] ?? $row['__type_id'] ?? null;
        if ($typeRaw !== null && is_numeric((string) $typeRaw)) {
            $typeIds[] = (int) $typeRaw;
        }
    }
    $typeIds = array_values(array_unique($typeIds));
    $speciesByType = [];
    $rewardsByType = [];
    $typeById = [];
    if ($typeIds !== []) {
        $inParts = [];
        $subParams = [];
        foreach ($typeIds as $idx => $id) {
            $ph = ':t_' . $idx;
            $inParts[] = $ph;
            $subParams[$ph] = $id;
        }
        $typeSql = 'SELECT competition_type_id, type_name, description_short, ' . $descriptionEsExpr . ' AS description_es, singleplayer, entrant_rules, '
            . $attemptsExpr . ' AS attempts, ' . $pointTypeExpr . ' AS point_type
                    FROM gpt.comp_types t
                    WHERE competition_type_id IN (' . implode(', ', $inParts) . ')
                    ORDER BY competition_type_id';
        foreach (app_query_all($typeSql, $subParams) as $trow) {
            $typeById[(int) ($trow['competition_type_id'] ?? 0)] = $trow;
        }
        $speciesSql = 'SELECT ts.competition_type_id, ts.species_id, COALESCE(s.especie_es, s.especie, ts.species_id::text) AS species_name
                       FROM gpt.comp_type_species ts
                       LEFT JOIN gpt.tab_especies s ON s.id_especie = ts.species_id
                       WHERE ts.competition_type_id IN (' . implode(', ', $inParts) . ')
                       ORDER BY ts.competition_type_id, ' . $speciesSortDefs[$speciesSortKey] . ' ' . strtoupper($speciesSortDir) . ', ts.species_id';
        foreach (app_query_all($speciesSql, $subParams) as $srow) {
            $speciesByType[(int) $srow['competition_type_id']][] = $srow;
        }
        $rewardsSql = 'SELECT tr.competition_type_id, tr.prize_position, tr.reward_position, tr.reward_type, tr.reward_define, tr.reward_amount
                       FROM gpt.comp_type_rewards tr
                       WHERE tr.competition_type_id IN (' . implode(', ', $inParts) . ')
                       ORDER BY tr.competition_type_id, ' . $rewardSortDefs[$rewardSortKey] . ' ' . strtoupper($rewardSortDir) . ', tr.prize_position, tr.reward_position';
        foreach (app_query_all($rewardsSql, $subParams) as $rrow) {
            $rewardsByType[(int) $rrow['competition_type_id']][] = $rrow;
        }
    }

    echo '<table><thead><tr>';
    foreach ($selectedCols as $key) {
        echo '<th>' . sort_link($key, $columnDefs[$key]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '<th>Detalle</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $tid = (int) ($row['competition_type_id'] ?? $row['__type_id'] ?? 0);
        echo '<tr>';
        foreach ($selectedCols as $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['start_at', 'end_at'], true)) {
                $value = format_datetime_display($value);
            }
            if ($key === 'competition_id' && $value !== null && $value !== '') {
                $compIdTxt = (string) $value;
                $compUrl = 'https://www.thehunter.com/#competitions/details/' . rawurlencode($compIdTxt);
                echo '<td><a class="record-link" href="' . h($compUrl) . '" target="_blank" rel="noopener noreferrer">' . h($compIdTxt) . '</a></td>';
                continue;
            }
            echo '<td>' . h($value === null ? '' : (string) $value) . '</td>';
        }
        echo '<td>';
        echo '<div class="subtable-panels competitions-panels">';
        $typeRow = $typeById[$tid] ?? null;
        echo '<details class="comp-type-details"><summary>Tipo</summary>';
        if (!is_array($typeRow)) {
            echo '<span class="muted">Sin tipo vinculado (competition_type_id=' . h((string) $tid) . ')</span>';
        } else {
            $sp = ($typeRow['singleplayer'] ?? null);
            $er = ($typeRow['entrant_rules'] ?? null);
            $spTxt = ($sp === true || $sp === 't' || $sp === 1 || $sp === '1') ? 'Si' : 'No';
            $erTxt = ($er === true || $er === 't' || $er === 1 || $er === '1') ? 'Si' : 'No';
            echo '<table><thead><tr><th>Type ID</th><th>Nombre tipo</th><th>Descripcion ES</th><th>Descripcion original</th><th>Singleplayer</th><th>Entrant rules</th><th>Attempts</th><th>Point type</th></tr></thead><tbody>';
            echo '<tr>';
            echo '<td>' . h((string) ($typeRow['competition_type_id'] ?? '')) . '</td>';
            echo '<td>' . h((string) ($typeRow['type_name'] ?? '')) . '</td>';
            echo '<td>' . h((string) ($typeRow['description_es'] ?? '')) . '</td>';
            echo '<td>' . h((string) ($typeRow['description_short'] ?? '')) . '</td>';
            echo '<td>' . h($spTxt) . '</td>';
            echo '<td>' . h($erTxt) . '</td>';
            echo '<td class="num-cell">' . h((string) ($typeRow['attempts'] ?? '')) . '</td>';
            echo '<td class="num-cell">' . h((string) ($typeRow['point_type'] ?? '')) . '</td>';
            echo '</tr>';
            echo '</tbody></table>';
        }
        echo '</details>';
        echo '<details class="comp-species-details"><summary>Especies (' . h((string) count($speciesByType[$tid] ?? [])) . ')</summary>';
        if (($speciesByType[$tid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table><thead><tr>';
            foreach ($selectedSpeciesCols as $colKey) {
                if (!isset($speciesSortDefs[$colKey])) {
                    echo '<th>' . h($speciesColumnDefs[$colKey] ?? $colKey) . '</th>';
                } else {
                    echo '<th>' . sort_link_param('cs_sort', 'cs_dir', $colKey, $speciesColumnDefs[$colKey] ?? $colKey, $speciesSortKey, $speciesSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($speciesByType[$tid] as $srow) {
                echo '<tr>';
                foreach ($selectedSpeciesCols as $colKey) {
                    if ($colKey === 'species_icons') {
                        $sname = (string) ($srow['species_name'] ?? '');
                        echo '<td>' . species_icons_pair_html($sname) . '</td>';
                        continue;
                    }
                    echo '<td>' . h((string) ($srow[$colKey] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</details>';
        echo '<details class="comp-rewards-details"><summary>Rewards (' . h((string) count($rewardsByType[$tid] ?? [])) . ')</summary>';
        if (($rewardsByType[$tid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table><thead><tr>';
            foreach ($selectedRewardCols as $colKey) {
                if (!isset($rewardSortDefs[$colKey])) {
                    echo '<th>' . h($rewardColumnDefs[$colKey] ?? $colKey) . '</th>';
                } else {
                    echo '<th>' . sort_link_param('cr_sort', 'cr_dir', $colKey, $rewardColumnDefs[$colKey] ?? $colKey, $rewardSortKey, $rewardSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($rewardsByType[$tid] as $rrow) {
                $amount = $rrow['reward_amount'] ?? '';
                if ($amount !== null && $amount !== '' && is_numeric((string) $amount)) {
                    $amount = number_format((float) $amount, 2, '.', '');
                }
                $rowCopy = $rrow;
                $rowCopy['reward_amount'] = $amount;
                echo '<tr>';
                foreach ($selectedRewardCols as $colKey) {
                    echo '<td>' . h((string) ($rowCopy[$colKey] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</details>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_classifications(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);
    $leaderboardType = strtolower(query_text('leaderboard_type') ?? '');
    $speciesNames = query_list('species_name');
    $rankPos = query_int('rank_pos');
    $playerNames = default_player_filter(query_list('player_name'), 'player_name');
    $userId = query_int('user_id');
    $scoreValue = query_text('value_numeric');
    $distanceValue = query_text('distance_m');

    $where = [];
    $params = [];

    if (in_array($leaderboardType, ['score', 'range'], true)) {
        $where[] = 'leaderboard_type = :leaderboard_type';
        $params[':leaderboard_type'] = $leaderboardType;
    }
    if ($speciesNames !== []) {
        $parts = [];
        foreach ($speciesNames as $idx => $name) {
            $ph = ':species_name_' . $idx;
            $parts[] = '(species_name_es = ' . $ph . ' OR species_name = ' . $ph . ')';
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($rankPos !== null) {
        $where[] = 'rank_pos = :rank_pos';
        $params[':rank_pos'] = $rankPos;
    }
    if ($playerNames !== []) {
        $parts = [];
        foreach ($playerNames as $idx => $name) {
            $ph = ':player_name_' . $idx;
            $parts[] = 'player_name = ' . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($userId !== null) {
        $where[] = 'user_id = :user_id';
        $params[':user_id'] = $userId;
    }
    if ($scoreValue !== null) {
        $where[] = "CAST((CASE WHEN leaderboard_type = 'score' THEN value_numeric ELSE NULL END) AS TEXT) ILIKE :value_numeric";
        $params[':value_numeric'] = '%' . $scoreValue . '%';
    }
    if ($distanceValue !== null) {
        $where[] = "CAST((CASE WHEN leaderboard_type = 'range' THEN distance_m ELSE NULL END) AS TEXT) ILIKE :distance_m";
        $params[':distance_m'] = '%' . $distanceValue . '%';
    }

    $sortable = [
        'leaderboard_type' => 'leaderboard_type',
        'species_id' => 'species_id',
        'species_name' => 'COALESCE(species_name_es, species_name)',
        'rank_pos' => 'rank_pos',
        'player_name' => 'player_name',
        'user_id' => 'COALESCE(user_id, -1)',
        'value_numeric' => "COALESCE((CASE WHEN leaderboard_type = 'score' THEN value_numeric ELSE NULL END), -1)",
        'distance_m' => "COALESCE((CASE WHEN leaderboard_type = 'range' THEN distance_m ELSE NULL END), -1)",
        'snapshot_at' => 'snapshot_at',
    ];
    [$sortKey, $sortDir] = query_sort('leaderboard_type', 'asc', $sortable);

    $sql = "SELECT leaderboard_type, species_id, species_name, species_name_es, rank_pos, user_id, player_name,
                   CASE WHEN leaderboard_type = 'score' THEN value_numeric ELSE NULL END AS display_score,
                   CASE WHEN leaderboard_type = 'range' THEN distance_m ELSE NULL END AS display_distance,
                   snapshot_at
            FROM gpt.clas_rankings_latest";
    $countSql = 'SELECT COUNT(*) AS c FROM gpt.clas_rankings_latest';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', species_id ASC, rank_pos ASC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        csv_stream(
            'clasificaciones_latest.csv',
            ['Tipo', 'IdEspecie', 'Especie', 'Rank', 'IdUsuario', 'Jugador', 'Puntuacion', 'Distancia', 'Snapshot'],
            $rows,
            ['leaderboard_type', 'species_id', 'species_name_es', 'rank_pos', 'user_id', 'player_name', 'display_score', 'display_distance', 'snapshot_at']
        );
    }

    $sql .= ' LIMIT :_limit OFFSET :_offset';

    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    echo '<section class="card"><h2>Tablas Clasificacion</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="classifications">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<select name="leaderboard_type" onchange="this.form.submit()">';
    echo '<option value="">Tipo (todos)</option>';
    echo '<option value="score"' . (query_raw('leaderboard_type') === 'score' ? ' selected' : '') . '>Puntuaci&oacute;n</option>';
    echo '<option value="range"' . (query_raw('leaderboard_type') === 'range' ? ' selected' : '') . '>Distancia</option>';
    echo '</select>';
    echo '<select name="species_name[]">';
    echo '<option value="">Especie ES (todas)</option>';
    foreach (species_es_name_suggestions() as $name) {
        $selected = in_array($name, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="rank_pos" placeholder="Rank" value="' . h(query_raw('rank_pos')) . '">';
    echo '<select name="player_name[]">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="user_id" placeholder="IdUsuario" value="' . h(query_raw('user_id')) . '">';
    echo '<input type="text" name="value_numeric" placeholder="Puntuacion" value="' . h(query_raw('value_numeric')) . '">';
    echo '<input type="text" name="distance_m" placeholder="Distancia" value="' . h(query_raw('distance_m')) . '">';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=classifications&reset=1">Limpiar</a>';
    echo '</form>';

    $rows = app_query_all(
        $sql,
        $params + [
            ':_limit' => $pageSize,
            ':_offset' => $offset,
        ]
    );
    echo '<table><thead><tr>';
    echo '<th>' . sort_link('leaderboard_type', 'Tipo', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('species_id', 'IdEspecie', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('species_name', 'Especie', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('rank_pos', 'Rank', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('user_id', 'IdUsuario', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('player_name', 'Jugador', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('value_numeric', 'Puntuacion', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('distance_m', 'Distancia', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('snapshot_at', 'Snapshot', $sortKey, $sortDir) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $speciesLabel = $row['species_name_es'] ?? $row['species_name'] ?? '';
        $isTop1 = ((int) ($row['rank_pos'] ?? 0) === 1);
        $rowClass = $isTop1 ? ' class="top-rank-row"' : '';
        $rankLabel = $isTop1 ? '<span class="top-rank-badge">TOP 1</span> 1' : h((string) $row['rank_pos']);
        echo '<tr' . $rowClass . '>'
            . '<td>' . h(((string) $row['leaderboard_type']) === 'score' ? 'Puntuacion' : (((string) $row['leaderboard_type']) === 'range' ? 'Distancia' : (string) $row['leaderboard_type']) ) . '</td>'
            . '<td>' . h((string) $row['species_id']) . '</td>'
            . '<td>' . h((string) $speciesLabel) . '</td>'
            . '<td class="num-cell">' . $rankLabel . '</td>'
            . '<td>' . h((string) $row['user_id']) . '</td>'
            . '<td>' . h((string) $row['player_name']) . '</td>'
            . '<td>' . h((string) $row['display_score']) . '</td>'
            . '<td>' . h((string) $row['display_distance']) . '</td>'
            . '<td>' . h((string) $row['snapshot_at']) . '</td>'
            . '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_classifications_history(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);

    $snapRows = app_query_all(
        'SELECT DISTINCT snapshot_at
         FROM gpt.clas_rankings_history
         ORDER BY snapshot_at DESC
         LIMIT 100'
    );
    $snapshots = array_values(array_filter(array_map(static fn (array $r): string => (string) ($r['snapshot_at'] ?? ''), $snapRows)));
    if ($snapshots === []) {
        echo '<section class="card"><h2>Tablas Clasificacion</h2><p>No hay snapshots hist</p></section>';
        return;
    }

    $currentSnapshot = query_text('snapshot_at') ?? $snapshots[0];
    if (!in_array($currentSnapshot, $snapshots, true)) {
        $currentSnapshot = $snapshots[0];
    }

    $compareSnapshot = query_text('compare_snapshot_at');
    if ($compareSnapshot !== null && $compareSnapshot !== '' && !in_array($compareSnapshot, $snapshots, true)) {
        $compareSnapshot = null;
    }
    if ($compareSnapshot === null || $compareSnapshot === '') {
        $idx = array_search($currentSnapshot, $snapshots, true);
        if (is_int($idx) && isset($snapshots[$idx + 1])) {
            $compareSnapshot = $snapshots[$idx + 1];
        }
    }

    $leaderboardType = strtolower(query_text('leaderboard_type') ?? '');
    if (!in_array($leaderboardType, ['', 'score', 'range'], true)) {
        $leaderboardType = '';
    }
    $speciesNames = query_list('species_name');
    $playerNames = default_player_filter(query_list('player_name'), 'player_name');
    $rankPos = query_int('rank_pos');
    $userId = query_int('user_id');
    $onlyChanged = query_raw('only_changed') === '1';

    $where = ['c.snapshot_at = :snapshot_at'];
    $params = [':snapshot_at' => $currentSnapshot];

    if ($leaderboardType !== '') {
        $where[] = 'c.leaderboard_type = :leaderboard_type';
        $params[':leaderboard_type'] = $leaderboardType;
    }
    if ($rankPos !== null) {
        $where[] = 'c.rank_pos = :rank_pos';
        $params[':rank_pos'] = $rankPos;
    }
    if ($userId !== null) {
        $where[] = 'c.user_id = :user_id';
        $params[':user_id'] = $userId;
    }
    if ($speciesNames !== []) {
        $parts = [];
        foreach ($speciesNames as $idx => $name) {
            $ph = ':species_name_' . $idx;
            $parts[] = "(COALESCE(c.species_name_es, c.species_name, '') = " . $ph . ')';
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if ($playerNames !== []) {
        $parts = [];
        foreach ($playerNames as $idx => $name) {
            $ph = ':player_name_' . $idx;
            $parts[] = 'c.player_name = ' . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $comparePart = ($compareSnapshot !== null && $compareSnapshot !== '')
        ? 'SELECT leaderboard_type, species_id, user_id, player_name, rank_pos, value_numeric, distance_m FROM gpt.clas_rankings_history WHERE snapshot_at = :compare_snapshot_at'
        : 'SELECT NULL::text AS leaderboard_type, NULL::int AS species_id, NULL::int AS user_id, NULL::text AS player_name, NULL::int AS rank_pos, NULL::numeric AS value_numeric, NULL::numeric AS distance_m WHERE false';

    if ($compareSnapshot !== null && $compareSnapshot !== '') {
        $params[':compare_snapshot_at'] = $compareSnapshot;
    }

    $baseSql = 'WITH prev_rows AS (' . $comparePart . ')
        SELECT c.snapshot_at,
               :compare_snapshot_label AS compare_snapshot_at,
               c.leaderboard_type,
               c.species_id,
               c.species_name,
               c.species_name_es,
               c.rank_pos,
               p.rank_pos AS prev_rank,
               CASE WHEN p.rank_pos IS NULL THEN NULL ELSE (p.rank_pos - c.rank_pos) END AS rank_delta,
               CASE WHEN c.leaderboard_type = \'score\' AND p.value_numeric IS NOT NULL THEN (c.value_numeric - p.value_numeric) ELSE NULL END AS score_delta,
               CASE WHEN c.leaderboard_type = \'range\' AND COALESCE(p.distance_m, p.value_numeric) IS NOT NULL THEN (COALESCE(c.distance_m, c.value_numeric) - COALESCE(p.distance_m, p.value_numeric)) ELSE NULL END AS distance_delta,
               c.user_id,
               c.player_name,
               CASE WHEN c.leaderboard_type = \'score\' THEN c.value_numeric ELSE NULL END AS display_score,
               CASE WHEN c.leaderboard_type = \'range\' THEN COALESCE(c.distance_m, c.value_numeric) ELSE NULL END AS display_distance,
               CASE WHEN c.leaderboard_type = \'score\' THEN p.value_numeric ELSE NULL END AS prev_display_score,
               CASE WHEN c.leaderboard_type = \'range\' THEN COALESCE(p.distance_m, p.value_numeric) ELSE NULL END AS prev_display_distance,
               CASE
                   WHEN :compare_snapshot_label = \'\' THEN false
                   WHEN (CASE WHEN p.rank_pos IS NULL THEN NULL ELSE (p.rank_pos - c.rank_pos) END) IS DISTINCT FROM 0 THEN true
                   WHEN c.leaderboard_type = \'score\' AND ((c.value_numeric - p.value_numeric) IS DISTINCT FROM 0) THEN true
                   WHEN c.leaderboard_type = \'range\' AND ((COALESCE(c.distance_m, c.value_numeric) - COALESCE(p.distance_m, p.value_numeric)) IS DISTINCT FROM 0) THEN true
                   ELSE false
               END AS mark_changed
        FROM gpt.clas_rankings_history c
        LEFT JOIN prev_rows p
          ON p.leaderboard_type = c.leaderboard_type
         AND p.species_id = c.species_id
         AND (
              (p.user_id IS NOT NULL AND c.user_id IS NOT NULL AND p.user_id = c.user_id)
              OR (p.user_id IS NULL AND c.user_id IS NULL AND p.player_name = c.player_name)
         )
        WHERE ' . implode(' AND ', $where);

    $params[':compare_snapshot_label'] = (string) ($compareSnapshot ?? '');

    $outerWhere = '';
    if ($onlyChanged && $compareSnapshot !== null && $compareSnapshot !== '') {
        $outerWhere = ' WHERE COALESCE(q.mark_changed, false) = true';
    }

    $sortable = [
        'snapshot_at' => 'q.snapshot_at',
        'compare_snapshot_at' => 'q.compare_snapshot_at',
        'leaderboard_type' => 'q.leaderboard_type',
        'species_id' => 'q.species_id',
        'species_name' => 'COALESCE(q.species_name_es, q.species_name, q.species_id::text)',
        'rank_pos' => 'q.rank_pos',
        'prev_rank' => 'COALESCE(q.prev_rank, 999999)',
        'rank_delta' => 'COALESCE(q.rank_delta, 0)',
        'score_delta' => 'COALESCE(q.score_delta, 0)',
        'distance_delta' => 'COALESCE(q.distance_delta, 0)',
        'user_id' => 'COALESCE(q.user_id, -1)',
        'player_name' => 'q.player_name',
        'display_score' => 'COALESCE(q.display_score, -1)',
        'display_distance' => 'COALESCE(q.display_distance, -1)',
    ];
    [$sortKey, $sortDir] = query_sort('rank_delta', 'asc', $sortable);

    $sql = 'SELECT * FROM (' . $baseSql . ') q' . $outerWhere . ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', q.species_id ASC, q.rank_pos ASC';
    $countSql = 'SELECT COUNT(*) AS c FROM (' . $baseSql . ') q' . $outerWhere;

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        csv_stream(
            'Tablas_Clasificacion_Historico.csv',
            ['Snapshot', 'Comparado Con', 'Tipo', 'IdEspecie', 'Especie', 'Rank', 'Rank Prev', 'Delta Rank', 'Delta Puntuacion', 'Delta Distancia', 'IdUsuario', 'Jugador', 'Puntuacion', 'Distancia'],
            $rows,
            ['snapshot_at', 'compare_snapshot_at', 'leaderboard_type', 'species_id', 'species_name_es', 'rank_pos', 'prev_rank', 'rank_delta', 'score_delta', 'distance_delta', 'user_id', 'player_name', 'display_score', 'display_distance']
        );
    }

    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    echo '<section class="card"><h2>Tablas Clasificacion</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="classifications_history">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<select name="snapshot_at">';
    foreach ($snapshots as $snapshot) {
        echo '<option value="' . h($snapshot) . '"' . ($snapshot === $currentSnapshot ? ' selected' : '') . '>' . h($snapshot) . '</option>';
    }
    echo '</select>';
    echo '<select name="compare_snapshot_at"><option value="">Comparar con...</option>';
    foreach ($snapshots as $snapshot) {
        echo '<option value="' . h($snapshot) . '"' . ($snapshot === $compareSnapshot ? ' selected' : '') . '>' . h($snapshot) . '</option>';
    }
    echo '</select>';
    echo '<select name="leaderboard_type"><option value="">Tipo (todos)</option><option value="score"' . (query_raw('leaderboard_type') === 'score' ? ' selected' : '') . '>Puntuaci&oacute;n</option><option value="range"' . (query_raw('leaderboard_type') === 'range' ? ' selected' : '') . '>Distancia</option></select>';
    echo '<input type="text" name="rank_pos" placeholder="Rank actual" value="' . h(query_raw('rank_pos')) . '">';
    echo '<select name="species_name[]"><option value="">Especie (todas)</option>';
    foreach (species_es_name_suggestions() as $name) {
        $selected = in_array($name, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<select name="player_name[]"><option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="user_id" placeholder="IdUsuario" value="' . h(query_raw('user_id')) . '">';
    echo '<label class="inline-check"><input type="checkbox" name="only_changed" value="1"' . ($onlyChanged ? ' checked' : '') . '> Solo cambios</label>';
    echo '<select name="page_size"><option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option><option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option><option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option><option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option></select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=classifications_history&reset=1">Limpiar</a>';
    echo '</form>';

    $rows = app_query_all($sql . ' LIMIT :_limit OFFSET :_offset', $params + [':_limit' => $pageSize, ':_offset' => $offset]);

    echo '<table><thead><tr>';
    echo '<th>' . sort_link('snapshot_at', 'Snapshot', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('compare_snapshot_at', 'Comparado con', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('leaderboard_type', 'Tipo', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('species_id', 'IdEspecie', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('species_name', 'Especie', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('rank_pos', 'Rank', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('prev_rank', 'Rank Prev', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('rank_delta', 'Delta Rank', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('score_delta', 'Delta Puntuacion', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('distance_delta', 'Delta Distancia', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('user_id', 'IdUsuario', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('player_name', 'Jugador', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('display_score', 'Puntuacion', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('display_distance', 'Distancia', $sortKey, $sortDir) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $speciesLabel = $row['species_name_es'] ?? $row['species_name'] ?? '';
        $isTop1 = ((int) ($row['rank_pos'] ?? 0) === 1);
        $markChanged = ((string) ($row['mark_changed'] ?? '')) === '1' || $row['mark_changed'] === true || (string) ($row['mark_changed'] ?? '') === 't';
        $rowClasses = [];
        if ($isTop1) {
            $rowClasses[] = 'top-rank-row';
        }
        if ($markChanged) {
            $rowClasses[] = 'mark-changed-row';
        }
        $rowClass = $rowClasses !== [] ? ' class="' . h(implode(' ', $rowClasses)) . '"' : '';
        $rankLabel = $isTop1 ? '<span class="top-rank-badge">TOP 1</span> 1' : h((string) $row['rank_pos']);
        $deltaClass = static function ($delta): string {
            if ($delta === null || $delta === '') {
                return 'num-cell';
            }
            $n = (float) $delta;
            if ($n > 0) {
                return 'num-cell delta-pos';
            }
            if ($n < 0) {
                return 'num-cell delta-neg';
            }
            return 'num-cell';
        };
        $scoreCellClass = $markChanged && ((string) ($row['leaderboard_type'] ?? '') === 'score') ? 'num-cell mark-changed-value' : 'num-cell';
        $distanceCellClass = $markChanged && ((string) ($row['leaderboard_type'] ?? '') === 'range') ? 'num-cell mark-changed-value' : 'num-cell';
        $rankDelta = $row['rank_delta'] ?? null;
        $scoreDelta = $row['score_delta'] ?? null;
        $distanceDelta = $row['distance_delta'] ?? null;
        echo '<tr' . $rowClass . '>'
            . '<td>' . h((string) $row['snapshot_at']) . '</td>'
            . '<td>' . h((string) $row['compare_snapshot_at']) . '</td>'
            . '<td>' . h(((string) $row['leaderboard_type']) === 'score' ? 'Puntuacion' : (((string) $row['leaderboard_type']) === 'range' ? 'Distancia' : (string) $row['leaderboard_type']) ) . '</td>'
            . '<td class="num-cell">' . h((string) $row['species_id']) . '</td>'
            . '<td>' . h((string) $speciesLabel) . '</td>'
            . '<td class="num-cell">' . $rankLabel . '</td>'
            . '<td class="num-cell">' . h((string) ($row['prev_rank'] ?? '')) . '</td>'
            . '<td class="' . h($deltaClass($rankDelta)) . '">' . h((string) ($rankDelta ?? '')) . '</td>'
            . '<td class="' . h($deltaClass($scoreDelta)) . '">' . h((string) ($scoreDelta ?? '')) . '</td>'
            . '<td class="' . h($deltaClass($distanceDelta)) . '">' . h((string) ($distanceDelta ?? '')) . '</td>'
            . '<td class="num-cell">' . h((string) ($row['user_id'] ?? '')) . '</td>'
            . '<td>' . h((string) $row['player_name']) . '</td>'
            . '<td class="' . h($scoreCellClass) . '">' . ($markChanged && ((string) ($row['leaderboard_type'] ?? '') === 'score') ? '<span class="mark-changed-badge">CAMBIO</span> ' : '') . h((string) ($row['display_score'] ?? '')) . '</td>'
            . '<td class="' . h($distanceCellClass) . '">' . ($markChanged && ((string) ($row['leaderboard_type'] ?? '') === 'range') ? '<span class="mark-changed-badge">CAMBIO</span> ' : '') . h((string) ($row['display_distance'] ?? '')) . '</td>'
            . '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_cheat_risk(): void
{
    $page = query_page();
    $pageSize = query_page_size(100);
    $userId = query_int('user_id');
    $playerNames = default_player_filter(query_list('player_name'), 'player_name');
    $riskLevel = strtolower(query_text('risk_level') ?? '');
    $minScore = query_int('min_score');
    $minKills = query_int('min_kills');
    $signalsOnly = query_raw('signals_only') === '1';
    $detailUserId = query_int('detail_user_id');

    $where = [];
    $params = [];
    if ($userId !== null) {
        $where[] = 'r.user_id = :user_id';
        $params[':user_id'] = $userId;
    }
    if ($playerNames !== []) {
        $parts = [];
        foreach ($playerNames as $idx => $name) {
            $ph = ':player_name_' . $idx;
            $parts[] = 'r.player_name = ' . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    if (in_array($riskLevel, ['bajo', 'medio', 'alto'], true)) {
        $where[] = 'r.risk_level = :risk_level';
        $params[':risk_level'] = $riskLevel;
    }
    if ($minScore !== null) {
        $where[] = 'r.risk_score >= :min_score';
        $params[':min_score'] = $minScore;
    }
    if ($minKills !== null) {
        $where[] = 'r.total_kills >= :min_kills';
        $params[':min_kills'] = $minKills;
    }
    if ($signalsOnly) {
        $where[] = 'r.signal_count > 0';
    }

    $sortable = [
        'risk_score' => 'r.risk_score',
        'risk_level' => 'r.risk_level',
        'player_name' => 'r.player_name',
        'user_id' => 'r.user_id',
        'total_kills' => 'r.total_kills',
        'signal_count' => 'r.signal_count',
        'max_hit_distance_m' => 'r.max_hit_distance_m',
        'max_kills_per_hour' => 'r.max_kills_per_hour',
        'min_gap_sec' => 'r.min_gap_sec',
    ];
    [$sortKey, $sortDir] = query_sort('risk_score', 'desc', $sortable);

    $sql = 'SELECT r.user_id, r.player_name, r.total_kills, r.risk_score, r.risk_level, r.signal_count,
                   r.kills_outside_window, r.max_hit_distance_m, r.max_kills_per_hour, r.min_gap_sec,
                   r.integrity_ratio_pct, r.signal_list
            FROM gpt.v_exp_cheat_risk r';
    $countSql = 'SELECT COUNT(*) AS c FROM gpt.v_exp_cheat_risk r';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', r.total_kills DESC, r.user_id ASC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $headers = ['IdUsuario', 'Jugador', 'Muertes', 'Score Riesgo', 'Nivel', 'Senales', 'Muertes fuera ventana', 'Distancia max (m)', 'Muertes/h max', 'Gap min (s)', 'Integridad %', 'Detalle senales'];
        $keys = ['user_id', 'player_name', 'total_kills', 'risk_score', 'risk_level', 'signal_count', 'kills_outside_window', 'max_hit_distance_m', 'max_kills_per_hour', 'min_gap_sec', 'integrity_ratio_pct', 'signal_list'];
        csv_stream('riesgo_trampas.csv', $headers, $rows, $keys);
    }

    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    $rows = app_query_all(
        $sql . ' LIMIT :_limit OFFSET :_offset',
        $params + [':_limit' => $pageSize, ':_offset' => $offset]
    );

    if ($detailUserId === null && $rows !== []) {
        $detailUserId = (int) ($rows[0]['user_id'] ?? 0);
    }
    $detailRows = [];
    if ($detailUserId !== null && $detailUserId > 0) {
        $detailRows = app_query_all(
            'SELECT signal_label, signal_value, signal_threshold, signal_weight
             FROM gpt.v_exp_cheat_signals
             WHERE user_id = :uid
             ORDER BY signal_weight DESC, signal_label ASC',
            [':uid' => $detailUserId]
        );
    }
    $detailExpRows = [];
    if ($detailUserId !== null && $detailUserId > 0) {
        $detailExpRows = app_query_all(
            'SELECT signal_label, expedition_id, signal_value, signal_threshold, signal_weight
             FROM gpt.v_exp_cheat_signal_expeditions
             WHERE user_id = :uid
             ORDER BY signal_weight DESC, signal_label ASC, expedition_id DESC
             LIMIT 500',
            [':uid' => $detailUserId]
        );
    }

    echo '<section class="card"><h2>Riesgo de Trampas</h2>';
    echo '<p class="muted">Semaforo orientativo basado en patrones de expediciones/kills/hits. Requiere revision manual.</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="cheat_risk">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="text" name="user_id" placeholder="IdUsuario" value="' . h(query_raw('user_id')) . '">';
    echo '<select name="player_name[]">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<select name="risk_level"><option value="">Nivel (todos)</option>';
    echo '<option value="alto"' . ($riskLevel === 'alto' ? ' selected' : '') . '>Alto</option>';
    echo '<option value="medio"' . ($riskLevel === 'medio' ? ' selected' : '') . '>Medio</option>';
    echo '<option value="bajo"' . ($riskLevel === 'bajo' ? ' selected' : '') . '>Bajo</option>';
    echo '</select>';
    echo '<input type="text" name="min_score" placeholder="Riesgo min" value="' . h(query_raw('min_score')) . '">';
    echo '<input type="text" name="min_kills" placeholder="Kills min" value="' . h(query_raw('min_kills')) . '">';
    echo '<label class="inline-check"><input type="checkbox" name="signals_only" value="1"' . ($signalsOnly ? ' checked' : '') . '>Solo con senales</label>';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=cheat_risk&reset=1">Limpiar</a>';
    echo '</form>';

    echo '<table><thead><tr>';
    echo '<th>' . sort_link('risk_score', 'Riesgo', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('risk_level', 'Nivel', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('player_name', 'Jugador', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('user_id', 'IdUsuario', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('total_kills', 'Kills', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('signal_count', 'Senales', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('max_hit_distance_m', 'Dist max (m)', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('max_kills_per_hour', 'Kills/h max', $sortKey, $sortDir) . '</th>';
    echo '<th>' . sort_link('min_gap_sec', 'Gap min (s)', $sortKey, $sortDir) . '</th>';
    echo '<th>Detalle</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $level = strtolower((string) ($row['risk_level'] ?? 'bajo'));
        $query = $_GET;
        $query['detail_user_id'] = $row['user_id'];
        $query['view'] = 'cheat_risk';
        echo '<tr>';
        echo '<td class="num-cell">' . h((string) $row['risk_score']) . '</td>';
        echo '<td><span class="risk-pill risk-' . h($level) . '">' . h($level) . '</span></td>';
        echo '<td>' . h((string) $row['player_name']) . '</td>';
        echo '<td class="num-cell">' . h((string) $row['user_id']) . '</td>';
        echo '<td class="num-cell">' . h((string) $row['total_kills']) . '</td>';
        echo '<td class="num-cell">' . h((string) $row['signal_count']) . '</td>';
        echo '<td class="num-cell">' . h((string) $row['max_hit_distance_m']) . '</td>';
        echo '<td class="num-cell">' . h((string) $row['max_kills_per_hour']) . '</td>';
        echo '<td class="num-cell">' . h((string) $row['min_gap_sec']) . '</td>';
        unset($query['export'], $query['export_csv']);
        echo '<td><a class="btn-link" href="?' . h(http_build_query($query)) . '">Ver senales</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);

    if ($detailUserId !== null && $detailUserId > 0) {
        echo '<h3>Senales' . h((string) $detailUserId) . '</h3>';
        if ($detailRows === []) {
            echo '<p class="muted">Sin senales</p>';
        } else {
            echo '<table><thead><tr><th>Senales</th><th>Valor</th><th>Umbral</th><th>Peso</th></tr></thead><tbody>';
            foreach ($detailRows as $drow) {
                echo '<tr>';
                echo '<td>' . h((string) $drow['signal_label']) . '</td>';
                echo '<td class="num-cell">' . h((string) $drow['signal_value']) . '</td>';
                echo '<td>' . h((string) $drow['signal_threshold']) . '</td>';
                echo '<td class="num-cell">' . h((string) $drow['signal_weight']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '<h3>Expediciones por indicio</h3>';
        if ($detailExpRows === []) {
            echo '<p class="muted">No hay expediciones marcadas para este jugador.</p>';
        } else {
            echo '<table><thead><tr><th>Senales</th><th>Expedicion</th><th>Valor</th><th>Umbral</th><th>Peso</th></tr></thead><tbody>';
            foreach ($detailExpRows as $drow) {
                echo '<tr>';
                echo '<td>' . h((string) $drow['signal_label']) . '</td>';
                echo '<td class="num-cell">' . h((string) $drow['expedition_id']) . '</td>';
                echo '<td class="num-cell">' . h((string) $drow['signal_value']) . '</td>';
                echo '<td>' . h((string) $drow['signal_threshold']) . '</td>';
                echo '<td class="num-cell">' . h((string) $drow['signal_weight']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
    }
    echo '</section>';
}

function render_best_xml_preview(): void
{
    $xmlPath = dirname(__DIR__) . '/out/best_all.xml';
    $xmlDownloadHref = 'download.php?file=best_all.xml';
    $rawXml = '';
    if (is_file($xmlPath)) {
        $rawXml = (string) @file_get_contents($xmlPath);
    }
    if (!is_file($xmlPath)) {
        echo '<section class="card"><h2>Comparativa Mejores Marcas</h2><p class="muted">No existe el archivo out/best_all.xml. Genera primero el XML.</p></section>';
        return;
    }

    libxml_use_internal_errors(true);
    $xml = function_exists('simplexml_load_file') ? simplexml_load_file($xmlPath) : false;
    if ($xml === false) {
        echo '<section class="card"><h2>Comparativa Mejores Marcas</h2><p><a class="btn-link" target="_blank" rel="noopener" href="' . h($xmlDownloadHref) . '">Abrir XML original</a></p><p class="muted">No se pudo parsear en tabla, pero puedes verlo en crudo aqu? abajo.</p>';
        $preview = function_exists('mb_substr') ? mb_substr($rawXml, 0, 200000) : substr($rawXml, 0, 200000);
        echo '<div style="overflow:auto; max-height: 70vh; border:1px solid var(--line); border-radius:8px; padding:10px;"><pre style="margin:0; white-space:pre-wrap;">' . h($preview) . '</pre></div>';
        echo '</section>';
        return;
    }

    $ns = 'urn:schemas-microsoft-com:office:spreadsheet';
    $xml->registerXPathNamespace('ss', $ns);
    $sheets = $xml->xpath('//ss:Worksheet') ?: [];
    if ($sheets === []) {
        echo '<section class="card"><h2>Comparativa Mejores Marcas</h2><p><a class="btn-link" target="_blank" rel="noopener" href="' . h($xmlDownloadHref) . '">Abrir XML original</a></p><p class="muted">El XML no contiene hojas.</p></section>';
        return;
    }

    $sheetNames = [];
    foreach ($sheets as $sheetNode) {
        $attrs = $sheetNode->attributes($ns);
        $sheetNames[] = (string) ($attrs['Name'] ?? 'Hoja');
    }

    $metricType = strtolower(trim((string) (query_text('metric_type') ?? 'score')));
    if (!in_array($metricType, ['score', 'distance'], true)) {
        $metricType = 'score';
    }

    $sheetMatchesType = [];
    foreach ($sheetNames as $sheetName) {
        $low = strtolower($sheetName);
        $isDistance = str_contains($low, 'distance') || str_contains($low, 'distancia') || str_contains($low, 'range');
        if (($metricType === 'distance' && $isDistance) || ($metricType === 'score' && !$isDistance)) {
            $sheetMatchesType[] = $sheetName;
        }
    }
    if ($sheetMatchesType === []) {
        $sheetMatchesType = $sheetNames;
    }

    $selectedSheet = query_text('sheet');
    if ($selectedSheet === null || !in_array($selectedSheet, $sheetMatchesType, true)) {
        $selectedSheet = $sheetMatchesType[0];
    }

    $maxRows = query_int('xml_rows');
    $maxRows = ($maxRows !== null && $maxRows > 0) ? min($maxRows, 1000) : 200;

    $targetSheet = null;
    foreach ($sheets as $sheetNode) {
        $attrs = $sheetNode->attributes($ns);
        if ((string) ($attrs['Name'] ?? '') === $selectedSheet) {
            $targetSheet = $sheetNode;
            break;
        }
    }
    if ($targetSheet === null) {
        $targetSheet = $sheets[0];
    }

    $rowsOut = [];
    $maxCol = 0;
    $rows = $targetSheet->xpath('.//ss:Table/ss:Row') ?: [];
    foreach ($rows as $rowNode) {
        $cells = $rowNode->xpath('./ss:Cell') ?: [];
        $rowData = [];
        $col = 1;
        foreach ($cells as $cell) {
            $cellAttrs = $cell->attributes($ns);
            if (isset($cellAttrs['Index'])) {
                $col = (int) $cellAttrs['Index'];
            }
            $dataNodes = $cell->xpath('./ss:Data');
            $value = isset($dataNodes[0]) ? trim((string) $dataNodes[0]) : '';
            $rowData[$col] = $value;
            if ($col > $maxCol) {
                $maxCol = $col;
            }
            $col++;
        }
        $rowsOut[] = $rowData;
        if (count($rowsOut) >= $maxRows) {
            break;
        }
    }

    $headerIndex = 0;
    $headerRow = $rowsOut[0] ?? [];
    foreach ($rowsOut as $idx => $candidateRow) {
        $filled = 0;
        foreach ($candidateRow as $cellValue) {
            if (trim((string) $cellValue) !== '') {
                $filled++;
            }
        }
        if ($filled >= 3) {
            $headerIndex = $idx;
            $headerRow = $candidateRow;
            break;
        }
    }
    $dataRows = array_slice($rowsOut, $headerIndex + 1);

    echo '<section class="card"><h2>Comparativa Mejores Marcas</h2>';
    echo '<p><a class="btn-link" target="_blank" rel="noopener" href="' . h($xmlDownloadHref) . '">Abrir XML original</a></p>';
    echo '<form class="table-filters best-xml-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="best_xml">';
    echo '<div class="metric-toggle">';
    echo '<label class="metric-option"><input type="radio" name="metric_type" value="score"' . ($metricType === 'score' ? ' checked' : '') . ' onchange="this.form.submit()"><span>Puntuaci&oacute;n</span></label>';
    echo '<label class="metric-option"><input type="radio" name="metric_type" value="distance"' . ($metricType === 'distance' ? ' checked' : '') . ' onchange="this.form.submit()"><span>Distancia</span></label>';
    echo '</div>';
    echo '<input class="best-xml-rows-input" type="text" name="xml_rows" placeholder="Filas (max 1000)" value="' . h((string) $maxRows) . '">';
    echo '<button class="best-xml-run-btn" type="submit">Ver comparativa</button>';
    echo '</form>';

    if ($rowsOut === []) {
        echo '<p class="muted">Sin filas para mostrar.</p></section>';
        return;
    }

    $toFloat = static function (string $raw): ?float {
        $v = trim($raw);
        if ($v === '') {
            return null;
        }
        $v = str_replace([' ', "	", "
", "
"], '', $v);
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        }
        return is_numeric($v) ? (float) $v : null;
    };

    $speciesCol = 1;
    $playerStartCol = 2;
    $topBadge = $metricType === 'distance' ? 'TOP D' : 'TOP P';

    $topByPlayer = [];

    $accumulateTopCounts = static function ($sheetNode, string $metricKey, array &$bucket) use ($ns, $toFloat): void {
        $rowsLocal = $sheetNode->xpath('.//ss:Table/ss:Row') ?: [];
        if ($rowsLocal === []) {
            return;
        }

        $rowsParsed = [];
        $maxColLocal = 0;
        foreach ($rowsLocal as $rowNode) {
            $cells = $rowNode->xpath('./ss:Cell') ?: [];
            $rowData = [];
            $col = 1;
            foreach ($cells as $cell) {
                $cellAttrs = $cell->attributes($ns);
                if (isset($cellAttrs['Index'])) {
                    $col = (int) $cellAttrs['Index'];
                }
                $dataNodes = $cell->xpath('./ss:Data');
                $value = isset($dataNodes[0]) ? trim((string) $dataNodes[0]) : "";
                $rowData[$col] = $value;
                if ($col > $maxColLocal) {
                    $maxColLocal = $col;
                }
                $col++;
            }
            $rowsParsed[] = $rowData;
        }

        if ($rowsParsed === [] || $maxColLocal < 2) {
            return;
        }

        $headerIdxLocal = 0;
        $headerLocal = $rowsParsed[0] ?? [];
        foreach ($rowsParsed as $idx => $candidateRow) {
            $filled = 0;
            foreach ($candidateRow as $cellValue) {
                if (trim((string) $cellValue) !== "") {
                    $filled++;
                }
            }
            if ($filled >= 3) {
                $headerIdxLocal = $idx;
                $headerLocal = $candidateRow;
                break;
            }
        }

        $rowsDataLocal = array_slice($rowsParsed, $headerIdxLocal + 1);
        foreach ($rowsDataLocal as $rowData) {
            $species = trim((string) ($rowData[1] ?? ""));
            if ($species === "") {
                continue;
            }
            $bestCols = [];
            $bestVal = null;
            for ($i = 2; $i <= $maxColLocal; $i++) {
                $num = $toFloat((string) ($rowData[$i] ?? ""));
                if ($num === null) {
                    continue;
                }
                if ($bestVal === null || $num > $bestVal + 0.0000001) {
                    $bestVal = $num;
                    $bestCols = [$i];
                } elseif (abs($num - $bestVal) <= 0.0000001) {
                    $bestCols[] = $i;
                }
            }
            foreach ($bestCols as $i) {
                $playerName = trim((string) ($headerLocal[$i] ?? ""));
                if ($playerName === "") {
                    continue;
                }
                if (!isset($bucket[$playerName])) {
                    $bucket[$playerName] = ['top_p' => 0, 'top_d' => 0];
                }
                if ($metricKey === "distance") {
                    $bucket[$playerName]['top_d']++;
                } else {
                    $bucket[$playerName]['top_p']++;
                }
            }
        }
    };

    $scoreSheetNode = null;
    $distanceSheetNode = null;
    foreach ($sheets as $sheetNode) {
        $attrs = $sheetNode->attributes($ns);
        $nm = strtolower((string) ($attrs['Name'] ?? ""));
        $isDistance = str_contains($nm, "distance") || str_contains($nm, "distancia") || str_contains($nm, "range");
        if ($isDistance && $distanceSheetNode === null) {
            $distanceSheetNode = $sheetNode;
        }
        if (!$isDistance && $scoreSheetNode === null) {
            $scoreSheetNode = $sheetNode;
        }
    }
    if ($scoreSheetNode !== null) {
        $accumulateTopCounts($scoreSheetNode, "score", $topByPlayer);
    }
    if ($distanceSheetNode !== null) {
        $accumulateTopCounts($distanceSheetNode, "distance", $topByPlayer);
    }
    ob_start();
    echo '<div style="overflow:auto"><table class="best-xml-main" data-top-badge="' . h($topBadge) . '"><thead><tr>';
    for ($i = 1; $i <= $maxCol; $i++) {
        $hval = (string) ($headerRow[$i] ?? '');
        if ($i >= $playerStartCol) {
            echo '<th data-player-name="' . h($hval) . '">' . h($hval) . '</th>';
        } else {
            echo '<th>' . h($hval) . '</th>';
        }
    }
    echo '</tr></thead><tbody>';

    foreach ($dataRows as $rowData) {
        $species = trim((string) ($rowData[$speciesCol] ?? ''));
        if ($species === '') {
            continue;
        }

        $bestCols = [];
        $bestVal = null;
        for ($i = $playerStartCol; $i <= $maxCol; $i++) {
            $num = $toFloat((string) ($rowData[$i] ?? ''));
            if ($num === null) {
                continue;
            }
            if ($bestVal === null || $num > $bestVal + 0.0000001) {
                $bestVal = $num;
                $bestCols = [$i];
            } elseif (abs($num - $bestVal) <= 0.0000001) {
                $bestCols[] = $i;
            }
        }

        if ($bestCols === []) {
            continue;
        }

        echo '<tr class="best-species-row">';
        for ($i = 1; $i <= $maxCol; $i++) {
            $v = (string) ($rowData[$i] ?? '');
            if ($i === $speciesCol) {
                $speciesIcons = species_icons_pair_html($v);
                echo '<td class="best-xml-species" data-raw="' . h($v) . '"><span class="best-species-tag">[' . h($topBadge === 'TOP D' ? 'D' : 'P') . ']</span> ' . $speciesIcons . h($v) . '</td>';
                continue;
            }
            if (in_array($i, $bestCols, true) && $v !== '') {
                $playerName = trim((string) ($headerRow[$i] ?? ''));
                $cls = $topBadge === 'TOP D' ? 'best-species-distance' : 'best-species-score';
                $numRaw = $toFloat($v);
                echo '<td class="best-xml-player ' . $cls . '" data-player-name="' . h($playerName) . '" data-raw="' . h($v) . '" data-num="' . h($numRaw !== null ? (string) $numRaw : '') . '"><span class="best-species-badge">' . h($topBadge) . '</span> ' . h($v) . '</td>';
                continue;
            }
            $playerName = trim((string) ($headerRow[$i] ?? ''));
            $numRaw = $toFloat($v);
            echo '<td class="best-xml-player" data-player-name="' . h($playerName) . '" data-raw="' . h($v) . '" data-num="' . h($numRaw !== null ? (string) $numRaw : '') . '">' . h($v) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    $mainTableHtml = (string) ob_get_clean();

    $summaryHtml = '';
    if ($topByPlayer !== []) {
        uasort(
            $topByPlayer,
            static function (array $a, array $b): int {
                $ta = (int) ($a['top_p'] ?? 0) + (int) ($a['top_d'] ?? 0);
                $tb = (int) ($b['top_p'] ?? 0) + (int) ($b['top_d'] ?? 0);
                if ($ta === $tb) {
                    return ((int) ($b['top_p'] ?? 0)) <=> ((int) ($a['top_p'] ?? 0));
                }
                return $tb <=> $ta;
            }
        );
        ob_start();
        echo '<h3 style="margin-top:10px;">N&ordm; TOPS por Jugador</h3>';
        echo '<div style="overflow:auto"><table class="best-xml-top-summary"><thead><tr><th>Jugador</th><th>TOP P</th><th>TOP D</th><th>Total TOPS</th></tr></thead><tbody>';
        foreach ($topByPlayer as $playerName => $counts) {
            $topP = (int) ($counts['top_p'] ?? 0);
            $topD = (int) ($counts['top_d'] ?? 0);
            echo '<tr>'
                . '<td>' . h($playerName) . '</td>'
                . '<td class="num-cell">' . h((string) $topP) . '</td>'
                . '<td class="num-cell">' . h((string) $topD) . '</td>'
                . '<td class="num-cell">' . h((string) ($topP + $topD)) . '</td>'
                . '</tr>';
        }
        echo '</tbody></table></div>';
        $summaryHtml = (string) ob_get_clean();
    }

    if ($summaryHtml !== '') {
        echo $summaryHtml;
    }
    echo $mainTableHtml;

    echo '<h3 style="margin-top:10px;">XML crudo</h3>';
    $preview = function_exists('mb_substr') ? mb_substr($rawXml, 0, 120000) : substr($rawXml, 0, 120000);
    echo '<div style="overflow:auto; max-height: 50vh; border:1px solid var(--line); border-radius:8px; padding:10px;"><pre style="margin:0; white-space:pre-wrap;">' . h($preview) . '</pre></div>';
    echo '</section>';
}

function recommended_species_for_player(string $playerName, int $limit = 12): array
{
    $playerName = trim($playerName);
    if ($playerName === '') {
        return [];
    }

    $userId = app_player_user_id($playerName);
    if ($userId === null || $userId <= 0) {
        return [];
    }

    $limit = max(1, min(50, $limit));
    try {
        $rows = app_query_all(
            'SELECT COALESCE(s.especie_es, s.especie) AS species_name
             FROM gpt.est_animal_stats a
             LEFT JOIN gpt.tab_especies s ON s.id_especie = a.species_id
             WHERE a.user_id = :uid
               AND COALESCE(a.kills, 0) > 0
               AND COALESCE(s.especie_es, s.especie, \'\') <> \'\'
             ORDER BY COALESCE(a.kills, 0) DESC, COALESCE(a.ethical_kills, 0) DESC, COALESCE(s.especie_es, s.especie) ASC
             LIMIT ' . $limit,
            [':uid' => $userId]
        );
    } catch (Throwable) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['species_name'] ?? ''));
        if ($name !== '') {
            $out[$name] = true;
        }
    }

    return array_keys($out);
}

function render_logs(): void
{
    $isAdmin = app_is_admin_user();

    $selected = query_text('log');
    $taskLogs = glob(task_logs_dir() . DIRECTORY_SEPARATOR . '*.log') ?: [];
    rsort($taskLogs);

    $options = [];

    if ($isAdmin) {
        $mainLogs = glob(logs_dir() . DIRECTORY_SEPARATOR . '*.log') ?: [];
        sort($mainLogs);
        foreach ($mainLogs as $path) {
            if (!is_string($path) || !is_file($path)) {
                continue;
            }
            $options[basename($path)] = $path;
        }
    }

    $allowedActions = TaskCatalog::nonAdminRunnableActions();
    foreach ($taskLogs as $path) {
        if (!is_string($path) || !is_file($path)) {
            continue;
        }
        $name = basename($path);
        $taskId = pathinfo($name, PATHINFO_FILENAME);
        $task = TaskManager::read($taskId);
        if (!$isAdmin) {
            if (!is_array($task)) {
                continue;
            }
            $action = (string) ($task['action'] ?? '');
            if (!in_array($action, $allowedActions, true)) {
                continue;
            }
        }
        $options[$name] = $path;
    }

    if ($options === []) {
        echo '<section class="card"><h2>Logs</h2><p class="muted">No hay archivos de log disponibles.</p></section>';
        return;
    }

    if ($selected === null || !isset($options[$selected])) {
        $selected = array_key_first($options);
    }
    $file = $options[$selected];
    $rows = @file($file, FILE_IGNORE_NEW_LINES);
    $rows = is_array($rows) ? array_slice($rows, -400) : [];

    echo '<section class="card"><h2>Logs</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="logs">';
    echo '<select name="log" onchange="this.form.submit()">';
    foreach ($options as $name => $_path) {
        $sel = ($name === $selected) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $sel . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit">Ver</button>';
    echo '</form>';
    echo '<p class="muted">Archivo: ' . h($selected) . '</p>';
    echo '<div style="overflow:auto;max-height:65vh;border:1px solid var(--line);border-radius:10px;padding:10px;">';
    echo '<pre style="margin:0;">' . h(implode(PHP_EOL, $rows)) . '</pre>';
    echo '</div>';
    echo '</section>';
}

function render_hall_of_fame(): void
{
    $tables = gpt_tables();
    $candidates = ['salones_fama', 'hall_of_fame', 'tab_salones_fama'];
    $table = null;
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $tables, true)) {
            $table = $candidate;
            break;
        }
    }
    if ($table === null) {
        foreach ($tables as $t) {
            $low = strtolower($t);
            if (str_contains($low, 'fama') || str_contains($low, 'hall')) {
                $table = $t;
                break;
            }
        }
    }

    echo '<section class="card"><h2>Salones Fama</h2>';
    if ($table === null) {
        echo '<p class="muted">No existe tabla de salones de la fama en esquema gpt.</p></section>';
        return;
    }

    $columns = array_keys(gpt_table_columns($table));
    $columnsByLower = [];
    foreach ($columns as $c) {
        $columnsByLower[strtolower($c)] = $c;
    }

    $urlCol = null;
    foreach (['url', 'mark_url', 'record_url', 'link'] as $candUrl) {
        if (isset($columnsByLower[$candUrl])) {
            $urlCol = $columnsByLower[$candUrl];
            break;
        }
    }
    if ($columns === []) {
        echo '<p class="muted">La tabla gpt.' . h($table) . ' no tiene columnas visibles.</p></section>';
        return;
    }

    $columnLabels = [
        'ion' => 'Especie',
        'especie' => 'Especie',
        'species' => 'Especie',
        'nombre_especie' => 'Especie',
        'imagen' => 'Imagen',
    ];

    $labelForCol = static function (string $col) use ($columnLabels): string {
        $low = strtolower($col);
        return $columnLabels[$low] ?? $col;
    };

    $displayColumns = array_values(array_filter($columns, static fn (string $c): bool => $c !== $urlCol));

    $columnDefs = [];
    foreach ($displayColumns as $col) {
        $columnDefs[$col] = ['label' => $labelForCol($col)];
    }

    $defaultCols = $displayColumns;
    $selectedCols = persistent_selected_columns('hall_of_fame_visible_cols_' . $table, $columnDefs, 'hfcol_', $defaultCols);
    $dragOrderRaw = query_text('hfcol_order');
    if ($dragOrderRaw !== null && trim($dragOrderRaw) !== '') {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn(string $k): bool => $k !== '' && isset($columnDefs[$k])));
        if ($ordered !== []) {
            $rest = array_values(array_filter($selectedCols, static fn(string $k): bool => !in_array($k, $ordered, true)));
            $selectedCols = array_merge($ordered, $rest);
            $_SESSION['hall_of_fame_visible_cols_' . $table] = $selectedCols;
        }
    }

    $where = [];
    $params = [];
    foreach ($columns as $idx => $col) {
        $value = query_text('hf_' . $col);
        if ($value === null) {
            continue;
        }
        $ph = ':hf_' . $idx;
        $where[] = "COALESCE(" . quote_ident($col) . "::text, '') ILIKE " . $ph;
        $params[$ph] = '%' . $value . '%';
    }

    $safeCols = implode(', ', array_map(static fn(string $c): string => quote_ident($c), $columns));
    $sql = 'SELECT ' . $safeCols . ' FROM ' . quote_ident('gpt') . '.' . quote_ident($table);
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' LIMIT 1000';
    $rows = app_query_all($sql, $params);

    echo '<p class="muted">Tabla: gpt.' . h($table) . '</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="hall_of_fame">';
    foreach ($displayColumns as $col) {
        $label = $labelForCol($col);
        echo '<input type="text" name="hf_' . h($col) . '" placeholder="' . h($label) . '" value="' . h(query_raw('hf_' . $col)) . '">';
    }
    echo '<details class="filter-details visible-columns" data-col-prefix="hfcol_" data-order-field="hfcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="hfcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<button type="submit">Filtrar</button>';
    echo '<a class="btn-link" href="?view=hall_of_fame&reset=1">Limpiar</a>';
    echo '</form>';

    $speciesCol = null;
    foreach (['ion', 'especie', 'species', 'animal', 'nombre_especie'] as $cand) {
        if (isset($columnsByLower[$cand])) {
            $speciesCol = $columnsByLower[$cand];
            break;
        }
    }
    $metricCol = null;
    foreach (['puntuacion', 'score', 'value_numeric', 'valor', 'marca', 'distance', 'distancia', 'range', 'distance_m'] as $cand) {
        if (isset($columnsByLower[$cand])) {
            $metricCol = $columnsByLower[$cand];
            break;
        }
    }
    $seasonCol = null;
    foreach (['temporada', 'season'] as $cand) {
        if (isset($columnsByLower[$cand])) {
            $seasonCol = $columnsByLower[$cand];
            break;
        }
    }
    $parseNum = static function ($v): ?float {
        if (!is_scalar($v) && $v !== null) {
            return null;
        }
        $s = trim((string) $v);
        if ($s === '') {
            return null;
        }
        $s = str_replace([' ', "	", "
", "
"], '', $s);
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float) $s : null;
    };

    $topBySpecies = [];
    if ($speciesCol !== null && $metricCol !== null) {
        $allSql = 'SELECT ' . quote_ident($speciesCol) . ' AS s, ' . quote_ident($metricCol) . ' AS m FROM ' . quote_ident('gpt') . '.' . quote_ident($table);
        foreach (app_query_all($allSql) as $r) {
            $sp = trim((string) ($r['s'] ?? ''));
            $mv = $parseNum($r['m'] ?? null);
            if ($sp === '' || $mv === null) {
                continue;
            }
            if (!isset($topBySpecies[$sp]) || $mv > $topBySpecies[$sp]) {
                $topBySpecies[$sp] = $mv;
            }
        }
    }

    echo '<div style="overflow:auto"><table><thead><tr>';
    foreach ($selectedCols as $c) {
        $headerLabel = $labelForCol($c);
        echo '<th>' . h($headerLabel) . '</th>';
        if ($seasonCol !== null && $c === $seasonCol) {
            echo '<th>Abrir</th>';
        }
    }
    if ($seasonCol === null && $urlCol !== null) {
        echo '<th>Abrir</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $isTop = false;
        if ($speciesCol !== null && $metricCol !== null) {
            $sp = trim((string) ($row[$speciesCol] ?? ''));
            $mv = $parseNum($row[$metricCol] ?? null);
            if ($sp !== '' && $mv !== null && isset($topBySpecies[$sp]) && abs($mv - (float) $topBySpecies[$sp]) <= 0.000001) {
                $isTop = true;
            }
        }
        echo '<tr' . ($isTop ? ' class="top-rank-row"' : '') . '>';
        $rowUrl = '';
        if ($urlCol !== null) {
            $rowUrl = trim((string) ($row[$urlCol] ?? ''));
        }
        foreach ($selectedCols as $c) {
            $v = $row[$c] ?? '';
            $cell = h(is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if ($isTop && $c === ($speciesCol ?? '')) {
                $cell = '<span class="top-rank-badge">TOP</span> ' . $cell;
            }
            echo '<td>' . $cell . '</td>';
            if ($seasonCol !== null && $c === $seasonCol) {
                if ($rowUrl !== '') {
                    echo '<td><a class="record-link" target="_blank" rel="noopener noreferrer" href="' . h($rowUrl) . '">Abrir</a></td>';
                } else {
                    echo '<td class="muted">-</td>';
                }
            }
        }
        if ($seasonCol === null && $urlCol !== null) {
            if ($rowUrl !== '') {
                echo '<td><a class="record-link" target="_blank" rel="noopener noreferrer" href="' . h($rowUrl) . '">Abrir</a></td>';
            } else {
                echo '<td class="muted">-</td>';
            }
        }
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    echo '</section>';
}


if (is_csv_export_requested()) {
    switch ($view) {
        case 'expeditions':
            render_expeditions();
            break;
        case 'best':
            render_best();
            break;
        case 'profiles':
            render_profiles();
            break;
        case 'competitions':
            render_competitions();
            break;
        case 'advanced':
            render_advanced();
            break;
        case 'classifications':
            render_classifications();
            break;
        case 'classifications_history':
            render_classifications_history();
            break;
        case 'species_ppft':
            render_species_ppft();
            break;
        case 'best_xml':
            render_best_xml_preview();
            break;
        case 'cheat_risk':
            render_cheat_risk();
            break;
        case 'logs':
            render_logs();
            break;
        case 'hall_of_fame':
            render_hall_of_fame();
            break;
    }
}
$cssVersion = (string) @filemtime(__DIR__ . '/style.css');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>THC GPT Panel</title>
    <link rel="stylesheet" href="style.css?v=<?= h($cssVersion) ?>">
</head>
<body class="theme-<?= h($theme) ?>">
<div class="layout">
    <aside class="sidebar">
        <h1 class="sidebar-logo-wrap">
            <img class="sidebar-logo" src="assets/logo-thc-query.png" alt="THC Query">
        </h1>
        <?php $sidebarUser = app_auth_username(); ?>
        <div class="sidebar-user-row">
            <div class="sidebar-user"><?= h($sidebarUser !== null && $sidebarUser !== '' ? $sidebarUser : 'Usuario') ?></div>
            <a class="sidebar-logout-mini" href="logout.php">Salir</a>
        </div>
        <details class="sidebar-password-box">
            <summary>Cambiar contrasena</summary>
            <form class="sidebar-password-form" method="post" action="change_password.php">
                <input type="hidden" name="csrf_token" value="<?= h(app_csrf_token()) ?>">
                <input type="password" name="current_password" placeholder="Contrasena actual" required>
                <input type="password" name="new_password" placeholder="Nueva contrasena" minlength="6" required>
                <input type="password" name="confirm_password" placeholder="Confirmar contrasena" minlength="6" required>
                <button type="submit">Guardar contrasena</button>
            </form>
        </details>
        <nav class="nav">
            <?= menu_link('dashboard', 'Panel', $view) ?>
            <?= menu_link('expeditions', 'Expediciones', $view) ?>
            <?= menu_link('best', 'Mejores Marcas', $view) ?>
            <?= menu_link('best_xml', 'Comparativa Mejores Marcas', $view) ?>
            <?= menu_link('profiles', 'Estadisticas', $view) ?>
            <?= menu_link('competitions', 'Competiciones', $view) ?>
            <?= menu_link('classifications', 'Tablas Clasificacion', $view) ?>
            <?= menu_link('classifications_history', 'Tablas Clasificacion Hist.', $view) ?>
            <?= menu_link('species_ppft', 'Especies PPFT', $view) ?>
            <?= menu_link('hall_of_fame', 'Salones Fama', $view) ?>
            <?= menu_link('cheat_risk', 'Anti-trampas', $view) ?>
            <?php if (app_is_admin_user()): ?>
                <?= menu_link('logs', 'Logs', $view) ?>
            <?php endif; ?>
            <?= menu_link('advanced', 'Consulta Avanzada', $view) ?>
        </nav>
        <div class="theme-switch">
            <div class="theme-title">Tema</div>
            <div class="theme-row">
                <?= theme_link('sober', 'Sobrio', $view, $theme) ?>
                <?= theme_link('gaming', 'Gaming', $view, $theme) ?>
                <?= theme_link('coral', 'Coral', $view, $theme) ?>
                <?= theme_link('arctic', 'Artico', $view, $theme) ?>
                <?= theme_link('graphite', 'Grafito', $view, $theme) ?>
                <?= theme_link('blossom', 'Blossom', $view, $theme) ?>
                <?= theme_link('midnight', 'Midnight', $view, $theme) ?>
                <?= theme_link('ember', 'Ember', $view, $theme) ?>
                <?= theme_link('forest', 'Forest', $view, $theme) ?>
                <?= theme_link('skyline', 'Skyline', $view, $theme) ?>
            </div>
        </div>
        <div class="sidebar-note">
            <p>Export XML disponible en <a href="download.php?file=best_all.xml">best_all.xml</a>.</p>
        </div>
    </aside>
    <main class="content">
        <?php if ($flash): ?>
            <div class="flash"><?= h($flash) ?></div>
        <?php endif; ?>
        <?php
        switch ($view) {
            case 'expeditions':
                render_expeditions();
                break;
            case 'best':
                render_best();
                break;
            case 'profiles':
                render_profiles();
                break;
            case 'competitions':
                render_competitions();
                break;
            case 'advanced':
                render_advanced();
                break;
            case 'classifications':
                render_classifications();
                break;
            case 'classifications_history':
                render_classifications_history();
                break;
            case 'species_ppft':
                render_species_ppft();
                break;
            case 'hall_of_fame':
                render_hall_of_fame();
                break;
            case 'logs':
                render_logs();
                break;
            case 'best_xml':
                render_best_xml_preview();
                break;
            case 'cheat_risk':
                render_cheat_risk();
                break;
            default:
                render_dashboard();
                break;
        }
        ?>
    </main>
</div>
<script>
(() => {
    const view = <?= json_encode($view, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (view === 'dashboard' || view === 'advanced' || view === 'expeditions') {
        return;
    }

    const table = document.querySelector('.content table');
    if (!table) {
        return;
    }
    const headers = Array.from(table.querySelectorAll('thead th'));
    if (headers.length === 0) {
        return;
    }

    const storageKey = `thc_visible_cols_${view}`;
    const defaults = headers.map(() => true);
    let state = defaults.slice();
    try {
        const raw = localStorage.getItem(storageKey);
        if (raw) {
            const parts = raw.split(',').map((v) => v === '1');
            if (parts.length === headers.length && parts.some(Boolean)) {
                state = parts;
            }
        }
    } catch (_) {}

    const escapeHtml = (s) =>
        String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#39;');

    const recomputeBestXmlTops = () => {
        if (view !== 'best_xml') {
            return;
        }
        const mainTable = document.querySelector('.content table.best-xml-main');
        if (!(mainTable instanceof HTMLTableElement)) {
            return;
        }

        const topBadge = (mainTable.getAttribute('data-top-badge') || 'TOP P').trim();
        const markTag = topBadge === 'TOP D' ? 'D' : 'P';
        const winnerClass = topBadge === 'TOP D' ? 'best-species-distance' : 'best-species-score';
        const headerCells = Array.from(mainTable.querySelectorAll('thead th'));
        const topByPlayer = new Map();

        Array.from(mainTable.querySelectorAll('tbody tr')).forEach((tr) => {
            const cells = Array.from(tr.children);
            if (cells.length <= 1) {
                return;
            }

            const speciesCell = cells[0];
            const speciesRaw = speciesCell.getAttribute('data-raw') || speciesCell.textContent || '';
            const visibleNumeric = [];

            for (let i = 1; i < cells.length; i++) {
                const cell = cells[i];
                const raw = cell.getAttribute('data-raw') || '';
                cell.classList.remove('best-species-score', 'best-species-distance');
                cell.innerHTML = escapeHtml(raw);

                if (!state[i]) {
                    continue;
                }
                const num = Number(cell.getAttribute('data-num') || '');
                if (!Number.isFinite(num)) {
                    continue;
                }
                visibleNumeric.push({ i, num });
            }

            if (visibleNumeric.length === 0) {
                speciesCell.innerHTML = escapeHtml(speciesRaw);
                return;
            }

            const maxVal = Math.max(...visibleNumeric.map((v) => v.num));
            const winners = visibleNumeric.filter((v) => Math.abs(v.num - maxVal) <= 1e-7);

            winners.forEach(({ i }) => {
                const cell = cells[i];
                const raw = cell.getAttribute('data-raw') || '';
                cell.classList.add(winnerClass);
                cell.innerHTML = '<span class="best-species-badge">' + escapeHtml(topBadge) + '</span> ' + escapeHtml(raw);

                const playerName = (headerCells[i]?.getAttribute('data-player-name') || '').trim();
                if (playerName !== '') {
                    topByPlayer.set(playerName, (topByPlayer.get(playerName) || 0) + 1);
                }
            });

            speciesCell.innerHTML = '<span class="best-species-tag">[' + escapeHtml(markTag) + ']</span> ' + escapeHtml(speciesRaw);
        });

        // El resumen TOP P/TOP D se calcula en servidor con ambas metricas.
        // Aqui solo recalculamos resaltado de la tabla activa.
        });
    };

    const applyState = () => {
        if (!state.some(Boolean)) {
            state[0] = true;
        }
        const rows = Array.from(table.querySelectorAll('tr'));
        rows.forEach((tr) => {
            Array.from(tr.children).forEach((cell, idx) => {
                cell.style.display = state[idx] ? '' : 'none';
            });
        });
        recomputeBestXmlTops();
    };

    const saveState = () => {
        try {
            localStorage.setItem(storageKey, state.map((v) => (v ? '1' : '0')).join(','));
        } catch (_) {}
    };

    const panel = document.createElement('details');
    panel.className = 'card';
    panel.style.marginBottom = '10px';

    const summary = document.createElement('summary');
    summary.textContent = 'Columnas visibles';
    summary.style.cursor = 'pointer';
    summary.style.fontWeight = '700';
    summary.style.marginBottom = '8px';
    panel.appendChild(summary);

    const wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.flexWrap = 'wrap';
    wrap.style.gap = '10px 18px';
    wrap.style.paddingTop = '10px';

    headers.forEach((th, idx) => {
        const label = document.createElement('label');
        label.style.display = 'flex';
        label.style.alignItems = 'center';
        label.style.gap = '6px';

        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = !!state[idx];
        cb.addEventListener('change', () => {
            state[idx] = cb.checked;
            applyState();
            saveState();
        });

        const text = document.createElement('span');
        text.textContent = th.textContent ? th.textContent.replace(/[^\p{L}\p{N}\s_-]/gu, '').trim() : `Col ${idx + 1}`;
        label.appendChild(cb);
        label.appendChild(text);
        wrap.appendChild(label);
    });

    const resetBtn = document.createElement('button');
    resetBtn.type = 'button';
    resetBtn.textContent = 'Restablecer';
    resetBtn.style.marginLeft = '8px';
    resetBtn.addEventListener('click', () => {
        state = defaults.slice();
        wrap.querySelectorAll('input[type="checkbox"]').forEach((el, idx) => {
            el.checked = state[idx];
        });
        applyState();
        saveState();
    });
    wrap.appendChild(resetBtn);

    panel.appendChild(wrap);
    table.parentNode.insertBefore(panel, table);
    applyState();
})();
</script>
<script>
(() => {
    document.querySelectorAll('.content .card').forEach((card) => {
        const form = card.querySelector('form.table-filters');
        if (!form) {
            return;
        }

        const clear = () => {
            card.querySelectorAll('.filter-col-focus').forEach((el) => el.classList.remove('filter-col-focus'));
        };

        const highlight = (key) => {
            clear();
            if (!key) {
                return;
            }
            card.querySelectorAll(`[data-col-key="${key}"]`).forEach((cell) => {
                if (!(cell instanceof HTMLElement)) {
                    return;
                }
                if (cell.style.display === 'none') {
                    return;
                }
                cell.classList.add('filter-col-focus');
            });
        };

        form.addEventListener('focusin', (e) => {
            const t = e.target;
            if (!(t instanceof HTMLInputElement || t instanceof HTMLSelectElement || t instanceof HTMLTextAreaElement)) {
                return;
            }
            const key = t.dataset.colTarget || t.name || '';
            highlight(key);
        });
        form.addEventListener('focusout', () => {
            setTimeout(() => {
                const active = document.activeElement;
                if (active instanceof HTMLElement && form.contains(active)) {
                    return;
                }
                clear();
            }, 0);
        });
    });
})();
</script>
<script>
(() => {
    document.querySelectorAll('.btn-reset-cols').forEach((btn) => {
        btn.addEventListener('click', () => {
            const defaults = new Set((btn.getAttribute('data-default-cols') || '').split(',').filter(Boolean));
            const details = btn.closest('details');
            if (!details) {
                return;
            }
            const prefix = details.getAttribute('data-col-prefix') || 'col_';
            const orderField = details.getAttribute('data-order-field') || 'col_order';

            const checkboxes = details.querySelectorAll(`input[type="checkbox"][name^="${prefix}"]`);
            checkboxes.forEach((cb) => {
                const key = cb.name.replace(new RegExp(`^${prefix}`), '');
                cb.checked = defaults.has(key);
            });

            const hiddenOrder = document.querySelector(`input[name="${orderField}"]`);
            if (hiddenOrder instanceof HTMLInputElement) {
                const ordered = Array.from(details.querySelectorAll('.visible-item'))
                    .map((item) => item.getAttribute('data-col-key'))
                    .filter((key) => key && defaults.has(key));
                hiddenOrder.value = ordered.join(',');
            }
        });
    });
})();
</script>
<script>
(() => {
    document.querySelectorAll('.visible-columns').forEach((details) => {
        const row = details.querySelector('.visible-row');
        if (!row) {
            return;
        }
        const prefix = details.getAttribute('data-col-prefix') || 'col_';
        const orderField = details.getAttribute('data-order-field') || 'col_order';
        const hiddenOrder = document.querySelector(`input[name="${orderField}"]`);

        const currentOrder = hiddenOrder instanceof HTMLInputElement ? hiddenOrder.value.trim() : '';
        if (currentOrder) {
            const map = new Map();
            row.querySelectorAll('.visible-item').forEach((item) => {
                const key = item.getAttribute('data-col-key');
                if (key) {
                    map.set(key, item);
                }
            });
            currentOrder.split(',').map((s) => s.trim()).forEach((key) => {
                const item = map.get(key);
                if (item) {
                    row.appendChild(item);
                    map.delete(key);
                }
            });
        }

        const syncOrder = () => {
            const orderedKeys = [];
            row.querySelectorAll('.visible-item').forEach((item) => {
                const key = item.getAttribute('data-col-key');
                if (!key) {
                    return;
                }
                const cb = item.querySelector(`input[name="${prefix}${key}"]`);
                if (!(cb instanceof HTMLInputElement) || !cb.checked) {
                    return;
                }
                orderedKeys.push(key);
            });
            if (hiddenOrder instanceof HTMLInputElement) {
                hiddenOrder.value = orderedKeys.join(',');
            }
        };

        let dragSrc = null;
        row.querySelectorAll('.visible-item').forEach((item) => {
            item.addEventListener('dragstart', (e) => {
                dragSrc = item;
                item.classList.add('dragging');
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                }
            });
            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                dragSrc = null;
                syncOrder();
            });
            item.addEventListener('dragover', (e) => {
                if (!dragSrc || dragSrc === item) {
                    return;
                }
                e.preventDefault();
                const rect = item.getBoundingClientRect();
                const before = (e.clientX - rect.left) < rect.width / 2;
                if (before) {
                    row.insertBefore(dragSrc, item);
                } else {
                    row.insertBefore(dragSrc, item.nextSibling);
                }
            });
        });

        row.querySelectorAll('.col-check').forEach((cb) => {
            cb.addEventListener('change', syncOrder);
        });
        syncOrder();
    });
})();
</script>
<script>
(() => {
    const isSubtableDetails = (d) =>
        d instanceof HTMLDetailsElement &&
        !d.classList.contains('filter-details') &&
        !!d.querySelector(':scope > table');

    const allDetails = Array.from(document.querySelectorAll('.content details')).filter(isSubtableDetails);
    if (allDetails.length === 0) {
        return;
    }

    const host = document.querySelector('.content form.table-filters') || document.querySelector('.content section.card');
    if (!host) {
        return;
    }

    const row = document.createElement('div');
    row.style.display = 'flex';
    row.style.gap = '10px';
    row.style.margin = '8px 0 10px';

    const openBtn = document.createElement('button');
    openBtn.type = 'button';
    openBtn.className = 'subtables-mini-btn';
    openBtn.innerHTML = '&#8595;';
    openBtn.title = 'Desplegar subtablas';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'subtables-mini-btn';
    closeBtn.innerHTML = '&#8593;';
    closeBtn.title = 'Plegar subtablas';

    const syncOpen = (open) => {
        allDetails.forEach((d) => {
            d.open = open;
        });
    };

    openBtn.addEventListener('click', () => syncOpen(true));
    closeBtn.addEventListener('click', () => syncOpen(false));

    row.appendChild(openBtn);
    row.appendChild(closeBtn);
    host.insertAdjacentElement('afterend', row);
})();
</script>
<script>
(() => {
    const isNumericText = (text) => /^-?\d+(?:[.,]\d+)?$/.test(text.trim());
    document.querySelectorAll('.content table').forEach((table) => {
        const bodyRows = Array.from(table.querySelectorAll('tbody tr'));
        if (bodyRows.length === 0) {
            return;
        }

        const colCount = Math.max(...bodyRows.map((tr) => tr.children.length), 0);
        const numericCols = new Array(colCount).fill(false);

        for (let i = 0; i < colCount; i += 1) {
            let nonEmpty = 0;
            let numeric = 0;
            bodyRows.forEach((tr) => {
                const cell = tr.children[i];
                if (!(cell instanceof HTMLTableCellElement)) {
                    return;
                }
                const raw = (cell.textContent || '').trim();
                if (raw === '') {
                    return;
                }
                nonEmpty += 1;
                if (isNumericText(raw)) {
                    numeric += 1;
                }
            });
            numericCols[i] = nonEmpty > 0 && (numeric / nonEmpty) >= 0.8;
        }

        bodyRows.forEach((tr) => {
            Array.from(tr.children).forEach((cell, idx) => {
                if (numericCols[idx] && cell instanceof HTMLTableCellElement) {
                    cell.style.textAlign = 'right';
                }
            });
        });

        table.querySelectorAll('thead tr').forEach((tr) => {
            Array.from(tr.children).forEach((cell, idx) => {
                if (numericCols[idx] && cell instanceof HTMLTableCellElement) {
                    cell.style.textAlign = 'right';
                }
            });
        });
    });
})();
</script>
<script>
(() => {
    const view = <?= json_encode($view, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (view !== 'expeditions') {
        return;
    }

    const collectOpenState = (url) => {
        url.searchParams.delete('open_exp');
        url.searchParams.delete('open_kill');
        document.querySelectorAll('details.exp-kills-details[open]').forEach((d) => {
            const id = d.getAttribute('data-exp-id');
            if (id) {
                url.searchParams.append('open_exp', id);
            }
        });
        document.querySelectorAll('details.kill-hits-details[open]').forEach((d) => {
            const id = d.getAttribute('data-kill-id');
            if (id) {
                url.searchParams.append('open_kill', id);
            }
        });
    };

    document.querySelectorAll('.content a.th-sort').forEach((link) => {
        link.addEventListener('click', (ev) => {
            const href = link.getAttribute('href') || '';
            if (!href.startsWith('?')) {
                return;
            }
            ev.preventDefault();
            const url = new URL(href, window.location.origin);
            collectOpenState(url);
            window.location.assign(url.pathname + '?' + url.searchParams.toString());
        });
    });
})();
</script>
<script>
(() => {
    const wireDetailsToSubRow = (details) => {
        if (!(details instanceof HTMLDetailsElement)) {
            return;
        }
        if (details.dataset.wiredSubrow === '1' || details.classList.contains('filter-details')) {
            return;
        }
        if (details.closest('.subtable-panels.stats-parallel') || details.closest('.subtable-panels.competitions-panels')) {
            return;
        }

        const nestedTable = details.querySelector(':scope > table');
        if (!(nestedTable instanceof HTMLTableElement)) {
            return;
        }

        const ownerCell = details.closest('td');
        const ownerRow = ownerCell ? ownerCell.parentElement : null;
        if (!(ownerCell instanceof HTMLTableCellElement) || !(ownerRow instanceof HTMLTableRowElement)) {
            return;
        }

        const subRow = document.createElement('tr');
        subRow.className = 'subtable-row-js';
        if (details.classList.contains('kill-hits-details')) {
            subRow.classList.add('nested-hits-row');
        }
        const isExpSubtable = details.classList.contains('exp-kills-details');

        const offsetCell = document.createElement('td');
        offsetCell.className = 'subtable-offset';
        subRow.appendChild(offsetCell);

        const containerCell = document.createElement('td');
        containerCell.className = 'subtable-container';
        containerCell.colSpan = Math.max(1, ownerRow.cells.length - 1);
        containerCell.appendChild(nestedTable);
        subRow.appendChild(containerCell);

        const nextDataRowBeforeInsert = ownerRow.nextElementSibling;
        const parentTable = ownerRow.closest('table');
        const parentHeaderRow = parentTable && parentTable.tHead ? parentTable.tHead.querySelector('tr') : null;
        let repeatedHeaderRow = null;
        if (isExpSubtable && parentHeaderRow && nextDataRowBeforeInsert instanceof HTMLTableRowElement) {
            repeatedHeaderRow = document.createElement('tr');
            repeatedHeaderRow.className = 'repeated-exp-header';
            Array.from(parentHeaderRow.children).forEach((th) => {
                const clone = th.cloneNode(true);
                clone.querySelectorAll('a').forEach((a) => {
                    const span = document.createElement('span');
                    span.textContent = (a.textContent || '').trim();
                    a.replaceWith(span);
                });
                repeatedHeaderRow.appendChild(clone);
            });
        }

        if (ownerRow.nextSibling) {
            ownerRow.parentElement.insertBefore(subRow, ownerRow.nextSibling);
        } else {
            ownerRow.parentElement.appendChild(subRow);
        }
        if (repeatedHeaderRow) {
            if (subRow.nextSibling) {
                subRow.parentElement.insertBefore(repeatedHeaderRow, subRow.nextSibling);
            } else {
                subRow.parentElement.appendChild(repeatedHeaderRow);
            }
        }

        const sync = () => {
            subRow.style.display = details.open ? '' : 'none';
            if (repeatedHeaderRow) {
                repeatedHeaderRow.style.display = details.open ? '' : 'none';
            }
        };
        details.addEventListener('toggle', sync);
        sync();
        details.dataset.wiredSubrow = '1';
    };

    document.querySelectorAll('.content details').forEach(wireDetailsToSubRow);

    const isInteractiveTarget = (el) => {
        if (!(el instanceof Element)) {
            return false;
        }
        return !!el.closest('a,button,input,select,textarea,label,summary');
    };

    document.querySelectorAll('.content table tbody tr').forEach((row) => {
        if (!(row instanceof HTMLTableRowElement) || row.classList.contains('subtable-row-js') || row.classList.contains('repeated-exp-header')) {
            return;
        }
        const detailsList = Array.from(row.querySelectorAll(':scope details'))
            .filter((d) => d instanceof HTMLDetailsElement && !d.classList.contains('filter-details'));
        if (detailsList.length !== 1) {
            return;
        }
        const details = detailsList[0];
        const summary = details.querySelector(':scope > summary');
        if (!(summary instanceof HTMLElement)) {
            return;
        }
        row.classList.add('row-toggle-subtable');
        row.addEventListener('click', (ev) => {
            if (isInteractiveTarget(ev.target)) {
                return;
            }
            summary.click();
        });
    });
})();
</script>
<script>
(() => {
    document.querySelectorAll('.content th, .content .visible-item span').forEach((el) => {
        const raw = el.textContent || '';
        const cleaned = raw.replace(/\s*\(exp\)\s*/gi, ' ').replace(/\s{2,}/g, ' ').trim();
        if (cleaned !== raw.trim()) {
            el.textContent = cleaned;
        }
    });
})();
</script>
<script>
(() => {
    const view = <?= json_encode($view, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const form = document.querySelector('.content form.table-filters');
    if (!form || !view) {
        return;
    }

    const key = `thc_filters_${view}`;
    const ignored = new Set(['view', 'theme', 'flash', 'page', 'sort', 'dir', 'reset', 'export', 'export_csv']);
    const params = new URLSearchParams(window.location.search);
    const forceReset = params.get('reset') === '1';
    const hasUserFilters = [...params.keys()].some((name) => !ignored.has(name));
    const isLayoutParam = (name) => {
        if (['view', 'theme', 'page_size', 'sort', 'dir'].includes(name)) {
            return true;
        }
        if (/^[a-z]+_sort$/i.test(name) || /^[a-z]+_dir$/i.test(name)) {
            return true;
        }
        if (/^[a-z]*col_/i.test(name) || /_order$/i.test(name)) {
            return true;
        }
        return false;
    };

    const saveState = () => {
        const state = {};
        Array.from(form.elements).forEach((el) => {
            if (!el.name || el.name === 'export' || el.name === 'export_csv') {
                return;
            }
            if (el instanceof HTMLSelectElement && el.multiple) {
                state[el.name] = Array.from(el.selectedOptions).map((opt) => opt.value);
                return;
            }
            if (el instanceof HTMLInputElement && el.type === 'checkbox') {
                state[el.name] = el.checked ? '1' : '0';
                return;
            }
            state[el.name] = el.value ?? '';
        });
        try {
            localStorage.setItem(key, JSON.stringify(state));
        } catch (_) {}
    };

    if (forceReset) {
        try {
            const raw = localStorage.getItem(key);
            if (raw) {
                const state = JSON.parse(raw);
                if (state && typeof state === 'object') {
                    const kept = {};
                    Object.keys(state).forEach((name) => {
                        if (isLayoutParam(name)) {
                            kept[name] = state[name];
                        }
                    });
                    localStorage.setItem(key, JSON.stringify(kept));
                } else {
                    localStorage.removeItem(key);
                }
            }
        } catch (_) {
            try { localStorage.removeItem(key); } catch (_) {}
        }

        const next = new URLSearchParams();
        const theme = params.get('theme');
        next.set('view', view);
        if (theme) {
            next.set('theme', theme);
        }
        for (const [name, value] of params.entries()) {
            if (name === 'reset' || name === 'page' || name === 'flash') {
                continue;
            }
            if (!isLayoutParam(name)) {
                continue;
            }
            next.append(name, value);
        }
        window.location.replace(`?${next.toString()}`);
        return;
    }

    if (!forceReset) {
        try {
            const raw = localStorage.getItem(key);
            if (raw) {
                const state = JSON.parse(raw);
                if (state && typeof state === 'object' && !hasUserFilters) {
                    const next = new URLSearchParams();
                    const theme = params.get('theme');
                    next.set('view', view);
                    if (theme) {
                        next.set('theme', theme);
                    }
                    Array.from(form.elements).forEach((el) => {
                        if (!el.name || el.name === 'export' || el.name === 'export_csv' || !(el.name in state)) {
                            return;
                        }
                        const v = state[el.name];
                        if (el instanceof HTMLSelectElement && el.multiple) {
                            const selected = Array.isArray(v) ? v.map(String) : [];
                            selected.forEach((val) => {
                                if (val !== '') {
                                    next.append(el.name, val);
                                }
                            });
                            return;
                        }
                        const scalar = String(v ?? '');
                        if (el instanceof HTMLInputElement && el.type === 'checkbox') {
                            if (scalar === '1') {
                                next.set(el.name, '1');
                            }
                            return;
                        }
                        if (scalar !== '') {
                            next.set(el.name, scalar);
                        }
                    });

                    const currentComparable = new URLSearchParams(params.toString());
                    currentComparable.delete('page');
                    if (next.toString() !== currentComparable.toString()) {
                        window.location.replace(`?${next.toString()}`);
                        return;
                    }
                } else if (state && typeof state === 'object') {
                    Array.from(form.elements).forEach((el) => {
                        if (!el.name || el.name === 'export' || el.name === 'export_csv' || !(el.name in state) || params.has(el.name)) {
                            return;
                        }
                        if (el instanceof HTMLSelectElement && el.multiple) {
                            const selected = Array.isArray(state[el.name]) ? state[el.name].map(String) : [];
                            Array.from(el.options).forEach((opt) => {
                                opt.selected = selected.includes(opt.value);
                            });
                            return;
                        }
                        if (el instanceof HTMLInputElement && el.type === 'checkbox') {
                            el.checked = String(state[el.name]) === '1';
                            return;
                        }
                        el.value = String(state[el.name] ?? '');
                    });
                }
            }
        } catch (_) {}
    }

    form.addEventListener('submit', saveState);
    form.addEventListener('change', saveState);

    document.querySelectorAll('.content a.btn-link[href^="?view="]').forEach((a) => {
        a.addEventListener('click', () => {
            try {
                const href = a.getAttribute('href') || '';
                const sp = new URLSearchParams(href.replace(/^\?/, ''));
                const targetView = sp.get('view') || view;
                if ([...sp.keys()].every((k) => k === 'view' || k === 'theme')) {
                    localStorage.removeItem(`thc_filters_${targetView}`);
                }
            } catch (_) {}
        });
    });
})();
</script>
<script>
(() => {})();
















































</script>
</body>
</html>











