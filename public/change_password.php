<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';

app_require_panel_auth();
app_start_session();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    header('Location: index.php?flash=' . urlencode('Metodo no permitido'));
    exit;
}

$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
if (!app_validate_csrf($csrfToken)) {
    header('Location: index.php?flash=' . urlencode('CSRF invalido'));
    exit;
}

$username = app_auth_username() ?? '';
$currentPassword = is_string($_POST['current_password'] ?? null) ? (string) $_POST['current_password'] : '';
$newPassword = is_string($_POST['new_password'] ?? null) ? (string) $_POST['new_password'] : '';
$confirmPassword = is_string($_POST['confirm_password'] ?? null) ? (string) $_POST['confirm_password'] : '';

if ($username === '') {
    header('Location: index.php?flash=' . urlencode('No se detecto usuario autenticado'));
    exit;
}

if (!app_verify_login_password($username, $currentPassword)) {
    header('Location: index.php?flash=' . urlencode('Contrasena actual incorrecta'));
    exit;
}

if ($newPassword === '' || strlen($newPassword) < 6) {
    header('Location: index.php?flash=' . urlencode('La nueva contrasena debe tener al menos 6 caracteres'));
    exit;
}

if ($newPassword !== $confirmPassword) {
    header('Location: index.php?flash=' . urlencode('La confirmacion no coincide'));
    exit;
}

app_set_user_password($username, $newPassword);
header('Location: index.php?flash=' . urlencode('Contrasena actualizada correctamente'));
exit;

