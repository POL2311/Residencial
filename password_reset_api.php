<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/password_reset.php';

function pr_require_csrf(): void
{
    $sent = trim((string)($_POST['csrf_token'] ?? ''));
    if ($sent === '' || !hash_equals(app_csrf_token(), $sent)) {
        app_json_out(false, ['error' => 'Tu sesión de recuperación expiró. Recarga la página e inténtalo de nuevo.'], 419);
    }
}

try {
    $action = trim((string)($_POST['action'] ?? ''));
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        app_json_out(false, ['error' => 'Método no permitido.'], 405);
    }

    app_require_write_guard();
    pr_require_csrf();

    if ($action === 'request_reset_code') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            app_json_out(false, ['error' => 'Ingresa un correo válido para continuar.'], 422);
        }

        try {
            $result = password_reset_request($pdo, $email);
            app_json_out(true, [
                'message' => $result['message'],
                'next_step' => 'verify',
                'email' => $email,
            ]);
        } catch (Throwable $e) {
            app_log_exception($e, 'password-reset-request');
            $message = 'No pudimos enviar el código en este momento. Revisa la configuración SMTP e inténtalo de nuevo.';
            if ($e instanceof RuntimeException && in_array($e->getMessage(), ['SMTP_MISSING_HOST', 'SMTP_MISSING_FROM_EMAIL'], true)) {
                $message = 'Aún no está configurado el correo del sistema. Entra como superadmin en Configuración > Correo SMTP y completa host, remitente y credenciales.';
            }
            app_json_out(false, ['error' => $message], 500);
        }
    }

    if ($action === 'verify_reset_code') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $code = preg_replace('/\D+/', '', (string)($_POST['code'] ?? '')) ?? '';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            app_json_out(false, ['error' => 'El correo no es válido.'], 422);
        }
        if (strlen($code) !== 6) {
            app_json_out(false, ['error' => 'Ingresa el código de 6 dígitos.'], 422);
        }

        $result = password_reset_verify($pdo, $email, $code);
        if (!$result['ok']) {
            app_json_out(false, ['error' => $result['error']], 422);
        }

        app_json_out(true, [
            'message' => 'Código validado correctamente. Ahora define tu nueva contraseña.',
            'next_step' => 'reset',
        ]);
    }

    if ($action === 'reset_password_with_code') {
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

        if (mb_strlen($password) < 6) {
            app_json_out(false, ['error' => 'La nueva contraseña debe tener al menos 6 caracteres.'], 422);
        }
        if ($password !== $passwordConfirm) {
            app_json_out(false, ['error' => 'Las contraseñas no coinciden.'], 422);
        }

        $result = password_reset_update_password($pdo, $password);
        if (!$result['ok']) {
            app_json_out(false, ['error' => $result['error']], 422);
        }

        app_json_out(true, [
            'message' => 'Tu contraseña se actualizó correctamente. Ya puedes iniciar sesión.',
            'redirect' => app_login_url() . '?reset=1',
        ]);
    }

    app_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos completar la recuperación de contraseña.');
}
