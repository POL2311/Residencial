<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
require_once __DIR__ . '/../../../config/compliance_helpers.php';

admin_module_required('ordenes_servicio', 'El módulo de órdenes de servicio no está habilitado para este cliente.');
admin_operational_required('Las órdenes de servicio están disponibles para modos operativos.');

function os_statuses(): array
{
    return ['programada', 'en_proceso', 'pendiente_validacion', 'cerrada', 'cancelada'];
}

function os_priorities(): array
{
    return ['baja', 'media', 'alta', 'critica'];
}

function os_evidence_types(): array
{
    return ['antes', 'despues', 'cierre', 'general'];
}

function os_status(string $value, string $fallback = 'programada'): string
{
    $value = clean_str($value);
    return in_array($value, os_statuses(), true) ? $value : $fallback;
}

function os_priority(string $value, string $fallback = 'media'): string
{
    $value = clean_str($value);
    return in_array($value, os_priorities(), true) ? $value : $fallback;
}

function os_date_or_null(mixed $value): ?string
{
    $value = trim((string)($value ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function os_time_or_null(mixed $value): ?string
{
    $value = trim((string)($value ?? ''));
    return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? substr($value, 0, 5) . ':00' : null;
}

function os_new_token(PDO $pdo): string
{
    do {
        $token = 'so-' . bin2hex(random_bytes(12));
        $stmt = $pdo->prepare("SELECT 1 FROM ordenes_servicio WHERE qr_token = :token LIMIT 1");
        $stmt->execute(['token' => $token]);
    } while ($stmt->fetchColumn());
    return $token;
}

function os_new_folio(PDO $pdo, int $residencialId): string
{
    $prefix = 'OS-' . date('Ymd') . '-';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ordenes_servicio WHERE residencial_id = :rid AND folio LIKE :prefix");
    $stmt->execute(['rid' => $residencialId, 'prefix' => $prefix . '%']);
    $next = (int)$stmt->fetchColumn() + 1;
    do {
        $folio = $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        $check = $pdo->prepare("SELECT 1 FROM ordenes_servicio WHERE residencial_id = :rid AND folio = :folio LIMIT 1");
        $check->execute(['rid' => $residencialId, 'folio' => $folio]);
        $next++;
    } while ($check->fetchColumn());
    return $folio;
}

function os_provider(PDO $pdo, int $residencialId, int $providerId): ?array
{
    if ($providerId <= 0 || !operational_table_exists($pdo, 'proveedores')) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = :id AND residencial_id = :rid LIMIT 1");
    $stmt->execute(['id' => $providerId, 'rid' => $residencialId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function os_person(PDO $pdo, int $residencialId, int $personId): ?array
{
    if ($personId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("
        SELECT p.*, a.nombre AS area_nombre
        FROM personas_recurrentes p
        LEFT JOIN areas_operativas a ON a.id = p.area_id AND a.residencial_id = p.residencial_id
        WHERE p.id = :id AND p.residencial_id = :rid
        LIMIT 1
    ");
    $stmt->execute(['id' => $personId, 'rid' => $residencialId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function os_area(PDO $pdo, int $residencialId, int $areaId): ?array
{
    if ($areaId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, nombre, codigo FROM areas_operativas WHERE id = :id AND residencial_id = :rid LIMIT 1");
    $stmt->execute(['id' => $areaId, 'rid' => $residencialId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function os_provider_compliance(?array $provider): array
{
    if (!$provider) {
        return [
            'cumplimiento_estado' => 'autorizado',
            'cumplimiento_label' => compliance_label('autorizado'),
            'cumplimiento_motivo' => '',
            'puede_ingresar' => true,
            'requiere_confirmacion' => false,
        ];
    }
    $status = compliance_normalize_status((string)($provider['estatus_cumplimiento'] ?? 'autorizado'));
    return [
        'cumplimiento_estado' => $status,
        'cumplimiento_label' => compliance_label($status),
        'cumplimiento_motivo' => (string)($provider['motivo_bloqueo'] ?? ''),
        'proveedor_id' => (int)$provider['id'],
        'proveedor_nombre' => (string)$provider['nombre_comercial'],
        'puede_ingresar' => !compliance_is_blocking($status),
        'requiere_confirmacion' => compliance_requires_confirmation($status),
    ];
}

function os_effective_compliance(PDO $pdo, int $residencialId, ?array $provider, ?array $person): array
{
    $personCompliance = $person ? compliance_effective_for_person($pdo, $residencialId, (int)$person['id'], $person) : null;
    $providerCompliance = os_provider_compliance($provider);
    if (!$personCompliance) {
        return $providerCompliance;
    }
    return compliance_pick_worst([
        [
            'status' => $providerCompliance['cumplimiento_estado'],
            'motivo' => $providerCompliance['cumplimiento_motivo'],
            'source' => 'proveedor',
            'provider' => $provider ? [
                'id' => (int)$provider['id'],
                'nombre' => (string)$provider['nombre_comercial'],
            ] : null,
        ],
        [
            'status' => $personCompliance['cumplimiento_estado'],
            'motivo' => $personCompliance['cumplimiento_motivo'],
            'source' => $personCompliance['cumplimiento_origen'] ?? 'persona',
            'provider' => $personCompliance['proveedor_id'] ? [
                'id' => (int)$personCompliance['proveedor_id'],
                'nombre' => (string)$personCompliance['proveedor_nombre'],
            ] : null,
        ],
    ]) + [
        'cumplimiento_estado' => $personCompliance['cumplimiento_estado'],
        'cumplimiento_label' => $personCompliance['cumplimiento_label'],
        'cumplimiento_motivo' => $personCompliance['cumplimiento_motivo'],
        'proveedor_id' => $personCompliance['proveedor_id'],
        'proveedor_nombre' => $personCompliance['proveedor_nombre'],
        'puede_ingresar' => $personCompliance['puede_ingresar'] && $providerCompliance['puede_ingresar'],
        'requiere_confirmacion' => $personCompliance['requiere_confirmacion'] || $providerCompliance['requiere_confirmacion'],
    ];
}

function os_normalize_row(PDO $pdo, int $residencialId, array $row): array
{
    $provider = !empty($row['proveedor_id']) ? [
        'id' => (int)$row['proveedor_id'],
        'nombre_comercial' => (string)($row['proveedor_nombre'] ?? ''),
        'estatus_cumplimiento' => (string)($row['proveedor_cumplimiento'] ?? 'autorizado'),
        'motivo_bloqueo' => (string)($row['proveedor_motivo'] ?? ''),
    ] : null;
    $person = !empty($row['persona_recurrente_id']) ? [
        'id' => (int)$row['persona_recurrente_id'],
        'estatus_cumplimiento' => (string)($row['persona_cumplimiento'] ?? 'autorizado'),
        'motivo_bloqueo' => (string)($row['persona_motivo'] ?? ''),
    ] : null;
    $compliance = os_effective_compliance($pdo, $residencialId, $provider, $person);
    $status = compliance_normalize_status((string)($compliance['status'] ?? $compliance['cumplimiento_estado'] ?? 'autorizado'));
    $motivo = (string)($compliance['motivo'] ?? $compliance['cumplimiento_motivo'] ?? '');

    return [
        'id' => (int)$row['id'],
        'folio' => (string)$row['folio'],
        'qr_token' => (string)$row['qr_token'],
        'qr_payload' => 'op:so:' . (string)$row['qr_token'],
        'tipo_servicio' => (string)($row['tipo_servicio'] ?? ''),
        'descripcion' => (string)($row['descripcion'] ?? ''),
        'fecha_programada' => (string)$row['fecha_programada'],
        'hora_inicio' => (string)($row['hora_inicio'] ?? ''),
        'hora_fin' => (string)($row['hora_fin'] ?? ''),
        'estatus' => os_status((string)$row['estatus']),
        'prioridad' => os_priority((string)$row['prioridad']),
        'proveedor_id' => $row['proveedor_id'] !== null ? (int)$row['proveedor_id'] : null,
        'proveedor_nombre' => (string)($row['proveedor_nombre'] ?? ''),
        'persona_recurrente_id' => $row['persona_recurrente_id'] !== null ? (int)$row['persona_recurrente_id'] : null,
        'persona_nombre' => (string)($row['persona_nombre'] ?? ''),
        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area_nombre' => (string)($row['area_nombre'] ?? ''),
        'inicio_real_at' => (string)($row['inicio_real_at'] ?? ''),
        'cierre_at' => (string)($row['cierre_at'] ?? ''),
        'observaciones_cierre' => (string)($row['observaciones_cierre'] ?? ''),
        'activo' => (int)($row['activo'] ?? 1),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'cumplimiento' => [
            'cumplimiento_estado' => $status,
            'cumplimiento_label' => compliance_label($status),
            'cumplimiento_motivo' => $motivo,
            'puede_ingresar' => !compliance_is_blocking($status),
            'requiere_confirmacion' => compliance_requires_confirmation($status),
        ],
    ];
}

function os_base_select(): string
{
    return "
        SELECT
            os.*,
            p.nombre_comercial AS proveedor_nombre,
            p.estatus_cumplimiento AS proveedor_cumplimiento,
            p.motivo_bloqueo AS proveedor_motivo,
            pr.nombre AS persona_nombre,
            pr.estatus_cumplimiento AS persona_cumplimiento,
            pr.motivo_bloqueo AS persona_motivo,
            a.nombre AS area_nombre
        FROM ordenes_servicio os
        LEFT JOIN proveedores p ON p.id = os.proveedor_id AND p.residencial_id = os.residencial_id
        LEFT JOIN personas_recurrentes pr ON pr.id = os.persona_recurrente_id AND pr.residencial_id = os.residencial_id
        LEFT JOIN areas_operativas a ON a.id = os.area_id AND a.residencial_id = os.residencial_id
    ";
}

function os_fetch_order(PDO $pdo, int $residencialId, int $orderId): ?array
{
    $stmt = $pdo->prepare(os_base_select() . " WHERE os.id = :id AND os.residencial_id = :rid LIMIT 1");
    $stmt->execute(['id' => $orderId, 'rid' => $residencialId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function os_write_event(PDO $pdo, int $residencialId, ?int $adminId, array $order, string $event, string $result = 'permitido', string $notes = ''): void
{
    operational_write_bitacora($pdo, [
        'residencial_id' => $residencialId,
        'guardia_id' => $adminId,
        'tipo_origen' => 'orden_servicio',
        'origen_id' => (int)$order['id'],
        'tipo_evento' => $event,
        'resultado' => $result,
        'persona_recurrente_id' => ($order['persona_recurrente_id'] ?? null) !== null ? (int)$order['persona_recurrente_id'] : null,
        'area_id' => ($order['area_id'] ?? null) !== null ? (int)$order['area_id'] : null,
        'observaciones' => $notes,
        'metadata_json' => [
            'folio' => (string)($order['folio'] ?? ''),
            'proveedor_id' => ($order['proveedor_id'] ?? null) !== null ? (int)$order['proveedor_id'] : null,
            'proveedor_nombre' => (string)($order['proveedor_nombre'] ?? ''),
            'persona_recurrente_id' => ($order['persona_recurrente_id'] ?? null) !== null ? (int)$order['persona_recurrente_id'] : null,
            'area_id' => ($order['area_id'] ?? null) !== null ? (int)$order['area_id'] : null,
            'qr_token' => (string)($order['qr_token'] ?? ''),
        ],
    ]);
}

function os_meta(PDO $pdo, int $residencialId): array
{
    $providers = [];
    if (operational_table_exists($pdo, 'proveedores')) {
        $stmt = $pdo->prepare("
            SELECT id, nombre_comercial, tipo_servicio, estatus, estatus_cumplimiento, motivo_bloqueo, activo
            FROM proveedores
            WHERE residencial_id = :rid
            ORDER BY nombre_comercial ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $providers = array_map(static function (array $row): array {
            $status = compliance_normalize_status((string)($row['estatus_cumplimiento'] ?? 'autorizado'));
            return [
                'id' => (int)$row['id'],
                'nombre' => (string)$row['nombre_comercial'],
                'tipo_servicio' => (string)($row['tipo_servicio'] ?? ''),
                'estatus' => (string)($row['estatus'] ?? 'activo'),
                'activo' => (int)($row['activo'] ?? 1),
                'cumplimiento_estado' => $status,
                'cumplimiento_label' => compliance_label($status),
                'motivo_bloqueo' => (string)($row['motivo_bloqueo'] ?? ''),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    $stmt = $pdo->prepare("
        SELECT pr.id, pr.nombre, pr.empresa, pr.puesto, pr.area_id, a.nombre AS area_nombre
        FROM personas_recurrentes pr
        LEFT JOIN areas_operativas a ON a.id = pr.area_id AND a.residencial_id = pr.residencial_id
        WHERE pr.residencial_id = :rid AND COALESCE(pr.activo, 1) = 1
        ORDER BY pr.nombre ASC
    ");
    $stmt->execute(['rid' => $residencialId]);
    $people = array_map(function (array $row) use ($pdo, $residencialId): array {
        $compliance = compliance_effective_for_person($pdo, $residencialId, (int)$row['id'], $row);
        return [
            'id' => (int)$row['id'],
            'nombre' => (string)$row['nombre'],
            'empresa' => (string)($row['empresa'] ?? ''),
            'puesto' => (string)($row['puesto'] ?? ''),
            'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
            'area_nombre' => (string)($row['area_nombre'] ?? ''),
            'cumplimiento' => $compliance,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $stmt = $pdo->prepare("SELECT id, nombre, codigo FROM areas_operativas WHERE residencial_id = :rid AND COALESCE(activo, 1) = 1 ORDER BY nombre ASC");
    $stmt->execute(['rid' => $residencialId]);
    $areas = array_map(static fn(array $row): array => [
        'id' => (int)$row['id'],
        'nombre' => (string)$row['nombre'],
        'codigo' => (string)($row['codigo'] ?? ''),
    ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    return [
        'context' => admin_operational_context(),
        'statuses' => os_statuses(),
        'priorities' => os_priorities(),
        'evidence_types' => os_evidence_types(),
        'proveedores' => $providers,
        'personas' => $people,
        'areas' => $areas,
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = clean_str((string)($_GET['action'] ?? $_POST['action'] ?? 'list'));

try {
    if ($method === 'GET' && $action === 'meta') {
        json_out(true, ['data' => os_meta($pdo, $residencialId)]);
    }

    if ($method === 'GET' && $action === 'list') {
        $where = ['os.residencial_id = :rid'];
        $params = ['rid' => $residencialId];

        $estatus = os_status((string)($_GET['estatus'] ?? ''), '');
        if ($estatus !== '') {
            $where[] = 'os.estatus = :estatus';
            $params['estatus'] = $estatus;
        }
        $proveedorId = (int)($_GET['proveedor_id'] ?? 0);
        if ($proveedorId > 0) {
            $where[] = 'os.proveedor_id = :proveedor_id';
            $params['proveedor_id'] = $proveedorId;
        }
        $areaId = (int)($_GET['area_id'] ?? 0);
        if ($areaId > 0) {
            $where[] = 'os.area_id = :area_id';
            $params['area_id'] = $areaId;
        }
        $fecha = os_date_or_null($_GET['fecha'] ?? null);
        if ($fecha) {
            $where[] = 'os.fecha_programada = :fecha';
            $params['fecha'] = $fecha;
        }
        $q = clean_str((string)($_GET['q'] ?? ''));
        if ($q !== '') {
            $where[] = "(os.folio LIKE :q OR os.tipo_servicio LIKE :q OR os.descripcion LIKE :q OR p.nombre_comercial LIKE :q OR pr.nombre LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $stmt = $pdo->prepare(os_base_select() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY os.fecha_programada DESC, os.id DESC LIMIT 200');
        $stmt->execute($params);
        $items = array_map(fn(array $row): array => os_normalize_row($pdo, $residencialId, $row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $summary = array_fill_keys(os_statuses(), 0);
        $sumStmt = $pdo->prepare("SELECT estatus, COUNT(*) AS total FROM ordenes_servicio WHERE residencial_id = :rid GROUP BY estatus");
        $sumStmt->execute(['rid' => $residencialId]);
        foreach ($sumStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $summary[os_status((string)$row['estatus'])] = (int)$row['total'];
        }

        json_out(true, ['data' => ['items' => $items, 'summary' => $summary, 'context' => admin_operational_context()]]);
    }

    if ($method === 'GET' && $action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        $row = os_fetch_order($pdo, $residencialId, $id);
        if (!$row) {
            json_out(false, ['error' => 'Orden no encontrada.'], 404);
        }
        $eStmt = $pdo->prepare("
            SELECT e.*, u.name AS created_by_nombre
            FROM ordenes_servicio_evidencias e
            LEFT JOIN users u ON u.id = e.created_by
            WHERE e.orden_id = :id
            ORDER BY e.created_at DESC, e.id DESC
        ");
        $eStmt->execute(['id' => $id]);
        $bStmt = $pdo->prepare("
            SELECT b.id, b.fecha_hora, b.tipo_evento, b.resultado, b.observaciones, u.name AS operador_nombre
            FROM bitacora_operativa b
            LEFT JOIN users u ON u.id = b.guardia_id
            WHERE b.residencial_id = :rid
              AND b.tipo_origen = 'orden_servicio'
              AND b.origen_id = :id
            ORDER BY b.fecha_hora DESC, b.id DESC
            LIMIT 50
        ");
        $bStmt->execute(['rid' => $residencialId, 'id' => $id]);
        json_out(true, ['data' => [
            'orden' => os_normalize_row($pdo, $residencialId, $row),
            'evidencias' => $eStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'eventos' => $bStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ]]);
    }

    if ($method === 'POST' && $action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $providerId = (int)($_POST['proveedor_id'] ?? 0) ?: null;
        $personId = (int)($_POST['persona_recurrente_id'] ?? 0) ?: null;
        $areaId = (int)($_POST['area_id'] ?? 0) ?: null;
        $fecha = os_date_or_null($_POST['fecha_programada'] ?? null);
        $tipoServicio = clean_str((string)($_POST['tipo_servicio'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $horaInicio = os_time_or_null($_POST['hora_inicio'] ?? null);
        $horaFin = os_time_or_null($_POST['hora_fin'] ?? null);
        $prioridad = os_priority((string)($_POST['prioridad'] ?? 'media'));
        $estatus = os_status((string)($_POST['estatus'] ?? 'programada'));

        if (!$fecha || $tipoServicio === '') {
            json_out(false, ['error' => 'Captura tipo de servicio y fecha programada.'], 422);
        }
        if ($providerId && !($provider = os_provider($pdo, $residencialId, $providerId))) {
            json_out(false, ['error' => 'Proveedor inválido para este cliente.'], 422);
        }
        $provider = $providerId ? os_provider($pdo, $residencialId, $providerId) : null;
        if ($personId && !($person = os_person($pdo, $residencialId, $personId))) {
            json_out(false, ['error' => 'Persona autorizada inválida para este cliente.'], 422);
        }
        $person = $personId ? os_person($pdo, $residencialId, $personId) : null;
        if ($areaId && !os_area($pdo, $residencialId, $areaId)) {
            json_out(false, ['error' => 'Área inválida para este cliente.'], 422);
        }

        $compliance = os_effective_compliance($pdo, $residencialId, $provider, $person);
        $effectiveStatus = compliance_normalize_status((string)($compliance['status'] ?? $compliance['cumplimiento_estado'] ?? 'autorizado'));
        if (compliance_is_blocking($effectiveStatus)) {
            json_out(false, ['error' => 'No se puede crear o actualizar la orden: proveedor o persona bloqueada por cumplimiento.'], 422);
        }

        $pdo->beginTransaction();
        if ($id > 0) {
            $current = os_fetch_order($pdo, $residencialId, $id);
            if (!$current) {
                $pdo->rollBack();
                json_out(false, ['error' => 'Orden no encontrada.'], 404);
            }
            $stmt = $pdo->prepare("
                UPDATE ordenes_servicio
                SET proveedor_id = :proveedor_id,
                    persona_recurrente_id = :persona_id,
                    area_id = :area_id,
                    tipo_servicio = :tipo_servicio,
                    descripcion = :descripcion,
                    fecha_programada = :fecha,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin,
                    estatus = :estatus,
                    prioridad = :prioridad,
                    updated_at = NOW()
                WHERE id = :id AND residencial_id = :rid
            ");
            $stmt->execute([
                'proveedor_id' => $providerId,
                'persona_id' => $personId,
                'area_id' => $areaId,
                'tipo_servicio' => $tipoServicio,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'estatus' => $estatus,
                'prioridad' => $prioridad,
                'id' => $id,
                'rid' => $residencialId,
            ]);
        } else {
            $folio = clean_str((string)($_POST['folio'] ?? '')) ?: os_new_folio($pdo, $residencialId);
            $qrToken = os_new_token($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO ordenes_servicio (
                    residencial_id, proveedor_id, persona_recurrente_id, area_id, folio, tipo_servicio,
                    descripcion, fecha_programada, hora_inicio, hora_fin, qr_token, estatus, prioridad, creado_por
                ) VALUES (
                    :rid, :proveedor_id, :persona_id, :area_id, :folio, :tipo_servicio,
                    :descripcion, :fecha, :hora_inicio, :hora_fin, :qr_token, :estatus, :prioridad, :creado_por
                )
            ");
            $stmt->execute([
                'rid' => $residencialId,
                'proveedor_id' => $providerId,
                'persona_id' => $personId,
                'area_id' => $areaId,
                'folio' => $folio,
                'tipo_servicio' => $tipoServicio,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'fecha' => $fecha,
                'hora_inicio' => $horaInicio,
                'hora_fin' => $horaFin,
                'qr_token' => $qrToken,
                'estatus' => $estatus,
                'prioridad' => $prioridad,
                'creado_por' => $adminId ?: null,
            ]);
            $id = (int)$pdo->lastInsertId();
            $created = os_fetch_order($pdo, $residencialId, $id);
            if ($created) {
                os_write_event($pdo, $residencialId, $adminId ?: null, $created, 'orden_servicio_creada', 'permitido', 'Orden de servicio creada.');
            }
        }
        $pdo->commit();

        $row = os_fetch_order($pdo, $residencialId, $id);
        json_out(true, ['message' => 'Orden guardada correctamente.', 'data' => ['orden' => os_normalize_row($pdo, $residencialId, $row ?: [])]]);
    }

    if ($method === 'POST' && in_array($action, ['cancel', 'close', 'regenerate_qr', 'save_evidence'], true)) {
        $id = (int)($_POST['id'] ?? $_POST['orden_id'] ?? 0);
        $row = os_fetch_order($pdo, $residencialId, $id);
        if (!$row) {
            json_out(false, ['error' => 'Orden no encontrada.'], 404);
        }

        $pdo->beginTransaction();
        if ($action === 'cancel') {
            $notes = trim((string)($_POST['motivo'] ?? 'Orden cancelada.'));
            $stmt = $pdo->prepare("UPDATE ordenes_servicio SET estatus='cancelada', activo=0, updated_at=NOW() WHERE id=:id AND residencial_id=:rid");
            $stmt->execute(['id' => $id, 'rid' => $residencialId]);
            $row['estatus'] = 'cancelada';
            os_write_event($pdo, $residencialId, $adminId ?: null, $row, 'orden_servicio_cancelada', 'cancelado', $notes);
            $message = 'Orden cancelada.';
        } elseif ($action === 'close') {
            $notes = trim((string)($_POST['observaciones_cierre'] ?? ''));
            $stmt = $pdo->prepare("
                UPDATE ordenes_servicio
                SET estatus='cerrada',
                    cerrado_por=:admin_id,
                    cierre_at=NOW(),
                    observaciones_cierre=:notes,
                    updated_at=NOW()
                WHERE id=:id AND residencial_id=:rid
            ");
            $stmt->execute(['admin_id' => $adminId ?: null, 'notes' => $notes !== '' ? $notes : null, 'id' => $id, 'rid' => $residencialId]);
            $row['estatus'] = 'cerrada';
            os_write_event($pdo, $residencialId, $adminId ?: null, $row, 'orden_servicio_cerrada', 'cerrado', $notes);
            $message = 'Orden cerrada.';
        } elseif ($action === 'regenerate_qr') {
            $token = os_new_token($pdo);
            $stmt = $pdo->prepare("UPDATE ordenes_servicio SET qr_token=:token, updated_at=NOW() WHERE id=:id AND residencial_id=:rid");
            $stmt->execute(['token' => $token, 'id' => $id, 'rid' => $residencialId]);
            $message = 'QR regenerado.';
        } else {
            $type = clean_str((string)($_POST['tipo'] ?? 'general'));
            if (!in_array($type, os_evidence_types(), true)) {
                $type = 'general';
            }
            $url = trim((string)($_POST['archivo_url'] ?? ''));
            $description = trim((string)($_POST['descripcion'] ?? ''));
            if ($url === '' && $description === '') {
                $pdo->rollBack();
                json_out(false, ['error' => 'Captura una URL o descripción para la evidencia.'], 422);
            }
            $stmt = $pdo->prepare("
                INSERT INTO ordenes_servicio_evidencias (orden_id, tipo, archivo_url, descripcion, created_by)
                VALUES (:orden_id, :tipo, :url, :descripcion, :created_by)
            ");
            $stmt->execute([
                'orden_id' => $id,
                'tipo' => $type,
                'url' => $url !== '' ? $url : null,
                'descripcion' => $description !== '' ? $description : null,
                'created_by' => $adminId ?: null,
            ]);
            $message = 'Evidencia guardada.';
        }
        $pdo->commit();
        json_out(true, ['message' => $message, 'data' => ['orden' => os_normalize_row($pdo, $residencialId, os_fetch_order($pdo, $residencialId, $id) ?: $row)]]);
    }

    json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar las órdenes de servicio.');
}
