<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/resident_access.php';
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/image_uploads.php';
require_once __DIR__ . '/../../../config/service_profile.php';
require_once __DIR__ . '/../../../config/compliance_helpers.php';

require_login();
require_role(['guardia', 'super_admin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_write_guard();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function out(bool $ok, array $payload = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function strv($value, int $max = 255): string
{
    $str = trim((string)($value ?? ''));
    if (mb_strlen($str) > $max) {
        $str = mb_substr($str, 0, $max);
    }
    return $str;
}

function intv_safe($value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    return (int)$value;
}

function get_residencial_id(PDO $pdo, int $uid): int
{
    $stmt = $pdo->prepare("
        SELECT ur.residencial_id
        FROM usuarios_residenciales ur
        WHERE ur.user_id = :uid
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmt->execute(['uid' => $uid]);
    $rid = (int)($stmt->fetchColumn() ?: 0);

    if ($rid <= 0) {
        out(false, ['error' => 'Tu usuario guardia no está asignado a un cliente.'], 403);
    }

    return $rid;
}

function now_mx(): DateTime
{
    return new DateTime('now');
}

function time_in_range(?string $from, ?string $to, DateTime $now): bool
{
    if (!$from && !$to) return true;
    $current = $now->format('H:i:s');
    if ($from && !$to) return $current >= $from;
    if (!$from && $to) return $current <= $to;
    return $current >= $from && $current <= $to;
}

function evaluar_visita(array $visita): array
{
    $now = now_mx();
    $today = $now->format('Y-m-d');

    $permitido = true;
    $motivo = '';

    if (($visita['estado'] ?? '') === 'cancelado') {
        $permitido = false;
        $motivo = 'Acceso cancelado.';
    }

    if (($visita['estado'] ?? '') === 'vencido') {
        $permitido = false;
        $motivo = 'Acceso vencido.';
    }

    if (($visita['estado'] ?? '') === 'usado' && (int)($visita['uso_unico'] ?? 0) === 1) {
        $permitido = false;
        $motivo = 'Acceso de uso único ya utilizado.';
    }

    if ($permitido) {
        if ($today < ($visita['fecha_desde'] ?? '0000-00-00') || $today > ($visita['fecha_hasta'] ?? '9999-12-31')) {
            $permitido = false;
            $motivo = 'Fuera del rango de fechas permitido.';
        }
    }

    if ($permitido) {
        $from = $visita['hora_desde'] ?: null;
        $to = $visita['hora_hasta'] ?: null;
        if (!time_in_range($from, $to, $now)) {
            $permitido = false;
            $motivo = 'Fuera del horario permitido.';
        }
    }

    return [
        'permitido' => $permitido,
        'motivo' => $motivo,
        'fecha_actual' => $today,
        'hora_actual' => $now->format('H:i:s'),
    ];
}

function operational_mode_for_rid(PDO $pdo, int $rid): string
{
    return operational_get_mode($pdo, $rid);
}

function qr_payload_prefix_map(): array
{
    return [
        'op:pr:' => 'persona_recurrente',
        'op:vr:' => 'visitante_rapido',
        'op:pm:' => 'permiso_material',
        'op:so:' => 'orden_servicio',
    ];
}

function decode_operational_code(string $code): array
{
    $code = trim($code);
    foreach (qr_payload_prefix_map() as $prefix => $type) {
        if (str_starts_with($code, $prefix)) {
            return ['type' => $type, 'token' => substr($code, strlen($prefix))];
        }
    }
    return ['type' => null, 'token' => $code];
}

function fetch_persona_by_token(PDO $pdo, int $rid, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT p.*, a.nombre AS area_nombre
        FROM personas_recurrentes p
        LEFT JOIN areas_operativas a ON a.id = p.area_id
        WHERE p.residencial_id = :rid
          AND p.qr_token = :token
        LIMIT 1
    ");
    $stmt->execute(['rid' => $rid, 'token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetch_visitante_rapido_by_token(PDO $pdo, int $rid, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            v.*,
            a.nombre AS area_nombre,
            u.name AS responsable_nombre
        FROM visitantes_rapidos v
        LEFT JOIN areas_operativas a ON a.id = v.area_id
        LEFT JOIN users u ON u.id = v.responsable_user_id
        WHERE v.residencial_id = :rid
          AND v.qr_token = :token
        LIMIT 1
    ");
    $stmt->execute(['rid' => $rid, 'token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetch_permiso_material_by_token(PDO $pdo, int $rid, string $token): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            a.nombre AS area_nombre,
            ru.name AS responsable_nombre,
            au.name AS aprobado_por_nombre
        FROM permisos_materiales p
        LEFT JOIN areas_operativas a ON a.id = p.area_id
        LEFT JOIN users ru ON ru.id = p.responsable_user_id
        LEFT JOIN users au ON au.id = p.aprobado_por_user_id
        WHERE p.residencial_id = :rid
          AND p.qr_token = :token
        LIMIT 1
    ");
    $stmt->execute(['rid' => $rid, 'token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $itemsStmt = $pdo->prepare("
        SELECT i.*, c.nombre AS catalogo_nombre
        FROM permisos_materiales_items i
        LEFT JOIN catalogo_materiales c ON c.id = i.material_id
        WHERE i.permiso_id = :permiso_id
        ORDER BY i.id ASC
    ");
    $itemsStmt->execute(['permiso_id' => (int)$row['id']]);
    $row['items'] = array_map(static function (array $item): array {
        return [
            'id' => (int)$item['id'],
            'material_id' => $item['material_id'] !== null ? (int)$item['material_id'] : null,
            'material_nombre' => (string)($item['material_nombre'] ?: $item['catalogo_nombre'] ?: ''),
            'cantidad_texto' => (string)$item['cantidad_texto'],
            'agregar_a_catalogo' => (int)$item['agregar_a_catalogo'],
        ];
    }, $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    return $row;
}

function fetch_orden_servicio_by_token(PDO $pdo, int $rid, string $token, bool $forUpdate = false): ?array
{
    if (!operational_table_exists($pdo, 'ordenes_servicio')) {
        return null;
    }
    $sql = "
        SELECT
            os.*,
            p.nombre_comercial AS proveedor_nombre,
            p.estatus_cumplimiento AS proveedor_cumplimiento,
            p.motivo_bloqueo AS proveedor_motivo,
            pr.nombre AS persona_nombre,
            pr.empresa AS persona_empresa,
            pr.puesto AS persona_puesto,
            pr.estatus_cumplimiento AS persona_cumplimiento,
            pr.motivo_bloqueo AS persona_motivo,
            a.nombre AS area_nombre
        FROM ordenes_servicio os
        LEFT JOIN proveedores p ON p.id = os.proveedor_id AND p.residencial_id = os.residencial_id
        LEFT JOIN personas_recurrentes pr ON pr.id = os.persona_recurrente_id AND pr.residencial_id = os.residencial_id
        LEFT JOIN areas_operativas a ON a.id = os.area_id AND a.residencial_id = os.residencial_id
        WHERE os.residencial_id = :rid
          AND os.qr_token = :token
        LIMIT 1
    ";
    if ($forUpdate) {
        $sql .= " FOR UPDATE";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['rid' => $rid, 'token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function orden_servicio_compliance(PDO $pdo, int $rid, array $row): array
{
    $providerStatus = compliance_normalize_status((string)($row['proveedor_cumplimiento'] ?? 'autorizado'));
    $providerPayload = !empty($row['proveedor_id']) ? [
        'id' => (int)$row['proveedor_id'],
        'nombre' => (string)($row['proveedor_nombre'] ?? ''),
        'estatus_cumplimiento' => $providerStatus,
        'motivo_bloqueo' => (string)($row['proveedor_motivo'] ?? ''),
    ] : null;

    $candidates = [[
        'status' => $providerStatus,
        'motivo' => (string)($row['proveedor_motivo'] ?? ''),
        'source' => 'proveedor',
        'provider' => $providerPayload,
    ]];

    if (!empty($row['persona_recurrente_id'])) {
        $personCompliance = compliance_effective_for_person($pdo, $rid, (int)$row['persona_recurrente_id'], [
            'id' => (int)$row['persona_recurrente_id'],
            'estatus_cumplimiento' => (string)($row['persona_cumplimiento'] ?? 'autorizado'),
            'motivo_bloqueo' => (string)($row['persona_motivo'] ?? ''),
        ]);
        $candidates[] = [
            'status' => $personCompliance['cumplimiento_estado'],
            'motivo' => $personCompliance['cumplimiento_motivo'],
            'source' => $personCompliance['cumplimiento_origen'] ?? 'persona',
            'provider' => $personCompliance['proveedor_id'] ? [
                'id' => (int)$personCompliance['proveedor_id'],
                'nombre' => (string)$personCompliance['proveedor_nombre'],
            ] : $providerPayload,
        ];
    }

    $winner = compliance_pick_worst($candidates);
    $status = compliance_normalize_status((string)$winner['status']);
    $provider = is_array($winner['provider']) ? $winner['provider'] : $providerPayload;
    return [
        'cumplimiento_estado' => $status,
        'cumplimiento_label' => compliance_label($status),
        'cumplimiento_motivo' => trim((string)$winner['motivo']),
        'cumplimiento_origen' => (string)$winner['source'],
        'proveedor_id' => $provider ? (int)($provider['id'] ?? 0) : null,
        'proveedor_nombre' => $provider ? (string)($provider['nombre'] ?? '') : (string)($row['proveedor_nombre'] ?? ''),
        'puede_ingresar' => !compliance_is_blocking($status),
        'requiere_confirmacion' => compliance_requires_confirmation($status),
    ];
}

function orden_servicio_schedule_eval(array $row): array
{
    $today = date('Y-m-d');
    $now = date('H:i:s');
    $warnings = [];
    $permitido = true;
    $estatus = (string)($row['estatus'] ?? 'programada');

    if (in_array($estatus, ['cerrada', 'cancelada'], true)) {
        $permitido = false;
        $warnings[] = $estatus === 'cerrada' ? 'La orden ya está cerrada.' : 'La orden fue cancelada.';
    }
    if ($permitido && (string)($row['fecha_programada'] ?? '') !== $today) {
        $warnings[] = 'La orden no está programada para hoy.';
    }
    if ($permitido && !empty($row['hora_inicio']) && $now < (string)$row['hora_inicio']) {
        $warnings[] = 'La orden está fuera de horario: aún no inicia.';
    }
    if ($permitido && !empty($row['hora_fin']) && $now > (string)$row['hora_fin']) {
        $warnings[] = 'La orden está fuera de horario: ya terminó.';
    }

    return [
        'permitido' => $permitido,
        'horario_validado' => empty($warnings),
        'requiere_confirmacion' => $permitido && !empty($warnings),
        'motivo' => implode(' ', $warnings),
        'fecha_actual' => $today,
        'hora_actual' => $now,
    ];
}

function normalize_orden_servicio_scan(PDO $pdo, int $rid, array $row): array
{
    $schedule = orden_servicio_schedule_eval($row);
    $compliance = orden_servicio_compliance($pdo, $rid, $row);
    $canEnter = (bool)$schedule['permitido'] && (bool)$compliance['puede_ingresar'];
    return [
        'id' => (int)$row['id'],
        'folio' => (string)$row['folio'],
        'qr_token' => (string)$row['qr_token'],
        'tipo_servicio' => (string)($row['tipo_servicio'] ?? ''),
        'descripcion' => (string)($row['descripcion'] ?? ''),
        'fecha_programada' => (string)$row['fecha_programada'],
        'hora_inicio' => (string)($row['hora_inicio'] ?? ''),
        'hora_fin' => (string)($row['hora_fin'] ?? ''),
        'estatus' => (string)($row['estatus'] ?? 'programada'),
        'prioridad' => (string)($row['prioridad'] ?? 'media'),
        'proveedor_id' => $row['proveedor_id'] !== null ? (int)$row['proveedor_id'] : null,
        'proveedor_nombre' => (string)($row['proveedor_nombre'] ?? ''),
        'persona_recurrente_id' => $row['persona_recurrente_id'] !== null ? (int)$row['persona_recurrente_id'] : null,
        'persona_nombre' => (string)($row['persona_nombre'] ?? ''),
        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area_nombre' => (string)($row['area_nombre'] ?? ''),
        'schedule' => $schedule,
        'cumplimiento' => $compliance,
        'puede_ingresar' => $canEnter,
        'requiere_confirmacion' => $canEnter && ((bool)$schedule['requiere_confirmacion'] || (bool)$compliance['requiere_confirmacion']),
    ];
}

function find_operational_scan(PDO $pdo, int $rid, string $code): ?array
{
    $decoded = decode_operational_code($code);
    $preferredType = $decoded['type'];
    $token = $decoded['token'];

    if ($token === '') {
        return null;
    }

    $try = [];
    if ($preferredType) {
        $try[] = $preferredType;
    }
    foreach (['persona_recurrente', 'visitante_rapido', 'permiso_material', 'orden_servicio'] as $type) {
        if (!in_array($type, $try, true)) {
            $try[] = $type;
        }
    }

    foreach ($try as $type) {
        $row = null;
        if ($type === 'persona_recurrente') {
            $row = fetch_persona_by_token($pdo, $rid, $token);
        } elseif ($type === 'visitante_rapido') {
            $row = fetch_visitante_rapido_by_token($pdo, $rid, $token);
        } elseif ($type === 'permiso_material') {
            $row = fetch_permiso_material_by_token($pdo, $rid, $token);
        } elseif ($type === 'orden_servicio') {
            $row = fetch_orden_servicio_by_token($pdo, $rid, $token);
        }

        if ($row) {
            return ['type' => $type, 'item' => $row, 'token' => $token];
        }
    }

    return null;
}

function normalize_persona_scan(array $row, ?PDO $pdo = null, ?int $rid = null): array
{
    $compliance = ($pdo && $rid)
        ? compliance_effective_for_person($pdo, $rid, (int)$row['id'], $row)
        : [
            'cumplimiento_estado' => compliance_normalize_status((string)($row['estatus_cumplimiento'] ?? 'autorizado')),
            'cumplimiento_label' => compliance_label((string)($row['estatus_cumplimiento'] ?? 'autorizado')),
            'cumplimiento_motivo' => (string)($row['motivo_bloqueo'] ?? ''),
            'proveedor_id' => null,
            'proveedor_nombre' => '',
            'puede_ingresar' => !compliance_is_blocking((string)($row['estatus_cumplimiento'] ?? 'autorizado')),
            'requiere_confirmacion' => compliance_requires_confirmation((string)($row['estatus_cumplimiento'] ?? 'autorizado')),
        ];

    return [
        'id' => (int)$row['id'],
        'nombre' => (string)$row['nombre'],
        'telefono' => (string)($row['telefono'] ?? ''),
        'empresa' => (string)($row['empresa'] ?? ''),
        'puesto' => (string)($row['puesto'] ?? ''),
        'notas' => (string)($row['notas'] ?? ''),
        'foto_url' => (string)($row['foto_url'] ?? ''),
        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area_nombre' => (string)($row['area_nombre'] ?? ''),
        'activo' => (int)$row['activo'],
        'esta_dentro' => (int)$row['esta_dentro'],
        'cumplimiento' => $compliance,
        'cumplimiento_estado' => $compliance['cumplimiento_estado'],
        'cumplimiento_label' => $compliance['cumplimiento_label'],
        'cumplimiento_motivo' => $compliance['cumplimiento_motivo'],
        'proveedor_id' => $compliance['proveedor_id'],
        'proveedor_nombre' => $compliance['proveedor_nombre'],
        'puede_ingresar' => $compliance['puede_ingresar'],
        'requiere_confirmacion' => $compliance['requiere_confirmacion'],
        'ultima_entrada_at' => (string)($row['ultima_entrada_at'] ?? ''),
        'ultima_salida_at' => (string)($row['ultima_salida_at'] ?? ''),
    ];
}

function visitante_vigente(array $row): array
{
    $today = date('Y-m-d');
    $permitido = true;
    $motivo = '';

    if (($row['estado'] ?? '') === 'cancelado') {
        $permitido = false;
        $motivo = 'El acceso rápido fue cancelado.';
    }

    if ($permitido && $today < ($row['fecha_desde'] ?? '0000-00-00')) {
        $permitido = false;
        $motivo = 'El acceso todavía no entra en vigencia.';
    }

    if ($permitido && $today > ($row['fecha_hasta'] ?? '9999-12-31')) {
        $permitido = false;
        $motivo = 'El acceso rápido ya venció.';
    }

    return ['permitido' => $permitido, 'motivo' => $motivo];
}

function normalize_visitante_scan(array $row): array
{
    $eval = visitante_vigente($row);
    return [
        'id' => (int)$row['id'],
        'nombre_visitante' => (string)$row['nombre_visitante'],
        'empresa' => (string)($row['empresa'] ?? ''),
        'placa_vehiculo' => (string)($row['placa_vehiculo'] ?? ''),
        'motivo' => (string)($row['motivo'] ?? ''),
        'estado' => (string)$row['estado'],
        'notas_admin' => (string)($row['notas_admin'] ?? ''),
        'fecha_desde' => (string)($row['fecha_desde'] ?? ''),
        'fecha_hasta' => (string)($row['fecha_hasta'] ?? ''),
        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area_nombre' => (string)($row['area_nombre'] ?? ''),
        'responsable_user_id' => $row['responsable_user_id'] !== null ? (int)$row['responsable_user_id'] : null,
        'responsable_nombre' => (string)($row['responsable_nombre'] ?? ''),
        'eval' => $eval,
    ];
}

function normalize_permiso_scan(array $row): array
{
    $permitido = in_array((string)$row['estado'], ['aprobado', 'en_proceso'], true);
    return [
        'id' => (int)$row['id'],
        'tipo_movimiento' => (string)$row['tipo_movimiento'],
        'estado' => (string)$row['estado'],
        'notas' => (string)($row['notas'] ?? ''),
        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area_nombre' => (string)($row['area_nombre'] ?? ''),
        'responsable_user_id' => $row['responsable_user_id'] !== null ? (int)$row['responsable_user_id'] : null,
        'responsable_nombre' => (string)($row['responsable_nombre'] ?? ''),
        'aprobado_por_nombre' => (string)($row['aprobado_por_nombre'] ?? ''),
        'aprobado_at' => (string)($row['aprobado_at'] ?? ''),
        'items' => $row['items'] ?? [],
        'eval' => [
            'permitido' => $permitido,
            'motivo' => $permitido ? '' : 'El permiso todavía no está aprobado o ya no es válido.',
        ],
    ];
}

function save_operational_file_record(PDO $pdo, int $rid, string $entityType, int $entityId, array $file, string $subtype, bool $preserveText = false): ?array
{
    if (empty($file['name'] ?? '')) {
        return null;
    }

    $processed = operational_process_image_upload($file, [
        'folder' => $entityType,
        'preserve_text' => $preserveText,
        'max_long_side' => 1200,
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

$user = current_user();
$guardiaId = (int)($user['id'] ?? 0);
$residencialId = get_residencial_id($pdo, $guardiaId);
$operationalMode = operational_mode_for_rid($pdo, $residencialId);
resident_access_ensure_schema($pdo);
operational_schema_ensure($pdo);
service_profile_schema_ensure($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'buscar';
$isSuperAdmin = (($user['role'] ?? '') === 'super_admin');
$serviceProfile = $isSuperAdmin
    ? service_profile_get($pdo, $residencialId)
    : service_profile_require_role_enabled($pdo, $residencialId, 'guardia', 'El panel de guardia no está habilitado para este cliente.');

function guardia_require_module_access(array $profile, bool $isSuperAdmin, string $module, string $message): void
{
    if ($isSuperAdmin) {
        return;
    }
    if (!service_profile_module_allowed_for_role_and_service($profile, 'guardia', $module)) {
        out(false, ['error' => $message], 403);
    }
}

if ($action === 'personas_dentro') {
    guardia_require_module_access($serviceProfile, $isSuperAdmin, 'personal_recurrente', 'El listado de personal no está habilitado para este cliente.');
} elseif ($action === 'materiales_autorizados') {
    guardia_require_module_access($serviceProfile, $isSuperAdmin, 'materiales', 'El módulo de materiales no está habilitado para este cliente.');
} elseif ($action === 'bitacora_hoy') {
    guardia_require_module_access($serviceProfile, $isSuperAdmin, 'bitacora_operativa', 'La bitácora operativa no está habilitada para este cliente.');
} else {
    guardia_require_module_access($serviceProfile, $isSuperAdmin, 'accesos', 'El control de accesos no está habilitado para este cliente.');
}

try {
    if ($method === 'GET' && $action === 'buscar') {
        $code = strv($_GET['code'] ?? '', 128);
        if ($code === '') {
            out(false, ['error' => 'Falta code'], 422);
        }

        $scan = find_operational_scan($pdo, $residencialId, $code);
        if ($scan) {
            $moduleByType = [
                'persona_recurrente' => 'personal_recurrente',
                'visitante_rapido' => 'visitantes_rapidos',
                'permiso_material' => 'materiales',
                'orden_servicio' => 'ordenes_servicio',
            ];
            $requiredModule = $moduleByType[$scan['type']] ?? '';
            if ($requiredModule === '' || $isSuperAdmin || service_profile_module_allowed_for_role_and_service($serviceProfile, 'guardia', $requiredModule)) {
                $payload = ['kind' => $scan['type']];
                if ($scan['type'] === 'persona_recurrente') {
                    $payload['persona'] = normalize_persona_scan($scan['item'], $pdo, $residencialId);
                    $payload['requires_pin'] = true;
                } elseif ($scan['type'] === 'visitante_rapido') {
                    $payload['visitante_rapido'] = normalize_visitante_scan($scan['item']);
                    $payload['requires_evidence'] = true;
                } elseif ($scan['type'] === 'permiso_material') {
                    $payload['permiso_material'] = normalize_permiso_scan($scan['item']);
                    $payload['requires_evidence'] = true;
                } else {
                    $payload['orden_servicio'] = normalize_orden_servicio_scan($pdo, $residencialId, $scan['item']);
                    $payload['requires_confirmation'] = (bool)$payload['orden_servicio']['requiere_confirmacion'];
                }
                out(true, ['data' => $payload]);
            }
        }

        $stmt = $pdo->prepare("
            SELECT
                v.*,
                u.clave AS unidad_clave,
                u.id AS unidad_id,
                r.name AS residente_nombre
            FROM visitas v
            JOIN unidades u ON u.id = v.unidad_id
            JOIN users r ON r.id = v.residente_id
            WHERE v.residencial_id = :rid
              AND v.codigo_acceso = :code
            LIMIT 1
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'code' => $code,
        ]);
        $visita = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$visita) {
            out(false, ['error' => 'Código no encontrado o no pertenece a este cliente.'], 404);
        }

        $ev = evaluar_visita($visita);
        out(true, [
            'data' => [
                'kind' => 'visita_residencial',
                'visita' => $visita,
                'eval' => $ev,
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'buscar_residente') {
        $q = strv($_GET['q'] ?? '', 120);
        if ($q === '') {
            out(false, ['error' => 'Escribe algo para buscar al residente.'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT
                u.id AS user_id,
                u.name,
                u.email,
                u.telefono,
                u.is_active,
                ur.residencial_id,
                ru.id AS resid_unid_id,
                ru.unidad_id,
                ru.activo AS activo_servicio,
                ru.acceso_baneado_manual,
                ru.acceso_baneo_motivo,
                un.clave AS unidad_clave
            FROM users u
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            LEFT JOIN residentes_unidades ru ON ru.user_id = u.id
            LEFT JOIN unidades un ON un.id = ru.unidad_id
            WHERE ur.residencial_id = :rid
              AND t.nombre = 'residente'
              AND (
                u.name LIKE :q
                OR u.email LIKE :q
                OR u.telefono LIKE :q
                OR un.clave LIKE :q
              )
            ORDER BY u.name ASC
            LIMIT 20
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'q' => '%' . $q . '%',
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $items = array_map(function (array $row) use ($pdo) {
            $access = resident_access_status($pdo, $row);
            return [
                'user_id' => (int)$row['user_id'],
                'resid_unid_id' => (int)($row['resid_unid_id'] ?? 0),
                'name' => (string)($row['name'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'telefono' => (string)($row['telefono'] ?? ''),
                'unidad_id' => (int)($row['unidad_id'] ?? 0),
                'unidad_clave' => (string)($row['unidad_clave'] ?? '—'),
                'access' => $access,
            ];
        }, $rows);

        out(true, ['data' => ['items' => $items]]);
    }

    if ($method === 'GET' && $action === 'personas_dentro') {
        $stmt = $pdo->prepare("
            SELECT p.id, p.nombre, p.empresa, p.puesto, p.telefono, p.foto_url, p.ultima_entrada_at, a.nombre AS area_nombre
            FROM personas_recurrentes p
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            WHERE p.residencial_id = :rid
              AND p.activo = 1
              AND p.esta_dentro = 1
            ORDER BY p.ultima_entrada_at DESC, p.nombre ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    if ($method === 'GET' && $action === 'materiales_autorizados') {
        $stmt = $pdo->prepare("
            SELECT
                p.id, p.tipo_movimiento, p.estado, p.aprobado_at, p.notas,
                a.nombre AS area_nombre,
                u.name AS responsable_nombre
            FROM permisos_materiales p
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            LEFT JOIN users u ON u.id = p.responsable_user_id
            WHERE p.residencial_id = :rid
              AND p.estado IN ('aprobado', 'en_proceso')
            ORDER BY p.aprobado_at DESC, p.created_at DESC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$row) {
            $itemsStmt = $pdo->prepare("
                SELECT material_nombre, cantidad_texto
                FROM permisos_materiales_items
                WHERE permiso_id = :permiso_id
                ORDER BY id ASC
            ");
            $itemsStmt->execute(['permiso_id' => (int)$row['id']]);
            $row['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        out(true, ['data' => ['items' => $items]]);
    }

    if ($method === 'GET' && $action === 'bitacora_hoy') {
        $stmt = $pdo->prepare("
            SELECT
                b.*,
                g.name AS guardia_nombre,
                p.nombre AS persona_nombre,
                vr.nombre_visitante,
                pm.tipo_movimiento AS permiso_tipo_movimiento,
                a.nombre AS area_nombre
            FROM bitacora_operativa b
            LEFT JOIN users g ON g.id = b.guardia_id
            LEFT JOIN personas_recurrentes p ON p.id = b.persona_recurrente_id
            LEFT JOIN visitantes_rapidos vr ON vr.id = b.visitante_rapido_id
            LEFT JOIN permisos_materiales pm ON pm.id = b.permiso_material_id
            LEFT JOIN areas_operativas a ON a.id = b.area_id
            WHERE b.residencial_id = :rid
              AND DATE(b.fecha_hora) = CURDATE()
            ORDER BY b.fecha_hora DESC, b.id DESC
            LIMIT 150
        ");
        $stmt->execute(['rid' => $residencialId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ids = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $items)));
        $evidenceMap = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $evStmt = $pdo->prepare("
                SELECT entidad_id, public_url
                FROM archivos_operativos
                WHERE residencial_id = ?
                  AND entidad_tipo = 'bitacora_operativa'
                  AND entidad_id IN ($placeholders)
                ORDER BY id ASC
            ");
            $evStmt->execute(array_merge([$residencialId], $ids));
            $rows = $evStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $eid = (int)($row['entidad_id'] ?? 0);
                if ($eid <= 0) {
                    continue;
                }
                $evidenceMap[$eid] = $evidenceMap[$eid] ?? [];
                $evidenceMap[$eid][] = (string)($row['public_url'] ?? '');
            }
        }

        foreach ($items as &$item) {
            $bid = (int)($item['id'] ?? 0);
            $item['evidencias'] = $evidenceMap[$bid] ?? [];
        }
        unset($item);

        out(true, ['data' => ['items' => $items]]);
    }

    if ($method === 'POST' && $action === 'scan') {
        $code = strv($_POST['code'] ?? '', 64);
        if ($code === '') out(false, ['error' => 'Falta code'], 422);

        $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
        $allowedTipoEvento = ['entrada', 'salida', 'verificacion'];
        if (!in_array($tipoEvento, $allowedTipoEvento, true)) {
            out(false, ['error' => 'tipo_evento inválido'], 422);
        }

        $override = strv($_POST['override'] ?? '', 20);
        if ($override !== '' && !in_array($override, ['permitido', 'denegado'], true)) {
            out(false, ['error' => 'override inválido'], 422);
        }

        $obsUser = strv($_POST['observaciones'] ?? '', 255);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                v.*,
                u.clave AS unidad_clave,
                r.name AS residente_nombre
            FROM visitas v
            JOIN unidades u ON u.id = v.unidad_id
            JOIN users r ON r.id = v.residente_id
            WHERE v.residencial_id = :rid
              AND v.codigo_acceso = :code
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'code' => $code,
        ]);

        $visita = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$visita) {
            $ins = $pdo->prepare("
                INSERT INTO accesos_guardia (visita_id, guardia_id, tipo_evento, resultado, observaciones)
                VALUES (0, :gid, :tipo, 'denegado', :obs)
            ");
            $ins->execute([
                'gid' => $guardiaId,
                'tipo' => $tipoEvento,
                'obs' => ('Código no encontrado en este cliente. ' . $obsUser),
            ]);

            $pdo->commit();
            out(false, ['error' => 'Código no encontrado o no pertenece a este cliente.'], 404);
        }

        $today = date('Y-m-d');
        if ($today > ($visita['fecha_hasta'] ?? $today) && ($visita['estado'] ?? '') === 'pendiente') {
            $u = $pdo->prepare("UPDATE visitas SET estado='vencido' WHERE id=:id");
            $u->execute(['id' => (int)$visita['id']]);
            $visita['estado'] = 'vencido';
        }

        $ev = evaluar_visita($visita);
        $permitido = ($override !== '') ? ($override === 'permitido') : (bool)$ev['permitido'];
        $motivoDenegado = $permitido ? '' : ($ev['motivo'] ?: 'Acceso denegado.');

        $resultado = $permitido ? 'permitido' : 'denegado';
        $obs = trim(($motivoDenegado ? $motivoDenegado . ' ' : '') . $obsUser);

        $ins = $pdo->prepare("
            INSERT INTO accesos_guardia (visita_id, guardia_id, tipo_evento, resultado, observaciones)
            VALUES (:vid, :gid, :tipo, :res, :obs)
        ");
        $ins->execute([
            'vid' => (int)$visita['id'],
            'gid' => $guardiaId,
            'tipo' => $tipoEvento,
            'res' => $resultado,
            'obs' => ($obs !== '' ? $obs : null),
        ]);

        if ($permitido && (int)($visita['uso_unico'] ?? 0) === 1 && ($visita['estado'] ?? '') !== 'usado') {
            $up = $pdo->prepare("UPDATE visitas SET estado='usado' WHERE id=:id");
            $up->execute(['id' => (int)$visita['id']]);
            $visita['estado'] = 'usado';
        }

        $pdo->commit();

        out(true, [
            'message' => $permitido ? 'Acceso permitido' : 'Acceso denegado',
            'permitido' => $permitido,
            'visita' => $visita,
            'resultado' => $resultado,
            'eval' => $ev,
        ]);
    }

    if ($method === 'POST' && $action === 'confirm_persona_recurrente') {
        guardia_require_module_access($serviceProfile, $isSuperAdmin, 'personal_recurrente', 'El módulo de personal recurrente no está habilitado para este cliente.');

        $personaId = intv_safe($_POST['persona_id'] ?? 0, 0);
        $pin = preg_replace('/\D+/', '', (string)($_POST['pin'] ?? ''));
        $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
        $observaciones = strv($_POST['observaciones'] ?? '', 500);
        $cumplimientoConfirmado = operational_bool_int($_POST['cumplimiento_confirmado'] ?? 0) === 1;

        if ($personaId <= 0 || $pin === '') {
            out(false, ['error' => 'Debes seleccionar una persona y capturar su PIN.'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT p.*, a.nombre AS area_nombre
            FROM personas_recurrentes p
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            WHERE p.id = :id
              AND p.residencial_id = :rid
            LIMIT 1
            FOR UPDATE
        ");
        $pdo->beginTransaction();
        $stmt->execute([
            'id' => $personaId,
            'rid' => $residencialId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            $pdo->rollBack();
            out(false, ['error' => 'No encontramos a la persona seleccionada.'], 404);
        }

        $compliance = compliance_effective_for_person($pdo, $residencialId, $personaId, $row);
        $pinValido = password_verify($pin, (string)$row['pin_hash']);
        $permitido = (int)$row['activo'] === 1 && $pinValido && (bool)$compliance['puede_ingresar'];
        $motivo = '';
        if ((int)$row['activo'] !== 1) {
            $motivo = 'La persona está inactiva.';
        } elseif (!$pinValido) {
            $motivo = 'PIN incorrecto.';
        } elseif (!(bool)$compliance['puede_ingresar']) {
            $motivo = sprintf(
                'Acceso bloqueado: %s%s',
                $compliance['proveedor_nombre'] ? 'proveedor ' . $compliance['proveedor_nombre'] . ' está bloqueado.' : 'persona bloqueada.',
                $compliance['cumplimiento_motivo'] ? ' Motivo: ' . $compliance['cumplimiento_motivo'] : ''
            );
        } elseif ((bool)$compliance['requiere_confirmacion'] && !$cumplimientoConfirmado) {
            $motivo = 'El acceso requiere confirmación manual por cumplimiento.';
            $permitido = false;
        }

        if ($permitido) {
            if ($tipoEvento === 'entrada') {
                $update = $pdo->prepare("
                    UPDATE personas_recurrentes
                    SET esta_dentro = 1,
                        ultima_entrada_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $update->execute(['id' => $personaId]);
            } elseif ($tipoEvento === 'salida') {
                $update = $pdo->prepare("
                    UPDATE personas_recurrentes
                    SET esta_dentro = 0,
                        ultima_salida_at = NOW(),
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $update->execute(['id' => $personaId]);
            }
        }

        $tipoOrigenBitacora = 'persona_recurrente';
        $tipoEventoBitacora = $tipoEvento;
        $resultadoBitacora = $permitido ? 'permitido' : 'denegado';
        $observacionesBitacora = $permitido ? $observaciones : trim(($motivo ?: 'Acceso denegado.') . ' ' . $observaciones);

        if (!$pinValido || (int)$row['activo'] !== 1) {
            $tipoOrigenBitacora = 'persona_recurrente';
        } elseif (!(bool)$compliance['puede_ingresar']) {
            $tipoOrigenBitacora = 'cumplimiento';
            $tipoEventoBitacora = 'acceso_bloqueado_cumplimiento';
            $resultadoBitacora = 'rechazado';
        } elseif ($permitido && (bool)$compliance['requiere_confirmacion']) {
            $tipoOrigenBitacora = 'cumplimiento';
            $tipoEventoBitacora = 'acceso_advertencia_cumplimiento';
            $resultadoBitacora = 'permitido';
            $observacionesBitacora = trim(($compliance['cumplimiento_motivo'] ?: 'Acceso con advertencia de cumplimiento.') . ' ' . $observaciones);
        }

        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => $tipoOrigenBitacora,
            'origen_id' => $personaId,
            'tipo_evento' => $tipoEventoBitacora,
            'resultado' => $resultadoBitacora,
            'persona_recurrente_id' => $personaId,
            'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
            'observaciones' => $observacionesBitacora,
            'metadata_json' => [
                'pin_valid' => $pinValido,
                'cumplimiento' => $compliance,
                'cumplimiento_confirmado' => $cumplimientoConfirmado,
                'tipo_movimiento_solicitado' => $tipoEvento,
            ],
        ]);

        $pdo->commit();

        out(true, [
            'message' => $permitido
                ? ($tipoEvento === 'salida' ? 'Salida registrada correctamente.' : 'Entrada registrada correctamente.')
                : ($motivo ?: 'Acceso denegado.'),
            'permitido' => $permitido,
            'cumplimiento' => $compliance,
            'data' => [
                'kind' => 'persona_recurrente',
                'persona' => normalize_persona_scan(array_merge($row, [
                    'esta_dentro' => $permitido ? ($tipoEvento === 'salida' ? 0 : 1) : (int)$row['esta_dentro'],
                ]), $pdo, $residencialId),
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'confirm_orden_servicio') {
        guardia_require_module_access($serviceProfile, $isSuperAdmin, 'ordenes_servicio', 'El módulo de órdenes de servicio no está habilitado para este cliente.');

        $orderId = intv_safe($_POST['orden_id'] ?? 0, 0);
        $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
        $observaciones = strv($_POST['observaciones'] ?? '', 500);
        $confirmado = operational_bool_int($_POST['cumplimiento_confirmado'] ?? 0) === 1;

        if ($orderId <= 0 || !in_array($tipoEvento, ['entrada', 'salida'], true)) {
            out(false, ['error' => 'Orden o movimiento inválido.'], 422);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT
                os.*,
                p.nombre_comercial AS proveedor_nombre,
                p.estatus_cumplimiento AS proveedor_cumplimiento,
                p.motivo_bloqueo AS proveedor_motivo,
                pr.nombre AS persona_nombre,
                pr.empresa AS persona_empresa,
                pr.puesto AS persona_puesto,
                pr.estatus_cumplimiento AS persona_cumplimiento,
                pr.motivo_bloqueo AS persona_motivo,
                a.nombre AS area_nombre
            FROM ordenes_servicio os
            LEFT JOIN proveedores p ON p.id = os.proveedor_id AND p.residencial_id = os.residencial_id
            LEFT JOIN personas_recurrentes pr ON pr.id = os.persona_recurrente_id AND pr.residencial_id = os.residencial_id
            LEFT JOIN areas_operativas a ON a.id = os.area_id AND a.residencial_id = os.residencial_id
            WHERE os.id = :id
              AND os.residencial_id = :rid
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['id' => $orderId, 'rid' => $residencialId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            $pdo->rollBack();
            out(false, ['error' => 'Orden de servicio no encontrada.'], 404);
        }

        $schedule = orden_servicio_schedule_eval($row);
        $compliance = orden_servicio_compliance($pdo, $residencialId, $row);
        $permitido = (bool)$schedule['permitido'] && (bool)$compliance['puede_ingresar'];
        $warning = ((bool)$schedule['requiere_confirmacion'] || (bool)$compliance['requiere_confirmacion']);
        $motivo = trim(($schedule['motivo'] ?? '') . ' ' . ($compliance['cumplimiento_motivo'] ?? ''));

        if ((string)$row['estatus'] === 'programada' && $tipoEvento !== 'entrada') {
            $permitido = false;
            $motivo = 'La orden debe registrar entrada antes de salida.';
        } elseif ((string)$row['estatus'] === 'en_proceso' && $tipoEvento !== 'salida') {
            $permitido = false;
            $motivo = 'La orden ya está en proceso; corresponde registrar salida.';
        } elseif (in_array((string)$row['estatus'], ['pendiente_validacion', 'cerrada', 'cancelada'], true)) {
            $permitido = false;
            $motivo = 'La orden no acepta movimientos desde Guardia en su estado actual.';
        }

        if ($permitido && $warning && !$confirmado) {
            $permitido = false;
            $motivo = $motivo ?: 'La orden requiere confirmación manual.';
        }

        if ($permitido) {
            if ($tipoEvento === 'entrada') {
                $up = $pdo->prepare("
                    UPDATE ordenes_servicio
                    SET estatus='en_proceso',
                        inicio_real_at = COALESCE(inicio_real_at, NOW()),
                        updated_at=NOW()
                    WHERE id=:id AND residencial_id=:rid
                ");
                $up->execute(['id' => $orderId, 'rid' => $residencialId]);
                $row['estatus'] = 'en_proceso';
                $row['inicio_real_at'] = $row['inicio_real_at'] ?: date('Y-m-d H:i:s');
            } else {
                $up = $pdo->prepare("
                    UPDATE ordenes_servicio
                    SET estatus='pendiente_validacion',
                        updated_at=NOW()
                    WHERE id=:id AND residencial_id=:rid
                ");
                $up->execute(['id' => $orderId, 'rid' => $residencialId]);
                $row['estatus'] = 'pendiente_validacion';
            }
        }

        $tipoEventoBitacora = $permitido
            ? ($warning ? 'orden_servicio_advertencia' : 'orden_servicio_' . $tipoEvento)
            : 'orden_servicio_rechazada';
        $resultado = $permitido ? 'permitido' : 'rechazado';

        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'orden_servicio',
            'origen_id' => $orderId,
            'tipo_evento' => $tipoEventoBitacora,
            'resultado' => $resultado,
            'persona_recurrente_id' => ($row['persona_recurrente_id'] ?? null) !== null ? (int)$row['persona_recurrente_id'] : null,
            'area_id' => ($row['area_id'] ?? null) !== null ? (int)$row['area_id'] : null,
            'observaciones' => trim(($permitido ? '' : ($motivo ?: 'Orden rechazada.') . ' ') . $observaciones),
            'metadata_json' => [
                'folio' => (string)($row['folio'] ?? ''),
                'proveedor_id' => ($row['proveedor_id'] ?? null) !== null ? (int)$row['proveedor_id'] : null,
                'proveedor_nombre' => (string)($row['proveedor_nombre'] ?? ''),
                'persona_recurrente_id' => ($row['persona_recurrente_id'] ?? null) !== null ? (int)$row['persona_recurrente_id'] : null,
                'area_id' => ($row['area_id'] ?? null) !== null ? (int)$row['area_id'] : null,
                'semaforo_estado' => $compliance['cumplimiento_estado'],
                'horario_validado' => (bool)$schedule['horario_validado'],
                'cumplimiento_confirmado' => $confirmado,
                'tipo_movimiento_solicitado' => $tipoEvento,
                'qr_token' => (string)($row['qr_token'] ?? ''),
            ],
        ]);

        $pdo->commit();

        out(true, [
            'message' => $permitido
                ? ($tipoEvento === 'salida' ? 'Salida de orden registrada. Pendiente validación de admin.' : 'Entrada de orden registrada.')
                : ($motivo ?: 'Orden rechazada.'),
            'permitido' => $permitido,
            'data' => [
                'kind' => 'orden_servicio',
                'orden_servicio' => normalize_orden_servicio_scan($pdo, $residencialId, $row),
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'confirm_visitante_rapido') {
        guardia_require_module_access($serviceProfile, $isSuperAdmin, 'visitantes_rapidos', 'El módulo de visitantes rápidos no está habilitado para este cliente.');

        $visitanteId = intv_safe($_POST['visitante_id'] ?? 0, 0);
        $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
        $observaciones = strv($_POST['observaciones'] ?? '', 500);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT *
            FROM visitantes_rapidos
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            'id' => $visitanteId,
            'rid' => $residencialId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            $pdo->rollBack();
            out(false, ['error' => 'No encontramos el acceso rápido seleccionado.'], 404);
        }

        $eval = visitante_vigente($row);
        $permitido = (bool)$eval['permitido'];
        $motivo = $eval['motivo'] ?: '';
        $evidencia = null;

        if ($permitido && !empty($_FILES['evidencia']['name'] ?? '')) {
            $evidencia = save_operational_file_record($pdo, $residencialId, 'visitante_rapido', (int)$row['id'], $_FILES['evidencia'], 'caseta_evidencia', true);
        }

        if ($permitido) {
            $nuevoEstado = $tipoEvento === 'salida' ? 'finalizado' : 'en_curso';
            $up = $pdo->prepare("
                UPDATE visitantes_rapidos
                SET estado = :estado,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $up->execute([
                'estado' => $nuevoEstado,
                'id' => (int)$row['id'],
            ]);
            $row['estado'] = $nuevoEstado;
        }

        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'visitante_rapido',
            'origen_id' => (int)$row['id'],
            'tipo_evento' => $tipoEvento,
            'resultado' => $permitido ? 'permitido' : 'denegado',
            'visitante_rapido_id' => (int)$row['id'],
            'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
            'observaciones' => $permitido ? $observaciones : trim(($motivo ?: 'Acceso denegado.') . ' ' . $observaciones),
            'metadata_json' => [
                'placa_vehiculo' => $row['placa_vehiculo'] ?? null,
                'evidencia_url' => $evidencia['public_url'] ?? null,
            ],
        ]);

        $pdo->commit();

        out(true, [
            'message' => $permitido
                ? ($tipoEvento === 'salida' ? 'Salida de visitante registrada.' : 'Entrada de visitante registrada.')
                : ($motivo ?: 'Acceso denegado.'),
            'permitido' => $permitido,
            'data' => [
                'kind' => 'visitante_rapido',
                'visitante_rapido' => normalize_visitante_scan($row),
                'evidencia_url' => $evidencia['public_url'] ?? null,
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'confirm_permiso_material') {
        guardia_require_module_access($serviceProfile, $isSuperAdmin, 'materiales', 'El módulo de materiales no está habilitado para este cliente.');

        $permisoId = intv_safe($_POST['permiso_material_id'] ?? 0, 0);
        $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
        $observaciones = strv($_POST['observaciones'] ?? '', 500);

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT *
            FROM permisos_materiales
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([
            'id' => $permisoId,
            'rid' => $residencialId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            $pdo->rollBack();
            out(false, ['error' => 'No encontramos el permiso seleccionado.'], 404);
        }

        $permitido = in_array((string)$row['estado'], ['aprobado', 'en_proceso'], true);
        $motivo = $permitido ? '' : 'El permiso no está aprobado o ya no es válido.';
        $evidencia = null;

        if ($permitido && !empty($_FILES['evidencia']['name'] ?? '')) {
            $evidencia = save_operational_file_record($pdo, $residencialId, 'permiso_material', (int)$row['id'], $_FILES['evidencia'], 'caseta_evidencia', true);
        }

        if ($permitido) {
            $nuevoEstado = $tipoEvento === 'salida' ? 'usado' : 'en_proceso';
            $up = $pdo->prepare("
                UPDATE permisos_materiales
                SET estado = :estado,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $up->execute([
                'estado' => $nuevoEstado,
                'id' => (int)$row['id'],
            ]);
            $row['estado'] = $nuevoEstado;
        }

        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'permiso_material',
            'origen_id' => (int)$row['id'],
            'tipo_evento' => $tipoEvento,
            'resultado' => $permitido ? 'permitido' : 'denegado',
            'permiso_material_id' => (int)$row['id'],
            'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
            'observaciones' => $permitido ? $observaciones : trim($motivo . ' ' . $observaciones),
            'metadata_json' => [
                'evidencia_url' => $evidencia['public_url'] ?? null,
                'tipo_movimiento' => $row['tipo_movimiento'] ?? null,
            ],
        ]);

        $pdo->commit();

        $permiso = fetch_permiso_material_by_token($pdo, $residencialId, (string)$row['qr_token']);
        out(true, [
            'message' => $permitido
                ? ($tipoEvento === 'salida' ? 'Salida de material registrada.' : 'Entrada de material registrada.')
                : $motivo,
            'permitido' => $permitido,
            'data' => [
                'kind' => 'permiso_material',
                'permiso_material' => $permiso ? normalize_permiso_scan($permiso) : null,
                'evidencia_url' => $evidencia['public_url'] ?? null,
            ],
        ]);
    }

    if ($method === 'POST' && $action === 'scan_residente') {
        $residentId = intv_safe($_POST['resident_id'] ?? 0, 0);
        $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
        $allowedTipoEvento = ['entrada', 'salida', 'verificacion'];

        if ($residentId <= 0) {
            out(false, ['error' => 'Falta seleccionar al residente.'], 422);
        }
        if (!in_array($tipoEvento, $allowedTipoEvento, true)) {
            out(false, ['error' => 'tipo_evento inválido'], 422);
        }

        $resident = resident_access_status_for_user($pdo, $residentId, $residencialId);
        if (!$resident) {
            out(false, ['error' => 'Residente no encontrado en este residencial.'], 404);
        }

        $access = $resident['access'];
        $permitido = (bool)($access['allow_direct_access'] ?? false);
        $resultado = $permitido ? 'permitido' : 'denegado';
        $obs = $permitido ? 'Acceso directo de residente validado.' : (string)($access['reason'] ?? 'Acceso directo denegado.');

        $stmt = $pdo->prepare("
            INSERT INTO accesos_guardia (
                visita_id, guardia_id, fecha_hora, tipo_evento, resultado, observaciones, origen_acceso, residente_id, unidad_id
            ) VALUES (
                0, :gid, NOW(), :tipo, :resultado, :obs, 'residente_directo', :resident_id, :unidad_id
            )
        ");
        $stmt->execute([
            'gid' => $guardiaId,
            'tipo' => $tipoEvento,
            'resultado' => $resultado,
            'obs' => $obs,
            'resident_id' => $residentId,
            'unidad_id' => (int)($resident['unidad_id'] ?? 0) ?: null,
        ]);

        out(true, [
            'message' => $permitido ? 'Acceso directo permitido.' : 'Acceso directo denegado.',
            'permitido' => $permitido,
            'resident' => [
                'user_id' => (int)$resident['user_id'],
                'name' => (string)$resident['name'],
                'email' => (string)$resident['email'],
                'telefono' => (string)$resident['telefono'],
                'unidad_clave' => (string)($resident['unidad_clave'] ?? '—'),
            ],
            'access' => $access,
            'resultado' => $resultado,
        ]);
    }

    if ($method === 'GET' && $action === 'hist') {
        $limit = max(1, min(100, intv_safe($_GET['limit'] ?? 50, 50)));

        if (operational_is_operational_mode($operationalMode)) {
            $stmt = $pdo->prepare("
                SELECT
                    b.id,
                    b.fecha_hora,
                    b.tipo_evento,
                    b.resultado,
                    b.observaciones,
                    b.tipo_origen,
                    p.nombre AS persona_nombre,
                    vr.nombre_visitante,
                    pm.tipo_movimiento AS permiso_tipo_movimiento,
                    a.nombre AS area_nombre
                FROM bitacora_operativa b
                LEFT JOIN personas_recurrentes p ON p.id = b.persona_recurrente_id
                LEFT JOIN visitantes_rapidos vr ON vr.id = b.visitante_rapido_id
                LEFT JOIN permisos_materiales pm ON pm.id = b.permiso_material_id
                LEFT JOIN areas_operativas a ON a.id = b.area_id
                WHERE b.residencial_id = :rid
                  AND b.guardia_id = :gid
                ORDER BY b.fecha_hora DESC, b.id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':rid', $residencialId, PDO::PARAM_INT);
            $stmt->bindValue(':gid', $guardiaId, PDO::PARAM_INT);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
        }

        $stmt = $pdo->prepare("
            SELECT
                ag.id,
                ag.fecha_hora,
                ag.tipo_evento,
                ag.resultado,
                ag.observaciones,
                v.codigo_acceso,
                v.nombre_visitante,
                v.placa_vehiculo,
                COALESCE(u.clave, uru.clave) AS unidad_clave,
                ag.origen_acceso,
                ru.name AS residente_nombre
            FROM accesos_guardia ag
            LEFT JOIN visitas v ON v.id = ag.visita_id
            LEFT JOIN unidades u ON u.id = v.unidad_id
            LEFT JOIN users ru ON ru.id = ag.residente_id
            LEFT JOIN unidades uru ON uru.id = ag.unidad_id
            WHERE ag.guardia_id = :gid
            ORDER BY ag.fecha_hora DESC, ag.id DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':gid', $guardiaId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    out(false, ['error' => 'Acción no soportada'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar el control de accesos.');
}
