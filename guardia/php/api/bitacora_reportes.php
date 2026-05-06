<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/service_profile.php';
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/image_uploads.php';

require_login();
require_role(['guardia', 'super_admin']);

function out(bool $ok, array $data = [], int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

function strv(string $value, int $maxLen): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (mb_strlen($value) > $maxLen) {
        $value = mb_substr($value, 0, $maxLen);
    }
    return $value;
}

function intv(mixed $value, int $default = 0): int
{
    if ($value === null) {
        return $default;
    }
    if (is_int($value)) {
        return $value;
    }
    $raw = preg_replace('/[^\d-]/', '', (string)$value);
    if ($raw === '' || $raw === '-' || $raw === '+') {
        return $default;
    }
    return (int)$raw;
}

function guardia_require_module_access(array $profile, bool $isSuperAdmin, string $module, string $message): void
{
    if ($isSuperAdmin) {
        return;
    }
    if (!service_profile_module_allowed_for_role_and_service($profile, 'guardia', $module)) {
        out(false, ['error' => $message], 403);
    }
}

function save_operational_file_record(PDO $pdo, int $rid, string $entityType, int $entityId, string $subtype, array $file): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $processed = operational_process_image_upload($file, [
        'folder_prefix' => 'bitacora',
        'quality' => 82,
        'preserve_text' => true,
        'max_long_side' => 1400,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO archivos_operativos (
            residencial_id, entidad_tipo, entidad_id, subtipo, storage_disk, storage_path, public_url,
            mime_type, size_bytes, width, height, created_at, updated_at
        ) VALUES (
            :rid, :entidad_tipo, :entidad_id, :subtipo, :storage_disk, :storage_path, :public_url,
            :mime_type, :size_bytes, :width, :height, NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $rid,
        'entidad_tipo' => $entityType,
        'entidad_id' => $entityId,
        'subtipo' => $subtype,
        'storage_disk' => $processed['disk'],
        'storage_path' => $processed['path'],
        'public_url' => $processed['public_url'],
        'mime_type' => $processed['mime_type'],
        'size_bytes' => (int)$processed['size_bytes'],
        'width' => (int)$processed['width'],
        'height' => (int)$processed['height'],
    ]);

    return $processed;
}

try {
    operational_schema_ensure($pdo);
    service_profile_schema_ensure($pdo);

    $user = current_user();
    $uid = (int)($user['id'] ?? 0);
    $isSuperAdmin = (($user['role'] ?? '') === 'super_admin');
    $rid = service_profile_resolve_residencial_id_for_user($pdo, $uid);
    if ($rid <= 0) {
        out(false, ['error' => 'No se pudo resolver el servicio asignado para este guardia.'], 422);
    }

    $profile = $isSuperAdmin
        ? service_profile_get($pdo, $rid)
        : service_profile_require_role_enabled($pdo, $rid, 'guardia', 'El panel de guardia no está habilitado para este cliente.');

    guardia_require_module_access($profile, $isSuperAdmin, 'bitacora_operativa', 'La bitácora operativa no está habilitada para este cliente.');

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        out(false, ['error' => 'Método no permitido.'], 405);
    }

    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $tipoEvento = strv((string)($_POST['tipo_evento'] ?? ''), 40);
        $allowedTipos = ['basura_ingreso', 'luces', 'rondin', 'nota'];
        if (!in_array($tipoEvento, $allowedTipos, true)) {
            out(false, ['error' => 'Tipo de reporte inválido.'], 422);
        }

        $observaciones = strv((string)($_POST['observaciones'] ?? ''), 2000);
        if ($observaciones === '') {
            out(false, ['error' => 'Debes capturar observaciones del reporte.'], 422);
        }

        $areaId = intv($_POST['area_id'] ?? null, 0);
        $areaId = $areaId > 0 ? $areaId : null;
        if ($areaId !== null && !operational_validate_area($pdo, $rid, $areaId)) {
            out(false, ['error' => 'Área inválida para este servicio.'], 422);
        }

        $meta = [
            'proveedor' => strv((string)($_POST['proveedor'] ?? ''), 140),
            'placas' => strv((string)($_POST['placas'] ?? ''), 40),
            'accion' => strv((string)($_POST['accion'] ?? ''), 40),
            'ubicacion' => strv((string)($_POST['ubicacion'] ?? ''), 140),
            'zona' => strv((string)($_POST['zona'] ?? ''), 140),
            'incidencias_detectadas' => (int)($_POST['incidencias_detectadas'] ?? 0) === 1,
        ];
        $meta = array_filter($meta, static fn($v) => $v !== '' && $v !== null && $v !== false);

        $pdo->beginTransaction();
        $bitacoraId = operational_write_bitacora($pdo, [
            'residencial_id' => $rid,
            'guardia_id' => $isSuperAdmin ? null : $uid,
            'tipo_origen' => 'reporte_operativo',
            'origen_id' => null,
            'tipo_evento' => $tipoEvento,
            'resultado' => 'informativo',
            'area_id' => $areaId,
            'observaciones' => $observaciones,
            'metadata_json' => $meta ?: null,
        ]);

        $urls = [];
        for ($i = 1; $i <= 3; $i++) {
            $key = 'evidencia_' . $i;
            if (!isset($_FILES[$key])) {
                continue;
            }
            $processed = save_operational_file_record($pdo, $rid, 'bitacora_operativa', $bitacoraId, 'evidencia', $_FILES[$key]);
            if ($processed && !empty($processed['public_url'])) {
                $urls[] = (string)$processed['public_url'];
            }
        }

        $pdo->commit();
        out(true, [
            'message' => 'Reporte registrado en bitácora.',
            'data' => [
                'id' => $bitacoraId,
                'evidencias' => $urls,
            ],
        ]);
    }

    if ($action === 'add_note') {
        $parentId = intv($_POST['parent_bitacora_id'] ?? 0, 0);
        if ($parentId <= 0) {
            out(false, ['error' => 'Falta parent_bitacora_id.'], 422);
        }
        $nota = strv((string)($_POST['observaciones'] ?? ''), 2000);
        if ($nota === '') {
            out(false, ['error' => 'Debes capturar la nota adicional.'], 422);
        }

        $pdo->beginTransaction();
        $noteId = operational_write_bitacora($pdo, [
            'residencial_id' => $rid,
            'guardia_id' => $isSuperAdmin ? null : $uid,
            'tipo_origen' => 'reporte_operativo',
            'origen_id' => $parentId,
            'tipo_evento' => 'nota',
            'resultado' => 'informativo',
            'observaciones' => $nota,
            'metadata_json' => ['parent_bitacora_id' => $parentId],
        ]);
        $pdo->commit();

        out(true, [
            'message' => 'Nota adicional registrada.',
            'data' => [
                'id' => $noteId,
                'parent_bitacora_id' => $parentId,
            ],
        ]);
    }

    out(false, ['error' => 'Acción inválida.'], 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // app_json_exception() is available via app_security.php (loaded by service_profile.php).
    if (function_exists('app_json_exception')) {
        app_json_exception($e, 'No pudimos registrar el reporte de bitácora.');
    }
    out(false, ['error' => 'No pudimos registrar el reporte de bitácora.'], 500);
}

