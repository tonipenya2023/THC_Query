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

// ============================================
// FUNCIONES AUXILIARES (las copiamos de tu index original)
// ============================================

function menu_link(string $key, string $label, string $current): string {
    $class = $key === $current ? 'nav-link active' : 'nav-link';
    return '<a class="' . $class . '" draggable="true" data-nav-key="' . h($key) . '" href="?view=' . urlencode($key) . '&theme=' . urlencode(app_theme()) . '&font=' . urlencode(app_font()) . '">' . h($label) . '</a>';
}

function theme_link(string $themeName, string $label, string $currentView, string $activeTheme): string {
    $class = $themeName === $activeTheme ? 'theme-chip active' : 'theme-chip';
    return '<a class="' . $class . '" href="?view=' . urlencode($currentView) . '&theme=' . urlencode($themeName) . '&font=' . urlencode(app_font()) . '">' . h($label) . '</a>';
}

function font_link(string $fontName, string $label, string $sample, string $currentView, string $activeFont, string $fontClass): string {
    $class = 'font-chip ' . $fontClass . ($fontName === $activeFont ? ' active' : '');
    return '<a class="' . h($class) . '" href="?view=' . urlencode($currentView) . '&theme=' . urlencode(app_theme()) . '&font=' . urlencode($fontName) . '">'
        . '<span class="font-chip-name">' . h($label) . '</span>'
        . '<span class="font-chip-sample">' . h($sample) . '</span>'
        . '</a>';
}

// Aquí copiaremos todas las funciones auxiliares de tu index original
// (query_text, query_int, thehunter_kill_url, etc. - todas las que ya tienes)

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



// Y aquí irán las funciones de renderizado que reconstruyamos

// Versión temporal de render_dashboard para que funcione
function render_dashboard(): void {
    echo '<section class="card"><h2>Panel de Control</h2><p>Bienvenido al panel de THC Query (reconstruido)</p></section>';
}

