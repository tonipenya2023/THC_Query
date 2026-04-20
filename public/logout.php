<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';

app_start_session();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool) ($params['secure'] ?? false), (bool) ($params['httponly'] ?? true));
}
session_destroy();

header('WWW-Authenticate: Basic realm="THC Query Panel"');
http_response_code(401);
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sesion cerrada</title>
    <style>
        body{font-family:Segoe UI,Arial,sans-serif;background:#f3f4f6;color:#111827;margin:0;padding:24px}
        .card{max-width:680px;margin:40px auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:20px}
        a{color:#0f766e;text-decoration:none}
    </style>
</head>
<body>
<section class="card">
    <h1>Sesion cerrada</h1>
    <p>Se ha forzado el cierre de acceso del panel.</p>
    <p><a href="index.php">Volver al panel</a></p>
</section>
</body>
</html>
