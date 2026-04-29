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
$font = app_font();
TaskScheduleManager::runDueTasks(app_is_admin_user(), app_auth_username());
$uiPreferences = app_ui_preferences_all();

function menu_link(string $key, string $label, string $current): string
{
    $class = $key === $current ? 'nav-link active' : 'nav-link';
    return '<a class="' . $class . '" draggable="true" data-nav-key="' . h($key) . '" href="?view=' . urlencode($key) . '&theme=' . urlencode(app_theme()) . '&font=' . urlencode(app_font()) . '">' . h($label) . '</a>';
}

function theme_link(string $themeName, string $label, string $currentView, string $activeTheme): string
{
    $class = $themeName === $activeTheme ? 'theme-chip active' : 'theme-chip';
    return '<a class="' . $class . '" href="?view=' . urlencode($currentView) . '&theme=' . urlencode($themeName) . '&font=' . urlencode(app_font()) . '">' . h($label) . '</a>';
}

function font_link(string $fontName, string $label, string $sample, string $currentView, string $activeFont, string $fontClass): string
{
    $class = 'font-chip ' . $fontClass . ($fontName === $activeFont ? ' active' : '');
    return '<a class="' . h($class) . '" href="?view=' . urlencode($currentView) . '&theme=' . urlencode(app_theme()) . '&font=' . urlencode($fontName) . '">'
        . '<span class="font-chip-name">' . h($label) . '</span>'
        . '<span class="font-chip-sample">' . h($sample) . '</span>'
        . '</a>';
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

function thehunter_kill_url(?string $playerName, mixed $killId): ?string
{
    $playerName = trim((string) $playerName);
    $killId = trim((string) $killId);
    if ($playerName === '' || $killId === '') {
        return null;
    }

    return 'https://www.thehunter.com/#profile/' . rawurlencode(strtolower($playerName)) . '/score/' . rawurlencode($killId);
}

function thehunter_profile_url(?string $playerName): ?string
{
    $playerName = trim((string) $playerName);
    if ($playerName === '') {
        return null;
    }

    return 'https://www.thehunter.com/#profile/' . rawurlencode(strtolower($playerName));
}

function player_profile_link_html(?string $playerName, mixed $label = null, string $class = 'record-link record-link-player'): string
{
    $playerName = trim((string) $playerName);
    $labelText = trim((string) ($label ?? $playerName));
    if ($labelText === '') {
        return '';
    }

    $url = thehunter_profile_url($playerName);
    if ($url === null) {
        return h($labelText);
    }

    return '<a class="' . h($class) . '" href="' . h($url) . '" target="_blank" rel="noopener noreferrer">' . h($labelText) . '</a>';
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

function normalize_species_icon_src(string $speciesKey, string $src): string
{
    $src = trim($src);
    if ($src === '') {
        return '';
    }

    if ($speciesKey === 'puma') {
        $src = str_replace(
            ['Cougar_male_common.png', 'Cougar_female_common.png'],
            ['Puma_male_common.png', 'Puma_female_common.png'],
            $src
        );
    }

    if ($speciesKey === 'cabra salvaje' || $speciesKey === 'feral goat') {
        $src = str_replace(
            ['Feral_goat_male_common.png', 'Feral_goat_female_common.png'],
            ['Feral_goat_male_brown.png', 'Feral_goat_female_brown.png'],
            $src
        );
    }

    if ($speciesKey === 'bisonte' || $speciesKey === 'bison') {
        $src = str_replace(
            ['Bison_common.png'],
            ['Bison_male_common.png'],
            $src
        );
    }

    return $src;
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
            $map[$key] = [
                'm' => normalize_species_icon_src($key, $iconM),
                'f' => normalize_species_icon_src($key, $iconF),
            ];
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
    $sharedCommonKeys = [
        'bisonte' => true,
        'bison' => true,
        'oso pardo' => true,
        'brown bear' => true,
        'oso grizzly' => true,
        'grizzly bear' => true,
        'oso negro' => true,
        'black bear' => true,
        'lobo gris' => true,
        'grey wolf' => true,
        'coyote' => true,
        'zorro rojo' => true,
        'red fox' => true,
        'zorro artico' => true,
        'arctic fox' => true,
        'lince europeo' => true,
        'eurasian lynx' => true,
        'lince rojo' => true,
        'bobcat' => true,
        'conejo europeo' => true,
        'european rabbit' => true,
        'conejo de cola algodon' => true,
        'cottontail rabit' => true,
        'cottontail rabbit' => true,
        'liebre artica' => true,
        'liebre americana' => true,
        'snowshoe hare' => true,
        'ganso del canada' => true,
        'ganso de canada' => true,
        'canada goose' => true,
        'ganso urraca' => true,
        'ganso urraco' => true,
        'magpie goose' => true,
    ];
    if (isset($sharedCommonKeys[$key]) && trim((string) ($map[$key]['m'] ?? '')) !== '') {
        $srcPrimary = (string) ($map[$key]['m'] ?? '');
        $srcSecondary = (string) ($map[$key]['f'] ?? '');
    } else {
        $srcPrimary = $code === 'M' ? (string) ($map[$key]['m'] ?? '') : (string) ($map[$key]['f'] ?? '');
        $srcSecondary = $code === 'M' ? (string) ($map[$key]['f'] ?? '') : (string) ($map[$key]['m'] ?? '');
    }
    $src = $srcPrimary !== '' ? $srcPrimary : $srcSecondary;
    if ($src === '') {
        return '';
    }
    $alt = $code === 'M' ? 'Macho' : 'Hembra';
    return '<img class="species-gender-icon" src="' . h($src) . '" alt="' . h($alt) . '" title="' . h($alt) . '" loading="lazy" decoding="async" onerror="this.remove()">';
}

function gender_badge_html(mixed $genderValue): string
{
    $code = gender_code_from_value($genderValue);
    if ($code === null) {
        $text = trim((string) $genderValue);
        return $text === '' ? '' : h($text);
    }

    $class = $code === 'M' ? 'gender-badge gender-badge-m' : 'gender-badge gender-badge-f';
    return '<span class="' . $class . '" title="' . h($code === 'M' ? 'Macho' : 'Hembra') . '">' . h($code) . '</span>';
}

function species_icons_pair_html(string $speciesName): string
{
    $iconM = gender_species_icon_html($speciesName, 'M');
    $iconF = gender_species_icon_html($speciesName, 'F');
    if ($iconM === '' && $iconF === '') {
        return '';
    }
    return '<span class="species-icons-pair"><span class="species-icon-slot">' . gender_badge_html('M') . $iconM . '</span><span class="species-icon-slot">' . gender_badge_html('F') . $iconF . '</span></span>';
}

function species_single_icon_html(string $speciesName): string
{
    return gender_species_icon_html($speciesName, 'M');
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

    $classes = ['th-sort'];
    if ($currentKey === $columnKey) {
        $classes[] = $currentDir === 'asc' ? 'is-asc' : 'is-desc';
    }

    return '<a class="' . h(implode(' ', $classes)) . '" href="?' . h(http_build_query($query)) . '">' . h($label) . '</a>';
}

function sort_link_param(string $sortParam, string $dirParam, string $columnKey, string $label, string $currentKey, string $currentDir): string
{
    $query = $_GET;
    unset($query['export'], $query['export_csv']);
    $query[$sortParam] = $columnKey;
    $query[$dirParam] = $currentKey === $columnKey && $currentDir === 'asc' ? 'desc' : 'asc';
    $query['page'] = 1;

    $classes = ['th-sort'];
    if ($currentKey === $columnKey) {
        $classes[] = $currentDir === 'asc' ? 'is-asc' : 'is-desc';
    }

    return '<a class="' . h(implode(' ', $classes)) . '" href="?' . h(http_build_query($query)) . '">' . h($label) . '</a>';
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

function competition_type_name_suggestions(): array
{
    static $values = null;
    if ($values !== null) {
        return $values;
    }

    try {
        $rows = app_query_all(
            "SELECT DISTINCT type_name
             FROM gpt.comp_types
             WHERE type_name IS NOT NULL AND type_name <> ''
             ORDER BY type_name"
        );
    } catch (Throwable) {
        $values = [];
        return $values;
    }

    $values = [];
    foreach ($rows as $row) {
        $name = trim((string) ($row['type_name'] ?? ''));
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

    $defs['e_weapon_hits_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(w.hits, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(w.hits, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'label' => 'Aciertos arma',
    ];
    $defs['e_weapon_misses_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(w.misses, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(w.misses, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'label' => 'Fallos arma',
    ];
    $defs['e_weapon_kills_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(w.kills, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(w.kills, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'label' => 'Muertes arma',
    ];
    $defs['e_weapon_ethical_kills_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(w.ethical_kills, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(w.ethical_kills, 0)), 0) FROM gpt.exp_weapon_stats w WHERE w.expedition_id = e.expedition_id)',
        'label' => 'Eticos arma',
    ];
    $defs['e_animal_kills_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(a.kills, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(a.kills, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'label' => 'Muertes animal',
    ];
    $defs['e_animal_spots_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(a.spots, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(a.spots, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'label' => 'Spots animal',
    ];
    $defs['e_animal_tracks_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(a.tracks, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(a.tracks, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'label' => 'Tracks animal',
    ];
    $defs['e_animal_ethical_kills_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(a.ethical_kills, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(a.ethical_kills, 0)), 0) FROM gpt.exp_animal_stats a WHERE a.expedition_id = e.expedition_id)',
        'label' => 'Eticos animal',
    ];
    $defs['e_collectables_collected_total'] = [
        'expr' => '(SELECT COALESCE(SUM(COALESCE(c.collected, 0)), 0) FROM gpt.exp_collectables c WHERE c.expedition_id = e.expedition_id)',
        'sort' => '(SELECT COALESCE(SUM(COALESCE(c.collected, 0)), 0) FROM gpt.exp_collectables c WHERE c.expedition_id = e.expedition_id)',
        'label' => 'Collected',
    ];

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
    echo '<select name="preset_name_select" data-single-combo="1" data-single-combo-placeholder="Preset">';
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

    echo '<select name="table" onchange="this.form.submit()" data-single-combo="1" data-single-combo-placeholder="Tabla">';
    foreach ($tables as $table) {
        echo '<option value="' . h($table) . '"' . ($table === $selectedTable ? ' selected' : '') . '>' . h($table) . '</option>';
    }
    echo '</select>';

    echo '<select name="column" data-single-combo="1" data-single-combo-placeholder="Columna">';
    foreach ($columnNames as $col) {
        echo '<option value="' . h($col) . '"' . ($col === $selectedColumn ? ' selected' : '') . '>' . h($col) . '</option>';
    }
    echo '</select>';

    echo '<select name="op" data-single-combo="1" data-single-combo-placeholder="Operador">';
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
    $countSql = static function (string $sql): int {
        try {
            $row = app_query_one($sql);
            return (int) ($row['c'] ?? 0);
        } catch (Throwable) {
            return 0;
        }
    };
    $counts = [
        'Expediciones' => $countSql('SELECT COUNT(*) AS c FROM gpt.exp_expeditions'),
        'Muertes' => $countSql('SELECT COUNT(*) AS c FROM gpt.exp_kills'),
        'Disparos' => $countSql('SELECT COUNT(*) AS c FROM gpt.exp_hits'),
        'Mejores Marcas' => $countSql('SELECT COUNT(*) AS c FROM gpt.best_personal_records'),
        'Usuarios' => $countSql('SELECT COUNT(*) AS c FROM gpt.users'),
        'Perfiles EST' => $countSql('SELECT COUNT(*) AS c FROM gpt.est_profiles'),
        'Trofeos' => $countSql('SELECT COUNT(*) AS c FROM gpt.user_trophies'),
        'Galeria Fotos' => $countSql('SELECT COUNT(*) AS c FROM gpt.user_gallery'),
        'Competiciones' => $countSql('SELECT COUNT(*) AS c FROM gpt.comp_competitions'),
        'Inscripciones' => $countSql('SELECT COUNT(*) AS c FROM gpt.comp_join_results'),
        'Clasificacion' => $countSql('SELECT COUNT(*) AS c FROM gpt.clas_rankings_latest'),
        'Clasif. Historico' => $countSql('SELECT COUNT(*) AS c FROM gpt.clas_rankings_history'),
        'URLs Muertes' => $countSql('SELECT COUNT(*) AS c FROM gpt.scrape_kill_urls'),
        'Detalle Scraper' => $countSql('SELECT COUNT(*) AS c FROM gpt.kill_detail_scrapes'),
    ];

    $tasks = TaskManager::list(8);

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
    echo '<button name="action" value="refresh_leaderboards">Actualizar Tablas Clasificacion</button>';
    echo '<button name="action" value="refresh_best_all">Actualizar Mejores Marcas Usuarios</button>';
    echo '<button name="action" value="refresh_public_all">Actualizar Estadisticas Usuarios</button>';
    echo '<button name="action" value="refresh_trophies_all">Actualizar Trofeos Usuarios</button>';
    echo '<button name="action" value="refresh_gallery_all">Actualizar Galerias Usuarios</button>';
    echo '<button name="action" value="join_all_competitions">Inscribirme en Competiciones</button>';
    if ($isAdmin) {
        echo '<button name="action" value="refresh_expeditions_all_users">Actualizar expediciones de todos</button>';
        echo '<button name="action" value="scrape_kill_urls">Scraper URLs de Muertes</button>';
    }
    echo '</form>';
    echo '</section>';

    $authUser = app_auth_username() ?? '';
    $hasTheHunterCookie = $authUser !== '' && app_thehunter_cookie_for_user($authUser) !== null;
    echo '<section class="card">';
    echo '<h2>Sesion theHunter</h2>';
    echo '<p class="muted">Usuario actual: ' . h($authUser !== '' ? $authUser : '-') . ' | Cookie guardada: ' . ($hasTheHunterCookie ? 'Si' : 'No') . ' | Si no existe, el sistema intentara importarla automaticamente desde Edge/Chrome local antes de pedirla manualmente.</p>';
    echo '<form class="table-filters" method="post" action="save_thehunter_cookie.php">';
    echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
    echo '<textarea name="thehunter_cookie" rows="3" placeholder="Pega aqui la cabecera Cookie completa de una sesion valida de theHunter" title="Cookie completa de sesion de theHunter para inscribirse automaticamente en competiciones"></textarea>';
    echo '<button type="submit" name="mode" value="save">Guardar sesion theHunter</button>';
    echo '<button type="submit" name="mode" value="clear">Eliminar sesion theHunter</button>';
    echo '</form>';
    echo '</section>';

    $taskHistory = TaskManager::list(500);
    $scheduledTasks = TaskScheduleManager::forPanel();
    echo '<section class="card">';
    echo '<h2>Tareas programadas</h2>';
    echo '<table><thead><tr><th>Tarea</th><th>Activa</th><th>Cada (min)</th><th>Jugador</th><th>&Uacute;ltima ejecuci&oacute;n</th><th>Estado</th><th>Ejecutar</th>' . ($isAdmin ? '<th>Guardar</th>' : '') . '</tr></thead><tbody>';
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
        $saveFormId = 'task-save-' . preg_replace('/[^a-z0-9_-]/i', '-', (string) $action);
        $runFormId = 'task-run-' . preg_replace('/[^a-z0-9_-]/i', '-', (string) $action);

        echo '<tr>';
        echo '<td class="task-label-editable" data-task-label-key="' . h('scheduled:' . (string) $action) . '">' . h((string) $def['label']) . '</td>';
        if ($isAdmin) {
            $checked = ((bool) ($def['enabled'] ?? false)) ? ' checked' : '';
            echo '<td><label><input type="checkbox" name="enabled" value="1" form="' . h($saveFormId) . '"' . $checked . '> Si</label></td>';
            echo '<td><input type="number" name="interval_min" min="1" max="10080" value="' . h((string) ($def['interval_min'] ?? 180)) . '" form="' . h($saveFormId) . '" style="width:92px"></td>';
            if ((string) $action === 'scrape_kill_details') {
                echo '<td><input type="text" name="player" value="' . h((string) ($def['player'] ?? '')) . '" form="' . h($saveFormId) . '" style="width:140px" placeholder="Jugador"></td>';
            } else {
                echo '<td><span class="muted">-</span></td>';
            }
        } else {
            echo '<td>' . (((bool) ($def['enabled'] ?? false)) ? 'Si' : 'No') . '</td>';
            echo '<td>' . h((string) ($def['interval_min'] ?? 180)) . '</td>';
            $playerText = trim((string) ($def['player'] ?? ''));
            echo '<td>' . ($playerText !== '' ? h($playerText) : '<span class="muted">-</span>') . '</td>';
        }
        echo '<td>' . h($lastAt) . '</td>';
        echo '<td>' . h($lastStatus) . '</td>';

        echo '<td>';
        if ($canRun && $isBusy) {
            echo '<span class="muted">En ejecucion</span>';
        } elseif ($canRun) {
            echo '<form id="' . h($runFormId) . '" method="post" action="task_create.php" class="task-stop-form">';
            echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
            echo '<input type="hidden" name="action" value="' . h((string) $action) . '">';
            echo '<button type="submit">Ejecutar</button>';
            echo '</form>';
        } else {
            echo '<span class="muted">-</span>';
        }
        echo '</td>';

        if ($isAdmin) {
            echo '<td>';
            echo '<form id="' . h($saveFormId) . '" method="post" action="task_schedule_save.php">';
            echo '<input type="hidden" name="csrf_token" value="' . h(app_csrf_token()) . '">';
            echo '<input type="hidden" name="action" value="' . h((string) $action) . '">';
            echo '<button type="submit">Guardar</button>';
            echo '</form>';
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</section>';

    echo '<section class="card">';
    echo '<h2>Tareas recientes</h2>';
    echo '<table><thead><tr><th>Etiqueta</th><th>Estado</th><th>Creada</th><th>Log</th><th>Acci&oacute;n</th></tr></thead><tbody>';
    foreach ($tasks as $task) {
        $taskId = (string) ($task['id'] ?? '');
        $taskStatus = (string) ($task['status'] ?? '');
        echo '<tr>';
        $recentTaskKey = (string) ($task['action'] ?? '');
        if ($recentTaskKey === '') {
            $recentTaskKey = 'task:' . $taskId;
        } else {
            $recentTaskKey = 'recent:' . $recentTaskKey;
        }
        echo '<td class="task-label-editable" data-task-label-key="' . h($recentTaskKey) . '">' . h((string) ($task['label'] ?? '')) . '</td>';
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
    echo '</section>';
}

function render_table_styles_preview(): void
{
    $rows = [
        ['13415904', '21/04/2026 21:02:50', 'Nefastix13', 'Val-des-Bois', '12', '4791.820', '100.000'],
        ['13415741', '21/04/2026 20:46:51', 'Nefastix13', 'Settler Creeks', '0', '6052.530', '94.924'],
        ['13409280', '17/04/2026 10:04:40', 'TheBubb', 'Piccabeen Bay', '5', '244.117', '100.000'],
        ['13408877', '16/04/2026 21:13:44', 'Bansan_', 'Whiterime Ridge', '1', '319.060', '86.420'],
    ];
    $styles = [
        'table-style-forest' => 'Forest claro',
        'table-style-graphite' => 'Grafito ambar',
        'table-style-navy' => 'Azul tactico',
        'table-style-parchment' => 'Pergamino',
        'table-style-terminal' => 'Terminal',
        'table-style-steel' => 'Acero compacto',
        'table-style-burgundy' => 'Borgona',
        'table-style-minimal' => 'Minimal blanco',
    ];

    echo '<section class="card table-style-preview"><h2>Estilos de tablas de datos</h2>';
    echo '<p class="muted">Vista de comparacion. Son tablas HTML reales, no imagenes.</p>';
    echo '<div class="table-style-grid">';
    foreach ($styles as $class => $title) {
        echo '<article class="table-style-card ' . h($class) . '">';
        echo '<h3>' . h($title) . '</h3>';
        echo '<table><thead><tr>';
        foreach (['IdExpedicion', 'Inicio', 'Jugador', 'Reserva', 'Muertes', 'Puntuacion', 'Harvest'] as $idx => $label) {
            $arrow = $idx === 0 ? ' <span class="active-sort-arrow">↓</span>' : '';
            echo '<th>' . h($label) . $arrow . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $idx => $value) {
                $classAttr = in_array($idx, [0, 4, 5, 6], true) ? ' class="num-cell"' : '';
                echo '<td' . $classAttr . '>' . h($value) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</article>';
    }
    echo '</div></section>';
}

function render_expeditions(): void
{
    $hasKillDetailExpeditionId = app_relation_has_column('gpt', 'v_kill_detail_scrapes_latest', 'expedition_id');
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
        'type_text' => 'Tipo',
        'wound_time_text' => 'Tiempo herida',
        'shot_location_text' => 'Lugar disparo',
        'confirm_at' => 'Confirmado',
        'hits_count' => 'Disparos',
        'hits_avg_distance' => 'Dist Avg (m)',
        'hits_max_distance' => 'Dist Max (m)',
        'hits_organs' => 'Organos',
    ];
    $expNumericCols = [
        'e_expedition_id' => true,
        'e_user_id' => true,
        'e_reserve_id' => true,
        'e_score' => true,
        'e_distance' => true,
        'e_duration' => true,
        'e_kills' => true,
        'e_hits' => true,
        'e_harvest_total' => true,
        'e_integrity_avg' => true,
        'e_weapon_hits_total' => true,
        'e_weapon_misses_total' => true,
        'e_weapon_kills_total' => true,
        'e_weapon_ethical_kills_total' => true,
        'e_animal_kills_total' => true,
        'e_animal_spots_total' => true,
        'e_animal_tracks_total' => true,
        'e_animal_ethical_kills_total' => true,
        'e_collectables_collected_total' => true,
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
    $expCenterCols = [
        'e_single_player' => true,
    ];
    $killCenterCols = [
        'gender' => true,
        'ethical' => true,
    ];
    $hitCenterCols = [];
    $columnAlignClass = static function (string $key, array $numericCols, array $centerCols): string {
        if (isset($centerCols[$key])) {
            return 'col-align-center';
        }
        if (isset($numericCols[$key])) {
            return 'col-align-right';
        }
        return 'col-align-left';
    };
    $defaultKillCols = ['kill_id', 'player_name', 'species_name', 'species_name_es', 'type_text', 'hit_min_distance', 'score', 'harvest_value', 'trophy_integrity', 'wound_time_text', 'shot_location_text', 'weight', 'ethical', 'hits_count'];
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
        'hit_index' => 'Disparo',
        'player_name' => 'Jugador',
        'user_id' => 'IdUsuario',
        'hunter_name' => 'Cazador',
        'weapon_text' => 'Arma',
        'scope_text' => 'Visor',
        'ammo_text' => 'Municion',
        'distance' => 'Distancia (m)',
        'shot_distance_text' => 'Distancia disparo',
        'animal_state_text' => 'Estado animal',
        'body_part_text' => 'Parte cuerpo',
        'posture_text' => 'Postura',
        'platform_text' => 'Plataforma',
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
    $defaultHitCols = ['hit_index', 'hunter_name', 'weapon_text', 'scope_text', 'ammo_text', 'shot_distance_text', 'animal_state_text', 'body_part_text', 'posture_text', 'platform_text'];
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
        'type_text' => 'COALESCE(kd.type_text, \'\')',
        'wound_time_text' => 'COALESCE(kd.wound_time_text, \'\')',
        'shot_location_text' => 'COALESCE(kd.shot_location_text, \'\')',
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
        'hunter_name' => 'COALESCE(hunter_name, \'\')',
        'weapon_text' => 'COALESCE(weapon_text, \'\')',
        'scope_text' => 'COALESCE(scope_text, \'\')',
        'shot_distance_text' => 'COALESCE(shot_distance_text, \'\')',
        'animal_state_text' => 'COALESCE(animal_state_text, \'\')',
        'body_part_text' => 'COALESCE(body_part_text, \'\')',
        'posture_text' => 'COALESCE(posture_text, \'\')',
        'platform_text' => 'COALESCE(platform_text, \'\')',
        'distance' => 'COALESCE(distance, -1)',
        'ammo_text' => 'COALESCE(ammo_text, \'\')',
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
    $killWoundTime = query_text('kill_wound_time');
    $killShotLocation = query_text('kill_shot_location');
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
    $hitHunterText = query_text('hit_hunter_name');
    $hitWeaponText = query_text('hit_weapon_text');
    $hitScopeText = query_text('hit_scope_text');
    $hitAmmoText = query_text('hit_ammo_text');
    $hitShotDistanceText = query_text('hit_shot_distance_text');
    $hitAnimalStateText = query_text('hit_animal_state_text');
    $hitBodyPartText = query_text('hit_body_part_text');
    $hitPostureText = query_text('hit_posture_text');
    $hitPlatformText = query_text('hit_platform_text');
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
        $killWoundTime = null;
        $killShotLocation = null;
        $markFilter = '';
        $photoTaxFilter = '';
        $hitWeaponId = null;
        $hitAmmoId = null;
        $hitOrgan = null;
        $hitHunterText = null;
        $hitWeaponText = null;
        $hitScopeText = null;
        $hitAmmoText = null;
        $hitShotDistanceText = null;
        $hitAnimalStateText = null;
        $hitBodyPartText = null;
        $hitPostureText = null;
        $hitPlatformText = null;
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
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores" data-col-target="e_player_name">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<select name="reserve_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Reserva (todas)" data-check-combo-many-label="reservas" data-col-target="e_reserve_name">';
    echo '<option value="">Reserva (todas)</option>';
    foreach (reserve_name_suggestions() as $name) {
        $selected = in_array($name, $reserveNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="datetime-local" name="start_at" data-col-target="e_start_at" title="Inicio desde fecha/hora" value="' . h(raw_to_datetime_local_value(query_raw('start_at'))) . '">';
    echo '<input type="datetime-local" name="end_at" data-col-target="e_end_at" title="Fin hasta fecha/hora" value="' . h(raw_to_datetime_local_value(query_raw('end_at'))) . '">';
    echo '<input type="text" name="kill_id" data-col-target="k_kill_id" placeholder="Kill ID" value="' . h(query_raw('kill_id')) . '">';
    echo '<input type="text" name="hit_index" data-col-target="h_hit_index" placeholder="Disparo ID" value="' . h(query_raw('hit_index')) . '">';
    echo '<select name="kill_species_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Nombre especie (todas)" data-col-target="k_species_name">';
    echo '<option value="">Nombre especie (todas)</option>';
    foreach (species_es_name_suggestions() as $value) {
        $selected = in_array($value, $killSpeciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="kill_gender" data-col-target="k_gender" placeholder="Kill gender" value="' . h(query_raw('kill_gender')) . '">';
    echo '<select name="kill_ethical" data-single-combo="1" data-single-combo-placeholder="Kill etico" data-col-target="k_ethical"><option value="">Kill etico</option><option value="1"' . (query_raw('kill_ethical') === '1' ? ' selected' : '') . '>Si</option><option value="0"' . (query_raw('kill_ethical') === '0' ? ' selected' : '') . '>No</option></select>';
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
    echo '<input type="text" name="kill_wound_time" data-col-target="k_wound_time_text" placeholder="Tiempo herida" value="' . h(query_raw('kill_wound_time')) . '">';
    echo '<input type="text" name="kill_shot_location" data-col-target="k_shot_location_text" placeholder="Lugar disparo" value="' . h(query_raw('kill_shot_location')) . '">';
    echo '<select name="mark_filter" data-single-combo="1" data-single-combo-placeholder="Marca (todas)"><option value="">Marca (todas)</option><option value="any"' . ($markFilter === 'any' ? ' selected' : '') . '>MM (MMP o MMD)</option><option value="mmp"' . ($markFilter === 'mmp' ? ' selected' : '') . '>Solo MMP</option><option value="mmd"' . ($markFilter === 'mmd' ? ' selected' : '') . '>Solo MMD</option></select>';
    echo '<select name="photo_tax_filter" data-single-combo="1" data-single-combo-placeholder="Foto/Tax"><option value="">Todas</option><option value="ft"' . ($photoTaxFilter === 'ft' ? ' selected' : '') . '>Foto o Tax</option><option value="f"' . ($photoTaxFilter === 'f' ? ' selected' : '') . '>Foto</option><option value="t"' . ($photoTaxFilter === 't' ? ' selected' : '') . '>Tax</option></select>';
    echo '<input type="text" name="hit_weapon_id" data-col-target="h_weapon_id" placeholder="Disparo weapon_id" value="' . h(query_raw('hit_weapon_id')) . '">';
    echo '<input type="text" name="hit_ammo_id" data-col-target="h_ammo_id" placeholder="Disparo ammo_id" value="' . h(query_raw('hit_ammo_id')) . '">';
    echo '<input type="text" name="hit_organ" data-col-target="h_organ" placeholder="Disparo organo" value="' . h(query_raw('hit_organ')) . '">';
    echo '<input type="text" name="hit_hunter_name" data-col-target="h_hunter_name" placeholder="Cazador" value="' . h(query_raw('hit_hunter_name')) . '">';
    echo '<input type="text" name="hit_weapon_text" data-col-target="h_weapon_text" placeholder="Arma" value="' . h(query_raw('hit_weapon_text')) . '">';
    echo '<input type="text" name="hit_scope_text" data-col-target="h_scope_text" placeholder="Visor" value="' . h(query_raw('hit_scope_text')) . '">';
    echo '<input type="text" name="hit_ammo_text" data-col-target="h_ammo_text" placeholder="Municion" value="' . h(query_raw('hit_ammo_text')) . '">';
    echo '<input type="text" name="hit_shot_distance_text" data-col-target="h_shot_distance_text" placeholder="Distancia disparo" value="' . h(query_raw('hit_shot_distance_text')) . '">';
    echo '<input type="text" name="hit_animal_state_text" data-col-target="h_animal_state_text" placeholder="Estado animal" value="' . h(query_raw('hit_animal_state_text')) . '">';
    echo '<input type="text" name="hit_body_part_text" data-col-target="h_body_part_text" placeholder="Parte cuerpo" value="' . h(query_raw('hit_body_part_text')) . '">';
    echo '<input type="text" name="hit_posture_text" data-col-target="h_posture_text" placeholder="Postura" value="' . h(query_raw('hit_posture_text')) . '">';
    echo '<input type="text" name="hit_platform_text" data-col-target="h_platform_text" placeholder="Plataforma" value="' . h(query_raw('hit_platform_text')) . '">';
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
    $scrapedKillMap = [];
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
                           kd.type_text, kd.wound_time_text, kd.shot_location_text,
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
        if ($killWoundTime !== null) {
            $killSql .= ' AND EXISTS (
                SELECT 1
                FROM gpt.v_kill_detail_scrapes_latest kd1
                WHERE kd1.kill_id = k.kill_id
                  ' . ($hasKillDetailExpeditionId ? 'AND COALESCE(kd1.expedition_id, -1) = COALESCE(k.expedition_id, -1)' : '') . '
                  AND kd1.wound_time_text ILIKE :kill_wound_time
            )';
            $killParams[':kill_wound_time'] = '%' . $killWoundTime . '%';
        }
        if ($killShotLocation !== null) {
            $killSql .= ' AND EXISTS (
                SELECT 1
                FROM gpt.v_kill_detail_scrapes_latest kd1
                WHERE kd1.kill_id = k.kill_id
                  ' . ($hasKillDetailExpeditionId ? 'AND COALESCE(kd1.expedition_id, -1) = COALESCE(k.expedition_id, -1)' : '') . '
                  AND kd1.shot_location_text ILIKE :kill_shot_location
            )';
            $killParams[':kill_shot_location'] = '%' . $killShotLocation . '%';
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

        $killSql = str_replace(
            'WHERE k.expedition_id IN (' . implode(', ', $inParts) . ')',
            'LEFT JOIN gpt.v_kill_detail_scrapes_latest kd
                    ON kd.kill_id = k.kill_id
                   ' . ($hasKillDetailExpeditionId ? 'AND COALESCE(kd.expedition_id, -1) = COALESCE(k.expedition_id, -1)' : '') . '
             WHERE k.expedition_id IN (' . implode(', ', $inParts) . ')',
            $killSql
        );

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
            $detailInParts = [];
            $detailParams = [];
            foreach ($killIds as $idx => $id) {
                $ph = ':dkill_' . $idx;
                $detailInParts[] = $ph;
                $detailParams[$ph] = $id;
            }
            try {
                $detailRows = app_query_all(
                    'SELECT ' . ($hasKillDetailExpeditionId ? 'expedition_id' : 'NULL::bigint AS expedition_id') . ', kill_id, player_name, scraped_at
                     FROM gpt.v_kill_detail_scrapes_latest
                     WHERE kill_id IN (' . implode(', ', $detailInParts) . ')',
                    $detailParams
                );
                foreach ($detailRows as $drow) {
                    $detailExpeditionId = (int) ($drow['expedition_id'] ?? 0);
                    $detailKillId = (int) ($drow['kill_id'] ?? 0);
                    if ($detailKillId <= 0) {
                        continue;
                    }
                    $scrapedKillMap[$detailExpeditionId . '#' . $detailKillId] = [
                        'player_name' => (string) ($drow['player_name'] ?? ''),
                        'scraped_at' => (string) ($drow['scraped_at'] ?? ''),
                    ];
                }
            } catch (Throwable) {
                $scrapedKillMap = [];
            }

            $hitInParts = [];
            $hitParams = [];
            foreach ($killIds as $idx => $id) {
                $ph = ':kill_' . $idx;
                $hitInParts[] = $ph;
                $hitParams[$ph] = $id;
            }

            $hitSql = 'SELECT h.kill_id, h.hit_index, h.user_id, h.player_name, h.distance, h.weapon_id, h.ammo_id, h.organ,
                              CASE
                                  WHEN NULLIF(BTRIM(kd.hunter_name), \'\') IS NULL THEN h.player_name
                                  WHEN LOWER(BTRIM(kd.hunter_name)) = LOWER(BTRIM(COALESCE(h.player_name, \'\'))) THEN h.player_name
                                  ELSE h.player_name
                              END AS hunter_name,
                              kd.weapon_text, kd.scope_text, kd.ammo_text, kd.shot_distance_text,
                              kd.animal_state_text, kd.body_part_text, kd.posture_text, kd.platform_text, kd.kill_data_json
                       FROM gpt.exp_hits h
                       LEFT JOIN gpt.v_kill_detail_scrapes_latest kd
                              ON kd.kill_id = h.kill_id
                             ' . ($hasKillDetailExpeditionId ? 'AND COALESCE(kd.expedition_id, -1) = COALESCE(h.expedition_id, -1)' : '') . '
                       WHERE h.kill_id IN (' . implode(', ', $hitInParts) . ')';
            if ($hitIndex !== null) {
                $hitSql .= ' AND h.hit_index = :hit_index_exact';
                $hitParams[':hit_index_exact'] = $hitIndex;
            }
            if ($hitWeaponId !== null) {
                $hitSql .= ' AND h.weapon_id = :hit_weapon_id_exact';
                $hitParams[':hit_weapon_id_exact'] = $hitWeaponId;
            }
            if ($hitAmmoId !== null) {
                $hitSql .= ' AND h.ammo_id = :hit_ammo_id_exact';
                $hitParams[':hit_ammo_id_exact'] = $hitAmmoId;
            }
            if ($hitOrgan !== null) {
                $hitSql .= ' AND h.organ = :hit_organ_exact';
                $hitParams[':hit_organ_exact'] = $hitOrgan;
            }
            if ($hitHunterText !== null) {
                $hitSql .= ' AND (
                    CASE
                        WHEN NULLIF(BTRIM(kd.hunter_name), \'\') IS NULL THEN h.player_name
                        WHEN LOWER(BTRIM(kd.hunter_name)) = LOWER(BTRIM(COALESCE(h.player_name, \'\'))) THEN h.player_name
                        ELSE h.player_name
                    END
                ) ILIKE :hit_hunter_name';
                $hitParams[':hit_hunter_name'] = '%' . $hitHunterText . '%';
            }
            if ($hitWeaponText !== null) {
                $hitSql .= ' AND kd.weapon_text ILIKE :hit_weapon_text';
                $hitParams[':hit_weapon_text'] = '%' . $hitWeaponText . '%';
            }
            if ($hitScopeText !== null) {
                $hitSql .= ' AND kd.scope_text ILIKE :hit_scope_text';
                $hitParams[':hit_scope_text'] = '%' . $hitScopeText . '%';
            }
            if ($hitAmmoText !== null) {
                $hitSql .= ' AND kd.ammo_text ILIKE :hit_ammo_text';
                $hitParams[':hit_ammo_text'] = '%' . $hitAmmoText . '%';
            }
            if ($hitShotDistanceText !== null) {
                $hitSql .= ' AND kd.shot_distance_text ILIKE :hit_shot_distance_text';
                $hitParams[':hit_shot_distance_text'] = '%' . $hitShotDistanceText . '%';
            }
            if ($hitAnimalStateText !== null) {
                $hitSql .= ' AND kd.animal_state_text ILIKE :hit_animal_state_text';
                $hitParams[':hit_animal_state_text'] = '%' . $hitAnimalStateText . '%';
            }
            if ($hitBodyPartText !== null) {
                $hitSql .= ' AND kd.body_part_text ILIKE :hit_body_part_text';
                $hitParams[':hit_body_part_text'] = '%' . $hitBodyPartText . '%';
            }
            if ($hitPostureText !== null) {
                $hitSql .= ' AND kd.posture_text ILIKE :hit_posture_text';
                $hitParams[':hit_posture_text'] = '%' . $hitPostureText . '%';
            }
            if ($hitPlatformText !== null) {
                $hitSql .= ' AND kd.platform_text ILIKE :hit_platform_text';
                $hitParams[':hit_platform_text'] = '%' . $hitPlatformText . '%';
            }
            $hitSql .= ' ORDER BY h.kill_id DESC, ' . $hitSortDefs[$hitSortKey] . ' ' . strtoupper($hitSortDir) . ', h.hit_index ASC';

            $hitRows = app_query_all($hitSql, $hitParams);
            foreach ($hitRows as $hitRow) {
                $killIdRow = (int) ($hitRow['kill_id'] ?? 0);
                if ($killIdRow <= 0) {
                    continue;
                }
                $hitRow = merge_hit_scrape_detail($hitRow);
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
    echo '<span><span class="totals-label">Total de Expediciones</span><span class="totals-value-red">' . h((string) $totalExpedicionesVista) . '</span></span>';
    echo '<span><span class="totals-label">Total de Muertes</span><span class="totals-value-red">' . h((string) $totalMuertesVista) . '</span></span>';
    echo '<span><span class="totals-label">Foto/Taxidermia</span><span class="totals-value-red">' . h((string) $totalFotoVista) . 'F ' . h((string) $totalTaxVista) . 'T</span></span>';
    echo '</div>';

    echo '<table><thead><tr>';
    foreach ($selectedCols as $key) {
        $mainThClass = $columnAlignClass($key, $expNumericCols, $expCenterCols);
        echo '<th class="' . h($mainThClass) . '" data-col-key="' . h($key) . '">' . sort_link($key, $columnDefs[$key]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '<th class="col-align-left">Muertes</th>';
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
            } elseif ($key === 'e_player_name') {
                $cellText = player_profile_link_html($expPlayerRaw, $row['e_player_name'] ?? '');
            } elseif ($key === 'e_user_id') {
                $cellText = player_profile_link_html($expPlayerRaw, $row['e_user_id'] ?? '');
            }
            $mainTdClass = $columnAlignClass($key, $expNumericCols, $expCenterCols);
            echo '<td class="' . h($mainTdClass) . '" data-col-key="' . h($key) . '"' . $mainCellStyle . '>' . $cellText . '</td>';
        }
        echo '<td class="col-align-left">';
        if ($killRows === []) {
            echo '<span class="muted">Sin muertes</span>';
        } else {
            $expOpenAttr = '';
            echo '<details class="exp-kills-details" data-exp-id="' . h((string) $expId) . '"' . $expOpenAttr . '><summary>Ver muertes (' . h((string) count($killRows)) . ')</summary>';
            echo '<table><thead><tr>';
            foreach ($selectedKillCols as $colKey) {
                $thClass = $columnAlignClass($colKey, $killNumericCols, $killCenterCols);
                $label = $killColumnDefs[$colKey] ?? $colKey;
                if (!isset($killSortDefs[$colKey])) {
                    echo '<th class="' . h($thClass) . '" data-col-key="k_' . h($colKey) . '">' . h($label) . '</th>';
                } else {
                    echo '<th class="' . h($thClass) . '" data-col-key="k_' . h($colKey) . '">' . sort_link_param('k_sort', 'k_dir', $colKey, $label, $killSortKey, $killSortDir) . '</th>';
                }
            }
            foreach ($selectedHitCols as $hcolKey) {
                $hitThClass = $columnAlignClass($hcolKey, $hitNumericCols, $hitCenterCols);
                $hlabel = $hitColumnDefs[$hcolKey] ?? $hcolKey;
                if (!isset($hitSortDefs[$hcolKey])) {
                    echo '<th class="' . h($hitThClass) . '" data-col-key="h_' . h($hcolKey) . '">' . h($hlabel) . '</th>';
                } else {
                    echo '<th class="' . h($hitThClass) . '" data-col-key="h_' . h($hcolKey) . '">' . sort_link_param('h_sort', 'h_dir', $hcolKey, $hlabel, $hitSortKey, $hitSortDir) . '</th>';
                }
            }
            echo '</tr></thead><tbody>';
            foreach ($killRows as $krow) {
                $currentKillId = (int) ($krow['kill_id'] ?? 0);
                $hitRows = $hitsByKill[$currentKillId] ?? [];
                $combinedHitRows = $hitRows !== [] ? array_values($hitRows) : [[]];
                foreach ($combinedHitRows as $hitRowIndex => $hrow) {
                    echo '<tr>';
                    foreach ($selectedKillCols as $colKey) {
                        if ($hitRowIndex > 0) {
                            $emptyKillClass = $columnAlignClass($colKey, $killNumericCols, $killCenterCols);
                            echo '<td class="' . h($emptyKillClass) . '" data-col-key="k_' . h($colKey) . '"></td>';
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
                        $killTdClass = $columnAlignClass($colKey, $killNumericCols, $killCenterCols);
                        $killTdStyle = '';
                        if ($colKey === 'score' && $scoreCellColor !== null) {
                            $killTdStyle = ' style="color:' . h($scoreCellColor) . ';font-weight:700"';
                        }
                        if ($colKey === 'gender') {
                            echo '<td class="' . h($killTdClass) . '" data-col-key="k_' . h($colKey) . '"' . $killTdStyle . '>' . gender_badge_html($value) . '</td>';
                            continue;
                        }
                        if ($colKey === 'ethical') {
                            $isEthical = in_array((string) $value, ['1', 'true', 't'], true);
                            $icon = $isEthical ? '&#10004;' : '&#10008;';
                            $color = $isEthical ? '#1f9d55' : '#c53030';
                            echo '<td class="' . h($killTdClass) . '" data-col-key="k_' . h($colKey) . '"' . $killTdStyle . '><span style="color:' . h($color) . ';font-weight:700">' . $icon . '</span></td>';
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
                        } elseif ($colKey === 'player_name') {
                            $killText = player_profile_link_html($krow['player_name'] ?? ($row['e_player_name'] ?? ''), $krow['player_name'] ?? '');
                        } elseif ($colKey === 'user_id') {
                            $killText = player_profile_link_html($krow['player_name'] ?? ($row['e_player_name'] ?? ''), $krow['user_id'] ?? '');
                        }
                        echo '<td class="' . h($killTdClass) . '" data-col-key="k_' . h($colKey) . '"' . $killTdStyle . '>' . $killText . '</td>';
                    }

                    foreach ($selectedHitCols as $hcolKey) {
                        $value = $hrow[$hcolKey] ?? '';
                        $hitTdClass = $columnAlignClass($hcolKey, $hitNumericCols, $hitCenterCols);
                        if ($hrow === []) {
                            if ($hcolKey === $selectedHitCols[0]) {
                                echo '<td class="' . h($hitTdClass) . '" data-col-key="h_' . h($hcolKey) . '"><span class="muted">Sin disparos</span></td>';
                            } else {
                                echo '<td class="' . h($hitTdClass) . '" data-col-key="h_' . h($hcolKey) . '"></td>';
                            }
                            continue;
                        }
                        if ($hcolKey === 'player_name' && ($value === null || $value === '')) {
                            $value = $hrow['user_id'] ?? '';
                        }
                        if ($hcolKey === 'distance' && $value !== null && $value !== '' && is_numeric((string) $value)) {
                            $value = number_format(((float) $value) / 1000, 3, '.', '');
                        }
                        $hitTdStyle = '';
                        if ($hcolKey === 'player_name') {
                            echo '<td class="' . h($hitTdClass) . '" data-col-key="h_' . h($hcolKey) . '"' . $hitTdStyle . '>' . player_profile_link_html($hrow['player_name'] ?? ($krow['player_name'] ?? ($row['e_player_name'] ?? '')), $value) . '</td>';
                        } elseif ($hcolKey === 'user_id') {
                            echo '<td class="' . h($hitTdClass) . '" data-col-key="h_' . h($hcolKey) . '"' . $hitTdStyle . '>' . player_profile_link_html($hrow['player_name'] ?? ($krow['player_name'] ?? ($row['e_player_name'] ?? '')), $value) . '</td>';
                        } else {
                            echo '<td class="' . h($hitTdClass) . '" data-col-key="h_' . h($hcolKey) . '"' . $hitTdStyle . '>' . h($value === null ? '' : (string) $value) . '</td>';
                        }
                    }
                    echo '</tr>';
                }
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

/**
 * @param array<string,mixed> $hitRow
 * @return array<string,mixed>
 */
function merge_hit_scrape_detail(array $hitRow): array
{
    $jsonRaw = $hitRow['kill_data_json'] ?? null;
    if (!is_string($jsonRaw) || trim($jsonRaw) === '') {
        return $hitRow;
    }

    $decoded = json_decode($jsonRaw, true);
    if (!is_array($decoded)) {
        return $hitRow;
    }

    $parsedHits = $decoded['parsed_hits'] ?? null;
    if (!is_array($parsedHits) || $parsedHits === []) {
        return $hitRow;
    }

    $targetIndex = is_numeric((string) ($hitRow['hit_index'] ?? null)) ? (int) $hitRow['hit_index'] : null;
    if ($targetIndex === null || $targetIndex <= 0) {
        return $hitRow;
    }

    $match = null;
    foreach ($parsedHits as $offset => $parsedHit) {
        if (!is_array($parsedHit)) {
            continue;
        }
        $parsedIndex = is_numeric((string) ($parsedHit['hit_index'] ?? null)) ? (int) $parsedHit['hit_index'] : ($offset + 1);
        if ($parsedIndex === $targetIndex) {
            $match = $parsedHit;
            break;
        }
    }

    if (!is_array($match)) {
        return $hitRow;
    }

    foreach ([
        'hunter_name',
        'weapon_text',
        'scope_text',
        'ammo_text',
        'shot_distance_text',
        'animal_state_text',
        'body_part_text',
        'posture_text',
        'platform_text',
    ] as $field) {
        $value = $match[$field] ?? null;
        if (is_string($value) && trim($value) !== '') {
            $hitRow[$field] = $value;
        }
    }

    return $hitRow;
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
    $selectParts[] = 'b.player_name AS "__link_player_name"';
    $selectParts[] = 'b.best_score_animal_id AS "__best_score_kill_id"';
    $selectParts[] = 'b.best_distance_animal_id AS "__best_distance_kill_id"';
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
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="hunter_score" placeholder="Hunter Score" value="' . h(query_raw('hunter_score')) . '">';
    echo '<select name="species_name_es[]" multiple data-check-combo="1" data-check-combo-placeholder="Especie (todas)">';
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
    echo '<a class="btn-link" href="?view=best_xml&theme=' . urlencode(app_theme()) . '&font=' . urlencode(app_font()) . '">Comparativa Mejores Marcas</a>';
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
    echo '<span><span class="totals-label">Top\'s D</span><span class="totals-value-red">' . h((string) $topDCount) . '</span></span>';
    echo '<span><span class="totals-label">Top\'s P</span><span class="totals-value-red">' . h((string) $topPCount) . '</span></span>';
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
        $bestPlayerName = trim((string) ($row['__link_player_name'] ?? ($row['player_name'] ?? '')));
        $bestScoreKillUrl = thehunter_kill_url($bestPlayerName, $row['__best_score_kill_id'] ?? null);
        $bestDistanceKillUrl = thehunter_kill_url($bestPlayerName, $row['__best_distance_kill_id'] ?? null);
        $isTopScore = is_numeric((string) $score) && is_numeric((string) $tops['score']) && ((float) $score >= ((float) $tops['score'] - 0.0001));
        $isTopDistance = is_numeric((string) $distance) && is_numeric((string) $tops['distance']) && ((float) $distance >= ((float) $tops['distance'] - 0.0005));

        $rowClass = ($isTopScore || $isTopDistance) ? ' class="best-species-row"' : '';
        echo '<tr' . $rowClass . '>';
        foreach ($selectedCols as $key) {
            $cell = h((string) ($row[$key] ?? ''));
            if ($key === 'best_score_value' && $isTopScore && $cell !== '') {
                $cell = '<span class="best-species-badge">TOP P</span> ' . $cell;
                if ($bestScoreKillUrl !== null) {
                    $cell = '<a class="record-link record-link-kill" href="' . h($bestScoreKillUrl) . '" target="_blank" rel="noopener noreferrer">' . $cell . '</a>';
                }
                echo '<td class="best-species-score">' . $cell . '</td>';
                continue;
            }
            if ($key === 'best_distance_m' && $isTopDistance && $cell !== '') {
                $cell = '<span class="best-species-badge">TOP D</span> ' . $cell;
                if ($bestDistanceKillUrl !== null) {
                    $cell = '<a class="record-link record-link-kill" href="' . h($bestDistanceKillUrl) . '" target="_blank" rel="noopener noreferrer">' . $cell . '</a>';
                }
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
            if ($key === 'best_score_value' && $cell !== '' && $bestScoreKillUrl !== null) {
                $cell = '<a class="record-link record-link-kill" href="' . h($bestScoreKillUrl) . '" target="_blank" rel="noopener noreferrer">' . $cell . '</a>';
            }
            if ($key === 'best_distance_m' && $cell !== '' && $bestDistanceKillUrl !== null) {
                $cell = '<a class="record-link record-link-kill" href="' . h($bestDistanceKillUrl) . '" target="_blank" rel="noopener noreferrer">' . $cell . '</a>';
            }
            if ($key === 'player_name') {
                $cell = player_profile_link_html($bestPlayerName, $row['player_name'] ?? '');
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
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores">';
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
        $animalCount = count($animalsByUser[$uid] ?? []);
        $weaponCount = count($weaponsByUser[$uid] ?? []);
        $collectCount = count($collectablesByUser[$uid] ?? []);
        $missionCount = count($missionsByUser[$uid] ?? []);
        echo '<tr>';
        foreach ($selectedCols as $key) {
            $value = $row[$key] ?? '';
            if ($key === 'distance' && $value !== null && $value !== '' && is_numeric((string) $value)) {
                $value = number_format((float) $value, 3, '.', '');
            }
            if ($key === 'player_name') {
                echo '<td>' . player_profile_link_html($row['player_name'] ?? '', $value) . '</td>';
            } elseif ($key === 'user_id') {
                echo '<td class="num-cell">' . player_profile_link_html($row['player_name'] ?? '', $value) . '</td>';
            } else {
                echo '<td>' . h((string) $value) . '</td>';
            }
        }
        echo '<td></td>';
        echo '</tr>';
        echo '<tr class="subtable-row-js stats-subtable-row">';
        echo '<td class="subtable-offset"></td>';
        echo '<td class="subtable-container" colspan="' . h((string) count($selectedCols)) . '">';
        echo '<div class="subtable-panels stats-parallel">';
        echo '<details class="stats-animal"><summary>Animal stats (' . h((string) $animalCount) . ')</summary>';
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
        echo '<details class="stats-weapon"><summary>Weapon stats (' . h((string) $weaponCount) . ')</summary>';
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
        echo '<details class="stats-collect"><summary>Coleccionables (' . h((string) $collectCount) . ')</summary>';
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
        echo '<details class="stats-mission"><summary>Misiones diarias (' . h((string) $missionCount) . ')</summary>';
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
    $hasTypeDescriptionShort = isset($compTypeCols['description_short']);
    $attemptsExpr = $hasTypeAttempts
        ? 't.attempts'
        : "CASE WHEN COALESCE(t.raw_json->>'attempts','') ~ '^-?[0-9]+$' THEN (t.raw_json->>'attempts')::int ELSE NULL END";
    $pointTypeExpr = $hasTypePointType
        ? 't.point_type'
        : "CASE WHEN COALESCE(t.raw_json->>'point_type','') ~ '^-?[0-9]+$' THEN (t.raw_json->>'point_type')::int ELSE NULL END";
    $descriptionEsExpr = $hasTypeDescriptionEs
        ? 't.description_es'
        : "COALESCE(t.raw_json->>'description_es','')";
    $descriptionOriginalExpr = $hasTypeDescriptionShort
        ? 't.description_short'
        : "COALESCE(t.raw_json->>'description_short','')";

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

    $typeColumnDefs = [
        'competition_type_id' => 'Type ID',
        'type_name' => 'Nombre tipo',
        'description_es' => 'Descripcion ES',
        'description_short' => 'Descripcion original',
        'singleplayer' => 'Singleplayer',
        'entrant_rules' => 'Entrant rules',
        'attempts' => 'Attempts',
        'point_type' => 'Point type',
    ];
    $defaultTypeCols = ['competition_type_id', 'type_name', 'description_es', 'description_short', 'singleplayer', 'entrant_rules', 'attempts', 'point_type'];
    $selectedTypeCols = [];
    $hasTypeChoice = false;
    foreach ($typeColumnDefs as $key => $_label) {
        if (array_key_exists('ctcol_' . $key, $_GET)) {
            $hasTypeChoice = true;
            $raw = $_GET['ctcol_' . $key];
            if ((is_string($raw) && $raw === '1') || $raw === 1) {
                $selectedTypeCols[] = $key;
            }
        }
    }
    if (!$hasTypeChoice || $selectedTypeCols === []) {
        $selectedTypeCols = $defaultTypeCols;
    }
    $dragTypeOrderRaw = query_text('ctcol_order');
    if ($dragTypeOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragTypeOrderRaw))));
        $ordered = array_values(array_filter($ordered, static fn (string $k): bool => in_array($k, $selectedTypeCols, true)));
        foreach ($selectedTypeCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedTypeCols = $ordered;
        }
    }
    $selectedTypeCols = order_selected_keys($selectedTypeCols, 'ord_ctcol_');

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
    $typeNames = query_list('type_name');
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
    if ($typeNames !== []) {
        $parts = [];
        foreach ($typeNames as $idx => $typeName) {
            $ph = ':type_name_' . $idx;
            $parts[] = 't.type_name = ' . $ph;
            $params[$ph] = $typeName;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
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
    echo '<input type="hidden" name="ctcol_order" value="' . h(query_raw('ctcol_order')) . '">';
    echo '<input type="hidden" name="cscol_order" value="' . h(query_raw('cscol_order')) . '">';
    echo '<input type="hidden" name="crcol_order" value="' . h(query_raw('crcol_order')) . '">';
    echo '<input type="hidden" name="cs_sort" value="' . h($speciesSortKey) . '">';
    echo '<input type="hidden" name="cs_dir" value="' . h($speciesSortDir) . '">';
    echo '<input type="hidden" name="cr_sort" value="' . h($rewardSortKey) . '">';
    echo '<input type="hidden" name="cr_dir" value="' . h($rewardSortDir) . '">';
    echo '<input type="text" name="competition_id" placeholder="ID" value="' . h(query_raw('competition_id')) . '">';
    echo '<select name="type_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Tipo (todos)" data-check-combo-many-label="tipos">';
    echo '<option value="">Tipo (todos)</option>';
    foreach (competition_type_name_suggestions() as $name) {
        $selected = in_array($name, $typeNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="entrants" placeholder="Entrants" value="' . h(query_raw('entrants')) . '">';
    $finishedUi = query_raw('finished');
    if ($finishedUi === '' && $finished === 'false') {
        $finishedUi = 'false';
    }
    echo '<select name="finished" data-single-combo="1" data-single-combo-placeholder="Finalizada"><option value="">Finalizada</option><option value="true"' . ($finishedUi === 'true' ? ' selected' : '') . '>Si</option><option value="false"' . ($finishedUi === 'false' ? ' selected' : '') . '>No</option></select>';
    echo '<input type="datetime-local" name="start_at" title="Inicio desde" value="' . h(raw_to_datetime_local_value(query_raw('start_at'))) . '">';
    echo '<input type="datetime-local" name="end_at" title="Fin hasta" value="' . h(raw_to_datetime_local_value(query_raw('end_at'))) . '">';
    echo '<select name="species_name_es[]" multiple data-check-combo="1" data-check-combo-placeholder="Especie ES (todas)">';
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
    echo '<details class="filter-details visible-columns" data-col-prefix="ctcol_" data-order-field="ctcol_order"><summary>Columnas Tipo</summary><div class="visible-row">';
    foreach ($typeColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedTypeCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="ctcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultTypeCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="cscol_" data-order-field="cscol_order"><summary>Columnas Especies</summary><div class="visible-row">';
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

    echo '<div class="competition-results">';
    foreach ($rows as $row) {
        $tid = (int) ($row['competition_type_id'] ?? $row['__type_id'] ?? 0);
        echo '<section class="competition-entry">';
        echo '<table class="competition-entry-main" data-static-table="1"><thead><tr>';
        foreach ($selectedCols as $key) {
            echo '<th>' . sort_link($key, $columnDefs[$key]['label'], $sortKey, $sortDir) . '</th>';
        }
        echo '</tr></thead><tbody><tr>';
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
        echo '</tr></tbody></table>';
        echo '<div class="competition-entry-detail">';
        echo '<div class="competition-detail-stack">';
        $typeRow = $typeById[$tid] ?? null;
        echo '<div class="competition-detail-block competition-detail-type">';
        echo '<div class="competition-detail-title">Tipo</div>';
        if (!is_array($typeRow)) {
            echo '<span class="muted">Sin tipo vinculado (competition_type_id=' . h((string) $tid) . ')</span>';
        } else {
            $sp = ($typeRow['singleplayer'] ?? null);
            $er = ($typeRow['entrant_rules'] ?? null);
            $spTxt = ($sp === true || $sp === 't' || $sp === 1 || $sp === '1') ? 'Si' : 'No';
            $erTxt = ($er === true || $er === 't' || $er === 1 || $er === '1') ? 'Si' : 'No';
            $typeValueMap = [
                'competition_type_id' => (string) ($typeRow['competition_type_id'] ?? ''),
                'type_name' => (string) ($typeRow['type_name'] ?? ''),
                'description_es' => (string) ($typeRow['description_es'] ?? ''),
                'description_short' => (string) ($typeRow['description_short'] ?? ''),
                'singleplayer' => $spTxt,
                'entrant_rules' => $erTxt,
                'attempts' => (string) ($typeRow['attempts'] ?? ''),
                'point_type' => (string) ($typeRow['point_type'] ?? ''),
            ];
            echo '<table data-static-table="1"><colgroup>';
            foreach ($selectedTypeCols as $colKey) {
                $colClass = match ($colKey) {
                    'competition_type_id' => 'comp-type-col-id',
                    'type_name' => 'comp-type-col-name',
                    'description_es', 'description_short' => 'comp-type-col-desc',
                    'singleplayer', 'entrant_rules' => 'comp-type-col-flag',
                    'attempts', 'point_type' => 'comp-type-col-num',
                    default => 'comp-type-col-name',
                };
                echo '<col class="' . h($colClass) . '">';
            }
            echo '</colgroup><thead><tr>';
            foreach ($selectedTypeCols as $colKey) {
                echo '<th>' . h($typeColumnDefs[$colKey] ?? $colKey) . '</th>';
            }
            echo '</tr></thead><tbody>';
            echo '<tr>';
            foreach ($selectedTypeCols as $colKey) {
                $class = in_array($colKey, ['attempts', 'point_type'], true) ? ' class="num-cell"' : '';
                echo '<td' . $class . '>' . h($typeValueMap[$colKey] ?? '') . '</td>';
            }
            echo '</tr>';
            echo '</tbody></table>';
        }
        echo '</div>';
        echo '<div class="competition-detail-grid">';
        echo '<div class="competition-detail-block competition-detail-species">';
        echo '<div class="competition-detail-title">Especies (' . h((string) count($speciesByType[$tid] ?? [])) . ')</div>';
        if (($speciesByType[$tid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table data-static-table="1"><thead><tr>';
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
        echo '</div>';
        echo '<div class="competition-detail-block competition-detail-rewards">';
        echo '<div class="competition-detail-title">Rewards (' . h((string) count($rewardsByType[$tid] ?? [])) . ')</div>';
        if (($rewardsByType[$tid] ?? []) === []) {
            echo '<span class="muted">Sin filas</span>';
        } else {
            echo '<table data-static-table="1"><thead><tr>';
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
        echo '</div>';
        echo '</div>';
        echo '</div>';
        echo '</section>';
    }
    echo '</div>';
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

    $columnDefs = [
        'leaderboard_type' => ['label' => 'Tipo'],
        'species_id' => ['label' => 'IdEspecie'],
        'species_name_es' => ['label' => 'Especie'],
        'rank_pos' => ['label' => 'Rank'],
        'user_id' => ['label' => 'IdUsuario'],
        'player_name' => ['label' => 'Jugador'],
        'display_score' => ['label' => 'Puntuacion'],
        'display_distance' => ['label' => 'Distancia'],
        'snapshot_at' => ['label' => 'Snapshot'],
    ];
    $defaultCols = array_keys($columnDefs);
    $selectedCols = persistent_selected_columns('clas_cols', $columnDefs, 'clcol_', $defaultCols);
    $dragOrderRaw = query_text('clcol_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn (string $k): bool => $k !== '' && in_array($k, $selectedCols, true)));
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
            array_map(static fn (string $k): string => $columnDefs[$k]['label'], $selectedCols),
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

    echo '<section class="card"><h2>Tablas Clasificacion</h2>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="classifications">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="clcol_order" value="' . h(query_raw('clcol_order')) . '">';
    echo '<select name="leaderboard_type" onchange="this.form.submit()" data-single-combo="1" data-single-combo-placeholder="Tipo (todos)">';
    echo '<option value="">Tipo (todos)</option>';
    echo '<option value="score"' . (query_raw('leaderboard_type') === 'score' ? ' selected' : '') . '>Puntuaci&oacute;n</option>';
    echo '<option value="range"' . (query_raw('leaderboard_type') === 'range' ? ' selected' : '') . '>Distancia</option>';
    echo '</select>';
    echo '<select name="species_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Especie ES (todas)">';
    echo '<option value="">Especie ES (todas)</option>';
    foreach (species_es_name_suggestions() as $name) {
        $selected = in_array($name, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="rank_pos" placeholder="Rank" value="' . h(query_raw('rank_pos')) . '">';
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="user_id" placeholder="IdUsuario" value="' . h(query_raw('user_id')) . '">';
    echo '<input type="text" name="value_numeric" placeholder="Puntuacion" value="' . h(query_raw('value_numeric')) . '">';
    echo '<input type="text" name="distance_m" placeholder="Distancia" value="' . h(query_raw('distance_m')) . '">';
    echo '<details class="filter-details visible-columns" data-col-prefix="clcol_" data-order-field="clcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="clcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
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
    echo '<a class="btn-link" href="?view=classifications&reset=1">Limpiar</a>';
    echo '<a class="btn-link" href="?view=classifications_history&theme=' . urlencode(app_theme()) . '&font=' . urlencode(app_font()) . '">Tablas Clasificacion Hist.</a>';
    echo '</form>';

    $rows = app_query_all(
        $sql,
        $params + [
            ':_limit' => $pageSize,
            ':_offset' => $offset,
        ]
    );
    echo '<table><thead><tr>';
    foreach ($selectedCols as $col) {
        if ($col === 'species_name_es') {
            echo '<th>' . sort_link('species_name', $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
            continue;
        }
        if ($col === 'display_score') {
            echo '<th>' . sort_link('value_numeric', $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
            continue;
        }
        if ($col === 'display_distance') {
            echo '<th>' . sort_link('distance_m', $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
            continue;
        }
        echo '<th>' . sort_link($col, $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $speciesLabel = $row['species_name_es'] ?? $row['species_name'] ?? '';
        $isTop1 = ((int) ($row['rank_pos'] ?? 0) === 1);
        $rowClass = $isTop1 ? ' class="top-rank-row"' : '';
        $rankLabel = $isTop1 ? '<span class="top-rank-badge">TOP 1</span> 1' : h((string) $row['rank_pos']);
        echo '<tr' . $rowClass . '>';
        foreach ($selectedCols as $col) {
            if ($col === 'leaderboard_type') {
                echo '<td>' . h(((string) $row['leaderboard_type']) === 'score' ? 'Puntuacion' : (((string) $row['leaderboard_type']) === 'range' ? 'Distancia' : (string) $row['leaderboard_type'])) . '</td>';
            } elseif ($col === 'species_name_es') {
                echo '<td>' . h((string) $speciesLabel) . '</td>';
            } elseif ($col === 'rank_pos') {
                echo '<td class="num-cell">' . $rankLabel . '</td>';
            } elseif ($col === 'player_name') {
                echo '<td>' . player_profile_link_html($row['player_name'] ?? '', $row['player_name'] ?? '') . '</td>';
            } elseif ($col === 'user_id') {
                echo '<td class="num-cell">' . player_profile_link_html($row['player_name'] ?? '', $row['user_id'] ?? '') . '</td>';
            } else {
                echo '<td>' . h((string) ($row[$col] ?? '')) . '</td>';
            }
        }
        echo '</tr>';
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

    $columnDefs = [
        'snapshot_at' => ['label' => 'Snapshot'],
        'compare_snapshot_at' => ['label' => 'Comparado con'],
        'leaderboard_type' => ['label' => 'Tipo'],
        'species_id' => ['label' => 'IdEspecie'],
        'species_name_es' => ['label' => 'Especie'],
        'rank_pos' => ['label' => 'Rank'],
        'prev_rank' => ['label' => 'Rank Prev'],
        'rank_delta' => ['label' => 'Delta Rank'],
        'score_delta' => ['label' => 'Delta Puntuacion'],
        'distance_delta' => ['label' => 'Delta Distancia'],
        'user_id' => ['label' => 'IdUsuario'],
        'player_name' => ['label' => 'Jugador'],
        'display_score' => ['label' => 'Puntuacion'],
        'display_distance' => ['label' => 'Distancia'],
    ];
    $defaultCols = array_keys($columnDefs);
    $selectedCols = persistent_selected_columns('clas_hist_cols', $columnDefs, 'chcol_', $defaultCols);
    $dragOrderRaw = query_text('chcol_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn (string $k): bool => $k !== '' && in_array($k, $selectedCols, true)));
        foreach ($selectedCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCols = $ordered;
        }
    }

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
            array_map(static fn (string $k): string => $columnDefs[$k]['label'], $selectedCols),
            $rows,
            $selectedCols
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
    echo '<input type="hidden" name="chcol_order" value="' . h(query_raw('chcol_order')) . '">';
    echo '<select name="snapshot_at" data-single-combo="1" data-single-combo-placeholder="Snapshot actual">';
    foreach ($snapshots as $snapshot) {
        echo '<option value="' . h($snapshot) . '"' . ($snapshot === $currentSnapshot ? ' selected' : '') . '>' . h($snapshot) . '</option>';
    }
    echo '</select>';
    echo '<select name="compare_snapshot_at" data-single-combo="1" data-single-combo-placeholder="Comparar con"><option value="">Comparar con...</option>';
    foreach ($snapshots as $snapshot) {
        echo '<option value="' . h($snapshot) . '"' . ($snapshot === $compareSnapshot ? ' selected' : '') . '>' . h($snapshot) . '</option>';
    }
    echo '</select>';
    echo '<select name="leaderboard_type" data-single-combo="1" data-single-combo-placeholder="Tipo (todos)"><option value="">Tipo (todos)</option><option value="score"' . (query_raw('leaderboard_type') === 'score' ? ' selected' : '') . '>Puntuaci&oacute;n</option><option value="range"' . (query_raw('leaderboard_type') === 'range' ? ' selected' : '') . '>Distancia</option></select>';
    echo '<input type="text" name="rank_pos" placeholder="Rank actual" value="' . h(query_raw('rank_pos')) . '">';
    echo '<select name="species_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Especie (todas)"><option value="">Especie (todas)</option>';
    foreach (species_es_name_suggestions() as $name) {
        $selected = in_array($name, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores"><option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="user_id" placeholder="IdUsuario" value="' . h(query_raw('user_id')) . '">';
    echo '<label class="inline-check"><input type="checkbox" name="only_changed" value="1"' . ($onlyChanged ? ' checked' : '') . '> Solo cambios</label>';
    echo '<details class="filter-details visible-columns" data-col-prefix="chcol_" data-order-field="chcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="chcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size"><option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option><option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option><option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option><option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option></select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=classifications_history&reset=1">Limpiar</a>';
    echo '</form>';

    $rows = app_query_all($sql . ' LIMIT :_limit OFFSET :_offset', $params + [':_limit' => $pageSize, ':_offset' => $offset]);

    echo '<table><thead><tr>';
    foreach ($selectedCols as $col) {
        if ($col === 'species_name_es') {
            echo '<th>' . sort_link('species_name', $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
            continue;
        }
        echo '<th>' . sort_link($col, $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
    }
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
        echo '<tr' . $rowClass . '>';
        foreach ($selectedCols as $col) {
            if ($col === 'leaderboard_type') {
                echo '<td>' . h(((string) $row['leaderboard_type']) === 'score' ? 'Puntuacion' : (((string) $row['leaderboard_type']) === 'range' ? 'Distancia' : (string) $row['leaderboard_type'])) . '</td>';
            } elseif ($col === 'species_name_es') {
                echo '<td>' . h((string) $speciesLabel) . '</td>';
            } elseif ($col === 'rank_pos') {
                echo '<td class="num-cell">' . $rankLabel . '</td>';
            } elseif ($col === 'score_delta') {
                echo '<td class="' . h($deltaClass($scoreDelta)) . '">' . h((string) ($scoreDelta ?? '')) . '</td>';
            } elseif ($col === 'distance_delta') {
                echo '<td class="' . h($deltaClass($distanceDelta)) . '">' . h((string) ($distanceDelta ?? '')) . '</td>';
            } elseif ($col === 'rank_delta') {
                echo '<td class="' . h($deltaClass($rankDelta)) . '">' . h((string) ($rankDelta ?? '')) . '</td>';
            } elseif ($col === 'display_score') {
                echo '<td class="' . h($scoreCellClass) . '">' . ($markChanged && ((string) ($row['leaderboard_type'] ?? '') === 'score') ? '<span class="mark-changed-badge">CAMBIO</span> ' : '') . h((string) ($row['display_score'] ?? '')) . '</td>';
            } elseif ($col === 'display_distance') {
                echo '<td class="' . h($distanceCellClass) . '">' . ($markChanged && ((string) ($row['leaderboard_type'] ?? '') === 'range') ? '<span class="mark-changed-badge">CAMBIO</span> ' : '') . h((string) ($row['display_distance'] ?? '')) . '</td>';
            } elseif ($col === 'player_name') {
                echo '<td>' . player_profile_link_html($row['player_name'] ?? '', $row['player_name'] ?? '') . '</td>';
            } elseif ($col === 'user_id') {
                echo '<td class="num-cell">' . player_profile_link_html($row['player_name'] ?? '', $row['user_id'] ?? '') . '</td>';
            } elseif (in_array($col, ['species_id', 'prev_rank', 'user_id'], true)) {
                echo '<td class="num-cell">' . h((string) ($row[$col] ?? '')) . '</td>';
            } else {
                echo '<td>' . h((string) ($row[$col] ?? '')) . '</td>';
            }
        }
        echo '</tr>';
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

    $columnDefs = [
        'risk_score' => ['label' => 'Riesgo'],
        'risk_level' => ['label' => 'Nivel'],
        'player_name' => ['label' => 'Jugador'],
        'user_id' => ['label' => 'IdUsuario'],
        'total_kills' => ['label' => 'Kills'],
        'signal_count' => ['label' => 'Senales'],
        'kills_outside_window' => ['label' => 'Muertes fuera ventana'],
        'max_hit_distance_m' => ['label' => 'Dist max (m)'],
        'max_kills_per_hour' => ['label' => 'Kills/h max'],
        'min_gap_sec' => ['label' => 'Gap min (s)'],
        'integrity_ratio_pct' => ['label' => 'Integridad %'],
    ];
    $defaultCols = array_keys($columnDefs);
    $selectedCols = persistent_selected_columns('cheat_risk_cols', $columnDefs, 'crcol_', $defaultCols);
    $dragOrderRaw = query_text('crcol_order');
    if ($dragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn (string $k): bool => $k !== '' && in_array($k, $selectedCols, true)));
        foreach ($selectedCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedCols = $ordered;
        }
    }

    $signalColumnDefs = [
        'signal_label' => ['label' => 'Senales'],
        'signal_value' => ['label' => 'Valor'],
        'signal_threshold' => ['label' => 'Umbral'],
        'signal_weight' => ['label' => 'Peso'],
    ];
    $signalDefaultCols = array_keys($signalColumnDefs);
    $selectedSignalCols = persistent_selected_columns('cheat_signal_cols', $signalColumnDefs, 'crscol_', $signalDefaultCols);
    $signalDragOrderRaw = query_text('crscol_order');
    if ($signalDragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $signalDragOrderRaw)), static fn (string $k): bool => $k !== '' && in_array($k, $selectedSignalCols, true)));
        foreach ($selectedSignalCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedSignalCols = $ordered;
        }
    }

    $signalExpColumnDefs = [
        'signal_label' => ['label' => 'Senales'],
        'expedition_id' => ['label' => 'Expedicion'],
        'signal_value' => ['label' => 'Valor'],
        'signal_threshold' => ['label' => 'Umbral'],
        'signal_weight' => ['label' => 'Peso'],
    ];
    $signalExpDefaultCols = array_keys($signalExpColumnDefs);
    $selectedSignalExpCols = persistent_selected_columns('cheat_signal_exp_cols', $signalExpColumnDefs, 'crecol_', $signalExpDefaultCols);
    $signalExpDragOrderRaw = query_text('crecol_order');
    if ($signalExpDragOrderRaw !== null) {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $signalExpDragOrderRaw)), static fn (string $k): bool => $k !== '' && in_array($k, $selectedSignalExpCols, true)));
        foreach ($selectedSignalExpCols as $k) {
            if (!in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }
        if ($ordered !== []) {
            $selectedSignalExpCols = $ordered;
        }
    }

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
    echo '<input type="hidden" name="crcol_order" value="' . h(query_raw('crcol_order')) . '">';
    echo '<input type="hidden" name="crscol_order" value="' . h(query_raw('crscol_order')) . '">';
    echo '<input type="hidden" name="crecol_order" value="' . h(query_raw('crecol_order')) . '">';
    echo '<input type="text" name="user_id" placeholder="IdUsuario" value="' . h(query_raw('user_id')) . '">';
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<select name="risk_level" data-single-combo="1" data-single-combo-placeholder="Nivel (todos)"><option value="">Nivel (todos)</option>';
    echo '<option value="alto"' . ($riskLevel === 'alto' ? ' selected' : '') . '>Alto</option>';
    echo '<option value="medio"' . ($riskLevel === 'medio' ? ' selected' : '') . '>Medio</option>';
    echo '<option value="bajo"' . ($riskLevel === 'bajo' ? ' selected' : '') . '>Bajo</option>';
    echo '</select>';
    echo '<input type="text" name="min_score" placeholder="Riesgo min" value="' . h(query_raw('min_score')) . '">';
    echo '<input type="text" name="min_kills" placeholder="Kills min" value="' . h(query_raw('min_kills')) . '">';
    echo '<label class="inline-check"><input type="checkbox" name="signals_only" value="1"' . ($signalsOnly ? ' checked' : '') . '>Solo con senales</label>';
    echo '<details class="filter-details visible-columns" data-col-prefix="crcol_" data-order-field="crcol_order"><summary>Columnas Riesgo</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="crcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="crscol_" data-order-field="crscol_order"><summary>Columnas Senales</summary><div class="visible-row">';
    foreach ($signalColumnDefs as $key => $def) {
        $checked = in_array($key, $selectedSignalCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="crscol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $signalDefaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="crecol_" data-order-field="crecol_order"><summary>Columnas Expediciones Senales</summary><div class="visible-row">';
    foreach ($signalExpColumnDefs as $key => $def) {
        $checked = in_array($key, $selectedSignalExpCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="crecol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $signalExpDefaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
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
    foreach ($selectedCols as $col) {
        echo '<th>' . sort_link($col, $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '<th>Detalle</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $level = strtolower((string) ($row['risk_level'] ?? 'bajo'));
        $query = $_GET;
        $query['detail_user_id'] = $row['user_id'];
        $query['view'] = 'cheat_risk';
        echo '<tr>';
        foreach ($selectedCols as $col) {
            if ($col === 'risk_level') {
                echo '<td><span class="risk-pill risk-' . h($level) . '">' . h($level) . '</span></td>';
            } elseif ($col === 'player_name') {
                echo '<td>' . player_profile_link_html($row['player_name'] ?? '', $row['player_name'] ?? '') . '</td>';
            } elseif ($col === 'user_id') {
                echo '<td class="num-cell">' . player_profile_link_html($row['player_name'] ?? '', $row['user_id'] ?? '') . '</td>';
            } elseif (in_array($col, ['risk_score', 'user_id', 'total_kills', 'signal_count', 'kills_outside_window', 'max_hit_distance_m', 'max_kills_per_hour', 'min_gap_sec', 'integrity_ratio_pct'], true)) {
                echo '<td class="num-cell">' . h((string) ($row[$col] ?? '')) . '</td>';
            } else {
                echo '<td>' . h((string) ($row[$col] ?? '')) . '</td>';
            }
        }
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
            echo '<table><thead><tr>';
            foreach ($selectedSignalCols as $col) {
                echo '<th>' . h($signalColumnDefs[$col]['label']) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($detailRows as $drow) {
                echo '<tr>';
                foreach ($selectedSignalCols as $col) {
                    $cellClass = in_array($col, ['signal_value', 'signal_weight'], true) ? ' class="num-cell"' : '';
                    echo '<td' . $cellClass . '>' . h((string) ($drow[$col] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '<h3>Expediciones por indicio</h3>';
        if ($detailExpRows === []) {
            echo '<p class="muted">No hay expediciones marcadas para este jugador.</p>';
        } else {
            echo '<table><thead><tr>';
            foreach ($selectedSignalExpCols as $col) {
                echo '<th>' . h($signalExpColumnDefs[$col]['label']) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($detailExpRows as $drow) {
                echo '<tr>';
                foreach ($selectedSignalExpCols as $col) {
                    $cellClass = in_array($col, ['expedition_id', 'signal_value', 'signal_weight'], true) ? ' class="num-cell"' : '';
                    echo '<td' . $cellClass . '>' . h((string) ($drow[$col] ?? '')) . '</td>';
                }
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

    $killUrlByMetricPlayerSpecies = [
        'score' => [],
        'distance' => [],
    ];
    $xmlPlayerNames = [];
    for ($i = $playerStartCol; $i <= $maxCol; $i++) {
        $playerName = trim((string) ($headerRow[$i] ?? ''));
        if ($playerName !== '') {
            $xmlPlayerNames[$playerName] = true;
        }
    }
    $xmlSpeciesNames = [];
    foreach ($dataRows as $rowData) {
        $speciesName = trim((string) ($rowData[$speciesCol] ?? ''));
        if ($speciesName !== '') {
            $xmlSpeciesNames[$speciesName] = true;
        }
    }
    if ($xmlPlayerNames !== [] && $xmlSpeciesNames !== []) {
        $playerPlaceholders = [];
        $speciesPlaceholders = [];
        $urlParams = [];
        $paramIdx = 0;
        foreach (array_keys($xmlPlayerNames) as $playerName) {
            $ph = ':xml_player_' . $paramIdx++;
            $playerPlaceholders[] = $ph;
            $urlParams[$ph] = $playerName;
        }
        $paramIdx = 0;
        foreach (array_keys($xmlSpeciesNames) as $speciesName) {
            $ph = ':xml_species_' . $paramIdx++;
            $speciesPlaceholders[] = $ph;
            $urlParams[$ph] = $speciesName;
        }
        try {
            $bestUrlRows = app_query_all(
                'SELECT player_name, species_name_es, best_score_animal_id, best_distance_animal_id
                   FROM gpt.best_personal_records
                  WHERE player_name IN (' . implode(', ', $playerPlaceholders) . ')
                    AND species_name_es IN (' . implode(', ', $speciesPlaceholders) . ')',
                $urlParams
            );
            foreach ($bestUrlRows as $bestUrlRow) {
                $playerName = trim((string) ($bestUrlRow['player_name'] ?? ''));
                $speciesName = trim((string) ($bestUrlRow['species_name_es'] ?? ''));
                if ($playerName === '' || $speciesName === '') {
                    continue;
                }
                $mapKey = $playerName . '|' . $speciesName;
                $scoreUrl = thehunter_kill_url($playerName, $bestUrlRow['best_score_animal_id'] ?? null);
                $distanceUrl = thehunter_kill_url($playerName, $bestUrlRow['best_distance_animal_id'] ?? null);
                if ($scoreUrl !== null) {
                    $killUrlByMetricPlayerSpecies['score'][$mapKey] = $scoreUrl;
                }
                if ($distanceUrl !== null) {
                    $killUrlByMetricPlayerSpecies['distance'][$mapKey] = $distanceUrl;
                }
            }
        } catch (Throwable) {
            $killUrlByMetricPlayerSpecies = [
                'score' => [],
                'distance' => [],
            ];
        }
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
                $speciesIcons = species_single_icon_html($v);
                echo '<td class="best-xml-species" data-raw="' . h($v) . '"><span class="best-species-tag">[' . h($topBadge === 'TOP D' ? 'D' : 'P') . ']</span> ' . $speciesIcons . h($v) . '</td>';
                continue;
            }
            if (in_array($i, $bestCols, true) && $v !== '') {
                $playerName = trim((string) ($headerRow[$i] ?? ''));
                $cls = $topBadge === 'TOP D' ? 'best-species-distance' : 'best-species-score';
                $numRaw = $toFloat($v);
                $killUrlKey = $playerName . '|' . $species;
                $killUrl = $killUrlByMetricPlayerSpecies[$metricType][$killUrlKey] ?? null;
                $cellHtml = '<span class="best-species-badge">' . h($topBadge) . '</span> ' . h($v);
                if (is_string($killUrl) && $killUrl !== '') {
                    $cellHtml = '<a class="record-link record-link-kill" href="' . h($killUrl) . '" target="_blank" rel="noopener noreferrer">' . $cellHtml . '</a>';
                }
                echo '<td class="best-xml-player ' . $cls . '" data-player-name="' . h($playerName) . '" data-raw="' . h($v) . '" data-num="' . h($numRaw !== null ? (string) $numRaw : '') . '">' . $cellHtml . '</td>';
                continue;
            }
            $playerName = trim((string) ($headerRow[$i] ?? ''));
            $numRaw = $toFloat($v);
            $killUrlKey = $playerName . '|' . $species;
            $killUrl = $killUrlByMetricPlayerSpecies[$metricType][$killUrlKey] ?? null;
            $cellHtml = h($v);
            if ($v !== '' && is_string($killUrl) && $killUrl !== '') {
                $cellHtml = '<a class="record-link record-link-kill" href="' . h($killUrl) . '" target="_blank" rel="noopener noreferrer">' . $cellHtml . '</a>';
            }
            echo '<td class="best-xml-player" data-player-name="' . h($playerName) . '" data-raw="' . h($v) . '" data-num="' . h($numRaw !== null ? (string) $numRaw : '') . '">' . $cellHtml . '</td>';
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
                . '<td>' . player_profile_link_html((string) $playerName, (string) $playerName) . '</td>'
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
    echo '<select name="log" onchange="this.form.submit()" data-single-combo="1" data-single-combo-placeholder="Archivo log">';
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

    $speciesCol = null;
    foreach (['ion', 'especie', 'species', 'animal', 'nombre_especie'] as $cand) {
        if (isset($columnsByLower[$cand])) {
            $speciesCol = $columnsByLower[$cand];
            break;
        }
    }
    $speciesNames = query_list('species_name');

    $where = [];
    $params = [];
    foreach ($columns as $idx => $col) {
        if ($speciesCol !== null && $col === $speciesCol) {
            continue;
        }
        $value = query_text('hf_' . $col);
        if ($value === null) {
            continue;
        }
        $ph = ':hf_' . $idx;
        $where[] = "COALESCE(" . quote_ident($col) . "::text, '') ILIKE " . $ph;
        $params[$ph] = '%' . $value . '%';
    }
    if ($speciesCol !== null && $speciesNames !== []) {
        $parts = [];
        foreach ($speciesNames as $idx => $name) {
            $ph = ':hf_species_name_' . $idx;
            $parts[] = "COALESCE(" . quote_ident($speciesCol) . "::text, '') = " . $ph;
            $params[$ph] = $name;
        }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }

    $safeCols = implode(', ', array_map(static fn(string $c): string => quote_ident($c), $columns));
    $sql = 'SELECT ' . $safeCols . ' FROM ' . quote_ident('gpt') . '.' . quote_ident($table);
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' LIMIT 1000';
    $rows = app_query_all($sql, $params);

    $speciesOptions = [];
    if ($speciesCol !== null) {
        $speciesSql = 'SELECT DISTINCT COALESCE(' . quote_ident($speciesCol) . "::text, '') AS species_name
                       FROM " . quote_ident('gpt') . '.' . quote_ident($table) . "
                       WHERE COALESCE(" . quote_ident($speciesCol) . "::text, '') <> ''
                       ORDER BY 1";
        foreach (app_query_all($speciesSql) as $srow) {
            $name = trim((string) ($srow['species_name'] ?? ''));
            if ($name !== '') {
                $speciesOptions[] = $name;
            }
        }
    }

    echo '<p class="muted">Tabla: gpt.' . h($table) . '</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="hall_of_fame">';
    foreach ($displayColumns as $col) {
        if ($speciesCol !== null && $col === $speciesCol) {
            continue;
        }
        $label = $labelForCol($col);
        echo '<input type="text" name="hf_' . h($col) . '" placeholder="' . h($label) . '" value="' . h(query_raw('hf_' . $col)) . '">';
    }
    if ($speciesCol !== null) {
        echo '<select name="species_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Especie (todas)">';
        echo '<option value="">Especie (todas)</option>';
        foreach ($speciesOptions as $name) {
            $selected = in_array($name, $speciesNames, true) ? ' selected' : '';
            echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
        }
        echo '</select>';
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
    $playerCol = null;
    foreach (['player_name', 'jugador', 'usuario', 'user_name', 'player'] as $cand) {
        if (isset($columnsByLower[$cand])) {
            $playerCol = $columnsByLower[$cand];
            break;
        }
    }
    $userIdCol = null;
    foreach (['user_id', 'idusuario', 'usuario_id'] as $cand) {
        if (isset($columnsByLower[$cand])) {
            $userIdCol = $columnsByLower[$cand];
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
            $rowPlayerName = $playerCol !== null ? trim((string) ($row[$playerCol] ?? '')) : '';
            if ($isTop && $c === ($speciesCol ?? '')) {
                $cell = '<span class="top-rank-badge">TOP</span> ' . $cell;
            }
            if ($playerCol !== null && $c === $playerCol) {
                $cell = player_profile_link_html($rowPlayerName, $v);
            } elseif ($userIdCol !== null && $c === $userIdCol) {
                $cell = player_profile_link_html($rowPlayerName, $v);
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

function render_trophies_summary(): void
{
    $table = 'v_user_trophies_summary';
    $columns = gpt_table_columns($table);
    $columnNames = array_keys($columns);

    echo '<section class="card"><h2>Resumen Trofeos</h2>';
    if ($columnNames === []) {
        echo '<p class="muted">La vista gpt.' . h($table) . ' no tiene columnas visibles.</p></section>';
        return;
    }

    $columnLabels = [
        'user_id' => 'IdUsuario',
        'player_name' => 'Jugador',
        'gold_count' => 'Gold',
        'silver_count' => 'Silver',
        'bronze_count' => 'Bronze',
        'total_trophies' => 'Total Trofeos',
    ];
    $labelForCol = static function (string $col) use ($columnLabels): string {
        return $columnLabels[strtolower($col)] ?? $col;
    };

    $columnDefs = [];
    foreach ($columnNames as $col) {
        $columnDefs[$col] = ['label' => $labelForCol($col)];
    }

    $defaultCols = ['player_name', 'gold_count', 'silver_count', 'bronze_count', 'total_trophies'];
    $selectedCols = persistent_selected_columns('trophies_summary_visible_cols', $columnDefs, 'tscol_', $defaultCols);
    $dragOrderRaw = query_text('tscol_order');
    if ($dragOrderRaw !== null && trim($dragOrderRaw) !== '') {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn(string $k): bool => $k !== '' && isset($columnDefs[$k])));
        if ($ordered !== []) {
            $rest = array_values(array_filter($selectedCols, static fn(string $k): bool => !in_array($k, $ordered, true)));
            $selectedCols = array_merge($ordered, $rest);
            $_SESSION['trophies_summary_visible_cols'] = $selectedCols;
        }
    }

    $detailColumnDefs = [
        'trophy_entry_id' => ['label' => 'IdTrofeo'],
        'trophy_name' => ['label' => 'Trofeo'],
        'competition_id' => ['label' => 'IdCompeticion'],
        'competition_name' => ['label' => 'Competicion'],
        'trophy_at' => ['label' => 'Fecha'],
    ];
    $detailDefaultCols = ['trophy_name', 'competition_name', 'trophy_at'];
    $detailSelectedCols = persistent_selected_columns(
        'trophies_summary_detail_visible_cols',
        $detailColumnDefs,
        'tsdcol_',
        $detailDefaultCols
    );
    $detailDragOrderRaw = query_text('tsdcol_order');
    if ($detailDragOrderRaw !== null && trim($detailDragOrderRaw) !== '') {
        $ordered = array_values(array_filter(
            array_map('trim', explode(',', $detailDragOrderRaw)),
            static fn(string $k): bool => $k !== '' && isset($detailColumnDefs[$k])
        ));
        if ($ordered !== []) {
            $rest = array_values(array_filter(
                $detailSelectedCols,
                static fn(string $k): bool => !in_array($k, $ordered, true)
            ));
            $detailSelectedCols = array_merge($ordered, $rest);
            $_SESSION['trophies_summary_detail_visible_cols'] = $detailSelectedCols;
        }
    }

    $playerNames = query_list('player_name');
    $playerOptionRows = app_query_all(
        'SELECT DISTINCT player_name
           FROM ' . quote_ident('gpt') . '.' . quote_ident($table) . '
          WHERE COALESCE(player_name, \'\') <> \'\'
          ORDER BY player_name ASC'
    );
    $playerOptions = [];
    foreach ($playerOptionRows as $row) {
        $name = trim((string) ($row['player_name'] ?? ''));
        if ($name !== '') {
            $playerOptions[] = $name;
        }
    }

    $where = [];
    $params = [];
    $mainFilterCols = ['user_id', 'total_trophies'];
    foreach ($mainFilterCols as $idx => $col) {
        $value = query_text('ts_' . $col);
        if ($value === null) {
            continue;
        }
        $ph = ':ts_' . $idx;
        $where[] = "COALESCE(" . quote_ident($col) . "::text, '') ILIKE " . $ph;
        $params[$ph] = '%' . $value . '%';
    }
    if ($playerNames !== []) {
        $playerPlaceholders = [];
        foreach (array_values($playerNames) as $idx => $name) {
            $ph = ':ts_player_' . $idx;
            $playerPlaceholders[] = $ph;
            $params[$ph] = $name;
        }
        $where[] = 'player_name IN (' . implode(', ', $playerPlaceholders) . ')';
    }

    $page = query_page();
    $pageSize = query_page_size(100);

    $sortable = [];
    foreach ($columnNames as $col) {
        $sortable[$col] = quote_ident($col);
    }
    [$sortKey, $sortDir] = query_sort('total_trophies', 'desc', $sortable);

    $selectCols = implode(', ', array_map(static fn(string $c): string => quote_ident($c), $columnNames));
    $tableSql = quote_ident('gpt') . '.' . quote_ident($table);

    $sql = 'SELECT ' . $selectCols . ' FROM ' . $tableSql;
    $countSql = 'SELECT COUNT(*) AS c FROM ' . $tableSql;
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
        $countSql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', player_name ASC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $headers = array_map(static fn(string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('resumen_trofeos.csv', $headers, $rows, $selectedCols);
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

    $detailFilters = [];
    foreach (array_keys($detailColumnDefs) as $col) {
        $value = query_text('tsd_' . $col);
        if ($value !== null) {
            $detailFilters[$col] = $value;
        }
    }

    $detailByUserType = [];
    $userIds = [];
    foreach ($rows as $row) {
        $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        if ($userId > 0) {
            $userIds[] = $userId;
        }
    }
    $userIds = array_values(array_unique($userIds));
    if ($userIds !== []) {
        $placeholders = [];
        $detailParams = [];
        foreach ($userIds as $idx => $userId) {
            $ph = ':ts_user_' . $idx;
            $placeholders[] = $ph;
            $detailParams[$ph] = $userId;
        }
        $detailSql = 'SELECT trophy_entry_id, user_id, trophy_name, competition_id, competition_name, trophy_at
                      FROM gpt.user_trophies
                      WHERE user_id IN (' . implode(', ', $placeholders) . ')
                        AND (
                            LOWER(COALESCE(trophy_name, \'\')) LIKE \'%gold%\'
                            OR LOWER(COALESCE(trophy_name, \'\')) LIKE \'%silver%\'
                            OR LOWER(COALESCE(trophy_name, \'\')) LIKE \'%bronze%\'
                        )
                       ORDER BY trophy_at DESC NULLS LAST, trophy_entry_id DESC';
        foreach (app_query_all($detailSql, $detailParams) as $drow) {
            $name = strtolower(trim((string) ($drow['trophy_name'] ?? '')));
            $medalType = null;
            if (str_contains($name, 'gold')) {
                $medalType = 'gold';
            } elseif (str_contains($name, 'silver')) {
                $medalType = 'silver';
            } elseif (str_contains($name, 'bronze')) {
                $medalType = 'bronze';
            }
            $userId = isset($drow['user_id']) ? (int) $drow['user_id'] : 0;
            if ($medalType === null || $userId <= 0) {
                continue;
            }

            $matchesDetailFilters = true;
            foreach ($detailFilters as $filterCol => $filterValue) {
                $candidate = '';
                if ($filterCol === 'trophy_at') {
                    $candidate = format_datetime_display($drow['trophy_at'] ?? null);
                } else {
                    $candidate = (string) ($drow[$filterCol] ?? '');
                }
                if (stripos($candidate, $filterValue) === false) {
                    $matchesDetailFilters = false;
                    break;
                }
            }

            if ($matchesDetailFilters) {
                $detailByUserType[$userId][$medalType][] = $drow;
            }
        }
    }

    echo '<p class="muted">Vista: gpt.' . h($table) . '</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="trophies_summary">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="tscol_order" value="' . h(query_raw('tscol_order')) . '">';
    echo '<input type="hidden" name="tsdcol_order" value="' . h(query_raw('tsdcol_order')) . '">';
    echo '<input type="text" name="ts_user_id" placeholder="IdUsuario" value="' . h(query_raw('ts_user_id')) . '">';
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores">';
    echo '<option value="">Jugador (todos)</option>';
    foreach ($playerOptions as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="ts_total_trophies" placeholder="Total Trofeos" value="' . h(query_raw('ts_total_trophies')) . '">';
    echo '<details class="filter-details visible-columns" data-col-prefix="tscol_" data-order-field="tscol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="tscol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<details class="filter-details"><summary>Filtros Detalle</summary><div class="cols-grid">';
    foreach ($detailColumnDefs as $key => $def) {
        echo '<input type="text" name="tsd_' . h($key) . '" placeholder="' . h($def['label']) . '" value="' . h(query_raw('tsd_' . $key)) . '">';
    }
    echo '</div></details>';
    echo '<details class="filter-details visible-columns" data-col-prefix="tsdcol_" data-order-field="tsdcol_order"><summary>Columnas Detalle</summary><div class="visible-row">';
    foreach ($detailColumnDefs as $key => $def) {
        $checked = in_array($key, $detailSelectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="tsdcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $detailDefaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '<option value="500"' . ($pageSize === 500 ? ' selected' : '') . '>500 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export_csv" value="1">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=trophies_summary&reset=1">Limpiar</a>';
    echo '</form>';

    echo '<table><thead><tr>';
    foreach ($selectedCols as $col) {
        echo '<th>' . sort_link($col, $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $userId = isset($row['user_id']) ? (int) $row['user_id'] : 0;
        echo '<tr>';
        foreach ($selectedCols as $col) {
            $cell = $row[$col] ?? null;
            if ($userId > 0 && in_array($col, ['gold_count', 'silver_count', 'bronze_count'], true)) {
                $count = (int) ($cell ?? 0);
                $medalType = substr($col, 0, -6);
                if ($count > 0) {
                    echo '<td><button type="button" class="trophy-count-toggle" data-user-id="' . h((string) $userId) . '" data-medal-type="' . h($medalType) . '">' . h((string) $count) . '</button></td>';
                } else {
                    echo '<td>0</td>';
                }
                continue;
            }
            if ($col === 'player_name') {
                echo '<td>' . player_profile_link_html($row['player_name'] ?? '', $cell) . '</td>';
            } elseif ($col === 'user_id') {
                echo '<td class="num-cell">' . player_profile_link_html($row['player_name'] ?? '', $cell) . '</td>';
            } else {
                echo '<td>' . h($cell === null ? '' : (string) $cell) . '</td>';
            }
        }
        echo '</tr>';
        echo '<tr class="trophy-summary-detail-row" data-user-id="' . h((string) $userId) . '" style="display:none;">';
        echo '<td colspan="' . h((string) max(1, count($selectedCols))) . '">';
        echo '<div class="trophy-summary-detail-wrap">';
        foreach (['gold' => 'Gold', 'silver' => 'Silver', 'bronze' => 'Bronze'] as $medalType => $medalLabel) {
            $detailRows = $detailByUserType[$userId][$medalType] ?? [];
            echo '<div class="trophy-summary-panel" data-medal-type="' . h($medalType) . '" style="display:none;">';
            echo '<div class="trophy-summary-panel-title">' . h($medalLabel) . ' - ' . h((string) count($detailRows)) . '</div>';
            if ($detailRows === []) {
                echo '<span class="muted">Sin trofeos</span>';
            } else {
                echo '<table class="trophy-summary-detail-table"><thead><tr>';
                foreach ($detailSelectedCols as $detailCol) {
                    echo '<th>' . h($detailColumnDefs[$detailCol]['label']) . '</th>';
                }
                echo '</tr></thead><tbody>';
                foreach ($detailRows as $drow) {
                    $competitionId = isset($drow['competition_id']) ? (int) $drow['competition_id'] : 0;
                    $competitionName = trim((string) ($drow['competition_name'] ?? ''));
                    $competitionCell = h($competitionName);
                    if ($competitionId > 0 && $competitionName !== '') {
                        $competitionUrl = 'https://www.thehunter.com/#competitions/details/' . rawurlencode((string) $competitionId);
                        $competitionCell = '<a class="record-link" href="' . h($competitionUrl) . '" target="_blank" rel="noopener noreferrer">' . h($competitionName) . '</a>';
                    }
                    echo '<tr>';
                    foreach ($detailSelectedCols as $detailCol) {
                        if ($detailCol === 'competition_name') {
                            echo '<td>' . $competitionCell . '</td>';
                            continue;
                        }
                        if ($detailCol === 'trophy_at') {
                            echo '<td>' . h(format_datetime_display($drow['trophy_at'] ?? null)) . '</td>';
                            continue;
                        }
                        echo '<td>' . h((string) ($drow[$detailCol] ?? '')) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function gallery_type_label(mixed $value): string
{
    $type = is_numeric((string) $value) ? (int) $value : null;
    return match ($type) {
        1 => 'Tipo 1',
        2 => 'Tipo 2',
        default => $type === null ? '' : ('Tipo ' . $type),
    };
}

function render_user_gallery(): void
{
    echo '<section class="card"><h2>Galerias Usuarios</h2>';

    $columnDefs = [
        'gallery_entry_id' => ['label' => 'IdGaleria'],
        'user_id' => ['label' => 'IdUsuario'],
        'player_name' => ['label' => 'Jugador'],
        'thumbnail' => ['label' => 'Miniatura'],
        'label' => ['label' => 'Etiqueta'],
        'photo_type' => ['label' => 'Tipo'],
        'species_name_es' => ['label' => 'Especie'],
        'animal_id' => ['label' => 'IdMuerte'],
        'score_type' => ['label' => 'Tipo Score'],
        'score_value' => ['label' => 'Score'],
        'photo_url' => ['label' => 'URL Imagen'],
        'thumbnail_url' => ['label' => 'URL Miniatura'],
        'updated_at' => ['label' => 'Actualizado'],
    ];
    $defaultCols = ['player_name', 'thumbnail', 'species_name_es', 'animal_id', 'label', 'photo_type', 'score_value', 'updated_at'];
    $selectedCols = persistent_selected_columns('gallery_visible_cols', $columnDefs, 'gcol_', $defaultCols);
    $dragOrderRaw = query_text('gcol_order');
    if ($dragOrderRaw !== null && trim($dragOrderRaw) !== '') {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn(string $k): bool => $k !== '' && isset($columnDefs[$k])));
        if ($ordered !== []) {
            $rest = array_values(array_filter($selectedCols, static fn(string $k): bool => !in_array($k, $ordered, true)));
            $selectedCols = array_merge($ordered, $rest);
            $_SESSION['gallery_visible_cols'] = $selectedCols;
        }
    }

    $playerNames = query_list('gallery_player_name');
    $speciesNames = query_list('gallery_species_name_es');
    $photoTypes = query_list('gallery_photo_type');

    $playerOptionRows = app_query_all(
        'SELECT DISTINCT player_name
           FROM gpt.v_user_gallery
          WHERE COALESCE(player_name, \'\') <> \'\'
          ORDER BY player_name ASC'
    );
    $playerOptions = [];
    foreach ($playerOptionRows as $row) {
        $name = trim((string) ($row['player_name'] ?? ''));
        if ($name !== '') {
            $playerOptions[] = $name;
        }
    }

    $speciesOptionRows = app_query_all(
        'SELECT DISTINCT species_name_es
           FROM gpt.v_user_gallery
          WHERE COALESCE(species_name_es, \'\') <> \'\'
          ORDER BY species_name_es ASC'
    );
    $speciesOptions = [];
    foreach ($speciesOptionRows as $row) {
        $name = trim((string) ($row['species_name_es'] ?? ''));
        if ($name !== '') {
            $speciesOptions[] = $name;
        }
    }

    $typeOptionRows = app_query_all(
        'SELECT DISTINCT photo_type
           FROM gpt.v_user_gallery
          WHERE photo_type IS NOT NULL
          ORDER BY photo_type ASC'
    );
    $typeOptions = [];
    foreach ($typeOptionRows as $row) {
        $value = (string) ($row['photo_type'] ?? '');
        if ($value !== '') {
            $typeOptions[] = $value;
        }
    }

    $where = [];
    $params = [];

    foreach (['gallery_entry_id', 'user_id', 'animal_id', 'label', 'score_value'] as $idx => $col) {
        $value = query_text('gallery_' . $col);
        if ($value === null) {
            continue;
        }
        $ph = ':gallery_filter_' . $idx;
        $where[] = 'COALESCE(' . quote_ident($col) . '::text, \'\') ILIKE ' . $ph;
        $params[$ph] = '%' . $value . '%';
    }

    if ($playerNames !== []) {
        $placeholders = [];
        foreach (array_values($playerNames) as $idx => $value) {
            $ph = ':gallery_player_' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $value;
        }
        $where[] = 'player_name IN (' . implode(', ', $placeholders) . ')';
    }

    if ($speciesNames !== []) {
        $placeholders = [];
        foreach (array_values($speciesNames) as $idx => $value) {
            $ph = ':gallery_species_' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $value;
        }
        $where[] = 'species_name_es IN (' . implode(', ', $placeholders) . ')';
    }

    if ($photoTypes !== []) {
        $placeholders = [];
        foreach (array_values($photoTypes) as $idx => $value) {
            $ph = ':gallery_type_' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = (int) $value;
        }
        $where[] = 'photo_type IN (' . implode(', ', $placeholders) . ')';
    }

    $page = query_page();
    $pageSize = query_page_size(100);
    $sortable = [
        'gallery_entry_id' => 'gallery_entry_id',
        'user_id' => 'user_id',
        'player_name' => 'player_name',
        'label' => 'label',
        'photo_type' => 'photo_type',
        'species_name_es' => 'species_name_es',
        'animal_id' => 'animal_id',
        'score_type' => 'score_type',
        'score_value' => 'score_value',
        'updated_at' => 'updated_at',
    ];
    [$sortKey, $sortDir] = query_sort('updated_at', 'desc', $sortable);

    $sql = 'SELECT gallery_entry_id, user_id, player_name, label, photo_type, species_name_es, animal_id, score_type, score_value, photo_url, thumbnail_url, updated_at
            FROM gpt.v_user_gallery';
    $countSql = 'SELECT COUNT(*) AS c FROM gpt.v_user_gallery';
    if ($where !== []) {
        $whereSql = implode(' AND ', $where);
        $sql .= ' WHERE ' . $whereSql;
        $countSql .= ' WHERE ' . $whereSql;
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', gallery_entry_id DESC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $exportRows = [];
        foreach ($rows as $row) {
            $row['photo_type'] = gallery_type_label($row['photo_type'] ?? null);
            $row['updated_at'] = format_datetime_display($row['updated_at'] ?? null);
            $exportRows[] = $row;
        }
        $headers = array_map(static fn(string $k): string => $columnDefs[$k]['label'], array_values(array_filter($selectedCols, static fn(string $k): bool => $k !== 'thumbnail')));
        $exportCols = array_values(array_filter($selectedCols, static fn(string $k): bool => $k !== 'thumbnail'));
        csv_stream('galerias_usuarios.csv', $headers, $exportRows, $exportCols);
    }

    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int) ($totalRow['c'] ?? 0);
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;

    $rows = app_query_all(
        $sql . ' LIMIT :_limit OFFSET :_offset',
        $params + [
            ':_limit' => $pageSize,
            ':_offset' => $offset,
        ]
    );

    echo '<p class="muted">Vista: gpt.v_user_gallery</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="user_gallery">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="gcol_order" value="' . h(query_raw('gcol_order')) . '">';
    echo '<input type="text" name="gallery_gallery_entry_id" placeholder="IdGaleria" value="' . h(query_raw('gallery_gallery_entry_id')) . '" title="Identificador interno de la imagen de galeria">';
    echo '<input type="text" name="gallery_user_id" placeholder="IdUsuario" value="' . h(query_raw('gallery_user_id')) . '" title="Identificador interno del jugador">';
    echo '<select name="gallery_player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores" title="Jugadores con imagenes en galeria">';
    echo '<option value="">Jugador (todos)</option>';
    foreach ($playerOptions as $value) {
        $selected = in_array($value, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<select name="gallery_species_name_es[]" multiple data-check-combo="1" data-check-combo-placeholder="Especie (todas)" data-check-combo-many-label="especies" title="Especies asociadas a la imagen">';
    echo '<option value="">Especie (todas)</option>';
    foreach ($speciesOptions as $value) {
        $selected = in_array($value, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<select name="gallery_photo_type[]" multiple data-check-combo="1" data-check-combo-placeholder="Tipo (todos)" data-check-combo-many-label="tipos" title="Tipo de elemento de galeria">';
    echo '<option value="">Tipo (todos)</option>';
    foreach ($typeOptions as $value) {
        $selected = in_array($value, $photoTypes, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h(gallery_type_label($value)) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="gallery_animal_id" placeholder="IdMuerte" value="' . h(query_raw('gallery_animal_id')) . '" title="Id de la muerte asociada, cuando exista">';
    echo '<input type="text" name="gallery_label" placeholder="Etiqueta" value="' . h(query_raw('gallery_label')) . '" title="Texto libre asociado a la imagen">';
    echo '<input type="text" name="gallery_score_value" placeholder="Score" value="' . h(query_raw('gallery_score_value')) . '" title="Puntuacion del animal asociado">';
    echo '<details class="filter-details visible-columns" data-col-prefix="gcol_" data-order-field="gcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="gcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size" title="Numero de filas por pagina">';
    echo '<option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option>';
    echo '<option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option>';
    echo '<option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option>';
    echo '</select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export" value="csv">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=user_gallery">Limpiar</a>';
    echo '</form>';

    echo '<div class="table-head-wrap"><span class="muted">Total imagenes: ' . h((string) $totalRows) . '</span></div>';
    echo '<div style="overflow:auto"><table><thead><tr>';
    foreach ($selectedCols as $col) {
        $label = $columnDefs[$col]['label'] ?? $col;
        if (isset($sortable[$col])) {
            echo '<th>' . sort_link($col, $label, $sortKey, $sortDir) . '</th>';
        } else {
            echo '<th>' . h($label) . '</th>';
        }
    }
    echo '<th>Abrir</th><th>Muerte</th></tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($selectedCols as $col) {
            $value = $row[$col] ?? null;
            if ($col === 'player_name') {
                echo '<td>' . player_profile_link_html((string) $value, $value) . '</td>';
                continue;
            }
            if ($col === 'thumbnail') {
                $thumb = trim((string) ($row['thumbnail_url'] ?? ''));
                $full = trim((string) ($row['photo_url'] ?? ''));
                if ($thumb !== '') {
                    $img = '<img class="gallery-thumb" src="' . h($thumb) . '" alt="' . h((string) ($row['species_name_es'] ?? 'Galeria')) . '">';
                    if ($full !== '') {
                        $img = '<a href="' . h($full) . '" target="_blank" rel="noopener noreferrer">' . $img . '</a>';
                    }
                    echo '<td>' . $img . '</td>';
                } else {
                    echo '<td class="muted">-</td>';
                }
                continue;
            }
            if ($col === 'species_name_es') {
                $speciesName = trim((string) ($value ?? ''));
                $iconHtml = species_single_icon_html($speciesName);
                echo '<td>' . ($iconHtml !== '' ? $iconHtml . ' ' : '') . h($speciesName) . '</td>';
                continue;
            }
            if ($col === 'animal_id') {
                $killUrl = thehunter_kill_url((string) ($row['player_name'] ?? ''), $value);
                if ($killUrl !== null && trim((string) $value) !== '') {
                    echo '<td><a class="record-link" href="' . h($killUrl) . '" target="_blank" rel="noopener noreferrer">' . h((string) $value) . '</a></td>';
                } else {
                    echo '<td>' . h((string) $value) . '</td>';
                }
                continue;
            }
            if ($col === 'photo_type') {
                echo '<td>' . h(gallery_type_label($value)) . '</td>';
                continue;
            }
            if ($col === 'updated_at') {
                echo '<td>' . h(format_datetime_display($value)) . '</td>';
                continue;
            }
            $cellClass = is_numeric((string) $value) ? ' class="num-cell"' : '';
            echo '<td' . $cellClass . '>' . h((string) $value) . '</td>';
        }

        $full = trim((string) ($row['photo_url'] ?? ''));
        if ($full !== '') {
            echo '<td><a class="record-link" href="' . h($full) . '" target="_blank" rel="noopener noreferrer">Abrir imagen</a></td>';
        } else {
            echo '<td class="muted">-</td>';
        }

        $killUrl = thehunter_kill_url((string) ($row['player_name'] ?? ''), $row['animal_id'] ?? null);
        if ($killUrl !== null && trim((string) ($row['animal_id'] ?? '')) !== '') {
            echo '<td><a class="record-link" href="' . h($killUrl) . '" target="_blank" rel="noopener noreferrer">Abrir muerte</a></td>';
        } else {
            echo '<td class="muted">-</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_kill_url_scrape_status(): void
{
    echo '<section class="card"><h2>Estado Scraper Muertes</h2>';

    $columnDefs = [
        'run_at' => ['label' => 'Ultima descarga'],
        'source' => ['label' => 'Origen'],
        'url_type' => ['label' => 'Tipo URL'],
        'player_name' => ['label' => 'Jugador'],
        'animal_id' => ['label' => 'Animal ID'],
        'kill_id' => ['label' => 'Kill ID'],
        'http_code' => ['label' => 'HTTP'],
        'ok' => ['label' => 'OK'],
        'page_kind' => ['label' => 'Tipo pagina'],
        'requires_login' => ['label' => 'Login'],
        'parsed_summary' => ['label' => 'Resumen'],
        'attempts' => ['label' => 'Intentos'],
        'last_success_at' => ['label' => 'Ultimo OK'],
        'url' => ['label' => 'URL'],
        'local_file_rel' => ['label' => 'Fichero'],
        'error' => ['label' => 'Error'],
        'page_title' => ['label' => 'Titulo'],
    ];
    $defaultCols = ['run_at', 'source', 'url_type', 'player_name', 'animal_id', 'kill_id', 'http_code', 'ok', 'page_kind', 'requires_login', 'parsed_summary', 'attempts'];
    $selectedCols = persistent_selected_columns('kill_scrape_visible_cols', $columnDefs, 'kscol_', $defaultCols);
    $dragOrderRaw = query_text('kscol_order');
    if ($dragOrderRaw !== null && trim($dragOrderRaw) !== '') {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn(string $k): bool => $k !== '' && isset($columnDefs[$k])));
        if ($ordered !== []) {
            $rest = array_values(array_filter($selectedCols, static fn(string $k): bool => !in_array($k, $ordered, true)));
            $selectedCols = array_merge($ordered, $rest);
            $_SESSION['kill_scrape_visible_cols'] = $selectedCols;
        }
    }

    $sources = query_list('ks_source');
    $urlTypes = query_list('ks_url_type');
    $pageKinds = query_list('ks_page_kind');
    $okValues = query_list('ks_ok');

    $sourceOptions = array_values(array_filter(array_map(static fn(array $r): string => trim((string) ($r['source'] ?? '')), app_query_all("SELECT DISTINCT source FROM gpt.v_scrape_kill_urls_latest WHERE COALESCE(source,'') <> '' ORDER BY source"))));
    $typeOptions = array_values(array_filter(array_map(static fn(array $r): string => trim((string) ($r['url_type'] ?? '')), app_query_all("SELECT DISTINCT url_type FROM gpt.v_scrape_kill_urls_latest WHERE COALESCE(url_type,'') <> '' ORDER BY url_type"))));
    $kindOptions = array_values(array_filter(array_map(static fn(array $r): string => trim((string) ($r['page_kind'] ?? '')), app_query_all("SELECT DISTINCT page_kind FROM gpt.v_scrape_kill_urls_latest WHERE COALESCE(page_kind,'') <> '' ORDER BY page_kind"))));

    $where = [];
    $params = [];
    foreach (['player_name', 'animal_id', 'kill_id', 'url', 'parsed_summary', 'error'] as $idx => $col) {
        $value = query_text('ks_' . $col);
        if ($value === null) {
            continue;
        }
        $ph = ':ks_filter_' . $idx;
        $where[] = quote_ident($col) . '::text ILIKE ' . $ph;
        $params[$ph] = '%' . $value . '%';
    }

    foreach ([['source', $sources], ['url_type', $urlTypes], ['page_kind', $pageKinds], ['ok', $okValues]] as [$col, $values]) {
        if ($values === []) {
            continue;
        }
        $phs = [];
        foreach (array_values($values) as $idx => $value) {
            $ph = ':ks_' . $col . '_' . $idx;
            $phs[] = $ph;
            $params[$ph] = $col === 'ok' ? ($value === '1') : $value;
        }
        $where[] = quote_ident((string) $col) . ' IN (' . implode(', ', $phs) . ')';
    }

    $page = query_page();
    $pageSize = query_page_size(100);
    $sortable = [
        'run_at' => 'run_at',
        'source' => 'source',
        'url_type' => 'url_type',
        'player_name' => 'player_name',
        'animal_id' => 'animal_id',
        'kill_id' => 'kill_id',
        'http_code' => 'http_code',
        'ok' => 'ok',
        'page_kind' => 'page_kind',
        'attempts' => 'attempts',
        'last_success_at' => 'last_success_at',
    ];
    [$sortKey, $sortDir] = query_sort('run_at', 'desc', $sortable);
    $sql = 'SELECT * FROM gpt.v_scrape_kill_urls_latest';
    $countSql = 'SELECT COUNT(*) AS c FROM gpt.v_scrape_kill_urls_latest';
    if ($where !== []) {
        $whereSql = implode(' AND ', $where);
        $sql .= ' WHERE ' . $whereSql;
        $countSql .= ' WHERE ' . $whereSql;
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', id DESC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        foreach ($rows as &$row) {
            $row['run_at'] = format_datetime_display($row['run_at'] ?? null);
            $row['last_success_at'] = format_datetime_display($row['last_success_at'] ?? null);
            $row['ok'] = !empty($row['ok']) ? 'Si' : 'No';
            $row['requires_login'] = !isset($row['requires_login']) ? '' : (!empty($row['requires_login']) ? 'Si' : 'No');
        }
        unset($row);
        $headers = array_map(static fn(string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('estado_scraper_muertes.csv', $headers, $rows, $selectedCols);
    }

    $summary = app_query_one(
        "SELECT
            COUNT(*) AS total_urls,
            COUNT(*) FILTER (WHERE ok = TRUE) AS total_ok,
            COUNT(*) FILTER (WHERE ok = FALSE) AS total_error,
            COUNT(*) FILTER (WHERE requires_login = TRUE) AS total_login,
            COUNT(*) FILTER (WHERE page_kind = 'public_home_not_signed_in') AS total_public_home
         FROM gpt.v_scrape_kill_urls_latest"
    );

    $totalRows = (int) ((app_query_one($countSql, $params)['c'] ?? 0));
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;
    $rows = app_query_all($sql . ' LIMIT :_limit OFFSET :_offset', $params + [':_limit' => $pageSize, ':_offset' => $offset]);

    echo '<p class="muted">Vista: gpt.v_scrape_kill_urls_latest</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="kill_scrape_status">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="kscol_order" value="' . h(query_raw('kscol_order')) . '">';
    echo '<select name="ks_source[]" multiple data-check-combo="1" data-check-combo-placeholder="Origen (todos)" data-check-combo-many-label="origenes" title="Origen de la URL">';
    echo '<option value="">Origen (todos)</option>';
    foreach ($sourceOptions as $value) {
        $selected = in_array($value, $sources, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<select name="ks_url_type[]" multiple data-check-combo="1" data-check-combo-placeholder="Tipo URL (todos)" data-check-combo-many-label="tipos" title="Tipo de URL detectado">';
    echo '<option value="">Tipo URL (todos)</option>';
    foreach ($typeOptions as $value) {
        $selected = in_array($value, $urlTypes, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="ks_player_name" placeholder="Jugador" value="' . h(query_raw('ks_player_name')) . '" title="Jugador detectado desde la URL">';
    echo '<input type="text" name="ks_animal_id" placeholder="Animal ID" value="' . h(query_raw('ks_animal_id')) . '" title="Animal id detectado">';
    echo '<input type="text" name="ks_kill_id" placeholder="Kill ID" value="' . h(query_raw('ks_kill_id')) . '" title="Kill id detectado">';
    echo '<select name="ks_ok[]" multiple data-check-combo="1" data-check-combo-placeholder="OK (todos)" data-check-combo-many-label="estados" title="Resultado de descarga">';
    echo '<option value="">OK (todos)</option>';
    echo '<option value="1"' . (in_array('1', $okValues, true) ? ' selected' : '') . '>Si</option>';
    echo '<option value="0"' . (in_array('0', $okValues, true) ? ' selected' : '') . '>No</option>';
    echo '</select>';
    echo '<select name="ks_page_kind[]" multiple data-check-combo="1" data-check-combo-placeholder="Tipo pagina (todos)" data-check-combo-many-label="paginas" title="Clasificacion del HTML descargado">';
    echo '<option value="">Tipo pagina (todos)</option>';
    foreach ($kindOptions as $value) {
        $selected = in_array($value, $pageKinds, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="ks_parsed_summary" placeholder="Resumen parseo" value="' . h(query_raw('ks_parsed_summary')) . '" title="Resumen del parseo">';
    echo '<input type="text" name="ks_error" placeholder="Error" value="' . h(query_raw('ks_error')) . '" title="Error de descarga">';
    echo '<input type="text" name="ks_url" placeholder="URL" value="' . h(query_raw('ks_url')) . '" title="URL completa">';
    echo '<details class="filter-details visible-columns" data-col-prefix="kscol_" data-order-field="kscol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="kscol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size"><option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option><option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option><option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option></select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export" value="csv">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=kill_scrape_status">Limpiar</a>';
    echo '</form>';

    echo '<div class="table-head-wrap">';
    echo '<span class="muted">URLs: ' . h((string) ($summary['total_urls'] ?? 0)) . '</span> ';
    echo '<span class="muted">OK: ' . h((string) ($summary['total_ok'] ?? 0)) . '</span> ';
    echo '<span class="muted">Error: ' . h((string) ($summary['total_error'] ?? 0)) . '</span> ';
    echo '<span class="muted">Requieren login: ' . h((string) ($summary['total_login'] ?? 0)) . '</span> ';
    echo '<span class="muted">Portada publica: ' . h((string) ($summary['total_public_home'] ?? 0)) . '</span>';
    echo '</div>';

    echo '<div style="overflow:auto"><table><thead><tr>';
    foreach ($selectedCols as $col) {
        if (isset($sortable[$col])) {
            echo '<th>' . sort_link($col, $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
        } else {
            echo '<th>' . h($columnDefs[$col]['label']) . '</th>';
        }
    }
    echo '<th>Abrir</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($selectedCols as $col) {
            $value = $row[$col] ?? null;
            if ($col === 'player_name') {
                echo '<td>' . player_profile_link_html((string) $value, $value) . '</td>';
                continue;
            }
            if ($col === 'run_at' || $col === 'last_success_at') {
                echo '<td>' . h(format_datetime_display($value)) . '</td>';
                continue;
            }
            if ($col === 'ok') {
                echo '<td>' . (!empty($value) ? 'Si' : 'No') . '</td>';
                continue;
            }
            if ($col === 'requires_login') {
                echo '<td>' . (!isset($value) ? '-' : (!empty($value) ? 'Si' : 'No')) . '</td>';
                continue;
            }
            if ($col === 'url' && trim((string) $value) !== '') {
                echo '<td><a class="record-link" href="' . h((string) $value) . '" target="_blank" rel="noopener noreferrer">' . h((string) $value) . '</a></td>';
                continue;
            }
            $cellClass = is_numeric((string) $value) ? ' class="num-cell"' : '';
            echo '<td' . $cellClass . '>' . h((string) $value) . '</td>';
        }
        $url = trim((string) ($row['url'] ?? ''));
        echo $url !== '' ? '<td><a class="record-link" href="' . h($url) . '" target="_blank" rel="noopener noreferrer">Abrir URL</a></td>' : '<td class="muted">-</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_kill_detail_scrapes(): void
{
    echo '<section class="card"><h2>Detalle Muertes Scraper</h2>';

    $columnDefs = [
        'scraped_at' => ['label' => 'Ultima captura'],
        'player_name' => ['label' => 'Jugador'],
        'kill_id' => ['label' => 'Kill ID'],
        'species_name' => ['label' => 'Especie'],
        'hunter_name' => ['label' => 'Cazador'],
        'weapon_text' => ['label' => 'Arma'],
        'scope_text' => ['label' => 'Visor'],
        'ammo_text' => ['label' => 'Municion'],
        'shot_distance_text' => ['label' => 'Distancia disparo'],
        'animal_state_text' => ['label' => 'Estado animal'],
        'body_part_text' => ['label' => 'Parte cuerpo'],
        'posture_text' => ['label' => 'Postura'],
        'platform_text' => ['label' => 'Plataforma'],
        'shot_location_text' => ['label' => 'Lugar disparo'],
        'weight_text' => ['label' => 'Peso'],
        'type_text' => ['label' => 'Tipo'],
        'wound_time_text' => ['label' => 'Tiempo herida'],
        'trophy_integrity_text' => ['label' => 'Integridad trofeo'],
        'shot_count_text' => ['label' => 'Disparos'],
        'capture_time_text' => ['label' => 'Tiempo captura'],
        'trophy_score_text' => ['label' => 'Trophy score'],
        'harvest_value_text' => ['label' => 'Valor captura'],
        'page_title' => ['label' => 'Titulo'],
    ];
    $defaultCols = ['scraped_at', 'player_name', 'kill_id', 'species_name', 'weapon_text', 'scope_text', 'ammo_text', 'shot_distance_text', 'animal_state_text', 'body_part_text', 'posture_text', 'platform_text', 'shot_location_text', 'trophy_score_text'];
    $selectedCols = persistent_selected_columns('kill_detail_visible_cols', $columnDefs, 'kdcol_', $defaultCols);
    $dragOrderRaw = query_text('kdcol_order');
    if ($dragOrderRaw !== null && trim($dragOrderRaw) !== '') {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn(string $k): bool => $k !== '' && isset($columnDefs[$k])));
        if ($ordered !== []) {
            $rest = array_values(array_filter($selectedCols, static fn(string $k): bool => !in_array($k, $ordered, true)));
            $selectedCols = array_merge($ordered, $rest);
            $_SESSION['kill_detail_visible_cols'] = $selectedCols;
        }
    }

    $playerNames = query_list('kd_player_name');
    $speciesNames = query_list('kd_species_name');
    $weaponText = query_text('kd_weapon_text');
    $ammoText = query_text('kd_ammo_text');
    $scopeText = query_text('kd_scope_text');
    $platformText = query_text('kd_platform_text');
    $killIdText = query_text('kd_kill_id');

    $playerOptions = array_values(array_filter(array_map(static fn(array $r): string => trim((string) ($r['player_name'] ?? '')), app_query_all("SELECT DISTINCT player_name FROM gpt.v_kill_detail_scrapes_latest WHERE COALESCE(player_name,'') <> '' ORDER BY player_name"))));
    $speciesOptions = array_values(array_filter(array_map(static fn(array $r): string => trim((string) ($r['species_name'] ?? '')), app_query_all("SELECT DISTINCT species_name FROM gpt.v_kill_detail_scrapes_latest WHERE COALESCE(species_name,'') <> '' ORDER BY species_name"))));

    $where = [];
    $params = [];
    if ($playerNames !== []) {
        $phs = [];
        foreach (array_values($playerNames) as $idx => $value) {
            $ph = ':kd_player_' . $idx;
            $phs[] = $ph;
            $params[$ph] = $value;
        }
        $where[] = 'player_name IN (' . implode(', ', $phs) . ')';
    }
    if ($speciesNames !== []) {
        $phs = [];
        foreach (array_values($speciesNames) as $idx => $value) {
            $ph = ':kd_species_' . $idx;
            $phs[] = $ph;
            $params[$ph] = $value;
        }
        $where[] = 'species_name IN (' . implode(', ', $phs) . ')';
    }
    foreach ([
        ['kill_id', $killIdText],
        ['weapon_text', $weaponText],
        ['ammo_text', $ammoText],
        ['scope_text', $scopeText],
        ['platform_text', $platformText],
    ] as $idx => [$col, $value]) {
        if ($value === null) {
            continue;
        }
        $ph = ':kd_filter_' . $idx;
        $where[] = quote_ident((string) $col) . '::text ILIKE ' . $ph;
        $params[$ph] = '%' . $value . '%';
    }

    $page = query_page();
    $pageSize = query_page_size(100);
    $sortable = [
        'scraped_at' => 'scraped_at',
        'player_name' => 'player_name',
        'kill_id' => 'kill_id',
        'species_name' => 'species_name',
        'hunter_name' => 'hunter_name',
        'weapon_text' => 'weapon_text',
        'ammo_text' => 'ammo_text',
        'shot_distance_text' => 'shot_distance_text',
        'trophy_score_text' => 'trophy_score_text',
    ];
    [$sortKey, $sortDir] = query_sort('scraped_at', 'desc', $sortable);
    $sql = 'SELECT * FROM gpt.v_kill_detail_scrapes_latest';
    $countSql = 'SELECT COUNT(*) AS c FROM gpt.v_kill_detail_scrapes_latest';
    if ($where !== []) {
        $whereSql = implode(' AND ', $where);
        $sql .= ' WHERE ' . $whereSql;
        $countSql .= ' WHERE ' . $whereSql;
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', scrape_id DESC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        foreach ($rows as &$row) {
            $row['scraped_at'] = format_datetime_display($row['scraped_at'] ?? null);
        }
        unset($row);
        $headers = array_map(static fn(string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('detalle_muertes_scraper.csv', $headers, $rows, $selectedCols);
    }

    $summary = app_query_one(
        'SELECT COUNT(*) AS total_rows,
                COUNT(*) FILTER (WHERE COALESCE(weapon_text, \'\') <> \'\') AS con_arma,
                COUNT(*) FILTER (WHERE COALESCE(scope_text, \'\') <> \'\') AS con_visor,
                COUNT(*) FILTER (WHERE COALESCE(ammo_text, \'\') <> \'\') AS con_municion,
                COUNT(*) FILTER (WHERE COALESCE(shot_location_text, \'\') <> \'\') AS con_lugar
         FROM gpt.v_kill_detail_scrapes_latest'
    );

    $totalRows = (int) ((app_query_one($countSql, $params)['c'] ?? 0));
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;
    $rows = app_query_all($sql . ' LIMIT :_limit OFFSET :_offset', $params + [':_limit' => $pageSize, ':_offset' => $offset]);

    echo '<p class="muted">Vista: gpt.v_kill_detail_scrapes_latest</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="kill_detail_scrapes">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="kdcol_order" value="' . h(query_raw('kdcol_order')) . '">';
    echo '<select name="kd_player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores" title="Jugador del registro capturado">';
    echo '<option value="">Jugador (todos)</option>';
    foreach ($playerOptions as $value) {
        $selected = in_array($value, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<select name="kd_species_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Especie (todas)" data-check-combo-many-label="especies" title="Especie de la muerte capturada">';
    echo '<option value="">Especie (todas)</option>';
    foreach ($speciesOptions as $value) {
        $selected = in_array($value, $speciesNames, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="kd_kill_id" placeholder="Kill ID" value="' . h(query_raw('kd_kill_id')) . '" title="Kill ID del theHunter">';
    echo '<input type="text" name="kd_weapon_text" placeholder="Arma" value="' . h(query_raw('kd_weapon_text')) . '" title="Arma mostrada en la ficha">';
    echo '<input type="text" name="kd_scope_text" placeholder="Visor" value="' . h(query_raw('kd_scope_text')) . '" title="Visor mostrado en la ficha">';
    echo '<input type="text" name="kd_ammo_text" placeholder="Municion" value="' . h(query_raw('kd_ammo_text')) . '" title="Municion mostrada en la ficha">';
    echo '<input type="text" name="kd_platform_text" placeholder="Plataforma" value="' . h(query_raw('kd_platform_text')) . '" title="Plataforma mostrada en la ficha">';
    echo '<details class="filter-details visible-columns" data-col-prefix="kdcol_" data-order-field="kdcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="kdcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size"><option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option><option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option><option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option></select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export" value="csv">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=kill_detail_scrapes">Limpiar</a>';
    echo '</form>';

    echo '<div class="table-head-wrap">';
    echo '<span class="muted">Registros: ' . h((string) ($summary['total_rows'] ?? 0)) . '</span> ';
    echo '<span class="muted">Con arma: ' . h((string) ($summary['con_arma'] ?? 0)) . '</span> ';
    echo '<span class="muted">Con visor: ' . h((string) ($summary['con_visor'] ?? 0)) . '</span> ';
    echo '<span class="muted">Con municion: ' . h((string) ($summary['con_municion'] ?? 0)) . '</span> ';
    echo '<span class="muted">Con lugar: ' . h((string) ($summary['con_lugar'] ?? 0)) . '</span>';
    echo '</div>';

    echo '<div style="overflow:auto"><table><thead><tr>';
    foreach ($selectedCols as $col) {
        if (isset($sortable[$col])) {
            echo '<th>' . sort_link($col, $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
        } else {
            echo '<th>' . h($columnDefs[$col]['label']) . '</th>';
        }
    }
    echo '<th>Abrir</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($selectedCols as $col) {
            $value = $row[$col] ?? null;
            if ($col === 'player_name' || $col === 'hunter_name') {
                echo '<td>' . player_profile_link_html((string) $value, $value) . '</td>';
                continue;
            }
            if ($col === 'scraped_at') {
                echo '<td>' . h(format_datetime_display($value)) . '</td>';
                continue;
            }
            $cellClass = is_numeric((string) $value) ? ' class="num-cell"' : '';
            echo '<td' . $cellClass . '>' . h((string) $value) . '</td>';
        }
        $url = thehunter_kill_url((string) ($row['player_name'] ?? ''), $row['kill_id'] ?? null);
        echo $url !== null ? '<td><a class="record-link" href="' . h($url) . '" target="_blank" rel="noopener noreferrer">Abrir muerte</a></td>' : '<td class="muted">-</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function render_competition_signups(): void
{
    echo '<section class="card"><h2>Inscripciones Competiciones</h2>';

    $columnDefs = [
        'join_result_id' => ['label' => 'Id'],
        'player_name' => ['label' => 'Jugador'],
        'competition_id' => ['label' => 'IdCompeticion'],
        'competition_name' => ['label' => 'Competicion'],
        'status' => ['label' => 'Estado'],
        'response_summary' => ['label' => 'Motivo'],
        'request_method' => ['label' => 'Metodo'],
        'request_param' => ['label' => 'Parametro'],
        'response_body' => ['label' => 'Respuesta'],
        'created_at' => ['label' => 'Fecha'],
    ];
    $defaultCols = ['player_name', 'competition_id', 'competition_name', 'status', 'response_summary', 'request_method', 'request_param', 'created_at'];
    $selectedCols = persistent_selected_columns('comp_signup_visible_cols', $columnDefs, 'cjcol_', $defaultCols);
    $dragOrderRaw = query_text('cjcol_order');
    if ($dragOrderRaw !== null && trim($dragOrderRaw) !== '') {
        $ordered = array_values(array_filter(array_map('trim', explode(',', $dragOrderRaw)), static fn(string $k): bool => $k !== '' && isset($columnDefs[$k])));
        if ($ordered !== []) {
            $rest = array_values(array_filter($selectedCols, static fn(string $k): bool => !in_array($k, $ordered, true)));
            $selectedCols = array_merge($ordered, $rest);
            $_SESSION['comp_signup_visible_cols'] = $selectedCols;
        }
    }

    $playerNames = query_list('cj_player_name');
    $statuses = query_list('cj_status');
    $playerOptionRows = app_query_all("SELECT DISTINCT player_name FROM gpt.v_comp_join_results WHERE COALESCE(player_name,'') <> '' ORDER BY player_name");
    $statusOptionRows = app_query_all("SELECT DISTINCT status FROM gpt.v_comp_join_results WHERE COALESCE(status,'') <> '' ORDER BY status");
    $playerOptions = array_values(array_filter(array_map(static fn(array $r): string => trim((string) ($r['player_name'] ?? '')), $playerOptionRows)));
    $statusOptions = array_values(array_filter(array_map(static fn(array $r): string => trim((string) ($r['status'] ?? '')), $statusOptionRows)));

    $where = [];
    $params = [];
    foreach (['competition_id', 'competition_name', 'request_method', 'request_param'] as $idx => $col) {
        $value = query_text('cj_' . $col);
        if ($value === null) {
            continue;
        }
        $ph = ':cj_filter_' . $idx;
        $where[] = quote_ident($col) . '::text ILIKE ' . $ph;
        $params[$ph] = '%' . $value . '%';
    }
    if ($playerNames !== []) {
        $phs = [];
        foreach (array_values($playerNames) as $idx => $value) {
            $ph = ':cj_player_' . $idx;
            $phs[] = $ph;
            $params[$ph] = $value;
        }
        $where[] = 'player_name IN (' . implode(', ', $phs) . ')';
    }
    if ($statuses !== []) {
        $phs = [];
        foreach (array_values($statuses) as $idx => $value) {
            $ph = ':cj_status_' . $idx;
            $phs[] = $ph;
            $params[$ph] = $value;
        }
        $where[] = 'status IN (' . implode(', ', $phs) . ')';
    }

    $page = query_page();
    $pageSize = query_page_size(100);
    $sortable = [
        'join_result_id' => 'join_result_id',
        'player_name' => 'player_name',
        'competition_id' => 'competition_id',
        'competition_name' => 'competition_name',
        'status' => 'status',
        'request_method' => 'request_method',
        'request_param' => 'request_param',
        'created_at' => 'created_at',
    ];
    [$sortKey, $sortDir] = query_sort('created_at', 'desc', $sortable);
    $sql = 'SELECT join_result_id, player_name, competition_id, competition_name, status, request_method, request_param, response_body, created_at, competition_url FROM gpt.v_comp_join_results';
    $countSql = 'SELECT COUNT(*) AS c FROM gpt.v_comp_join_results';
    if ($where !== []) {
        $whereSql = implode(' AND ', $where);
        $sql .= ' WHERE ' . $whereSql;
        $countSql .= ' WHERE ' . $whereSql;
    }
    $sql .= ' ORDER BY ' . $sortable[$sortKey] . ' ' . strtoupper($sortDir) . ', join_result_id DESC';

    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        foreach ($rows as &$row) {
            $row['response_summary'] = competition_signup_response_summary((string) ($row['status'] ?? ''), $row['response_body'] ?? null);
            $row['created_at'] = format_datetime_display($row['created_at'] ?? null);
        }
        unset($row);
        $headers = array_map(static fn(string $k): string => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('inscripciones_competiciones.csv', $headers, $rows, $selectedCols);
    }

    $totalRows = (int) ((app_query_one($countSql, $params)['c'] ?? 0));
    $pageCount = max(1, (int) ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;
    $rows = app_query_all($sql . ' LIMIT :_limit OFFSET :_offset', $params + [':_limit' => $pageSize, ':_offset' => $offset]);

    echo '<p class="muted">Vista: gpt.v_comp_join_results</p>';
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="competition_signups">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="cjcol_order" value="' . h(query_raw('cjcol_order')) . '">';
    echo '<select name="cj_player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores" title="Jugador inscrito">';
    echo '<option value="">Jugador (todos)</option>';
    foreach ($playerOptions as $value) {
        $selected = in_array($value, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="cj_competition_id" placeholder="IdCompeticion" value="' . h(query_raw('cj_competition_id')) . '" title="Id de competicion">';
    echo '<input type="text" name="cj_competition_name" placeholder="Competicion" value="' . h(query_raw('cj_competition_name')) . '" title="Nombre de la competicion">';
    echo '<select name="cj_status[]" multiple data-check-combo="1" data-check-combo-placeholder="Estado (todos)" data-check-combo-many-label="estados" title="Estado del intento de inscripcion">';
    echo '<option value="">Estado (todos)</option>';
    foreach ($statusOptions as $value) {
        $selected = in_array($value, $statuses, true) ? ' selected' : '';
        echo '<option value="' . h($value) . '"' . $selected . '>' . h($value) . '</option>';
    }
    echo '</select>';
    echo '<input type="text" name="cj_request_method" placeholder="Metodo" value="' . h(query_raw('cj_request_method')) . '" title="Metodo HTTP usado">';
    echo '<input type="text" name="cj_request_param" placeholder="Parametro" value="' . h(query_raw('cj_request_param')) . '" title="Nombre del parametro usado">';
    echo '<details class="filter-details visible-columns" data-col-prefix="cjcol_" data-order-field="cjcol_order"><summary>Columnas visibles</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '"><input class="col-check" type="checkbox" name="cjcol_' . h($key) . '" value="1"' . $checked . '><span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    echo '<select name="page_size"><option value="50"' . ($pageSize === 50 ? ' selected' : '') . '>50 filas</option><option value="100"' . ($pageSize === 100 ? ' selected' : '') . '>100 filas</option><option value="200"' . ($pageSize === 200 ? ' selected' : '') . '>200 filas</option></select>';
    echo '<button type="submit">Filtrar</button>';
    echo '<button type="submit" name="export" value="csv">Exportar CSV</button>';
    echo '<a class="btn-link" href="?view=competition_signups">Limpiar</a>';
    echo '</form>';

    echo '<div class="table-head-wrap"><span class="muted">Total intentos: ' . h((string) $totalRows) . '</span></div>';
    echo '<div style="overflow:auto"><table><thead><tr>';
    foreach ($selectedCols as $col) {
        echo '<th>' . sort_link($col, $columnDefs[$col]['label'], $sortKey, $sortDir) . '</th>';
    }
    echo '<th>Abrir</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($selectedCols as $col) {
            $value = $row[$col] ?? null;
            if ($col === 'player_name') {
                echo '<td>' . player_profile_link_html((string) $value, $value) . '</td>';
                continue;
            }
            if ($col === 'status') {
                echo '<td>' . competition_signup_status_badge((string) $value) . '</td>';
                continue;
            }
            if ($col === 'response_summary') {
                echo '<td>' . h(competition_signup_response_summary((string) ($row['status'] ?? ''), $row['response_body'] ?? null)) . '</td>';
                continue;
            }
            if ($col === 'created_at') {
                echo '<td>' . h(format_datetime_display($value)) . '</td>';
                continue;
            }
            if ($col === 'response_body') {
                $raw = trim((string) $value);
                if ($raw === '') {
                    echo '<td class="muted">-</td>';
                } else {
                    echo '<td><details><summary>Ver respuesta</summary><pre class="raw-response-block">' . h($raw) . '</pre></details></td>';
                }
                continue;
            }
            $cellClass = is_numeric((string) $value) ? ' class="num-cell"' : '';
            echo '<td' . $cellClass . '>' . h((string) $value) . '</td>';
        }
        $url = trim((string) ($row['competition_url'] ?? ''));
        echo $url !== '' ? '<td><a class="record-link" href="' . h($url) . '" target="_blank" rel="noopener noreferrer">Abrir competicion</a></td>' : '<td class="muted">-</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

function competition_signup_status_badge(string $status): string
{
    $status = trim($status);
    $class = 'signup-status-badge signup-status-failed';
    $label = $status !== '' ? $status : 'failed';

    if ($status === 'joined') {
        $class = 'signup-status-badge signup-status-joined';
    } elseif ($status === 'already_joined') {
        $class = 'signup-status-badge signup-status-already';
    } elseif ($status === 'auth_error') {
        $class = 'signup-status-badge signup-status-auth';
    } elseif ($status === 'ineligible') {
        $class = 'signup-status-badge signup-status-ineligible';
    } elseif ($status === 'skipped') {
        $class = 'signup-status-badge signup-status-skipped';
    }

    return '<span class="' . h($class) . '">' . h($label) . '</span>';
}

function competition_signup_response_summary(string $status, mixed $responseBody): string
{
    $status = trim($status);
    $raw = trim((string) $responseBody);
    $rawLower = strtolower($raw);

    if ($status === 'joined') {
        return 'Inscripcion correcta';
    }
    if ($status === 'already_joined') {
        return 'La competicion ya estaba inscrita';
    }
    if ($status === 'auth_error') {
        return 'Sesion no valida o acceso denegado';
    }
    if ($status === 'ineligible') {
        return $raw !== '' ? $raw : 'El jugador no cumple el tramo de muertes de la competicion';
    }
    if ($status === 'skipped') {
        return 'Saltada por intento previo';
    }
    if ($rawLower === 'false') {
        return 'La API rechazo la inscripcion para esa competicion';
    }
    if ($rawLower === 'true') {
        return 'Inscripcion correcta';
    }
    if ($raw === '') {
        return 'Sin detalle de respuesta';
    }

    $json = json_decode($raw, true);
    if (is_array($json)) {
        $message = trim((string) ($json['message'] ?? $json['errorMessage'] ?? $json['code'] ?? ''));
        if ($message !== '') {
            return $message;
        }
    }

    return $raw;
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
        case 'trophies_summary':
            render_trophies_summary();
            break;
        case 'user_gallery':
            render_user_gallery();
            break;
        case 'kill_scrape_status':
            render_kill_url_scrape_status();
            break;
        case 'kill_detail_scrapes':
            render_kill_detail_scrapes();
            break;
        case 'competition_signups':
            render_competition_signups();
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
$sidebarItems = [
    'dashboard' => 'Panel',
    'expeditions' => 'Expediciones',
    'best' => 'Mejores Marcas',
    'profiles' => 'Estadisticas',
    'competitions' => 'Competiciones',
    'classifications' => 'Tablas Clasificacion',
    'species_ppft' => 'Especies PPFT',
    'hall_of_fame' => 'Salones Fama',
    'trophies_summary' => 'Resumen Trofeos',
    'user_gallery' => 'Galerias Usuarios',
    'kill_scrape_status' => 'Estado Scraper',
    'kill_detail_scrapes' => 'Detalle Muertes',
    'competition_signups' => 'Inscripciones Comp.',
    'cheat_risk' => 'Anti-trampas',
];
if (app_is_admin_user()) {
    $sidebarItems['logs'] = 'Logs';
}
$sidebarItems['advanced'] = 'Consulta Avanzada';

$savedSidebarOrder = $uiPreferences['thc_sidebar_nav_order']['order'] ?? [];
if (is_array($savedSidebarOrder) && $savedSidebarOrder !== []) {
    $orderedSidebarItems = [];
    foreach ($savedSidebarOrder as $key) {
        $key = (string) $key;
        if (array_key_exists($key, $sidebarItems)) {
            $orderedSidebarItems[$key] = $sidebarItems[$key];
            unset($sidebarItems[$key]);
        }
    }
    $sidebarItems = $orderedSidebarItems + $sidebarItems;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>THC GPT Panel</title>
    <link rel="stylesheet" href="style.css?v=<?= h($cssVersion) ?>">
</head>
<body class="theme-<?= h($theme) ?> font-<?= h($font) ?><?= $view === 'expeditions' ? ' view-expeditions-initializing' : '' ?>">
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
            <?php foreach ($sidebarItems as $key => $label): ?>
                <?= menu_link((string) $key, (string) $label, $view) ?>
            <?php endforeach; ?>
        </nav>
        <details class="theme-switch sidebar-collapsible">
            <summary class="theme-title">Tema</summary>
            <div class="theme-row">
                <?= theme_link('sober', 'Sobrio', $view, $theme) ?>
                <?= theme_link('aurora', 'Aurora', $view, $theme) ?>
                <?= theme_link('arctic', 'Artico', $view, $theme) ?>
                <?= theme_link('studio', 'Studio', $view, $theme) ?>
                <?= theme_link('lagoon', 'Lagoon', $view, $theme) ?>
                <?= theme_link('sandstone', 'Sandstone', $view, $theme) ?>
                <?= theme_link('skyline', 'Skyline', $view, $theme) ?>
                <?= theme_link('terminal', 'Terminal', $view, $theme) ?>
                <?= theme_link('noir', 'Noir', $view, $theme) ?>
            </div>
        </details>
        <details class="font-switch sidebar-collapsible">
            <summary class="theme-title">Fuente</summary>
            <div class="font-row">
                <?= font_link('system', 'UI', 'Aa Bb 123', $view, $font, 'font-chip-system') ?>
                <?= font_link('modern', 'Moderna', 'Aa Bb 123', $view, $font, 'font-chip-modern') ?>
                <?= font_link('classic', 'Clasica', 'Aa Bb 123', $view, $font, 'font-chip-classic') ?>
                <?= font_link('serif', 'Serif', 'Aa Bb 123', $view, $font, 'font-chip-serif') ?>
                <?= font_link('mono', 'Mono', 'Aa Bb 123', $view, $font, 'font-chip-mono') ?>
            </div>
        </details>
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
            case 'trophies_summary':
                render_trophies_summary();
                break;
            case 'user_gallery':
                render_user_gallery();
                break;
            case 'kill_scrape_status':
                render_kill_url_scrape_status();
                break;
            case 'kill_detail_scrapes':
                render_kill_detail_scrapes();
                break;
            case 'competition_signups':
                render_competition_signups();
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
            case 'table_styles_preview':
                render_table_styles_preview();
                break;
            default:
                render_dashboard();
                break;
        }
        ?>
    </main>
</div>
<script>
window.THC_GLOBAL_PREFS = <?= json_encode($uiPreferences, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.thcReadGlobalPref = (key) => {
    const source = window.THC_GLOBAL_PREFS || {};
    const value = source[key];
    return value && typeof value === 'object' && !Array.isArray(value) ? {...value} : {};
};
window.thcWriteGlobalPref = (key, value) => {
    if (!window.THC_GLOBAL_PREFS || typeof window.THC_GLOBAL_PREFS !== 'object') {
        window.THC_GLOBAL_PREFS = {};
    }
    window.THC_GLOBAL_PREFS[key] = value && typeof value === 'object' ? value : {};
    fetch('ui_preferences.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({key, value: window.THC_GLOBAL_PREFS[key]}),
        credentials: 'same-origin',
    }).catch(() => {});
};
</script>
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
                hiddenOrder.dispatchEvent(new Event('change', { bubbles: true }));
            }

            checkboxes.forEach((cb) => cb.dispatchEvent(new Event('change', { bubbles: true })));
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
                hiddenOrder.dispatchEvent(new Event('change', { bubbles: true }));
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
    const view = new URLSearchParams(window.location.search).get('view') || 'dashboard';
    const params = new URLSearchParams(window.location.search);
    const resetRequested = params.get('reset') === '1';

    const normalizeName = (name) => String(name || '').trim();
    const safeRead = (key) => {
        try {
            return JSON.parse(localStorage.getItem(key) || '{}');
        } catch (_) {
            return {};
        }
    };
    const safeWrite = (key, value) => {
        localStorage.setItem(key, JSON.stringify(value));
    };

    const isSystemName = (name) => ['view', 'page', 'csrf_token', 'export_csv', 'export'].includes(name);

    const controlValue = (control) => {
        if (control instanceof HTMLSelectElement && control.multiple) {
            return Array.from(control.options)
                .filter((opt) => opt.selected && String(opt.value || '').trim() !== '')
                .map((opt) => String(opt.value));
        }
        if (control instanceof HTMLInputElement) {
            if (control.type === 'checkbox') {
                return control.checked ? '1' : '0';
            }
            if (control.type === 'radio') {
                return control.checked ? String(control.value || '1') : null;
            }
        }
        return String(control.value ?? '');
    };

    const applyControlValue = (control, value) => {
        if (control instanceof HTMLSelectElement && control.multiple) {
            const wanted = new Set(Array.isArray(value) ? value.map(String) : []);
            let changed = false;
            Array.from(control.options).forEach((opt) => {
                const nextSelected = wanted.has(String(opt.value));
                if (opt.selected !== nextSelected) {
                    opt.selected = nextSelected;
                    changed = true;
                }
            });
            return changed;
        }
        if (control instanceof HTMLInputElement) {
            if (control.type === 'checkbox') {
                const nextChecked = String(value) === '1';
                if (control.checked !== nextChecked) {
                    control.checked = nextChecked;
                    return true;
                }
                return false;
            }
            if (control.type === 'radio') {
                const nextChecked = String(control.value) === String(value);
                if (control.checked !== nextChecked) {
                    control.checked = nextChecked;
                    return true;
                }
                return false;
            }
        }
        const nextValue = Array.isArray(value) ? (value[0] ?? '') : String(value ?? '');
        if (String(control.value ?? '') !== nextValue) {
            control.value = nextValue;
            return true;
        }
        return false;
    };

    const sameValue = (a, b) => JSON.stringify(a) === JSON.stringify(b);

    document.querySelectorAll('form.table-filters').forEach((form, formIndex) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const layoutKey = `thc_form_layout_v1_${view}_${formIndex}`;
        const filterKey = `thc_form_filters_v1_${view}_${formIndex}`;

        const columnPrefixes = Array.from(form.querySelectorAll('.visible-columns'))
            .map((details) => details.getAttribute('data-col-prefix') || '')
            .filter(Boolean);
        const orderFields = Array.from(form.querySelectorAll('.visible-columns'))
            .map((details) => details.getAttribute('data-order-field') || '')
            .filter(Boolean);

        const allControls = () => Array.from(form.elements).filter((el) =>
            (el instanceof HTMLInputElement || el instanceof HTMLSelectElement || el instanceof HTMLTextAreaElement) &&
            normalizeName(el.name) !== '' &&
            !isSystemName(normalizeName(el.name))
        );

        const isLayoutControl = (name) => {
            if (name === 'page_size') {
                return true;
            }
            if (orderFields.includes(name)) {
                return true;
            }
            return columnPrefixes.some((prefix) => name.startsWith(prefix));
        };

        const serializeGroup = (wantLayout) => {
            const payload = {};
            allControls().forEach((control) => {
                const name = normalizeName(control.name);
                if (isLayoutControl(name) !== wantLayout) {
                    return;
                }
                if (control instanceof HTMLInputElement && control.type === 'radio' && !control.checked) {
                    return;
                }
                payload[name] = controlValue(control);
            });
            return payload;
        };

        const persistAll = () => {
            safeWrite(layoutKey, serializeGroup(true));
            safeWrite(filterKey, serializeGroup(false));
        };

        if (resetRequested) {
            localStorage.removeItem(filterKey);
        }

        const savedLayout = safeRead(layoutKey);
        const savedFilters = safeRead(filterKey);
        let changedByRestore = false;

        allControls().forEach((control) => {
            const name = normalizeName(control.name);
            const bucket = isLayoutControl(name) ? savedLayout : savedFilters;
            if (!Object.prototype.hasOwnProperty.call(bucket, name)) {
                return;
            }
            const before = controlValue(control);
            const changed = applyControlValue(control, bucket[name]);
            if (changed && !sameValue(before, controlValue(control))) {
                changedByRestore = true;
            }
        });

        allControls().forEach((control) => {
            const eventName = (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement) ? 'input' : 'change';
            control.addEventListener(eventName, persistAll);
            control.addEventListener('change', persistAll);
        });

        form.querySelectorAll('.btn-reset-cols').forEach((btn) => {
            btn.addEventListener('click', () => {
                window.setTimeout(persistAll, 0);
            });
        });

        form.querySelectorAll('.btn-link').forEach((link) => {
            if (!(link instanceof HTMLAnchorElement)) {
                return;
            }
            const text = (link.textContent || '').trim().toLowerCase();
            if (text !== 'limpiar') {
                return;
            }
            link.addEventListener('click', () => {
                localStorage.removeItem(filterKey);
            });
        });

        persistAll();

        const syncFlag = `thc_form_sync_once_${view}_${formIndex}`;
        if (changedByRestore && sessionStorage.getItem(syncFlag) !== '1') {
            sessionStorage.setItem(syncFlag, '1');
            const pageField = form.querySelector('input[name="page"]');
            if (pageField instanceof HTMLInputElement) {
                pageField.value = '1';
            }
            form.submit();
            return;
        }
        if (!changedByRestore) {
            sessionStorage.removeItem(syncFlag);
        }
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
    openBtn.style.display = 'flex';
openBtn.style.alignItems = 'center';
openBtn.style.justifyContent = 'center';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'subtables-mini-btn';
    closeBtn.innerHTML = '&#8593;';
    closeBtn.title = 'Plegar subtablas';
    closeBtn.style.display = 'flex';
closeBtn.style.alignItems = 'center';
closeBtn.style.justifyContent = 'center';

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
        if (table instanceof HTMLTableElement && table.dataset.staticTable === '1') {
            return;
        }
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
    const normalize = (value) => String(value || '').replace(/\s+/g, ' ').trim();

    const parseNumber = (raw) => {
        let value = normalize(raw);
        if (value === '') {
            return null;
        }
        value = value.replace(/[%]/g, '');
        value = value.replace(/[^\d,.\-]/g, '');
        if (value === '' || !/\d/.test(value)) {
            return null;
        }
        if (value.includes(',') && value.includes('.')) {
            if (value.lastIndexOf(',') > value.lastIndexOf('.')) {
                value = value.replace(/\./g, '').replace(',', '.');
            } else {
                value = value.replace(/,/g, '');
            }
        } else if (value.includes(',')) {
            value = value.replace(',', '.');
        }
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const centerKeyPattern = /(?:^|_)(gender|ethical|icon|icons|scope|visor|platform|posture|pose|singleplayer|enabled|status|ok|reward_position|prize_position|medal|badge)(?:$|_)/i;
    const rightKeyPattern = /(?:^|_)(id|rank|score|distance|distancia|peso|weight|hits|hit|kills|kill|shots|shot|amount|value|count|integrity|harvest|time|duration|x|y|z|min|max|avg|position|timestamp|money|cash|price|puntuacion)(?:$|_)/i;

    const hasInteractiveContent = (cell) =>
        cell.querySelector(':scope > details > summary, button, a.btn-link, input:not([type="checkbox"]), select, textarea');

    const isCenteredWidget = (cell) =>
        cell.querySelector('input[type="checkbox"], .species-gender-icon, img, svg, .medal-icon, .ethical-icon');

    const detectCellKind = (cell) => {
        if (!(cell instanceof HTMLTableCellElement)) {
            return 'left';
        }
        if (hasInteractiveContent(cell)) {
            return 'left';
        }
        const text = normalize(cell.dataset.sortValue || cell.dataset.num || cell.textContent || '');
        if (isCenteredWidget(cell)) {
            return 'center';
        }
        if (/^(si|no|sí|true|false|ok|x|✓|✔|✅|m|f)$/iu.test(text)) {
            return 'center';
        }
        if (text !== '' && parseNumber(text) !== null) {
            return 'right';
        }
        return 'left';
    };

    const classifyColumn = (table, colIndex) => {
        const headerCell = table.tHead?.rows[table.tHead.rows.length - 1]?.cells[colIndex];
        const colKey = normalize(headerCell instanceof HTMLTableCellElement ? headerCell.dataset.colKey || '' : '');
        if (colKey !== '') {
            if (rightKeyPattern.test(colKey)) {
                return 'right';
            }
            if (centerKeyPattern.test(colKey)) {
                return 'center';
            }
        }

        const kinds = [];
        Array.from(table.tBodies).forEach((tbody) => {
            Array.from(tbody.rows).forEach((row) => {
                const cell = row.cells[colIndex];
                if (!(cell instanceof HTMLTableCellElement)) {
                    return;
                }
                if (hasInteractiveContent(cell)) {
                    kinds.push('left');
                    return;
                }
                const text = normalize(cell.textContent || '');
                if (text === '' && !isCenteredWidget(cell)) {
                    return;
                }
                kinds.push(detectCellKind(cell));
            });
        });

        if (kinds.length === 0) {
            return 'left';
        }
        if (kinds.every((kind) => kind === 'center')) {
            return 'center';
        }
        if (kinds.every((kind) => kind === 'right')) {
            return 'right';
        }
        return 'left';
    };

    const applyColumnAlignment = (table) => {
        if (!(table instanceof HTMLTableElement) || !(table.tHead instanceof HTMLTableSectionElement) || table.tHead.rows.length === 0) {
            return;
        }
        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        if (!(headerRow instanceof HTMLTableRowElement)) {
            return;
        }

        Array.from(headerRow.cells).forEach((headerCell, colIndex) => {
            if (!(headerCell instanceof HTMLTableCellElement)) {
                return;
            }
            const align = classifyColumn(table, colIndex);
            const className = `col-align-${align}`;
            headerCell.classList.remove('col-align-left', 'col-align-center', 'col-align-right');
            headerCell.classList.add(className);
            headerCell.style.textAlign = '';

            Array.from(table.rows).forEach((row) => {
                if (!(row instanceof HTMLTableRowElement)) {
                    return;
                }
                const cell = row.cells[colIndex];
                if (!(cell instanceof HTMLTableCellElement)) {
                    return;
                }
                cell.classList.remove('col-align-left', 'col-align-center', 'col-align-right');
                cell.classList.add(className);
                cell.style.textAlign = '';
            });
        });
    };

    document.querySelectorAll('.content table').forEach((table) => {
        if (table instanceof HTMLTableElement && table.dataset.staticTable === '1') {
            return;
        }
        applyColumnAlignment(table);
    });
})();
</script>
<script>
(() => {
    const view = <?= json_encode($view, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (view !== 'expeditions') {
        return;
    }

    document.body.classList.add('view-expeditions-initializing');

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
    const ensureExpHitsLayout = (expDetails) => {
        if (!(expDetails instanceof HTMLDetailsElement)) {
            return null;
        }

        const existing = expDetails.querySelector(':scope > .exp-kills-layout');
        if (existing instanceof HTMLDivElement) {
            return existing;
        }

        const killsTable = expDetails.querySelector(':scope > table');
        if (!(killsTable instanceof HTMLTableElement)) {
            return null;
        }

        const layout = document.createElement('div');
        layout.className = 'exp-kills-layout';

        const left = document.createElement('div');
        left.className = 'exp-kills-left';

        const side = document.createElement('div');
        side.className = 'exp-hits-sidepanel';

        killsTable.replaceWith(layout);
        left.appendChild(killsTable);
        layout.appendChild(left);
        layout.appendChild(side);

        return layout;
    };

    const wireHitsSidePanel = (details) => {
        const expDetails = details.closest('.exp-kills-details');
        const layout = ensureExpHitsLayout(expDetails);
        if (!(layout instanceof HTMLDivElement)) {
            return;
        }

        const sidePanel = layout.querySelector(':scope > .exp-hits-sidepanel');
        if (!(sidePanel instanceof HTMLDivElement)) {
            return;
        }

        let home = details.querySelector(':scope > .kill-hits-home');
        if (!(home instanceof HTMLDivElement)) {
            home = document.createElement('div');
            home.className = 'kill-hits-home';
            home.style.display = 'none';
            details.appendChild(home);
        }

        const directTable = details.querySelector(':scope > table');
        if (directTable instanceof HTMLTableElement) {
            home.appendChild(directTable);
        }

        const hitTable = home.querySelector(':scope > table');
        if (!(hitTable instanceof HTMLTableElement)) {
            return;
        }

        const sync = () => {
            if (details.open) {
                expDetails.querySelectorAll('.kill-hits-details[open]').forEach((other) => {
                    if (other !== details) {
                        other.open = false;
                    }
                });
                sidePanel.replaceChildren(hitTable);
                sidePanel.classList.add('is-visible');
                return;
            }

            if (sidePanel.contains(hitTable)) {
                home.appendChild(hitTable);
            }
            if (!expDetails.querySelector('.kill-hits-details[open]')) {
                sidePanel.classList.remove('is-visible');
                sidePanel.replaceChildren();
            }
        };

        details.addEventListener('toggle', sync);
        sync();
        details.dataset.wiredSubrow = '1';
    };

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

        if (details.classList.contains('kill-hits-details')) {
            wireHitsSidePanel(details);
            return;
        }

        const nestedContent = details.querySelector(':scope > table, :scope > .subtable-panels');
        if (!(nestedContent instanceof HTMLElement)) {
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
        containerCell.appendChild(nestedContent);
        subRow.appendChild(containerCell);

        const nextDataRowBeforeInsert = ownerRow.nextElementSibling;
        const parentTable = ownerRow.closest('table');
        const parentHeaderRow = parentTable && parentTable.tHead ? parentTable.tHead.querySelector('tr') : null;
        let repeatedHeaderRow = null;
        if (isExpSubtable && nestedContent instanceof HTMLTableElement && parentHeaderRow && nextDataRowBeforeInsert instanceof HTMLTableRowElement) {
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
            subRow.classList.toggle('is-subtable-open', details.open);
            if (repeatedHeaderRow) {
                repeatedHeaderRow.style.display = details.open ? '' : 'none';
            }
            details.dispatchEvent(new CustomEvent('thc:subtable-toggle', { bubbles: true }));
        };
        details.addEventListener('toggle', sync);
        sync();
        details.dataset.wiredSubrow = '1';
    };

    const syncCompetitionSubtableShift = () => {
        document.querySelectorAll('.competition-entry').forEach((entry) => {
            if (!(entry instanceof HTMLElement)) {
                return;
            }

            const mainTable = entry.querySelector('.competition-entry-main');
            const detail = entry.querySelector('.competition-entry-detail');
            if (!(mainTable instanceof HTMLTableElement) || !(detail instanceof HTMLElement)) {
                entry.style.setProperty('--competition-detail-indent', '0px');
                return;
            }

            const firstBodyRow = mainTable.tBodies.length > 0 ? mainTable.tBodies[0].rows[0] : null;
            if (!(firstBodyRow instanceof HTMLTableRowElement)) {
                entry.style.setProperty('--competition-detail-indent', '0px');
                return;
            }

            const secondCell = firstBodyRow.cells.length > 1 ? firstBodyRow.cells[1] : null;
            if (!(secondCell instanceof HTMLTableCellElement)) {
                entry.style.setProperty('--competition-detail-indent', '0px');
                return;
            }

            const shift = Math.max(0, secondCell.getBoundingClientRect().left - entry.getBoundingClientRect().left);
            entry.style.setProperty('--competition-detail-indent', `${shift}px`);
        });
    };

    document.querySelectorAll('.content details').forEach(wireDetailsToSubRow);
    syncCompetitionSubtableShift();
    window.addEventListener('resize', syncCompetitionSubtableShift);
    const isInteractiveTarget = (el) => {
        if (!(el instanceof Element)) {
            return false;
        }
        return !!el.closest('a,button,input,select,textarea,label,summary');
    };

    if (view === 'competitions') {
        window.thcExpeditionsSubtablesReady = true;
        return;
    }

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
            ev.preventDefault();
            details.open = !details.open;
        });
    });

    window.thcExpeditionsSubtablesReady = true;
    document.dispatchEvent(new CustomEvent('thc:expeditions-subtables-ready'));
})();
</script>
<script>
(() => {
    const nav = document.querySelector('.sidebar .nav');
    if (!(nav instanceof HTMLElement)) {
        return;
    }

    const storageKey = 'thc_sidebar_nav_order';
    const navLinks = () => Array.from(nav.querySelectorAll('.nav-link[data-nav-key]'));

    const applySavedOrder = () => {
        try {
            const pref = window.thcReadGlobalPref ? window.thcReadGlobalPref(storageKey) : {};
            const saved = Array.isArray(pref.order) ? pref.order : [];
            if (!Array.isArray(saved)) {
                return;
            }
            const map = new Map(navLinks().map((link) => [String(link.getAttribute('data-nav-key') || ''), link]));
            saved.forEach((key) => {
                const link = map.get(String(key));
                if (link) {
                    nav.appendChild(link);
                    map.delete(String(key));
                }
            });
            map.forEach((link) => {
                nav.appendChild(link);
            });
        } catch (_) {}
    };

    const saveOrder = () => {
        try {
            const order = navLinks()
                .map((link) => String(link.getAttribute('data-nav-key') || '').trim())
                .filter((key) => key !== '');
            if (window.thcWriteGlobalPref) {
                window.thcWriteGlobalPref(storageKey, {order});
            }
        } catch (_) {}
    };

    const clearDropState = () => {
        navLinks().forEach((link) => {
            link.classList.remove('dragging', 'nav-drop-before', 'nav-drop-after');
        });
    };

    applySavedOrder();

    let draggedLink = null;
    navLinks().forEach((link) => {
        link.addEventListener('dragstart', () => {
            draggedLink = link;
            clearDropState();
            link.classList.add('dragging');
            if (link instanceof HTMLElement) {
                link.dataset.draggingNav = '1';
            }
        });

        link.addEventListener('dragover', (event) => {
            event.preventDefault();
            if (!(draggedLink instanceof HTMLElement) || draggedLink === link) {
                return;
            }
            const rect = link.getBoundingClientRect();
            const placeBefore = event.clientY < (rect.top + rect.height / 2);
            link.classList.toggle('nav-drop-before', placeBefore);
            link.classList.toggle('nav-drop-after', !placeBefore);
        });

        link.addEventListener('dragleave', () => {
            link.classList.remove('nav-drop-before', 'nav-drop-after');
        });

        link.addEventListener('drop', (event) => {
            event.preventDefault();
            if (!(draggedLink instanceof HTMLElement) || draggedLink === link) {
                clearDropState();
                return;
            }
            const rect = link.getBoundingClientRect();
            const placeBefore = event.clientY < (rect.top + rect.height / 2);
            if (placeBefore) {
                nav.insertBefore(draggedLink, link);
            } else {
                nav.insertBefore(draggedLink, link.nextSibling);
            }
            clearDropState();
            saveOrder();
        });

        link.addEventListener('dragend', () => {
            clearDropState();
            if (draggedLink instanceof HTMLElement) {
                delete draggedLink.dataset.draggingNav;
            }
            draggedLink = null;
            saveOrder();
        });
    });

    window.addEventListener('beforeunload', saveOrder);
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
    const layoutKey = `thc_layout_${view}`;
    const ignored = new Set(['view', 'theme', 'flash', 'page', 'sort', 'dir', 'reset', 'export', 'export_csv']);
    const params = new URLSearchParams(window.location.search);
    const forceReset = params.get('reset') === '1';
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
    const hasUserFilters = [...params.keys()].some((name) => !ignored.has(name) && !isLayoutParam(name));

    const readLocalState = () => {
        try {
            const raw = localStorage.getItem(key);
            const state = raw ? JSON.parse(raw) : {};
            return state && typeof state === 'object' && !Array.isArray(state) ? state : {};
        } catch (_) {
            return {};
        }
    };

    const writeLocalState = (state) => {
        try {
            localStorage.setItem(key, JSON.stringify(state));
        } catch (_) {}
    };

    const readGlobalLayout = () => window.thcReadGlobalPref ? window.thcReadGlobalPref(layoutKey) : {};
    const writeGlobalLayout = (state) => {
        if (window.thcWriteGlobalPref) {
            window.thcWriteGlobalPref(layoutKey, state);
        }
    };

    const saveState = () => {
        const state = {};
        const layoutState = {};
        Array.from(form.elements).forEach((el) => {
            if (!el.name || el.name === 'export' || el.name === 'export_csv') {
                return;
            }
            const target = isLayoutParam(el.name) ? layoutState : state;
            if (el instanceof HTMLSelectElement && el.multiple) {
                target[el.name] = Array.from(el.selectedOptions).map((opt) => opt.value);
                return;
            }
            if (el instanceof HTMLInputElement && el.type === 'checkbox') {
                target[el.name] = el.checked ? '1' : '0';
                return;
            }
            target[el.name] = el.value ?? '';
        });
        writeLocalState(state);
        writeGlobalLayout(layoutState);
    };

    if (forceReset) {
        writeLocalState({});

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
            const localState = readLocalState();
            const globalLayout = readGlobalLayout();
            const state = {...globalLayout, ...localState};
            if (Object.keys(state).length > 0) {
                if (hasUserFilters && Object.keys(globalLayout).length > 0) {
                    const next = new URLSearchParams(params.toString());
                    let changed = false;
                    Object.entries(globalLayout).forEach(([name, v]) => {
                        if (!isLayoutParam(name) || params.has(name)) {
                            return;
                        }
                        const values = Array.isArray(v) ? v.map(String) : [String(v ?? '')];
                        values.forEach((val) => {
                            if (val !== '') {
                                next.append(name, val);
                                changed = true;
                            }
                        });
                    });
                    if (changed) {
                        window.location.replace(`?${next.toString()}`);
                        return;
                    }
                }
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
(() => {
    const syncNativeSelect = (select, selectedValues) => {
        const selectedSet = new Set(selectedValues);
        Array.from(select.options).forEach((option) => {
            option.selected = option.value !== '' && selectedSet.has(option.value);
        });
    };

    const positionComboMenu = (details, menu) => {
        if (!(details instanceof HTMLElement) || !(menu instanceof HTMLElement)) {
            return;
        }

        details.classList.remove('combo-open-left', 'combo-open-right');
        menu.style.left = '';
        menu.style.right = '';

        const viewportWidth = document.documentElement.clientWidth || window.innerWidth || 0;
        const detailsRect = details.getBoundingClientRect();
        const preferredWidth = Math.ceil(menu.scrollWidth || menu.offsetWidth || 0);
        const menuWidth = Math.max(preferredWidth, 240);
        const spaceRight = viewportWidth - detailsRect.left;
        const spaceLeft = detailsRect.right;

        if (spaceRight >= menuWidth || spaceRight >= spaceLeft) {
            details.classList.add('combo-open-right');
            menu.style.left = '0';
            menu.style.right = 'auto';
            return;
        }

        details.classList.add('combo-open-left');
        menu.style.left = 'auto';
        menu.style.right = '0';
    };

    const closeOtherCombos = (current) => {
        document.querySelectorAll('.filter-check-combo[open], .filter-single-combo[open]').forEach((other) => {
            if (other !== current) {
                other.open = false;
            }
        });
    };

    const wireCheckCombo = (select) => {
        if (!(select instanceof HTMLSelectElement) || !select.multiple || select.dataset.comboWired === '1') {
            return;
        }

        const placeholder = select.dataset.checkComboPlaceholder || select.options[0]?.textContent?.trim() || 'Seleccionar';
        const manyLabel = select.dataset.checkComboManyLabel || 'especies';
        const colTarget = select.dataset.colTarget || select.name || '';
        const options = Array.from(select.options).filter((option) => option.value !== '');
        if (options.length === 0) {
            return;
        }

        const details = document.createElement('details');
        details.className = 'filter-check-combo';
        details.dataset.colTarget = colTarget;
        details.dataset.fieldName = select.name.replace(/\[\]$/g, '');

        const summary = document.createElement('summary');
        details.appendChild(summary);

        const menu = document.createElement('div');
        menu.className = 'filter-check-combo-menu';

        const actions = document.createElement('div');
        actions.className = 'filter-check-combo-actions';

        const selectAllBtn = document.createElement('button');
        selectAllBtn.type = 'button';
        selectAllBtn.className = 'btn-link';
        selectAllBtn.textContent = 'Todos';

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn-link';
        clearBtn.textContent = 'Limpiar';

        actions.appendChild(selectAllBtn);
        actions.appendChild(clearBtn);
        menu.appendChild(actions);

        const checkboxMap = new Map();
        options.forEach((option) => {
            const label = document.createElement('label');
            label.className = 'filter-check-combo-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = option.value;
            checkbox.checked = option.selected;
            checkbox.dataset.colTarget = colTarget;

            const text = document.createElement('span');
            text.textContent = option.textContent || option.value;

            label.appendChild(checkbox);
            label.appendChild(text);
            menu.appendChild(label);
            checkboxMap.set(option.value, checkbox);
        });

        details.appendChild(menu);

        const updateSummary = () => {
            const selectedLabels = options
                .filter((option) => option.selected)
                .map((option) => (option.textContent || option.value).trim())
                .filter(Boolean);

            if (selectedLabels.length === 0) {
                summary.textContent = placeholder;
                summary.title = summary.dataset.baseTitle ? `${summary.dataset.baseTitle} Actual: ${placeholder}` : placeholder;
                return;
            }

            if (selectedLabels.length <= 2) {
                summary.textContent = selectedLabels.join(', ');
                summary.title = summary.dataset.baseTitle ? `${summary.dataset.baseTitle} Actual: ${summary.textContent}` : summary.textContent;
                return;
            }

            summary.textContent = `${selectedLabels.length} ${manyLabel}`;
            summary.title = summary.dataset.baseTitle ? `${summary.dataset.baseTitle} Actual: ${summary.textContent}` : summary.textContent;
        };

        const dispatchChange = () => {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const applySelection = (selectedValues) => {
            syncNativeSelect(select, selectedValues);
            checkboxMap.forEach((checkbox, value) => {
                checkbox.checked = selectedValues.includes(value);
            });
            updateSummary();
            dispatchChange();
        };

        checkboxMap.forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                const selectedValues = Array.from(checkboxMap.entries())
                    .filter(([, cb]) => cb.checked)
                    .map(([currentValue]) => currentValue);
                applySelection(selectedValues);
            });
        });

        selectAllBtn.addEventListener('click', () => {
            applySelection(options.map((option) => option.value));
        });

        clearBtn.addEventListener('click', () => {
            applySelection([]);
        });

        details.addEventListener('toggle', () => {
            if (!details.open) {
                return;
            }
            closeOtherCombos(details);
            positionComboMenu(details, menu);
        });

        document.addEventListener('click', (event) => {
            if (!details.open) {
                return;
            }
            if (event.target instanceof Node && details.contains(event.target)) {
                return;
            }
            details.open = false;
        });

        select.style.display = 'none';
        select.insertAdjacentElement('afterend', details);
        select.dataset.comboWired = '1';
        details.dataset.filterKey = `combo:${select.name}`;
        updateSummary();
    };

    const wireSingleCombo = (select) => {
        if (!(select instanceof HTMLSelectElement) || select.multiple || select.dataset.singleComboWired === '1') {
            return;
        }

        const options = Array.from(select.options);
        if (options.length === 0) {
            return;
        }

        const placeholder = select.dataset.singleComboPlaceholder || options[0]?.textContent?.trim() || 'Seleccionar';
        const details = document.createElement('details');
        details.className = 'filter-single-combo';
        details.dataset.fieldName = select.name.replace(/\[\]$/g, '');

        const summary = document.createElement('summary');
        details.appendChild(summary);

        const menu = document.createElement('div');
        menu.className = 'filter-single-combo-menu';

        const actions = document.createElement('div');
        actions.className = 'filter-check-combo-actions';

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn-link';
        clearBtn.textContent = 'Limpiar';
        actions.appendChild(clearBtn);
        menu.appendChild(actions);

        const radioName = `single_combo_${select.name}_${Math.random().toString(36).slice(2)}`;
        const radioMap = new Map();
        options.forEach((option) => {
            const label = document.createElement('label');
            label.className = 'filter-single-combo-item';

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = radioName;
            radio.value = option.value;
            radio.checked = option.selected;

            const text = document.createElement('span');
            text.textContent = option.textContent || option.value;

            label.appendChild(radio);
            label.appendChild(text);
            menu.appendChild(label);
            radioMap.set(option.value, radio);
        });

        details.appendChild(menu);

        const updateSummary = () => {
            const selectedOption = options.find((option) => option.selected) || null;
            const text = (selectedOption?.textContent || placeholder).trim();
            summary.textContent = text;
            summary.title = summary.dataset.baseTitle ? `${summary.dataset.baseTitle} Actual: ${text}` : text;
        };

        const dispatchChange = () => {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        };

        const applySelection = (selectedValue) => {
            Array.from(select.options).forEach((option) => {
                option.selected = option.value === selectedValue;
            });
            radioMap.forEach((radio, value) => {
                radio.checked = value === selectedValue;
            });
            updateSummary();
            dispatchChange();
            details.open = false;
        };

        radioMap.forEach((radio, value) => {
            radio.addEventListener('change', () => {
                if (radio.checked) {
                    applySelection(value);
                }
            });
        });

        clearBtn.addEventListener('click', () => {
            const emptyOption = options.find((option) => option.value === '') || options[0];
            applySelection(emptyOption?.value || '');
        });

        details.addEventListener('toggle', () => {
            if (!details.open) {
                return;
            }
            closeOtherCombos(details);
            positionComboMenu(details, menu);
        });

        document.addEventListener('click', (event) => {
            if (!details.open) {
                return;
            }
            if (event.target instanceof Node && details.contains(event.target)) {
                return;
            }
            details.open = false;
        });

        select.style.display = 'none';
        select.insertAdjacentElement('afterend', details);
        select.dataset.singleComboWired = '1';
        details.dataset.filterKey = `single:${select.name}`;
        updateSummary();
    };

    document.querySelectorAll('select[data-check-combo="1"]').forEach((select) => {
        wireCheckCombo(select);
    });
    document.querySelectorAll('select[data-single-combo="1"]').forEach((select) => {
        wireSingleCombo(select);
    });

    window.addEventListener('resize', () => {
        document.querySelectorAll('.filter-check-combo[open], .filter-single-combo[open]').forEach((details) => {
            const menu = details.querySelector(':scope > .filter-check-combo-menu, :scope > .filter-single-combo-menu');
            if (menu instanceof HTMLElement) {
                positionComboMenu(details, menu);
            }
        });
    });
})();

(() => {
    const view = <?= json_encode($view, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const tooltipByName = {
        preset_name_select: 'Preset guardado de la consulta avanzada.',
        table: 'Tabla de datos sobre la que aplicar la consulta.',
        column: 'Columna usada para filtrar.',
        op: 'Operacion aplicada al filtro.',
        value: 'Valor introducido para filtrar.',
        log: 'Archivo de log a visualizar.',
        global_rank: 'Posicion global del jugador o marca.',
        expedition_id: 'Identificador de la expedicion.',
        ts_user_id: 'Identificador interno del usuario.',
        player_name: 'Seleccion de jugadores.',
        reserve_name: 'Seleccion de reservas.',
        reserve_id: 'Identificador interno de la reserva.',
        kill_species_name: 'Seleccion de especies de las muertes.',
        species_name: 'Seleccion de especies.',
        species_name_es: 'Seleccion de especies en espanol.',
        especie: 'Nombre de la especie.',
        type_name: 'Seleccion de tipos de competicion.',
        leaderboard_type: 'Tipo de clasificacion o comparativa.',
        finished: 'Estado de finalizacion.',
        kill_ethical: 'Indica si la muerte fue etica.',
        mark_filter: 'Filtro por tipo de mejor marca.',
        photo_tax_filter: 'Filtro por foto o taxidermia.',
        risk_level: 'Nivel de riesgo calculado.',
        user_id: 'Identificador interno de usuario.',
        competition_id: 'Identificador de la competicion.',
        kill_id: 'Identificador de la muerte.',
        hit_index: 'Identificador o indice del disparo.',
        kill_gender: 'Genero de la pieza abatida.',
        kill_score_min: 'Puntuacion minima de la muerte.',
        kill_score_max: 'Puntuacion maxima de la muerte.',
        kill_distance_min: 'Distancia minima de la muerte.',
        kill_distance_max: 'Distancia maxima de la muerte.',
        kill_weight_min: 'Peso minimo de la muerte.',
        kill_weight_max: 'Peso maximo de la muerte.',
        kill_integrity_min: 'Integridad minima de la muerte.',
        kill_integrity_max: 'Integridad maxima de la muerte.',
        kill_harvest_min: 'Harvest minimo de la muerte.',
        kill_harvest_max: 'Harvest maximo de la muerte.',
        hit_weapon_id: 'Identificador del arma usada en el disparo.',
        hit_ammo_id: 'Identificador de la municion usada en el disparo.',
        hit_organ: 'Organo impactado en el disparo.',
        exp_duration_min: 'Duracion minima de la expedicion.',
        exp_duration_max: 'Duracion maxima de la expedicion.',
        start_at: 'Fecha y hora de inicio.',
        end_at: 'Fecha y hora de fin.',
        date_from: 'Fecha inicial del rango.',
        date_to: 'Fecha final del rango.',
        entrants: 'Numero de participantes.',
        hunter_score: 'Hunter Score del jugador.',
        best_score_value: 'Mejor puntuacion registrada.',
        best_distance_m: 'Mejor distancia registrada.',
        duration: 'Duracion acumulada.',
        distance: 'Distancia acumulada.',
        foto_min: 'Numero minimo de fotos.',
        foto_max: 'Numero maximo de fotos.',
        tax_min: 'Numero minimo de taxidermias.',
        tax_max: 'Numero maximo de taxidermias.',
        peso_max_kg: 'Peso maximo en kg.',
        score_min: 'Puntuacion minima.',
        score_max: 'Puntuacion maxima.',
        animal_species_id: 'Identificador de especie en estadisticas.',
        animal_kills_min: 'Minimo de muertes por especie.',
        weapon_id: 'Identificador de arma.',
        weapon_ammo_id: 'Identificador de municion.',
        weapon_kills_min: 'Minimo de muertes con arma.',
        reward_type: 'Tipo de premio.',
        reward_define: 'Codigo o definicion del premio.',
        prize_position: 'Puesto del premio.',
        attempts: 'Numero de intentos.',
        point_type: 'Modo o tipo de puntuacion.',
        rank_pos: 'Posicion del ranking.',
        value_numeric: 'Valor numerico de puntuacion.',
        distance_m: 'Valor de distancia en metros.',
        snapshot_at: 'Snapshot actual de clasificacion.',
        compare_snapshot_at: 'Snapshot con el que comparar.',
        min_score: 'Riesgo minimo.',
        min_kills: 'Numero minimo de muertes.',
        signals_only: 'Mostrar solo filas con senales.',
        page_size: 'Numero de filas por pagina.',
        xml_rows: 'Numero maximo de filas a mostrar.',
        sheet: 'Hoja del XML a visualizar.',
        ts_total_trophies: 'Total de trofeos del usuario.'
    };
    const defaultOrderByView = {
        advanced: [[
            'single:table',
            'single:column',
            'single:op',
            'field:value',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        best: [[
            'field:global_rank',
            'combo:player_name[]',
            'field:hunter_score',
            'combo:species_name_es[]',
            'field:best_score_value',
            'field:best_distance_m',
            'details:Columnas visibles',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        profiles: [[
            'field:global_rank',
            'combo:player_name[]',
            'field:hunter_score',
            'field:duration',
            'field:distance',
            'field:animal_species_id',
            'field:animal_kills_min',
            'field:weapon_id',
            'field:weapon_ammo_id',
            'field:weapon_kills_min',
            'details:Columnas Estadisticas',
            'details:Columnas Estadisticas Especies',
            'details:Columnas Estadisticas Armas',
            'details:Columnas Estadisticas Coleccionables',
            'details:Columnas Estadisticas Misiones',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        competitions: [[
            'field:competition_id',
            'combo:type_name[]',
            'field:entrants',
            'single:finished',
            'field:start_at',
            'field:end_at',
            'combo:species_name_es[]',
            'field:reward_type',
            'field:reward_define',
            'field:prize_position',
            'field:attempts',
            'field:point_type',
            'details:Columnas visibles',
            'details:Columnas Tipo',
            'details:Columnas Especies',
            'details:Columnas Premios',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        classifications: [[
            'single:leaderboard_type',
            'combo:species_name[]',
            'field:rank_pos',
            'combo:player_name[]',
            'field:user_id',
            'field:value_numeric',
            'field:distance_m',
            'details:Columnas visibles',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        classifications_history: [[
            'single:snapshot_at',
            'single:compare_snapshot_at',
            'single:leaderboard_type',
            'field:rank_pos',
            'combo:species_name[]',
            'combo:player_name[]',
            'field:user_id',
            'check:only_changed',
            'details:Columnas visibles',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        cheat_risk: [[
            'field:user_id',
            'combo:player_name[]',
            'single:risk_level',
            'field:min_score',
            'field:min_kills',
            'check:signals_only',
            'details:Columnas Riesgo',
            'details:Columnas Senales',
            'details:Columnas Expediciones Senales',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        hall_of_fame: [[
            'combo:species_name[]',
            'details:Columnas visibles',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        trophies_summary: [[
            'combo:player_name[]',
            'details:Columnas visibles',
            'details:Filtros Detalle',
            'details:Columnas Detalle',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        expeditions: [[
            'field:expedition_id',
            'field:user_id',
            'combo:player_name[]',
            'combo:reserve_name[]',
            'field:start_at',
            'field:end_at',
            'combo:kill_species_name[]',
            'field:kill_id',
            'field:hit_index',
            'single:kill_ethical',
            'single:mark_filter',
            'single:photo_tax_filter',
            'field:date_from',
            'field:date_to',
            'details:Columnas Expediciones',
            'details:Columnas de Muertes',
            'details:Columnas de Disparos',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        species_ppft: [[
            'field:especie',
            'field:foto_min',
            'field:foto_max',
            'field:tax_min',
            'field:tax_max',
            'field:peso_max_kg',
            'field:score_min',
            'field:score_max',
            'details:Columnas visibles',
            'field:page_size',
            'button:Filtrar',
            'button:Exportar CSV'
        ]],
        logs: [[
            'single:log',
            'button:Ver'
        ]]
    };
    const humanizeName = (value) => String(value || '')
        .replace(/\[\]/g, '')
        .replace(/_/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    const nodeKey = (node) => {
        if (!(node instanceof HTMLElement)) {
            return null;
        }
        if (node.dataset.filterKey) {
            return node.dataset.filterKey;
        }
        if (node instanceof HTMLInputElement || node instanceof HTMLSelectElement || node instanceof HTMLTextAreaElement) {
            return node.name ? `field:${node.name}` : null;
        }
        if (node instanceof HTMLDetailsElement) {
            if (node.classList.contains('filter-check-combo')) {
                const prev = node.previousElementSibling;
                if (prev instanceof HTMLSelectElement && prev.name) {
                    return `combo:${prev.name}`;
                }
            }
            const summary = node.querySelector(':scope > summary');
            return `details:${(summary?.textContent || '').trim()}`;
        }
        if (node instanceof HTMLButtonElement) {
            return `button:${(node.textContent || '').trim()}`;
        }
        if (node instanceof HTMLAnchorElement) {
            return `link:${node.getAttribute('href') || (node.textContent || '').trim()}`;
        }
        if (node.matches('label.inline-check')) {
            const input = node.querySelector('input');
            if (input instanceof HTMLInputElement && input.name) {
                return `check:${input.name}`;
            }
            return `label:${(node.textContent || '').trim()}`;
        }
        return null;
    };

    const applyTooltip = (node) => {
        if (!(node instanceof HTMLElement)) {
            return;
        }
        if (node instanceof HTMLInputElement || node instanceof HTMLTextAreaElement) {
            if (!node.title) {
                node.title = tooltipByName[node.name.replace(/\[\]$/g, '')] || node.placeholder || humanizeName(node.name);
            }
            return;
        }
        if (node instanceof HTMLSelectElement) {
            if (!node.title) {
                const selected = node.selectedOptions.length > 0
                    ? Array.from(node.selectedOptions).map((opt) => opt.textContent || '').join(', ').trim()
                    : '';
                node.title = tooltipByName[node.name.replace(/\[\]$/g, '')] || selected || node.options[0]?.textContent?.trim() || humanizeName(node.name);
            }
            return;
        }
        if (node instanceof HTMLDetailsElement) {
            const summary = node.querySelector(':scope > summary');
            if (summary instanceof HTMLElement && !summary.dataset.baseTitle) {
                const fieldName = (node.dataset.fieldName || '').replace(/\[\]$/g, '');
                const baseTitle = tooltipByName[fieldName] || (summary.textContent || '').trim();
                summary.dataset.baseTitle = baseTitle;
                if (!summary.title) {
                    summary.title = baseTitle;
                }
            }
            return;
        }
        if (node.matches('label.inline-check')) {
            const input = node.querySelector('input');
            if (input instanceof HTMLInputElement && !node.title) {
                node.title = tooltipByName[input.name.replace(/\[\]$/g, '')] || (node.textContent || '').trim();
            }
            return;
        }
        if ((node instanceof HTMLButtonElement || node instanceof HTMLAnchorElement) && !node.title) {
            node.title = (node.textContent || '').trim();
        }
    };

    document.querySelectorAll('.content form.table-filters').forEach((form, formIndex) => {
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const storageKey = `thc_filter_order_v2_${view}_${formIndex}`;
        const filterNodes = () => Array.from(form.children).filter((node) => {
            if (!(node instanceof HTMLElement)) {
                return false;
            }
            if (node instanceof HTMLInputElement && node.type === 'hidden') {
                return false;
            }
            if (node instanceof HTMLSelectElement && node.style.display === 'none') {
                return false;
            }
            return nodeKey(node) !== null;
        });

        const saveOrder = () => {
            try {
                localStorage.setItem(storageKey, JSON.stringify(
                    filterNodes().map((node) => nodeKey(node)).filter((key) => typeof key === 'string' && key !== '')
                ));
            } catch (_) {}
        };

        const clearState = () => {
            filterNodes().forEach((node) => {
                node.classList.remove('filter-drop-before', 'filter-drop-after', 'is-dragging', 'filter-reorder-item');
            });
        };

        const wireNode = (node) => {
            if (!(node instanceof HTMLElement)) {
                return;
            }
            applyTooltip(node);
            node.classList.add('filter-reorder-item');
            node.draggable = true;
        };

        const applySavedOrder = () => {
            try {
                const raw = localStorage.getItem(storageKey);
                const defaultOrder = defaultOrderByView[view]?.[formIndex] || [];
                if (!raw) {
                    if (defaultOrder.length === 0) {
                        return;
                    }
                    const map = new Map(filterNodes().map((node) => [nodeKey(node), node]));
                    defaultOrder.forEach((key) => {
                        const current = map.get(key);
                        if (current) {
                            form.appendChild(current);
                            map.delete(key);
                        }
                    });
                    map.forEach((node) => {
                        form.appendChild(node);
                    });
                    return;
                }
                const saved = JSON.parse(raw);
                if (!Array.isArray(saved)) {
                    return;
                }
                const map = new Map(filterNodes().map((node) => [nodeKey(node), node]));
                saved.forEach((key) => {
                    const current = map.get(key);
                    if (current) {
                        form.appendChild(current);
                        map.delete(key);
                    }
                });
                map.forEach((node) => {
                    form.appendChild(node);
                });
            } catch (_) {}
        };

        let dragged = null;
        applySavedOrder();
        filterNodes().forEach(wireNode);

        filterNodes().forEach((node) => {
            node.addEventListener('dragstart', () => {
                dragged = node;
                clearState();
                node.classList.add('is-dragging');
            });

            node.addEventListener('dragover', (event) => {
                event.preventDefault();
                if (!(dragged instanceof HTMLElement) || dragged === node) {
                    return;
                }
                const rect = node.getBoundingClientRect();
                const before = event.clientY < (rect.top + rect.height / 2);
                node.classList.toggle('filter-drop-before', before);
                node.classList.toggle('filter-drop-after', !before);
            });

            node.addEventListener('dragleave', () => {
                node.classList.remove('filter-drop-before', 'filter-drop-after');
            });

            node.addEventListener('drop', (event) => {
                event.preventDefault();
                if (!(dragged instanceof HTMLElement) || dragged === node) {
                    clearState();
                    return;
                }
                const rect = node.getBoundingClientRect();
                const before = event.clientY < (rect.top + rect.height / 2);
                if (before) {
                    form.insertBefore(dragged, node);
                } else {
                    form.insertBefore(dragged, node.nextSibling);
                }
                clearState();
                filterNodes().forEach(wireNode);
                saveOrder();
            });

            node.addEventListener('dragend', () => {
                clearState();
                dragged = null;
            });
        });
    });

    document.querySelectorAll('.content .visible-item span, .content form.table-filters details > summary').forEach((node) => {
        if (node instanceof HTMLElement && !node.title) {
            node.title = (node.textContent || '').trim();
        }
    });
})();

(() => {
    const view = <?= json_encode($view, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    if (view !== 'trophies_summary') {
        return;
    }

    const closeAll = () => {
        document.querySelectorAll('.trophy-summary-detail-row').forEach((row) => {
            if (!(row instanceof HTMLTableRowElement)) {
                return;
            }

            row.style.display = 'none';
            row.dataset.activeMedalType = '';
            row.querySelectorAll('.trophy-summary-panel').forEach((panel) => {
                if (panel instanceof HTMLElement) {
                    panel.style.display = 'none';
                }
            });
        });

        document.querySelectorAll('.trophy-count-toggle.is-active').forEach((btn) => {
            btn.classList.remove('is-active');
        });
    };

    document.querySelectorAll('.trophy-count-toggle').forEach((btn) => {
        btn.addEventListener('click', () => {
            if (!(btn instanceof HTMLButtonElement)) {
                return;
            }

            const userId = btn.getAttribute('data-user-id') || '';
            const medalType = btn.getAttribute('data-medal-type') || '';
            const row = document.querySelector(`.trophy-summary-detail-row[data-user-id="${userId}"]`);
            if (!(row instanceof HTMLTableRowElement)) {
                return;
            }

            const sameOpen = row.style.display !== 'none' && row.dataset.activeMedalType === medalType;
            closeAll();
            if (sameOpen) {
                return;
            }

            row.style.display = '';
            row.dataset.activeMedalType = medalType;
            const panel = row.querySelector(`.trophy-summary-panel[data-medal-type="${medalType}"]`);
            if (panel instanceof HTMLElement) {
                panel.style.display = 'block';
            }

            btn.classList.add('is-active');
        });
    });
})();

(() => {
    const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

    const parseSortableNumber = (raw) => {
        let value = String(raw || '').trim();
        if (value === '') {
            return null;
        }
        value = value.replace(/\s+/g, '');
        value = value.replace(/[^0-9,.\-]/g, '');
        if (value === '' || !/\d/.test(value)) {
            return null;
        }
        if (value.includes(',') && value.includes('.')) {
            if (value.lastIndexOf(',') > value.lastIndexOf('.')) {
                value = value.replace(/\./g, '').replace(',', '.');
            } else {
                value = value.replace(/,/g, '');
            }
        } else if (value.includes(',')) {
            value = value.replace(',', '.');
        }
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const parseSortableDate = (raw) => {
        const value = String(raw || '').trim();
        const match = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!match) {
            return null;
        }
        const [, dd, mm, yyyy, hh = '0', mi = '0', ss = '0'] = match;
        return Date.UTC(Number(yyyy), Number(mm) - 1, Number(dd), Number(hh), Number(mi), Number(ss));
    };

    const cellComparable = (row, colIndex) => {
        const cell = row.cells[colIndex];
        if (!(cell instanceof HTMLTableCellElement)) {
            return { empty: true, kind: 'string', value: '' };
        }
        const raw = String(cell.dataset.sortValue || cell.dataset.num || cell.textContent || '').replace(/\s+/g, ' ').trim();
        if (raw === '') {
            return { empty: true, kind: 'string', value: '' };
        }
        const dateValue = parseSortableDate(raw);
        if (dateValue !== null) {
            return { empty: false, kind: 'number', value: dateValue };
        }
        const numValue = parseSortableNumber(raw);
        if (numValue !== null) {
            return { empty: false, kind: 'number', value: numValue };
        }
        return { empty: false, kind: 'string', value: raw };
    };

    const sortTableBody = (table, colIndex, dir) => {
        const tbody = table.tBodies[0];
        if (!(tbody instanceof HTMLTableSectionElement)) {
            return;
        }

        const decorated = Array.from(tbody.rows).map((row, idx) => ({
            row,
            idx,
            comparable: cellComparable(row, colIndex),
        }));

        decorated.sort((a, b) => {
            if (a.comparable.empty && b.comparable.empty) {
                return a.idx - b.idx;
            }
            if (a.comparable.empty) {
                return 1;
            }
            if (b.comparable.empty) {
                return -1;
            }

            let result = 0;
            if (a.comparable.kind === 'number' && b.comparable.kind === 'number') {
                result = a.comparable.value - b.comparable.value;
            } else {
                result = collator.compare(String(a.comparable.value), String(b.comparable.value));
            }
            if (result === 0) {
                result = a.idx - b.idx;
            }
            return dir === 'desc' ? -result : result;
        });

        decorated.forEach((item) => {
            tbody.appendChild(item.row);
        });
    };

    const clearHeaderState = (table) => {
        table.querySelectorAll('.client-sortable-th').forEach((th) => {
            th.classList.remove('is-asc', 'is-desc');
        });
    };

    const wireClientSortableTable = (table) => {
        if (!(table instanceof HTMLTableElement) || table.dataset.clientSortableWired === '1') {
            return;
        }
        if (!(table.tHead instanceof HTMLTableSectionElement) || !(table.tBodies[0] instanceof HTMLTableSectionElement)) {
            return;
        }
        if (table.tBodies[0].rows.length < 2) {
            return;
        }
        if (table.tHead.querySelector('.th-sort')) {
            return;
        }

        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        if (!(headerRow instanceof HTMLTableRowElement)) {
            return;
        }

        Array.from(headerRow.cells).forEach((cell, idx) => {
            if (!(cell instanceof HTMLTableCellElement)) {
                return;
            }
            if (cell.colSpan !== 1) {
                return;
            }
            cell.classList.add('client-sortable-th');
            cell.title = 'Ordenar';
            cell.addEventListener('click', () => {
                const nextDir = table.dataset.clientSortCol === String(idx) && table.dataset.clientSortDir === 'asc' ? 'desc' : 'asc';
                table.dataset.clientSortCol = String(idx);
                table.dataset.clientSortDir = nextDir;
                clearHeaderState(table);
                cell.classList.add(nextDir === 'asc' ? 'is-asc' : 'is-desc');
                sortTableBody(table, idx, nextDir);
            });
        });

        table.dataset.clientSortableWired = '1';
    };

    document.querySelectorAll('.content table').forEach((table) => {
        if (table instanceof HTMLTableElement && table.dataset.staticTable === '1') {
            return;
        }
        wireClientSortableTable(table);
    });
})();

(() => {
    const view = new URLSearchParams(window.location.search).get('view') || 'panel';
    const renameStorageKey = 'thc_table_header_labels_v1';
    const orderStorageKey = 'thc_table_column_order_v1';
    const widthStorageKey = 'thc_table_column_widths_v1';

    const safeReadJson = (key) => {
        try {
            if (window.thcReadGlobalPref) {
                return window.thcReadGlobalPref(key);
            }
            return {};
        } catch (_) {
            return {};
        }
    };

    const readRenameMap = () => safeReadJson(renameStorageKey);
    const writeRenameMap = (data) => {
        if (window.thcWriteGlobalPref) {
            window.thcWriteGlobalPref(renameStorageKey, data);
        }
    };
    const readOrderMap = () => safeReadJson(orderStorageKey);
    const writeOrderMap = (data) => {
        if (window.thcWriteGlobalPref) {
            window.thcWriteGlobalPref(orderStorageKey, data);
        }
    };
    const readWidthMap = () => safeReadJson(widthStorageKey);
    const writeWidthMap = (data) => {
        if (window.thcWriteGlobalPref) {
            window.thcWriteGlobalPref(widthStorageKey, data);
        }
    };

    const normalizeText = (value) => String(value || '').replace(/\s+/g, ' ').trim();

    const stableHeaderKey = (th, fallbackIndex = 0) => {
        if (!(th instanceof HTMLTableCellElement)) {
            return `col_${fallbackIndex}`;
        }
        const explicitKey = normalizeText(th.dataset.colKey || '');
        if (explicitKey !== '') {
            return explicitKey;
        }
        const sortLink = th.querySelector(':scope .th-sort');
        if (sortLink instanceof HTMLAnchorElement) {
            try {
                const url = new URL(sortLink.getAttribute('href') || '', window.location.href);
                const sortKey = normalizeText(url.searchParams.get('sort') || '');
                const killSortKey = normalizeText(url.searchParams.get('k_sort') || '');
                const hitSortKey = normalizeText(url.searchParams.get('h_sort') || '');
                const prefixedKey = killSortKey !== '' ? `k_${killSortKey}` : (hitSortKey !== '' ? `h_${hitSortKey}` : sortKey);
                if (prefixedKey !== '') {
                    return prefixedKey;
                }
            } catch (_) {}
        }
        const defaultLabel = normalizeText(th.dataset.defaultHeaderLabel || '');
        const labelText = normalizeText(th.querySelector(':scope > .th-label-text')?.textContent || th.textContent || '')
            .replace(/[↑↓↕]/g, '')
            .trim();
        return defaultLabel || labelText || `col_${fallbackIndex}`;
    };

    const tablePersistId = (table, tableIndex) => {
        if (table.dataset.persistTableId) {
            return table.dataset.persistTableId;
        }
        const card = table.closest('.card');
        const cardTitle = normalizeText(card?.querySelector('h2')?.textContent || '');
        const details = table.closest('details');
        const explicitScopeParts = [];
        if (details instanceof HTMLElement) {
            const expId = normalizeText(details.getAttribute('data-exp-id') || '');
            const killId = normalizeText(details.getAttribute('data-kill-id') || '');
            const userId = normalizeText(details.getAttribute('data-user-id') || '');
            const medalType = normalizeText(details.getAttribute('data-medal-type') || '');
            const scoreType = normalizeText(details.getAttribute('data-score-type') || '');
            if (expId !== '') {
                explicitScopeParts.push(`exp:${expId}`);
            }
            if (killId !== '') {
                explicitScopeParts.push(`kill:${killId}`);
            }
            if (userId !== '') {
                explicitScopeParts.push(`user:${userId}`);
            }
            if (medalType !== '') {
                explicitScopeParts.push(`medal:${medalType}`);
            }
            if (scoreType !== '') {
                explicitScopeParts.push(`score:${scoreType}`);
            }
        }
        const wrapperTitle = explicitScopeParts.join('|') || normalizeText(
            details?.querySelector(':scope > summary')?.textContent ||
            table.closest('.subtable-panels > details')?.querySelector(':scope > summary')?.textContent ||
            ''
        ).replace(/\(\d+\)\s*$/u, '').trim();
        const sectionHint = normalizeText(
            table.closest('.exp-kills-details') ? 'exp-kills' :
            table.closest('.kill-hits-details') ? 'kill-hits' :
            table.closest('.subtable-panels') ? 'subtable' :
            'main'
        );
        const id = [view, cardTitle || 'table', wrapperTitle || 'main', sectionHint, `idx_${tableIndex}`].join('__');
        table.dataset.persistTableId = id;
        return id;
    };

    const headerPersistKey = (table, th, colIndex, tableIndex) => {
        const stableKey = stableHeaderKey(th, colIndex);
        return [tablePersistId(table, tableIndex), stableKey].join('::');
    };

    const ensureLabelTarget = (th) => {
        const sortLink = th.querySelector(':scope > .th-sort');
        if (sortLink instanceof HTMLElement) {
            sortLink.classList.add('th-label-text');
            return sortLink;
        }
        let label = th.querySelector(':scope > .th-label-text');
        if (!(label instanceof HTMLElement)) {
            label = document.createElement('span');
            label.className = 'th-label-text';
            label.textContent = normalizeText(th.textContent || '');
            th.textContent = '';
            th.appendChild(label);
        }
        return label;
    };

    const setHeaderLabel = (th, label) => {
        const target = ensureLabelTarget(th);
        target.textContent = label;
        th.dataset.headerLabel = label;
    };

    const getHeaderCells = (table) => {
        if (!(table.tHead instanceof HTMLTableSectionElement) || table.tHead.rows.length === 0) {
            return [];
        }
        return Array.from(table.tHead.rows[table.tHead.rows.length - 1].cells)
            .filter((cell) => cell instanceof HTMLTableCellElement && cell.colSpan === 1);
    };

    const getColumnCells = (table, colIndex) => {
        const cells = [];
        const headerCells = getHeaderCells(table);
        const headerCell = headerCells[colIndex];
        if (headerCell instanceof HTMLTableCellElement) {
            cells.push(headerCell);
        }
        Array.from(table.tBodies).forEach((tbody) => {
            Array.from(tbody.rows).forEach((row) => {
                if (!(row instanceof HTMLTableRowElement)) {
                    return;
                }
                if (row.classList.contains('subtable-row-js') || row.classList.contains('repeated-exp-header')) {
                    return;
                }
                const cell = row.cells[colIndex];
                if (!(cell instanceof HTMLTableCellElement) || cell.colSpan !== 1) {
                    return;
                }
                cells.push(cell);
            });
        });
        return cells;
    };

    const clearColumnWidth = (table, colIndex) => {
        getColumnCells(table, colIndex).forEach((cell) => {
            cell.style.width = '';
            cell.style.minWidth = '';
            cell.style.maxWidth = '';
        });
    };

    const shouldSkipAutoWidths = (table) => {
        if (!(table instanceof HTMLTableElement)) {
            return false;
        }
        return table.dataset.staticTable === '1' || (view === 'competitions' && !!table.closest('.competition-results'));
    };

    const applyColumnWidth = (table, colIndex, widthPx) => {
        const width = `${Math.max(48, Math.round(widthPx))}px`;
        getColumnCells(table, colIndex).forEach((cell) => {
            cell.style.width = width;
            cell.style.minWidth = width;
            cell.style.maxWidth = width;
        });
    };

    const measureAutoColumnWidth = (table, colIndex) => {
        const cells = getColumnCells(table, colIndex);
        if (cells.length === 0) {
            return 48;
        }
        let maxWidth = 48;
        cells.forEach((cell) => {
            const prevWidth = cell.style.width;
            const prevMinWidth = cell.style.minWidth;
            const prevMaxWidth = cell.style.maxWidth;
            cell.style.width = 'auto';
            cell.style.minWidth = '0';
            cell.style.maxWidth = 'none';
            const rectWidth = cell.getBoundingClientRect().width;
            const scrollWidth = cell.scrollWidth;
            maxWidth = Math.max(maxWidth, rectWidth, scrollWidth + 8);
            cell.style.width = prevWidth;
            cell.style.minWidth = prevMinWidth;
            cell.style.maxWidth = prevMaxWidth;
        });
        return Math.ceil(maxWidth);
    };

    const applyAutoWidths = (table, tableIndex) => {
        if (shouldSkipAutoWidths(table)) {
            return;
        }
        const widthMap = readWidthMap();
        const headers = getHeaderCells(table);
        headers.forEach((th, colIndex) => {
            const persistKey = headerPersistKey(table, th, colIndex, tableIndex);
            const savedWidth = Number(widthMap[persistKey]);
            if (Number.isFinite(savedWidth) && savedWidth >= 48) {
                applyColumnWidth(table, colIndex, savedWidth);
                return;
            }
            clearColumnWidth(table, colIndex);
            applyColumnWidth(table, colIndex, measureAutoColumnWidth(table, colIndex));
        });
    };

    const recalcVisibleTableTree = (rootTable) => {
        if (!(rootTable instanceof HTMLTableElement)) {
            return;
        }
        const tables = [rootTable, ...Array.from(rootTable.querySelectorAll('table'))]
            .filter((table, idx, arr) => table instanceof HTMLTableElement && arr.indexOf(table) === idx);
        tables.forEach((table) => {
            if (shouldSkipAutoWidths(table)) {
                return;
            }
            const idx = Number(table.dataset.tableIndex || '0');
            applyAutoWidths(table, Number.isFinite(idx) ? idx : 0);
        });
    };

    const moveCellInRow = (row, fromIndex, toIndex) => {
        if (!(row instanceof HTMLTableRowElement)) {
            return;
        }
        if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
            return;
        }
        if (row.cells.length <= Math.max(fromIndex, toIndex)) {
            return;
        }
        const fromCell = row.cells[fromIndex];
        const targetCell = row.cells[toIndex];
        if (!(fromCell instanceof HTMLTableCellElement) || !(targetCell instanceof HTMLTableCellElement)) {
            return;
        }
        const insertBeforeNode = fromIndex < toIndex ? targetCell.nextSibling : targetCell;
        row.insertBefore(fromCell, insertBeforeNode);
    };

    const reorderTableColumns = (table, fromIndex, toIndex) => {
        if (!(table instanceof HTMLTableElement) || fromIndex === toIndex) {
            return;
        }
        Array.from(table.rows).forEach((row) => moveCellInRow(row, fromIndex, toIndex));
    };

    const applySavedOrder = (table, tableIndex) => {
        const orderMap = readOrderMap();
        const tableKey = tablePersistId(table, tableIndex);
        const saved = Array.isArray(orderMap[tableKey]) ? orderMap[tableKey] : null;
        if (!saved || !table.tHead || !table.tHead.rows.length) {
            return;
        }
        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        const currentHeaders = Array.from(headerRow.cells).filter((cell) => cell instanceof HTMLTableCellElement && cell.colSpan === 1);
        if (currentHeaders.length < 2) {
            return;
        }
        const currentKeys = currentHeaders.map((th, idx) => headerPersistKey(table, th, idx, tableIndex));
        if (saved.length !== currentKeys.length || !saved.every((key) => currentKeys.includes(key))) {
            return;
        }
        saved.forEach((wantedKey, targetIndex) => {
            const liveHeaders = Array.from(headerRow.cells).filter((cell) => cell instanceof HTMLTableCellElement && cell.colSpan === 1);
            const sourceIndex = liveHeaders.findIndex((th, idx) => headerPersistKey(table, th, idx, tableIndex) === wantedKey);
            if (sourceIndex >= 0 && sourceIndex !== targetIndex) {
                reorderTableColumns(table, sourceIndex, targetIndex);
            }
        });
    };

    const persistCurrentOrder = (table, tableIndex) => {
        if (!(table.tHead instanceof HTMLTableSectionElement) || !table.tHead.rows.length) {
            return;
        }
        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        const order = Array.from(headerRow.cells)
            .filter((cell) => cell instanceof HTMLTableCellElement && cell.colSpan === 1)
            .map((th, idx) => headerPersistKey(table, th, idx, tableIndex));
        const orderMap = readOrderMap();
        orderMap[tablePersistId(table, tableIndex)] = order;
        writeOrderMap(orderMap);
    };

    const syncVisibleControlsForTable = (table) => {
        const form = table.closest('.card')?.querySelector('form.table-filters');
        if (!(form instanceof HTMLFormElement) || !(table.tHead instanceof HTMLTableSectionElement) || !table.tHead.rows.length) {
            return;
        }

        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        const headerKeys = Array.from(headerRow.cells)
            .filter((cell) => cell instanceof HTMLTableCellElement && cell.colSpan === 1)
            .map((cell) => String(cell.dataset.colKey || '').trim())
            .filter((key) => key !== '');

        if (headerKeys.length === 0) {
            return;
        }

        const groups = Array.from(form.querySelectorAll('.visible-columns'));
        groups.forEach((details) => {
            if (!(details instanceof HTMLElement)) {
                return;
            }
            const prefix = String(details.getAttribute('data-col-prefix') || '').trim();
            const orderFieldName = String(details.getAttribute('data-order-field') || '').trim();
            if (prefix === '' || orderFieldName === '') {
                return;
            }

            const visibleRow = details.querySelector('.visible-row');
            if (!(visibleRow instanceof HTMLElement)) {
                return;
            }

            const keysForGroup = headerKeys
                .map((key) => {
                    if (prefix === 'col_') {
                        return key.startsWith('k_') || key.startsWith('h_') ? null : key;
                    }
                    if (prefix === 'kcol_') {
                        return key.startsWith('k_') ? key.slice(2) : null;
                    }
                    if (prefix === 'hcol_') {
                        return key.startsWith('h_') ? key.slice(2) : null;
                    }
                    return key;
                })
                .filter((key) => typeof key === 'string' && key !== '');

            if (keysForGroup.length === 0) {
                return;
            }

            const itemMap = new Map();
            visibleRow.querySelectorAll('.visible-item').forEach((item) => {
                const key = item.getAttribute('data-col-key');
                if (key) {
                    itemMap.set(key, item);
                }
            });

            let moved = false;
            keysForGroup.forEach((key) => {
                const item = itemMap.get(key);
                if (item) {
                    visibleRow.appendChild(item);
                    moved = true;
                }
            });

            const hiddenOrder = form.querySelector(`input[name="${orderFieldName}"]`);
            if (hiddenOrder instanceof HTMLInputElement) {
                const orderedVisibleKeys = Array.from(visibleRow.querySelectorAll('.visible-item'))
                    .map((item) => {
                        const key = item.getAttribute('data-col-key');
                        const cb = key ? item.querySelector(`input[name="${prefix}${key}"]`) : null;
                        return key && cb instanceof HTMLInputElement && cb.checked ? key : null;
                    })
                    .filter((key) => typeof key === 'string' && key !== '');
                hiddenOrder.value = orderedVisibleKeys.join(',');
                hiddenOrder.dispatchEvent(new Event('change', { bubbles: true }));
            }

            if (moved) {
                visibleRow.querySelectorAll('.col-check').forEach((cb) => {
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }
        });
    };

    const wireResizableAndRenameableTable = (table, tableIndex) => {
        if (!(table instanceof HTMLTableElement) || !(table.tHead instanceof HTMLTableSectionElement)) {
            return;
        }
        applySavedOrder(table, tableIndex);
        if (!shouldSkipAutoWidths(table)) {
            applyAutoWidths(table, tableIndex);
        }
        const headerRow = table.tHead.rows[table.tHead.rows.length - 1];
        if (!(headerRow instanceof HTMLTableRowElement)) {
            return;
        }

        const renameMap = readRenameMap();
        const tableKey = tablePersistId(table, tableIndex);

        Array.from(headerRow.cells).forEach((th, colIndex) => {
            if (!(th instanceof HTMLTableCellElement) || th.colSpan !== 1) {
                return;
            }

            th.classList.add('th-renameable');
            const initialLabel = normalizeText(th.dataset.headerLabel || th.textContent || '');
            if (!th.dataset.defaultHeaderLabel && initialLabel !== '') {
                th.dataset.defaultHeaderLabel = initialLabel;
            }
            ensureLabelTarget(th);

            const persistKey = headerPersistKey(table, th, colIndex, tableIndex);
            const renamedLabel = normalizeText(renameMap[persistKey] || '');
            if (renamedLabel !== '') {
                setHeaderLabel(th, renamedLabel);
                th.classList.add('is-renamed');
            }

            if (!th.querySelector(':scope > .th-resize-handle')) {
                const handle = document.createElement('span');
                handle.className = 'th-resize-handle';
                handle.title = 'Arrastrar para cambiar ancho';
                th.appendChild(handle);

                handle.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    event.stopPropagation();

                    const startX = event.clientX;
                    const startWidth = th.getBoundingClientRect().width;
                    th.classList.add('is-resizing');
                    document.body.classList.add('col-resize-active');

                    const onMove = (moveEvent) => {
                        const nextWidth = startWidth + (moveEvent.clientX - startX);
                        applyColumnWidth(table, colIndex, nextWidth);
                    };

                    const onUp = (upEvent) => {
                        const finalWidth = startWidth + (upEvent.clientX - startX);
                        applyColumnWidth(table, colIndex, finalWidth);
                        const widthMap = readWidthMap();
                        widthMap[persistKey] = Math.max(48, Math.round(finalWidth));
                        writeWidthMap(widthMap);
                        th.classList.remove('is-resizing');
                        document.body.classList.remove('col-resize-active');
                        window.removeEventListener('mousemove', onMove);
                        window.removeEventListener('mouseup', onUp);
                    };

                    window.addEventListener('mousemove', onMove);
                    window.addEventListener('mouseup', onUp);
                });
            }

            if (th.dataset.renameWired === '1') {
                return;
            }
            th.dataset.renameWired = '1';
            th.classList.add('th-draggable');
            th.draggable = true;

            th.addEventListener('dragstart', (event) => {
                const target = event.target;
                if (target instanceof HTMLElement && target.closest('.th-resize-handle')) {
                    event.preventDefault();
                    return;
                }
                const currentHeaderRow = table.tHead?.rows[table.tHead.rows.length - 1];
                if (!(currentHeaderRow instanceof HTMLTableRowElement)) {
                    event.preventDefault();
                    return;
                }
                const dragHeaders = Array.from(currentHeaderRow.cells).filter((cell) => cell instanceof HTMLTableCellElement && cell.colSpan === 1);
                const currentIndex = dragHeaders.indexOf(th);
                if (currentIndex < 0) {
                    event.preventDefault();
                    return;
                }
                th.classList.add('is-dragging');
                th.dataset.dragIndex = String(currentIndex);
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', String(currentIndex));
                }
            });

            th.addEventListener('dragend', () => {
                table.querySelectorAll('th.th-draggable').forEach((cell) => {
                    cell.classList.remove('is-dragging', 'drag-target-left', 'drag-target-right');
                    delete cell.dataset.dragIndex;
                });
            });

            th.addEventListener('dragover', (event) => {
                const dragging = table.querySelector('th.th-draggable.is-dragging');
                if (!(dragging instanceof HTMLTableCellElement) || dragging === th) {
                    return;
                }
                event.preventDefault();
                const rect = th.getBoundingClientRect();
                const before = event.clientX < rect.left + rect.width / 2;
                th.classList.toggle('drag-target-left', before);
                th.classList.toggle('drag-target-right', !before);
            });

            th.addEventListener('dragleave', () => {
                th.classList.remove('drag-target-left', 'drag-target-right');
            });

            th.addEventListener('drop', (event) => {
                event.preventDefault();
                const currentHeaderRow = table.tHead?.rows[table.tHead.rows.length - 1];
                if (!(currentHeaderRow instanceof HTMLTableRowElement)) {
                    return;
                }
                const dragHeaders = Array.from(currentHeaderRow.cells).filter((cell) => cell instanceof HTMLTableCellElement && cell.colSpan === 1);
                const sourceIndex = Number(event.dataTransfer?.getData('text/plain') || -1);
                const targetIndex = dragHeaders.indexOf(th);
                if (!Number.isInteger(sourceIndex) || sourceIndex < 0 || targetIndex < 0 || sourceIndex === targetIndex) {
                    th.classList.remove('drag-target-left', 'drag-target-right');
                    return;
                }
                const rect = th.getBoundingClientRect();
                const before = event.clientX < rect.left + rect.width / 2;
                const finalIndex = before ? targetIndex : targetIndex + 1;
                const adjustedIndex = sourceIndex < finalIndex ? finalIndex - 1 : finalIndex;
                reorderTableColumns(table, sourceIndex, adjustedIndex);
                persistCurrentOrder(table, tableIndex);
                syncVisibleControlsForTable(table);
                table.querySelectorAll('th.th-draggable').forEach((cell) => {
                    cell.classList.remove('is-dragging', 'drag-target-left', 'drag-target-right');
                    delete cell.dataset.dragIndex;
                });
            });

            th.addEventListener('click', (event) => {
                const target = event.target;
                if (!(event.ctrlKey && event.shiftKey)) {
                    return;
                }
                if (target instanceof HTMLElement && target.closest('.th-resize-handle')) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const currentLabel = normalizeText(th.dataset.headerLabel || th.dataset.defaultHeaderLabel || th.textContent || '');
                const nextLabelRaw = window.prompt('Nuevo nombre de la columna. Deja vacio para restaurar el original.', currentLabel);
                if (nextLabelRaw === null) {
                    return;
                }

                const nextLabel = normalizeText(nextLabelRaw);
                const defaultLabel = normalizeText(th.dataset.defaultHeaderLabel || currentLabel);
                const nextMap = readRenameMap();

                if (nextLabel === '' || nextLabel === defaultLabel) {
                    delete nextMap[persistKey];
                    writeRenameMap(nextMap);
                    setHeaderLabel(th, defaultLabel);
                    th.classList.remove('is-renamed');
                    return;
                }

                nextMap[persistKey] = nextLabel;
                writeRenameMap(nextMap);
                setHeaderLabel(th, nextLabel);
                th.classList.add('is-renamed');
            }, true);
        });
    };

    document.querySelectorAll('.content table').forEach((table, tableIndex) => {
        if (table instanceof HTMLTableElement) {
            table.dataset.tableIndex = String(tableIndex);
        }
        wireResizableAndRenameableTable(table, tableIndex);
    });

    document.addEventListener('thc:subtable-toggle', (event) => {
        const detail = event.target;
        if (!(detail instanceof HTMLDetailsElement)) {
            return;
        }
        const parentTable = detail.closest('table');
        if (parentTable instanceof HTMLTableElement) {
            window.requestAnimationFrame(() => recalcVisibleTableTree(parentTable));
        }
    });

    if (view === 'expeditions') {
        const finishExpeditionsInit = () => {
            window.requestAnimationFrame(() => {
                window.requestAnimationFrame(() => {
                    document.body.classList.remove('view-expeditions-initializing');
                });
            });
        };

        let done = false;
        const onceFinish = () => {
            if (done) {
                return;
            }
            done = true;
            finishExpeditionsInit();
        };

        if (window.thcExpeditionsSubtablesReady === true) {
            onceFinish();
            return;
        }

        document.addEventListener('thc:expeditions-subtables-ready', onceFinish, { once: true });
        window.addEventListener('load', onceFinish, { once: true });
        window.setTimeout(onceFinish, 1200);
    }
})();

(() => {
    const view = new URLSearchParams(window.location.search).get('view') || 'dashboard';
    const storageKey = 'thc_button_labels_v2';
    const sizeStorageKey = 'thc_button_sizes_v1';
    const separatorStorageKey = 'thc_button_separators_v1';
    try {
        localStorage.removeItem('thc_button_labels_v1');
    } catch (_) {}

    const normalizeText = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const safeReadJson = () => {
        try {
            if (window.thcReadGlobalPref) {
                return window.thcReadGlobalPref(storageKey);
            }
            return {};
        } catch (_) {
            return {};
        }
    };
    const safeWriteJson = (data) => {
        if (window.thcWriteGlobalPref) {
            window.thcWriteGlobalPref(storageKey, data);
        }
    };
    const readGlobalMap = (key) => {
        try {
            return window.thcReadGlobalPref ? window.thcReadGlobalPref(key) : {};
        } catch (_) {
            return {};
        }
    };
    const writeGlobalMap = (key, data) => {
        if (window.thcWriteGlobalPref) {
            window.thcWriteGlobalPref(key, data);
        }
    };

    const scopedButtonIndex = (el) => {
        const scope = el.closest('.sidebar .nav, .card, form, .content') || document.body;
        const peers = Array.from(scope.querySelectorAll('button, a.btn-link, .nav-link'));
        return String(Math.max(0, peers.indexOf(el)));
    };

    const buttonKey = (el, idx) => {
        if (el.classList.contains('nav-link')) {
            const navKey = normalizeText(el.getAttribute('data-nav-key') || el.getAttribute('href') || `nav_${idx}`);
            return ['sidebar-nav', navKey].join('::');
        }

        const form = el.closest('form');
        const card = el.closest('.card');
        const cardTitle = normalizeText(card?.querySelector('h2')?.textContent || '');
        const formView = normalizeText(form?.querySelector('input[name="view"]')?.value || '');
        const actionValue = normalizeText(form?.querySelector('input[name="action"]')?.value || '');
        const presetActionValue = normalizeText(form?.querySelector('input[name="preset_action"]')?.value || '');
        const taskIdValue = normalizeText(form?.querySelector('input[name="id"]')?.value || '');
        const name = normalizeText(el.getAttribute('name') || '');
        const value = normalizeText(el.getAttribute('value') || '');
        const type = normalizeText(el.getAttribute('type') || '');
        const href = el instanceof HTMLAnchorElement ? normalizeText(el.getAttribute('href') || '') : '';
        let identity = '';
        if (actionValue !== '') {
            identity = `action:${actionValue}:${presetActionValue}:${taskIdValue}:${name}:${value}:${type}`;
        } else if (taskIdValue !== '') {
            identity = `task:${taskIdValue}:${name}:${value}:${type}`;
        } else if (presetActionValue !== '') {
            identity = `preset:${presetActionValue}:${name}:${value}:${type}`;
        } else if (href !== '') {
            identity = `href:${href}`;
        } else if ([name, value, type].some((part) => part !== '')) {
            identity = `control:${name}:${value}:${type}:${scopedButtonIndex(el)}`;
        } else {
            identity = `dom:${scopedButtonIndex(el)}:${idx}`;
        }
        return [view, formView || cardTitle || 'page', identity].join('::');
    };

    const applyButtonSize = (el, size) => {
        if (!size || typeof size !== 'object') {
            return;
        }
        const width = Number(size.w);
        const height = Number(size.h);
        if (Number.isFinite(width) && width >= 24) {
            const value = `${Math.round(width)}px`;
            el.style.width = value;
            el.style.minWidth = value;
            el.style.maxWidth = value;
        }
        if (Number.isFinite(height) && height >= 18) {
            const value = `${Math.round(height)}px`;
            el.style.height = value;
            el.style.minHeight = value;
        }
    };

    const buttonSizeGroup = (el, fallbackKey) => {
        if (el.classList.contains('nav-link')) {
            return 'group::sidebar-nav';
        }
        if (el.closest('.action-grid')) {
            return 'group::action-grid';
        }
        return fallbackKey;
    };

    const groupButtons = (el) => {
        if (el.classList.contains('nav-link')) {
            return Array.from(document.querySelectorAll('.sidebar .nav-link'));
        }
        const actionGrid = el.closest('.action-grid');
        if (actionGrid instanceof HTMLElement) {
            return Array.from(actionGrid.querySelectorAll('button, a.btn-link'));
        }
        return [el];
    };

    const clearButtonSize = (button) => {
        button.style.width = '';
        button.style.minWidth = '';
        button.style.maxWidth = '';
        button.style.height = '';
        button.style.minHeight = '';
    };

    const applyButtonSizeToGroup = (el, size) => {
        groupButtons(el).forEach((button) => {
            if (button instanceof HTMLElement) {
                applyButtonSize(button, size);
            }
        });
    };

    const resetButtonSize = (el, sizeKey) => {
        groupButtons(el).forEach((button) => {
            if (button instanceof HTMLElement) {
                clearButtonSize(button);
            }
        });
        const sizes = readGlobalMap(sizeStorageKey);
        delete sizes[sizeKey];
        writeGlobalMap(sizeStorageKey, sizes);
    };

    const ensureResizeHandle = (el, key, sizeKey) => {
        if (el.querySelector(':scope > .button-resize-handle')) {
            return;
        }
        el.classList.add('button-resizable');
        const handle = document.createElement('span');
        handle.className = 'button-resize-handle';
        handle.title = 'Arrastrar para redimensionar. Doble click para restaurar tamano';
        el.appendChild(handle);

        handle.addEventListener('dblclick', (event) => {
            event.preventDefault();
            event.stopPropagation();
            resetButtonSize(el, sizeKey);
        });

        handle.addEventListener('mousedown', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const rect = el.getBoundingClientRect();
            const startX = event.clientX;
            const startY = event.clientY;
            const startWidth = rect.width;
            const startHeight = rect.height;
            document.body.classList.add('button-resize-active');
            el.classList.add('is-button-resizing');

            const onMove = (moveEvent) => {
                const nextWidth = Math.max(24, startWidth + (moveEvent.clientX - startX));
                const nextHeight = Math.max(18, startHeight + (moveEvent.clientY - startY));
                applyButtonSizeToGroup(el, {w: nextWidth, h: nextHeight});
            };

            const onUp = (upEvent) => {
                const nextWidth = Math.max(24, startWidth + (upEvent.clientX - startX));
                const nextHeight = Math.max(18, startHeight + (upEvent.clientY - startY));
                applyButtonSizeToGroup(el, {w: nextWidth, h: nextHeight});
                const sizes = readGlobalMap(sizeStorageKey);
                sizes[sizeKey] = {w: Math.round(nextWidth), h: Math.round(nextHeight)};
                writeGlobalMap(sizeStorageKey, sizes);
                document.body.classList.remove('button-resize-active');
                el.classList.remove('is-button-resizing');
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('mouseup', onUp);
            };

            window.addEventListener('mousemove', onMove);
            window.addEventListener('mouseup', onUp);
        });
    };

    const renderSeparator = (el, key, text) => {
        const normalized = normalizeText(text);
        const existing = el.previousElementSibling;
        if (existing instanceof HTMLElement && existing.classList.contains('button-separator-text') && existing.dataset.buttonSeparatorKey === key) {
            if (normalized === '') {
                existing.remove();
            } else {
                existing.textContent = normalized;
            }
            return;
        }
        if (normalized === '') {
            return;
        }
        const sep = document.createElement(el.classList.contains('nav-link') ? 'div' : 'span');
        sep.className = 'button-separator-text';
        sep.dataset.buttonSeparatorKey = key;
        sep.textContent = normalized;
        el.insertAdjacentElement('beforebegin', sep);
    };

    const labels = safeReadJson();
    const sizes = readGlobalMap(sizeStorageKey);
    const separators = readGlobalMap(separatorStorageKey);
    Array.from(document.querySelectorAll('.content button, .content a.btn-link, .sidebar .nav-link')).forEach((el, idx) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }
        const text = normalizeText(el.textContent || '');
        if (text === '') {
            return;
        }
        if (!el.dataset.defaultButtonLabel) {
            el.dataset.defaultButtonLabel = text;
        }

        const key = buttonKey(el, idx);
        const sizeKey = buttonSizeGroup(el, key);
        const saved = normalizeText(labels[key] || '');
        if (saved !== '') {
            el.textContent = saved;
            el.classList.add('is-renamed-button');
        }
        applyButtonSize(el, sizes[sizeKey]);
        renderSeparator(el, key, separators[key] || '');
        ensureResizeHandle(el, key, sizeKey);

        if (el.dataset.buttonRenameWired === '1') {
            return;
        }
        el.dataset.buttonRenameWired = '1';
        el.title = el.title || 'Ctrl+Shift+Click para cambiar texto. Shift+Alt+Click para texto antes. Ctrl+Alt+Click restaura tamano.';
        el.addEventListener('click', (event) => {
            const target = event.target;
            if (target instanceof HTMLElement && target.closest('.button-resize-handle')) {
                event.preventDefault();
                event.stopPropagation();
                return;
            }
            if (event.ctrlKey && event.altKey && !event.shiftKey) {
                event.preventDefault();
                event.stopPropagation();
                resetButtonSize(el, sizeKey);
                return;
            }
            if (event.altKey && event.shiftKey && !event.ctrlKey) {
                event.preventDefault();
                event.stopPropagation();
                const currentSeparator = normalizeText(readGlobalMap(separatorStorageKey)[key] || '');
                const nextRaw = window.prompt('Texto antes de este boton. Deja vacio para quitarlo.', currentSeparator);
                if (nextRaw === null) {
                    return;
                }
                const nextText = normalizeText(nextRaw);
                const nextMap = readGlobalMap(separatorStorageKey);
                if (nextText === '') {
                    delete nextMap[key];
                } else {
                    nextMap[key] = nextText;
                }
                writeGlobalMap(separatorStorageKey, nextMap);
                renderSeparator(el, key, nextText);
                return;
            }
            if (!(event.ctrlKey && event.shiftKey)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();

            const currentLabel = normalizeText(el.textContent || '');
            const defaultLabel = normalizeText(el.dataset.defaultButtonLabel || currentLabel);
            const nextRaw = window.prompt('Nuevo texto del boton. Deja vacio para restaurar el original.', currentLabel);
            if (nextRaw === null) {
                return;
            }
            const nextLabel = normalizeText(nextRaw);
            const nextMap = safeReadJson();
            if (nextLabel === '' || nextLabel === defaultLabel) {
                delete nextMap[key];
                safeWriteJson(nextMap);
                el.textContent = defaultLabel;
                el.classList.remove('is-renamed-button');
                ensureResizeHandle(el, key, sizeKey);
                return;
            }
            nextMap[key] = nextLabel;
            safeWriteJson(nextMap);
            el.textContent = nextLabel;
            el.classList.add('is-renamed-button');
            ensureResizeHandle(el, key, sizeKey);
        }, true);
    });
})();

(() => {
    const storageKey = 'thc_task_labels_v1';
    const normalizeText = (value) => String(value || '').replace(/\s+/g, ' ').trim();
    const readMap = () => {
        try {
            return window.thcReadGlobalPref ? window.thcReadGlobalPref(storageKey) : {};
        } catch (_) {
            return {};
        }
    };
    const writeMap = (data) => {
        if (window.thcWriteGlobalPref) {
            window.thcWriteGlobalPref(storageKey, data);
        }
    };

    const labels = readMap();
    document.querySelectorAll('.task-label-editable[data-task-label-key]').forEach((cell) => {
        if (!(cell instanceof HTMLElement)) {
            return;
        }
        const key = normalizeText(cell.dataset.taskLabelKey || '');
        if (key === '') {
            return;
        }
        const defaultText = normalizeText(cell.textContent || '');
        cell.dataset.defaultTaskLabel = defaultText;
        const saved = normalizeText(labels[key] || '');
        if (saved !== '') {
            cell.textContent = saved;
            cell.classList.add('is-renamed-task-label');
        }
        cell.title = cell.title || 'Ctrl+Shift+Click para cambiar texto de la tarea';
        cell.addEventListener('click', (event) => {
            if (!(event.ctrlKey && event.shiftKey)) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            const current = normalizeText(cell.textContent || '');
            const original = normalizeText(cell.dataset.defaultTaskLabel || current);
            const nextRaw = window.prompt('Nuevo texto de la tarea. Deja vacio para restaurar el original.', current);
            if (nextRaw === null) {
                return;
            }
            const next = normalizeText(nextRaw);
            const nextMap = readMap();
            if (next === '' || next === original) {
                delete nextMap[key];
                writeMap(nextMap);
                cell.textContent = original;
                cell.classList.remove('is-renamed-task-label');
                return;
            }
            nextMap[key] = next;
            writeMap(nextMap);
            cell.textContent = next;
            cell.classList.add('is-renamed-task-label');
        }, true);
    });
})();
















































</script>
</body>
</html>











