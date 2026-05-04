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
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/image_uploads.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['admin_residencial']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_write_guard();
}

operational_schema_ensure($pdo);
service_profile_schema_ensure($pdo);

$adminUser = current_user();
$adminId = (int)($adminUser['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);
$operationalMode = operational_get_mode($pdo, $residencialId);
$serviceProfile = service_profile_require_role_enabled($pdo, $residencialId, 'admin_residencial', 'El panel administrativo no está habilitado para este cliente.');

function admin_operational_required(string $message = 'Este módulo solo está disponible en modo operativo.'): void
{
    global $operationalMode;

    if (!operational_is_operational_mode($operationalMode)) {
        json_out(false, ['error' => $message]);
    }
}

function admin_operational_context(): array
{
    global $pdo, $residencialId;

    return operational_get_context($pdo, $residencialId);
}

function admin_module_required(string $module, string $message = 'Este módulo no está habilitado para este cliente.'): void
{
    global $serviceProfile;

    if (!service_profile_module_allowed_for_role_and_service($serviceProfile, 'admin_residencial', $module)) {
        json_out(false, ['error' => $message], 403);
    }
}
