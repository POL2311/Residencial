<?php
declare(strict_types=1);

require_once __DIR__ . '/_operational_bootstrap.php';

admin_operational_required();
admin_module_required('personal_recurrente');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function personal_qr_payload(string $token): string
{
    return 'op:pr:' . $token;
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

        $q = clean_str($_GET['q'] ?? '');
        $where = ['p.residencial_id = :rid'];
        $params = ['rid' => $residencialId];

        if ($q !== '') {
            $where[] = "(p.nombre LIKE :q OR p.telefono LIKE :q OR p.empresa LIKE :q OR p.puesto LIKE :q OR a.nombre LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $stmt = $pdo->prepare("
            SELECT
                p.*,
                a.nombre AS area_nombre
            FROM personas_recurrentes p
            LEFT JOIN areas_operativas a ON a.id = p.area_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.activo DESC, p.nombre ASC
        ");
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        json_out(true, [
            'data' => [
                'items' => array_map(static function (array $row): array {
                    return [
                        'id' => (int)$row['id'],
                        'nombre' => (string)$row['nombre'],
                        'telefono' => (string)($row['telefono'] ?? ''),
                        'empresa' => (string)($row['empresa'] ?? ''),
                        'puesto' => (string)($row['puesto'] ?? ''),
                        'notas' => (string)($row['notas'] ?? ''),
                        'foto_url' => (string)($row['foto_url'] ?? ''),
                        'area_id' => $row['area_id'] !== null ? (int)$row['area_id'] : null,
                        'area_nombre' => (string)($row['area_nombre'] ?? ''),
                        'qr_token' => (string)$row['qr_token'],
                        'qr_payload' => personal_qr_payload((string)$row['qr_token']),
                        'activo' => (int)$row['activo'],
                        'esta_dentro' => (int)$row['esta_dentro'],
                        'ultima_entrada_at' => (string)($row['ultima_entrada_at'] ?? ''),
                        'ultima_salida_at' => (string)($row['ultima_salida_at'] ?? ''),
                        'created_at' => (string)($row['created_at'] ?? ''),
                    ];
                }, $items),
            ],
        ]);
    }

    $action = clean_str($_POST['action'] ?? 'save');

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Personal inválido.']);
        }

        $stmt = $pdo->prepare("
            DELETE FROM personas_recurrentes
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $id,
            'rid' => $residencialId,
        ]);

        if ($stmt->rowCount() <= 0) {
            json_out(false, ['error' => 'No encontramos a la persona seleccionada.']);
        }

        json_out(true, ['message' => 'Personal eliminado correctamente.']);
    }

    if ($action === 'reset_pin') {
        $id = (int)($_POST['id'] ?? 0);
        $pin = preg_replace('/\D+/', '', (string)($_POST['pin'] ?? ''));
        if ($id <= 0 || strlen($pin) < 4 || strlen($pin) > 8) {
            json_out(false, ['error' => 'Debes indicar un PIN numérico entre 4 y 8 dígitos.']);
        }

        $stmt = $pdo->prepare("
            UPDATE personas_recurrentes
            SET pin_hash = :pin_hash,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
            'id' => $id,
            'rid' => $residencialId,
        ]);

        if ($stmt->rowCount() <= 0) {
            json_out(false, ['error' => 'No encontramos a la persona seleccionada.']);
        }

        json_out(true, ['message' => 'PIN actualizado correctamente.']);
    }

    if ($action === 'regenerate_qr') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            json_out(false, ['error' => 'Personal inválido.']);
        }

        $token = operational_generate_token(32);
        $stmt = $pdo->prepare("
            UPDATE personas_recurrentes
            SET qr_token = :token,
                updated_at = NOW()
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'token' => $token,
            'id' => $id,
            'rid' => $residencialId,
        ]);

        json_out(true, [
            'message' => 'QR regenerado correctamente.',
            'data' => [
                'qr_token' => $token,
                'qr_payload' => personal_qr_payload($token),
            ],
        ]);
    }

    $id = (int)($_POST['id'] ?? 0);
    $nombre = clean_str($_POST['nombre'] ?? '');
    $telefono = clean_str($_POST['telefono'] ?? '');
    $empresa = clean_str($_POST['empresa'] ?? '');
    $puesto = clean_str($_POST['puesto'] ?? '');
    $notas = clean_str($_POST['notas'] ?? '');
    $pin = preg_replace('/\D+/', '', (string)($_POST['pin'] ?? ''));
    $activo = isset($_POST['activo']) ? (int)((string)$_POST['activo'] === '1') : 1;
    $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? (int)$_POST['area_id'] : null;

    if ($nombre === '') {
        json_out(false, ['error' => 'El nombre es obligatorio.']);
    }

    if ($areaId !== null && !operational_validate_area($pdo, $residencialId, $areaId)) {
        json_out(false, ['error' => 'El área seleccionada no pertenece a tu cliente.']);
    }

    if ($id <= 0 && (strlen($pin) < 4 || strlen($pin) > 8)) {
        json_out(false, ['error' => 'Debes definir un PIN numérico entre 4 y 8 dígitos.']);
    }

    $photoUrl = null;
    if (!empty($_FILES['foto']['name'] ?? '')) {
        $processed = operational_process_image_upload($_FILES['foto'], [
            'folder' => 'personal',
            'preserve_text' => false,
            'max_long_side' => 1200,
        ]);
        $photoUrl = (string)$processed['public_url'];
    }

    if ($id > 0) {
        $stmtSel = $pdo->prepare("
            SELECT foto_url
            FROM personas_recurrentes
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmtSel->execute([
            'id' => $id,
            'rid' => $residencialId,
        ]);
        $current = $stmtSel->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$current) {
            json_out(false, ['error' => 'No encontramos a la persona seleccionada.']);
        }

        if ($photoUrl === null) {
            $photoUrl = (string)($current['foto_url'] ?? '');
        }

        $fields = "
            nombre = :nombre,
            telefono = :telefono,
            empresa = :empresa,
            puesto = :puesto,
            notas = :notas,
            foto_url = :foto_url,
            area_id = :area_id,
            activo = :activo,
            updated_at = NOW()
        ";
        $params = [
            'id' => $id,
            'rid' => $residencialId,
            'nombre' => $nombre,
            'telefono' => $telefono !== '' ? $telefono : null,
            'empresa' => $empresa !== '' ? $empresa : null,
            'puesto' => $puesto !== '' ? $puesto : null,
            'notas' => $notas !== '' ? $notas : null,
            'foto_url' => $photoUrl !== '' ? $photoUrl : null,
            'area_id' => $areaId,
            'activo' => $activo ? 1 : 0,
        ];

        if ($pin !== '') {
            $fields .= ", pin_hash = :pin_hash";
            $params['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
        }

        $stmt = $pdo->prepare("
            UPDATE personas_recurrentes
            SET {$fields}
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute($params);

        json_out(true, ['message' => 'Personal actualizado correctamente.']);
    }

    $token = operational_generate_token(32);
    $stmt = $pdo->prepare("
        INSERT INTO personas_recurrentes (
            residencial_id, area_id, nombre, foto_url, telefono, empresa, puesto, notas,
            qr_token, pin_hash, activo, esta_dentro, created_at, updated_at
        ) VALUES (
            :rid, :area_id, :nombre, :foto_url, :telefono, :empresa, :puesto, :notas,
            :qr_token, :pin_hash, :activo, 0, NOW(), NOW()
        )
    ");
    $stmt->execute([
        'rid' => $residencialId,
        'area_id' => $areaId,
        'nombre' => $nombre,
        'foto_url' => $photoUrl !== null && $photoUrl !== '' ? $photoUrl : null,
        'telefono' => $telefono !== '' ? $telefono : null,
        'empresa' => $empresa !== '' ? $empresa : null,
        'puesto' => $puesto !== '' ? $puesto : null,
        'notas' => $notas !== '' ? $notas : null,
        'qr_token' => $token,
        'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
        'activo' => $activo ? 1 : 0,
    ]);

    json_out(true, [
        'message' => 'Personal recurrente creado correctamente.',
        'data' => [
            'qr_token' => $token,
            'qr_payload' => personal_qr_payload($token),
        ],
    ]);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar el personal recurrente.');
}
