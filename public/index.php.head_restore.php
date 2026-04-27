<?php
// public/index.php - Versión original limpia

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
        'bisonte' => true, 'bison' => true,
        'oso pardo' => true, 'brown bear' => true,
        'oso grizzly' => true, 'grizzly bear' => true,
        'oso negro' => true, 'black bear' => true,
        'lobo gris' => true, 'grey wolf' => true,
        'coyote' => true,
        'zorro rojo' => true, 'red fox' => true,
        'zorro artico' => true, 'arctic fox' => true,
        'lince europeo' => true, 'eurasian lynx' => true,
        'lince rojo' => true, 'bobcat' => true,
        'conejo europeo' => true, 'european rabbit' => true,
        'conejo de cola algodon' => true, 'cottontail rabit' => true, 'cottontail rabbit' => true,
        'liebre artica' => true, 'liebre americana' => true, 'snowshoe hare' => true,
        'ganso del canada' => true, 'ganso de canada' => true, 'canada goose' => true,
        'ganso urraca' => true, 'ganso urraco' => true, 'magpie goose' => true,
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
    usort($ranked, static fn (array $a, array $b): int => ($a['order'] <=> $b['order']) ?: ($a['idx'] <=> $b['idx']));
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
        $rows = app_query_all("SELECT DISTINCT reserve_name FROM gpt.exp_expeditions WHERE reserve_name IS NOT NULL AND reserve_name <> '' ORDER BY reserve_name");
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
        $rows = app_query_all("SELECT id_especie, especie, especie_es FROM gpt.tab_especies ORDER BY id_especie");
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
    $values = array_map(static fn ($v): string => (string) $v, array_keys($set));
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
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n',
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

// ============================================
// FUNCIONES DE RENDERIZADO (versiones simplificadas para que funcione)
// ============================================

function render_dashboard(): void
{
    echo '<section class="card"><h2>Panel de Control</h2><p>Bienvenido al panel de THC Query</p></section>';
}

function render_expeditions(): void
{
    echo '<section class="card"><h2>Expediciones</h2><p>Funcionalidad de expediciones</p></section>';
}

function render_best(): void
{
    echo '<section class="card"><h2>Mejores Marcas</h2><p>Funcionalidad de mejores marcas</p></section>';
}

function render_profiles(): void
{
    echo '<section class="card"><h2>Estadisticas</h2><p>Funcionalidad de estadisticas</p></section>';
}

function render_competitions(): void
{
    echo '<section class="card"><h2>Competiciones</h2><p>Funcionalidad de competiciones</p></section>';
}

function render_classifications(): void
{
    echo '<section class="card"><h2>Tablas Clasificacion</h2><p>Funcionalidad de clasificaciones</p></section>';
}

function render_classifications_history(): void
{
    echo '<section class="card"><h2>Tablas Clasificacion Hist.</h2><p>Funcionalidad de historico</p></section>';
}

function render_species_ppft(): void
{
    echo '<section class="card"><h2>Especies PPFT</h2><p>Funcionalidad de especies</p></section>';
}

function render_hall_of_fame(): void
{
    echo '<section class="card"><h2>Salones Fama</h2><p>Funcionalidad de salones de la fama</p></section>';
}

function render_trophies_summary(): void
{
    echo '<section class="card"><h2>Resumen Trofeos</h2><p>Funcionalidad de trofeos</p></section>';
}

function render_user_gallery(): void
{
    echo '<section class="card"><h2>Galerias Usuarios</h2><p>Funcionalidad de galerias</p></section>';
}

function render_kill_url_scrape_status(): void
{
    echo '<section class="card"><h2>Estado Scraper</h2><p>Funcionalidad de scraper</p></section>';
}

function render_kill_detail_scrapes(): void
{
    echo '<section class="card"><h2>Detalle Muertes</h2><p>Funcionalidad de detalle de muertes</p></section>';
}

function render_competition_signups(): void
{
    echo '<section class="card"><h2>Inscripciones Comp.</h2><p>Funcionalidad de inscripciones</p></section>';
}

function render_cheat_risk(): void
{
    echo '<section class="card"><h2>Anti-trampas</h2><p>Funcionalidad de anti-trampas</p></section>';
}

function render_logs(): void
{
    echo '<section class="card"><h2>Logs</h2><p>Funcionalidad de logs</p></section>';
}

function render_best_xml_preview(): void
{
    echo '<section class="card"><h2>Comparativa Mejores Marcas</h2><p>Funcionalidad de comparativa XML</p></section>';
}

function render_table_styles_preview(): void
{
    echo '<section class="card"><h2>Vista Previa Estilos</h2><p>Vista previa de estilos de tablas</p></section>';
}

function render_advanced(): void
{
    echo '<section class="card"><h2>Consulta Avanzada</h2><p>Funcionalidad de consulta avanzada</p></section>';
}

// ============================================
// HTML PRINCIPAL
// ============================================

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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>THC GPT Panel</title>
    <link rel="stylesheet" href="style.css?v=<?= h($cssVersion) ?>">
</head>
<body class="theme-<?= h($theme) ?> font-<?= h($font) ?>">
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
</body>
</html>