<?php
// pagos.php
// Endpoint para gestionar los pagos de residentes dentro de un residencial.
// Permite listar pagos y registrar nuevos pagos. Opcionalmente soporta actualización y eliminación.
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
 * Verifica si una tabla existe en la base de datos actual.
 *
 * @param PDO    $pdo
 * @param string $table
 * @return bool
 */
function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM INFORMATION_SCHEMA.TABLES\n        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t\n    ");
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

// Lista de nombres de tabla candidatos para pagos (compatibilidad)
$candidateTables = [
    'pagos_residentes',
    'pagos',
    'pagos_usuarios',
    'residentes_pagos',
    'pagos_users',
];

// Seleccionar la primera tabla que exista
$table = null;
foreach ($candidateTables as $t) {
    if (tableExists($pdo, $t)) {
        $table = $t;
        break;
    }
}
if (!$table) {
    json_out(false, ['error' => 'No se encontró ninguna tabla de pagos.']);
}

/**
 * Obtiene metadatos de columnas para la tabla seleccionada.
 *
 * @param PDO    $pdo
 * @param string $table
 * @return array
 */
function getColsMeta(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("\n        SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA\n        FROM INFORMATION_SCHEMA.COLUMNS\n        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t\n        ORDER BY ORDINAL_POSITION\n    ");
    $stmt->execute(['t' => $table]);
    $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name         = $r['COLUMN_NAME'];
        $out[$name] = [
            'nullable' => ($r['IS_NULLABLE'] === 'YES'),
            'default'  => $r['COLUMN_DEFAULT'],
            'extra'    => $r['EXTRA'],
        ];
    }
    return $out;
}

// Metadatos de la tabla de pagos
$meta = getColsMeta($pdo, $table);
$cols = array_keys($meta);

/**
 * Devuelve el primer nombre de columna de una lista de candidatos.
 *
 * @param array $cols
 * @param array $candidates
 * @return string|null
 */
function pickCol(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

// Determinar columnas relevantes de pagos
$colId        = pickCol($cols, ['id']);
$colUser      = pickCol($cols, ['residente_id', 'user_id', 'usuario_id', 'owner_user_id', 'propietario_user_id']);
$colResid     = pickCol($cols, ['residencial_id', 'residencia_id', 'residential_id']);
$colUnidad    = pickCol($cols, ['unidad_id', 'unit_id']);
$colMonto     = pickCol($cols, ['monto', 'amount', 'importe']);
$colFecha     = pickCol($cols, ['fecha_pago', 'fecha', 'payment_date', 'date']);
$colMetodo    = pickCol($cols, ['metodo', 'method', 'medio', 'forma_pago']);
$colConcepto  = pickCol($cols, ['concepto', 'concept', 'descripcion', 'description', 'nota']);
$colActivo    = pickCol($cols, ['activo', 'active', 'estatus', 'status']);
$colCreatedAt = pickCol($cols, ['created_at', 'fecha_creado', 'created']);
$colUpdatedAt = pickCol($cols, ['updated_at', 'fecha_actualizado', 'updated']);

// Validar columnas mínimas requeridas (id, user y monto y fecha)
if (!$colId || !$colUser || !$colMonto || !$colFecha) {
    json_out(false, ['error' => 'La tabla de pagos no tiene columnas mínimas (id, user, monto, fecha).']);
}

/**
 * Obtiene el contexto de residencial y unidad principal para un usuario.
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

// Determinar el usuario objetivo para la operación
$requestUserId = $uid;
if ($isAdmin) {
    $param = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
    if ($param && ctype_digit((string)$param)) {
        $requestUserId = (int)$param;
    }
}

// Obtener contexto para el usuario seleccionado
$ctx            = getContextForUser($pdo, $requestUserId);
$residencial_id = $ctx['residencial_id'];
$unidad_id      = $ctx['unidad_id'];

// Construir WHERE para listado de pagos
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
 * Devuelve un listado de pagos según condiciones y columnas detectadas.
 *
 * @param PDO    $pdo
 * @param string $table
 * @param string $whereSql
 * @param array  $params
 * @param string $colId
 * @param string $colMonto
 * @param string $colFecha
 * @param string|null $colMetodo
 * @param string|null $colConcepto
 * @return array
 */
function listPagos(PDO $pdo, string $table, string $whereSql, array $params, string $colId, string $colMonto, string $colFecha, ?string $colMetodo, ?string $colConcepto): array {
    // Seleccionar todas las columnas para devolver información completa de cada pago.
    $sql  = "SELECT * FROM $table $whereSql ORDER BY $colFecha DESC, " . $colId . " DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map(function ($r) use ($colId, $colMonto) {
        if (isset($r[$colId])) {
            $r[$colId] = (int)$r[$colId];
        }
        if (isset($r[$colMonto])) {
            $r[$colMonto] = (float)$r[$colMonto];
        }
        return $r;
    }, $rows);
}

