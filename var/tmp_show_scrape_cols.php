<?php
require 'src/web_bootstrap.php';
$cols = app_query_all("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = 'gpt' AND table_name = 'scrape_kill_urls' ORDER BY ordinal_position");
foreach ($cols as $c) {
    echo $c['column_name'] . ' | ' . $c['data_type'] . PHP_EOL;
}
