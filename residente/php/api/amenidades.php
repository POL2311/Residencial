<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';
require_once __DIR__ . '/../../../config/service_profile.php';
require_once __DIR__ . '/../../../config/amenidades.php';

require_login();
require_role(['residente']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_write_guard();
}

$uid = (int)(current_user()['id'] ?? 0);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = amenidades_clean_str($_POST['action'] ?? $_GET['action'] ?? 'list', 40);

function resident_amenidades_out(bool $ok, array $payload = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $payload), JSON_UNESCAPED_UNICODE);
    exit;
}

function resident_amenidades_context(PDO $pdo, int $uid): array
{
    $status = resolve_resident_context($pdo, $uid, true);
    if (!$status['ok']) {
        resident_amenidades_out(false, [
            'error' => $status['error'] ?? 'No se pudo resolver el contexto del residente.',
            'missing' => $status['missing'] ?? [],
        ], (int)($status['http_status'] ?? 403));
    }
    return $status['ctx'];
}

function resident_amenidad_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'nombre' => (string)$row['nombre'],
        'descripcion' => (string)($row['descripcion'] ?? ''),
        'ubicacion' => (string)($row['ubicacion'] ?? ''),
        'capacidad' => $row['capacidad'] !== null ? (int)$row['capacidad'] : null,
        'activo' => (int)$row['activo'],
    ];
}

try {
    amenidades_schema_ensure($pdo);
    $ctx = resident_amenidades_context($pdo, $uid);
    $rid = (int)$ctx['residencial_id'];
    $unidadId = (int)$ctx['unidad_id'];
    service_profile_api_require_module($pdo, $rid, 'residente', 'amenidades', 'Las amenidades no están habilitadas para este cliente.');

    if ($method === 'GET' || $action === 'list') {
        $stmtAmenities = $pdo->prepare("
            SELECT id, nombre, descripcion, ubicacion, capacidad, activo
            FROM amenidades
            WHERE residencial_id = :rid
              AND activo = 1
            ORDER BY nombre ASC, id ASC
        ");
        $stmtAmenities->execute(['rid' => $rid]);

        $stmtReservas = $pdo->prepare("
            SELECT
                r.*,
                a.nombre AS amenidad_nombre,
                a.ubicacion AS amenidad_ubicacion,
                u.clave AS unidad_clave,
                usr.name AS residente_nombre,
                rev.name AS revisado_por_nombre
            FROM amenidad_reservas r
            JOIN amenidades a ON a.id = r.amenidad_id
            JOIN unidades u ON u.id = r.unidad_id
            JOIN users usr ON usr.id = r.residente_id
            LEFT JOIN users rev ON rev.id = r.revisado_por_user_id
            WHERE r.residencial_id = :rid
              AND r.residente_id = :uid
              AND r.unidad_id = :unidad_id
            ORDER BY r.fecha DESC, r.hora_inicio DESC, r.id DESC
            LIMIT 200
        ");
        $stmtReservas->execute([
            'rid' => $rid,
            'uid' => $uid,
            'unidad_id' => $unidadId,
        ]);

        resident_amenidades_out(true, [
            'data' => [
                'ctx' => $ctx,
                'amenidades' => array_map('resident_amenidad_row', $stmtAmenities->fetchAll(PDO::FETCH_ASSOC) ?: []),
                'reservas' => array_map('amenidades_normalize_reserva', $stmtReservas->fetchAll(PDO::FETCH_ASSOC) ?: []),
            ],
        ]);
    }

    if ($action === 'create') {
        $amenidadId = (int)($_POST['amenidad_id'] ?? 0);
        $fecha = amenidades_clean_str($_POST['fecha'] ?? '', 20);
        $horaInicio = amenidades_clean_str($_POST['hora_inicio'] ?? '', 5);
        $horaFin = amenidades_clean_str($_POST['hora_fin'] ?? '', 5);
        $notas = trim((string)($_POST['notas_residente'] ?? ''));

        if ($amenidadId <= 0) {
            resident_amenidades_out(false, ['error' => 'Selecciona una amenidad.'], 422);
        }
        $timeError = amenidades_validate_time_range($fecha, $horaInicio, $horaFin);
        if ($timeError) {
            resident_amenidades_out(false, ['error' => $timeError], 422);
        }

        $stmtAmenidad = $pdo->prepare("
            SELECT id
            FROM amenidades
            WHERE id = :id
              AND residencial_id = :rid
              AND activo = 1
            LIMIT 1
        ");
        $stmtAmenidad->execute(['id' => $amenidadId, 'rid' => $rid]);
        if (!$stmtAmenidad->fetchColumn()) {
            resident_amenidades_out(false, ['error' => 'La amenidad no está disponible.'], 404);
        }

        if (amenidades_has_overlap($pdo, $amenidadId, $fecha, $horaInicio, $horaFin)) {
            resident_amenidades_out(false, ['error' => 'Ese horario ya está apartado. Elige otro horario.'], 422);
        }

        $stmt = $pdo->prepare("
            INSERT INTO amenidad_reservas (
                residencial_id, amenidad_id, residente_id, unidad_id, fecha, hora_inicio, hora_fin,
                estado, notas_residente, created_at, updated_at
            ) VALUES (
                :rid, :amenidad_id, :residente_id, :unidad_id, :fecha, :hora_inicio, :hora_fin,
                'pendiente', :notas_residente, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'rid' => $rid,
            'amenidad_id' => $amenidadId,
            'residente_id' => $uid,
            'unidad_id' => $unidadId,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'notas_residente' => $notas !== '' ? $notas : null,
        ]);

        resident_amenidades_out(true, ['message' => 'Solicitud enviada correctamente.']);
    }

    if ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            resident_amenidades_out(false, ['error' => 'Solicitud inválida.'], 422);
        }

        $stmt = $pdo->prepare("
            UPDATE amenidad_reservas
            SET estado = 'cancelada',
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
              AND residente_id = :uid
              AND unidad_id = :unidad_id
              AND estado = 'pendiente'
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $rid,
            'uid' => $uid,
            'unidad_id' => $unidadId,
        ]);

        if ($stmt->rowCount() < 1) {
            resident_amenidades_out(false, ['error' => 'Solo puedes cancelar solicitudes pendientes.'], 422);
        }

        resident_amenidades_out(true, ['message' => 'Solicitud cancelada correctamente.']);
    }

    resident_amenidades_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar amenidades.');
}

