<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

admin_operational_required();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function permiso_material_qr_payload(string $token): string
{
    return 'op:pm:' . $token;
}

function permisos_parse_items(string $raw): array
{
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $items = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $materialId = isset($row['material_id']) && $row['material_id'] !== '' ? (int)$row['material_id'] : null;
        $materialNombre = clean_str($row['material_nombre'] ?? '');
        $cantidad = clean_str($row['cantidad_texto'] ?? '');
        $agregar = !empty($row['agregar_a_catalogo']) ? 1 : 0;

        if ($materialId === null && $materialNombre === '') {
            continue;
        }
        if ($cantidad === '') {
            continue;
        }

        $items[] = [
            'material_id' => $materialId,
            'material_nombre' => $materialNombre,
            'cantidad_texto' => $cantidad,
            'agregar_a_catalogo' => $agregar,
        ];
    }

    return $items;
}

try {
    if ($method === 'GET') {
        $action = clean_str($_GET['action'] ?? 'list');

        if ($action === 'meta') {
            $stmtCat = $pdo->prepare("
                SELECT id, nombre, categoria
                FROM catalogo_materiales
                WHERE residencial_id = :rid
                  AND activo = 1
                ORDER BY nombre ASC
            ");
            $stmtCat->execute(['rid' => $residencialId]);

            json_out(true, [
                'data' => [
                    'areas' => operational_area_options($pdo, $residencialId),
                    'responsables' => operational_user_options($pdo, $residencialId),
                    'catalogo' => $stmtCat->fetchAll(PDO::FETCH_ASSOC) ?: [],
                    'context' => admin_operational_context(),
                ],
            ]);
        }

        $estado = clean_str($_GET['estado'] ?? '');
        $where = ['p.residencial_id = :rid'];
        $params = ['rid' => $residencialId];

        if ($estado !== '') {
            $where[] = 'p.estado = :estado';
            $params['estado'] = $estado;
        }

        $stmt = $pdo->prepare("
            SELECT
                p.*,
                a.nombre AS area_nombre,
                ru.name AS responsable_nombre,
                su.name AS solicitado_por_nombre,
                au.name AS aprobado_por_nombre
            FROM permisos_materiales p
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            LEFT JOIN users ru ON ru.id = p.responsable_user_id
            LEFT JOIN users su ON su.id = p.solicitado_por_user_id
            LEFT JOIN users au ON au.id = p.aprobado_por_user_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE p.estado
                    WHEN 'pendiente' THEN 1
                    WHEN 'aprobado' THEN 2
                    WHEN 'en_proceso' THEN 3
                    WHEN 'usado' THEN 4
                    WHEN 'cancelado' THEN 5
                    ELSE 6
                END,
                p.created_at DESC
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $ids = array_map(static fn(array $row): int => (int)$row['id'], $items);
        $itemsByPermiso = [];
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtItems = $pdo->prepare("
                SELECT i.*, c.nombre AS catalogo_nombre
                FROM permisos_materiales_items i
                LEFT JOIN catalogo_materiales c ON c.id = i.material_id
                WHERE i.permiso_id IN ($placeholders)
                ORDER BY i.id ASC
            ");
            $stmtItems->execute($ids);
            foreach ($stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $pid = (int)$row['permiso_id'];
                $itemsByPermiso[$pid][] = [
                    'id' => (int)$row['id'],
                    'material_id' => $row['material_id'] !== null ? (int)$row['material_id'] : null,
                    'material_nombre' => (string)($row['material_nombre'] ?: $row['catalogo_nombre'] ?: ''),
                    'cantidad_texto' => (string)$row['cantidad_texto'],
                    'agregar_a_catalogo' => (int)$row['agregar_a_catalogo'],
                ];
            }
        }

        json_out(true, [
            'data' => [
                'items' => array_map(static function (array $row) use ($itemsByPermiso): array {
                    $id = (int)$row['id'];
                    return [
                        'id' => $id,
                        'tipo_movimiento' => (string)$row['tipo_movimiento'],
                        'estado' => (string)$row['estado'],
                        'notas' => (string)($row['notas'] ?? ''),
                        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
                        'area_nombre' => (string)($row['area_nombre'] ?? ''),
                        'responsable_user_id' => $row['responsable_user_id'] !== null ? (int)$row['responsable_user_id'] : null,
                        'responsable_nombre' => (string)($row['responsable_nombre'] ?? ''),
                        'solicitado_por_nombre' => (string)($row['solicitado_por_nombre'] ?? ''),
                        'aprobado_por_nombre' => (string)($row['aprobado_por_nombre'] ?? ''),
                        'aprobado_at' => (string)($row['aprobado_at'] ?? ''),
                        'created_at' => (string)($row['created_at'] ?? ''),
                        'qr_token' => (string)$row['qr_token'],
                        'qr_payload' => permiso_material_qr_payload((string)$row['qr_token']),
                        'items' => $itemsByPermiso[$id] ?? [],
                    ];
                }, $items),
            ],
        ]);
    }

    $action = clean_str($_POST['action'] ?? 'save');

    if ($action === 'approve') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Permiso inválido.']);
        }

        $stmt = $pdo->prepare("
            UPDATE permisos_materiales
            SET estado = 'aprobado',
                aprobado_por_user_id = :admin_id,
                aprobado_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
              AND estado IN ('pendiente', 'cancelado')
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
            'admin_id' => $adminId,
        ]);

        json_out(true, ['message' => 'Permiso aprobado correctamente.']);
    }

    if ($action === 'cancel') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Permiso inválido.']);
        }

        $stmt = $pdo->prepare("
            UPDATE permisos_materiales
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

        json_out(true, ['message' => 'Permiso cancelado.']);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Permiso inválido.']);
        }

        $pdo->beginTransaction();
        $stmtItems = $pdo->prepare("DELETE FROM permisos_materiales_items WHERE permiso_id = :id");
        $stmtItems->execute(['id' => $id]);

        $stmt = $pdo->prepare("
            DELETE FROM permisos_materiales
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
        ]);
        $pdo->commit();

        json_out(true, ['message' => 'Permiso eliminado correctamente.']);
    }

    $id = (int)($_POST['id'] ?? 0);
    $tipoMovimiento = clean_str($_POST['tipo_movimiento'] ?? 'entrada');
    $notas = clean_str($_POST['notas'] ?? '');
    $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;
    $responsableId = isset($_POST['responsable_user_id']) && $_POST['responsable_user_id'] !== '' ? (int)$_POST['responsable_user_id'] : null;
    $items = permisos_parse_items((string)($_POST['items_json'] ?? '[]'));

    if (!in_array($tipoMovimiento, ['entrada', 'salida'], true)) {
        json_out(false, ['error' => 'Tipo de movimiento inválido.']);
    }

    if (!$items) {
        json_out(false, ['error' => 'Debes agregar al menos un material o equipo.']);
    }

    if ($areaId !== null && !operational_validate_area($pdo, $residencialId, $areaId)) {
        json_out(false, ['error' => 'El área seleccionada no pertenece a tu cliente.']);
    }

    if ($responsableId !== null && !operational_validate_internal_user($pdo, $residencialId, $responsableId)) {
        json_out(false, ['error' => 'El responsable seleccionado no pertenece a este cliente.']);
    }

    foreach ($items as $item) {
        if ($item['material_id'] !== null) {
            $stmtCheck = $pdo->prepare("
                SELECT id
                FROM catalogo_materiales
                WHERE id = :id
                  AND residencial_id = :rid
                LIMIT 1
            ");
            $stmtCheck->execute([
                'id' => $item['material_id'],
                'rid' => $residencialId,
            ]);

            if (!$stmtCheck->fetchColumn()) {
                json_out(false, ['error' => 'Uno de los materiales seleccionados no pertenece a tu catálogo.']);
            }
        }
    }

    $pdo->beginTransaction();

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE permisos_materiales
            SET area_id = :area_id,
                responsable_user_id = :responsable_user_id,
                tipo_movimiento = :tipo_movimiento,
                notas = :notas,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
            'area_id' => $areaId,
            'responsable_user_id' => $responsableId,
            'tipo_movimiento' => $tipoMovimiento,
            'notas' => $notas !== '' ? $notas : null,
        ]);

        $stmtDel = $pdo->prepare("DELETE FROM permisos_materiales_items WHERE permiso_id = :id");
        $stmtDel->execute(['id' => $id]);
        $permisoId = $id;
    } else {
        $token = operational_generate_token(32);
        $stmt = $pdo->prepare("
            INSERT INTO permisos_materiales (
                residencial_id, area_id, responsable_user_id, tipo_movimiento, qr_token, estado,
                solicitado_por_user_id, aprobado_por_user_id, aprobado_at, notas, created_at, updated_at
            ) VALUES (
                :rid, :area_id, :responsable_user_id, :tipo_movimiento, :qr_token, 'pendiente',
                :solicitado_por_user_id, NULL, NULL, :notas, NOW(), NOW()
            )
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'area_id' => $areaId,
            'responsable_user_id' => $responsableId,
            'tipo_movimiento' => $tipoMovimiento,
            'qr_token' => $token,
            'solicitado_por_user_id' => $adminId,
            'notas' => $notas !== '' ? $notas : null,
        ]);
        $permisoId = (int)$pdo->lastInsertId();
    }

    $stmtInsItem = $pdo->prepare("
        INSERT INTO permisos_materiales_items (
            permiso_id, material_id, material_nombre, cantidad_texto, agregar_a_catalogo, created_at, updated_at
        ) VALUES (
            :permiso_id, :material_id, :material_nombre, :cantidad_texto, :agregar_a_catalogo, NOW(), NOW()
        )
    ");

    $stmtCatIns = $pdo->prepare("
        INSERT INTO catalogo_materiales (
            residencial_id, nombre, categoria, descripcion, activo, created_at, updated_at
        ) VALUES (
            :rid, :nombre, NULL, NULL, 1, NOW(), NOW()
        )
    ");

    foreach ($items as $item) {
        $materialId = $item['material_id'];
        $materialNombre = $item['material_nombre'];

        if ($materialId === null && $item['agregar_a_catalogo'] && $materialNombre !== '') {
            $stmtCatIns->execute([
                'rid' => $residencialId,
                'nombre' => $materialNombre,
            ]);
            $materialId = (int)$pdo->lastInsertId();
        }

        $stmtInsItem->execute([
            'permiso_id' => $permisoId,
            'material_id' => $materialId,
            'material_nombre' => $materialNombre !== '' ? $materialNombre : null,
            'cantidad_texto' => $item['cantidad_texto'],
            'agregar_a_catalogo' => $item['agregar_a_catalogo'],
        ]);
    }

    $pdo->commit();

    json_out(true, [
        'message' => $id > 0 ? 'Permiso actualizado correctamente.' : 'Permiso creado correctamente.',
        'data' => $id > 0 ? [] : [
            'id' => $permisoId,
            'qr_payload' => permiso_material_qr_payload($token),
            'qr_token' => $token,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar los permisos de materiales.');
}
