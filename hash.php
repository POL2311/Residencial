<?php
declare(strict_types=1);

require_once __DIR__ . '/config/app_security.php';

app_abort(
    404,
    'La página que buscas no existe',
    'Esta utilidad ya no está disponible desde la web pública.',
    [
        ['label' => 'Volver al inicio de sesión', 'href' => app_login_url()],
    ]
);
