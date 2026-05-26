<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
guardia_module_required('rondines', 'El módulo de rondines no está habilitado para este cliente.');

function rondines_out(bool $ok, array $data = [], int $status = 200): void
{
    http_response_code($status);
    json_out($ok, $data);
}

function rondines_str(string $value, int $max = 255): string
{
    $value = clean_str($value);
    return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
}

function rondines_float_or_null(mixed $value): ?float
{
    $raw = trim((string)($value ?? ''));
    if ($raw === '' || !is_numeric($raw)) {
        return null;
    }
    return (float)$raw;
}

function rondines_payload_token(string $code): string
{
    $code = trim($code);
    $prefix = 'op:round_point:';
    if (str_starts_with($code, $prefix)) {
        return substr($code, strlen($prefix));
    }
    return $code;
}

function rondines_distance_m(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
{
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
        return null;
    }
    $earth = 6371000.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function rondines_route(PDO $pdo, int $rid, int $routeId, bool $onlyActive = false): ?array
{
    $sql = "
        SELECT r.*,
               COALESCE(points.total_puntos, 0) AS puntos_count
        FROM rondines_rutas r
        LEFT JOIN (
            SELECT ruta_id, COUNT(*) AS total_puntos
            FROM rondines_ruta_puntos
            GROUP BY ruta_id
        ) points ON points.ruta_id = r.id
        WHERE r.id = :id
          AND r.residencial_id = :rid
    ";
    if ($onlyActive) {
        $sql .= " AND r.activo = 1";
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $routeId, 'rid' => $rid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['puntos_count'] = (int)($row['puntos_count'] ?? 0);
    return $row;
}

function rondines_active_execution(PDO $pdo, int $rid, int $guardiaId): ?array
{
    $stmt = $pdo->prepare("
        SELECT e.*, r.nombre AS ruta_nombre
        FROM rondines_ejecuciones e
        JOIN rondines_rutas r ON r.id = e.ruta_id
        WHERE e.residencial_id = :rid
          AND e.guardia_id = :gid
          AND e.estado = 'en_proceso'
        ORDER BY e.inicio_at DESC, e.id DESC
        LIMIT 1
    ");
    $stmt->execute(['rid' => $rid, 'gid' => $guardiaId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['ruta_id'] = (int)$row['ruta_id'];
    return $row;
}

function rondines_route_points(PDO $pdo, int $rid, int $routeId): array
{
    $stmt = $pdo->prepare("
        SELECT p.*, a.nombre AS area_nombre, rp.orden
        FROM rondines_ruta_puntos rp
        JOIN rondines_puntos p ON p.id = rp.punto_id
        LEFT JOIN areas_operativas a ON a.id = p.area_id AND a.residencial_id = p.residencial_id
        WHERE rp.ruta_id = :route_id
          AND p.residencial_id = :rid
          AND p.activo = 1
        ORDER BY rp.orden ASC, p.nombre ASC
    ");
    $stmt->execute(['route_id' => $routeId, 'rid' => $rid]);
    return array_map(static function (array $row): array {
        $row['id'] = (int)$row['id'];
        $row['area_id'] = $row['area_id'] !== null ? (int)$row['area_id'] : null;
        $row['orden'] = (int)($row['orden'] ?? 0);
        $row['radio_metros'] = (int)($row['radio_metros'] ?? 50);
        $row['requiere_foto'] = (int)$row['requiere_foto'];
        $row['requiere_observacion'] = (int)$row['requiere_observacion'];
        $row['latitud'] = $row['latitud'] !== null ? (float)$row['latitud'] : null;
        $row['longitud'] = $row['longitud'] !== null ? (float)$row['longitud'] : null;
        $row['qr_payload'] = 'op:round_point:' . (string)$row['codigo_qr'];
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function rondines_execution_events(PDO $pdo, int $rid, int $executionId): array
{
    $stmt = $pdo->prepare("
        SELECT ev.*, p.nombre AS punto_nombre, p.codigo_qr, a.nombre AS area_nombre
        FROM rondines_eventos ev
        JOIN rondines_puntos p ON p.id = ev.punto_id
        LEFT JOIN areas_operativas a ON a.id = p.area_id
        WHERE ev.ejecucion_id = :eid
          AND ev.residencial_id = :rid
        ORDER BY ev.escaneado_at ASC, ev.id ASC
    ");
    $stmt->execute(['eid' => $executionId, 'rid' => $rid]);
    return array_map(static function (array $row): array {
        $row['id'] = (int)$row['id'];
        $row['punto_id'] = (int)$row['punto_id'];
        $row['gps_valido'] = (int)$row['gps_valido'];
        $row['latitud'] = $row['latitud'] !== null ? (float)$row['latitud'] : null;
        $row['longitud'] = $row['longitud'] !== null ? (float)$row['longitud'] : null;
        return $row;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function rondines_execution_detail(PDO $pdo, int $rid, int $executionId): ?array
{
    $stmt = $pdo->prepare("
        SELECT e.*, r.nombre AS ruta_nombre, r.descripcion AS ruta_descripcion
        FROM rondines_ejecuciones e
        JOIN rondines_rutas r ON r.id = e.ruta_id
        WHERE e.id = :id
          AND e.residencial_id = :rid
        LIMIT 1
    ");
    $stmt->execute(['id' => $executionId, 'rid' => $rid]);
    $exec = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$exec) {
        return null;
    }
    $exec['id'] = (int)$exec['id'];
    $exec['ruta_id'] = (int)$exec['ruta_id'];
    $points = rondines_route_points($pdo, $rid, (int)$exec['ruta_id']);
    $events = rondines_execution_events($pdo, $rid, (int)$exec['id']);
    $scanned = array_column($events, null, 'punto_id');
    foreach ($points as &$point) {
        $point['evento'] = $scanned[$point['id']] ?? null;
    }
    unset($point);
    return [
        'ejecucion' => $exec,
        'puntos' => $points,
        'eventos' => $events,
        'summary' => [
            'puntos_totales' => count($points),
            'puntos_escaneados' => count($events),
            'anomalias' => count(array_filter($events, static fn(array $row): bool => $row['estado'] === 'anomalia')),
        ],
    ];
}

function rondines_save_file(PDO $pdo, int $rid, int $eventId, array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $processed = operational_process_image_upload($file, [
        'folder_prefix' => 'rondines',
        'quality' => 82,
        'preserve_text' => true,
        'max_long_side' => 1400,
    ]);
    $stmt = $pdo->prepare("
        INSERT INTO archivos_operativos (
            residencial_id, entidad_tipo, entidad_id, subtipo, storage_disk, storage_path, public_url,
            mime_type, size_bytes, width, height, created_at, updated_at
        ) VALUES (
            :rid, 'rondin_evento', :eid, 'evidencia', :disk, :path, :url,
            :mime, :size, :width, :height, NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $rid,
        'eid' => $eventId,
        'disk' => $processed['disk'],
        'path' => $processed['path'],
        'url' => $processed['public_url'],
        'mime' => $processed['mime_type'],
        'size' => (int)$processed['size_bytes'],
        'width' => (int)$processed['width'],
        'height' => (int)$processed['height'],
    ]);
    return (string)$processed['public_url'];
}

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = clean_str((string)($_GET['action'] ?? $_POST['action'] ?? 'rutas'));

    if ($method === 'GET' && $action === 'rutas') {
        $stmt = $pdo->prepare("
            SELECT r.*, COALESCE(points.total_puntos, 0) AS puntos_count
            FROM rondines_rutas r
            LEFT JOIN (
                SELECT ruta_id, COUNT(*) AS total_puntos
                FROM rondines_ruta_puntos
                GROUP BY ruta_id
            ) points ON points.ruta_id = r.id
            WHERE r.residencial_id = :rid
              AND r.activo = 1
            ORDER BY r.nombre ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        rondines_out(true, [
            'data' => [
                'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                'activo' => rondines_active_execution($pdo, $residencialId, $guardiaId),
                'context' => guardia_operational_context(),
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'activo') {
        $active = rondines_active_execution($pdo, $residencialId, $guardiaId);
        rondines_out(true, [
            'data' => [
                'activo' => $active,
                'detalle' => $active ? rondines_execution_detail($pdo, $residencialId, (int)$active['id']) : null,
            ],
        ]);
    }

    if ($method === 'GET' && $action === 'detalle') {
        $executionId = (int)($_GET['ejecucion_id'] ?? 0);
        $routeId = (int)($_GET['ruta_id'] ?? 0);
        if ($executionId > 0) {
            $detail = rondines_execution_detail($pdo, $residencialId, $executionId);
            if (!$detail) {
                rondines_out(false, ['error' => 'Rondín no encontrado.'], 404);
            }
            rondines_out(true, ['data' => $detail]);
        }
        $route = rondines_route($pdo, $residencialId, $routeId, true);
        if (!$route) {
            rondines_out(false, ['error' => 'Ruta no encontrada.'], 404);
        }
        rondines_out(true, ['data' => ['ruta' => $route, 'puntos' => rondines_route_points($pdo, $residencialId, $routeId)]]);
    }

    if ($method !== 'POST') {
        rondines_out(false, ['error' => 'Acción no válida.'], 400);
    }

    if ($action === 'iniciar') {
        $routeId = (int)($_POST['ruta_id'] ?? 0);
        $route = rondines_route($pdo, $residencialId, $routeId, true);
        if (!$route || (int)$route['puntos_count'] <= 0) {
            rondines_out(false, ['error' => 'Selecciona una ruta activa con puntos.'], 422);
        }
        if (rondines_active_execution($pdo, $residencialId, $guardiaId)) {
            rondines_out(false, ['error' => 'Ya tienes un rondín en proceso.'], 409);
        }
        $lat = rondines_float_or_null($_POST['latitud'] ?? null);
        $lng = rondines_float_or_null($_POST['longitud'] ?? null);
        $acc = rondines_float_or_null($_POST['precision_metros'] ?? null);
        $stmt = $pdo->prepare("
            INSERT INTO rondines_ejecuciones (
                residencial_id, ruta_id, guardia_id, estado, inicio_at,
                latitud_inicio, longitud_inicio, precision_inicio_metros, created_at, updated_at
            ) VALUES (
                :rid, :ruta_id, :guardia_id, 'en_proceso', NOW(),
                :lat, :lng, :acc, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'ruta_id' => $routeId,
            'guardia_id' => $guardiaId,
            'lat' => $lat,
            'lng' => $lng,
            'acc' => $acc,
        ]);
        $executionId = (int)$pdo->lastInsertId();
        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'rondin',
            'origen_id' => $executionId,
            'tipo_evento' => 'rondin_iniciado',
            'resultado' => 'permitido',
            'observaciones' => 'Rondín iniciado: ' . (string)$route['nombre'],
            'metadata_json' => [
                'ruta_id' => $routeId,
                'ejecucion_id' => $executionId,
                'latitud' => $lat,
                'longitud' => $lng,
                'precision_metros' => $acc,
            ],
        ]);
        rondines_out(true, ['message' => 'Rondín iniciado.', 'data' => rondines_execution_detail($pdo, $residencialId, $executionId)]);
    }

    if ($action === 'registrar_punto') {
        $executionId = (int)($_POST['ejecucion_id'] ?? 0);
        $code = rondines_payload_token((string)($_POST['codigo_qr'] ?? $_POST['code'] ?? ''));
        $estado = rondines_str((string)($_POST['estado'] ?? 'correcto'), 30);
        $estado = in_array($estado, ['correcto', 'anomalia'], true) ? $estado : 'correcto';
        $observacion = rondines_str((string)($_POST['observacion'] ?? ''), 2000);

        $detail = rondines_execution_detail($pdo, $residencialId, $executionId);
        if (!$detail || ($detail['ejecucion']['estado'] ?? '') !== 'en_proceso' || (int)($detail['ejecucion']['guardia_id'] ?? 0) !== $guardiaId) {
            rondines_out(false, ['error' => 'No encontramos un rondín activo válido.'], 404);
        }

        $stmtPoint = $pdo->prepare("
            SELECT p.*, rp.ruta_id
            FROM rondines_puntos p
            JOIN rondines_ruta_puntos rp ON rp.punto_id = p.id
            WHERE p.codigo_qr = :code
              AND p.residencial_id = :rid
              AND rp.ruta_id = :ruta_id
              AND p.activo = 1
            LIMIT 1
        ");
        $stmtPoint->execute([
            'code' => $code,
            'rid' => $residencialId,
            'ruta_id' => (int)$detail['ejecucion']['ruta_id'],
        ]);
        $point = $stmtPoint->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$point) {
            rondines_out(false, ['error' => 'El QR no pertenece a esta ruta o servicio.'], 422);
        }

        foreach ($detail['eventos'] as $event) {
            if ((int)$event['punto_id'] === (int)$point['id']) {
                rondines_out(false, ['error' => 'Este punto ya fue registrado en el rondín.'], 409);
            }
        }
        if ((int)$point['requiere_observacion'] === 1 && $observacion === '') {
            rondines_out(false, ['error' => 'Este punto requiere observación.'], 422);
        }
        if ((int)$point['requiere_foto'] === 1 && (($_FILES['evidencia']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            rondines_out(false, ['error' => 'Este punto requiere evidencia fotográfica.'], 422);
        }

        $lat = rondines_float_or_null($_POST['latitud'] ?? null);
        $lng = rondines_float_or_null($_POST['longitud'] ?? null);
        $acc = rondines_float_or_null($_POST['precision_metros'] ?? null);
        $distance = rondines_distance_m($lat, $lng, $point['latitud'] !== null ? (float)$point['latitud'] : null, $point['longitud'] !== null ? (float)$point['longitud'] : null);
        $gpsOk = $lat !== null && $lng !== null;
        if ($gpsOk && $acc !== null && $acc > 100) {
            $gpsOk = false;
        }
        if ($gpsOk && $distance !== null && $distance > (float)$point['radio_metros']) {
            $gpsOk = false;
        }

        $metadata = [
            'ruta_id' => (int)$detail['ejecucion']['ruta_id'],
            'punto_id' => (int)$point['id'],
            'ejecucion_id' => $executionId,
            'guardia_id' => $guardiaId,
            'area_id' => $point['area_id'] !== null ? (int)$point['area_id'] : null,
            'latitud' => $lat,
            'longitud' => $lng,
            'precision_metros' => $acc,
            'distancia_punto_metros' => $distance !== null ? round($distance, 2) : null,
            'gps_valido' => $gpsOk ? 1 : 0,
            'estado' => $estado,
        ];

        $stmt = $pdo->prepare("
            INSERT INTO rondines_eventos (
                ejecucion_id, residencial_id, punto_id, guardia_id, escaneado_at, estado,
                latitud, longitud, precision_metros, distancia_punto_metros, gps_valido,
                observacion, metadata_json, created_at
            ) VALUES (
                :eid, :rid, :pid, :gid, NOW(), :estado,
                :lat, :lng, :acc, :dist, :gps, :obs, :meta, NOW()
            )
        ");
        $stmt->execute([
            'eid' => $executionId,
            'rid' => $residencialId,
            'pid' => (int)$point['id'],
            'gid' => $guardiaId,
            'estado' => $estado,
            'lat' => $lat,
            'lng' => $lng,
            'acc' => $acc,
            'dist' => $distance !== null ? round($distance, 2) : null,
            'gps' => $gpsOk ? 1 : 0,
            'obs' => $observacion !== '' ? $observacion : null,
            'meta' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
        $eventId = (int)$pdo->lastInsertId();
        $evidenceUrl = null;
        if (isset($_FILES['evidencia'])) {
            $evidenceUrl = rondines_save_file($pdo, $residencialId, $eventId, $_FILES['evidencia']);
            if ($evidenceUrl) {
                $pdo->prepare("UPDATE rondines_eventos SET evidencia_url = :url WHERE id = :id LIMIT 1")->execute(['url' => $evidenceUrl, 'id' => $eventId]);
                $metadata['evidencia_url'] = $evidenceUrl;
            }
        }

        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'rondin',
            'origen_id' => $executionId,
            'tipo_evento' => $estado === 'anomalia' ? 'rondin_punto_anomalia' : 'rondin_punto_correcto',
            'resultado' => $estado === 'anomalia' ? 'alerta' : 'permitido',
            'area_id' => $point['area_id'] !== null ? (int)$point['area_id'] : null,
            'observaciones' => ($estado === 'anomalia' ? 'Anomalía' : 'Punto correcto') . ': ' . (string)$point['nombre'],
            'metadata_json' => $metadata,
        ]);

        rondines_out(true, [
            'message' => $gpsOk ? 'Punto registrado.' : 'Punto registrado con advertencia de GPS.',
            'warning' => $gpsOk ? '' : 'GPS sin validar, fuera de radio o con baja precisión.',
            'data' => rondines_execution_detail($pdo, $residencialId, $executionId),
        ]);
    }

    if ($action === 'finalizar') {
        $executionId = (int)($_POST['ejecucion_id'] ?? 0);
        $detail = rondines_execution_detail($pdo, $residencialId, $executionId);
        if (!$detail || ($detail['ejecucion']['estado'] ?? '') !== 'en_proceso' || (int)($detail['ejecucion']['guardia_id'] ?? 0) !== $guardiaId) {
            rondines_out(false, ['error' => 'No encontramos un rondín activo válido.'], 404);
        }
        $summary = $detail['summary'];
        $finalState = (int)$summary['puntos_escaneados'] >= (int)$summary['puntos_totales'] ? 'completado' : 'incompleto';
        $lat = rondines_float_or_null($_POST['latitud'] ?? null);
        $lng = rondines_float_or_null($_POST['longitud'] ?? null);
        $acc = rondines_float_or_null($_POST['precision_metros'] ?? null);
        $obs = rondines_str((string)($_POST['observaciones'] ?? ''), 2000);
        $stmt = $pdo->prepare("
            UPDATE rondines_ejecuciones
            SET estado = :estado, fin_at = NOW(), latitud_fin = :lat, longitud_fin = :lng,
                precision_fin_metros = :acc, observaciones = :obs, updated_at = NOW()
            WHERE id = :id AND residencial_id = :rid AND guardia_id = :gid
            LIMIT 1
        ");
        $stmt->execute([
            'estado' => $finalState,
            'lat' => $lat,
            'lng' => $lng,
            'acc' => $acc,
            'obs' => $obs !== '' ? $obs : null,
            'id' => $executionId,
            'rid' => $residencialId,
            'gid' => $guardiaId,
        ]);
        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'rondin',
            'origen_id' => $executionId,
            'tipo_evento' => $finalState === 'completado' ? 'rondin_finalizado' : 'rondin_incompleto',
            'resultado' => $finalState === 'completado' ? 'permitido' : 'alerta',
            'observaciones' => 'Rondín ' . $finalState . '.',
            'metadata_json' => [
                'ejecucion_id' => $executionId,
                'ruta_id' => (int)$detail['ejecucion']['ruta_id'],
                'puntos_totales' => (int)$summary['puntos_totales'],
                'puntos_escaneados' => (int)$summary['puntos_escaneados'],
                'latitud' => $lat,
                'longitud' => $lng,
                'precision_metros' => $acc,
                'estado' => $finalState,
            ],
        ]);
        rondines_out(true, ['message' => $finalState === 'completado' ? 'Rondín finalizado.' : 'Rondín finalizado como incompleto.', 'data' => rondines_execution_detail($pdo, $residencialId, $executionId)]);
    }

    if ($action === 'cancelar') {
        $executionId = (int)($_POST['ejecucion_id'] ?? 0);
        $detail = rondines_execution_detail($pdo, $residencialId, $executionId);
        if (!$detail || ($detail['ejecucion']['estado'] ?? '') !== 'en_proceso' || (int)($detail['ejecucion']['guardia_id'] ?? 0) !== $guardiaId) {
            rondines_out(false, ['error' => 'No encontramos un rondín activo válido.'], 404);
        }
        $motivo = rondines_str((string)($_POST['motivo'] ?? ''), 2000);
        $stmt = $pdo->prepare("
            UPDATE rondines_ejecuciones
            SET estado = 'cancelado', fin_at = NOW(), observaciones = :motivo, updated_at = NOW()
            WHERE id = :id AND residencial_id = :rid AND guardia_id = :gid
            LIMIT 1
        ");
        $stmt->execute(['motivo' => $motivo !== '' ? $motivo : null, 'id' => $executionId, 'rid' => $residencialId, 'gid' => $guardiaId]);
        operational_write_bitacora($pdo, [
            'residencial_id' => $residencialId,
            'guardia_id' => $guardiaId,
            'tipo_origen' => 'rondin',
            'origen_id' => $executionId,
            'tipo_evento' => 'rondin_cancelado',
            'resultado' => 'alerta',
            'observaciones' => $motivo !== '' ? $motivo : 'Rondín cancelado.',
            'metadata_json' => [
                'ejecucion_id' => $executionId,
                'ruta_id' => (int)$detail['ejecucion']['ruta_id'],
                'estado' => 'cancelado',
            ],
        ]);
        rondines_out(true, ['message' => 'Rondín cancelado.']);
    }

    rondines_out(false, ['error' => 'Acción no válida.'], 400);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar rondines.');
}
