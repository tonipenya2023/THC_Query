<?php

declare(strict_types=1);

require __DIR__ . '/web_bootstrap.php';

/**
 * Translate competition descriptions to Spanish using Google Translate endpoint
 * and persist into gpt.comp_types.description_es.
 *
 * Usage:
 *   php run_translate_competition_descriptions.php
 *   php run_translate_competition_descriptions.php --force
 *   php run_translate_competition_descriptions.php --from=en --to=es
 */

function cli_arg(string $name): ?string
{
    global $argv;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--' . $name . '=')) {
            return trim((string) substr($arg, strlen($name) + 3));
        }
    }
    return null;
}

function cli_has_flag(string $name): bool
{
    global $argv;
    return in_array('--' . $name, $argv, true);
}

function google_translate_batch(array $texts, string $from = 'auto', string $to = 'es'): array
{
    $texts = array_values(array_filter(array_map(
        static fn ($text): string => trim((string) $text),
        $texts
    ), static fn (string $text): bool => $text !== ''));

    if ($texts === []) {
        return [];
    }

    $url = 'https://translate.googleapis.com/translate_a/t?client=gtx'
        . '&sl=' . rawurlencode($from)
        . '&tl=' . rawurlencode($to)
        . '&dj=1'
        . '&source=input'
        . '&dt=t';

    $postFields = http_build_query(array_merge(
        ['client' => 'gtx', 'sl' => $from, 'tl' => $to, 'dj' => '1', 'source' => 'input', 'dt' => 't'],
        array_reduce(
            $texts,
            static function (array $carry, string $text): array {
                $carry['q'][] = $text;
                return $carry;
            },
            []
        )
    ));

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 15,
            'header' => "User-Agent: THC-Query-Translator/1.0\r\n"
                . "Accept: application/json\r\n"
                . "Content-Type: application/x-www-form-urlencoded; charset=UTF-8\r\n",
            'content' => $postFields,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        return [];
    }

    $sentences = $json['sentences'] ?? null;
    if (!is_array($sentences)) {
        return [];
    }

    $translated = [];
    $current = '';
    foreach ($sentences as $segment) {
        if (!is_array($segment)) {
            continue;
        }
        $trans = isset($segment['trans']) && is_string($segment['trans']) ? $segment['trans'] : '';
        $orig = isset($segment['orig']) && is_string($segment['orig']) ? $segment['orig'] : '';
        if ($orig === '') {
            $current .= $trans;
            continue;
        }
        $current .= $trans;
        $translated[] = trim($current);
        $current = '';
    }

    if ($current !== '') {
        $translated[] = trim($current);
    }

    if (count($translated) !== count($texts)) {
        return [];
    }

    return $translated;
}

$force = cli_has_flag('force');
$from = cli_arg('from') ?? 'auto';
$to = cli_arg('to') ?? 'es';
$sleepMs = (int) (cli_arg('sleep_ms') ?? '250');
if ($sleepMs < 0) {
    $sleepMs = 0;
}

$rows = app_query_all(
    'SELECT competition_type_id, type_name, description_short, description_es
     FROM gpt.comp_types
     ORDER BY competition_type_id'
);

$toProcess = [];
foreach ($rows as $row) {
    $src = trim((string) ($row['description_short'] ?? ''));
    $dst = trim((string) ($row['description_es'] ?? ''));
    if ($src === '') {
        continue;
    }
    if (!$force && $dst !== '') {
        continue;
    }
    $toProcess[] = $row;
}

$total = count($toProcess);
echo 'Competiciones con descripcion a traducir: ' . $total . PHP_EOL;
if ($total === 0) {
    exit(0);
}

$update = app_pdo()->prepare(
    'UPDATE gpt.comp_types
     SET description_es = :description_es,
         updated_at = NOW()
     WHERE competition_type_id = :competition_type_id'
);

$cache = [];
$ok = 0;
$fail = 0;

$pendingTexts = [];
foreach ($toProcess as $row) {
    $src = trim((string) ($row['description_short'] ?? ''));
    if ($src !== '' && !array_key_exists($src, $cache) && !array_key_exists($src, $pendingTexts)) {
        $pendingTexts[$src] = true;
    }
}

$uniqueTexts = array_keys($pendingTexts);
if ($uniqueTexts !== []) {
    $translatedBatch = google_translate_batch($uniqueTexts, $from, $to);
    if (count($translatedBatch) === count($uniqueTexts)) {
        foreach ($uniqueTexts as $idx => $src) {
            $cache[$src] = $translatedBatch[$idx] ?? '';
        }
    }
}

foreach ($toProcess as $idx => $row) {
    $id = (int) ($row['competition_type_id'] ?? 0);
    $name = trim((string) ($row['type_name'] ?? ''));
    $src = trim((string) ($row['description_short'] ?? ''));
    $translated = trim((string) ($cache[$src] ?? ''));

    if ($translated === '') {
        $fail++;
        echo '[ERROR] ' . ($idx + 1) . '/' . $total . ' type_id=' . $id . ' ' . $name . PHP_EOL;
        continue;
    }

    $update->execute([
        ':competition_type_id' => $id,
        ':description_es' => $translated,
    ]);
    $ok++;
    echo '[OK] ' . ($idx + 1) . '/' . $total . ' type_id=' . $id . ' ' . $name . PHP_EOL;
}

if ($sleepMs > 0) {
    usleep($sleepMs * 1000);
}

echo PHP_EOL;
echo 'Traducciones guardadas: ' . $ok . PHP_EOL;
echo 'Errores: ' . $fail . PHP_EOL;

