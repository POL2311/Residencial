<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/api_helpers.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);
service_profile_api_require_module($pdo, $residencialId, 'admin_residencial', 'autos', 'Los autos no están habilitados para este cliente.');

resident_vehicle_access_schema_ensure($pdo);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($method !== 'GET' || $action !== 'list') {
    json_out(false, ['error' => 'Acción no soportada.']);
}

try {
    $tipo = trim((string)($_GET['tipo_movimiento'] ?? ''));
    $limit = (int)($_GET['limit'] ?? 60);
    if ($limit <= 0) $limit = 60;
    $limit = min($limit, 200);

    $items = resident_vehicle_access_list($pdo, [
        'residencial_id' => $residencialId,
        'tipo_movimiento' => in_array($tipo, ['ingreso', 'egreso'], true) ? $tipo : null,
        'limit' => $limit,
    ]);

    json_out(true, [
        'data' => [
            'items' => $items,
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar los accesos de residentes.');
}
