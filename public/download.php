<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';

app_require_panel_auth();

$file = basename($_GET['file'] ?? '');
$path = app_root() . DIRECTORY_SEPARATOR . 'out' . DIRECTORY_SEPARATOR . $file;

if ($file === '' || !is_file($path)) {
    http_response_code(404);
    echo 'Fichero no encontrado';
    exit;
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $file . '"');
readfile($path);
exit;
