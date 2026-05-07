<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';
guardia_module_required('materiales');

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = clean_str($_GET['action'] ?? $_POST['action'] ?? 'meta');

function guardia_permiso_material_qr_payload(string $token): string
{
    return 'op:pm:' . $token;
}

function guardia_permisos_parse_items(string $raw): array
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
    if ($method === 'GET' && $action === 'meta') {
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
            ],
        ]);
    }

    if ($method !== 'POST' || $action !== 'create_solicitud') {
        json_out(false, ['error' => 'Accion invalida.']);
    }

    $tipoMovimiento = clean_str($_POST['tipo_movimiento'] ?? 'entrada');
    $notas = clean_str($_POST['notas'] ?? '');
    $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;
    $responsableId = isset($_POST['responsable_user_id']) && $_POST['responsable_user_id'] !== '' ? (int)$_POST['responsable_user_id'] : null;
    $items = guardia_permisos_parse_items((string)($_POST['items_json'] ?? '[]'));

    if (!in_array($tipoMovimiento, ['entrada', 'salida'], true)) {
        json_out(false, ['error' => 'Tipo de movimiento invalido.']);
    }
    if (!$responsableId || !operational_validate_internal_user($pdo, $residencialId, $responsableId)) {
        json_out(false, ['error' => 'Selecciona un responsable interno valido.']);
    }
    if ($areaId !== null && !operational_validate_area($pdo, $residencialId, $areaId)) {
        json_out(false, ['error' => 'El area seleccionada no pertenece a este cliente.']);
    }
    if (!$items) {
        json_out(false, ['error' => 'Debes agregar al menos un material o equipo.']);
    }

    $catalogNames = [];
    foreach ($items as $idx => $item) {
        if ($item['material_id'] === null) {
            continue;
        }
        $stmtCheck = $pdo->prepare("
            SELECT id, nombre
            FROM catalogo_materiales
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmtCheck->execute([
            'id' => $item['material_id'],
            'rid' => $residencialId,
        ]);
        $catalogRow = $stmtCheck->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$catalogRow) {
            json_out(false, ['error' => 'Uno de los materiales seleccionados no pertenece al catalogo.']);
        }
        $catalogNames[$idx] = clean_str((string)($catalogRow['nombre'] ?? ''));
    }

    $pdo->beginTransaction();

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
        'solicitado_por_user_id' => $guardiaId,
        'notas' => $notas !== '' ? $notas : null,
    ]);
    $permisoId = (int)$pdo->lastInsertId();

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

    foreach ($items as $idx => $item) {
        $materialId = $item['material_id'];
        $materialNombre = clean_str((string)($item['material_nombre'] ?? ''));

        if ($materialId === null && $item['agregar_a_catalogo'] && $materialNombre !== '') {
            $stmtCatIns->execute([
                'rid' => $residencialId,
                'nombre' => $materialNombre,
            ]);
            $materialId = (int)$pdo->lastInsertId();
        }

        if ($materialId !== null && $materialNombre === '') {
            $materialNombre = (string)($catalogNames[$idx] ?? '');
        }
        if ($materialNombre === '') {
            $materialNombre = 'Material';
        }

        $stmtInsItem->execute([
            'permiso_id' => $permisoId,
            'material_id' => $materialId,
            'material_nombre' => $materialNombre,
            'cantidad_texto' => $item['cantidad_texto'],
            'agregar_a_catalogo' => $item['agregar_a_catalogo'],
        ]);
    }

    $pdo->commit();

    json_out(true, [
        'message' => 'Solicitud enviada para revision del administrador.',
        'data' => [
            'id' => $permisoId,
            'estado' => 'pendiente',
            'qr_payload' => guardia_permiso_material_qr_payload($token),
            'qr_token' => $token,
        ],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar la solicitud de materiales.');
}
