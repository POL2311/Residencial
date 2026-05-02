<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/service_profile.php';
require_once __DIR__ . '/../../../config/resident_access.php';

require_login();
require_role(['guardia', 'super_admin']);

function out(bool $ok, array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    service_profile_schema_ensure($pdo);
    resident_access_ensure_schema($pdo);

    $uid = (int)(current_user()['id'] ?? 0);
    $residencialId = service_profile_resolve_residencial_id_for_user($pdo, $uid);

    if ($residencialId <= 0) {
        out(true, ['data' => ['notifications' => [
            'total' => 0,
            'latest_id' => 0,
            'latest_updated_at' => null,
            'items' => [],
        ]]]);
    }

    service_profile_api_require_module($pdo, $residencialId, 'guardia', 'contexto', 'El panel de guardia no está habilitado para este cliente.');

    $notifications = resident_access_notifications($pdo, $residencialId, 12);

    out(true, [
        'data' => [
            'notifications' => $notifications,
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar las notificaciones del guardia.');
}
