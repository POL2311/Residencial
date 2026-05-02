<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

if (!function_exists('resident_access_column_exists')) {
    function resident_access_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND COLUMN_NAME = :column
        ");
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('resident_access_ensure_schema')) {
    function resident_access_ensure_schema(PDO $pdo): void
    {
        if (tableExists($pdo, 'residentes_unidades')) {
            if (!resident_access_column_exists($pdo, 'residentes_unidades', 'acceso_baneado_manual')) {
                $pdo->exec("ALTER TABLE residentes_unidades ADD COLUMN acceso_baneado_manual TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!resident_access_column_exists($pdo, 'residentes_unidades', 'acceso_baneo_motivo')) {
                $pdo->exec("ALTER TABLE residentes_unidades ADD COLUMN acceso_baneo_motivo VARCHAR(255) NULL DEFAULT NULL");
            }
            if (!resident_access_column_exists($pdo, 'residentes_unidades', 'acceso_baneado_at')) {
                $pdo->exec("ALTER TABLE residentes_unidades ADD COLUMN acceso_baneado_at DATETIME NULL DEFAULT NULL");
            }
            if (!resident_access_column_exists($pdo, 'residentes_unidades', 'acceso_estado_actualizado_at')) {
                $pdo->exec("ALTER TABLE residentes_unidades ADD COLUMN acceso_estado_actualizado_at DATETIME NULL DEFAULT NULL");
            }
        }

        if (tableExists($pdo, 'accesos_guardia')) {
            if (!resident_access_column_exists($pdo, 'accesos_guardia', 'origen_acceso')) {
                $pdo->exec("ALTER TABLE accesos_guardia ADD COLUMN origen_acceso ENUM('visita','residente_directo') NOT NULL DEFAULT 'visita'");
            }
            if (!resident_access_column_exists($pdo, 'accesos_guardia', 'residente_id')) {
                $pdo->exec("ALTER TABLE accesos_guardia ADD COLUMN residente_id INT(11) NULL DEFAULT NULL");
            }
            if (!resident_access_column_exists($pdo, 'accesos_guardia', 'unidad_id')) {
                $pdo->exec("ALTER TABLE accesos_guardia ADD COLUMN unidad_id INT(11) NULL DEFAULT NULL");
            }
        }
    }
}

if (!function_exists('resident_access_touch')) {
    function resident_access_touch(PDO $pdo, int $residentUnitId): void
    {
        if ($residentUnitId <= 0) {
            return;
        }

        resident_access_ensure_schema($pdo);

        $stmt = $pdo->prepare("
            UPDATE residentes_unidades
            SET acceso_estado_actualizado_at = NOW()
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $residentUnitId]);
    }
}

if (!function_exists('resident_access_payment_meta')) {
    function resident_access_payment_meta(PDO $pdo): ?array
    {
        $candidateTables = [
            'pagos_residentes',
            'pagos',
            'pagos_usuarios',
            'residentes_pagos',
            'pagos_users',
        ];

        $table = null;
        foreach ($candidateTables as $candidate) {
            if (tableExists($pdo, $candidate)) {
                $table = $candidate;
                break;
            }
        }

        if (!$table) {
            return null;
        }

        $cols = getCols($pdo, $table);

        $colUser = pickCol($cols, ['residente_id', 'user_id', 'usuario_id', 'owner_user_id', 'propietario_user_id']);
        $colResid = pickCol($cols, ['residencial_id', 'residencia_id', 'residential_id']);
        $colUnidad = pickCol($cols, ['unidad_id', 'unit_id']);
        $colFecha = pickCol($cols, ['fecha_pago', 'fecha', 'payment_date', 'date']);
        $colMonto = pickCol($cols, ['monto', 'amount', 'importe']);
        $colActivo = pickCol($cols, ['activo', 'active', 'estatus', 'status']);
        $colConcepto = pickCol($cols, ['concepto', 'concept', 'descripcion', 'description', 'nota']);

        if (!$colUser || !$colFecha) {
            return null;
        }

        return [
            'table' => $table,
            'col_user' => $colUser,
            'col_residencial' => $colResid,
            'col_unidad' => $colUnidad,
            'col_fecha' => $colFecha,
            'col_monto' => $colMonto,
            'col_activo' => $colActivo,
            'col_concepto' => $colConcepto,
        ];
    }
}

