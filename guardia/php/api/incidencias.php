<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isOperational = operational_is_operational_mode($operationalMode);

function guardia_clean(string $value): string
{
    return trim((string)preg_replace('/\s+/', ' ', $value));
}

function guardia_get_inc(PDO $pdo, int $id, int $rid): ?array
{
    $stmt = $pdo->prepare("
        SELECT i.*
        FROM incidencias i
        WHERE i.id = :id
          AND i.residencial_id = :rid
        LIMIT 1
    ");
    $stmt->execute(['id' => $id, 'rid' => $rid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function guardia_validate_unidad(PDO $pdo, int $unidadId, int $rid): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM unidades
        WHERE id = :id
          AND residencial_id = :rid
          AND activo = 1
        LIMIT 1
    ");
    $stmt->execute(['id' => $unidadId, 'rid' => $rid]);
    return (bool)$stmt->fetchColumn();
}

function guardia_validate_residente_unidad(PDO $pdo, int $residenteId, int $unidadId, int $rid): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM residentes_unidades ru
        JOIN unidades u ON u.id = ru.unidad_id
        WHERE ru.user_id = :residente_id
          AND ru.unidad_id = :unidad_id
          AND ru.activo = 1
          AND u.residencial_id = :rid
        LIMIT 1
    ");
    $stmt->execute([
        'residente_id' => $residenteId,
        'unidad_id' => $unidadId,
        'rid' => $rid,
    ]);
    return (bool)$stmt->fetchColumn();
}

try {
    if ($method === 'GET' && (($_GET['action'] ?? '') === 'meta')) {
        $data = [
            'modo_operacion' => $operationalMode,
            'areas' => [],
            'personas' => [],
            'visitantes' => [],
            'permisos' => [],
        ];

        if ($isOperational) {
            $data['areas'] = operational_area_options($pdo, $residencialId);

            $stmt = $pdo->prepare("SELECT id, nombre FROM personas_recurrentes WHERE residencial_id = :rid AND activo = 1 ORDER BY nombre ASC");
            $stmt->execute(['rid' => $residencialId]);
            $data['personas'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $pdo->prepare("SELECT id, nombre_visitante FROM visitantes_rapidos WHERE residencial_id = :rid ORDER BY created_at DESC LIMIT 100");
            $stmt->execute(['rid' => $residencialId]);
            $data['visitantes'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $pdo->prepare("SELECT id, tipo_movimiento FROM permisos_materiales WHERE residencial_id = :rid AND estado IN ('pendiente','aprobado','en_proceso') ORDER BY created_at DESC LIMIT 100");
            $stmt->execute(['rid' => $residencialId]);
            $data['permisos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        json_out(true, ['data' => $data]);
    }

    if ($method === 'GET' && (($_GET['action'] ?? '') === 'unidades')) {
        $stmt = $pdo->prepare("
            SELECT id, clave
            FROM unidades
            WHERE residencial_id = :rid
              AND activo = 1
            ORDER BY clave ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        json_out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    if ($method === 'GET' && (($_GET['action'] ?? '') === 'residentes_unidad')) {
        $unidadId = (int)($_GET['unidad_id'] ?? 0);
        if ($unidadId <= 0) {
            json_out(false, ['error' => 'Unidad inválida.'], 422);
        }
        if (!guardia_validate_unidad($pdo, $unidadId, $residencialId)) {
            json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT ru.user_id AS id, us.name, us.email, ru.es_titular
            FROM residentes_unidades ru
            JOIN users us ON us.id = ru.user_id
            JOIN unidades u ON u.id = ru.unidad_id
            WHERE ru.unidad_id = :unidad_id
              AND ru.activo = 1
              AND u.residencial_id = :rid
            ORDER BY ru.es_titular DESC, us.name ASC
        ");
        $stmt->execute(['unidad_id' => $unidadId, 'rid' => $residencialId]);
        json_out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    if ($method === 'GET') {
        $q = guardia_clean((string)($_GET['q'] ?? ''));
        $estado = guardia_clean((string)($_GET['estado'] ?? ''));
        $prioridad = guardia_clean((string)($_GET['prioridad'] ?? ''));
        $tipo = guardia_clean((string)($_GET['tipo'] ?? ''));

        $where = ["i.residencial_id = :rid"];
        $params = ['rid' => $residencialId];

        if ($q !== '') {
            $where[] = "(i.titulo LIKE :q OR i.descripcion LIKE :q OR u.clave LIKE :q OR a.nombre LIKE :q OR pr.nombre LIKE :q OR vr.nombre_visitante LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }
        if ($estado !== '') {
            $where[] = "i.estado = :estado";
            $params['estado'] = $estado;
        }
        if ($prioridad !== '') {
            $where[] = "i.prioridad = :prioridad";
            $params['prioridad'] = $prioridad;
        }
        if ($tipo !== '') {
            $where[] = "i.tipo = :tipo";
            $params['tipo'] = $tipo;
        }

        $sql = "
            SELECT
                i.*,
                u.clave AS unidad_clave,
                a.nombre AS area_nombre,
                pr.nombre AS persona_recurrente_nombre,
                vr.nombre_visitante AS visitante_rapido_nombre
            FROM incidencias i
            LEFT JOIN unidades u ON u.id = i.unidad_id
            LEFT JOIN areas_operativas a ON a.id = i.area_id
            LEFT JOIN personas_recurrentes pr ON pr.id = i.persona_recurrente_id
            LEFT JOIN visitantes_rapidos vr ON vr.id = i.visitante_rapido_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.created_at DESC, i.id DESC
            LIMIT 200
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
    }

    $action = guardia_clean((string)($_POST['action'] ?? ''));

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_out(false, ['error' => 'Incidencia inválida.'], 422);
        $stmt = $pdo->prepare("
            DELETE FROM incidencias
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute(['id' => $id, 'rid' => $residencialId]);
        if ($stmt->rowCount() <= 0) {
            json_out(false, ['error' => 'No se pudo eliminar la incidencia.'], 404);
        }
        json_out(true, ['message' => 'Incidencia eliminada correctamente.']);
    }

    if ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $tipo = guardia_clean((string)($_POST['tipo'] ?? 'seguridad'));
        $prioridad = guardia_clean((string)($_POST['prioridad'] ?? 'media'));
        $estado = guardia_clean((string)($_POST['estado'] ?? 'abierta'));
        $titulo = guardia_clean((string)($_POST['titulo'] ?? ''));
        $descripcion = guardia_clean((string)($_POST['descripcion'] ?? ''));

        if ($id <= 0) json_out(false, ['error' => 'Incidencia inválida.'], 422);
        if ($titulo === '' || $descripcion === '') json_out(false, ['error' => 'Título y descripción son obligatorios.'], 422);
        if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) json_out(false, ['error' => 'Prioridad inválida.'], 422);
        if (!in_array($estado, ['abierta', 'en_proceso', 'cerrada'], true)) json_out(false, ['error' => 'Estado inválido.'], 422);

        $existing = guardia_get_inc($pdo, $id, $residencialId);
        if (!$existing) json_out(false, ['error' => 'Incidencia no encontrada.'], 404);

        if ($isOperational) {
            $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;
            $personaId = isset($_POST['persona_recurrente_id']) && $_POST['persona_recurrente_id'] !== '' ? (int)$_POST['persona_recurrente_id'] : null;
            $visitanteId = isset($_POST['visitante_rapido_id']) && $_POST['visitante_rapido_id'] !== '' ? (int)$_POST['visitante_rapido_id'] : null;
            $permisoId = isset($_POST['permiso_material_id']) && $_POST['permiso_material_id'] !== '' ? (int)$_POST['permiso_material_id'] : null;
            $origenTipo = guardia_clean((string)($_POST['origen_tipo'] ?? ''));

            $stmt = $pdo->prepare("
                UPDATE incidencias
                SET area_id = :area_id,
                    persona_recurrente_id = :persona_id,
                    visitante_rapido_id = :visitante_id,
                    permiso_material_id = :permiso_id,
                    origen_tipo = :origen_tipo,
                    titulo = :titulo,
                    descripcion = :descripcion,
                    tipo = :tipo,
                    prioridad = :prioridad,
                    estado = :estado,
                    updated_at = NOW()
                WHERE id = :id
                  AND residencial_id = :rid
                LIMIT 1
            ");
            $stmt->execute([
                'area_id' => $areaId,
                'persona_id' => $personaId,
                'visitante_id' => $visitanteId,
                'permiso_id' => $permisoId,
                'origen_tipo' => $origenTipo !== '' ? $origenTipo : null,
                'titulo' => $titulo,
                'descripcion' => $descripcion,
                'tipo' => $tipo,
                'prioridad' => $prioridad,
                'estado' => $estado,
                'id' => $id,
                'rid' => $residencialId,
            ]);
            json_out(true, ['message' => 'Incidencia actualizada correctamente.']);
        }

        $unidadId = (int)($_POST['unidad_id'] ?? 0);
        $residenteId = (int)($_POST['residente_id'] ?? 0);
        if ($unidadId <= 0) json_out(false, ['error' => 'Debes seleccionar una unidad.'], 422);
        if ($residenteId <= 0) json_out(false, ['error' => 'Debes seleccionar un residente.'], 422);
        if (!guardia_validate_unidad($pdo, $unidadId, $residencialId)) json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
        if (!guardia_validate_residente_unidad($pdo, $residenteId, $unidadId, $residencialId)) json_out(false, ['error' => 'El residente seleccionado no pertenece a la unidad.'], 422);

        $stmt = $pdo->prepare("
            UPDATE incidencias
            SET unidad_id = :unidad_id,
                residente_id = :residente_id,
                titulo = :titulo,
                descripcion = :descripcion,
                tipo = :tipo,
                prioridad = :prioridad,
                estado = :estado,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'unidad_id' => $unidadId,
            'residente_id' => $residenteId,
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'tipo' => $tipo,
            'prioridad' => $prioridad,
            'estado' => $estado,
            'id' => $id,
            'rid' => $residencialId,
        ]);
        json_out(true, ['message' => 'Incidencia actualizada correctamente.']);
    }

    if ($action !== 'create') {
        json_out(false, ['error' => 'Acción inválida.'], 422);
    }

    $tipo = guardia_clean((string)($_POST['tipo'] ?? 'seguridad'));
    $prioridad = guardia_clean((string)($_POST['prioridad'] ?? 'media'));
    $titulo = guardia_clean((string)($_POST['titulo'] ?? ''));
    $descripcion = guardia_clean((string)($_POST['descripcion'] ?? ''));

    if ($titulo === '' || $descripcion === '') {
        json_out(false, ['error' => 'Título y descripción son obligatorios.'], 422);
    }
    if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) {
        json_out(false, ['error' => 'Prioridad inválida.'], 422);
    }

    if ($isOperational) {
        $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;
        $personaId = isset($_POST['persona_recurrente_id']) && $_POST['persona_recurrente_id'] !== '' ? (int)$_POST['persona_recurrente_id'] : null;
        $visitanteId = isset($_POST['visitante_rapido_id']) && $_POST['visitante_rapido_id'] !== '' ? (int)$_POST['visitante_rapido_id'] : null;
        $permisoId = isset($_POST['permiso_material_id']) && $_POST['permiso_material_id'] !== '' ? (int)$_POST['permiso_material_id'] : null;
        $origenTipo = guardia_clean((string)($_POST['origen_tipo'] ?? ''));

        $stmt = $pdo->prepare("
            INSERT INTO incidencias (
                residencial_id, guardia_id, area_id, persona_recurrente_id, visitante_rapido_id,
                permiso_material_id, origen_tipo, tipo, titulo, descripcion, prioridad, estado, created_at, updated_at
            ) VALUES (
                :rid, :guardia_id, :area_id, :persona_id, :visitante_id,
                :permiso_id, :origen_tipo, :tipo, :titulo, :descripcion, :prioridad, 'abierta', NOW(), NOW()
            )
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'guardia_id' => $guardiaId,
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

        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'incidencia',
            'origen_id' => (int)$pdo->lastInsertId(),
            'tipo_evento' => 'incidencia_creada',
            'resultado' => 'permitido',
            'persona_recurrente_id' => $personaId,
            'visitante_rapido_id' => $visitanteId,
            'permiso_material_id' => $permisoId,
            'area_id' => $areaId,
            'observaciones' => $titulo,
            'metadata_json' => ['tipo' => $tipo],
        ]);

        json_out(true, ['message' => 'Incidencia registrada correctamente.']);
    }

    $unidadId = (int)($_POST['unidad_id'] ?? 0);
    $residenteId = (int)($_POST['residente_id'] ?? 0);
    if ($unidadId <= 0) json_out(false, ['error' => 'Debes seleccionar una unidad.'], 422);
    if ($residenteId <= 0) json_out(false, ['error' => 'Debes seleccionar un residente.'], 422);
    if (!guardia_validate_unidad($pdo, $unidadId, $residencialId)) json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
    if (!guardia_validate_residente_unidad($pdo, $residenteId, $unidadId, $residencialId)) json_out(false, ['error' => 'El residente seleccionado no pertenece a la unidad.'], 422);

    $stmt = $pdo->prepare("
        INSERT INTO incidencias (
            residencial_id, unidad_id, residente_id, guardia_id, tipo, titulo, descripcion, prioridad, estado, created_at, updated_at
        ) VALUES (
            :rid, :unidad_id, :residente_id, :guardia_id, :tipo, :titulo, :descripcion, :prioridad, 'abierta', NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $residencialId,
        'unidad_id' => $unidadId,
        'residente_id' => $residenteId,
        'guardia_id' => $guardiaId,
        'tipo' => $tipo,
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'prioridad' => $prioridad,
    ]);

    json_out(true, ['message' => 'Incidencia registrada correctamente.']);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar las incidencias.');
}
