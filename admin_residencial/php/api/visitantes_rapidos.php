<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

admin_operational_required();
admin_module_required('visitantes_rapidos');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function visitante_qr_payload(string $token): string
{
    return 'op:vr:' . $token;
}

try {
    if ($method === 'GET') {
        $action = clean_str($_GET['action'] ?? 'list');

        if ($action === 'meta') {
            json_out(true, [
                'data' => [
                    'areas' => operational_area_options($pdo, $residencialId),
                    'responsables' => operational_user_options($pdo, $residencialId),
                    'context' => admin_operational_context(),
                ],
            ]);
        }

        $stmt = $pdo->prepare("
            SELECT
                v.*,
                a.nombre AS area_nombre,
                u.name AS responsable_nombre
            FROM visitantes_rapidos v
            LEFT JOIN areas_operativas a ON a.id = v.area_id
            LEFT JOIN users u ON u.id = v.responsable_user_id
            WHERE v.residencial_id = :rid
            ORDER BY
                CASE v.estado
                    WHEN 'pendiente' THEN 1
                    WHEN 'en_curso' THEN 2
                    WHEN 'finalizado' THEN 3
                    WHEN 'cancelado' THEN 4
                    ELSE 5
                END,
                v.created_at DESC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        json_out(true, [
            'data' => [
                'items' => array_map(static function (array $row): array {
                    return [
                        'id' => (int)$row['id'],
                        'nombre_visitante' => (string)$row['nombre_visitante'],
                        'empresa' => (string)($row['empresa'] ?? ''),
                        'placa_vehiculo' => (string)($row['placa_vehiculo'] ?? ''),
                        'motivo' => (string)($row['motivo'] ?? ''),
                        'estado' => (string)$row['estado'],
                        'notas_admin' => (string)($row['notas_admin'] ?? ''),
                        'fecha_desde' => (string)($row['fecha_desde'] ?? ''),
                        'fecha_hasta' => (string)($row['fecha_hasta'] ?? ''),
                        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
                        'area_nombre' => (string)($row['area_nombre'] ?? ''),
                        'responsable_user_id' => $row['responsable_user_id'] !== null ? (int)$row['responsable_user_id'] : null,
                        'responsable_nombre' => (string)($row['responsable_nombre'] ?? ''),
                        'qr_token' => (string)$row['qr_token'],
                        'qr_payload' => visitante_qr_payload((string)$row['qr_token']),
                        'created_at' => (string)($row['created_at'] ?? ''),
                    ];
                }, $items),
            ],
        ]);
    }

    $action = clean_str($_POST['action'] ?? 'save');

    if ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Acceso rápido inválido.']);
        }

        $stmt = $pdo->prepare("
            UPDATE visitantes_rapidos
            SET estado = 'cancelado',
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
        ]);

        json_out(true, ['message' => 'Acceso rápido cancelado.']);
    }

    $id = (int)($_POST['id'] ?? 0);
    $nombre = clean_str($_POST['nombre_visitante'] ?? '');
    $empresa = clean_str($_POST['empresa'] ?? '');
    $placa = clean_str($_POST['placa_vehiculo'] ?? '');
    $motivo = clean_str($_POST['motivo'] ?? '');
    $notas = clean_str($_POST['notas_admin'] ?? '');
    $fechaDesde = clean_str($_POST['fecha_desde'] ?? date('Y-m-d'));
    $fechaHasta = clean_str($_POST['fecha_hasta'] ?? date('Y-m-d'));
    $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;
    $responsableId = isset($_POST['responsable_user_id']) && $_POST['responsable_user_id'] !== '' ? (int)$_POST['responsable_user_id'] : null;

    if ($nombre === '' || $motivo === '') {
        json_out(false, ['error' => 'Nombre del visitante y motivo son obligatorios.']);
    }

    if ($fechaDesde === '' || $fechaHasta === '') {
        json_out(false, ['error' => 'Debes definir el rango de vigencia.']);
    }

    if ($areaId !== null && !operational_validate_area($pdo, $residencialId, $areaId)) {
        json_out(false, ['error' => 'El área seleccionada no pertenece a tu cliente.']);
    }

    if ($responsableId !== null && !operational_validate_internal_user($pdo, $residencialId, $responsableId)) {
        json_out(false, ['error' => 'El responsable seleccionado no pertenece a este cliente.']);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE visitantes_rapidos
            SET responsable_user_id = :responsable_user_id,
                area_id = :area_id,
                nombre_visitante = :nombre_visitante,
                empresa = :empresa,
                placa_vehiculo = :placa_vehiculo,
                motivo = :motivo,
                notas_admin = :notas_admin,
                fecha_desde = :fecha_desde,
                fecha_hasta = :fecha_hasta,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
            'responsable_user_id' => $responsableId,
            'area_id' => $areaId,
            'nombre_visitante' => $nombre,
            'empresa' => $empresa !== '' ? $empresa : null,
            'placa_vehiculo' => $placa !== '' ? strtoupper($placa) : null,
            'motivo' => $motivo,
            'notas_admin' => $notas !== '' ? $notas : null,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ]);

        json_out(true, ['message' => 'Acceso rápido actualizado correctamente.']);
    }

    $token = operational_generate_token(32);
    $stmt = $pdo->prepare("
        INSERT INTO visitantes_rapidos (
            residencial_id, responsable_user_id, area_id, nombre_visitante, empresa,
            placa_vehiculo, motivo, qr_token, estado, notas_admin, fecha_desde, fecha_hasta,
            created_at, updated_at
        ) VALUES (
            :rid, :responsable_user_id, :area_id, :nombre_visitante, :empresa,
            :placa_vehiculo, :motivo, :qr_token, 'pendiente', :notas_admin, :fecha_desde, :fecha_hasta,
            NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $residencialId,
        'responsable_user_id' => $responsableId,
        'area_id' => $areaId,
        'nombre_visitante' => $nombre,
        'empresa' => $empresa !== '' ? $empresa : null,
        'placa_vehiculo' => $placa !== '' ? strtoupper($placa) : null,
        'motivo' => $motivo,
        'qr_token' => $token,
        'notas_admin' => $notas !== '' ? $notas : null,
        'fecha_desde' => $fechaDesde,
        'fecha_hasta' => $fechaHasta,
    ]);

    json_out(true, [
        'message' => 'Acceso rápido creado correctamente.',
        'data' => [
            'qr_token' => $token,
            'qr_payload' => visitante_qr_payload($token),
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar los visitantes rápidos.');
}
