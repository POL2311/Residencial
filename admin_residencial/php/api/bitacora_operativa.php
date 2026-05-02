<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
admin_module_required('bitacora_operativa');

try {
    $desde = clean_str($_GET['desde'] ?? date('Y-m-d'));
    $hasta = clean_str($_GET['hasta'] ?? date('Y-m-d'));
    $tipo = clean_str($_GET['tipo_origen'] ?? '');
    $resultado = clean_str($_GET['resultado'] ?? '');

    $where = ['b.residencial_id = :rid', 'DATE(b.fecha_hora) BETWEEN :desde AND :hasta'];
    $params = [
        'rid' => $residencialId,
        'desde' => $desde,
        'hasta' => $hasta,
    ];

    if ($tipo !== '') {
        $where[] = 'b.tipo_origen = :tipo_origen';
        $params['tipo_origen'] = $tipo;
    }

    if ($resultado !== '') {
        $where[] = 'b.resultado = :resultado';
        $params['resultado'] = $resultado;
    }

    $stmt = $pdo->prepare("
        SELECT
            b.*,
            g.name AS guardia_nombre,
            p.nombre AS persona_nombre,
            vr.nombre_visitante,
            pm.tipo_movimiento AS permiso_tipo_movimiento,
            a.nombre AS area_nombre
        FROM bitacora_operativa b
        LEFT JOIN users g ON g.id = b.guardia_id
        LEFT JOIN personas_recurrentes p ON p.id = b.persona_recurrente_id
        LEFT JOIN visitantes_rapidos vr ON vr.id = b.visitante_rapido_id
        LEFT JOIN permisos_materiales pm ON pm.id = b.permiso_material_id
        LEFT JOIN areas_operativas a ON a.id = b.area_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.fecha_hora DESC, b.id DESC
        LIMIT 300
    ");
    $stmt->execute($params);

    $items = array_map(static function (array $row): array {
        $meta = [];
        if (!empty($row['metadata_json'])) {
            $decoded = json_decode((string)$row['metadata_json'], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        return [
            'id' => (int)$row['id'],
            'tipo_origen' => (string)$row['tipo_origen'],
            'tipo_evento' => (string)$row['tipo_evento'],
            'resultado' => (string)$row['resultado'],
            'observaciones' => (string)($row['observaciones'] ?? ''),
            'fecha_hora' => (string)$row['fecha_hora'],
            'guardia_nombre' => (string)($row['guardia_nombre'] ?? ''),
            'persona_nombre' => (string)($row['persona_nombre'] ?? ''),
            'nombre_visitante' => (string)($row['nombre_visitante'] ?? ''),
            'permiso_tipo_movimiento' => (string)($row['permiso_tipo_movimiento'] ?? ''),
            'area_nombre' => (string)($row['area_nombre'] ?? ''),
            'metadata' => $meta,
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

    $summary = [
        'total' => count($items),
        'permitidos' => count(array_filter($items, static fn(array $row): bool => $row['resultado'] === 'permitido')),
        'denegados' => count(array_filter($items, static fn(array $row): bool => $row['resultado'] === 'denegado')),
    ];

    json_out(true, [
        'data' => [
            'items' => $items,
            'summary' => $summary,
            'context' => admin_operational_context(),
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar la bitácora operativa.');
}