if (!function_exists('resident_access_payment_status')) {
    function resident_access_payment_status(PDO $pdo, int $userId, int $residencialId = 0, int $unidadId = 0): array
    {
        $meta = resident_access_payment_meta($pdo);
        if (!$meta) {
            return [
                'current' => false,
                'count' => 0,
                'latest_payment_date' => null,
                'latest_amount' => null,
                'concept' => null,
            ];
        }

        $tz = new DateTimeZone('America/Mexico_City');
        $start = new DateTime('first day of this month 00:00:00', $tz);
        $end = new DateTime('last day of this month 23:59:59', $tz);

        $where = ["{$meta['col_user']} = :uid"];
        $params = [
            'uid' => $userId,
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
        ];

        if ($meta['col_residencial'] && $residencialId > 0) {
            $where[] = "{$meta['col_residencial']} = :rid";
            $params['rid'] = $residencialId;
        }

        if ($meta['col_unidad'] && $unidadId > 0) {
            $where[] = "{$meta['col_unidad']} = :unidad";
            $params['unidad'] = $unidadId;
        }

        if ($meta['col_activo']) {
            $where[] = "({$meta['col_activo']} = 1 OR {$meta['col_activo']} IS NULL)";
        }

        $whereMonth = $where;
        $whereMonth[] = "{$meta['col_fecha']} BETWEEN :start AND :end";

        $sql = "
            SELECT *
            FROM {$meta['table']}
            WHERE " . implode(' AND ', $whereMonth) . "
            ORDER BY {$meta['col_fecha']} DESC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'current' => $row !== null,
            'count' => $row ? 1 : 0,
            'latest_payment_date' => $row[$meta['col_fecha']] ?? null,
            'latest_amount' => $meta['col_monto'] ? ($row[$meta['col_monto']] ?? null) : null,
            'concept' => $meta['col_concepto'] ? ($row[$meta['col_concepto']] ?? null) : null,
        ];
    }
}

if (!function_exists('resident_access_status')) {
    function resident_access_status(PDO $pdo, array $resident): array
    {
        resident_access_ensure_schema($pdo);

        $userId = (int)($resident['user_id'] ?? 0);
        $residencialId = (int)($resident['residencial_id'] ?? 0);
        $unidadId = (int)($resident['unidad_id'] ?? 0);
        $serviceActive = (int)($resident['activo_servicio'] ?? $resident['activo'] ?? 1) === 1;
        $accountActive = (int)($resident['is_active'] ?? 1) === 1;
        $manualBan = (int)($resident['acceso_baneado_manual'] ?? 0) === 1;
        $manualBanReason = trim((string)($resident['acceso_baneo_motivo'] ?? ''));

        $payment = resident_access_payment_status($pdo, $userId, $residencialId, $unidadId);

        $status = 'lista_blanca';
        $label = 'Lista blanca';
        $reason = 'Al corriente y sin restricciones manuales.';
        $allow = true;

        if (!$accountActive) {
            $status = 'lista_negra_cuenta';
            $label = 'Lista negra';
            $reason = 'La cuenta del residente está inactiva.';
            $allow = false;
        } elseif (!$serviceActive) {
            $status = 'lista_negra_suspendido';
            $label = 'Lista negra';
            $reason = 'El residente está suspendido por administración.';
            $allow = false;
        } elseif ($manualBan) {
            $status = 'lista_negra_baneo';
            $label = 'Lista negra';
            $reason = $manualBanReason !== '' ? $manualBanReason : 'Bloqueado manualmente por administración.';
            $allow = false;
        } elseif (!$payment['current']) {
            $status = 'lista_negra_adeudo';
            $label = 'Lista negra';
            $reason = 'No hay pago activo registrado en el mes actual.';
            $allow = false;
        }

        return [
            'allow_direct_access' => $allow,
            'status' => $status,
            'label' => $label,
            'reason' => $reason,
            'is_whitelist' => $allow,
            'is_blacklist' => !$allow,
            'payment_current' => (bool)$payment['current'],
            'payment_latest_date' => $payment['latest_payment_date'],
            'payment_latest_amount' => $payment['latest_amount'],
            'manual_ban' => $manualBan,
            'manual_ban_reason' => $manualBanReason,
        ];
    }
}

