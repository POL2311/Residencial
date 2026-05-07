<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
admin_module_required('incidencias', 'Las incidencias no están habilitadas para este cliente.');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isOperational = operational_is_operational_mode($operationalMode);

function normalize_incidencia(array $row): array
{
    return [
        'id' => (int)($row['id'] ?? 0),
        'titulo' => (string)($row['titulo'] ?? ''),
        'descripcion' => (string)($row['descripcion'] ?? ''),
        'tipo' => (string)($row['tipo'] ?? ''),
        'prioridad' => (string)($row['prioridad'] ?? 'media'),
        'estado' => (string)($row['estado'] ?? 'abierta'),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'residente_nombre' => (string)($row['residente_nombre'] ?? ''),
        'unidad_clave' => (string)($row['unidad_clave'] ?? ''),
        'guardia_nombre' => (string)($row['guardia_nombre'] ?? ''),
        'guardia_id' => isset($row['guardia_id']) ? (int)$row['guardia_id'] : null,
        'area_id' => isset($row['area_id']) && $row['area_id'] !== null ? (int)$row['area_id'] : null,
        'area_nombre' => (string)($row['area_nombre'] ?? ''),
        'persona_recurrente_id' => isset($row['persona_recurrente_id']) && $row['persona_recurrente_id'] !== null ? (int)$row['persona_recurrente_id'] : null,
        'persona_recurrente_nombre' => (string)($row['persona_recurrente_nombre'] ?? ''),
        'visitante_rapido_id' => isset($row['visitante_rapido_id']) && $row['visitante_rapido_id'] !== null ? (int)$row['visitante_rapido_id'] : null,
        'visitante_rapido_nombre' => (string)($row['visitante_rapido_nombre'] ?? ''),
        'permiso_material_id' => isset($row['permiso_material_id']) && $row['permiso_material_id'] !== null ? (int)$row['permiso_material_id'] : null,
        'origen_tipo' => (string)($row['origen_tipo'] ?? ''),
    ];
}

function admin_incidencias_meta(PDO $pdo, int $residencialId, bool $isOperational): array
{
    $meta = [
        'modo_operacion' => $isOperational ? operational_get_mode($pdo, $residencialId) : 'residencial',
        'areas' => [],
        'personas' => [],
        'visitantes' => [],
        'permisos' => [],
    ];

    if (!$isOperational) {
        return $meta;
    }

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

    return $meta;
}

