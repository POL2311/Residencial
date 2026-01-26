<?php
// autos.php
// Endpoint para gestionar los vehículos asociados a usuarios (residentes y admins).
// Devuelve respuestas en JSON y soporta operaciones de listado, creación, actualización y eliminación.
// Requiere que el usuario esté autenticado y tenga rol de residente o administrador residencial.

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

// Exigir autenticación y rol adecuado
require_login();
require_role(['residente', 'admin_residencial']);

$user    = current_user();
$uid     = (int)($user['id'] ?? 0);
$isAdmin = ($user['role'] ?? ($user['tipo_usuario_nombre'] ?? '')) === 'admin_residencial';

/**
 * Responde en JSON y termina la ejecución.
 *
 * @param bool  $ok    Indica si la operación tuvo éxito.
 * @param array $extra Datos adicionales a incluir en la respuesta.
 */
function json_out(bool $ok, array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Comprueba si una tabla existe en la base de datos actual.
 *
 * @param PDO    $pdo   Conexión a la base de datos.
 * @param string $table Nombre de la tabla.
 * @return bool
 */
function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t\n    ");
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

// Lista de nombres de tabla candidatos (para compatibilidad con distintas instalaciones)
$candidateTables = [
    'autos',
    'autos_residentes',
    'autos_usuarios',
    'vehiculos',
    'vehiculos_residentes',
    'residentes_autos',
];

// Seleccionar la primera tabla existente
$table = null;
foreach ($candidateTables as $t) {
    if (tableExists($pdo, $t)) {
        $table = $t;
        break;
    }
}
if (!$table) {
    json_out(false, ['error' => 'No se encontró ninguna tabla de autos o vehículos.']);
}

/**
 * Obtiene metadatos de columnas para una tabla dada.
 *
 * @param PDO    $pdo   Conexión a la base de datos.
 * @param string $table Nombre de la tabla.
 * @return array
 */
function getColsMeta(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("\n        SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t\n        ORDER BY ORDINAL_POSITION\n    ");
    $stmt->execute(['t' => $table]);
    $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name          = $r['COLUMN_NAME'];
        $out[$name] = [
            'nullable' => ($r['IS_NULLABLE'] === 'YES'),
            'default'  => $r['COLUMN_DEFAULT'],
            'extra'    => $r['EXTRA'],
        ];
    }
    return $out;
}

// Obtiene los metadatos de la tabla seleccionada
$meta = getColsMeta($pdo, $table);
$cols = array_keys($meta);

/**
 * Devuelve el primer nombre de columna coincidente de una lista de candidatos.
 *
 * @param array $cols       Columnas disponibles.
 * @param array $candidates Lista de posibles nombres.
 * @return string|null
 */
