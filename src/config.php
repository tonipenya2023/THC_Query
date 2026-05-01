<?php
declare(strict_types=1);

$dbDsn = getenv('THC_DB_DSN') ?: 'pgsql:host=localhost;port=5432;dbname=test';
$dbUser = getenv('THC_DB_USER') ?: 'postgres';
$dbPassword = getenv('THC_DB_PASSWORD') ?: 'system';
$dbSchema = getenv('THC_DB_SCHEMA') ?: 'gpt';

$apiUserAgent = getenv('THC_API_USER_AGENT') ?: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36';
$apiTimeout = getenv('THC_API_TIMEOUT');
$timeoutValue = is_string($apiTimeout) && ctype_digit($apiTimeout) ? (int) $apiTimeout : 30;

$scoreTemplatesRaw = getenv('THC_LB_SCORE_TEMPLATES');
$rangeTemplatesRaw = getenv('THC_LB_RANGE_TEMPLATES');

$defaultScoreTemplates = [
    'https://api.thehunter.com/v1/Leaderboard/score?species_id={species_id}&limit={limit}&offset=0',
    'https://api.thehunter.com/v1/Leaderboard/score?specie={species_id}&limit={limit}&offset=0',
    'https://api.thehunter.com/v1/Leaderboard/list?mode=score&species_id={species_id}&limit={limit}&offset=0',
    'https://api.thehunter.com/v1/Leaderboard/list?type=score&species_id={species_id}&limit={limit}&offset=0',
];

$defaultRangeTemplates = [
    'https://api.thehunter.com/v1/Leaderboard/range?species_id={species_id}&limit={limit}&offset=0',
    'https://api.thehunter.com/v1/Leaderboard/range?specie={species_id}&limit={limit}&offset=0',
    'https://api.thehunter.com/v1/Leaderboard/list?mode=range&species_id={species_id}&limit={limit}&offset=0',
    'https://api.thehunter.com/v1/Leaderboard/list?type=range&species_id={species_id}&limit={limit}&offset=0',
];

$scoreTemplates = is_string($scoreTemplatesRaw) && trim($scoreTemplatesRaw) !== ''
    ? array_values(array_filter(array_map('trim', explode(';', $scoreTemplatesRaw))))
    : $defaultScoreTemplates;

$rangeTemplates = is_string($rangeTemplatesRaw) && trim($rangeTemplatesRaw) !== ''
    ? array_values(array_filter(array_map('trim', explode(';', $rangeTemplatesRaw))))
    : $defaultRangeTemplates;

return [
    'db' => [
        'dsn' => $dbDsn,
        'user' => $dbUser,
        'password' => $dbPassword,
        'schema' => $dbSchema,
    ],
    'api' => [
        'base_url' => 'https://api.thehunter.com/v1/Public_user/expedition',
        'list_url' => 'https://api.thehunter.com/v1/Expedition/list',
        'user_agent' => $apiUserAgent,
        'timeout' => $timeoutValue,
    ],
    'leaderboards' => [
        'summary_url' => 'https://api.thehunter.com/v1/Page_content/leaderboards_all',
        'details_url' => 'https://api.thehunter.com/v1/Page_content/leaderboard_details',
        'score_templates' => $scoreTemplates,
        'range_templates' => $rangeTemplates,
    ],
];