// Versión temporal para expediciones
function render_expeditions(): void {
    $page = query_page();
    $pageSize = query_page_size(100);
    $isReset = is_reset_requested();
    
    // Configuración de columnas principales
    $columnDefs = [
        'expedition_id' => ['expr' => 'e.expedition_id', 'sort' => 'e.expedition_id', 'label' => 'ID'],
        'user_id' => ['expr' => 'e.user_id', 'sort' => 'e.user_id', 'label' => 'ID Usuario'],
        'player_name' => ['expr' => 'COALESCE(u.player_name, e.user_id::text)', 'sort' => 'COALESCE(u.player_name, e.user_id::text)', 'label' => 'Jugador'],
        'reserve_name' => ['expr' => 'e.reserve_name', 'sort' => 'e.reserve_name', 'label' => 'Reserva'],
        'start_at' => ['expr' => 'e.start_at', 'sort' => 'e.start_at', 'label' => 'Inicio'],
        'end_at' => ['expr' => 'e.end_at', 'sort' => 'e.end_at', 'label' => 'Fin'],
        'kill_count' => ['expr' => 'COALESCE(kc.kill_count, 0)', 'sort' => 'COALESCE(kc.kill_count, 0)', 'label' => 'Muertes'],
    ];
    
    // Columnas para la subtabla de muertes
$killColumnDefs = [
    'kill_id' => 'ID Muerte',
    'species_name' => 'Especie',
    'score' => 'Puntuación',
    'weight' => 'Peso (kg)',
    'harvest_value' => 'Valor',
    'hit_count' => 'Disparos',
    'hit_min_distance' => 'Distancia (m)',
];
    
    // Columnas para la subtabla de disparos
    $hitColumnDefs = [
        'hunter_name' => 'Cazador',
        'weapon_text' => 'Arma',
        'ammo_text' => 'Munición',
        'shot_distance_text' => 'Distancia',
        'body_part_text' => 'Parte',
    ];
    
    $defaultCols = ['expedition_id', 'player_name', 'reserve_name', 'start_at', 'kill_count'];
    $selectedCols = persistent_selected_columns('exp_cols', $columnDefs, 'col_', $defaultCols);
    
    $defaultKillCols = array_keys($killColumnDefs);
    $selectedKillCols = persistent_selected_columns('exp_kill_cols', $killColumnDefs, 'kcol_', $defaultKillCols);
    
    $defaultHitCols = array_keys($hitColumnDefs);
    $selectedHitCols = persistent_selected_columns('exp_hit_cols', $hitColumnDefs, 'hcol_', $defaultHitCols);
    
    // Filtros
    $playerNames = default_player_filter(query_list('player_name'), 'player_name');
    $reserveNames = query_list('reserve_name');
    $startAtFrom = query_date('date_from') ?? query_date('start_at_from');
    $endAtTo = query_date('date_to') ?? query_date('end_at_to');
    $openExpSet = [];
    foreach (query_list('open_exp') as $raw) {
        if (preg_match('/^\d+$/', (string) $raw) === 1) {
            $openExpSet[(int) $raw] = true;
        }
    }
    
    $where = [];
    $params = [];
    
    if ($playerNames !== []) {
        $parts = [];
        foreach ($playerNames as $idx => $name) {
            $ph = ':player_name_' . $idx;
            $parts[] = 'COALESCE(u.player_name, e.user_id::text) = ' . $ph;
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
    
    if ($startAtFrom !== null) {
        $where[] = 'DATE(e.start_at) >= :start_at_from';
        $params[':start_at_from'] = $startAtFrom;
    }
    
    if ($endAtTo !== null) {
        $where[] = 'DATE(e.end_at) <= :end_at_to';
        $params[':end_at_to'] = $endAtTo;
    }
    
    // Construir SQL principal
    $selectParts = [];
    foreach ($selectedCols as $key) {
        $selectParts[] = $columnDefs[$key]['expr'] . ' AS ' . quote_ident($key);
    }
    $selectSql = implode(', ', $selectParts);
    
    $sql = "SELECT {$selectSql}
            FROM gpt.exp_expeditions e
            LEFT JOIN gpt.tab_usuarios u ON u.user_id = e.user_id
            LEFT JOIN (
                SELECT expedition_id, COUNT(*) AS kill_count
                FROM gpt.exp_kills
                GROUP BY expedition_id
            ) kc ON kc.expedition_id = e.expedition_id";
    
    $countSql = "SELECT COUNT(*) AS c FROM gpt.exp_expeditions e
                 LEFT JOIN gpt.tab_usuarios u ON u.user_id = e.user_id";
    
    if ($where !== []) {
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $sql .= $whereSql;
        $countSql .= $whereSql;
    }
    
    $sql .= ' ORDER BY e.start_at DESC';
    
    if (is_csv_export_requested()) {
        $rows = app_query_all($sql, $params);
        $headers = array_map(fn($k) => $columnDefs[$k]['label'], $selectedCols);
        csv_stream('expediciones.csv', $headers, $rows, $selectedCols);
    }
    
    $totalRow = app_query_one($countSql, $params);
    $totalRows = (int)($totalRow['c'] ?? 0);
    $pageCount = max(1, (int)ceil($totalRows / $pageSize));
    $page = min($page, $pageCount);
    $offset = ($page - 1) * $pageSize;
    
    $sql .= ' LIMIT :_limit OFFSET :_offset';
    $rows = app_query_all($sql, $params + ['_limit' => $pageSize, '_offset' => $offset]);
    
    // Obtener expedition IDs para cargar kills
    $expeditionIds = array_column($rows, 'expedition_id');
    $killsByExpedition = [];
    
    if ($expeditionIds !== []) {
        $placeholders = implode(',', array_fill(0, count($expeditionIds), '?'));
$killSql = "
    SELECT 
        k.expedition_id,
        k.kill_id,
        COALESCE(s.especie_es, s.especie, k.species_name) AS species_name,
        k.score,
        k.weight / 1000 AS weight,
        k.harvest_value,
        COALESCE(h.hits_count, 0) AS hit_count,
        COALESCE(h.min_distance / 1000, 0) AS hit_min_distance
    FROM gpt.exp_kills k
    LEFT JOIN gpt.tab_especies s ON s.id_especie = k.species_id
    LEFT JOIN (
        SELECT kill_id, COUNT(*) AS hits_count, MIN(distance) AS min_distance
        FROM gpt.exp_hits
        GROUP BY kill_id
    ) h ON h.kill_id = k.kill_id
    WHERE k.expedition_id IN ({$placeholders})
    ORDER BY k.expedition_id DESC, k.kill_time DESC
";
        $killRows = app_query_all($killSql, $expeditionIds);
        
        foreach ($killRows as $killRow) {
            $expId = (int)$killRow['expedition_id'];
            if (!isset($killsByExpedition[$expId])) {
                $killsByExpedition[$expId] = [];
            }
            $killsByExpedition[$expId][] = $killRow;
        }
    }
    
    // Mostrar el formulario de filtros y la tabla
    echo '<section class="card"><h2>Expediciones</h2>';
    
    // Formulario de filtros
    echo '<form class="table-filters" method="get" action="' . h(current_path()) . '">';
    echo '<input type="hidden" name="view" value="expeditions">';
    echo '<input type="hidden" name="page" value="1">';
    echo '<input type="hidden" name="col_order" value="' . h(query_raw('col_order')) . '">';
    echo '<input type="hidden" name="kcol_order" value="' . h(query_raw('kcol_order')) . '">';
    echo '<input type="hidden" name="hcol_order" value="' . h(query_raw('hcol_order')) . '">';
    
    echo '<select name="player_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Jugador (todos)" data-check-combo-many-label="jugadores">';
    echo '<option value="">Jugador (todos)</option>';
    foreach (player_name_suggestions() as $name) {
        $selected = in_array($name, $playerNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    
    echo '<select name="reserve_name[]" multiple data-check-combo="1" data-check-combo-placeholder="Reserva (todas)" data-check-combo-many-label="reservas">';
    echo '<option value="">Reserva (todas)</option>';
    foreach (reserve_name_suggestions() as $name) {
        $selected = in_array($name, $reserveNames, true) ? ' selected' : '';
        echo '<option value="' . h($name) . '"' . $selected . '>' . h($name) . '</option>';
    }
    echo '</select>';
    
    echo '<input type="date" name="date_from" placeholder="Fecha inicio" value="' . h($startAtFrom ?? '') . '">';
    echo '<input type="date" name="date_to" placeholder="Fecha fin" value="' . h($endAtTo ?? '') . '">';
    
    echo '<details class="filter-details visible-columns" data-col-prefix="col_" data-order-field="col_order"><summary>Columnas Expediciones</summary><div class="visible-row">';
    foreach ($columnDefs as $key => $def) {
        $checked = in_array($key, $selectedCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '">';
        echo '<input class="col-check" type="checkbox" name="col_' . h($key) . '" value="1"' . $checked . '>';
        echo '<span>' . h($def['label']) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultCols)) . '">Restablecer</button>';
    echo '</div></details>';
    
    echo '<details class="filter-details visible-columns" data-col-prefix="kcol_" data-order-field="kcol_order"><summary>Columnas Muertes</summary><div class="visible-row">';
    foreach ($killColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedKillCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '">';
        echo '<input class="col-check" type="checkbox" name="kcol_' . h($key) . '" value="1"' . $checked . '>';
        echo '<span>' . h($label) . '</span></label>';
    }
    echo '<button type="button" class="btn-reset-cols" data-default-cols="' . h(implode(',', $defaultKillCols)) . '">Restablecer</button>';
    echo '</div></details>';
    
    echo '<details class="filter-details visible-columns" data-col-prefix="hcol_" data-order-field="hcol_order"><summary>Columnas Disparos</summary><div class="visible-row">';
    foreach ($hitColumnDefs as $key => $label) {
        $checked = in_array($key, $selectedHitCols, true) ? ' checked' : '';
        echo '<label class="visible-item" draggable="true" data-col-key="' . h($key) . '">';
        echo '<input class="col-check" type="checkbox" name="hcol_' . h($key) . '" value="1"' . $checked . '>';
        echo '<span>' . h($label) . '</span></label>';
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
    
    // Tabla de resultados con subtablas
    if ($rows === []) {
        echo '<p class="muted">No hay expediciones con los filtros actuales.</p>';
    } else {
        echo '<div class="table-responsive">';
        echo '<table><thead><tr>';
        foreach ($selectedCols as $key) {
            echo '<th>' . h($columnDefs[$key]['label']) . '</th>';
        }
        echo '<th>Muertes</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($rows as $row) {
            $expId = (int)$row['expedition_id'];
            $killRows = $killsByExpedition[$expId] ?? [];
            $playerName = $row['player_name'] ?? '';
            
            echo '<tr>';
            foreach ($selectedCols as $key) {
                $value = $row[$key] ?? '';
                if ($key === 'player_name') {
                    $value = player_profile_link_html($value, $value);
                } elseif (in_array($key, ['start_at', 'end_at'])) {
                    $value = format_datetime_display($value);
                }
                echo '<td>' . ((string)$value !== '' ? h((string)$value) : '-') . '</td>';
            }
            
            // Columna de muertes con subtabla expandible
            echo '<td class="col-align-left">';
            if ($killRows === []) {
                echo '<span class="muted">Sin muertes</span>';
            } else {
                $expOpenAttr = isset($openExpSet[$expId]) ? ' open' : '';
                echo '<details class="exp-kills-details" data-exp-id="' . h((string)$expId) . '"' . $expOpenAttr . '>';
                echo '<summary>Expand (' . h((string)count($killRows)) . ')</summary>';
                
                // Tabla de muertes
                echo '<tr><thead><tr>';
                foreach ($selectedKillCols as $colKey) {
                    echo '<th>' . h($killColumnDefs[$colKey] ?? $colKey) . '</th>';
                }
                echo '<th>Disparos</th>';
                echo '</tr></thead><tbody>';
                
                foreach ($killRows as $krow) {
                    echo '<tr>';
                    foreach ($selectedKillCols as $colKey) {
                        $value = $krow[$colKey] ?? '';
                        if ($colKey === 'weight' && is_numeric($value)) {
                            $value = number_format((float)$value, 3, '.', '');
                        } elseif ($colKey === 'score' && is_numeric($value)) {
                            $value = number_format((float)$value, 4, '.', '');
                        } elseif ($colKey === 'hit_min_distance' && is_numeric($value)) {
                            $value = number_format((float)$value, 3, '.', '');
                        }
                        echo '<td>' . h((string)$value) . '</td>';
                    }
                    
                    // Columna de disparos (expansible)
                    echo '<td>';
                    echo '<details class="kill-hits-details">';
                    echo '<summary>Ver disparos (' . h((string)($krow['hit_count'] ?? 0)) . ')</summary>';
                    echo '<p class="muted">Detalle de disparos pendiente de implementar</p>';
                    echo '</details>';
                    echo '</td>';
                    
                    echo '</tr>';
                }
                echo '</tbody><tr>';
                echo '</details>';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    render_pagination($page, $pageSize, $totalRows);
    echo '</section>';
}

$cssVersion = (string) @filemtime(__DIR__ . '/style.css');

$sidebarItems = [
    'dashboard' => 'Panel',
    'expeditions' => 'Expediciones',
    'best' => 'Mejores Marcas',
    'profiles' => 'Estadisticas',
    'competitions' => 'Competiciones',
    'advanced' => 'Consulta Avanzada',
];

if (app_is_admin_user()) {
    $sidebarItems['logs'] = 'Logs';
}

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
    <title>THC GPT Panel (Reconstruido)</title>
    <link rel="stylesheet" href="style.css?v=<?= h($cssVersion) ?>">
</head>
<body class="theme-<?= h($theme) ?> font-<?= h($font) ?>">
<div class="layout">
    <aside class="sidebar">
        <h1 class="sidebar-logo-wrap"><img class="sidebar-logo" src="assets/logo-thc-query.png" alt="THC Query"></h1>
        <div class="sidebar-user-row">
            <div class="sidebar-user"><?= h(app_auth_username() ?? 'Usuario') ?></div>
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
            case 'logs':
                render_logs();
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