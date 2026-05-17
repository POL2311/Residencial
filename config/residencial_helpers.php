<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

if (!function_exists('require_residencial_id')) {
    function require_residencial_id(PDO $pdo, int $adminId): int {
        $stmt = $pdo->prepare("
            SELECT residencial_id
            FROM usuarios_residenciales
            WHERE user_id = ?
            ORDER BY es_principal DESC, created_at ASC
            LIMIT 1
        ");
        $stmt->execute([$adminId]);

        $rid = (int)$stmt->fetchColumn();
        if (!$rid) {
            json_out(false, ['error' => 'No tienes residencial asignado.']);
        }

        return $rid;
    }
}

if (!function_exists('get_context_for_user')) {
    function get_context_for_user(PDO $pdo, int $userId): array {
        $stmt = $pdo->prepare("
            SELECT ur.residencial_id,
                   ru.unidad_id
            FROM usuarios_residenciales ur
            LEFT JOIN residentes_unidades ru ON ru.user_id = ur.user_id
            WHERE ur.user_id = :uid
            ORDER BY ur.es_principal DESC, ur.created_at ASC
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'residencial_id' => (int)($row['residencial_id'] ?? 0),
            'unidad_id'      => (int)($row['unidad_id'] ?? 0),
        ];
    }
}

if (!function_exists('resolve_resident_context')) {
    function resolve_resident_context(PDO $pdo, int $userId, bool $requireUnit = true): array {
        $stmt = $pdo->prepare("
            SELECT
                ur.residencial_id,
                r.nombre AS residencial_nombre,
                r.telefono_contacto,
                ru.unidad_id,
                ru.activo AS unidad_activa,
                u.id AS unidad_exists,
                u.residencial_id AS unidad_residencial_id,
                u.clave AS unidad_clave
            FROM usuarios_residenciales ur
            LEFT JOIN residenciales r ON r.id = ur.residencial_id
            LEFT JOIN residentes_unidades ru ON ru.user_id = ur.user_id
            LEFT JOIN unidades u ON u.id = ru.unidad_id
            WHERE ur.user_id = :uid
            ORDER BY ur.es_principal DESC, ru.activo DESC, ur.created_at ASC, ru.created_at ASC
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return [
                'ok' => false,
                'http_status' => 403,
                'error' => 'Tu cuenta no está ligada a un residencial.',
                'missing' => ['residencial'],
                'ctx' => null,
            ];
        }

        $residencialId = (int)($row['residencial_id'] ?? 0);
        $unidadId = (int)($row['unidad_id'] ?? 0);
        $unidadExists = (int)($row['unidad_exists'] ?? 0);
        $unidadActiva = (int)($row['unidad_activa'] ?? 0);
        $unidadResidencialId = (int)($row['unidad_residencial_id'] ?? 0);

        $ctx = [
            'residencial_id' => $residencialId,
            'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
            'caseta_phone' => $row['telefono_contacto'] ?? null,
            'unidad_id' => $unidadId,
            'unidad_clave' => (string)($row['unidad_clave'] ?? ''),
            'unidad_activa' => $unidadActiva,
        ];

        $missing = [];
        if ($residencialId <= 0) {
            $missing[] = 'residencial';
        }
        if ($requireUnit && $unidadId <= 0) {
            $missing[] = 'unidad';
        }

        if ($requireUnit && $unidadId > 0 && $unidadActiva !== 1) {
            return [
                'ok' => false,
                'http_status' => 403,
                'error' => 'Tu cuenta no tiene una unidad activa asignada.',
                'missing' => ['unidad_activa'],
                'ctx' => $ctx,
            ];
        }

        if ($requireUnit && $unidadId > 0 && $unidadExists <= 0) {
            return [
                'ok' => false,
                'http_status' => 422,
                'error' => 'La unidad asignada a tu cuenta no existe en la base de datos.',
                'missing' => ['unidad_invalida'],
                'ctx' => $ctx,
            ];
        }

        if ($requireUnit && $unidadId > 0 && $unidadResidencialId > 0 && $unidadResidencialId !== $residencialId) {
            return [
                'ok' => false,
                'http_status' => 422,
                'error' => 'La unidad asignada no pertenece al mismo residencial de tu cuenta.',
                'missing' => ['unidad_residencial_mismatch'],
                'ctx' => $ctx,
            ];
        }

        if ($missing) {
            $label = (count($missing) > 1)
                ? 'un residencial y una unidad'
                : ($missing[0] === 'unidad' ? 'una unidad' : 'un residencial');

            return [
                'ok' => false,
                'http_status' => 403,
                'error' => 'Tu cuenta no está ligada a ' . $label . '.',
                'missing' => $missing,
                'ctx' => $ctx,
            ];
        }

        return [
            'ok' => true,
            'http_status' => 200,
            'error' => null,
            'missing' => [],
            'ctx' => $ctx,
        ];
    }
}

if (!function_exists('residential_home_services_schema_ensure')) {
    function residential_home_services_schema_ensure(PDO $pdo): void {
        $targets = [
            'home_servicios_globales',
            'home_servicios_residenciales',
        ];

        foreach ($targets as $table) {
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table
                ");
                $stmt->execute(['table' => $table]);
                $exists = (int)$stmt->fetchColumn() > 0;
                if (!$exists) {
                    continue;
                }

                $col = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table
                      AND COLUMN_NAME = 'perfil_url'
                ");
                $col->execute(['table' => $table]);
                $hasPerfilUrl = (int)$col->fetchColumn() > 0;

                if (!$hasPerfilUrl) {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN perfil_url VARCHAR(500) DEFAULT NULL AFTER link_url");
                }
            } catch (Throwable $e) {
                // keep backward compatibility if schema introspection is unavailable
            }
        }
    }
}