try {
    if ($method === 'GET') {
        $action = clean_str($_GET['action'] ?? 'list');

        if ($action === 'meta') {
            json_out(true, ['data' => admin_incidencias_meta($pdo, $residencialId, $isOperational)]);
        }

        $estadoFilter = trim((string)($_GET['estado'] ?? ''));
        $q = clean_str($_GET['q'] ?? '');
        $params = ['resid' => $residencialId];
        $where = ['i.residencial_id = :resid'];

        if ($estadoFilter !== '' && in_array($estadoFilter, ['abierta', 'en_proceso', 'cerrada'], true)) {
            $where[] = 'i.estado = :estado';
            $params['estado'] = $estadoFilter;
        }

        if ($q !== '') {
            $where[] = "(i.titulo LIKE :q OR i.descripcion LIKE :q OR un.clave LIKE :q OR ar.nombre LIKE :q OR pr.nombre LIKE :q OR vr.nombre_visitante LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $stmt = $pdo->prepare("
            SELECT
                i.*,
                res.name AS residente_nombre,
                un.clave AS unidad_clave,
                gur.name AS guardia_nombre,
                ar.nombre AS area_nombre,
                pr.nombre AS persona_recurrente_nombre,
                vr.nombre_visitante AS visitante_rapido_nombre
            FROM incidencias i
            LEFT JOIN users res ON res.id = i.residente_id
            LEFT JOIN unidades un ON un.id = i.unidad_id
            LEFT JOIN users gur ON gur.id = i.guardia_id
            LEFT JOIN areas_operativas ar ON ar.id = i.area_id
            LEFT JOIN personas_recurrentes pr ON pr.id = i.persona_recurrente_id
            LEFT JOIN visitantes_rapidos vr ON vr.id = i.visitante_rapido_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE i.estado
                    WHEN 'abierta' THEN 1
                    WHEN 'en_proceso' THEN 2
                    WHEN 'cerrada' THEN 3
                    ELSE 4
                END,
                i.updated_at DESC,
                i.created_at DESC
        ");
        $stmt->execute($params);
        $incidencias = array_map('normalize_incidencia', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        json_out(true, ['incidencias' => $incidencias]);
    }

    $action = clean_str($_POST['action'] ?? '');

    if ($action === 'delete') {
        $incidentId = (int)($_POST['id'] ?? 0);
        if ($incidentId <= 0) {
            json_out(false, ['error' => 'ID inválido.']);
        }

        $stmt = $pdo->prepare("
            DELETE FROM incidencias
            WHERE id = :id
              AND residencial_id = :resid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $incidentId,
            'resid' => $residencialId,
        ]);

        if ($stmt->rowCount() === 0) {
            json_out(false, ['error' => 'Incidencia no encontrada o fuera de tu cliente.']);
        }

        json_out(true, ['message' => 'Incidencia eliminada.']);
    }

    if (isset($_POST['id']) && $_POST['id'] !== '' && $action !== 'create') {
        $incidentId = (int)$_POST['id'];
        $newState = trim((string)($_POST['estado'] ?? ''));
        $assignGuardId = isset($_POST['guardia_id']) && $_POST['guardia_id'] !== '' ? (int)$_POST['guardia_id'] : null;
        $newPriority = trim((string)($_POST['prioridad'] ?? ''));

        if ($incidentId <= 0) {
            json_out(false, ['error' => 'ID inválido.']);
        }

        $stmtCheck = $pdo->prepare("
            SELECT id, estado, guardia_id, prioridad
            FROM incidencias
            WHERE id = :id
              AND residencial_id = :resid
            LIMIT 1
        ");
        $stmtCheck->execute([
            'id' => $incidentId,
            'resid' => $residencialId,
        ]);
        $current = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            json_out(false, ['error' => 'Incidencia no encontrada.']);
        }

        if ($newState !== '' && !in_array($newState, ['abierta', 'en_proceso', 'cerrada'], true)) {
            json_out(false, ['error' => 'Estado inválido.']);
        }
        if ($newPriority !== '' && !in_array($newPriority, ['baja', 'media', 'alta'], true)) {
            json_out(false, ['error' => 'Prioridad inválida.']);
        }

        if ($assignGuardId !== null) {
            $stmtGuard = $pdo->prepare("
                SELECT u.id
                FROM users u
                JOIN usuarios_residenciales ur ON ur.user_id = u.id
                JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                WHERE u.id = :id
                  AND ur.residencial_id = :resid
                  AND t.nombre = 'guardia'
                LIMIT 1
            ");
            $stmtGuard->execute([
                'id' => $assignGuardId,
                'resid' => $residencialId,
            ]);
            if (!$stmtGuard->fetchColumn()) {
                json_out(false, ['error' => 'El guardia seleccionado no es válido.']);
            }
        }

        $updEstado = $newState !== '' ? $newState : (string)$current['estado'];
        if ($newState === '' && $assignGuardId !== null && (string)$current['estado'] === 'abierta') {
            $updEstado = 'en_proceso';
        }
        $updGuard = isset($_POST['guardia_id']) ? $assignGuardId : ($current['guardia_id'] !== null ? (int)$current['guardia_id'] : null);
        $updPrio = $newPriority !== '' ? $newPriority : (string)$current['prioridad'];

        $stmtUpd = $pdo->prepare("
            UPDATE incidencias
            SET guardia_id = :g,
                estado = :estado,
                prioridad = :prio,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :resid
        ");
        $stmtUpd->execute([
            'g' => $updGuard,
            'estado' => $updEstado,
            'prio' => $updPrio,
            'id' => $incidentId,
            'resid' => $residencialId,
        ]);

        json_out(true, ['message' => 'Incidencia actualizada.']);
    }

    $unidadId = isset($_POST['unidad_id']) && $_POST['unidad_id'] !== '' ? (int)$_POST['unidad_id'] : null;
    $residenteId = isset($_POST['residente_id']) && $_POST['residente_id'] !== '' ? (int)$_POST['residente_id'] : null;
    $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;
    $personaId = isset($_POST['persona_recurrente_id']) && $_POST['persona_recurrente_id'] !== '' ? (int)$_POST['persona_recurrente_id'] : null;
    $visitanteId = isset($_POST['visitante_rapido_id']) && $_POST['visitante_rapido_id'] !== '' ? (int)$_POST['visitante_rapido_id'] : null;
    $permisoId = isset($_POST['permiso_material_id']) && $_POST['permiso_material_id'] !== '' ? (int)$_POST['permiso_material_id'] : null;
    $origenTipo = clean_str($_POST['origen_tipo'] ?? '');
    $incidenciaScope = clean_str($_POST['incidencia_scope'] ?? 'unidad');
    $tipo = clean_str($_POST['tipo'] ?? 'otro');
    $titulo = clean_str($_POST['titulo'] ?? '');
    $descripcion = clean_str($_POST['descripcion'] ?? '');
    $prioridad = clean_str($_POST['prioridad'] ?? 'media');

    if ($titulo === '' || $descripcion === '') {
        json_out(false, ['error' => 'Título y descripción son requeridos.']);
    }
    if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) {
        json_out(false, ['error' => 'Prioridad inválida.']);
    }

    if ($isOperational) {
        if (!in_array($tipo, ['seguridad', 'robo', 'conflicto', 'salida_sin_permiso', 'visitante_sin_ine', 'material_no_coincide', 'evento_general', 'otro'], true)) {
            json_out(false, ['error' => 'Tipo inválido para el modo operativo.']);
        }

        if ($areaId !== null && !operational_validate_area($pdo, $residencialId, $areaId)) {
            json_out(false, ['error' => 'El área seleccionada no pertenece a tu cliente.']);
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

        json_out(true, ['message' => 'Incidencia operativa registrada exitosamente.']);
    }

    if ($unidadId === null || $unidadId <= 0) {
        json_out(false, ['error' => 'Unidad requerida en modo residencial.']);
    }
    if (!in_array($tipo, ['seguridad', 'servicio', 'vecino', 'infraestructura', 'otro'], true)) {
        json_out(false, ['error' => 'Tipo inválido.']);
    }

    if ($incidenciaScope === 'general') {
        $stmtIns = $pdo->prepare("
            INSERT INTO incidencias (
                residencial_id, unidad_id, residente_id, guardia_id, tipo, titulo, descripcion, prioridad,
                estado, created_at, updated_at
            ) VALUES (
                :resid, NULL, NULL, NULL, :tipo, :titulo, :descripcion, :prioridad,
                'abierta', NOW(), NOW()
            )
        ");
        $stmtIns->execute([
            'resid' => $residencialId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'prioridad' => $prioridad,
        ]);

        json_out(true, ['message' => 'Incidencia general registrada exitosamente.']);
    }

    $stmtUnidad = $pdo->prepare("
        SELECT id
        FROM unidades
        WHERE id = :id
          AND residencial_id = :resid
        LIMIT 1
    ");
    $stmtUnidad->execute([
        'id' => $unidadId,
        'resid' => $residencialId,
    ]);
    if (!$stmtUnidad->fetchColumn()) {
        json_out(false, ['error' => 'La unidad no pertenece a tu residencial.']);
    }

    if ($residenteId === null || $residenteId <= 0) {
        $stmtRu = $pdo->prepare("
            SELECT ru.user_id
            FROM residentes_unidades ru
            JOIN unidades un ON un.id = ru.unidad_id
            WHERE ru.unidad_id = :unidad
              AND un.residencial_id = :resid
              AND ru.activo = 1
            ORDER BY ru.es_titular DESC, ru.id ASC
            LIMIT 1
        ");
        $stmtRu->execute([
            'unidad' => $unidadId,
            'resid' => $residencialId,
        ]);
        $residenteId = (int)$stmtRu->fetchColumn();
    }

    if (!$residenteId) {
        json_out(false, ['error' => 'No hay un residente asignado a esa unidad.']);
    }

    $stmtIns = $pdo->prepare("
        INSERT INTO incidencias (
            residencial_id, unidad_id, residente_id, guardia_id, tipo, titulo, descripcion, prioridad,
            estado, created_at, updated_at
        ) VALUES (
            :resid, :unidad, :residente, NULL, :tipo, :titulo, :descripcion, :prioridad,
            'abierta', NOW(), NOW()
        )
    ");
    $stmtIns->execute([
        'resid' => $residencialId,
        'unidad' => $unidadId,
        'residente' => $residenteId,
        'tipo' => $tipo,
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'prioridad' => $prioridad,
    ]);

    json_out(true, ['message' => 'Incidencia registrada exitosamente.']);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar las incidencias.');
}