// Determinar método y acción
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// ---------- LISTAR PAGOS ----------
if ($action === 'list') {
    try {
        $pagos = listPagos($pdo, $table, $whereSql, $params, $colId, $colMonto, $colFecha, $colMetodo, $colConcepto);
        json_out(true, ['data' => ['pagos' => $pagos, 'table' => $table]]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al listar pagos: ' . $e->getMessage()]);
    }
}

// ---------- CREAR PAGO ----------
if ($method === 'POST' && $action === 'create') {
    $monto     = (float)($_POST['monto'] ?? 0);
    $fecha = $_POST['fecha'] ?? '';

    $fecha = trim((string)$fecha);
    file_put_contents(
    __DIR__ . '/debug_pagos.log',
    print_r($_POST, true),
    FILE_APPEND
);

    if ($fecha === '') {
        json_out(false, ['error' => 'Debe indicar monto y fecha del pago.']);
    }
    $metodoInp = trim($_POST['metodo'] ?? '');
    $concepto  = trim($_POST['concepto'] ?? '');

    if ($monto <= 0 || $fecha === '') {
        json_out(false, ['error' => 'Debe indicar monto y fecha del pago.']);
    }

    // Construir campos y valores dinámicos para el insert
    $fields = [$colUser, $colMonto, $colFecha];
    $values = [':uid', ':monto', ':fecha'];
    $insP   = ['uid' => $requestUserId, 'monto' => $monto, 'fecha' => $fecha];

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
    if ($colMetodo) {
        $fields[] = $colMetodo;
        $values[] = ':metodo';
        $insP['metodo'] = $metodoInp !== '' ? $metodoInp : null;
    }
    if ($colConcepto) {
        $fields[] = $colConcepto;
        $values[] = ':concepto';
        $insP['concepto'] = $concepto !== '' ? $concepto : null;
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

        $pagos = listPagos($pdo, $table, $whereSql, $params, $colId, $colMonto, $colFecha, $colMetodo, $colConcepto);
        json_out(true, ['message' => 'Pago registrado.', 'data' => ['pagos' => $pagos]]);
    } catch (Throwable $e) {
        $msg = strpos($e->getMessage(), '1062') !== false ? 'Este pago ya está registrado.' : 'Error al registrar pago.';
        json_out(false, ['error' => $msg]);
    }
}

// ---------- ACTUALIZAR PAGO ----------
if ($method === 'POST' && $action === 'update') {
    $pagoId = (int)($_POST['pago_id'] ?? 0);
    $monto  = (float)($_POST['monto'] ?? 0);
    $fecha  = trim($_POST['fecha'] ?? '');
    $metodoInp = trim($_POST['metodo'] ?? '');
    $concepto  = trim($_POST['concepto'] ?? '');

    if ($pagoId <= 0 || $monto <= 0 || $fecha === '') {
        json_out(false, ['error' => 'Datos de pago inválidos.']);
    }

    $updFields = [$colMonto . ' = :monto', $colFecha . ' = :fecha'];
    $updData   = ['monto' => $monto, 'fecha' => $fecha, 'id' => $pagoId, 'uid' => $requestUserId];
    if ($colMetodo) {
        $updFields[]       = "$colMetodo = :metodo";
        $updData['metodo'] = $metodoInp !== '' ? $metodoInp : null;
    }
    if ($colConcepto) {
        $updFields[]         = "$colConcepto = :concepto";
        $updData['concepto'] = $concepto !== '' ? $concepto : null;
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
        $whereUpd[]        = "$colUnidad = :unid";
        $updData['unid']   = $unidad_id;
    }

    try {
        $stmt = $pdo->prepare("UPDATE $table SET " . implode(', ', $updFields) . " WHERE " . implode(' AND ', $whereUpd));
        $stmt->execute($updData);

        $pagos = listPagos($pdo, $table, $whereSql, $params, $colId, $colMonto, $colFecha, $colMetodo, $colConcepto);
        json_out(true, ['message' => 'Pago actualizado.', 'data' => ['pagos' => $pagos]]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al actualizar pago.']);
    }
}

// ---------- ELIMINAR PAGO ----------
if ($method === 'POST' && $action === 'delete') {
    $pagoId = (int)($_POST['pago_id'] ?? 0);
    if ($pagoId <= 0) {
        json_out(false, ['error' => 'ID de pago inválido.']);
    }

    $delData  = ['id' => $pagoId, 'uid' => $requestUserId];
    $whereDel = ["$colId = :id", "$colUser = :uid"];
    if ($colResid && $residencial_id > 0) {
        $whereDel[]   = "$colResid = :rid";
        $delData['rid'] = $residencial_id;
    }
    if ($colUnidad && $unidad_id > 0) {
        $whereDel[]     = "$colUnidad = :unid";
        $delData['unid'] = $unidad_id;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE " . implode(' AND ', $whereDel));
        $stmt->execute($delData);

        $pagos = listPagos($pdo, $table, $whereSql, $params, $colId, $colMonto, $colFecha, $colMetodo, $colConcepto);
        json_out(true, ['message' => 'Pago eliminado.', 'data' => ['pagos' => $pagos]]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al eliminar pago.']);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {

    $monto  = (float)($_POST['monto'] ?? 0);
    $fecha  = trim((string)($_POST['fecha'] ?? ''));
    $metodo = trim($_POST['metodo'] ?? 'efectivo');
    $concepto = trim($_POST['concepto'] ?? '');
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($monto <= 0 || $fecha === '' || !$userId) {
        json_out(false, ['error' => 'Debe indicar monto y fecha del pago.']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO pagos (user_id, monto, fecha, metodo, concepto)
        VALUES (:uid, :monto, :fecha, :metodo, :concepto)
    ");

    $stmt->execute([
        'uid' => $userId,
        'monto' => $monto,
        'fecha' => $fecha,
        'metodo' => $metodo,
        'concepto' => $concepto
    ]);

    json_out(true);
}


// Acción no reconocida
json_out(false, ['error' => 'Acción no soportada.']);