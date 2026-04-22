<?php

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

$pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$limit = 0;
$offset = 0;
$sleepMs = 250;
$from = 'all';
$pendingOnly = true;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(0, (int) substr($arg, 8));
    } elseif (str_starts_with($arg, '--offset=')) {
        $offset = max(0, (int) substr($arg, 9));
    } elseif (str_starts_with($arg, '--sleep-ms=')) {
        $sleepMs = max(0, (int) substr($arg, 11));
    } elseif (str_starts_with($arg, '--from=')) {
        $from = strtolower(trim(substr($arg, 7)));
    } elseif ($arg === '--all') {
        $pendingOnly = false;
    } elseif ($arg === '--pending-only') {
        $pendingOnly = true;
    }
}

if (!in_array($from, ['all', 'exp', 'clas'], true)) {
    $from = 'all';
}

$root = dirname(__DIR__);
$outDir = $root . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . 'kill_url_scrape';
$pagesDir = $outDir . DIRECTORY_SEPARATOR . 'pages';
$logsDir = $root . DIRECTORY_SEPARATOR . 'logs';
if (!is_dir($pagesDir)) {
    mkdir($pagesDir, 0777, true);
}
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0777, true);
}

$resultsCsv = $outDir . DIRECTORY_SEPARATOR . 'results.csv';
$runLog = $logsDir . DIRECTORY_SEPARATOR . 'scrape_kill_urls.log';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS gpt.scrape_kill_urls (
        id BIGSERIAL PRIMARY KEY,
        run_at TIMESTAMPTZ NOT NULL,
        source TEXT NOT NULL,
        ref TEXT NULL,
        url TEXT NOT NULL,
        http_code INTEGER NULL,
        ok BOOLEAN NOT NULL,
        file_name TEXT NULL,
        error TEXT NULL,
        url_type TEXT NULL,
        player_slug TEXT NULL,
        player_name TEXT NULL,
        animal_id BIGINT NULL,
        kill_id BIGINT NULL,
        page_title TEXT NULL,
        page_kind TEXT NULL,
        requires_login BOOLEAN NULL,
        parsed_ok BOOLEAN NULL,
        parsed_summary TEXT NULL,
        created_at TIMESTAMPTZ NOT NULL DEFAULT now()
    )"
);
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS url_type TEXT NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS player_slug TEXT NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS player_name TEXT NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS animal_id BIGINT NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS kill_id BIGINT NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS page_title TEXT NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS page_kind TEXT NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS requires_login BOOLEAN NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS parsed_ok BOOLEAN NULL");
$pdo->exec("ALTER TABLE gpt.scrape_kill_urls ADD COLUMN IF NOT EXISTS parsed_summary TEXT NULL");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_scrape_kill_urls_run_at ON gpt.scrape_kill_urls(run_at DESC)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_scrape_kill_urls_url ON gpt.scrape_kill_urls(url)");
$insertStmt = $pdo->prepare(
    'INSERT INTO gpt.scrape_kill_urls (
        run_at, source, ref, url, http_code, ok, file_name, error,
        url_type, player_slug, player_name, animal_id, kill_id,
        page_title, page_kind, requires_login, parsed_ok, parsed_summary
     ) VALUES (
        :run_at, :source, :ref, :url, :http_code, :ok, :file_name, :error,
        :url_type, :player_slug, :player_name, :animal_id, :kill_id,
        :page_title, :page_kind, :requires_login, :parsed_ok, :parsed_summary
     )'
);

/** @var array<string, array{url:string,source:string,ref:string}> $urlMap */
$urlMap = [];

if ($from === 'all' || $from === 'exp') {
    $rows = $pdo->query(
        "SELECT DISTINCT
            k.kill_id,
            COALESCE(NULLIF(k.player_name, ''), NULLIF(e.player_name, '')) AS player_name
         FROM gpt.exp_kills k
         LEFT JOIN gpt.exp_expeditions e ON e.expedition_id = k.expedition_id
         WHERE k.kill_id IS NOT NULL"
    )->fetchAll();

    foreach ($rows as $row) {
        $killId = (string) ($row['kill_id'] ?? '');
        $player = trim((string) ($row['player_name'] ?? ''));
        if ($killId === '' || $player === '') {
            continue;
        }
        $slug = rawurlencode(strtolower($player));
        $url = 'https://www.thehunter.com/#profile/' . $slug . '/score/' . rawurlencode($killId);
        $urlMap[$url] = ['url' => $url, 'source' => 'exp_kills', 'ref' => $killId];
    }
}

