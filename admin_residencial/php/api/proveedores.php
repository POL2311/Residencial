<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
require_once __DIR__ . '/../../../config/compliance_helpers.php';
admin_module_required('proveedores', 'El módulo de proveedores no está habilitado para este cliente.');
admin_operational_required('El módulo de proveedores está disponible para modos operativos.');

function proveedores_statuses(): array
{
    return ['activo', 'pendiente', 'bloqueado'];
}

function proveedores_document_statuses(): array
{
    return ['vigente', 'vencido', 'pendiente'];
}

function proveedores_status(string $value, string $fallback = 'activo'): string
{
    $value = clean_str($value);
    return in_array($value, proveedores_statuses(), true) ? $value : $fallback;
}

function proveedores_document_status(string $value, string $fallback = 'pendiente'): string
{
    $value = clean_str($value);
    return in_array($value, proveedores_document_statuses(), true) ? $value : $fallback;
}

function proveedores_compliance_status(string $value, string $fallback = 'autorizado'): string
{
    $value = clean_str($value);
    return in_array($value, compliance_statuses(), true) ? $value : $fallback;
}

function proveedores_date_or_null(mixed $value): ?string
{
    $value = trim((string)($value ?? ''));
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function proveedores_provider(PDO $pdo, int $residencialId, int $providerId): ?array
{
    if ($providerId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM proveedores WHERE id = :id AND residencial_id = :rid LIMIT 1");
    $stmt->execute(['id' => $providerId, 'rid' => $residencialId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function proveedores_person_belongs(PDO $pdo, int $residencialId, int $personId): bool
{
    if ($personId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM personas_recurrentes WHERE id = :id AND residencial_id = :rid LIMIT 1");
    $stmt->execute(['id' => $personId, 'rid' => $residencialId]);
    return (bool)$stmt->fetchColumn();
}

function proveedores_normalize(array $row): array
{
    $row['id'] = (int)$row['id'];
    $row['residencial_id'] = (int)$row['residencial_id'];
    $row['activo'] = (int)($row['activo'] ?? 0);
    $row['personas_count'] = (int)($row['personas_count'] ?? 0);
    $row['documentos_count'] = (int)($row['documentos_count'] ?? 0);
    $row['eventos_count'] = (int)($row['eventos_count'] ?? 0);
    $row['estatus_cumplimiento'] = compliance_normalize_status((string)($row['estatus_cumplimiento'] ?? 'autorizado'));
    $row['cumplimiento_label'] = compliance_label($row['estatus_cumplimiento']);
    $row['motivo_bloqueo'] = (string)($row['motivo_bloqueo'] ?? '');
    $row['puede_ingresar'] = !compliance_is_blocking($row['estatus_cumplimiento']);
    $row['requiere_confirmacion'] = compliance_requires_confirmation($row['estatus_cumplimiento']);
    return $row;
}

function proveedores_personal_options(PDO $pdo, int $residencialId): array
{
    $stmt = $pdo->prepare("
        SELECT pr.id, pr.nombre, pr.empresa, pr.puesto, pr.area_id, a.nombre AS area_nombre
        FROM personas_recurrentes pr
        LEFT JOIN areas_operativas a ON a.id = pr.area_id AND a.residencial_id = pr.residencial_id
        WHERE pr.residencial_id = :rid
          AND COALESCE(pr.activo, 1) = 1
        ORDER BY pr.nombre ASC, pr.empresa ASC
    ");
    $stmt->execute(['rid' => $residencialId]);
    return array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'nombre' => (string)($row['nombre'] ?? ''),
            'empresa' => (string)($row['empresa'] ?? ''),
            'puesto' => (string)($row['puesto'] ?? ''),
            'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
            'area_nombre' => (string)($row['area_nombre'] ?? ''),
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function proveedores_list(PDO $pdo, int $residencialId, array $input = []): array
{
    $where = ['p.residencial_id = :rid'];
    $params = ['rid' => $residencialId];

    $q = clean_str((string)($input['q'] ?? ''));
    if ($q !== '') {
        $where[] = "(p.nombre_comercial LIKE :q OR p.razon_social LIKE :q OR p.tipo_servicio LIKE :q OR p.contacto_nombre LIKE :q)";
        $params['q'] = '%' . $q . '%';
    }

    $estatus = clean_str((string)($input['estatus'] ?? ''));
    if (in_array($estatus, proveedores_statuses(), true)) {
        $where[] = 'p.estatus = :estatus';
        $params['estatus'] = $estatus;
    }

    $activo = trim((string)($input['activo'] ?? ''));
    if ($activo === '0' || $activo === '1') {
        $where[] = 'p.activo = :activo';
        $params['activo'] = (int)$activo;
    }

    $eventSelect = '0 AS eventos_count, NULL AS ultimo_evento_at';
    if (tableExists($pdo, 'bitacora_operativa')) {
        $eventSelect = "
            (
                SELECT COUNT(*)
                FROM bitacora_operativa b
                JOIN proveedor_personas pp2 ON pp2.persona_recurrente_id = b.persona_recurrente_id
                WHERE pp2.proveedor_id = p.id
                  AND pp2.activo = 1
                  AND b.residencial_id = p.residencial_id
            ) AS eventos_count,
            (
                SELECT MAX(b.fecha_hora)
                FROM bitacora_operativa b
                JOIN proveedor_personas pp3 ON pp3.persona_recurrente_id = b.persona_recurrente_id
                WHERE pp3.proveedor_id = p.id
                  AND pp3.activo = 1
                  AND b.residencial_id = p.residencial_id
            ) AS ultimo_evento_at
        ";
    }

    $sql = "
        SELECT
            p.*,
            (SELECT COUNT(*) FROM proveedor_personas pp WHERE pp.proveedor_id = p.id AND pp.activo = 1) AS personas_count,
            (SELECT COUNT(*) FROM proveedor_documentos pd WHERE pd.proveedor_id = p.id) AS documentos_count,
            $eventSelect
        FROM proveedores p
        WHERE " . implode(' AND ', $where) . "
        ORDER BY p.activo DESC, FIELD(p.estatus, 'bloqueado', 'pendiente', 'activo') ASC, p.nombre_comercial ASC
        LIMIT 200
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('proveedores_normalize', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function proveedores_associated_people(PDO $pdo, int $residencialId, int $providerId): array
{
    $stmt = $pdo->prepare("
        SELECT
            pp.id AS asociacion_id,
            pp.rol,
            pp.activo AS asociacion_activa,
            pp.created_at AS asociado_at,
            pr.id,
            pr.nombre,
            pr.empresa,
            pr.puesto,
            pr.telefono,
            pr.estatus_cumplimiento,
            pr.motivo_bloqueo,
            pr.area_id,
            a.nombre AS area_nombre
        FROM proveedor_personas pp
        JOIN proveedores p ON p.id = pp.proveedor_id
        JOIN personas_recurrentes pr ON pr.id = pp.persona_recurrente_id
        LEFT JOIN areas_operativas a ON a.id = pr.area_id AND a.residencial_id = pr.residencial_id
        WHERE pp.proveedor_id = :pid
          AND p.residencial_id = :rid
        ORDER BY pp.activo DESC, pr.nombre ASC
    ");
    $stmt->execute(['pid' => $providerId, 'rid' => $residencialId]);
    return array_map(function (array $row) use ($pdo, $residencialId): array {
        $row['asociacion_id'] = (int)$row['asociacion_id'];
        $row['asociacion_activa'] = (int)$row['asociacion_activa'];
        $row['id'] = (int)$row['id'];
        $row['area_id'] = $row['area_id'] !== null ? (int)$row['area_id'] : null;
        $row['estatus_cumplimiento'] = compliance_normalize_status((string)($row['estatus_cumplimiento'] ?? 'autorizado'));
        $row['cumplimiento_label'] = compliance_label($row['estatus_cumplimiento']);
        $row['motivo_bloqueo'] = (string)($row['motivo_bloqueo'] ?? '');
        $row['cumplimiento_efectivo'] = compliance_effective_for_person($pdo, $residencialId, (int)$row['id'], $row);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function proveedores_documents(PDO $pdo, int $residencialId, int $providerId): array
{
    $stmt = $pdo->prepare("
        SELECT pd.*
        FROM proveedor_documentos pd
        JOIN proveedores p ON p.id = pd.proveedor_id
        WHERE pd.proveedor_id = :pid
          AND p.residencial_id = :rid
        ORDER BY pd.fecha_vencimiento IS NULL ASC, pd.fecha_vencimiento ASC, pd.created_at DESC
    ");
    $stmt->execute(['pid' => $providerId, 'rid' => $residencialId]);
    return array_map(static function (array $row): array {
        $row['id'] = (int)$row['id'];
        $row['proveedor_id'] = (int)$row['proveedor_id'];
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function proveedores_recent_events(PDO $pdo, int $residencialId, int $providerId): array
{
    if (!tableExists($pdo, 'bitacora_operativa')) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT b.id, b.fecha_hora, b.tipo_origen, b.tipo_evento, b.resultado, b.observaciones,
               pr.nombre AS persona_nombre, a.nombre AS area_nombre, u.name AS operador_nombre
        FROM bitacora_operativa b
        JOIN proveedor_personas pp ON pp.persona_recurrente_id = b.persona_recurrente_id AND pp.activo = 1
        LEFT JOIN personas_recurrentes pr ON pr.id = b.persona_recurrente_id
        LEFT JOIN areas_operativas a ON a.id = b.area_id AND a.residencial_id = b.residencial_id
        LEFT JOIN users u ON u.id = b.guardia_id
        WHERE pp.proveedor_id = :pid
          AND b.residencial_id = :rid
        ORDER BY b.fecha_hora DESC, b.id DESC
        LIMIT 20
    ");
    $stmt->execute(['pid' => $providerId, 'rid' => $residencialId]);
    return array_map(static function (array $row): array {
        $row['id'] = (int)$row['id'];
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function proveedores_detail(PDO $pdo, int $residencialId, int $providerId): array
{
    $provider = proveedores_provider($pdo, $residencialId, $providerId);
    if (!$provider) {
        json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
    }

    return [
        'proveedor' => proveedores_normalize($provider + [
            'personas_count' => 0,
            'documentos_count' => 0,
            'eventos_count' => 0,
        ]),
        'personas' => proveedores_associated_people($pdo, $residencialId, $providerId),
        'documentos' => proveedores_documents($pdo, $residencialId, $providerId),
        'eventos' => proveedores_recent_events($pdo, $residencialId, $providerId),
    ];
}

try {
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = clean_str((string)(($method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? 'list'))));

    if ($method === 'GET' && $action === 'meta') {
        json_out(true, [
            'data' => [
                'estatus' => proveedores_statuses(),
                'document_estatus' => proveedores_document_statuses(),
                'cumplimiento_estatus' => compliance_statuses(),
                'cumplimiento_labels' => compliance_labels(),
                'personal' => proveedores_personal_options($pdo, $residencialId),
                'context' => admin_operational_context(),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'list') {
        json_out(true, [
            'data' => [
                'proveedores' => proveedores_list($pdo, $residencialId, $_GET),
                'context' => admin_operational_context(),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'detail') {
        $id = (int)($_GET['id'] ?? 0);
        json_out(true, ['data' => proveedores_detail($pdo, $residencialId, $id)]);
    }

    if ($method !== 'POST') {
        json_out(false, ['error' => 'Acción no válida.'], 400);
    }

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0 && !proveedores_provider($pdo, $residencialId, $id)) {
            json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
        }

        $nombreComercial = clean_str((string)($_POST['nombre_comercial'] ?? ''));
        if ($nombreComercial === '') {
            json_out(false, ['error' => 'El nombre comercial es obligatorio.'], 422);
        }

        $params = [
            'rid' => $residencialId,
            'nombre_comercial' => $nombreComercial,
            'razon_social' => clean_str((string)($_POST['razon_social'] ?? '')),
            'tipo_servicio' => clean_str((string)($_POST['tipo_servicio'] ?? '')),
            'contacto_nombre' => clean_str((string)($_POST['contacto_nombre'] ?? '')),
            'contacto_telefono' => clean_str((string)($_POST['contacto_telefono'] ?? '')),
            'contacto_email' => clean_str((string)($_POST['contacto_email'] ?? '')),
            'estatus' => proveedores_status((string)($_POST['estatus'] ?? 'activo')),
            'notas' => clean_str((string)($_POST['notas'] ?? '')),
            'activo' => operational_bool_int($_POST['activo'] ?? 1),
        ];

        if ($id > 0) {
            $params['id'] = $id;
            $stmt = $pdo->prepare("
                UPDATE proveedores
                SET nombre_comercial = :nombre_comercial,
                    razon_social = :razon_social,
                    tipo_servicio = :tipo_servicio,
                    contacto_nombre = :contacto_nombre,
                    contacto_telefono = :contacto_telefono,
                    contacto_email = :contacto_email,
                    estatus = :estatus,
                    notas = :notas,
                    activo = :activo,
                    updated_at = NOW()
                WHERE id = :id AND residencial_id = :rid
                LIMIT 1
            ");
            $stmt->execute($params);
            json_out(true, ['message' => 'Proveedor actualizado correctamente.', 'data' => ['id' => $id]]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO proveedores (
                residencial_id, nombre_comercial, razon_social, tipo_servicio,
                contacto_nombre, contacto_telefono, contacto_email, estatus,
                notas, activo, created_at, updated_at
            ) VALUES (
                :rid, :nombre_comercial, :razon_social, :tipo_servicio,
                :contacto_nombre, :contacto_telefono, :contacto_email, :estatus,
                :notas, :activo, NOW(), NOW()
            )
        ");
        $stmt->execute($params);
        json_out(true, ['message' => 'Proveedor creado correctamente.', 'data' => ['id' => (int)$pdo->lastInsertId()]]);
    }

    if ($action === 'set_status') {
        $id = (int)($_POST['id'] ?? 0);
        if (!proveedores_provider($pdo, $residencialId, $id)) {
            json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
        }
        $estatus = proveedores_status((string)($_POST['estatus'] ?? ''), '');
        if ($estatus === '') {
            json_out(false, ['error' => 'Estatus inválido.'], 422);
        }
        $stmt = $pdo->prepare("UPDATE proveedores SET estatus = :estatus, updated_at = NOW() WHERE id = :id AND residencial_id = :rid LIMIT 1");
        $stmt->execute(['estatus' => $estatus, 'id' => $id, 'rid' => $residencialId]);
        json_out(true, ['message' => 'Estatus actualizado correctamente.']);
    }

    if ($action === 'set_compliance') {
        $id = (int)($_POST['id'] ?? 0);
        if (!proveedores_provider($pdo, $residencialId, $id)) {
            json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
        }
        $estatus = proveedores_compliance_status((string)($_POST['estatus_cumplimiento'] ?? ''), '');
        $motivo = clean_str((string)($_POST['motivo_bloqueo'] ?? ''));
        if ($estatus === '') {
            json_out(false, ['error' => 'Estado de cumplimiento inválido.'], 422);
        }
        if ($estatus === 'bloqueado' && $motivo === '') {
            json_out(false, ['error' => 'Indica el motivo del bloqueo.'], 422);
        }
        $stmt = $pdo->prepare("
            UPDATE proveedores
            SET estatus_cumplimiento = :estatus,
                motivo_bloqueo = :motivo,
                cumplimiento_actualizado_at = NOW(),
                cumplimiento_actualizado_por = :admin_id,
                updated_at = NOW()
            WHERE id = :id AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'estatus' => $estatus,
            'motivo' => $estatus === 'autorizado' ? null : ($motivo !== '' ? $motivo : null),
            'admin_id' => $adminId > 0 ? $adminId : null,
            'id' => $id,
            'rid' => $residencialId,
        ]);
        json_out(true, ['message' => 'Semáforo actualizado correctamente.']);
    }

    if ($action === 'toggle_active') {
        $id = (int)($_POST['id'] ?? 0);
        if (!proveedores_provider($pdo, $residencialId, $id)) {
            json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
        }
        $activo = operational_bool_int($_POST['activo'] ?? 0);
        $stmt = $pdo->prepare("UPDATE proveedores SET activo = :activo, updated_at = NOW() WHERE id = :id AND residencial_id = :rid LIMIT 1");
        $stmt->execute(['activo' => $activo, 'id' => $id, 'rid' => $residencialId]);
        json_out(true, ['message' => $activo ? 'Proveedor activado.' : 'Proveedor desactivado.']);
    }

    if ($action === 'associate_person') {
        $providerId = (int)($_POST['proveedor_id'] ?? 0);
        $personId = (int)($_POST['persona_recurrente_id'] ?? 0);
        if (!proveedores_provider($pdo, $residencialId, $providerId)) {
            json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
        }
        if (!proveedores_person_belongs($pdo, $residencialId, $personId)) {
            json_out(false, ['error' => 'La persona seleccionada no pertenece a este servicio.'], 422);
        }
        $rol = clean_str((string)($_POST['rol'] ?? ''));

        $stmt = $pdo->prepare("SELECT id FROM proveedor_personas WHERE proveedor_id = :pid AND persona_recurrente_id = :person_id LIMIT 1");
        $stmt->execute(['pid' => $providerId, 'person_id' => $personId]);
        $assocId = (int)($stmt->fetchColumn() ?: 0);
        if ($assocId > 0) {
            $stmt = $pdo->prepare("UPDATE proveedor_personas SET rol = :rol, activo = 1 WHERE id = :id LIMIT 1");
            $stmt->execute(['rol' => $rol, 'id' => $assocId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO proveedor_personas (proveedor_id, persona_recurrente_id, rol, activo, created_at) VALUES (:pid, :person_id, :rol, 1, NOW())");
            $stmt->execute(['pid' => $providerId, 'person_id' => $personId, 'rol' => $rol]);
        }
        json_out(true, ['message' => 'Personal asociado correctamente.']);
    }

    if ($action === 'remove_person') {
        $providerId = (int)($_POST['proveedor_id'] ?? 0);
        $personId = (int)($_POST['persona_recurrente_id'] ?? 0);
        if (!proveedores_provider($pdo, $residencialId, $providerId)) {
            json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
        }
        $stmt = $pdo->prepare("
            UPDATE proveedor_personas pp
            JOIN proveedores p ON p.id = pp.proveedor_id
            SET pp.activo = 0
            WHERE pp.proveedor_id = :pid
              AND pp.persona_recurrente_id = :person_id
              AND p.residencial_id = :rid
        ");
        $stmt->execute(['pid' => $providerId, 'person_id' => $personId, 'rid' => $residencialId]);
        json_out(true, ['message' => 'Asociación desactivada correctamente.']);
    }

    if ($action === 'save_document') {
        $id = (int)($_POST['id'] ?? 0);
        $providerId = (int)($_POST['proveedor_id'] ?? 0);
        if (!proveedores_provider($pdo, $residencialId, $providerId)) {
            json_out(false, ['error' => 'Proveedor no encontrado.'], 404);
        }

        $tipo = clean_str((string)($_POST['tipo_documento'] ?? ''));
        if ($tipo === '') {
            json_out(false, ['error' => 'El tipo de documento es obligatorio.'], 422);
        }

        $params = [
            'proveedor_id' => $providerId,
            'tipo_documento' => $tipo,
            'archivo_url' => clean_str((string)($_POST['archivo_url'] ?? '')),
            'fecha_vencimiento' => proveedores_date_or_null($_POST['fecha_vencimiento'] ?? null),
            'estatus' => proveedores_document_status((string)($_POST['estatus'] ?? 'pendiente')),
        ];

        if ($id > 0) {
            $stmt = $pdo->prepare("
                SELECT 1
                FROM proveedor_documentos pd
                JOIN proveedores p ON p.id = pd.proveedor_id
                WHERE pd.id = :id
                  AND pd.proveedor_id = :pid
                  AND p.residencial_id = :rid
                LIMIT 1
            ");
            $stmt->execute(['id' => $id, 'pid' => $providerId, 'rid' => $residencialId]);
            if (!$stmt->fetchColumn()) {
                json_out(false, ['error' => 'Documento no encontrado.'], 404);
            }
            $params['id'] = $id;
            $stmt = $pdo->prepare("
                UPDATE proveedor_documentos
                SET tipo_documento = :tipo_documento,
                    archivo_url = :archivo_url,
                    fecha_vencimiento = :fecha_vencimiento,
                    estatus = :estatus,
                    updated_at = NOW()
                WHERE id = :id AND proveedor_id = :proveedor_id
                LIMIT 1
            ");
            $stmt->execute($params);
            json_out(true, ['message' => 'Documento actualizado correctamente.']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO proveedor_documentos (
                proveedor_id, tipo_documento, archivo_url, fecha_vencimiento, estatus, created_at, updated_at
            ) VALUES (
                :proveedor_id, :tipo_documento, :archivo_url, :fecha_vencimiento, :estatus, NOW(), NOW()
            )
        ");
        $stmt->execute($params);
        json_out(true, ['message' => 'Documento registrado correctamente.']);
    }

    json_out(false, ['error' => 'Acción no válida.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar proveedores.');
}
