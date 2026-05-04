<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../config/operational_mode.php';

$action = sa_post_action('list');

function sa_incidencia_normalize(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'residencial_id' => (int)($row['residencial_id'] ?? 0),
        'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
        'residencial_codigo' => (string)($row['residencial_codigo'] ?? ''),
        'modo_operacion' => (string)($row['modo_operacion'] ?? 'residencial'),
        'unidad_id' => isset($row['unidad_id']) && $row['unidad_id'] !== null ? (int)$row['unidad_id'] : null,
        'unidad_clave' => (string)($row['unidad_clave'] ?? ''),
        'residente_id' => isset($row['residente_id']) && $row['residente_id'] !== null ? (int)$row['residente_id'] : null,
        'residente_nombre' => (string)($row['residente_nombre'] ?? ''),
        'guardia_id' => isset($row['guardia_id']) && $row['guardia_id'] !== null ? (int)$row['guardia_id'] : null,
        'guardia_nombre' => (string)($row['guardia_nombre'] ?? ''),
        'area_id' => isset($row['area_id']) && $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area_nombre' => (string)($row['area_nombre'] ?? ''),
        'persona_recurrente_id' => isset($row['persona_recurrente_id']) && $row['persona_recurrente_id'] !== null ? (int)$row['persona_recurrente_id'] : null,
        'persona_recurrente_nombre' => (string)($row['persona_recurrente_nombre'] ?? ''),
        'visitante_rapido_id' => isset($row['visitante_rapido_id']) && $row['visitante_rapido_id'] !== null ? (int)$row['visitante_rapido_id'] : null,
        'visitante_rapido_nombre' => (string)($row['visitante_rapido_nombre'] ?? ''),
        'permiso_material_id' => isset($row['permiso_material_id']) && $row['permiso_material_id'] !== null ? (int)$row['permiso_material_id'] : null,
        'origen_tipo' => (string)($row['origen_tipo'] ?? ''),
        'tipo' => (string)($row['tipo'] ?? ''),
        'titulo' => (string)($row['titulo'] ?? ''),
        'descripcion' => (string)($row['descripcion'] ?? ''),
        'prioridad' => (string)($row['prioridad'] ?? 'media'),
        'estado' => (string)($row['estado'] ?? 'abierta'),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function sa_incidencia_meta(PDO $pdo, int $residencialId): array
{
    $mode = operational_get_mode($pdo, $residencialId);
    $isOperational = operational_is_operational_mode($mode);

    $meta = [
        'modo_operacion' => $mode,
        'is_operational' => $isOperational,
        'unidades' => [],
        'guardias' => [],
        'areas' => [],
        'personas' => [],
        'visitantes' => [],
        'permisos' => [],
    ];

    $stmt = $pdo->prepare("
        SELECT id, clave
        FROM unidades
        WHERE residencial_id = :rid
        ORDER BY clave ASC
    ");
    $stmt->execute(['rid' => $residencialId]);
    $meta['unidades'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("
        SELECT u.id, u.name
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        JOIN usuarios_residenciales ur ON ur.user_id = u.id
        WHERE ur.residencial_id = :rid
          AND t.nombre = 'guardia'
        ORDER BY u.name ASC
    ");
    $stmt->execute(['rid' => $residencialId]);
    $meta['guardias'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($isOperational) {
        $meta['areas'] = operational_area_options($pdo, $residencialId);

        $stmt = $pdo->prepare("
            SELECT id, nombre
            FROM personas_recurrentes
            WHERE residencial_id = :rid
              AND activo = 1
            ORDER BY nombre ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $meta['personas'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("
            SELECT id, nombre_visitante
            FROM visitantes_rapidos
            WHERE residencial_id = :rid
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute(['rid' => $residencialId]);
        $meta['visitantes'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("
            SELECT id, tipo_movimiento
            FROM permisos_materiales
            WHERE residencial_id = :rid
              AND estado IN ('pendiente', 'aprobado', 'en_proceso')
            ORDER BY created_at DESC
            LIMIT 100
        ");
        $stmt->execute(['rid' => $residencialId]);
        $meta['permisos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $meta;
}

function sa_incidencia_validate_guardia(PDO $pdo, int $guardiaId, int $residencialId): void
{
    $stmt = $pdo->prepare("
        SELECT u.id
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        JOIN usuarios_residenciales ur ON ur.user_id = u.id
        WHERE u.id = :id
          AND ur.residencial_id = :rid
          AND t.nombre = 'guardia'
        LIMIT 1
    ");
    $stmt->execute([
        'id' => $guardiaId,
        'rid' => $residencialId,
    ]);
    if (!$stmt->fetchColumn()) {
        sa_json_out(false, ['error' => 'El guardia seleccionado no es válido para este servicio.'], 422);
    }
}

try {
    operational_schema_ensure($pdo);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'meta') {
        $residencialId = (int)($_GET['residencial_id'] ?? 0);
        $payload = [
            'csrf_token' => sa_csrf_token(),
            'servicios' => sa_fetch_services($pdo),
        ];
        if ($residencialId > 0) {
            $payload['service_meta'] = sa_incidencia_meta($pdo, $residencialId);
        }
        sa_json_out(true, ['data' => $payload]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'list') {
        $residencialId = (int)($_GET['residencial_id'] ?? 0);
        $estado = trim((string)($_GET['estado'] ?? ''));
        $prioridad = trim((string)($_GET['prioridad'] ?? ''));
        $tipo = trim((string)($_GET['tipo'] ?? ''));
        $q = sa_clean_str($_GET['q'] ?? '', 120);

        $sql = "
            SELECT
                i.*,
                r.nombre AS residencial_nombre,
                r.codigo AS residencial_codigo,
                r.modo_operacion,
                un.clave AS unidad_clave,
                res.name AS residente_nombre,
                gur.name AS guardia_nombre,
                ar.nombre AS area_nombre,
                pr.nombre AS persona_recurrente_nombre,
                vr.nombre_visitante AS visitante_rapido_nombre
            FROM incidencias i
            JOIN residenciales r ON r.id = i.residencial_id
            LEFT JOIN unidades un ON un.id = i.unidad_id
            LEFT JOIN users res ON res.id = i.residente_id
            LEFT JOIN users gur ON gur.id = i.guardia_id
            LEFT JOIN areas_operativas ar ON ar.id = i.area_id
            LEFT JOIN personas_recurrentes pr ON pr.id = i.persona_recurrente_id
            LEFT JOIN visitantes_rapidos vr ON vr.id = i.visitante_rapido_id
            WHERE 1 = 1
        ";
        $params = [];

        if ($residencialId > 0) {
            $sql .= " AND i.residencial_id = :rid";
            $params['rid'] = $residencialId;
        }
        if ($estado !== '' && in_array($estado, ['abierta', 'en_proceso', 'cerrada'], true)) {
            $sql .= " AND i.estado = :estado";
            $params['estado'] = $estado;
        }
        if ($prioridad !== '' && in_array($prioridad, ['baja', 'media', 'alta'], true)) {
            $sql .= " AND i.prioridad = :prioridad";
            $params['prioridad'] = $prioridad;
        }
        if ($tipo !== '') {
            $sql .= " AND i.tipo LIKE :tipo";
            $params['tipo'] = '%' . $tipo . '%';
        }
        if ($q !== '') {
            $sql .= " AND (i.titulo LIKE :q OR i.descripcion LIKE :q OR r.nombre LIKE :q OR r.codigo LIKE :q OR un.clave LIKE :q OR res.name LIKE :q OR gur.name LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $sql .= "
            ORDER BY
                CASE i.estado
                    WHEN 'abierta' THEN 1
                    WHEN 'en_proceso' THEN 2
                    WHEN 'cerrada' THEN 3
                    ELSE 4
                END,
                i.updated_at DESC,
                i.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = array_map('sa_incidencia_normalize', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $summary = [
            'total' => count($items),
            'abiertas' => 0,
            'en_proceso' => 0,
            'cerradas' => 0,
        ];
        foreach ($items as $item) {
            $status = (string)($item['estado'] ?? '');
            if ($status === 'abierta') {
                $summary['abiertas']++;
            } elseif ($status === 'en_proceso') {
                $summary['en_proceso']++;
            } elseif ($status === 'cerrada') {
                $summary['cerradas']++;
            }
        }

        sa_json_out(true, ['data' => ['items' => $items, 'summary' => $summary]]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        sa_require_csrf();

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                sa_json_out(false, ['error' => 'Incidencia inválida.'], 422);
            }

            $stmt = $pdo->prepare("DELETE FROM incidencias WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() <= 0) {
                sa_json_out(false, ['error' => 'No encontramos la incidencia a eliminar.'], 404);
            }

            sa_json_out(true, ['message' => 'Incidencia eliminada.']);
        }

        if ($action === 'update') {
            $incidentId = (int)($_POST['id'] ?? 0);
            $newState = trim((string)($_POST['estado'] ?? ''));
            $newPriority = trim((string)($_POST['prioridad'] ?? ''));
            $assignGuardId = isset($_POST['guardia_id']) && $_POST['guardia_id'] !== '' ? (int)$_POST['guardia_id'] : null;

            if ($incidentId <= 0) {
                sa_json_out(false, ['error' => 'Incidencia inválida.'], 422);
            }
            if (!in_array($newState, ['abierta', 'en_proceso', 'cerrada'], true)) {
                sa_json_out(false, ['error' => 'Estado inválido.'], 422);
            }
            if (!in_array($newPriority, ['baja', 'media', 'alta'], true)) {
                sa_json_out(false, ['error' => 'Prioridad inválida.'], 422);
            }

            $stmtCheck = $pdo->prepare("
                SELECT id, residencial_id, estado
                FROM incidencias
                WHERE id = :id
                LIMIT 1
            ");
            $stmtCheck->execute(['id' => $incidentId]);
            $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                sa_json_out(false, ['error' => 'Incidencia no encontrada.'], 404);
            }

            $residencialId = (int)($current['residencial_id'] ?? 0);
            if ($assignGuardId !== null) {
                sa_incidencia_validate_guardia($pdo, $assignGuardId, $residencialId);
            }

            $stmt = $pdo->prepare("
                UPDATE incidencias
                SET guardia_id = :guardia_id,
                    estado = :estado,
                    prioridad = :prioridad,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                'guardia_id' => $assignGuardId,
                'estado' => $newState,
                'prioridad' => $newPriority,
                'id' => $incidentId,
            ]);

            sa_json_out(true, ['message' => 'Incidencia actualizada.']);
        }

        if ($action === 'create') {
            $residencialId = (int)($_POST['residencial_id'] ?? 0);
            if ($residencialId <= 0) {
                sa_json_out(false, ['error' => 'Debes seleccionar un servicio.'], 422);
            }

            $stmtService = $pdo->prepare("SELECT id FROM residenciales WHERE id = :id LIMIT 1");
            $stmtService->execute(['id' => $residencialId]);
            if (!$stmtService->fetchColumn()) {
                sa_json_out(false, ['error' => 'Servicio no encontrado.'], 404);
            }

            $mode = operational_get_mode($pdo, $residencialId);
            $isOperational = operational_is_operational_mode($mode);

            $unidadId = isset($_POST['unidad_id']) && $_POST['unidad_id'] !== '' ? (int)$_POST['unidad_id'] : null;
            $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;
            $personaId = isset($_POST['persona_recurrente_id']) && $_POST['persona_recurrente_id'] !== '' ? (int)$_POST['persona_recurrente_id'] : null;
            $visitanteId = isset($_POST['visitante_rapido_id']) && $_POST['visitante_rapido_id'] !== '' ? (int)$_POST['visitante_rapido_id'] : null;
            $permisoId = isset($_POST['permiso_material_id']) && $_POST['permiso_material_id'] !== '' ? (int)$_POST['permiso_material_id'] : null;
            $origenTipo = sa_clean_str($_POST['origen_tipo'] ?? '', 60);
            $tipo = sa_clean_str($_POST['tipo'] ?? 'otro', 60);
            $titulo = sa_clean_str($_POST['titulo'] ?? '', 150);
            $descripcion = sa_clean_str($_POST['descripcion'] ?? '', 1000);
            $prioridad = sa_clean_str($_POST['prioridad'] ?? 'media', 20);

            if ($titulo === '' || mb_strlen($titulo) < 3) {
                sa_json_out(false, ['error' => 'El título debe tener al menos 3 caracteres.'], 422);
            }
            if ($descripcion === '' || mb_strlen($descripcion) < 5) {
                sa_json_out(false, ['error' => 'La descripción debe tener al menos 5 caracteres.'], 422);
            }
            if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) {
                sa_json_out(false, ['error' => 'Prioridad inválida.'], 422);
            }

            if ($isOperational) {
                if (!in_array($tipo, ['seguridad', 'robo', 'conflicto', 'salida_sin_permiso', 'visitante_sin_ine', 'material_no_coincide', 'evento_general', 'otro'], true)) {
                    sa_json_out(false, ['error' => 'Tipo inválido para el modo operativo.'], 422);
                }
                if ($areaId !== null && !operational_validate_area($pdo, $residencialId, $areaId)) {
                    sa_json_out(false, ['error' => 'El área seleccionada no pertenece a este servicio.'], 422);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO incidencias (
                        residencial_id, unidad_id, residente_id, guardia_id, area_id, persona_recurrente_id,
                        visitante_rapido_id, permiso_material_id, origen_tipo, tipo, titulo, descripcion,
                        prioridad, estado, created_at, updated_at
                    ) VALUES (
                        :resid, NULL, NULL, NULL, :area_id, :persona_id,
                        :visitante_id, :permiso_id, :origen_tipo, :tipo, :titulo, :descripcion,
                        :prioridad, 'abierta', NOW(), NOW()
                    )
                ");
                $stmt->execute([
                    'resid' => $residencialId,
                    'area_id' => $areaId,
                    'persona_id' => $personaId,
                    'visitante_id' => $visitanteId,
                    'permiso_id' => $permisoId,
                    'origen_tipo' => $origenTipo !== '' ? $origenTipo : null,
                    'tipo' => $tipo,
                    'titulo' => $titulo,
                    'descripcion' => $descripcion,
                    'prioridad' => $prioridad,
                ]);

                sa_json_out(true, ['message' => 'Incidencia operativa registrada correctamente.']);
            }

            if ($unidadId === null || $unidadId <= 0) {
                sa_json_out(false, ['error' => 'Debes seleccionar una unidad.'], 422);
            }
            if (!in_array($tipo, ['seguridad', 'servicio', 'vecino', 'infraestructura', 'otro'], true)) {
                sa_json_out(false, ['error' => 'Tipo inválido para este servicio.'], 422);
            }

            $stmtUnidad = $pdo->prepare("
                SELECT id
                FROM unidades
                WHERE id = :id
                  AND residencial_id = :rid
                LIMIT 1
            ");
            $stmtUnidad->execute([
                'id' => $unidadId,
                'rid' => $residencialId,
            ]);
            if (!$stmtUnidad->fetchColumn()) {
                sa_json_out(false, ['error' => 'La unidad no pertenece a este servicio.'], 422);
            }

            $stmt = $pdo->prepare("
                INSERT INTO incidencias (
                    residencial_id, unidad_id, residente_id, guardia_id, tipo, titulo, descripcion,
                    prioridad, estado, created_at, updated_at
                ) VALUES (
                    :resid, :unidad_id, NULL, NULL, :tipo, :titulo, :descripcion,
                    :prioridad, 'abierta', NOW(), NOW()
                )
            ");
            $stmt->execute([
                'resid' => $residencialId,
                'unidad_id' => $unidadId,
                'tipo' => $tipo,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'prioridad' => $prioridad,
            ]);

            sa_json_out(true, ['message' => 'Incidencia registrada correctamente.']);
        }
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    sa_json_out(false, ['error' => 'No pudimos procesar las incidencias.'], 500);
}
