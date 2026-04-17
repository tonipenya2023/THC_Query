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

function google_translate_text(string $text, string $from = 'auto', string $to = 'es'): ?string
{
    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx'
        . '&sl=' . rawurlencode($from)
        . '&tl=' . rawurlencode($to)
        . '&dt=t&q=' . rawurlencode($text);

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'header' => "User-Agent: THC-GPT-Translator/1.0\r\nAccept: application/json\r\n",
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $json = json_decode($raw, true);
    if (!is_array($json) || !isset($json[0]) || !is_array($json[0])) {
        return null;
    }

    $translated = '';
    foreach ($json[0] as $segment) {
        if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
            $translated .= $segment[0];
        }
    }

    $translated = trim($translated);
    return $translated === '' ? null : $translated;
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

foreach ($toProcess as $idx => $row) {
    $id = (int) ($row['competition_type_id'] ?? 0);
    $name = trim((string) ($row['type_name'] ?? ''));
    $src = trim((string) ($row['description_short'] ?? ''));
    if ($src === '') {
        continue;
    }

    $translated = $cache[$src] ?? null;
    if ($translated === null) {
        $translated = google_translate_text($src, $from, $to);
        if ($translated !== null) {
            $cache[$src] = $translated;
        }
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }

    if ($translated === null) {
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

echo PHP_EOL;
echo 'Traducciones guardadas: ' . $ok . PHP_EOL;
echo 'Errores: ' . $fail . PHP_EOL;

