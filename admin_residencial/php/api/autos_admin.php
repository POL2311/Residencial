<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/api_helpers.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);

$candidateTables = [
    'autos',
    'autos_residentes',
    'autos_usuarios',
    'vehiculos',
    'vehiculos_residentes',
    'residentes_autos',
];

$table = null;
foreach ($candidateTables as $t) {
    if (tableExists($pdo, $t)) {
        $table = $t;
        break;
    }
}

if (!$table) {
    json_out(false, ['error' => 'No se encontró ninguna tabla de autos.']);
}

$cols = getCols($pdo, $table);

$colId        = pickCol($cols, ['id']);
$colUser      = pickCol($cols, ['propietario_user_id', 'user_id', 'residente_id', 'owner_user_id', 'usuario_id']);
$colResid     = pickCol($cols, ['residencial_id', 'residencia_id', 'residential_id']);
$colUnidad    = pickCol($cols, ['unidad_id', 'unit_id']);
$colPlacas    = pickCol($cols, ['placas', 'placa', 'matricula']);
$colModelo    = pickCol($cols, ['modelo', 'model', 'marca_modelo', 'descripcion_modelo']);
$colColor     = pickCol($cols, ['color', 'colour']);
$colCreatedAt = pickCol($cols, ['created_at', 'fecha_creado', 'created']);

if (!$colId || !$colUser || !$colPlacas) {
    json_out(false, ['error' => 'La tabla de autos no tiene columnas mínimas requeridas.']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list_by_resident';

try {
    if ($method === 'GET') {
        if ($action === 'list_by_resident') {
            $userId = (int)($_GET['user_id'] ?? 0);
            if ($userId <= 0) {
                json_out(false, ['error' => 'Parámetro user_id requerido.']);
            }

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM usuarios_residenciales
                WHERE user_id = :uid
                  AND residencial_id = :rid
            ");
            $stmt->execute([
                'uid' => $userId,
                'rid' => $residencialId,
            ]);

            if ((int)$stmt->fetchColumn() === 0) {
                json_out(false, ['error' => 'El usuario no pertenece a tu residencial.']);
            }

            $select = [
                "a.`$colId` AS auto_id",
                "a.`$colPlacas` AS placas",
            ];

            if ($colModelo) {
                $select[] = "a.`$colModelo` AS modelo";
            }
            if ($colColor) {
                $select[] = "a.`$colColor` AS color";
            }
            if ($colUnidad) {
                $select[] = "a.`$colUnidad` AS unidad_id";
            }
            if ($colCreatedAt) {
                $select[] = "a.`$colCreatedAt` AS created_at";
            }

            $select[] = "u.id AS user_id";
            $select[] = "u.name AS user_name";
            $select[] = "u.email";
            $select[] = "u.telefono";
            $select[] = "un.clave AS unidad_clave";

            $sql = "
                SELECT " . implode(",\n", $select) . "
                FROM `$table` a
                LEFT JOIN users u ON u.id = a.`$colUser`
                LEFT JOIN unidades un ON " . ($colUnidad ? "un.id = a.`$colUnidad`" : "1=0") . "
                WHERE a.`$colUser` = :uid
            ";

            $params = ['uid' => $userId];

            if ($colResid) {
                $sql .= " AND a.`$colResid` = :rid";
                $params['rid'] = $residencialId;
            }

            $sql .= " ORDER BY a.`$colId` DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $autos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            json_out(true, [
                'data' => ['autos' => $autos],
            ]);
        }

        if ($action === 'list_all') {
            $select = [
                "a.`$colId` AS auto_id",
                "a.`$colPlacas` AS placas",
            ];

            if ($colModelo) {
                $select[] = "a.`$colModelo` AS modelo";
            }
            if ($colColor) {
                $select[] = "a.`$colColor` AS color";
            }
            if ($colUnidad) {
                $select[] = "a.`$colUnidad` AS unidad_id";
            }
            if ($colCreatedAt) {
                $select[] = "a.`$colCreatedAt` AS created_at";
            }

            $select[] = "u.id AS user_id";
            $select[] = "u.name AS user_name";
            $select[] = "u.email";
            $select[] = "u.telefono";
            $select[] = "un.clave AS unidad_clave";

            $sql = "
                SELECT " . implode(",\n", $select) . "
                FROM `$table` a
                LEFT JOIN users u ON u.id = a.`$colUser`
                LEFT JOIN unidades un ON " . ($colUnidad ? "un.id = a.`$colUnidad`" : "1=0") . "
            ";

            $params = [];
            if ($colResid) {
                $sql .= " WHERE a.`$colResid` = :rid";
                $params['rid'] = $residencialId;
            }

            $sql .= " ORDER BY a.`$colId` DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $autos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            json_out(true, [
                'data' => ['autos' => $autos],
            ]);
        }

        json_out(false, ['error' => 'Acción GET no soportada.']);
    }

    if ($method === 'POST' && $action === 'create') {
        $placas   = strtoupper(trim((string)($_POST['placas'] ?? '')));
        $modelo   = trim((string)($_POST['modelo'] ?? ''));
        $color    = trim((string)($_POST['color'] ?? ''));
        $userId   = (int)($_POST['user_id'] ?? 0);
        $unidadId = (int)($_POST['unidad_id'] ?? 0);

        if ($placas === '' || $userId <= 0) {
            json_out(false, ['error' => 'Placas y usuario son obligatorios.']);
        }

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM usuarios_residenciales
            WHERE user_id = :uid
              AND residencial_id = :rid
        ");
        $stmt->execute([
            'uid' => $userId,
            'rid' => $residencialId,
        ]);

        if ((int)$stmt->fetchColumn() === 0) {
            json_out(false, ['error' => 'El usuario no pertenece a tu residencial.']);
        }

        $fields = [$colUser, $colPlacas];
        $values = [':uid', ':placas'];
        $params = [
            'uid'    => $userId,
            'placas' => $placas,
        ];

        if ($colModelo) {
            $fields[] = $colModelo;
            $values[] = ':modelo';
            $params['modelo'] = $modelo !== '' ? $modelo : null;
        }

        if ($colColor) {
            $fields[] = $colColor;
            $values[] = ':color';
            $params['color'] = $color !== '' ? ucfirst(strtolower($color)) : null;
        }

        if ($colUnidad) {
            $fields[] = $colUnidad;
            $values[] = ':unidad';
            $params['unidad'] = $unidadId > 0 ? $unidadId : null;
        }

        if ($colResid) {
            $fields[] = $colResid;
            $values[] = ':rid';
            $params['rid'] = $residencialId;
        }

        $sql = "
            INSERT INTO `$table` (" . implode(', ', array_map(fn($f) => "`$f`", $fields)) . ")
            VALUES (" . implode(', ', $values) . ")
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        json_out(true, [
            'message' => 'Auto registrado correctamente.',
        ]);
    }

    json_out(false, ['error' => 'Acción no soportada.']);
} catch (Throwable $e) {
    $msg = $e->getMessage();

    if (strpos($msg, '1062') !== false || stripos($msg, 'Duplicate entry') !== false) {
        if (
            stripos($msg, 'placa') !== false ||
            stripos($msg, 'placas') !== false ||
            stripos($msg, 'unique_placas') !== false
        ) {
            json_out(false, ['error' => 'No se puede registrar el auto porque las placas ya están en uso.']);
        }

        json_out(false, ['error' => 'Ya existe un registro duplicado con esos datos.']);
    }

    json_out(false, ['error' => 'No se pudo registrar el auto. Intenta nuevamente.']);
}