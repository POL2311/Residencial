<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app_security.php';

$code = (int)($_GET['code'] ?? 404);

$meta = match ($code) {
    401 => [
        'title' => 'Necesitas iniciar sesión',
        'message' => 'Tu sesión no está activa. Vuelve a iniciar sesión para continuar.',
        'actions' => [
            ['label' => 'Ir al inicio de sesión', 'href' => app_login_url()],
        ],
    ],
    403 => [
        'title' => 'No tienes permiso para entrar aquí',
        'message' => 'Tu cuenta no tiene acceso a esta sección. Si crees que esto es un error, contacta al administrador.',
        'actions' => [
            ['label' => 'Volver al login', 'href' => app_login_url()],
        ],
    ],
    500 => [
        'title' => 'Ocurrió un problema interno',
        'message' => 'No pudimos completar la solicitud en este momento. Inténtalo de nuevo en unos minutos.',
        'actions' => [
            ['label' => 'Volver al login', 'href' => app_login_url()],
        ],
    ],
    default => [
        'title' => 'La página que buscas no existe',
        'message' => 'La URL que intentaste abrir no está disponible o ya no forma parte del sistema.',
        'actions' => [
            ['label' => 'Volver al login', 'href' => app_login_url()],
        ],
    ],
};

app_render_error_page($code, $meta['title'], $meta['message'], $meta['actions']);