if (!function_exists('resolve_autos_table')) {
    function resolve_autos_table(PDO $pdo): ?string {
        $candidateTables = [
            'autos',
            'autos_residentes',
            'autos_usuarios',
            'vehiculos',
            'vehiculos_residentes',
            'residentes_autos',
        ];

        foreach ($candidateTables as $table) {
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM INFORMATION_SCHEMA.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table
                ");
                $stmt->execute(['table' => $table]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return $table;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return null;
    }
}

if (!function_exists('resident_vehicle_access_schema_ensure')) {
    function resident_vehicle_access_schema_ensure(PDO $pdo, ?string $autosTable = null): void {
        try {
            $autosTable = $autosTable ?: resolve_autos_table($pdo);
            if ($autosTable) {
                $col = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = :table
                      AND COLUMN_NAME = 'tag_id'
                ");
                $col->execute(['table' => $autosTable]);
                if ((int)$col->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE {$autosTable} ADD COLUMN tag_id VARCHAR(120) DEFAULT NULL AFTER placas");
                }
            }

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS accesos_residentes (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    residente_id INT(11) NOT NULL,
                    unidad_id INT(11) DEFAULT NULL,
                    auto_id INT(11) DEFAULT NULL,
                    tag_id VARCHAR(120) DEFAULT NULL,
                    lector_check_id BIGINT UNSIGNED DEFAULT NULL,
                    tipo_movimiento ENUM('ingreso','egreso') NOT NULL,
                    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    fuente VARCHAR(40) NOT NULL DEFAULT 'lector_tag',
                    metadata_json LONGTEXT DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_acc_res_residencial_fecha (residencial_id, fecha_hora),
                    KEY idx_acc_res_residente_fecha (residente_id, fecha_hora),
                    KEY idx_acc_res_auto_fecha (auto_id, fecha_hora),
                    KEY idx_acc_res_tag_fecha (tag_id, fecha_hora),
                    KEY idx_acc_res_lector_check (lector_check_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Throwable $e) {
            // Compatibilidad hacia atrás: no romper si la introspección está restringida.
        }
    }
}

if (!function_exists('resident_vehicle_access_list')) {
    function resident_vehicle_access_list(PDO $pdo, array $options = []): array {
        $autosTable = $options['autos_table'] ?? resolve_autos_table($pdo) ?? 'autos';
        $residencialId = (int)($options['residencial_id'] ?? 0);
        $limit = max(1, min(500, (int)($options['limit'] ?? 100)));
        $movement = trim((string)($options['tipo_movimiento'] ?? ''));
        $serviceId = (int)($options['service_id'] ?? 0);
        $since = $options['since'] ?? null;
        $until = $options['until'] ?? null;

        $where = ['1=1'];
        $params = [];

        if ($residencialId > 0) {
            $where[] = 'ar.residencial_id = :rid';
            $params['rid'] = $residencialId;
        }
        if ($serviceId > 0) {
            $where[] = 'ar.residencial_id = :service_id';
            $params['service_id'] = $serviceId;
        }
        if ($movement !== '' && in_array($movement, ['ingreso', 'egreso'], true)) {
            $where[] = 'ar.tipo_movimiento = :tipo_movimiento';
            $params['tipo_movimiento'] = $movement;
        }
        if ($since instanceof DateTimeInterface) {
            $where[] = 'ar.fecha_hora >= :since';
            $params['since'] = $since->format('Y-m-d H:i:s');
        }
        if ($until instanceof DateTimeInterface) {
            $where[] = 'ar.fecha_hora <= :until';
            $params['until'] = $until->format('Y-m-d H:i:s');
        }

        $sql = "
            SELECT
                ar.id,
                ar.residencial_id,
                ar.residente_id,
                ar.unidad_id,
                ar.auto_id,
                ar.tag_id,
                ar.lector_check_id,
                ar.tipo_movimiento,
                ar.fecha_hora,
                ar.fuente,
                ar.metadata_json,
                r.nombre AS residencial_nombre,
                u.clave AS unidad_clave,
                usr.name AS residente_nombre,
                a.placas,
                a.modelo,
                a.color
            FROM accesos_residentes ar
            LEFT JOIN residenciales r ON r.id = ar.residencial_id
            LEFT JOIN users usr ON usr.id = ar.residente_id
            LEFT JOIN unidades u ON u.id = ar.unidad_id
            LEFT JOIN {$autosTable} a ON a.id = ar.auto_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ar.fecha_hora DESC, ar.id DESC
            LIMIT {$limit}
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
