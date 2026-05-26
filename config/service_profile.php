<?php
declare(strict_types=1);

require_once __DIR__ . '/app_security.php';
require_once __DIR__ . '/operational_mode.php';

if (!function_exists('service_profile_role_flags')) {
    function service_profile_role_flags(): array
    {
        return [
            'habilita_admin_operativo',
            'habilita_guardia',
            'habilita_residente',
        ];
    }
}

if (!function_exists('service_profile_module_flags')) {
    function service_profile_module_flags(): array
    {
        return [
            'habilita_unidades',
            'habilita_residentes_catalogo',
            'habilita_guardias_catalogo',
            'habilita_guardias_admin_actions',
            'habilita_autos',
            'habilita_visitas_residente',
            'habilita_paqueteria',
            'habilita_pagos',
            'habilita_comunicados',
            'habilita_servicios_directorio',
            'habilita_control_acceso',
            'habilita_incidencias',
            'habilita_personal_recurrente',
            'habilita_visitantes_rapidos',
            'habilita_materiales',
            'habilita_solicitudes_pendientes',
            'habilita_bitacora_operativa',
            'habilita_herramientas',
            'habilita_reportes_operativos',
            'habilita_rondines',
            'habilita_proveedores',
            'habilita_ordenes_servicio',
        ];
    }
}

if (!function_exists('service_profile_all_flags')) {
    function service_profile_all_flags(): array
    {
        return array_merge(service_profile_role_flags(), service_profile_module_flags());
    }
}

if (!function_exists('service_profile_allowed_presets')) {
    function service_profile_allowed_presets(): array
    {
        return ['residencial', 'empresa', 'obra', 'comercio', 'servicio', 'retailops'];
    }
}

if (!function_exists('service_profile_preset_metadata')) {
    function service_profile_preset_metadata(): array
    {
        return [
            'residencial' => [
                'label' => 'Residencial',
                'description' => 'Operación residencial para residentes, guardias, accesos, comunicados, pagos y servicios.',
            ],
            'empresa' => [
                'label' => 'Empresa',
                'description' => 'Control operativo para oficinas, empleados, accesos e incidencias.',
            ],
            'obra' => [
                'label' => 'Obra',
                'description' => 'Control de obra para personal, materiales, accesos e incidencias.',
            ],
            'comercio' => [
                'label' => 'Comercio',
                'description' => 'Control operativo para comercios con accesos, guardias e incidencias.',
            ],
            'servicio' => [
                'label' => 'Servicio',
                'description' => 'Operación ligera para servicios con guardias, accesos, incidencias y bitácora.',
            ],
            'retailops' => [
                'label' => 'RetailOps',
                'description' => 'Control operativo para tiendas, clubes, proveedores, contratistas, bitácoras, accesos, incidencias y materiales.',
            ],
        ];
    }
}

if (!function_exists('service_profile_normalize_preset')) {
    function service_profile_normalize_preset(?string $preset): string
    {
        $preset = trim((string)($preset ?? ''));
        return in_array($preset, service_profile_allowed_presets(), true) ? $preset : 'residencial';
    }
}

if (!function_exists('service_profile_labels')) {
    function service_profile_labels(): array
    {
        return [
            'roles' => [
                'habilita_admin_operativo' => 'Admin operativo',
                'habilita_guardia' => 'Guardia',
                'habilita_residente' => 'Residente',
            ],
            'modules' => [
                'habilita_unidades' => 'Unidades',
                'habilita_residentes_catalogo' => 'Residentes',
                'habilita_guardias_catalogo' => 'Guardias',
                'habilita_guardias_admin_actions' => 'Acciones de guardias desde admin',
                'habilita_autos' => 'Autos',
                'habilita_visitas_residente' => 'Visitas',
                'habilita_paqueteria' => 'Paquetería',
                'habilita_pagos' => 'Pagos',
                'habilita_comunicados' => 'Comunicados',
                'habilita_servicios_directorio' => 'Servicios',
                'habilita_control_acceso' => 'Control de accesos',
                'habilita_incidencias' => 'Incidencias',
                'habilita_personal_recurrente' => 'Personal recurrente',
                'habilita_visitantes_rapidos' => 'Visitantes rápidos',
                'habilita_materiales' => 'Materiales / equipos',
                'habilita_solicitudes_pendientes' => 'Solicitudes pendientes',
                'habilita_bitacora_operativa' => 'Bitácora operativa',
                'habilita_herramientas' => 'Herramientas',
                'habilita_reportes_operativos' => 'Reportes operativos',
                'habilita_rondines' => 'Rondines',
                'habilita_proveedores' => 'Proveedores',
                'habilita_ordenes_servicio' => 'Órdenes de servicio',
            ],
        ];
    }
}

