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
require_once __DIR__ . '/../../../config/resident_access.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();
$uid = (int)($user['id'] ?? 0);
$residencialId = service_profile_resolve_residencial_id_for_user($pdo, $uid);
service_profile_api_require_module($pdo, $residencialId, 'admin_residencial', 'residentes', 'Los pagos de residentes no están habilitados para este cliente.');

$candidateTables = [
    'pagos_residentes',
    'pagos',
    'pagos_usuarios',
    'residentes_pagos',
    'pagos_users',
];

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

function getColsMeta(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :t
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute(['t' => $table]);

    $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $name = $r['COLUMN_NAME'];
        $out[$name] = [
            'nullable' => ($r['IS_NULLABLE'] === 'YES'),
            'default'  => $r['COLUMN_DEFAULT'],
            'extra'    => $r['EXTRA'],
        ];
    }

    return $out;
}

$meta = getColsMeta($pdo, $table);
$cols = array_keys($meta);

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

if (!$colId || !$colUser || !$colMonto || !$colFecha) {
    json_out(false, ['error' => 'La tabla de pagos no tiene columnas mínimas (id, user, monto, fecha).']);
}

function listPagos(
    PDO $pdo,
    string $table,
    string $whereSql,
    array $params,
    string $colId,
    string $colMonto,
    string $colFecha,
    ?string $colMetodo,
    ?string $colConcepto
): array {
    $sql = "SELECT * FROM $table $whereSql ORDER BY $colFecha DESC, $colId DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(function ($r) use ($colId, $colMonto, $colFecha, $colMetodo, $colConcepto) {
        return [
            'id'         => (int)$r[$colId],
            'monto'      => (float)$r[$colMonto],
            'fecha_pago' => (string)$r[$colFecha],
            'metodo'     => $colMetodo ? (string)($r[$colMetodo] ?? '') : '',
            'concepto'   => $colConcepto ? (string)($r[$colConcepto] ?? '') : '',
        ];
    }, $rows);
}

$requestUserId = $uid;
$param = $_GET['user_id'] ?? $_POST['user_id'] ?? null;
if ($param && ctype_digit((string)$param)) {
    $requestUserId = (int)$param;
}

$ctx = get_context_for_user($pdo, $requestUserId);
$residencial_id = $ctx['residencial_id'];
$unidad_id = $ctx['unidad_id'];
resident_access_ensure_schema($pdo);

$whereParts = ["$colUser = :uid"];
$params = ['uid' => $requestUserId];

if ($colResid && $residencial_id > 0) {
    $whereParts[] = "$colResid = :rid";
    $params['rid'] = $residencial_id;
}

if ($colUnidad && $unidad_id > 0) {
    $whereParts[] = "$colUnidad = :unid";
    $params['unid'] = $unidad_id;
}

