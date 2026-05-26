<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

$areasAllowed = service_profile_module_allowed_for_role_and_service($serviceProfile, 'admin_residencial', 'unidades')
    || service_profile_module_allowed_for_role_and_service($serviceProfile, 'admin_residencial', 'personal_recurrente');
if (!$areasAllowed) {
    json_out(false, ['error' => 'Las áreas operativas no están habilitadas para este cliente.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $stmt = $pdo->prepare("
            SELECT id, nombre, codigo, tipo, descripcion, activo, created_at, updated_at
            FROM areas_operativas
            WHERE residencial_id = :rid
            ORDER BY activo DESC, nombre ASC
        ");
        $stmt->execute(['rid' => $residencialId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        json_out(true, [
            'data' => [
                'items' => array_map(static function (array $row): array {
                    $row['id'] = (int)$row['id'];
                    $row['activo'] = (int)$row['activo'];
                    return $row;
                }, $items),
            ],
        ]);
    }

    $action = clean_str($_POST['action'] ?? 'save');

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = isset($_POST['activo']) && (string)$_POST['activo'] === '1' ? 1 : 0;
        if ($id <= 0) {
            json_out(false, ['error' => 'Área inválida.']);
        }

        $stmt = $pdo->prepare("
            UPDATE areas_operativas
            SET activo = :activo,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'activo' => $activo,
            'id' => $id,
            'rid' => $residencialId,
        ]);

        if ($stmt->rowCount() <= 0) {
            json_out(false, ['error' => 'No encontramos el área seleccionada.']);
        }

        json_out(true, ['message' => $activo ? 'Área activada correctamente.' : 'Área desactivada correctamente.']);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Área inválida.']);
        }

        $stmt = $pdo->prepare("
            DELETE FROM areas_operativas
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
        ]);

        if ($stmt->rowCount() <= 0) {
            json_out(false, ['error' => 'No encontramos el área seleccionada.']);
        }

        json_out(true, ['message' => 'Área eliminada correctamente.']);
    }

    $id = (int)($_POST['id'] ?? 0);
    $nombre = clean_str($_POST['nombre'] ?? '');
    $codigo = clean_str($_POST['codigo'] ?? '');
    $tipo = clean_str($_POST['tipo'] ?? 'general');
    $descripcion = clean_str($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? (int)((string)$_POST['activo'] === '1') : 1;

    if ($nombre === '') {
        json_out(false, ['error' => 'El nombre del área es obligatorio.']);
    }

    if ($codigo === '') {
        $codigo = strtoupper(substr(preg_replace('/[^A-Z0-9]+/i', '', $nombre), 0, 20));
        if ($codigo === '') {
            $codigo = 'AREA-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        }
    }

    $params = [
        'rid' => $residencialId,
        'nombre' => $nombre,
        'codigo' => strtoupper($codigo),
        'tipo' => $tipo !== '' ? $tipo : 'general',
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'activo' => $activo ? 1 : 0,
    ];

    if ($id > 0) {
        $params['id'] = $id;
        $stmt = $pdo->prepare("
            UPDATE areas_operativas
            SET nombre = :nombre,
                codigo = :codigo,
                tipo = :tipo,
                descripcion = :descripcion,
                activo = :activo,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute($params);
        json_out(true, ['message' => 'Área actualizada correctamente.']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO areas_operativas (
            residencial_id, nombre, codigo, tipo, descripcion, activo, created_at, updated_at
        ) VALUES (
            :rid, :nombre, :codigo, :tipo, :descripcion, :activo, NOW(), NOW()
        )
    ");
    $stmt->execute($params);

    json_out(true, ['message' => 'Área creada correctamente.']);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar las áreas operativas.');
}
