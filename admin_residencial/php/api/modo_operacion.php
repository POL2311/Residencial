<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $profile = service_profile_frontend_payload($pdo, $residencialId, 'admin_residencial');
        json_out(true, [
            'data' => [
                'modo_operacion' => $operationalMode,
                'modos' => operational_allowed_modes(),
                'context' => admin_operational_context(),
                'service_profile' => $profile,
                'locked_by_superadmin' => true,
            ],
        ]);
    }

    json_out(false, ['error' => 'El perfil de servicio se administra desde Superadmin.'], 403);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos actualizar el modo operativo.');
}
