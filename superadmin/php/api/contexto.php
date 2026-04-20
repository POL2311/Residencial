<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $user = sa_current_user($pdo);
    $config = sa_fetch_config_general($pdo);

    $quick = [
        'residenciales' => 0,
        'residenciales_activos' => 0,
        'usuarios' => 0,
    ];

    try {
        $quick['residenciales'] = (int)$pdo->query("SELECT COUNT(*) FROM residenciales")->fetchColumn();
        $quick['residenciales_activos'] = (int)$pdo->query("SELECT COUNT(*) FROM residenciales WHERE activo = 1")->fetchColumn();
        $quick['usuarios'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Throwable $e) {
        $quick = ['residenciales' => 0, 'residenciales_activos' => 0, 'usuarios' => 0];
    }

    sa_json_out(true, [
        'data' => [
            'user' => $user,
            'system_name' => $config['nombre_sistema'] ?? 'Sistema Residencial',
            'company' => $config['empresa'] ?? null,
            'logo_url' => $config['logo_url'] ?? null,
            'role_label' => 'Control global del sistema',
            'csrf_token' => sa_csrf_token(),
            'quick_stats' => $quick,
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar el contexto del panel.');
}
