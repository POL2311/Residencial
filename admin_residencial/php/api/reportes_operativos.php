<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
admin_module_required('reportes_operativos', 'Los reportes operativos no están habilitados para este cliente.');

function reportes_operativos_date(string $value, string $fallback): string
{
    $value = clean_str($value);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

function reportes_operativos_limit(mixed $value, int $default = 100, int $max = 200): int
{
    $limit = (int)($value ?? $default);
    if ($limit <= 0) {
        return $default;
    }
    return min($limit, $max);
}

function reportes_operativos_filters(PDO $pdo, int $residencialId): array
{
    $areas = [];
    if (tableExists($pdo, 'areas_operativas')) {
        $stmt = $pdo->prepare("
            SELECT id, nombre, codigo
            FROM areas_operativas
            WHERE residencial_id = :rid
              AND activo = 1
            ORDER BY nombre ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $areas = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'nombre' => (string)$row['nombre'],
            'codigo' => (string)($row['codigo'] ?? ''),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    $operadores = [];
    if (tableExists($pdo, 'users') && tableExists($pdo, 'usuarios_residenciales') && tableExists($pdo, 'tipos_usuario')) {
        $userCols = getCols($pdo, 'users');
        $activeCondition = in_array('is_active', $userCols, true) ? 'AND COALESCE(u.is_active, 1) = 1' : '';
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.name, u.email
            FROM users u
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE ur.residencial_id = :rid
              AND t.nombre = 'guardia'
              $activeCondition
            ORDER BY u.name ASC, u.email ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $operadores = array_map(static fn(array $row): array => [
            'id' => (int)$row['id'],
            'nombre' => (string)($row['name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    $tipos = [];
    if (tableExists($pdo, 'bitacora_operativa')) {
        $stmt = $pdo->prepare("
            SELECT tipo_origen, tipo_evento, COUNT(*) AS total
            FROM bitacora_operativa
            WHERE residencial_id = :rid
            GROUP BY tipo_origen, tipo_evento
            ORDER BY tipo_origen ASC, tipo_evento ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $tipos = array_map(static function (array $row): array {
            $origen = (string)($row['tipo_origen'] ?? '');
            $evento = (string)($row['tipo_evento'] ?? '');
            return [
                'value' => $origen . '::' . $evento,
                'label' => trim($origen . ' · ' . $evento, ' ·'),
                'tipo_origen' => $origen,
                'tipo_evento' => $evento,
                'total' => (int)($row['total'] ?? 0),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    return [
        'areas' => $areas,
        'operadores' => $operadores,
        'tipos_evento' => $tipos,
    ];
}

function reportes_operativos_query(PDO $pdo, int $residencialId, array $input = [], int $maxLimit = 200): array
{
    if (!tableExists($pdo, 'bitacora_operativa')) {
        return [
            'items' => [],
            'summary' => ['total' => 0, 'permitidos' => 0, 'denegados' => 0, 'otros' => 0],
            'pagination' => ['limit' => 0],
        ];
    }

    $today = date('Y-m-d');
    $desde = reportes_operativos_date((string)($input['desde'] ?? ''), $today);
    $hasta = reportes_operativos_date((string)($input['hasta'] ?? ''), $desde);
    if ($hasta < $desde) {
        [$desde, $hasta] = [$hasta, $desde];
    }

    $areaId = (int)($input['area_id'] ?? 0);
    $operadorId = (int)($input['operador_id'] ?? $input['guardia_id'] ?? 0);
    $tipoEvento = clean_str((string)($input['tipo_evento'] ?? $input['tipo'] ?? ''));
    $q = clean_str((string)($input['q'] ?? ''));
    $limit = reportes_operativos_limit($input['limit'] ?? null, 100, $maxLimit);

    $joins = [];
    $select = [
        'b.id',
        'b.fecha_hora',
        'b.tipo_origen',
        'b.tipo_evento',
        'b.resultado',
        'b.observaciones',
        'b.metadata_json',
        'b.guardia_id',
        'b.area_id',
        'b.origen_id',
    ];
    $searchFields = ['b.tipo_origen', 'b.tipo_evento', 'b.resultado', 'b.observaciones', 'b.metadata_json'];

    if (tableExists($pdo, 'users')) {
        $joins[] = 'LEFT JOIN users g ON g.id = b.guardia_id';
        $select[] = 'g.name AS operador_nombre';
        $searchFields[] = 'g.name';
        $searchFields[] = 'g.email';
    } else {
        $select[] = 'NULL AS operador_nombre';
    }

    if (tableExists($pdo, 'areas_operativas')) {
        $joins[] = 'LEFT JOIN areas_operativas a ON a.id = b.area_id AND a.residencial_id = b.residencial_id';
        $select[] = 'a.nombre AS area_nombre';
        $select[] = 'a.codigo AS area_codigo';
        $searchFields[] = 'a.nombre';
        $searchFields[] = 'a.codigo';
    } else {
        $select[] = 'NULL AS area_nombre';
        $select[] = 'NULL AS area_codigo';
    }

    if (tableExists($pdo, 'personas_recurrentes')) {
        $joins[] = 'LEFT JOIN personas_recurrentes pr ON pr.id = b.persona_recurrente_id AND pr.residencial_id = b.residencial_id';
        $select[] = 'pr.nombre AS persona_nombre';
        $searchFields[] = 'pr.nombre';
        $searchFields[] = 'pr.empresa';
    } else {
        $select[] = 'NULL AS persona_nombre';
    }

    if (tableExists($pdo, 'visitantes_rapidos')) {
        $joins[] = 'LEFT JOIN visitantes_rapidos vr ON vr.id = b.visitante_rapido_id AND vr.residencial_id = b.residencial_id';
        $select[] = 'vr.nombre_visitante';
        $select[] = 'vr.empresa AS visitante_empresa';
        $searchFields[] = 'vr.nombre_visitante';
        $searchFields[] = 'vr.empresa';
        $searchFields[] = 'vr.motivo';
    } else {
        $select[] = 'NULL AS nombre_visitante';
        $select[] = 'NULL AS visitante_empresa';
    }

    if (tableExists($pdo, 'permisos_materiales')) {
        $joins[] = 'LEFT JOIN permisos_materiales pm ON pm.id = b.permiso_material_id AND pm.residencial_id = b.residencial_id';
        $select[] = 'pm.tipo_movimiento AS permiso_tipo_movimiento';
        $select[] = 'pm.estado AS permiso_estado';
        $searchFields[] = 'pm.tipo_movimiento';
        $searchFields[] = 'pm.estado';
        $searchFields[] = 'pm.notas';
    } else {
        $select[] = 'NULL AS permiso_tipo_movimiento';
        $select[] = 'NULL AS permiso_estado';
    }

    if (tableExists($pdo, 'incidencias')) {
        $joins[] = "LEFT JOIN incidencias i ON b.tipo_origen = 'incidencia' AND i.id = b.origen_id AND i.residencial_id = b.residencial_id";
        $select[] = 'i.titulo AS incidencia_titulo';
        $select[] = 'i.estado AS incidencia_estado';
        $searchFields[] = 'i.titulo';
        $searchFields[] = 'i.descripcion';
    } else {
        $select[] = 'NULL AS incidencia_titulo';
        $select[] = 'NULL AS incidencia_estado';
    }

    if (tableExists($pdo, 'prestamos_herramientas') && tableExists($pdo, 'catalogo_herramientas')) {
        $joins[] = "LEFT JOIN prestamos_herramientas ph ON b.tipo_origen = 'prestamo_herramienta' AND ph.id = b.origen_id AND ph.residencial_id = b.residencial_id";
        $joins[] = 'LEFT JOIN catalogo_herramientas ch ON ch.id = ph.herramienta_id AND ch.residencial_id = b.residencial_id';
        $select[] = 'ch.nombre AS herramienta_nombre';
        $select[] = 'ph.responsable_nombre AS herramienta_responsable';
        $select[] = 'ph.estado AS herramienta_estado';
        $searchFields[] = 'ch.nombre';
        $searchFields[] = 'ph.responsable_nombre';
        $searchFields[] = 'ph.estado';
    } else {
        $select[] = 'NULL AS herramienta_nombre';
        $select[] = 'NULL AS herramienta_responsable';
        $select[] = 'NULL AS herramienta_estado';
    }

    $where = ['b.residencial_id = :rid', 'DATE(b.fecha_hora) BETWEEN :desde AND :hasta'];
    $params = [
        'rid' => $residencialId,
        'desde' => $desde,
        'hasta' => $hasta,
    ];

    if ($areaId > 0) {
        $where[] = 'b.area_id = :area_id';
        $params['area_id'] = $areaId;
    }
    if ($operadorId > 0) {
        $where[] = 'b.guardia_id = :guardia_id';
        $params['guardia_id'] = $operadorId;
    }
    if ($tipoEvento !== '') {
        if (str_contains($tipoEvento, '::')) {
            [$origen, $evento] = array_pad(explode('::', $tipoEvento, 2), 2, '');
            $where[] = 'b.tipo_origen = :tipo_origen AND b.tipo_evento = :tipo_evento';
            $params['tipo_origen'] = $origen;
            $params['tipo_evento'] = $evento;
        } else {
            $where[] = '(b.tipo_origen = :tipo_evento_origen_simple OR b.tipo_evento = :tipo_evento_evento_simple)';
            $params['tipo_evento_origen_simple'] = $tipoEvento;
            $params['tipo_evento_evento_simple'] = $tipoEvento;
        }
    }
    if ($q !== '') {
        $qParts = [];
        foreach ($searchFields as $idx => $field) {
            $key = 'q' . $idx;
            $qParts[] = $field . ' LIKE :' . $key;
            $params[$key] = '%' . $q . '%';
        }
        $where[] = '(' . implode(' OR ', $qParts) . ')';
    }

    $sql = "
        SELECT " . implode(",\n               ", $select) . "
        FROM bitacora_operativa b
        " . implode("\n        ", $joins) . "
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.fecha_hora DESC, b.id DESC
        LIMIT " . (int)$limit . "
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = array_map(static function (array $row): array {
        $metadata = [];
        if (!empty($row['metadata_json'])) {
            $decoded = json_decode((string)$row['metadata_json'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $responsable = (string)($row['persona_nombre'] ?? '');
        if ($responsable === '') {
            $responsable = (string)($row['nombre_visitante'] ?? '');
        }
        if ($responsable === '') {
            $responsable = (string)($row['herramienta_responsable'] ?? '');
        }
        if ($responsable === '') {
            $responsable = (string)($metadata['responsable_nombre'] ?? $metadata['responsable'] ?? '');
        }

        $detalle = (string)($row['observaciones'] ?? '');
        if ($detalle === '') {
            $detalle = (string)($row['incidencia_titulo'] ?? $row['herramienta_nombre'] ?? $row['permiso_tipo_movimiento'] ?? '');
        }

        $estado = (string)($row['resultado'] ?? '');
        $extraEstado = (string)($row['incidencia_estado'] ?? $row['herramienta_estado'] ?? $row['permiso_estado'] ?? '');
        if ($extraEstado !== '') {
            $estado = trim($estado . ' · ' . $extraEstado, ' ·');
        }

        return [
            'id' => (int)$row['id'],
            'fecha_hora' => (string)$row['fecha_hora'],
            'tipo_origen' => (string)$row['tipo_origen'],
            'tipo_evento' => (string)$row['tipo_evento'],
            'resultado' => (string)$row['resultado'],
            'estado' => $estado,
            'area_id' => isset($row['area_id']) ? (int)$row['area_id'] : null,
            'area_nombre' => (string)($row['area_nombre'] ?? ''),
            'area_codigo' => (string)($row['area_codigo'] ?? ''),
            'persona_responsable' => $responsable,
            'operador_nombre' => (string)($row['operador_nombre'] ?? ''),
            'origen_modulo' => (string)$row['tipo_origen'],
            'descripcion' => $detalle,
            'metadata' => $metadata,
            'herramienta_nombre' => (string)($row['herramienta_nombre'] ?? ''),
            'visitante_empresa' => (string)($row['visitante_empresa'] ?? ''),
            'permiso_tipo_movimiento' => (string)($row['permiso_tipo_movimiento'] ?? ''),
        ];
    }, $rows);

    return [
        'items' => $items,
        'summary' => [
            'total' => count($items),
            'permitidos' => count(array_filter($items, static fn(array $row): bool => $row['resultado'] === 'permitido')),
            'denegados' => count(array_filter($items, static fn(array $row): bool => $row['resultado'] === 'denegado')),
            'otros' => count(array_filter($items, static fn(array $row): bool => !in_array($row['resultado'], ['permitido', 'denegado'], true))),
        ],
        'pagination' => [
            'limit' => $limit,
        ],
    ];
}

function reportes_operativos_output_csv(array $items): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header_remove('Content-Type');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="auditoria-operativa-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        exit;
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Fecha/hora', 'Tipo de evento', 'Area', 'Persona/responsable', 'Operador', 'Origen/modulo', 'Descripcion/observacion', 'Resultado/estado']);
    foreach ($items as $item) {
        fputcsv($out, [
            $item['fecha_hora'] ?? '',
            trim((string)($item['tipo_origen'] ?? '') . ' / ' . (string)($item['tipo_evento'] ?? ''), ' /'),
            $item['area_nombre'] ?? '',
            $item['persona_responsable'] ?? '',
            $item['operador_nombre'] ?? '',
            $item['origen_modulo'] ?? '',
            $item['descripcion'] ?? '',
            $item['estado'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

try {
    $action = clean_str((string)($_GET['action'] ?? 'list'));

    if ($action === 'filters') {
        json_out(true, [
            'data' => [
                'filters' => reportes_operativos_filters($pdo, $residencialId),
                'context' => admin_operational_context(),
            ],
        ]);
    }

    if ($action === 'export_csv') {
        $result = reportes_operativos_query($pdo, $residencialId, $_GET, 1000);
        reportes_operativos_output_csv($result['items']);
    }

    if ($action !== 'list') {
        json_out(false, ['error' => 'Acción no válida.'], 400);
    }

    $result = reportes_operativos_query($pdo, $residencialId, $_GET, 200);
    json_out(true, [
        'data' => [
            'items' => $result['items'],
            'summary' => $result['summary'],
            'pagination' => $result['pagination'],
            'filters' => reportes_operativos_filters($pdo, $residencialId),
            'context' => admin_operational_context(),
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos cargar la auditoría operativa.');
}
