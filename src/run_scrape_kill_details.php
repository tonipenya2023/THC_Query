<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

if (defined('STDOUT') && is_resource(STDOUT)) {
    @stream_set_write_buffer(STDOUT, 0);
}
if (defined('STDERR') && is_resource(STDERR)) {
    @stream_set_write_buffer(STDERR, 0);
}

$pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$player = null;
$limit = 0;
$pendingOnly = true;
$cookiePlayer = null;
$existingOnly = false;
$killIdFilter = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with((string) $arg, '--player=')) {
        $player = trim((string) substr((string) $arg, 9));
    } elseif (str_starts_with((string) $arg, '--kill-id=')) {
        $killIdFilter = max(0, (int) substr((string) $arg, 10));
    } elseif (str_starts_with((string) $arg, '--limit=')) {
        $limit = max(0, (int) substr((string) $arg, 8));
    } elseif (str_starts_with((string) $arg, '--cookie-player=')) {
        $cookiePlayer = trim((string) substr((string) $arg, 16));
    } elseif ((string) $arg === '--existing-only') {
        $existingOnly = true;
    } elseif ((string) $arg === '--all') {
        $pendingOnly = false;
    } elseif ((string) $arg === '--pending-only') {
        $pendingOnly = true;
    }
}

if ($cookiePlayer === null || $cookiePlayer === '') {
    $cookiePlayer = $player;
}

if ($cookiePlayer === null || trim((string) $cookiePlayer) === '') {
    log_err("Error: Debes indicar --cookie-player=<usuario_con_cookie>");
    exit(1);
}

const COOKIE_FILE = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'thehunter_cookies.json';

$cookie = loadTheHunterCookie((string) $cookiePlayer);
if ($cookie === '') {
    log_err("Error: No hay cookie guardada para {$cookiePlayer}");
    exit(1);
}
$cookieFile = COOKIE_FILE;
$cookieKey = mb_strtolower((string) $cookiePlayer, 'UTF-8');

$where = [];
$params = [];
if ($player !== null && $player !== '') {
    $where[] = $existingOnly
        ? "LOWER(COALESCE(NULLIF(kd.player_name, ''), '')) = LOWER(:player_name)"
        : "LOWER(COALESCE(NULLIF(k.player_name, ''), NULLIF(e.player_name, ''))) = LOWER(:player_name)";
    $params[':player_name'] = $player;
}
if ($killIdFilter !== null && $killIdFilter > 0) {
    $where[] = $existingOnly
        ? 'kd.kill_id = :kill_id'
        : 'k.kill_id = :kill_id';
    $params[':kill_id'] = $killIdFilter;
}

if ($existingOnly) {
    $where[] = "kd.kill_id IS NOT NULL";
    $where[] = "COALESCE(NULLIF(kd.player_name, ''), '') <> ''";
    $sql = "SELECT
                kd.kill_id,
                kd.player_name,
                kd.species_name
            FROM gpt.v_kill_detail_scrapes_latest kd
            WHERE " . implode(' AND ', $where) . "
            ORDER BY kd.kill_id DESC";
} else {
    $where[] = "k.kill_id IS NOT NULL";
    $where[] = "COALESCE(NULLIF(k.player_name, ''), NULLIF(e.player_name, '')) <> ''";
    $sql = "SELECT
                k.kill_id,
                COALESCE(NULLIF(k.player_name, ''), NULLIF(e.player_name, '')) AS player_name,
                COALESCE(NULLIF(sp.especie_es, ''), NULLIF(sp.especie, ''), NULLIF(k.species_name, '')) AS species_name
            FROM gpt.exp_kills k
            LEFT JOIN gpt.exp_expeditions e ON e.expedition_id = k.expedition_id
            LEFT JOIN gpt.tab_especies sp ON sp.id_especie = k.species_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY k.kill_id DESC";
}

$rows = $pdo->prepare($sql);
$rows->execute($params);
$candidates = $rows->fetchAll();