function pickCol(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

// Determinar columnas relevantes según la tabla
$colId        = pickCol($cols, ['id']);
$colUser      = pickCol($cols, ['residente_id', 'user_id', 'propietario_user_id', 'owner_user_id', 'usuario_id']);
$colResid     = pickCol($cols, ['residencial_id', 'residencia_id', 'residential_id']);
$colUnidad    = pickCol($cols, ['unidad_id', 'unit_id']);
$colPlacas    = pickCol($cols, ['placas', 'placa', 'matricula']);
$colModelo    = pickCol($cols, ['modelo', 'model', 'marca_modelo', 'descripcion_modelo']);
$colColor     = pickCol($cols, ['color', 'colour']);
$colActivo    = pickCol($cols, ['activo', 'active', 'estatus', 'status']);
$colCreatedAt = pickCol($cols, ['created_at', 'fecha_creado', 'created']);
$colUpdatedAt = pickCol($cols, ['updated_at', 'fecha_actualizado', 'updated']);

// Validar columnas mínimas requeridas
if (!$colId || !$colUser || !$colPlacas) {
    json_out(false, ['error' => 'La tabla de autos no tiene columnas mínimas (id, user, placas).']);
}

/**
 * Obtiene el contexto de residencial y unidad principal para un usuario dado.
 *
 * @param PDO $pdo
 * @param int $userId
 * @return array
 */
function getContextForUser(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("\n        SELECT ur.residencial_id,\n               ru.unidad_id\n        FROM usuarios_residenciales ur\n        LEFT JOIN residentes_unidades ru ON ru.user_id = ur.user_id\n        WHERE ur.user_id = :uid\n        ORDER BY ur.es_principal DESC, ur.created_at ASC\n        LIMIT 1\n    ");
    $stmt->execute(['uid' => $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
        'residencial_id' => (int)($row['residencial_id'] ?? 0),
        'unidad_id'      => (int)($row['unidad_id'] ?? 0),
    ];
}

// Determinar el usuario objetivo según el rol y parámetros
$requestUserId = $uid;
if ($isAdmin) {
    $param = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    if ($param && ctype_digit((string)$param)) {
        $requestUserId = (int)$param;
    }
}

// Obtener contexto del usuario
$ctx            = getContextForUser($pdo, $requestUserId);
$residencial_id = $ctx['residencial_id'];
$unidad_id      = $ctx['unidad_id'];

// Construir cláusula WHERE para filtrar autos
$whereParts = ["$colUser = :uid"];
$params     = ['uid' => $requestUserId];
if ($colResid && $residencial_id > 0) {
    $whereParts[]      = "$colResid = :rid";
    $params['rid']     = $residencial_id;
}
if ($colUnidad && $unidad_id > 0) {
    $whereParts[]      = "$colUnidad = :unid";
    $params['unid']    = $unidad_id;
}
$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

/**
 * Devuelve un listado de autos según condiciones y columnas detectadas.
 *
 * @param PDO    $pdo
 * @param string $table
 * @param string $whereSql
 * @param array  $params
 * @param string $colId
 * @param string $colPlacas
 * @param string|null $colModelo
 * @param string|null $colColor
 * @return array
 */
function listAutos(PDO $pdo, string $table, string $whereSql, array $params, string $colId, string $colPlacas, ?string $colModelo, ?string $colColor): array {
    // Seleccionar todas las columnas para obtener un conjunto completo de datos del auto.
    $sql  = "SELECT * FROM $table $whereSql ORDER BY $colId DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    // Convertir valores numéricos de id a enteros
    return array_map(function ($r) use ($colId) {
        if (isset($r[$colId])) {
            $r[$colId] = (int)$r[$colId];
        }
        return $r;
    }, $rows);
}

// Determinar método y acción
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// ---------- LISTAR AUTOS ----------
if ($action === 'list') {
    try {
        $autos = listAutos($pdo, $table, $whereSql, $params, $colUser, $colPlacas, $colModelo, $colColor);
        json_out(true, ['data' => ['autos' => $autos, 'table' => $table]]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al listar autos: ' . $e->getMessage()]);
    }
}

// ---------- LISTAR TODOS LOS AUTOS (solo admin) ----------
// Permite obtener todos los registros de la tabla sin filtrar por usuario.
if ($action === 'list_all') {
    if (!$isAdmin) {
        json_out(false, ['error' => 'No autorizado.']);
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM $table ORDER BY $colId DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $autos = array_map(function ($r) use ($colId) {
            if (isset($r[$colId])) {
                $r[$colId] = (int)$r[$colId];
            }
            return $r;
        }, $rows);
        json_out(true, ['data' => ['autos' => $autos, 'table' => $table]]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al listar todos los autos: ' . $e->getMessage()]);
    }
}

// ---------- CREAR AUTO ----------
if ($method === 'POST' && $action === 'create') {
    $placas = strtoupper(trim($_POST['placas'] ?? ''));
    $modelo = trim($_POST['modelo'] ?? '');
    $color  = trim($_POST['color']  ?? '');

    if ($placas === '') {
        json_out(false, ['error' => 'Las placas son obligatorias.']);
    }

    // Construir campos y valores dinámicos
    $fields = [$colUser, $colPlacas];
    $values = [':uid', ':placas'];
    $insP   = ['uid' => $requestUserId, 'placas' => $placas];

    if ($colResid) {
        $fields[] = $colResid;
        $values[] = ':rid';
        $insP['rid'] = $residencial_id ?: null;
    }
    if ($colUnidad) {
        $fields[] = $colUnidad;
        $values[] = ':unid';
        $insP['unid'] = $unidad_id ?: null;
    }
    if ($colModelo) {
        $fields[] = $colModelo;
        $values[] = ':modelo';
        $insP['modelo'] = $modelo !== '' ? $modelo : null;
    }
    if ($colColor) {
        $fields[] = $colColor;
        $values[] = ':color';
        $insP['color'] = $color !== '' ? $color : null;
    }
    if ($colActivo) {
        $fields[] = $colActivo;
        $values[] = ':activo';
        $insP['activo'] = 1;
    }
    if ($colCreatedAt) {
        $fields[] = $colCreatedAt;
        $values[] = 'NOW()';
    }

    try {
        $sql = "INSERT INTO $table (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($insP);

        $autos = listAutos($pdo, $table, $whereSql, $params, $colId, $colPlacas, $colModelo, $colColor);
        json_out(true, ['message' => 'Auto agregado.', 'data' => ['autos' => $autos]]);
    } catch (Throwable $e) {
        $msg = strpos($e->getMessage(), '1062') !== false ? 'Estas placas ya están registradas.' : 'Error al crear auto.';
        json_out(false, ['error' => $msg]);
    }
}

// ---------- ACTUALIZAR AUTO ----------
if ($method === 'POST' && $action === 'update') {
    $autoId = (int)($_POST['auto_id'] ?? 0);
    $placas = strtoupper(trim($_POST['placas'] ?? ''));
    $modelo = trim($_POST['modelo'] ?? '');
    $color  = trim($_POST['color']  ?? '');

    if ($autoId <= 0 || $placas === '') {
        json_out(false, ['error' => 'Datos del auto inválidos.']);
    }

    $updFields = [$colPlacas . ' = :placas'];
    $updData   = ['placas' => $placas, 'id' => $autoId, 'uid' => $requestUserId];
    if ($colModelo) {
        $updFields[]    = "$colModelo = :modelo";
        $updData['modelo'] = $modelo !== '' ? $modelo : null;
    }
    if ($colColor) {
        $updFields[]    = "$colColor = :color";
        $updData['color'] = $color !== '' ? $color : null;
    }
    if ($colUpdatedAt) {
        $updFields[] = "$colUpdatedAt = NOW()";
    }

    $whereUpd = ["$colId = :id", "$colUser = :uid"];
    if ($colResid && $residencial_id > 0) {
        $whereUpd[]      = "$colResid = :rid";
        $updData['rid']  = $residencial_id;
    }
    if ($colUnidad && $unidad_id > 0) {
        $whereUpd[]      = "$colUnidad = :unid";
        $updData['unid'] = $unidad_id;
    }

    try {
        $stmt = $pdo->prepare("UPDATE $table SET " . implode(', ', $updFields) . " WHERE " . implode(' AND ', $whereUpd));
        $stmt->execute($updData);

        $autos = listAutos($pdo, $table, $whereSql, $params, $colId, $colPlacas, $colModelo, $colColor);
        json_out(true, ['message' => 'Auto actualizado.', 'data' => ['autos' => $autos]]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al actualizar auto.']);
    }
}

// ---------- ELIMINAR AUTO ----------
if ($method === 'POST' && $action === 'delete') {
    $autoId = (int)($_POST['auto_id'] ?? 0);
    if ($autoId <= 0) {
        json_out(false, ['error' => 'ID de auto inválido.']);
    }

    $delData  = ['id' => $autoId, 'uid' => $requestUserId];
    $whereDel = ["$colId = :id", "$colUser = :uid"];
    if ($colResid && $residencial_id > 0) {
        $whereDel[]   = "$colResid = :rid";
        $delData['rid'] = $residencial_id;
    }
    if ($colUnidad && $unidad_id > 0) {
        $whereDel[]    = "$colUnidad = :unid";
        $delData['unid'] = $unidad_id;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE " . implode(' AND ', $whereDel));
        $stmt->execute($delData);

        $autos = listAutos($pdo, $table, $whereSql, $params, $colId, $colPlacas, $colModelo, $colColor);
        json_out(true, ['message' => 'Auto eliminado.', 'data' => ['autos' => $autos]]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al eliminar auto.']);
    }
}

// Acción no reconocida
json_out(false, ['error' => 'Acción no soportada.']);