<?php
// auth.php
require_once __DIR__ . '/config.php';

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
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
        header('Location: login.php');
        exit;
    }
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        http_response_code(403);
        echo "No tienes permisos para acceder a esta secci贸n.";
        exit;
    }
}
