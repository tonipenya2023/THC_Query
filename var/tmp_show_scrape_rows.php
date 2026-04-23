<?php
require 'src/web_bootstrap.php';
$rows = app_query_all('SELECT id, run_at, source, ref, url, http_code, ok, file_name, error, url_type, player_slug, player_name, animal_id, kill_id, page_title, page_kind, requires_login, parsed_ok, parsed_summary FROM gpt.scrape_kill_urls ORDER BY id DESC LIMIT 5');
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
