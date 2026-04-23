<?php
require 'src/web_bootstrap.php';
$row = app_query_one("SELECT COUNT(*) AS total, COUNT(*) FILTER (WHERE url_type IS NOT NULL) AS with_url_type, COUNT(*) FILTER (WHERE animal_id IS NOT NULL) AS with_animal_id, COUNT(*) FILTER (WHERE kill_id IS NOT NULL) AS with_kill_id, COUNT(*) FILTER (WHERE player_slug IS NOT NULL) AS with_player_slug, COUNT(*) FILTER (WHERE page_title IS NOT NULL) AS with_page_title, COUNT(*) FILTER (WHERE page_kind IS NOT NULL) AS with_page_kind, COUNT(*) FILTER (WHERE requires_login IS TRUE) AS with_requires_login, COUNT(*) FILTER (WHERE parsed_summary IS NOT NULL) AS with_parsed_summary FROM gpt.scrape_kill_urls");
echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
