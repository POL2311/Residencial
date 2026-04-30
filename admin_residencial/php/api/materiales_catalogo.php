<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

admin_operational_required();
admin_module_required('materiales');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        $q = clean_str($_GET['q'] ?? '');
        $where = ['residencial_id = :rid'];
        $params = ['rid' => $residencialId];

        if ($q !== '') {
            $where[] = "(nombre LIKE :q OR categoria LIKE :q OR descripcion LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $stmt = $pdo->prepare("
            SELECT id, nombre, categoria, descripcion, activo, created_at, updated_at
            FROM catalogo_materiales
            WHERE " . implode(' AND ', $where) . "
            ORDER BY activo DESC, nombre ASC
        ");
        $stmt->execute($params);
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

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Material inválido.']);
        }

        $stmt = $pdo->prepare("
            DELETE FROM catalogo_materiales
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
        ]);

        if ($stmt->rowCount() <= 0) {
            json_out(false, ['error' => 'No encontramos ese material o equipo.']);
        }

        json_out(true, ['message' => 'Material eliminado correctamente.']);
    }

    $id = (int)($_POST['id'] ?? 0);
    $nombre = clean_str($_POST['nombre'] ?? '');
    $categoria = clean_str($_POST['categoria'] ?? '');
    $descripcion = clean_str($_POST['descripcion'] ?? '');
    $activo = isset($_POST['activo']) ? (int)((string)$_POST['activo'] === '1') : 1;

    if ($nombre === '') {
        json_out(false, ['error' => 'El nombre del material o equipo es obligatorio.']);
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("
            UPDATE catalogo_materiales
            SET nombre = :nombre,
                categoria = :categoria,
                descripcion = :descripcion,
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
            'categoria' => $categoria !== '' ? $categoria : null,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'activo' => $activo ? 1 : 0,
        ]);

        json_out(true, ['message' => 'Material actualizado correctamente.']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO catalogo_materiales (
            residencial_id, nombre, categoria, descripcion, activo, created_at, updated_at
        ) VALUES (
            :rid, :nombre, :categoria, :descripcion, :activo, NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $residencialId,
        'nombre' => $nombre,
        'categoria' => $categoria !== '' ? $categoria : null,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'activo' => $activo ? 1 : 0,
    ]);

    json_out(true, ['message' => 'Material agregado correctamente.']);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar el catálogo de materiales.');
}
