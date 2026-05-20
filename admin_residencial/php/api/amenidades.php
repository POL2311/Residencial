<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
require_once __DIR__ . '/../../../config/amenidades.php';

admin_module_required('amenidades', 'El módulo de amenidades no está habilitado para este cliente.');
amenidades_schema_ensure($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function admin_amenidad_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'nombre' => (string)$row['nombre'],
        'descripcion' => (string)($row['descripcion'] ?? ''),
        'ubicacion' => (string)($row['ubicacion'] ?? ''),
        'capacidad' => $row['capacidad'] !== null ? (int)$row['capacidad'] : null,
        'activo' => (int)$row['activo'],
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}

function admin_list_amenidades(PDO $pdo, int $rid): array
{
    $stmt = $pdo->prepare("
        SELECT *
        FROM amenidades
        WHERE residencial_id = :rid
        ORDER BY activo DESC, nombre ASC, id ASC
    ");
    $stmt->execute(['rid' => $rid]);
    return array_map('admin_amenidad_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

function admin_list_reservas(PDO $pdo, int $rid): array
{
    $estado = amenidades_clean_str($_GET['estado'] ?? '', 20);
    $fechaDesde = amenidades_clean_str($_GET['fecha_desde'] ?? '', 20);
    $fechaHasta = amenidades_clean_str($_GET['fecha_hasta'] ?? '', 20);

    $where = ['r.residencial_id = :rid'];
    $params = ['rid' => $rid];

    if ($estado !== '' && in_array($estado, amenidades_allowed_estados(), true)) {
        $where[] = 'r.estado = :estado';
        $params['estado'] = $estado;
    }
    if ($fechaDesde !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
        $where[] = 'r.fecha >= :fecha_desde';
        $params['fecha_desde'] = $fechaDesde;
    }
    if ($fechaHasta !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
        $where[] = 'r.fecha <= :fecha_hasta';
        $params['fecha_hasta'] = $fechaHasta;
    }

    $stmt = $pdo->prepare("
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
        WHERE " . implode(' AND ', $where) . "
        ORDER BY
            CASE r.estado
                WHEN 'pendiente' THEN 1
                WHEN 'aprobada' THEN 2
                WHEN 'rechazada' THEN 3
                WHEN 'cancelada' THEN 4
                ELSE 5
            END,
            r.fecha ASC,
            r.hora_inicio ASC,
            r.id DESC
        LIMIT 300
    ");
    $stmt->execute($params);

    return array_map('amenidades_normalize_reserva', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
}

try {
    if ($method === 'GET') {
        $action = amenidades_clean_str($_GET['action'] ?? 'list', 40);
        json_out(true, [
            'data' => [
                'amenidades' => admin_list_amenidades($pdo, $residencialId),
                'reservas' => $action === 'catalogo' ? [] : admin_list_reservas($pdo, $residencialId),
            ],
        ]);
    }

    $action = amenidades_clean_str($_POST['action'] ?? 'save_amenidad', 40);

    if ($action === 'review') {
        $id = (int)($_POST['id'] ?? 0);
        $decision = amenidades_clean_str($_POST['decision'] ?? '', 20);
        $notasAdmin = amenidades_clean_str($_POST['notas_admin'] ?? '', 1000);

        if ($id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
            json_out(false, ['error' => 'Solicitud inválida.']);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            SELECT *
            FROM amenidad_reservas
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['id' => $id, 'rid' => $residencialId]);
        $reserva = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$reserva) {
            $pdo->rollBack();
            json_out(false, ['error' => 'No encontramos esa solicitud.']);
        }
        if ((string)$reserva['estado'] !== 'pendiente') {
            $pdo->rollBack();
            json_out(false, ['error' => 'Solo puedes revisar solicitudes pendientes.']);
        }

        $nuevoEstado = $decision === 'approve' ? 'aprobada' : 'rechazada';
        if ($nuevoEstado === 'aprobada' && amenidades_has_overlap(
            $pdo,
            (int)$reserva['amenidad_id'],
            (string)$reserva['fecha'],
            substr((string)$reserva['hora_inicio'], 0, 5),
            substr((string)$reserva['hora_fin'], 0, 5),
            $id
        )) {
            $pdo->rollBack();
            json_out(false, ['error' => 'Ya existe una reserva aprobada para ese horario.']);
        }

        $up = $pdo->prepare("
            UPDATE amenidad_reservas
            SET estado = :estado,
                notas_admin = :notas_admin,
                revisado_por_user_id = :admin_id,
                revisado_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $up->execute([
            'estado' => $nuevoEstado,
            'notas_admin' => $notasAdmin !== '' ? $notasAdmin : null,
            'admin_id' => $adminId,
            'id' => $id,
            'rid' => $residencialId,
        ]);
        $pdo->commit();

        $updatedReserva = amenidades_fetch_reserva($pdo, $id, $residencialId);
        json_out(true, [
            'message' => $nuevoEstado === 'aprobada' ? 'Reserva aprobada correctamente.' : 'Reserva rechazada correctamente.',
            'data' => ['reserva' => $updatedReserva ? amenidades_normalize_reserva($updatedReserva) : null],
        ]);
    }

    $id = (int)($_POST['id'] ?? 0);
    $nombre = amenidades_clean_str($_POST['nombre'] ?? '', 150);
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $ubicacion = amenidades_clean_str($_POST['ubicacion'] ?? '', 150);
    $capacidadRaw = amenidades_clean_str($_POST['capacidad'] ?? '', 20);
    $capacidad = $capacidadRaw !== '' ? max(0, (int)$capacidadRaw) : null;
    $activo = isset($_POST['activo']) ? (int)((string)$_POST['activo'] === '1') : 1;

    if ($nombre === '') {
        json_out(false, ['error' => 'El nombre de la amenidad es obligatorio.']);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE amenidades
            SET nombre = :nombre,
                descripcion = :descripcion,
                ubicacion = :ubicacion,
                capacidad = :capacidad,
                activo = :activo,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'ubicacion' => $ubicacion !== '' ? $ubicacion : null,
            'capacidad' => $capacidad,
            'activo' => $activo,
        ]);

        json_out(true, ['message' => 'Amenidad actualizada correctamente.']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO amenidades (
            residencial_id, nombre, descripcion, ubicacion, capacidad, activo, created_at, updated_at
        ) VALUES (
            :rid, :nombre, :descripcion, :ubicacion, :capacidad, :activo, NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $residencialId,
        'nombre' => $nombre,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'ubicacion' => $ubicacion !== '' ? $ubicacion : null,
        'capacidad' => $capacidad,
        'activo' => $activo,
    ]);

    json_out(true, ['message' => 'Amenidad creada correctamente.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar amenidades.');
}
