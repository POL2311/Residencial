<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
require_once __DIR__ . '/../../../config/amenidades.php';

guardia_module_required('amenidades', 'Las amenidades no están habilitadas para este cliente.');
amenidades_schema_ensure($pdo);

try {
    $estado = amenidades_clean_str($_GET['estado'] ?? '', 20);
    $fecha = amenidades_clean_str($_GET['fecha'] ?? '', 20);

    $where = ['r.residencial_id = :rid'];
    $params = ['rid' => $residencialId];

    if ($estado !== '' && in_array($estado, amenidades_allowed_estados(), true)) {
        $where[] = 'r.estado = :estado';
        $params['estado'] = $estado;
    }

    if ($fecha !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $where[] = 'r.fecha = :fecha';
        $params['fecha'] = $fecha;
    } else {
        $where[] = 'r.fecha >= CURDATE()';
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
        ORDER BY r.fecha ASC, r.hora_inicio ASC, r.id DESC
        LIMIT 200
    ");
    $stmt->execute($params);

    json_out(true, [
        'data' => [
            'items' => array_map('amenidades_normalize_reserva', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []),
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar las reservas de amenidades.');
}