if ($pendingOnly && $candidates !== []) {
    // Una sola consulta para obtener todos los kill_id ya procesados,
    // evitando el patrón N+1 de consultas individuales por candidato.
    $seenStmt = $pdo->query(
        'SELECT LOWER(player_name) || \'#\' || kill_id AS seen_key
         FROM gpt.v_kill_detail_scrapes_latest
         WHERE player_name IS NOT NULL AND kill_id IS NOT NULL'
    );
    $seenKeys = array_flip($seenStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    $candidates = array_values(array_filter($candidates, static function (array $row) use ($seenKeys): bool {
        $key = mb_strtolower((string) ($row['player_name'] ?? ''), 'UTF-8') . '#' . (int) ($row['kill_id'] ?? 0);
        return !isset($seenKeys[$key]);
    }));
}

if ($limit > 0) {
    $candidates = array_slice($candidates, 0, $limit);
}

$insert = $pdo->prepare(
    'INSERT INTO gpt.kill_detail_scrapes (
        kill_id, player_name, url, scraped_at, species_name, hunter_name, weapon_text, scope_text, ammo_text,
        shot_distance_text, animal_state_text, body_part_text, posture_text, platform_text, shot_location_text,
        weight_text, type_text, wound_time_text, trophy_integrity_text, shot_count_text, capture_time_text,
        trophy_score_text, harvest_value_text, page_title, render_url, raw_body_text, raw_html, kill_data_json
     ) VALUES (
        :kill_id, :player_name, :url, NOW(), :species_name, :hunter_name, :weapon_text, :scope_text, :ammo_text,
        :shot_distance_text, :animal_state_text, :body_part_text, :posture_text, :platform_text, :shot_location_text,
        :weight_text, :type_text, :wound_time_text, :trophy_integrity_text, :shot_count_text, :capture_time_text,
        :trophy_score_text, :harvest_value_text, :page_title, :render_url, :raw_body_text, :raw_html, CAST(:kill_data_json AS JSONB)
     )'
);

$tmpDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'kill_detail_tmp';
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0777, true);
} else {
    // Limpiar archivos temporales huérfanos de ejecuciones anteriores (más de 1 hora)
    $staleThreshold = time() - 3600;
    foreach (glob($tmpDir . DIRECTORY_SEPARATOR . 'batch_*') ?: [] as $staleFile) {
        if (is_file($staleFile) && filemtime($staleFile) < $staleThreshold) {
            @unlink($staleFile);
        }
    }
}

$processed = 0;
$errors = 0;
$batchSize = 100;