if (!function_exists('service_profile_defaults')) {
    function service_profile_defaults(string $preset = 'residencial'): array
    {
        $preset = service_profile_normalize_preset($preset);

        $base = [
            'preset_servicio' => $preset,
            'habilita_admin_operativo' => 0,
            'habilita_guardia' => 1,
            'habilita_residente' => 0,
            'habilita_unidades' => 0,
            'habilita_residentes_catalogo' => 0,
            'habilita_guardias_catalogo' => 0,
            'habilita_guardias_admin_actions' => 0,
            'habilita_autos' => 0,
            'habilita_visitas_residente' => 0,
            'habilita_paqueteria' => 0,
            'habilita_pagos' => 0,
            'habilita_comunicados' => 0,
            'habilita_servicios_directorio' => 0,
            'habilita_control_acceso' => 0,
            'habilita_incidencias' => 0,
            'habilita_personal_recurrente' => 0,
            'habilita_visitantes_rapidos' => 0,
            'habilita_materiales' => 0,
            'habilita_solicitudes_pendientes' => 0,
            'habilita_bitacora_operativa' => 0,
            'habilita_herramientas' => 0,
            'habilita_reportes_operativos' => 0,
            'habilita_rondines' => 0,
            'habilita_proveedores' => 0,
            'habilita_ordenes_servicio' => 0,
        ];

        return match ($preset) {
            'empresa' => array_merge($base, [
                'habilita_admin_operativo' => 1,
                'habilita_guardia' => 1,
                'habilita_guardias_catalogo' => 1,
                'habilita_guardias_admin_actions' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
                'habilita_personal_recurrente' => 1,
            ]),
            'obra' => array_merge($base, [
                'habilita_admin_operativo' => 1,
                'habilita_guardia' => 1,
                'habilita_guardias_catalogo' => 1,
                'habilita_guardias_admin_actions' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
                'habilita_personal_recurrente' => 1,
                'habilita_materiales' => 1,
            ]),
            'comercio' => array_merge($base, [
                'habilita_admin_operativo' => 1,
                'habilita_guardia' => 1,
                'habilita_guardias_catalogo' => 1,
                'habilita_guardias_admin_actions' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
            ]),
            'servicio' => array_merge($base, [
                'habilita_guardia' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
                'habilita_bitacora_operativa' => 1,
            ]),
            'retailops' => array_merge($base, [
                'habilita_admin_operativo' => 1,
                'habilita_guardia' => 1,
                'habilita_unidades' => 1,
                'habilita_guardias_catalogo' => 1,
                'habilita_guardias_admin_actions' => 1,
                'habilita_comunicados' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
                'habilita_personal_recurrente' => 1,
                'habilita_visitantes_rapidos' => 1,
                'habilita_materiales' => 1,
                'habilita_solicitudes_pendientes' => 1,
                'habilita_bitacora_operativa' => 1,
                'habilita_herramientas' => 1,
                'habilita_reportes_operativos' => 1,
                'habilita_rondines' => 1,
                'habilita_proveedores' => 1,
                'habilita_ordenes_servicio' => 1,
            ]),
            default => array_merge($base, [
                'habilita_admin_operativo' => 1,
                'habilita_guardia' => 1,
                'habilita_residente' => 1,
                'habilita_unidades' => 1,
                'habilita_residentes_catalogo' => 1,
                'habilita_guardias_catalogo' => 1,
                'habilita_guardias_admin_actions' => 1,
                'habilita_autos' => 1,
                'habilita_visitas_residente' => 1,
                'habilita_paqueteria' => 1,
                'habilita_pagos' => 1,
                'habilita_comunicados' => 1,
                'habilita_servicios_directorio' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
            ]),
        };
    }
}

