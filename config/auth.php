<?php
// auth.php
require_once __DIR__ . '/config.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        app_abort(
            401,
            'Necesitas iniciar sesión',
            'Tu sesión no está activa o ya expiró. Inicia sesión para continuar.',
            [
                ['label' => 'Ir al inicio de sesión', 'href' => app_login_url()],
            ]
        );
    }
}

function current_user() {
    if (!is_logged_in()) return null;
    return [
        'id'   => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'role' => $_SESSION['user_role'], // super_admin, guardia, etc.
    ];
}

/**
 */
function require_role(array $roles): void {
    if (!is_logged_in()) {
        require_login();
    }
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        app_abort(
            403,
            'No tienes permiso para entrar aquí',
            'Tu cuenta no tiene acceso a esta sección del sistema.',
            [
                ['label' => 'Volver a mi panel', 'href' => app_role_home_url((string)($_SESSION['user_role'] ?? ''))],
                ['label' => 'Cerrar sesión', 'href' => app_logout_url(), 'kind' => 'secondary'],
            ]
        );
    }
}