for ($offset = 0, $totalCandidates = count($candidates); $offset < $totalCandidates; $offset += $batchSize) {
    $slice = array_slice($candidates, $offset, $batchSize);
    $jobs = [];
    $rowsByKey = [];

    foreach ($slice as $row) {
        $killId = (int) ($row['kill_id'] ?? 0);
        $playerName = trim((string) ($row['player_name'] ?? ''));
        if ($killId <= 0 || $playerName === '') {
            continue;
        }
        $url = buildKillUrl($playerName, $killId);
        $job = [
            'kill_id' => $killId,
            'player_name' => $playerName,
            'url' => $url,
        ];
        $jobs[] = $job;
        $rowsByKey[$playerName . '#' . $killId] = $row;
    }

    if ($jobs === []) {
        continue;
    }

    $batchInput = $tmpDir . DIRECTORY_SEPARATOR . 'batch_' . $offset . '_' . bin2hex(random_bytes(4)) . '.json';
    $batchOutput = $tmpDir . DIRECTORY_SEPARATOR . 'batch_' . $offset . '_' . bin2hex(random_bytes(4)) . '_out.json';
    file_put_contents($batchInput, json_encode($jobs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $batchStderr = $tmpDir . DIRECTORY_SEPARATOR . 'batch_' . $offset . '_stderr.log';
    $cmd = 'node ' . escapeshellarg(__DIR__ . DIRECTORY_SEPARATOR . 'scrape_kill_detail_browser.mjs')
        . ' --input=' . escapeshellarg($batchInput)
        . ' --cookieFile=' . escapeshellarg($cookieFile)
        . ' --cookieKey=' . escapeshellarg($cookieKey)
        . ' --output=' . escapeshellarg($batchOutput)
        . ' 2>' . escapeshellarg($batchStderr);

    exec($cmd, $out, $exitCode);
    @unlink($batchInput);
    if ($exitCode !== 0 || !is_file($batchOutput)) {
        $nodeErr = is_file($batchStderr) ? trim((string) file_get_contents($batchStderr)) : '';
        log_err("[ERROR] Lote {$offset} browser scrape failed" . ($nodeErr !== '' ? ": {$nodeErr}" : ''));
        $errors += count($jobs);
        @unlink($batchOutput);
        @unlink($batchStderr);
        continue;
    }
    @unlink($batchStderr);

    $results = json_decode((string) file_get_contents($batchOutput), true);
    @unlink($batchOutput);
    if (!is_array($results)) {
        log_err("[ERROR] Lote {$offset} invalid JSON output");
        $errors += count($jobs);
        continue;
    }

    foreach ($results as $result) {
        $killId = (int) ($result['kill_id'] ?? 0);
        $playerName = trim((string) ($result['player_name'] ?? ''));
        $key = $playerName . '#' . $killId;
        $row = $rowsByKey[$key] ?? null;
        if (!is_array($row) || $killId <= 0 || $playerName === '') {
            $errors++;
            continue;
        }

        $url = buildKillUrl($playerName, $killId);
        if (!($result['ok'] ?? false) || !is_array($result['payload'] ?? null)) {
            log_err("[ERROR] {$playerName} {$killId} browser scrape failed");
            $errors++;
            continue;
        }

        $payload = $result['payload'];
        $parsed = parseKillDetailPayload($payload);
        $insert->execute([
            ':kill_id' => $killId,
            ':player_name' => $playerName,
            ':url' => $url,
            ':species_name' => $parsed['species_name'] ?: ($row['species_name'] ?? null),
            ':hunter_name' => $parsed['hunter_name'],
            ':weapon_text' => $parsed['weapon_text'],
            ':scope_text' => $parsed['scope_text'],
            ':ammo_text' => $parsed['ammo_text'],
            ':shot_distance_text' => $parsed['shot_distance_text'],
            ':animal_state_text' => $parsed['animal_state_text'],
            ':body_part_text' => $parsed['body_part_text'],
            ':posture_text' => $parsed['posture_text'],
            ':platform_text' => $parsed['platform_text'],
            ':shot_location_text' => $parsed['shot_location_text'],
            ':weight_text' => $parsed['weight_text'],
            ':type_text' => $parsed['type_text'],
            ':wound_time_text' => $parsed['wound_time_text'],
            ':trophy_integrity_text' => $parsed['trophy_integrity_text'],
            ':shot_count_text' => $parsed['shot_count_text'],
            ':capture_time_text' => $parsed['capture_time_text'],
            ':trophy_score_text' => $parsed['trophy_score_text'],
            ':harvest_value_text' => $parsed['harvest_value_text'],
            ':page_title' => (string) ($payload['title'] ?? ''),
            ':render_url' => (string) ($payload['url'] ?? ''),
            ':raw_body_text' => (string) ($payload['bodyText'] ?? ''),
            ':raw_html' => (string) ($payload['html'] ?? ''),
            ':kill_data_json' => json_encode($parsed['kill_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $processed++;
        log_out("[OK] {$playerName} {$killId}");
    }
}

log_out('');
log_out('Resumen');
log_out("Procesadas: {$processed}");
log_out("Errores: {$errors}");

function buildKillUrl(string $playerName, int $killId): string
{
    return 'https://www.thehunter.com/#profile/'
        . rawurlencode(strtolower($playerName))
        . '/score/'
        . rawurlencode((string) $killId);
}

function log_out(string $message): void
{
    if (defined('STDOUT') && is_resource(STDOUT)) {
        fwrite(STDOUT, $message . PHP_EOL);
        @fflush(STDOUT);
    }
}

function log_err(string $message): void
{
    if (defined('STDERR') && is_resource(STDERR)) {
        fwrite(STDERR, $message . PHP_EOL);
        @fflush(STDERR);
    }
}

function loadTheHunterCookie(string $playerName): string
{
    $file = COOKIE_FILE;
    if (!is_file($file)) {
        return '';
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        return '';
    }
    $key = mb_strtolower($playerName, 'UTF-8');
    $value = $data[$key] ?? null;
    return is_string($value) ? trim($value) : '';
}

/**
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function parseKillDetailPayload(array $payload): array
{
    $html = (string) ($payload['html'] ?? '');
    $bodyText = normalize_mojibake((string) ($payload['bodyText'] ?? ''));
    $parsedHitRows = parseShotsTableRows($html);
    $out = [
        'species_name' => null,
        'hunter_name' => null,
        'weapon_text' => null,
        'scope_text' => null,
        'ammo_text' => null,
        'shot_distance_text' => null,
        'animal_state_text' => null,
        'body_part_text' => null,
        'posture_text' => null,
        'platform_text' => null,
        'shot_location_text' => null,
        'weight_text' => null,
        'type_text' => null,
        'wound_time_text' => null,
        'trophy_integrity_text' => null,
        'shot_count_text' => null,
        'capture_time_text' => null,
        'trophy_score_text' => null,
        'harvest_value_text' => null,
        'kill_data' => null,
    ];

    if (preg_match('~<div class="species-title">(.*?)</div>~is', $html, $m) === 1) {
        $out['species_name'] = cleanHtmlText($m[1]);
    }

    if ($parsedHitRows !== []) {
        $firstShot = $parsedHitRows[0];
        $out['hunter_name'] = $firstShot['hunter_name'] ?? null;
        $out['weapon_text'] = $firstShot['weapon_text'] ?? null;
        $out['scope_text'] = $firstShot['scope_text'] ?? null;
        $out['ammo_text'] = $firstShot['ammo_text'] ?? null;
        $out['shot_distance_text'] = $firstShot['shot_distance_text'] ?? null;
        $out['animal_state_text'] = $firstShot['animal_state_text'] ?? null;
        $out['body_part_text'] = $firstShot['body_part_text'] ?? null;
        $out['posture_text'] = $firstShot['posture_text'] ?? null;
        $out['platform_text'] = $firstShot['platform_text'] ?? null;
    }

    if (preg_match('~<table class="summary".*?</table>~is', $html, $m) === 1) {
        $summaryHtml = $m[0];
        $summaryText = normalize_mojibake(cleanHtmlText($summaryHtml));
        $summaryRows = parseSummaryRows($summaryHtml);
        $out['weight_text'] = $summaryRows['weight_text'] ?? extractLabelValue($summaryText, 'Peso:');
        $out['type_text'] = $summaryRows['type_text'] ?? extractLabelValue($summaryText, 'Tipo:');
        $out['wound_time_text'] = $summaryRows['wound_time_text'] ?? extractLabelValue($summaryText, 'Tiempo de la herida:');
        $out['trophy_integrity_text'] = $summaryRows['trophy_integrity_text'] ?? extractLabelValue($summaryText, 'Integridad del trofeo:');
        $out['shot_location_text'] = $summaryRows['shot_location_text'] ?? extractLabelValue($summaryText, 'Lugar donde se realizo el tiro del jugador:', 'Lugar donde se realizo el tiro del jugador');
        $out['shot_count_text'] = $summaryRows['shot_count_text'] ?? extractLabelValue($summaryText, 'Disparos:');
        $out['capture_time_text'] = $summaryRows['capture_time_text'] ?? extractLabelValue($summaryText, 'Tiempo de Captura:');
        $out['hunter_name'] = $out['hunter_name'] ?? ($summaryRows['hunter_name'] ?? null);
        $out['trophy_score_text'] = extractFirstMatch($summaryText, '/Trophy score:\s*([0-9\.,]+)/iu');
        $out['harvest_value_text'] = extractFirstMatch($summaryText, '/Valor de la captura:\s*([0-9\.,]+)/iu');
    }

    if (preg_match('~var killData = (\{.*?\})\s*,\s*strs =~s', $html, $m) === 1) {
        $json = json_decode($m[1], true);
        if (is_array($json)) {
            if ($parsedHitRows !== []) {
                $json['parsed_hits'] = $parsedHitRows;
            }
            $out['kill_data'] = $json;
        }
    }

    if ($out['kill_data'] === null && $parsedHitRows !== []) {
        $out['kill_data'] = ['parsed_hits' => $parsedHitRows];
    }

    if ($out['capture_time_text'] === null && preg_match('/Tiempo de Captura:\s*(.*?)\s*Cazador:/isu', $bodyText, $m) === 1) {
        $out['capture_time_text'] = trim($m[1]);
    }

    if ($out['shot_location_text'] === null && preg_match('/Lugar donde se realizo el tiro del jugador:\s*(.*?)\s*Disparos:/isu', $bodyText, $m) === 1) {
        $out['shot_location_text'] = trim($m[1]);
    }

    if (is_array($out['kill_data'])) {
        $hit = (isset($out['kill_data']['hits'][0]) && is_array($out['kill_data']['hits'][0])) ? $out['kill_data']['hits'][0] : null;
        $kill = (isset($out['kill_data']['kill']) && is_array($out['kill_data']['kill'])) ? $out['kill_data']['kill'] : null;
        $expedition = (isset($out['kill_data']['expedition'][0]) && is_array($out['kill_data']['expedition'][0])) ? $out['kill_data']['expedition'][0] : null;

        if ($out['species_name'] === null) {
            $out['species_name'] = scalar_text($out['kill_data']['species'] ?? null);
        }
        if ($out['hunter_name'] === null && is_array($hit)) {
            $out['hunter_name'] = scalar_text($hit['handle'] ?? null);
        }
        if ($out['shot_distance_text'] === null && is_array($hit)) {
            $distanceRaw = $hit['distance'] ?? null;
            if (is_numeric((string) $distanceRaw)) {
                $out['shot_distance_text'] = number_format(((float) $distanceRaw) / 1000.0, 3, '.', '') . ' m';
            } else {
                $out['shot_distance_text'] = scalar_text($distanceRaw);
            }
        }
        if ($out['weapon_text'] === null && is_array($hit)) {
            $out['weapon_text'] = resolve_weapon_label($hit['weapon_id'] ?? null);
        }
        if ($out['ammo_text'] === null && is_array($hit)) {
            $out['ammo_text'] = resolve_ammo_label($hit['ammo_id'] ?? null);
        }
        if ($out['scope_text'] === null && is_array($hit)) {
            $out['scope_text'] = resolve_scope_label($hit['scope_id'] ?? null);
        }
        if ($out['animal_state_text'] === null && is_array($hit)) {
            $out['animal_state_text'] = resolve_animal_state_label($hit['animal_state'] ?? null);
        }
        if ($out['body_part_text'] === null && is_array($hit)) {
            $out['body_part_text'] = resolve_bodypart_label($hit['bodypart'] ?? null);
        }
        if ($out['posture_text'] === null && is_array($hit)) {
            $out['posture_text'] = resolve_pose_label($hit['pose'] ?? null);
        }
        if ($out['platform_text'] === null && is_array($hit)) {
            $out['platform_text'] = resolve_platform_label($hit['platform'] ?? null);
        }
        if ($out['weight_text'] === null) {
            $weightRaw = $out['kill_data']['weight'] ?? null;
            if (is_numeric((string) $weightRaw)) {
                $out['weight_text'] = number_format(((float) $weightRaw) / 1000.0, 3, '.', '') . ' kg';
            } else {
                $out['weight_text'] = scalar_text($weightRaw);
            }
        }
        if ($out['type_text'] === null) {
            $out['type_text'] = resolve_texture_label($out['kill_data']['texture'] ?? null, $out['kill_data']['gender'] ?? null);
        }
        if ($out['wound_time_text'] === null && is_array($kill)) {
            $out['wound_time_text'] = scalar_text($kill['wound_time'] ?? null);
        }
        if ($out['trophy_integrity_text'] === null && is_array($kill)) {
            $out['trophy_integrity_text'] = scalar_text($kill['trophy_integrity'] ?? null);
        }
        if ($out['shot_count_text'] === null && isset($out['kill_data']['hits']) && is_array($out['kill_data']['hits'])) {
            $out['shot_count_text'] = (string) count($out['kill_data']['hits']);
        }
        if ($out['capture_time_text'] === null && is_array($kill)) {
            $out['capture_time_text'] = scalar_text($kill['ts'] ?? null);
        }
        if ($out['trophy_score_text'] === null && is_array($kill)) {
            $out['trophy_score_text'] = scalar_text($kill['score'] ?? null);
        }
        if ($out['harvest_value_text'] === null && is_array($kill)) {
            $out['harvest_value_text'] = scalar_text($kill['harvest_value'] ?? null);
        }
        if ($out['shot_location_text'] === null && is_array($expedition) && is_array($hit)) {
            $reserve = scalar_text($expedition['reserve'] ?? null);
            $playerX = scalar_text($hit['player_x'] ?? null);
            $playerY = scalar_text($hit['player_y'] ?? null);
            if ($reserve !== null && $playerX !== null && $playerY !== null) {
                $out['shot_location_text'] = $reserve . ' (' . $playerX . ', ' . $playerY . ')';
            }
        }
    }

    return $out;
}

/**
 * @return array<int,array<string,string|null>>
 */
function parseShotsTableRows(string $html): array
{
    if (preg_match('~<table class="scoretable shots".*?<tbody>(.*?)</tbody>~is', $html, $m) !== 1) {
        return [];
    }

    $rowsHtml = $m[1];
    if (preg_match_all('~<tr[^>]*>(.*?)</tr>~is', $rowsHtml, $rowMatches) < 1) {
        return [];
    }

    $rows = [];
    foreach ($rowMatches[1] as $rowHtml) {
        preg_match_all('~<td[^>]*>(.*?)</td>~is', $rowHtml, $cells);
        $vals = array_map('cleanHtmlText', $cells[1] ?? []);
        if (count($vals) < 10) {
            log_err("[WARN] parseShotsTableRows: fila descartada con " . count($vals) . " columnas (se esperan >=10); puede que haya cambiado el formato de la página");
            continue;
        }

        $shotIndexRaw = trim((string) ($vals[0] ?? ''));
        $rows[] = [
            'hit_index' => $shotIndexRaw !== '' ? $shotIndexRaw : null,
            'hunter_name' => $vals[1] ?? null,
            'weapon_text' => $vals[2] ?? null,
            'scope_text' => normalize_scope_cell($vals[3] ?? null, $cells[1][3] ?? null),
            'ammo_text' => $vals[4] ?? null,
            'shot_distance_text' => $vals[5] ?? null,
            'animal_state_text' => $vals[6] ?? null,
            'body_part_text' => $vals[7] ?? null,
            'posture_text' => $vals[8] ?? null,
            'platform_text' => $vals[9] ?? null,
        ];
    }

    return $rows;
}

function normalize_scope_value(?string $value): ?string
{
    $value = $value !== null ? trim($value) : null;
    if ($value === null || $value === '') {
        return $value;
    }
    return $value === '✓' ? 'Si' : $value;
}

function normalize_scope_cell(?string $value, ?string $rawHtml = null): ?string
{
    $value = $value !== null ? trim($value) : null;
    $rawHtml = $rawHtml !== null ? trim($rawHtml) : null;

    $scopeTitle = null;
    if ($rawHtml !== null && preg_match('/\btitle\s*=\s*"([^"]+)"/iu', $rawHtml, $m) === 1) {
        $scopeTitle = cleanHtmlText($m[1]);
    }

    if ($value === null || $value === '') {
        return $scopeTitle;
    }

    $normalized = preg_replace('/\s+/u', '', $value) ?? $value;
    if (in_array($normalized, ['✓', '✔', '✅', 'âœ“'], true)) {
        return $scopeTitle ?? 'Si';
    }

    return $value;
}

function cleanHtmlText(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text ?? '') ?? '';
    return trim(normalize_mojibake($text));
}

function extractLabelValue(string $text, string ...$labels): ?string
{
    foreach ($labels as $label) {
        $quoted = preg_quote($label, '/');
        if (preg_match('/' . $quoted . '\s*(.*?)\s*(?=(?:[A-ZÁÉÍÓÚÑ][^:]{0,60}:)|Trophy score:|Valor de la captura:|$)/isu', $text, $m) === 1) {
            $value = trim($m[1]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return null;
}

function extractFirstMatch(string $text, string $regex): ?string
{
    if (preg_match($regex, $text, $m) === 1) {
        $value = trim((string) ($m[1] ?? ''));
        return $value !== '' ? $value : null;
    }
    return null;
}

function normalize_mojibake(string $text): string
{
    $text = str_replace(
        ['Ã¡', 'Ã©', 'Ã­', 'Ã³', 'Ãº', 'Ã', 'Ã‰', 'Ã', 'Ã“', 'Ãš', 'Ã±', 'Ã‘', 'Â¿', 'Â¡', 'Âº', 'Âª'],
        ['á', 'é', 'í', 'ó', 'ú', 'Á', 'É', 'Í', 'Ó', 'Ú', 'ñ', 'Ñ', '¿', '¡', 'º', 'ª'],
        $text
    );
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function scalar_text(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_scalar($value)) {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
    return null;
}

function resolve_weapon_label(mixed $value): ?string
{
    $id = is_numeric((string) $value) ? (int) $value : null;
    return $id === null ? scalar_text($value) : 'Arma ID ' . $id;
}

function resolve_ammo_label(mixed $value): ?string
{
    $id = is_numeric((string) $value) ? (int) $value : null;
    return $id === null ? scalar_text($value) : 'Municion ID ' . $id;
}

function resolve_scope_label(mixed $value): ?string
{
    $id = is_numeric((string) $value) ? (int) $value : null;
    return $id === null ? scalar_text($value) : 'Visor ID ' . $id;
}

function resolve_platform_label(mixed $value): ?string
{
    $id = is_numeric((string) $value) ? (int) $value : null;
    $map = [
        0 => 'Nada',
    ];
    if ($id === null) {
        return scalar_text($value);
    }
    return $map[$id] ?? ('Plataforma ' . $id);
}

function resolve_animal_state_label(mixed $value): ?string
{
    $id = is_numeric((string) $value) ? (int) $value : null;
    $map = [
        0 => 'Quieto',
        1 => 'Alerta',
        2 => 'Caminando',
        3 => 'Trotando',
        4 => 'Huyendo',
        5 => 'Corriendo',
        6 => 'Volando',
    ];
    if ($id === null) {
        return scalar_text($value);
    }
    return $map[$id] ?? ('Estado ' . $id);
}

function resolve_bodypart_label(mixed $value): ?string
{
    $id = is_numeric((string) $value) ? (int) $value : null;
    $map = [
        0 => 'Organos',
        1 => '-',
        2 => 'Cabeza',
        3 => 'Cuello',
        4 => 'Torso',
        5 => 'Pata delantera',
        6 => 'Pata trasera',
    ];
    if ($id === null) {
        return scalar_text($value);
    }
    return $map[$id] ?? ('Parte ' . $id);
}

function resolve_pose_label(mixed $value): ?string
{
    $id = is_numeric((string) $value) ? (int) $value : null;
    $map = [
        0 => 'De pie',
        1 => 'Agazapado',
        2 => 'Tumbado',
    ];
    if ($id === null) {
        return scalar_text($value);
    }
    return $map[$id] ?? ('Postura ' . $id);
}

function resolve_texture_label(mixed $textureValue, mixed $genderValue): ?string
{
    $textureId = is_numeric((string) $textureValue) ? (int) $textureValue : null;
    $genderId = is_numeric((string) $genderValue) ? (int) $genderValue : null;
    if ($textureId === null && $genderId === null) {
        return null;
    }
    $genderLabel = match ($genderId) {
        0 => 'M',
        1 => 'F',
        default => '?',
    };
    if ($textureId === 0) {
        return 'Comun (' . $genderLabel . ')';
    }
    return 'Variante ' . (string) $textureId . ' (' . $genderLabel . ')';
}

/**
 * @return array<string,string|null>
 */
function parseSummaryRows(string $summaryHtml): array
{
    $out = [
        'weight_text' => null,
        'type_text' => null,
        'wound_time_text' => null,
        'trophy_integrity_text' => null,
        'shot_location_text' => null,
        'shot_count_text' => null,
        'capture_time_text' => null,
        'hunter_name' => null,
    ];

    if (!class_exists(DOMDocument::class)) {
        return $out;
    }

    $dom = new DOMDocument();
    $loaded = @$dom->loadHTML('<html><body>' . $summaryHtml . '</body></html>');
    if ($loaded !== true) {
        return $out;
    }

    $xpath = new DOMXPath($dom);
    $rows = $xpath->query('//table[contains(@class,"summary")]//tr');
    if ($rows === false) {
        return $out;
    }

    foreach ($rows as $row) {
        $cells = $xpath->query('./td', $row);
        if ($cells === false || $cells->length === 0) {
            continue;
        }

        $labels = [];
        $values = [];
        foreach ($cells as $cell) {
            $classAttr = (string) ($cell->attributes?->getNamedItem('class')?->nodeValue ?? '');
            $text = cleanHtmlText($dom->saveHTML($cell) ?: '');
            if ($text === '') {
                continue;
            }
            if (str_contains($classAttr, 'value')) {
                $values[] = $text;
            } else {
                $labels[] = $text;
            }
        }

        if ($labels === [] || $values === []) {
            continue;
        }

        foreach ($labels as $idx => $label) {
            $key = canonical_summary_label($label);
            if ($key === null) {
                continue;
            }
            $out[$key] = $values[$idx] ?? $values[0] ?? $out[$key];
        }
    }

    if (preg_match('~<tr>\s*<td[^>]*class="value"[^>]*>\s*<a[^>]*>(.*?)</a>\s*</td>\s*<td[^>]*class="value"[^>]*>(.*?)</td>\s*</tr>~is', $summaryHtml, $m) === 1) {
        $out['capture_time_text'] = cleanHtmlText($m[1]);
        $out['hunter_name'] = cleanHtmlText($m[2]);
    }

    return $out;
}

function canonical_summary_label(string $label): ?string
{
    $normalized = mb_strtolower(normalize_mojibake($label), 'UTF-8');
    $normalized = str_replace([':', '.'], '', $normalized);
    $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
    $normalized = trim($normalized);

    return match ($normalized) {
        'peso' => 'weight_text',
        'tipo' => 'type_text',
        'tiempo de la herida' => 'wound_time_text',
        'integridad del trofeo' => 'trophy_integrity_text',
        'lugar donde se realizo el tiro del jugador', 'lugar donde se realizó el tiro del jugador' => 'shot_location_text',
        'disparos' => 'shot_count_text',
        'tiempo de captura' => 'capture_time_text',
        'cazador' => 'hunter_name',
        default => null,
    };
}