if (!function_exists('resident_access_status_for_user')) {
    function resident_access_status_for_user(PDO $pdo, int $userId, int $residencialId): ?array
    {
        resident_access_ensure_schema($pdo);

        $stmt = $pdo->prepare("
            SELECT
                u.id AS user_id,
                u.name,
                u.email,
                u.telefono,
                u.is_active,
                ur.residencial_id,
                ru.id AS resid_unid_id,
                ru.unidad_id,
                ru.activo AS activo_servicio,
                ru.acceso_baneado_manual,
                ru.acceso_baneo_motivo,
                un.clave AS unidad_clave
            FROM users u
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            LEFT JOIN residentes_unidades ru ON ru.user_id = u.id
            LEFT JOIN unidades un ON un.id = ru.unidad_id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE u.id = :uid
              AND ur.residencial_id = :rid
              AND t.nombre = 'residente'
            ORDER BY ur.es_principal DESC, ru.activo DESC, ru.es_titular DESC, ru.id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'uid' => $userId,
            'rid' => $residencialId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return null;
        }

        return array_merge($row, [
            'access' => resident_access_status($pdo, $row),
        ]);
    }
}

if (!function_exists('resident_access_notifications')) {
    function resident_access_notifications(PDO $pdo, int $residencialId, int $limit = 10): array
    {
        resident_access_ensure_schema($pdo);

        $limit = max(1, min(50, $limit));

        $summary = [
            'total' => 0,
            'latest_id' => 0,
            'latest_updated_at' => null,
            'items' => [],
        ];

        if (!tableExists($pdo, 'residentes_unidades') || !resident_access_column_exists($pdo, 'residentes_unidades', 'acceso_estado_actualizado_at')) {
            return $summary;
        }

        $stmtCount = $pdo->prepare("
            SELECT
                COUNT(*) AS total,
                MAX(id) AS latest_id,
                MAX(acceso_estado_actualizado_at) AS latest_updated_at
            FROM residentes_unidades ru
            JOIN unidades un ON un.id = ru.unidad_id
            WHERE un.residencial_id = :rid
              AND ru.acceso_estado_actualizado_at IS NOT NULL
        ");
        $stmtCount->execute(['rid' => $residencialId]);
        $countRow = $stmtCount->fetch(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->prepare("
            SELECT
                ru.id AS resid_unid_id,
                ru.user_id,
                ru.unidad_id,
                ru.activo AS activo_servicio,
                ru.acceso_baneado_manual,
                ru.acceso_baneo_motivo,
                ru.acceso_estado_actualizado_at,
                u.name,
                u.email,
                u.telefono,
                u.is_active,
                un.residencial_id,
                un.clave AS unidad_clave
            FROM residentes_unidades ru
            JOIN users u ON u.id = ru.user_id
            JOIN unidades un ON un.id = ru.unidad_id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE un.residencial_id = :rid
              AND t.nombre = 'residente'
              AND ru.acceso_estado_actualizado_at IS NOT NULL
            ORDER BY ru.acceso_estado_actualizado_at DESC, ru.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['rid' => $residencialId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $items = array_map(static function (array $row) use ($pdo): array {
            $access = resident_access_status($pdo, $row);
            $permitido = (bool)($access['allow_direct_access'] ?? false);

            return [
                'id' => (int)($row['resid_unid_id'] ?? 0),
                'resident_id' => (int)($row['user_id'] ?? 0),
                'resident_name' => (string)($row['name'] ?? '—'),
                'unidad_clave' => (string)($row['unidad_clave'] ?? '—'),
                'updated_at' => $row['acceso_estado_actualizado_at'] ?? null,
                'status' => $permitido ? 'permitido' : 'bloqueado',
                'status_label' => $permitido ? 'Permitido' : 'Bloqueado',
                'reason' => (string)($access['reason'] ?? ''),
                'access_status' => (string)($access['status'] ?? ''),
            ];
        }, $rows);

        return [
            'total' => (int)($countRow['total'] ?? 0),
            'latest_id' => (int)($countRow['latest_id'] ?? 0),
            'latest_updated_at' => $countRow['latest_updated_at'] ?? null,
            'items' => $items,
        ];
    }
}
