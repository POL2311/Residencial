<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
admin_module_required('rondines', 'El módulo de rondines no está habilitado para este cliente.');

function rondines_float_or_null(mixed $value): ?float
{
    $raw = trim((string)($value ?? ''));
    if ($raw === '') {
        return null;
    }
    if (!is_numeric($raw)) {
        return null;
    }
    return (float)$raw;
}

function rondines_bool(mixed $value): int
{
    return operational_bool_int($value);
}

function rondines_qr_payload(string $codigo): string
{
    return 'op:round_point:' . trim($codigo);
}

function rondines_new_point_code(PDO $pdo): string
{
    do {
        $code = 'RP-' . strtoupper(substr(operational_generate_token(10), 0, 16));
        $stmt = $pdo->prepare("SELECT 1 FROM rondines_puntos WHERE codigo_qr = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
    } while ($stmt->fetchColumn());
    return $code;
}

function rondines_route_belongs(PDO $pdo, int $residencialId, int $routeId): bool
{
    if ($routeId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM rondines_rutas WHERE id = :id AND residencial_id = :rid LIMIT 1");
    $stmt->execute(['id' => $routeId, 'rid' => $residencialId]);
    return (bool)$stmt->fetchColumn();
}

function rondines_point_belongs(PDO $pdo, int $residencialId, int $pointId): bool
{
    if ($pointId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM rondines_puntos WHERE id = :id AND residencial_id = :rid LIMIT 1");
    $stmt->execute(['id' => $pointId, 'rid' => $residencialId]);
    return (bool)$stmt->fetchColumn();
}

function rondines_normalize_point(array $row): array
{
    $row['id'] = (int)$row['id'];
    $row['residencial_id'] = (int)$row['residencial_id'];
    $row['area_id'] = $row['area_id'] !== null ? (int)$row['area_id'] : null;
    $row['radio_metros'] = (int)$row['radio_metros'];
    $row['requiere_foto'] = (int)$row['requiere_foto'];
    $row['requiere_observacion'] = (int)$row['requiere_observacion'];
    $row['activo'] = (int)$row['activo'];
    $row['latitud'] = $row['latitud'] !== null ? (float)$row['latitud'] : null;
    $row['longitud'] = $row['longitud'] !== null ? (float)$row['longitud'] : null;
    $row['qr_payload'] = rondines_qr_payload((string)$row['codigo_qr']);
    $row['rutas_count'] = (int)($row['rutas_count'] ?? 0);
    return $row;
}

function rondines_list_routes(PDO $pdo, int $residencialId): array
{
    $stmt = $pdo->prepare("
        SELECT
            r.*,
            u.name AS created_by_nombre,
            COALESCE(pc.puntos_count, 0) AS puntos_count
        FROM rondines_rutas r
        LEFT JOIN users u ON u.id = r.created_by
        LEFT JOIN (
            SELECT ruta_id, COUNT(*) AS puntos_count
            FROM rondines_ruta_puntos
            GROUP BY ruta_id
        ) pc ON pc.ruta_id = r.id
        WHERE r.residencial_id = :rid
        ORDER BY r.activo DESC, r.nombre ASC
    ");
    $stmt->execute(['rid' => $residencialId]);
    return array_map(static function (array $row): array {
        $row['id'] = (int)$row['id'];
        $row['residencial_id'] = (int)$row['residencial_id'];
        $row['activo'] = (int)$row['activo'];
        $row['created_by'] = $row['created_by'] !== null ? (int)$row['created_by'] : null;
        $row['puntos_count'] = (int)($row['puntos_count'] ?? 0);
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function rondines_list_points(PDO $pdo, int $residencialId): array
{
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            a.nombre AS area_nombre,
            COALESCE(rc.rutas_count, 0) AS rutas_count
        FROM rondines_puntos p
        LEFT JOIN areas_operativas a ON a.id = p.area_id AND a.residencial_id = p.residencial_id
        LEFT JOIN (
            SELECT punto_id, COUNT(*) AS rutas_count
            FROM rondines_ruta_puntos
            GROUP BY punto_id
        ) rc ON rc.punto_id = p.id
        WHERE p.residencial_id = :rid
        ORDER BY p.activo DESC, p.nombre ASC
    ");
    $stmt->execute(['rid' => $residencialId]);
    return array_map('rondines_normalize_point', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function rondines_list_route_points(PDO $pdo, int $residencialId, int $routeId): array
{
    $stmt = $pdo->prepare("
        SELECT p.*, a.nombre AS area_nombre, rp.orden, 1 AS rutas_count
        FROM rondines_ruta_puntos rp
        JOIN rondines_puntos p ON p.id = rp.punto_id
        LEFT JOIN areas_operativas a ON a.id = p.area_id AND a.residencial_id = p.residencial_id
        WHERE rp.ruta_id = :route_id
          AND p.residencial_id = :rid
        ORDER BY rp.orden ASC, p.nombre ASC
    ");
    $stmt->execute(['route_id' => $routeId, 'rid' => $residencialId]);
    return array_map('rondines_normalize_point', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function rondines_history(PDO $pdo, int $residencialId, int $limit = 80): array
{
    $stmt = $pdo->prepare("
        SELECT
            e.*,
            r.nombre AS ruta_nombre,
            g.name AS guardia_nombre,
            COALESCE(total.total_puntos, 0) AS puntos_totales,
            COALESCE(ev.puntos_escaneados, 0) AS puntos_escaneados,
            COALESCE(ev.anomalias, 0) AS anomalias
        FROM rondines_ejecuciones e
        JOIN rondines_rutas r ON r.id = e.ruta_id
        LEFT JOIN users g ON g.id = e.guardia_id
        LEFT JOIN (
            SELECT ruta_id, COUNT(*) AS total_puntos
            FROM rondines_ruta_puntos
            GROUP BY ruta_id
        ) total ON total.ruta_id = e.ruta_id
        LEFT JOIN (
            SELECT ejecucion_id,
                   COUNT(*) AS puntos_escaneados,
                   SUM(CASE WHEN estado = 'anomalia' THEN 1 ELSE 0 END) AS anomalias
            FROM rondines_eventos
            GROUP BY ejecucion_id
        ) ev ON ev.ejecucion_id = e.id
        WHERE e.residencial_id = :rid
        ORDER BY e.inicio_at DESC, e.id DESC
        LIMIT " . max(1, min(200, $limit)) . "
    ");
    $stmt->execute(['rid' => $residencialId]);
    return array_map(static function (array $row): array {
        $total = (int)($row['puntos_totales'] ?? 0);
        $scanned = (int)($row['puntos_escaneados'] ?? 0);
        $row['id'] = (int)$row['id'];
        $row['ruta_id'] = (int)$row['ruta_id'];
        $row['guardia_id'] = $row['guardia_id'] !== null ? (int)$row['guardia_id'] : null;
        $row['puntos_totales'] = $total;
        $row['puntos_escaneados'] = $scanned;
        $row['anomalias'] = (int)($row['anomalias'] ?? 0);
        $row['cumplimiento'] = $total > 0 ? round(($scanned / $total) * 100, 1) : 0;
        $row['duracion_minutos'] = null;
        if (!empty($row['fin_at']) && !empty($row['inicio_at'])) {
            $row['duracion_minutos'] = max(0, (int)round((strtotime((string)$row['fin_at']) - strtotime((string)$row['inicio_at'])) / 60));
        }
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = clean_str((string)($_GET['action'] ?? $_POST['action'] ?? 'rutas'));

    if ($method === 'GET' && $action === 'meta') {
        json_out(true, [
            'data' => [
                'areas' => operational_area_options($pdo, $residencialId),
                'context' => admin_operational_context(),
                'qr_prefix' => 'op:round_point:',
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'rutas') {
        json_out(true, ['data' => ['items' => rondines_list_routes($pdo, $residencialId)]]);
    }

    if ($method === 'GET' && $action === 'puntos') {
        json_out(true, ['data' => ['items' => rondines_list_points($pdo, $residencialId)]]);
    }

    if ($method === 'GET' && $action === 'historial') {
        json_out(true, ['data' => ['items' => rondines_history($pdo, $residencialId)]]);
    }

    if ($method === 'GET' && $action === 'detalle_ruta') {
        $rutaId = (int)($_GET['ruta_id'] ?? 0);
        if (!rondines_route_belongs($pdo, $residencialId, $rutaId)) {
            json_out(false, ['error' => 'Ruta no encontrada.'], 404);
        }
        json_out(true, [
            'data' => [
                'puntos' => rondines_list_route_points($pdo, $residencialId, $rutaId),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'detalle') {
        $ejecucionId = (int)($_GET['ejecucion_id'] ?? 0);
        if ($ejecucionId <= 0) {
            json_out(false, ['error' => 'Ejecución inválida.'], 422);
        }
        $stmt = $pdo->prepare("
            SELECT e.*, r.nombre AS ruta_nombre, r.descripcion AS ruta_descripcion, g.name AS guardia_nombre
            FROM rondines_ejecuciones e
            JOIN rondines_rutas r ON r.id = e.ruta_id
            LEFT JOIN users g ON g.id = e.guardia_id
            WHERE e.id = :id
              AND e.residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute(['id' => $ejecucionId, 'rid' => $residencialId]);
        $ejecucion = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$ejecucion) {
            json_out(false, ['error' => 'Rondín no encontrado.'], 404);
        }
        $events = $pdo->prepare("
            SELECT ev.*, p.nombre AS punto_nombre, p.codigo_qr, a.nombre AS area_nombre
            FROM rondines_eventos ev
            JOIN rondines_puntos p ON p.id = ev.punto_id
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            WHERE ev.ejecucion_id = :eid
              AND ev.residencial_id = :rid
            ORDER BY ev.escaneado_at ASC, ev.id ASC
        ");
        $events->execute(['eid' => $ejecucionId, 'rid' => $residencialId]);
        json_out(true, [
            'data' => [
                'ejecucion' => $ejecucion,
                'puntos' => rondines_list_route_points($pdo, $residencialId, (int)$ejecucion['ruta_id']),
                'eventos' => $events->fetchAll(PDO::FETCH_ASSOC) ?: [],
            ],
        ]);
    }

    if ($method !== 'POST') {
        json_out(false, ['error' => 'Acción no válida.'], 400);
    }

    if ($action === 'save_ruta') {
        $id = (int)($_POST['id'] ?? 0);
        $nombre = clean_str($_POST['nombre'] ?? '');
        $descripcion = clean_str($_POST['descripcion'] ?? '');
        $activo = isset($_POST['activo']) ? rondines_bool($_POST['activo']) : 1;
        if ($nombre === '') {
            json_out(false, ['error' => 'El nombre de la ruta es obligatorio.'], 422);
        }

        if ($id > 0) {
            if (!rondines_route_belongs($pdo, $residencialId, $id)) {
                json_out(false, ['error' => 'Ruta no encontrada.'], 404);
            }
            $stmt = $pdo->prepare("
                UPDATE rondines_rutas
                SET nombre = :nombre, descripcion = :descripcion, activo = :activo, updated_at = NOW()
                WHERE id = :id AND residencial_id = :rid
                LIMIT 1
            ");
            $stmt->execute([
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'activo' => $activo,
                'id' => $id,
                'rid' => $residencialId,
            ]);
            json_out(true, ['message' => 'Ruta actualizada correctamente.']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO rondines_rutas (residencial_id, nombre, descripcion, activo, created_by, created_at, updated_at)
            VALUES (:rid, :nombre, :descripcion, :activo, :created_by, NOW(), NOW())
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'activo' => $activo,
            'created_by' => $adminId > 0 ? $adminId : null,
        ]);
        json_out(true, ['message' => 'Ruta creada correctamente.']);
    }

    if ($action === 'toggle_ruta') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = rondines_bool($_POST['activo'] ?? 0);
        if (!rondines_route_belongs($pdo, $residencialId, $id)) {
            json_out(false, ['error' => 'Ruta no encontrada.'], 404);
        }
        $stmt = $pdo->prepare("UPDATE rondines_rutas SET activo = :activo, updated_at = NOW() WHERE id = :id AND residencial_id = :rid LIMIT 1");
        $stmt->execute(['activo' => $activo, 'id' => $id, 'rid' => $residencialId]);
        json_out(true, ['message' => $activo ? 'Ruta activada.' : 'Ruta desactivada.']);
    }

    if ($action === 'save_punto') {
        $id = (int)($_POST['id'] ?? 0);
        $areaId = (int)($_POST['area_id'] ?? 0);
        $areaId = $areaId > 0 ? $areaId : null;
        if ($areaId !== null && !operational_validate_area($pdo, $residencialId, $areaId)) {
            json_out(false, ['error' => 'El área seleccionada no pertenece a este servicio.'], 422);
        }

        $nombre = clean_str($_POST['nombre'] ?? '');
        if ($nombre === '') {
            json_out(false, ['error' => 'El nombre del punto es obligatorio.'], 422);
        }
        $descripcion = clean_str($_POST['descripcion'] ?? '');
        $latitud = rondines_float_or_null($_POST['latitud'] ?? null);
        $longitud = rondines_float_or_null($_POST['longitud'] ?? null);
        $radio = max(5, min(5000, (int)($_POST['radio_metros'] ?? 50)));
        $requiereFoto = rondines_bool($_POST['requiere_foto'] ?? 0);
        $requiereObservacion = rondines_bool($_POST['requiere_observacion'] ?? 0);
        $activo = isset($_POST['activo']) ? rondines_bool($_POST['activo']) : 1;

        $params = [
            'rid' => $residencialId,
            'area_id' => $areaId,
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'latitud' => $latitud,
            'longitud' => $longitud,
            'radio_metros' => $radio,
            'requiere_foto' => $requiereFoto,
            'requiere_observacion' => $requiereObservacion,
            'activo' => $activo,
        ];

        if ($id > 0) {
            if (!rondines_point_belongs($pdo, $residencialId, $id)) {
                json_out(false, ['error' => 'Punto no encontrado.'], 404);
            }
            $params['id'] = $id;
            $stmt = $pdo->prepare("
                UPDATE rondines_puntos
                SET area_id = :area_id, nombre = :nombre, descripcion = :descripcion,
                    latitud = :latitud, longitud = :longitud, radio_metros = :radio_metros,
                    requiere_foto = :requiere_foto, requiere_observacion = :requiere_observacion,
                    activo = :activo, updated_at = NOW()
                WHERE id = :id AND residencial_id = :rid
                LIMIT 1
            ");
            $stmt->execute($params);
            json_out(true, ['message' => 'Punto actualizado correctamente.']);
        }

        $params['codigo_qr'] = rondines_new_point_code($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO rondines_puntos (
                residencial_id, area_id, nombre, descripcion, codigo_qr, latitud, longitud,
                radio_metros, requiere_foto, requiere_observacion, activo, created_at, updated_at
            ) VALUES (
                :rid, :area_id, :nombre, :descripcion, :codigo_qr, :latitud, :longitud,
                :radio_metros, :requiere_foto, :requiere_observacion, :activo, NOW(), NOW()
            )
        ");
        $stmt->execute($params);
        json_out(true, ['message' => 'Punto creado correctamente.']);
    }

    if ($action === 'toggle_punto') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = rondines_bool($_POST['activo'] ?? 0);
        if (!rondines_point_belongs($pdo, $residencialId, $id)) {
            json_out(false, ['error' => 'Punto no encontrado.'], 404);
        }
        $stmt = $pdo->prepare("UPDATE rondines_puntos SET activo = :activo, updated_at = NOW() WHERE id = :id AND residencial_id = :rid LIMIT 1");
        $stmt->execute(['activo' => $activo, 'id' => $id, 'rid' => $residencialId]);
        json_out(true, ['message' => $activo ? 'Punto activado.' : 'Punto desactivado.']);
    }

    if ($action === 'regenerate_punto_qr') {
        $id = (int)($_POST['id'] ?? 0);
        if (!rondines_point_belongs($pdo, $residencialId, $id)) {
            json_out(false, ['error' => 'Punto no encontrado.'], 404);
        }
        $code = rondines_new_point_code($pdo);
        $stmt = $pdo->prepare("UPDATE rondines_puntos SET codigo_qr = :code, updated_at = NOW() WHERE id = :id AND residencial_id = :rid LIMIT 1");
        $stmt->execute(['code' => $code, 'id' => $id, 'rid' => $residencialId]);
        json_out(true, ['message' => 'QR regenerado correctamente.', 'data' => ['codigo_qr' => $code, 'qr_payload' => rondines_qr_payload($code)]]);
    }

    if ($action === 'save_ruta_puntos') {
        $routeId = (int)($_POST['ruta_id'] ?? 0);
        if (!rondines_route_belongs($pdo, $residencialId, $routeId)) {
            json_out(false, ['error' => 'Ruta no encontrada.'], 404);
        }
        $raw = $_POST['puntos'] ?? '[]';
        $pointIds = is_array($raw) ? $raw : json_decode((string)$raw, true);
        if (!is_array($pointIds)) {
            json_out(false, ['error' => 'Lista de puntos inválida.'], 422);
        }
        $pointIds = array_values(array_unique(array_filter(array_map(static fn($v): int => (int)$v, $pointIds))));
        foreach ($pointIds as $pointId) {
            if (!rondines_point_belongs($pdo, $residencialId, $pointId)) {
                json_out(false, ['error' => 'Uno de los puntos no pertenece a este servicio.'], 422);
            }
        }

        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM rondines_ruta_puntos WHERE ruta_id = :rid")->execute(['rid' => $routeId]);
        $ins = $pdo->prepare("INSERT INTO rondines_ruta_puntos (ruta_id, punto_id, orden) VALUES (:ruta_id, :punto_id, :orden)");
        foreach ($pointIds as $index => $pointId) {
            $ins->execute(['ruta_id' => $routeId, 'punto_id' => $pointId, 'orden' => $index + 1]);
        }
        $pdo->commit();
        json_out(true, ['message' => 'Puntos de la ruta actualizados correctamente.']);
    }

    json_out(false, ['error' => 'Acción no válida.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar el módulo de rondines.');
}
