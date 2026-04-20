<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/auth.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'] ?: '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
    }
    session_destroy();
}

header('Location: ' . app_login_url());
exit;