$whereSql = 'WHERE ' . implode(' AND ', $whereParts);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($action === 'list') {
    try {
        $pagos = listPagos(
            $pdo,
            $table,
            $whereSql,
            $params,
            $colId,
            $colMonto,
            $colFecha,
            $colMetodo,
            $colConcepto
        );

        json_out(true, [
            'data' => [
                'pagos' => $pagos,
                'table' => $table,
            ],
        ]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al listar pagos: ' . $e->getMessage()]);
    }
}

if ($method === 'POST' && $action === 'create') {
    $monto = (float)($_POST['monto'] ?? 0);
    $fecha = trim((string)($_POST['fecha'] ?? ''));
    $metodoInp = trim((string)($_POST['metodo'] ?? ''));
    $concepto = trim((string)($_POST['concepto'] ?? ''));

    if ($monto <= 0 || $fecha === '') {
        json_out(false, ['error' => 'Debe indicar monto y fecha del pago.']);
    }

    $fields = [$colUser, $colMonto, $colFecha];
    $values = [':uid', ':monto', ':fecha'];
    $insP = [
        'uid'   => $requestUserId,
        'monto' => $monto,
        'fecha' => $fecha,
    ];

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
        $conceptMeta = $meta[$colConcepto] ?? ['nullable' => true, 'default' => null];
        $conceptIsEmpty = ($concepto === '');

        if (!$conceptIsEmpty) {
            $fields[] = $colConcepto;
            $values[] = ':concepto';
            $insP['concepto'] = $concepto;
        } elseif (!empty($conceptMeta['nullable'])) {
            $fields[] = $colConcepto;
            $values[] = ':concepto';
            $insP['concepto'] = null;
        } elseif (($conceptMeta['default'] ?? null) !== null) {
            // No incluir la columna para que aplique el DEFAULT.
        } else {
            $fields[] = $colConcepto;
            $values[] = ':concepto';
            $insP['concepto'] = 'Pago registrado';
        }
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

        $residentUnitId = 0;
        if ($requestUserId > 0 && $residencial_id > 0) {
            // Toca la relación residente↔unidad más reciente del servicio para recalcular whitelist en tiempo real.
            $stmtResidentUnit = $pdo->prepare("
                SELECT ru.id
                FROM residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                WHERE ru.user_id = :uid
                  AND un.residencial_id = :rid
                ORDER BY ru.id DESC
                LIMIT 1
            ");
            $stmtResidentUnit->execute([
                'uid' => $requestUserId,
                'rid' => $residencial_id,
            ]);
            $residentUnitId = (int)($stmtResidentUnit->fetchColumn() ?: 0);
        }

        if ($residentUnitId > 0) {
            resident_access_touch($pdo, $residentUnitId);
        }

        $pagos = listPagos(
            $pdo,
            $table,
            $whereSql,
            $params,
            $colId,
            $colMonto,
            $colFecha,
            $colMetodo,
            $colConcepto
        );

        $residenteActualizado = null;
        if ($requestUserId > 0 && $residencial_id > 0) {
            $statusRow = resident_access_status_for_user($pdo, $requestUserId, (int)$residencial_id);
            if ($statusRow) {
                $access = (array)($statusRow['access'] ?? []);
                $residenteActualizado = [
                    'user_id' => (int)($statusRow['user_id'] ?? 0),
                    'resid_unid_id' => (int)($statusRow['resid_unid_id'] ?? 0),
                    'unidad_id' => (int)($statusRow['unidad_id'] ?? 0),
                    'unidad_clave' => (string)($statusRow['unidad_clave'] ?? '—'),
                    'activo_servicio' => (int)($statusRow['activo_servicio'] ?? 1),
                    'acceso_baneado_manual' => (int)($statusRow['acceso_baneado_manual'] ?? 0),
                    'acceso_baneo_motivo' => (string)($statusRow['acceso_baneo_motivo'] ?? ''),
                    'is_active' => (int)($statusRow['is_active'] ?? 1),

                    'access_status' => (string)($access['status'] ?? ''),
                    'access_label' => (string)($access['label'] ?? ''),
                    'access_reason' => (string)($access['reason'] ?? ''),
                    'allow_direct_access' => (bool)($access['allow_direct_access'] ?? false),
                    'payment_current' => (bool)($access['payment_current'] ?? false),
                    'payment_latest_date' => $access['payment_latest_date'] ?? null,
                    'payment_latest_amount' => $access['payment_latest_amount'] ?? null,
                ];
            }
        }

        json_out(true, [
            'message' => 'Pago registrado.',
            'data' => [
                'pagos' => $pagos,
                'residente_actualizado' => $residenteActualizado,
            ],
        ]);
    } catch (Throwable $e) {
        $msg = strpos($e->getMessage(), '1062') !== false
            ? 'Este pago ya está registrado.'
            : 'Error al registrar pago.';

        json_out(false, ['error' => $msg]);
    }
}

if ($method === 'POST' && $action === 'update') {
    $pagoId = (int)($_POST['pago_id'] ?? 0);
    $monto = (float)($_POST['monto'] ?? 0);
    $fecha = trim((string)($_POST['fecha'] ?? ''));
    $metodoInp = trim((string)($_POST['metodo'] ?? ''));
    $concepto = trim((string)($_POST['concepto'] ?? ''));

    if ($pagoId <= 0 || $monto <= 0 || $fecha === '') {
        json_out(false, ['error' => 'Datos de pago inválidos.']);
    }

    $updFields = [
        "$colMonto = :monto",
        "$colFecha = :fecha",
    ];

    $updData = [
        'monto' => $monto,
        'fecha' => $fecha,
        'id'    => $pagoId,
        'uid'   => $requestUserId,
    ];

    if ($colMetodo) {
        $updFields[] = "$colMetodo = :metodo";
        $updData['metodo'] = $metodoInp !== '' ? $metodoInp : null;
    }

    if ($colConcepto) {
        $updFields[] = "$colConcepto = :concepto";
        $updData['concepto'] = $concepto !== '' ? $concepto : null;
    }

    if ($colUpdatedAt) {
        $updFields[] = "$colUpdatedAt = NOW()";
    }

    $whereUpd = [
        "$colId = :id",
        "$colUser = :uid",
    ];

    if ($colResid && $residencial_id > 0) {
        $whereUpd[] = "$colResid = :rid";
        $updData['rid'] = $residencial_id;
    }

    if ($colUnidad && $unidad_id > 0) {
        $whereUpd[] = "$colUnidad = :unid";
        $updData['unid'] = $unidad_id;
    }

    try {
        $stmt = $pdo->prepare(
            "UPDATE $table SET " . implode(', ', $updFields) .
            " WHERE " . implode(' AND ', $whereUpd)
        );
        $stmt->execute($updData);

        $pagos = listPagos(
            $pdo,
            $table,
            $whereSql,
            $params,
            $colId,
            $colMonto,
            $colFecha,
            $colMetodo,
            $colConcepto
        );

        json_out(true, [
            'message' => 'Pago actualizado.',
            'data' => ['pagos' => $pagos],
        ]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al actualizar pago.']);
    }
}

if ($method === 'POST' && $action === 'delete') {
    $pagoId = (int)($_POST['pago_id'] ?? 0);
    if ($pagoId <= 0) {
        json_out(false, ['error' => 'ID de pago inválido.']);
    }

    $delData = [
        'id'  => $pagoId,
        'uid' => $requestUserId,
    ];

    $whereDel = [
        "$colId = :id",
        "$colUser = :uid",
    ];

    if ($colResid && $residencial_id > 0) {
        $whereDel[] = "$colResid = :rid";
        $delData['rid'] = $residencial_id;
    }

    if ($colUnidad && $unidad_id > 0) {
        $whereDel[] = "$colUnidad = :unid";
        $delData['unid'] = $unidad_id;
    }

    try {
        $stmt = $pdo->prepare(
            "DELETE FROM $table WHERE " . implode(' AND ', $whereDel)
        );
        $stmt->execute($delData);

        $pagos = listPagos(
            $pdo,
            $table,
            $whereSql,
            $params,
            $colId,
            $colMonto,
            $colFecha,
            $colMetodo,
            $colConcepto
        );

        json_out(true, [
            'message' => 'Pago eliminado.',
            'data' => ['pagos' => $pagos],
        ]);
    } catch (Throwable $e) {
        json_out(false, ['error' => 'Error al eliminar pago.']);
    }
}

json_out(false, ['error' => 'Acción no soportada.']);
