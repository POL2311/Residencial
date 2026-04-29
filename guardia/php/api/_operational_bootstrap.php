<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/api_helpers.php';
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/image_uploads.php';

require_login();
require_role(['guardia', 'super_admin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_write_guard();
}

operational_schema_ensure($pdo);

$guardUser = current_user();
$guardiaId = (int)($guardUser['id'] ?? 0);

$stmtGuardRid = $pdo->prepare("
    SELECT residencial_id
    FROM usuarios_residenciales
    WHERE user_id = :uid
    ORDER BY es_principal DESC, created_at ASC
    LIMIT 1
");
$stmtGuardRid->execute(['uid' => $guardiaId]);
$residencialId = (int)($stmtGuardRid->fetchColumn() ?: 0);

if ($residencialId <= 0) {
    json_out(false, ['error' => 'Guardia no asociado a un cliente.']);
}

$operationalMode = operational_get_mode($pdo, $residencialId);

function guardia_operational_required(string $message = 'Este módulo solo está disponible en modo operativo.'): void
{
    global $operationalMode;

    if (!operational_is_operational_mode($operationalMode)) {
        json_out(false, ['error' => $message]);
    }
}

function guardia_operational_context(): array
{
    global $pdo, $residencialId, $operationalMode;

    return operational_get_context($pdo, $residencialId, $operationalMode);
}