if ($from === 'all' || $from === 'clas') {
    $rows = $pdo->query(
        "SELECT DISTINCT COALESCE(NULLIF(mark_url, ''), CASE WHEN animal_id IS NOT NULL THEN 'https://www.thehunter.com/#animal/' || animal_id::text ELSE NULL END) AS url,
                animal_id
         FROM gpt.clas_rankings_latest
         UNION
         SELECT DISTINCT COALESCE(NULLIF(mark_url, ''), CASE WHEN animal_id IS NOT NULL THEN 'https://www.thehunter.com/#animal/' || animal_id::text ELSE NULL END) AS url,
                animal_id
         FROM gpt.clas_rankings_history"
    )->fetchAll();

    foreach ($rows as $row) {
        $url = trim((string) ($row['url'] ?? ''));
        if ($url === '') {
            continue;
        }
        $urlMap[$url] = ['url' => $url, 'source' => 'clas', 'ref' => (string) ($row['animal_id'] ?? '')];
    }
}

$urls = array_values($urlMap);
usort($urls, static fn(array $a, array $b): int => strcmp($a['url'], $b['url']));
$total = count($urls);

if ($pendingOnly) {
    $existingOkStmt = $pdo->prepare(
        'SELECT 1
           FROM gpt.v_scrape_kill_urls_latest
          WHERE url = :url
            AND ok = TRUE
            AND (parsed_summary IS NOT NULL OR page_kind IS NOT NULL OR url_type IS NOT NULL)
          LIMIT 1'
    );

    $urls = array_values(array_filter(
        $urls,
        static function (array $item) use ($existingOkStmt): bool {
            $existingOkStmt->execute([':url' => $item['url']]);
            return $existingOkStmt->fetchColumn() === false;
        }
    ));
}

if ($offset > 0) {
    $urls = array_slice($urls, $offset);
}
if ($limit > 0) {
    $urls = array_slice($urls, 0, $limit);
}

$runAt = date(DATE_ATOM);
$headerNeeded = !is_file($resultsCsv);
$fh = fopen($resultsCsv, 'ab');
if ($fh === false) {
    throw new RuntimeException('No se pudo abrir results.csv para escritura');
}
if ($headerNeeded) {
    fputcsv($fh, ['run_at', 'source', 'ref', 'url', 'http_code', 'ok', 'file', 'error']);
}

$ok = 0;
$fail = 0;
$userAgent = $config['api']['user_agent'] ?? 'Mozilla/5.0';
$timeout = (int) ($config['api']['timeout'] ?? 30);

foreach ($urls as $idx => $item) {
    $url = $item['url'];
    $source = $item['source'];
    $ref = $item['ref'];

    $hash = sha1($url);
    $targetFile = $pagesDir . DIRECTORY_SEPARATOR . $hash . '.html.gz';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: {$userAgent}\r\nAccept: text/html,application/xhtml+xml\r\n",
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);

    $error = '';
    $httpCode = '';
    $body = @file_get_contents($url, false, $context);

    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) === 1) {
        $httpCode = $m[1];
    }

    if ($body === false) {
        $fail++;
        $error = 'request_failed';
        $okFlag = '0';
        $fileName = '';
        $parsed = parseKillUrlMetadata($url, '');
    } else {
        $compressed = gzencode($body, 9);
        file_put_contents($targetFile, $compressed === false ? $body : $compressed);
        $ok++;
        $okFlag = '1';
        $fileName = basename($targetFile);
        $parsed = parseKillUrlMetadata($url, $body);
    }

    fputcsv($fh, [$runAt, $source, $ref, $url, $httpCode, $okFlag, $fileName, $error]);

    $insertStmt->execute([
        ':run_at' => $runAt,
        ':source' => $source,
        ':ref' => $ref !== '' ? $ref : null,
        ':url' => $url,
        ':http_code' => $httpCode !== '' ? (int) $httpCode : null,
        ':ok' => $okFlag === '1',
        ':file_name' => $fileName !== '' ? $fileName : null,
        ':error' => $error !== '' ? $error : null,
        ':url_type' => $parsed['url_type'],
        ':player_slug' => $parsed['player_slug'],
        ':player_name' => $parsed['player_name'],
        ':animal_id' => $parsed['animal_id'],
        ':kill_id' => $parsed['kill_id'],
        ':page_title' => $parsed['page_title'],
        ':page_kind' => $parsed['page_kind'],
        ':requires_login' => $parsed['requires_login'],
        ':parsed_ok' => $parsed['parsed_ok'],
        ':parsed_summary' => $parsed['parsed_summary'],
    ]);

    $msg = sprintf("[%s] %d/%d %s %s\n", date('Y-m-d H:i:s'), $idx + 1, count($urls), $okFlag === '1' ? 'OK' : 'ERROR', $url);
    file_put_contents($runLog, $msg, FILE_APPEND);
    fwrite(STDOUT, $msg);

    if ($sleepMs > 0) {
        usleep($sleepMs * 1000);
    }
}

