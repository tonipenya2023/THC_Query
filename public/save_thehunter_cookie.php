<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/web_bootstrap.php';

app_require_panel_auth();
app_start_session();

$csrfToken = is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : null;
if (!app_validate_csrf($csrfToken)) {
    header('Location: index.php?flash=' . urlencode('CSRF invalido'));
    exit;
}

$username = app_auth_username() ?? '';
if ($username === '') {
    header('Location: index.php?flash=' . urlencode('No se encontro el usuario autenticado actual'));
    exit;
}

$mode = is_string($_POST['mode'] ?? null) ? trim($_POST['mode']) : 'save';

/**
 * @return array{ok:bool,message:string}
 */
function validate_thehunter_cookie_text(string $cookie): array
{
    $cookie = trim($cookie);
    if ($cookie === '') {
        return ['ok' => false, 'message' => 'La cookie de theHunter es obligatoria'];
    }

    if (!str_contains($cookie, '=')) {
        return ['ok' => false, 'message' => 'La cookie no tiene formato valido. Debe ser nombre=valor'];
    }

    $parts = array_values(array_filter(array_map('trim', explode(';', $cookie)), static fn(string $part): bool => $part !== ''));
    if ($parts === []) {
        return ['ok' => false, 'message' => 'La cookie no tiene partes validas'];
    }

    foreach ($parts as $part) {
        if (!str_contains($part, '=')) {
            return ['ok' => false, 'message' => 'Cada parte debe tener formato nombre=valor separada por ;'];
        }

        [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        if ($name === '' || $value === '') {
            return ['ok' => false, 'message' => 'Hay una cookie vacia o mal copiada'];
        }

        if (str_contains($name, ',') || str_contains($value, ',')) {
            return ['ok' => false, 'message' => 'Formato invalido. Usa nombre=valor y separa varias cookies con ; no con ,'];
        }
    }

    return ['ok' => true, 'message' => 'ok'];
}

try {
    if ($mode === 'clear') {
        app_clear_thehunter_cookie($username);
        header('Location: index.php?flash=' . urlencode('Sesion theHunter eliminada'));
        exit;
    }

    $cookie = is_string($_POST['thehunter_cookie'] ?? null) ? trim((string) $_POST['thehunter_cookie']) : '';
    $validation = validate_thehunter_cookie_text($cookie);
    if (!($validation['ok'] ?? false)) {
        header('Location: index.php?flash=' . urlencode((string) ($validation['message'] ?? 'Cookie invalida')));
        exit;
    }

    app_set_thehunter_cookie($username, $cookie);
    header('Location: index.php?flash=' . urlencode('Sesion theHunter guardada para ' . $username));
    exit;
} catch (Throwable $e) {
    header('Location: index.php?flash=' . urlencode('Error guardando sesion theHunter: ' . $e->getMessage()));
    exit;
}
