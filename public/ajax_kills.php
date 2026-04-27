<?php
// public/ajax_kills.php - Endpoint alternativo para AJAX
// Este archivo puede usarse si prefieres no pasar por index.php

require_once __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json');

$controller = $container->get(\THC\Web\Controllers\ExpeditionsController::class);
echo $controller->getKills($_REQUEST);