fclose($fh);

fwrite(STDOUT, "\nResumen\n");
fwrite(STDOUT, "Total candidatas (antes de offset/limit): {$total}\n");
fwrite(STDOUT, 'Pendientes tras filtrar existentes OK: ' . count($urls) . "\n");
fwrite(STDOUT, 'Procesadas: ' . count($urls) . "\n");
fwrite(STDOUT, "OK: {$ok}\n");
fwrite(STDOUT, "Errores: {$fail}\n");
fwrite(STDOUT, "CSV: {$resultsCsv}\n");
fwrite(STDOUT, "Paginas: {$pagesDir}\n");
fwrite(STDOUT, "SQL: gpt.scrape_kill_urls\n");

/**
 * @return array{
 *   url_type:?string,
 *   player_slug:?string,
 *   player_name:?string,
 *   animal_id:?int,
 *   kill_id:?int,
 *   page_title:?string,
 *   page_kind:?string,
 *   requires_login:?bool,
 *   parsed_ok:bool,
 *   parsed_summary:string
 * }
 */
function parseKillUrlMetadata(string $url, string $html): array
{
    $parts = parse_url($url);
    $fragment = (string) ($parts['fragment'] ?? '');
    $result = [
        'url_type' => null,
        'player_slug' => null,
        'player_name' => null,
        'animal_id' => null,
        'kill_id' => null,
        'page_title' => null,
        'page_kind' => null,
        'requires_login' => null,
        'parsed_ok' => false,
        'parsed_summary' => 'Sin parsear',
    ];

    if (preg_match('~^animal/(\d+)$~', $fragment, $m) === 1) {
        $result['url_type'] = 'animal';
        $result['animal_id'] = (int) $m[1];
    } elseif (preg_match('~^profile/([^/]+)/score/(\d+)$~', $fragment, $m) === 1) {
        $result['url_type'] = 'profile_score';
        $result['player_slug'] = rawurldecode($m[1]);
        $result['player_name'] = rawurldecode($m[1]);
        $result['kill_id'] = (int) $m[2];
    } elseif ($fragment !== '') {
        $result['url_type'] = 'hash_other';
    } else {
        $result['url_type'] = 'plain';
    }

    if ($html !== '') {
        if (preg_match('~<title>\s*(.*?)\s*</title>~is', $html, $m) === 1) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $result['page_title'] = $title !== '' ? $title : null;
        }

        $htmlLower = strtolower($html);
        if (str_contains($htmlLower, 'id="not-signed-in"') || str_contains($htmlLower, 'already a hunter?')) {
            $result['page_kind'] = 'public_home_not_signed_in';
            $result['requires_login'] = true;
            $result['parsed_ok'] = true;
            $result['parsed_summary'] = 'La descarga devuelve la portada publica, no la ficha renderizada';
        } elseif (str_contains($htmlLower, 'id="page_container"')) {
            $result['page_kind'] = 'site_shell';
            $result['requires_login'] = null;
            $result['parsed_ok'] = true;
            $result['parsed_summary'] = 'HTML base del sitio descargado';
        } else {
            $result['page_kind'] = 'unknown_html';
            $result['requires_login'] = null;
            $result['parsed_ok'] = true;
            $result['parsed_summary'] = 'HTML descargado sin patron reconocido';
        }
    } else {
        $result['page_kind'] = 'no_html';
        $result['requires_login'] = null;
        $result['parsed_ok'] = false;
        $result['parsed_summary'] = 'No se pudo descargar HTML';
    }

    return $result;
}