if (!function_exists('service_profile_schema_ensure')) {
    function service_profile_schema_ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        operational_schema_ensure($pdo);

        if (!operational_table_exists($pdo, 'residenciales_servicio_config')) {
            $pdo->exec("
                CREATE TABLE residenciales_servicio_config (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    preset_servicio VARCHAR(30) NOT NULL DEFAULT 'residencial',
                    habilita_admin_operativo TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_guardia TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_residente TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_unidades TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_residentes_catalogo TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_guardias_catalogo TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_guardias_admin_actions TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_autos TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_visitas_residente TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_paqueteria TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_pagos TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_comunicados TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_servicios_directorio TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_control_acceso TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_incidencias TINYINT(1) NOT NULL DEFAULT 1,
                    habilita_personal_recurrente TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_visitantes_rapidos TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_materiales TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_solicitudes_pendientes TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_bitacora_operativa TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_herramientas TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_reportes_operativos TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_rondines TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_proveedores TINYINT(1) NOT NULL DEFAULT 0,
                    habilita_ordenes_servicio TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_residenciales_servicio_config_rid (residencial_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'residenciales_servicio_config', 'fk_residenciales_servicio_config_residencial')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD CONSTRAINT fk_residenciales_servicio_config_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_column_exists($pdo, 'residenciales_servicio_config', 'habilita_guardias_admin_actions')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD COLUMN habilita_guardias_admin_actions TINYINT(1) NOT NULL DEFAULT 1
                AFTER habilita_guardias_catalogo
            ");
        }

        if (!operational_column_exists($pdo, 'residenciales_servicio_config', 'habilita_herramientas')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD COLUMN habilita_herramientas TINYINT(1) NOT NULL DEFAULT 0
                AFTER habilita_bitacora_operativa
            ");
        }

        if (!operational_column_exists($pdo, 'residenciales_servicio_config', 'habilita_reportes_operativos')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD COLUMN habilita_reportes_operativos TINYINT(1) NOT NULL DEFAULT 0
                AFTER habilita_herramientas
            ");
            $pdo->exec("
                UPDATE residenciales_servicio_config rsc
                JOIN residenciales r ON r.id = rsc.residencial_id
                SET rsc.habilita_reportes_operativos = 1
                WHERE rsc.preset_servicio = 'retailops'
                   OR r.modo_operacion = 'retailops'
            ");
        }

        if (!operational_column_exists($pdo, 'residenciales_servicio_config', 'habilita_rondines')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD COLUMN habilita_rondines TINYINT(1) NOT NULL DEFAULT 0
                AFTER habilita_reportes_operativos
            ");
            $pdo->exec("
                UPDATE residenciales_servicio_config rsc
                JOIN residenciales r ON r.id = rsc.residencial_id
                SET rsc.habilita_rondines = 1
                WHERE rsc.preset_servicio = 'retailops'
                   OR r.modo_operacion = 'retailops'
            ");
        }

        if (!operational_column_exists($pdo, 'residenciales_servicio_config', 'habilita_proveedores')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD COLUMN habilita_proveedores TINYINT(1) NOT NULL DEFAULT 0
                AFTER habilita_rondines
            ");
            $pdo->exec("
                UPDATE residenciales_servicio_config rsc
                JOIN residenciales r ON r.id = rsc.residencial_id
                SET rsc.habilita_proveedores = 1
                WHERE rsc.preset_servicio = 'retailops'
                   OR r.modo_operacion = 'retailops'
            ");
        }

        if (!operational_column_exists($pdo, 'residenciales_servicio_config', 'habilita_ordenes_servicio')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD COLUMN habilita_ordenes_servicio TINYINT(1) NOT NULL DEFAULT 0
                AFTER habilita_proveedores
            ");
            $pdo->exec("
                UPDATE residenciales_servicio_config rsc
                JOIN residenciales r ON r.id = rsc.residencial_id
                SET rsc.habilita_ordenes_servicio = 1
                WHERE rsc.preset_servicio = 'retailops'
                   OR r.modo_operacion = 'retailops'
            ");
        }

        $done = true;
    }
}

if (!function_exists('service_profile_seed_defaults')) {
    function service_profile_seed_defaults(PDO $pdo, int $residencialId, ?array $residencial = null): array
    {
        service_profile_schema_ensure($pdo);

        if ($residencial === null) {
            $stmt = $pdo->prepare("
                SELECT id, modo_operacion, permite_qr, permite_trabajadores_recurrentes
                FROM residenciales
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $residencialId]);
            $residencial = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $preset = service_profile_normalize_preset((string)($residencial['modo_operacion'] ?? 'residencial'));
        $defaults = service_profile_defaults($preset);
        if (array_key_exists('permite_qr', $residencial) && (int)$residencial['permite_qr'] === 0) {
            $defaults['habilita_control_acceso'] = 0;
        }
        if (array_key_exists('permite_trabajadores_recurrentes', $residencial) && (int)$residencial['permite_trabajadores_recurrentes'] === 0) {
            $defaults['habilita_personal_recurrente'] = 0;
        }

        $fields = array_merge(['residencial_id', 'preset_servicio'], service_profile_all_flags());
        $placeholders = array_map(static fn(string $field): string => ':' . $field, $fields);

        $params = ['residencial_id' => $residencialId, 'preset_servicio' => $defaults['preset_servicio']];
        foreach (service_profile_all_flags() as $flag) {
            $params[$flag] = (int)($defaults[$flag] ?? 0);
        }

        $stmt = $pdo->prepare("
            INSERT INTO residenciales_servicio_config (" . implode(', ', $fields) . ")
            VALUES (" . implode(', ', $placeholders) . ")
        ");
        $stmt->execute($params);

        return service_profile_get($pdo, $residencialId);
    }
}

if (!function_exists('service_profile_get')) {
    function service_profile_get(PDO $pdo, int $residencialId): array
    {
        service_profile_schema_ensure($pdo);

        $stmt = $pdo->prepare("
            SELECT rsc.*, r.nombre AS residencial_nombre, r.modo_operacion
            FROM residenciales_servicio_config rsc
            JOIN residenciales r ON r.id = rsc.residencial_id
            WHERE rsc.residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute(['rid' => $residencialId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return service_profile_seed_defaults($pdo, $residencialId);
        }

        $profile = [
            'residencial_id' => (int)$row['residencial_id'],
            'preset_servicio' => service_profile_normalize_preset((string)($row['preset_servicio'] ?? $row['modo_operacion'] ?? 'residencial')),
            'modo_operacion' => operational_normalize_mode((string)($row['modo_operacion'] ?? 'residencial')),
            'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
        ];
        foreach (service_profile_all_flags() as $flag) {
            $profile[$flag] = (int)($row[$flag] ?? 0);
        }
        $profile = service_profile_sanitize_flags($profile);
        return $profile;
    }
}

if (!function_exists('service_profile_save')) {
    function service_profile_save(PDO $pdo, int $residencialId, array $input): array
    {
        service_profile_schema_ensure($pdo);
        $current = service_profile_get($pdo, $residencialId);
        $preset = service_profile_normalize_preset((string)($input['preset_servicio'] ?? $current['preset_servicio'] ?? $current['modo_operacion'] ?? 'residencial'));
        $base = service_profile_defaults($preset);
        $merged = $current;
        $merged['preset_servicio'] = $preset;

        foreach (service_profile_all_flags() as $flag) {
            if (array_key_exists($flag, $input)) {
                $merged[$flag] = operational_bool_int($input[$flag]);
            } elseif (!array_key_exists($flag, $current)) {
                $merged[$flag] = (int)($base[$flag] ?? 0);
            }
        }
        $merged = service_profile_sanitize_flags($merged);

        // Build SET clause dynamically from the canonical flag list to avoid placeholder mismatches.
        $setParts = ['preset_servicio = :preset_servicio'];
        foreach (service_profile_all_flags() as $flag) {
            $setParts[] = $flag . ' = :' . $flag;
        }
        $setParts[] = 'updated_at = NOW()';

        $stmt = $pdo->prepare("
            UPDATE residenciales_servicio_config
            SET " . implode(",\n                ", $setParts) . "
            WHERE residencial_id = :residencial_id
            LIMIT 1
        ");
        $params = [
            'residencial_id' => $residencialId,
            'preset_servicio' => $preset,
        ];
        foreach (service_profile_all_flags() as $flag) {
            $params[$flag] = (int)($merged[$flag] ?? 0);
        }
        $stmt->execute($params);

        return service_profile_get($pdo, $residencialId);
    }
}

if (!function_exists('service_profile_role_enabled')) {
    function service_profile_role_enabled(array $profile, string $role): bool
    {
        $role = service_profile_normalize_role_name($role);
        return match ($role) {
            'admin_residencial' => (int)($profile['habilita_admin_operativo'] ?? 0) === 1,
            'guardia' => (int)($profile['habilita_guardia'] ?? 0) === 1,
            'residente' => (int)($profile['habilita_residente'] ?? 0) === 1,
            default => true,
        };
    }
}

if (!function_exists('service_profile_normalize_role_name')) {
    function service_profile_normalize_role_name(?string $role): string
    {
        $raw = mb_strtolower(trim((string)($role ?? '')));
        if ($raw === '') {
            return '';
        }

        return match (true) {
            str_contains($raw, 'super') => 'super_admin',
            str_contains($raw, 'guard') => 'guardia',
            str_contains($raw, 'resident') => 'residente',
            str_contains($raw, 'admin_residencial'),
            str_contains($raw, 'admin operativo'),
            str_contains($raw, 'administr') => 'admin_residencial',
            default => str_replace(' ', '_', $raw),
        };
    }
}

if (!function_exists('service_profile_role_flag_map')) {
    function service_profile_role_flag_map(): array
    {
        return [
            'admin_residencial' => 'habilita_admin_operativo',
            'guardia' => 'habilita_guardia',
            'residente' => 'habilita_residente',
        ];
    }
}

if (!function_exists('service_profile_module_catalog')) {
    function service_profile_module_catalog(): array
    {
        return [
            'habilita_unidades' => [
                'module' => 'unidades',
                'roles' => ['admin_residencial'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_residentes_catalogo' => [
                'module' => 'residentes',
                'roles' => ['admin_residencial'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_guardias_catalogo' => [
                'module' => 'guardias',
                'roles' => ['admin_residencial'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_guardias_admin_actions' => [
                'module' => 'guardias_admin_actions',
                'roles' => ['admin_residencial'],
                'support_only' => true,
                'depends_on' => ['habilita_guardias_catalogo'],
            ],
            'habilita_autos' => [
                'module' => 'autos',
                'roles' => ['admin_residencial', 'guardia', 'residente'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_visitas_residente' => [
                'module' => 'visitas',
                'roles' => ['residente'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_paqueteria' => [
                'module' => 'paqueteria',
                'roles' => ['guardia', 'residente'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_pagos' => [
                'module' => 'pagos',
                'roles' => ['residente'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_comunicados' => [
                'module' => 'comunicados',
                'roles' => ['admin_residencial', 'residente'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_servicios_directorio' => [
                'module' => 'servicios',
                'roles' => ['residente'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_control_acceso' => [
                'module' => 'accesos',
                'roles' => ['guardia'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_incidencias' => [
                'module' => 'incidencias',
                'roles' => ['admin_residencial', 'guardia', 'residente'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_personal_recurrente' => [
                'module' => 'personal_recurrente',
                'roles' => ['admin_residencial', 'guardia'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_visitantes_rapidos' => [
                'module' => 'visitantes_rapidos',
                'roles' => ['admin_residencial', 'guardia'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_materiales' => [
                'module' => 'materiales',
                'roles' => ['admin_residencial', 'guardia'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_solicitudes_pendientes' => [
                'module' => 'solicitudes_pendientes',
                'roles' => ['admin_residencial'],
                'support_only' => false,
                'depends_on' => ['habilita_materiales'],
            ],
            'habilita_bitacora_operativa' => [
                'module' => 'bitacora_operativa',
                'roles' => ['admin_residencial', 'guardia'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_herramientas' => [
                'module' => 'herramientas',
                'roles' => ['admin_residencial', 'guardia'],
                'support_only' => false,
                'depends_on' => [],
            ],
            'habilita_reportes_operativos' => [
                'module' => 'reportes_operativos',
                'roles' => ['admin_residencial'],
                'support_only' => false,
                'depends_on' => ['habilita_bitacora_operativa'],
            ],
            'habilita_rondines' => [
                'module' => 'rondines',
                'roles' => ['admin_residencial', 'guardia'],
                'support_only' => false,
                'depends_on' => ['habilita_bitacora_operativa'],
            ],
            'habilita_proveedores' => [
                'module' => 'proveedores',
                'roles' => ['admin_residencial'],
                'support_only' => false,
                'depends_on' => ['habilita_personal_recurrente'],
            ],
            'habilita_ordenes_servicio' => [
                'module' => 'ordenes_servicio',
                'roles' => ['admin_residencial', 'guardia'],
                'support_only' => false,
                'depends_on' => ['habilita_proveedores', 'habilita_personal_recurrente', 'habilita_control_acceso', 'habilita_bitacora_operativa'],
            ],
        ];
    }
}

if (!function_exists('service_profile_frontend_matrix')) {
    function service_profile_frontend_matrix(): array
    {
        $labels = service_profile_labels();
        $roleFlags = service_profile_role_flag_map();
        $roles = [];
        $modules = [];

        foreach ($roleFlags as $role => $flag) {
            $roles[] = [
                'role' => $role,
                'flag' => $flag,
                'label' => $labels['roles'][$flag] ?? $role,
            ];
        }

        foreach (service_profile_module_catalog() as $flag => $meta) {
            $modules[$flag] = [
                'flag' => $flag,
                'label' => $labels['modules'][$flag] ?? $flag,
                'module' => $meta['module'],
                'roles' => $meta['roles'],
                'support_only' => (bool)($meta['support_only'] ?? false),
                'depends_on' => $meta['depends_on'] ?? [],
            ];
        }

        return [
            'roles' => $roles,
            'modules' => $modules,
        ];
    }
}

if (!function_exists('service_profile_assignable_roles')) {
    function service_profile_assignable_roles(array $profile): array
    {
        $roles = [];
        foreach (['admin_residencial', 'guardia', 'residente'] as $role) {
            if (service_profile_role_assignable_to_service($profile, $role)) {
                $roles[] = $role;
            }
        }
        return $roles;
    }
}

if (!function_exists('service_profile_sanitize_flags')) {
    function service_profile_sanitize_flags(array $flags): array
    {
        $roleFlags = service_profile_role_flag_map();
        $moduleCatalog = service_profile_module_catalog();
        $sanitized = $flags;

        $enabledRoles = [];
        foreach ($roleFlags as $role => $flag) {
            $sanitized[$flag] = operational_bool_int($sanitized[$flag] ?? 0);
            if ((int)$sanitized[$flag] === 1) {
                $enabledRoles[] = $role;
            }
        }

        foreach ($moduleCatalog as $flag => $meta) {
            $sanitized[$flag] = operational_bool_int($sanitized[$flag] ?? 0);
            if ((int)$sanitized[$flag] !== 1) {
                $sanitized[$flag] = 0;
                continue;
            }

            $supportedRoles = array_values(array_filter(array_map(
                'service_profile_normalize_role_name',
                (array)($meta['roles'] ?? [])
            )));

            if (!$supportedRoles || !array_intersect($enabledRoles, $supportedRoles)) {
                $sanitized[$flag] = 0;
                continue;
            }

            $dependencies = (array)($meta['depends_on'] ?? []);
            foreach ($dependencies as $dependencyFlag) {
                if ((int)($sanitized[$dependencyFlag] ?? 0) !== 1) {
                    $sanitized[$flag] = 0;
                    break;
                }
            }
        }

        return $sanitized;
    }
}

if (!function_exists('service_profile_role_assignable_to_service')) {
    function service_profile_role_assignable_to_service(array $profile, string $role): bool
    {
        $role = service_profile_normalize_role_name($role);
        if ($role === '' || $role === 'super_admin') {
            return false;
        }
        if (!service_profile_role_enabled($profile, $role)) {
            return false;
        }

        return !empty(service_profile_role_operable_views($profile, $role));
    }
}

if (!function_exists('service_profile_role_pending_reason')) {
    function service_profile_role_pending_reason(array $profile, string $role): array
    {
        $role = service_profile_normalize_role_name($role);
        $labels = service_profile_labels();
        $roleFlagMap = service_profile_role_flag_map();
        $roleLabel = $labels['roles'][$roleFlagMap[$role] ?? ''] ?? ucfirst(str_replace('_', ' ', $role));

        if (!service_profile_role_enabled($profile, $role)) {
            return [
                'code' => 'role_disabled',
                'message' => sprintf('El rol %s no está habilitado para este servicio.', $roleLabel),
            ];
        }

        $operableViews = service_profile_role_operable_views($profile, $role);
        if ($operableViews) {
            return [
                'code' => 'ready',
                'message' => sprintf('El rol %s ya tiene vistas operables para este servicio.', $roleLabel),
            ];
        }

        $roleModules = service_profile_role_module_matrix()[$role] ?? [];
        $configurableModules = [];
        foreach (service_profile_module_catalog() as $flag => $meta) {
            $moduleName = service_profile_normalize_module_name((string)($meta['module'] ?? ''));
            if ($moduleName === '' || !in_array($role, (array)($meta['roles'] ?? []), true)) {
                continue;
            }
            if (!in_array($moduleName, $roleModules, true)) {
                continue;
            }
            if (!empty($meta['support_only'])) {
                continue;
            }
            $configurableModules[] = [
                'flag' => $flag,
                'label' => $labels['modules'][$flag] ?? $flag,
            ];
        }

        $enabledModuleLabels = [];
        foreach ($configurableModules as $moduleMeta) {
            if ((int)($profile[$moduleMeta['flag']] ?? 0) === 1) {
                $enabledModuleLabels[] = $moduleMeta['label'];
            }
        }

        if ($enabledModuleLabels) {
            return [
                'code' => 'no_operable_views',
                'message' => sprintf(
                    'El rol %s tiene módulos activos (%s), pero todavía no quedó con vistas operables reales.',
                    $roleLabel,
                    implode(', ', $enabledModuleLabels)
                ),
            ];
        }

        $suggestedModuleLabels = array_values(array_unique(array_column($configurableModules, 'label')));
        return [
            'code' => 'missing_modules',
            'message' => $suggestedModuleLabels
                ? sprintf(
                    'Activa al menos un módulo operable para %s, por ejemplo: %s.',
                    $roleLabel,
                    implode(', ', array_slice($suggestedModuleLabels, 0, 4))
                )
                : sprintf('Este servicio todavía no tiene módulos operables disponibles para %s.', $roleLabel),
        ];
    }
}

if (!function_exists('service_profile_common_views')) {
    function service_profile_common_views(): array
    {
        return ['home', 'perfil', 'reglamento'];
    }
}

if (!function_exists('service_profile_role_module_matrix')) {
    function service_profile_role_module_matrix(): array
    {
        return [
            'admin_residencial' => ['home', 'perfil', 'reglamento', 'contexto', 'unidades', 'residentes', 'guardias', 'guardias_admin_actions', 'incidencias', 'comunicados', 'autos', 'personal_recurrente', 'visitantes_rapidos', 'materiales', 'solicitudes_pendientes', 'bitacora_operativa', 'herramientas', 'reportes_operativos', 'rondines', 'proveedores', 'ordenes_servicio'],
            'guardia' => ['home', 'perfil', 'reglamento', 'contexto', 'accesos', 'autos', 'incidencias', 'paqueteria', 'personal_recurrente', 'visitantes_rapidos', 'materiales', 'bitacora_operativa', 'herramientas', 'rondines', 'ordenes_servicio'],
            'residente' => ['home', 'perfil', 'reglamento', 'contexto', 'visitas', 'incidencias', 'paqueteria', 'autos', 'pagos', 'comunicados', 'servicios'],
        ];
    }
}

if (!function_exists('service_profile_role_view_matrix')) {
    function service_profile_role_view_matrix(): array
    {
        return [
            'admin_residencial' => [
                'home' => 'home',
                'perfil' => 'perfil',
                'reglamento' => 'reglamento',
                'unidades' => 'unidades',
                'residentes' => 'residentes',
                // Guardias stays visible if the catalog is enabled; admin actions are controlled separately.
                'guardias' => 'guardias',
                'incidencias' => 'incidencias',
                'comunicados' => 'comunicados',
                'autos' => 'autos',
                'personal_recurrente' => 'personal_recurrente',
                'visitantes_rapidos' => 'visitantes_rapidos',
                'materiales' => 'materiales',
                'solicitudes_pendientes' => 'solicitudes_pendientes',
                'bitacora_operativa' => 'bitacora_operativa',
                'herramientas' => 'herramientas',
                'reportes_operativos' => 'reportes_operativos',
                'rondines' => 'rondines',
                'proveedores' => 'proveedores',
                'ordenes_servicio' => 'ordenes_servicio',
            ],
            'guardia' => [
                'home' => 'home',
                'perfil' => 'perfil',
                'reglamento' => 'reglamento',
                'accesos' => 'accesos',
                'autos' => 'autos',
                'incidencias' => 'incidencias',
                'paqueteria' => 'paqueteria',
                'personas_dentro' => 'personal_recurrente',
                'materiales_autorizados' => 'materiales',
                'bitacora_hoy' => 'bitacora_operativa',
                'herramientas' => 'herramientas',
                'rondines' => 'rondines',
            ],
            'residente' => [
                'home' => 'home',
                'perfil' => 'perfil',
                'reglamento' => 'reglamento',
                'visitas' => 'visitas',
                'incidencias' => 'incidencias',
                'paqueteria' => 'paqueteria',
                'autos' => 'autos',
                'pagos' => 'pagos',
                'comunicados' => 'comunicados',
                'servicios' => 'servicios',
            ],
        ];
    }
}

if (!function_exists('service_profile_normalize_module_name')) {
    function service_profile_normalize_module_name(?string $module): string
    {
        $module = trim((string)($module ?? ''));
        return match ($module) {
            'personas_dentro' => 'personal_recurrente',
            'materiales_autorizados' => 'materiales',
            'bitacora_hoy' => 'bitacora_operativa',
            default => $module,
        };
    }
}

if (!function_exists('service_profile_module_enabled')) {
    function service_profile_module_enabled(array $profile, string $module): bool
    {
        $module = service_profile_normalize_module_name($module);
        $enabled = match ($module) {
            'home', 'perfil', 'reglamento', 'contexto' => true,
            'unidades' => (int)($profile['habilita_unidades'] ?? 0) === 1,
            'residentes' => (int)($profile['habilita_residentes_catalogo'] ?? 0) === 1,
            'guardias' => (int)($profile['habilita_guardias_catalogo'] ?? 0) === 1,
            'guardias_admin_actions' => (int)($profile['habilita_guardias_admin_actions'] ?? 0) === 1,
            'autos' => (int)($profile['habilita_autos'] ?? 0) === 1,
            'visitas' => (int)($profile['habilita_visitas_residente'] ?? 0) === 1,
            'paqueteria' => (int)($profile['habilita_paqueteria'] ?? 0) === 1,
            'pagos' => (int)($profile['habilita_pagos'] ?? 0) === 1,
            'comunicados' => (int)($profile['habilita_comunicados'] ?? 0) === 1,
            'servicios' => (int)($profile['habilita_servicios_directorio'] ?? 0) === 1,
            'accesos' => (int)($profile['habilita_control_acceso'] ?? 0) === 1,
            'incidencias' => (int)($profile['habilita_incidencias'] ?? 0) === 1,
            'personal_recurrente', 'personas_dentro' => (int)($profile['habilita_personal_recurrente'] ?? 0) === 1,
            'visitantes_rapidos' => (int)($profile['habilita_visitantes_rapidos'] ?? 0) === 1,
            'materiales', 'materiales_autorizados' => (int)($profile['habilita_materiales'] ?? 0) === 1,
            'solicitudes_pendientes' => (int)($profile['habilita_solicitudes_pendientes'] ?? 0) === 1,
            'bitacora_operativa', 'bitacora_hoy' => (int)($profile['habilita_bitacora_operativa'] ?? 0) === 1,
            'herramientas' => (int)($profile['habilita_herramientas'] ?? 0) === 1,
            'reportes_operativos' => (int)($profile['habilita_reportes_operativos'] ?? 0) === 1,
            'rondines' => (int)($profile['habilita_rondines'] ?? 0) === 1,
            'proveedores' => (int)($profile['habilita_proveedores'] ?? 0) === 1,
            'ordenes_servicio' => (int)($profile['habilita_ordenes_servicio'] ?? 0) === 1,
            default => true,
        };

        if (!$enabled) {
            return false;
        }

        foreach (service_profile_module_catalog() as $flag => $meta) {
            if (service_profile_normalize_module_name((string)($meta['module'] ?? '')) !== $module) {
                continue;
            }
            foreach ((array)($meta['depends_on'] ?? []) as $dependencyFlag) {
                if ((int)($profile[$dependencyFlag] ?? 0) !== 1) {
                    return false;
                }
            }
        }

        return true;
    }
}

if (!function_exists('service_profile_module_allowed_for_role_and_service')) {
    function service_profile_module_allowed_for_role_and_service(array $profile, string $role, string $module): bool
    {
        $role = service_profile_normalize_role_name($role);
        $module = service_profile_normalize_module_name($module);

        if (!service_profile_role_enabled($profile, $role)) {
            return false;
        }

        $roleModules = service_profile_role_module_matrix()[$role] ?? [];
        if (!in_array($module, $roleModules, true)) {
            return false;
        }

        return service_profile_module_enabled($profile, $module);
    }
}

if (!function_exists('service_profile_view_allowed_for_role_and_service')) {
    function service_profile_view_allowed_for_role_and_service(array $profile, string $role, string $view): bool
    {
        $role = service_profile_normalize_role_name($role);
        $module = service_profile_role_view_matrix()[$role][$view] ?? null;
        if ($module === null) {
            return false;
        }
        return service_profile_module_allowed_for_role_and_service($profile, $role, $module);
    }
}

if (!function_exists('service_profile_allowed_views_raw')) {
    function service_profile_allowed_views_raw(array $profile, string $role): array
    {
        $role = service_profile_normalize_role_name($role);
        $views = service_profile_role_view_matrix()[$role] ?? [];
        if (!$views) {
            return [];
        }

        return array_values(array_keys(array_filter($views, static function (string $module, string $view) use ($profile, $role): bool {
            return service_profile_view_allowed_for_role_and_service($profile, $role, $view);
        }, ARRAY_FILTER_USE_BOTH)));
    }
}

if (!function_exists('service_profile_role_operable_views')) {
    function service_profile_role_operable_views(array $profile, string $role): array
    {
        $rawViews = service_profile_allowed_views_raw($profile, $role);
        if (!$rawViews) {
            return [];
        }

        return array_values(array_filter($rawViews, static function (string $view): bool {
            return !in_array($view, service_profile_common_views(), true);
        }));
    }
}

if (!function_exists('service_profile_operator_status_for_service')) {
    function service_profile_operator_status_for_service(array $profile, string $role, bool $isActive): array
    {
        $role = service_profile_normalize_role_name($role);

        if (!$isActive) {
            return [
                'code' => 'inactivo',
                'label' => 'Inactivo',
                'ready' => false,
            ];
        }

        if (!service_profile_role_assignable_to_service($profile, $role)) {
            $reason = service_profile_role_pending_reason($profile, $role);
            return [
                'code' => 'pendiente',
                'label' => 'Pendiente',
                'ready' => false,
                'reason_code' => $reason['code'] ?? 'pending',
                'reason_message' => $reason['message'] ?? 'El operador sigue pendiente de activación para este servicio.',
            ];
        }

        return [
            'code' => 'listo',
            'label' => 'Listo',
            'ready' => true,
            'reason_code' => 'ready',
            'reason_message' => 'El operador ya puede entrar y operar este servicio.',
        ];
    }
}

if (!function_exists('service_profile_allowed_views')) {
    function service_profile_allowed_views(array $profile, string $role): array
    {
        $role = service_profile_normalize_role_name($role);
        if ($role === '' || !service_profile_role_enabled($profile, $role)) {
            return [];
        }

        if (empty(service_profile_role_operable_views($profile, $role))) {
            return [];
        }

        return service_profile_allowed_views_raw($profile, $role);
    }
}

if (!function_exists('service_profile_resolve_residencial_id_for_user')) {
    function service_profile_resolve_residencial_id_for_user(PDO $pdo, int $userId): int
    {
        $stmt = $pdo->prepare("
            SELECT residencial_id
            FROM usuarios_residenciales
            WHERE user_id = :uid
            ORDER BY es_principal DESC, created_at ASC
            LIMIT 1
        ");
        $stmt->execute(['uid' => $userId]);
        return (int)($stmt->fetchColumn() ?: 0);
    }
}

if (!function_exists('service_profile_require_role_enabled')) {
    function service_profile_require_role_enabled(PDO $pdo, int $residencialId, string $role, ?string $message = null): array
    {
        $profile = service_profile_get($pdo, $residencialId);
        if (!service_profile_role_enabled($profile, $role)) {
            $title = 'Servicio no disponible para este cliente';
            $msg = $message ?: 'Este rol no está habilitado para el perfil de servicio configurado.';
            app_abort(403, $title, $msg, [
                ['label' => 'Cerrar sesión', 'href' => app_logout_url()],
            ]);
        }
        return $profile;
    }
}

if (!function_exists('service_profile_require_module_enabled')) {
    function service_profile_require_module_enabled(array $profile, string $module, ?string $message = null): void
    {
        if (!service_profile_module_enabled($profile, $module)) {
            app_abort(
                403,
                'Módulo no disponible',
                $message ?: 'Este módulo no está habilitado para el servicio configurado para este cliente.',
                [
                    ['label' => 'Volver a mi panel', 'href' => app_role_home_url((string)(current_user()['role'] ?? ''))],
                    ['label' => 'Cerrar sesión', 'href' => app_logout_url(), 'kind' => 'secondary'],
                ]
            );
        }
    }
}

if (!function_exists('service_profile_api_require_module')) {
    function service_profile_api_require_module(PDO $pdo, int $residencialId, string $role, string $module, ?string $message = null): array
    {
        $profile = service_profile_require_role_enabled($pdo, $residencialId, $role);
        if (!service_profile_module_allowed_for_role_and_service($profile, $role, $module)) {
            app_abort(403, 'Módulo no disponible', $message ?: 'Este módulo no está habilitado para este cliente.');
        }
        return $profile;
    }
}

if (!function_exists('service_profile_frontend_payload')) {
    function service_profile_frontend_payload(PDO $pdo, int $residencialId, string $role): array
    {
        $profile = service_profile_get($pdo, $residencialId);
        $labels = service_profile_labels();
        return [
            'preset_servicio' => $profile['preset_servicio'],
            'modo_operacion' => $profile['modo_operacion'],
            'assignable_roles' => service_profile_assignable_roles($profile),
            'role_assignable' => service_profile_role_assignable_to_service($profile, $role),
            'roles' => array_reduce(service_profile_role_flags(), static function (array $carry, string $flag) use ($profile, $labels): array {
                $carry[$flag] = [
                    'enabled' => (int)($profile[$flag] ?? 0) === 1,
                    'label' => $labels['roles'][$flag] ?? $flag,
                ];
                return $carry;
            }, []),
            'modules' => array_reduce(service_profile_module_flags(), static function (array $carry, string $flag) use ($profile, $labels): array {
                $carry[$flag] = [
                    'enabled' => (int)($profile[$flag] ?? 0) === 1,
                    'label' => $labels['modules'][$flag] ?? $flag,
                ];
                return $carry;
            }, []),
            'operable_views' => service_profile_role_operable_views($profile, $role),
            'allowed_views' => service_profile_allowed_views($profile, $role),
            'matrix' => service_profile_frontend_matrix(),
        ];
    }
}
