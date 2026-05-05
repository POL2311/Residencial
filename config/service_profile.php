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
        return ['residencial', 'empresa', 'obra', 'comercio', 'servicio'];
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
            ],
        ];
    }
}

if (!function_exists('service_profile_plan_schema_ensure')) {
    function service_profile_plan_schema_ensure(PDO $pdo): void
    {
        if (!operational_table_exists($pdo, 'planes')) {
            return;
        }

        if (!operational_column_exists($pdo, 'planes', 'entitlements')) {
            $pdo->exec("
                ALTER TABLE planes
                ADD COLUMN entitlements LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
                AFTER limites
            ");
        }
    }
}

if (!function_exists('service_profile_plan_aliases')) {
    function service_profile_plan_aliases(): array
    {
        return [
            'accesos' => 'accesos',
            'control_acceso' => 'accesos',
            'control_de_accesos' => 'accesos',
            'visitas' => 'visitas',
            'visitas_residente' => 'visitas',
            'paqueteria' => 'paqueteria',
            'pagos' => 'pagos',
            'comunicados' => 'comunicados',
            'servicios' => 'servicios',
            'servicios_directorio' => 'servicios',
            'autos' => 'autos',
            'guardias' => 'guardias',
            'guardias_admin_actions' => 'guardias_admin_actions',
            'acciones_guardias_admin' => 'guardias_admin_actions',
            'residentes' => 'residentes',
            'unidades' => 'unidades',
            'incidencias' => 'incidencias',
            'personal_recurrente' => 'personal_recurrente',
            'visitantes_rapidos' => 'visitantes_rapidos',
            'materiales' => 'materiales',
            'materiales_equipos' => 'materiales',
            'solicitudes_pendientes' => 'solicitudes_pendientes',
            'bitacora_operativa' => 'bitacora_operativa',
            'rondines' => 'bitacora_operativa',
        ];
    }
}

if (!function_exists('service_profile_plan_normalize_feature_name')) {
    function service_profile_plan_normalize_feature_name(?string $value): string
    {
        $raw = mb_strtolower(trim((string)($value ?? '')));
        if ($raw === '') {
            return '';
        }
        $raw = str_replace([' ', '-'], '_', $raw);
        return service_profile_plan_aliases()[$raw] ?? $raw;
    }
}

if (!function_exists('service_profile_plan_fallback_catalog')) {
    function service_profile_plan_fallback_catalog(): array
    {
        $basicModules = [
            'unidades',
            'residentes',
            'guardias',
            'guardias_admin_actions',
            'autos',
            'visitas',
            'paqueteria',
            'comunicados',
            'servicios',
            'accesos',
            'incidencias',
        ];

        $proModules = array_merge($basicModules, [
            'pagos',
            'personal_recurrente',
            'visitantes_rapidos',
            'bitacora_operativa',
        ]);

        $enterpriseModules = array_merge($proModules, [
            'materiales',
            'solicitudes_pendientes',
        ]);

        return [
            'basic' => [
                'roles' => ['admin_residencial', 'guardia', 'residente'],
                'modules' => $basicModules,
                'actions' => ['guardias_admin_actions'],
                'extras' => [
                    'permite_qr' => true,
                    'permite_trabajadores_recurrentes' => false,
                ],
            ],
            'pro' => [
                'roles' => ['admin_residencial', 'guardia', 'residente'],
                'modules' => $proModules,
                'actions' => ['guardias_admin_actions'],
                'extras' => [
                    'permite_qr' => true,
                    'permite_trabajadores_recurrentes' => true,
                ],
            ],
            'enterprise' => [
                'roles' => ['admin_residencial', 'guardia', 'residente'],
                'modules' => $enterpriseModules,
                'actions' => ['guardias_admin_actions'],
                'extras' => [
                    'permite_qr' => true,
                    'permite_trabajadores_recurrentes' => true,
                ],
            ],
        ];
    }
}

if (!function_exists('service_profile_plan_fallback_entitlements')) {
    function service_profile_plan_fallback_entitlements(?string $planCode): array
    {
        $code = mb_strtolower(trim((string)($planCode ?? '')));
        $catalog = service_profile_plan_fallback_catalog();
        return $catalog[$code] ?? [
            'roles' => ['admin_residencial', 'guardia', 'residente'],
            'modules' => array_values(array_unique(array_map(
                static fn(array $meta): string => service_profile_plan_normalize_feature_name((string)($meta['module'] ?? '')),
                service_profile_module_catalog()
            ))),
            'actions' => ['guardias_admin_actions'],
            'extras' => [
                'permite_qr' => true,
                'permite_trabajadores_recurrentes' => true,
            ],
        ];
    }
}

if (!function_exists('service_profile_plan_parse_json')) {
    function service_profile_plan_parse_json($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value)) {
            return [];
        }
        $value = trim($value);
        if ($value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}

if (!function_exists('service_profile_plan_normalize_array_values')) {
    function service_profile_plan_normalize_array_values(array $values, callable $normalizer): array
    {
        $normalized = [];
        foreach ($values as $value) {
            $item = $normalizer($value);
            if ($item === '') {
                continue;
            }
            $normalized[] = $item;
        }
        return array_values(array_unique($normalized));
    }
}

if (!function_exists('service_profile_plan_resolve_entitlements')) {
    function service_profile_plan_resolve_entitlements(?array $planRow): ?array
    {
        if (!$planRow || (int)($planRow['id'] ?? 0) <= 0) {
            return null;
        }

        $code = mb_strtolower(trim((string)($planRow['codigo'] ?? '')));
        $limits = service_profile_plan_parse_json($planRow['limites'] ?? null);
        $structured = service_profile_plan_parse_json($planRow['entitlements'] ?? null);
        $fallback = service_profile_plan_fallback_entitlements($code);

        $roles = service_profile_plan_normalize_array_values(
            (array)($structured['roles_incluidos'] ?? $limits['roles_incluidos'] ?? $fallback['roles'] ?? []),
            'service_profile_normalize_role_name'
        );
        if (!$roles) {
            $roles = $fallback['roles'];
        }

        $modules = service_profile_plan_normalize_array_values(
            (array)($structured['modulos_incluidos'] ?? $limits['modulos_incluidos'] ?? $fallback['modules'] ?? []),
            'service_profile_plan_normalize_feature_name'
        );
        if (!$modules) {
            $modules = $fallback['modules'];
        }

        $actions = service_profile_plan_normalize_array_values(
            (array)($structured['acciones_incluidas'] ?? $limits['acciones_incluidas'] ?? $fallback['actions'] ?? []),
            'service_profile_plan_normalize_feature_name'
        );
        if (!$actions) {
            $actions = $fallback['actions'];
        }

        $extras = array_merge(
            [
                'permite_qr' => true,
                'permite_trabajadores_recurrentes' => true,
            ],
            $fallback['extras'] ?? [],
            is_array($limits['extras'] ?? null) ? $limits['extras'] : [],
            is_array($structured['extras'] ?? null) ? $structured['extras'] : []
        );

        return [
            'id' => (int)$planRow['id'],
            'nombre' => (string)($planRow['nombre'] ?? ''),
            'codigo' => $code,
            'descripcion' => (string)($planRow['descripcion'] ?? ''),
            'periodo' => (string)($planRow['periodo'] ?? ''),
            'precio_mensual' => isset($planRow['precio_mensual']) ? (float)$planRow['precio_mensual'] : null,
            'precio_anual' => isset($planRow['precio_anual']) ? (float)$planRow['precio_anual'] : null,
            'activo' => (int)($planRow['activo'] ?? 0) === 1,
            'roles' => $roles,
            'modules' => $modules,
            'actions' => $actions,
            'extras' => [
                'permite_qr' => !empty($extras['permite_qr']),
                'permite_trabajadores_recurrentes' => !empty($extras['permite_trabajadores_recurrentes']),
            ],
            'limits' => [
                'max_casas' => isset($limits['max_casas']) ? (int)$limits['max_casas'] : null,
                'max_guardias' => isset($limits['max_guardias']) ? (int)$limits['max_guardias'] : null,
            ],
            'has_structured_entitlements' => !empty($structured),
            'valid' => true,
        ];
    }
}

if (!function_exists('service_profile_fetch_plan')) {
    function service_profile_fetch_plan(PDO $pdo, int $planId): ?array
    {
        if ($planId <= 0 || !operational_table_exists($pdo, 'planes')) {
            return null;
        }

        service_profile_plan_schema_ensure($pdo);

        $hasEntitlementsColumn = operational_column_exists($pdo, 'planes', 'entitlements');
        $sql = "
            SELECT id, nombre, codigo, descripcion, periodo, precio_mensual, precio_anual, limites, activo" .
            ($hasEntitlementsColumn ? ", entitlements" : "") . "
            FROM planes
            WHERE id = :id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $planId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return service_profile_plan_resolve_entitlements($row);
    }
}

if (!function_exists('service_profile_fetch_active_plans')) {
    function service_profile_fetch_active_plans(PDO $pdo): array
    {
        if (!operational_table_exists($pdo, 'planes')) {
            return [];
        }

        service_profile_plan_schema_ensure($pdo);

        $hasEntitlementsColumn = operational_column_exists($pdo, 'planes', 'entitlements');
        $sql = "
            SELECT id, nombre, codigo, descripcion, periodo, precio_mensual, precio_anual, limites, activo" .
            ($hasEntitlementsColumn ? ", entitlements" : "") . "
            FROM planes
            WHERE activo = 1
            ORDER BY precio_mensual ASC, nombre ASC
        ";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_values(array_filter(array_map(
            'service_profile_plan_resolve_entitlements',
            $rows
        )));
    }
}

if (!function_exists('service_profile_entitlement_mode_normalize')) {
    function service_profile_entitlement_mode_normalize(?string $mode): string
    {
        $mode = mb_strtolower(trim((string)($mode ?? '')));
        return in_array($mode, ['compat', 'strict'], true) ? $mode : 'compat';
    }
}

if (!function_exists('service_profile_plan_status_operational')) {
    function service_profile_plan_status_operational(?string $status): bool
    {
        $status = mb_strtolower(trim((string)($status ?? '')));
        return in_array($status, ['activo', 'prueba'], true);
    }
}

if (!function_exists('service_profile_core_flags')) {
    function service_profile_core_flags(string $preset, array $residencial = []): array
    {
        $core = service_profile_defaults($preset);

        if (array_key_exists('permite_qr', $residencial) && (int)$residencial['permite_qr'] === 0) {
            $core['habilita_control_acceso'] = 0;
        }
        if (array_key_exists('permite_trabajadores_recurrentes', $residencial) && (int)$residencial['permite_trabajadores_recurrentes'] === 0) {
            $core['habilita_personal_recurrente'] = 0;
        }

        return service_profile_sanitize_flags($core);
    }
}

if (!function_exists('service_profile_plan_extra_flags')) {
    function service_profile_plan_extra_flags(?array $plan, string $preset, array $residencial = []): array
    {
        $extra = ['preset_servicio' => service_profile_normalize_preset($preset)];
        foreach (service_profile_all_flags() as $flag) {
            $extra[$flag] = 0;
        }

        if (!$plan || empty($plan['valid'])) {
            return service_profile_sanitize_flags($extra);
        }

        $core = service_profile_core_flags($preset, $residencial);
        $roles = array_values(array_unique(array_map(
            'service_profile_normalize_role_name',
            (array)($plan['roles'] ?? [])
        )));
        $modules = array_values(array_unique(array_map(
            'service_profile_plan_normalize_feature_name',
            array_merge((array)($plan['modules'] ?? []), (array)($plan['actions'] ?? []))
        )));

        foreach (service_profile_role_flag_map() as $role => $flag) {
            if (in_array($role, $roles, true) && (int)($core[$flag] ?? 0) !== 1) {
                $extra[$flag] = 1;
            }
        }

        foreach (service_profile_module_catalog() as $flag => $meta) {
            $moduleName = service_profile_plan_normalize_feature_name((string)($meta['module'] ?? ''));
            if (in_array($moduleName, $modules, true) && (int)($core[$flag] ?? 0) !== 1) {
                $extra[$flag] = 1;
            }
        }

        $entitledRoleFlags = [];
        foreach (service_profile_role_flag_map() as $role => $flag) {
            $entitledRoleFlags[$flag] = ((int)($core[$flag] ?? 0) === 1 || (int)($extra[$flag] ?? 0) === 1) ? 1 : 0;
        }

        foreach (service_profile_module_catalog() as $flag => $meta) {
            if ((int)($extra[$flag] ?? 0) !== 1) {
                continue;
            }

            $supportedRoles = array_values(array_unique(array_map(
                'service_profile_normalize_role_name',
                (array)($meta['roles'] ?? [])
            )));

            $hasEntitledRole = false;
            foreach ($supportedRoles as $role) {
                $roleFlag = service_profile_role_flag_map()[$role] ?? null;
                if ($roleFlag && (int)($entitledRoleFlags[$roleFlag] ?? 0) === 1) {
                    $hasEntitledRole = true;
                    break;
                }
            }

            if ($supportedRoles && !$hasEntitledRole) {
                $extra[$flag] = 0;
                continue;
            }

            foreach ((array)($meta['depends_on'] ?? []) as $dependencyFlag) {
                $dependencyEntitled = (int)($core[$dependencyFlag] ?? 0) === 1 || (int)($extra[$dependencyFlag] ?? 0) === 1;
                if (!$dependencyEntitled) {
                    $extra[$flag] = 0;
                    break;
                }
            }
        }

        return service_profile_sanitize_flags($extra);
    }
}

if (!function_exists('service_profile_plan_allowed_flags')) {
    function service_profile_plan_allowed_flags(?array $plan, string $preset, array $residencial = []): array
    {
        if (!$plan || empty($plan['valid'])) {
            return service_profile_core_flags($preset, $residencial);
        }

        $core = service_profile_core_flags($preset, $residencial);
        $extra = service_profile_plan_extra_flags($plan, $preset, $residencial);
        $allowed = ['preset_servicio' => service_profile_normalize_preset($preset)];

        foreach (service_profile_all_flags() as $flag) {
            $allowed[$flag] = ((int)($core[$flag] ?? 0) === 1 || (int)($extra[$flag] ?? 0) === 1) ? 1 : 0;
        }

        if (empty($plan['extras']['permite_qr']) || (int)($residencial['permite_qr'] ?? 1) !== 1) {
            $allowed['habilita_control_acceso'] = 0;
        }
        if (empty($plan['extras']['permite_trabajadores_recurrentes']) || (int)($residencial['permite_trabajadores_recurrentes'] ?? 1) !== 1) {
            $allowed['habilita_personal_recurrente'] = 0;
        }

        return service_profile_sanitize_flags($allowed);
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
        service_profile_plan_schema_ensure($pdo);

        if (!operational_table_exists($pdo, 'residenciales_servicio_config')) {
            $pdo->exec("
                CREATE TABLE residenciales_servicio_config (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    preset_servicio VARCHAR(30) NOT NULL DEFAULT 'residencial',
                    entitlement_enforcement VARCHAR(20) NOT NULL DEFAULT 'compat',
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

        if (!operational_column_exists($pdo, 'residenciales_servicio_config', 'entitlement_enforcement')) {
            $pdo->exec("
                ALTER TABLE residenciales_servicio_config
                ADD COLUMN entitlement_enforcement VARCHAR(20) NOT NULL DEFAULT 'compat'
                AFTER preset_servicio
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

        $fields = array_merge(['residencial_id', 'preset_servicio', 'entitlement_enforcement'], service_profile_all_flags());
        $placeholders = array_map(static fn(string $field): string => ':' . $field, $fields);

        $params = [
            'residencial_id' => $residencialId,
            'preset_servicio' => $defaults['preset_servicio'],
            'entitlement_enforcement' => 'compat',
        ];
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

if (!function_exists('service_profile_set_entitlement_enforcement')) {
    function service_profile_set_entitlement_enforcement(PDO $pdo, int $residencialId, string $mode): void
    {
        service_profile_schema_ensure($pdo);

        $stmt = $pdo->prepare("
            UPDATE residenciales_servicio_config
            SET entitlement_enforcement = :mode,
                updated_at = NOW()
            WHERE residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'mode' => service_profile_entitlement_mode_normalize($mode),
            'rid' => $residencialId,
        ]);
    }
}

if (!function_exists('service_profile_get')) {
    function service_profile_get(PDO $pdo, int $residencialId): array
    {
        service_profile_schema_ensure($pdo);

        $stmt = $pdo->prepare("
            SELECT rsc.*,
                   r.nombre AS residencial_nombre,
                   r.modo_operacion,
                   r.plan_id,
                   r.estatus_plan,
                   r.permite_qr,
                   r.permite_trabajadores_recurrentes,
                   r.max_casas,
                   r.max_guardias,
                   r.fecha_inicio_plan,
                   r.fecha_fin_plan
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

        $preset = service_profile_normalize_preset((string)($row['preset_servicio'] ?? $row['modo_operacion'] ?? 'residencial'));
        $enforcement = service_profile_entitlement_mode_normalize((string)($row['entitlement_enforcement'] ?? 'compat'));
        $coreFlags = service_profile_core_flags($preset, $row);
        $storedFlags = [];
        foreach (service_profile_all_flags() as $flag) {
            $storedFlags[$flag] = operational_bool_int($row[$flag] ?? 0);
        }

        $plan = service_profile_fetch_plan($pdo, (int)($row['plan_id'] ?? 0));
        $hasValidPlan = !empty($plan['valid']);
        $planExtraFlags = $hasValidPlan
            ? service_profile_plan_extra_flags($plan, $preset, $row)
            : service_profile_sanitize_flags(['preset_servicio' => $preset]);
        $planAllowedFlags = $hasValidPlan
            ? service_profile_plan_allowed_flags($plan, $preset, $row)
            : $coreFlags;

        $effectiveFlags = $storedFlags;
        if ($hasValidPlan && $enforcement === 'strict') {
            foreach (service_profile_all_flags() as $flag) {
                $effectiveFlags[$flag] = ((int)$storedFlags[$flag] === 1 && (int)($planAllowedFlags[$flag] ?? 0) === 1) ? 1 : 0;
            }
        }
        $effectiveFlags = service_profile_sanitize_flags(array_merge(['preset_servicio' => $preset], $effectiveFlags));

        $roleLabels = service_profile_labels()['roles'];
        $moduleLabels = service_profile_labels()['modules'];
        $outOfPlanFlags = [];
        $manualOffFlags = [];

        if ($hasValidPlan) {
            foreach (service_profile_all_flags() as $flag) {
                $storedEnabled = (int)($storedFlags[$flag] ?? 0) === 1;
                $entitled = (int)($planAllowedFlags[$flag] ?? 0) === 1;
                if ($storedEnabled && !$entitled) {
                    $outOfPlanFlags[] = [
                        'flag' => $flag,
                        'label' => $roleLabels[$flag] ?? $moduleLabels[$flag] ?? $flag,
                    ];
                }
                if (!$storedEnabled && $entitled) {
                    $manualOffFlags[] = [
                        'flag' => $flag,
                        'label' => $roleLabels[$flag] ?? $moduleLabels[$flag] ?? $flag,
                    ];
                }
            }
        }

        $alignmentCode = 'sin_plan_valido';
        $alignmentLabel = 'Sin plan válido';
        $alignmentMessage = 'Este servicio todavía no tiene un plan válido como fuente canónica de entitlements.';
        if ($hasValidPlan) {
            if ($outOfPlanFlags) {
                $alignmentCode = 'con_capacidades_fuera_de_plan';
                $alignmentLabel = 'Fuera de plan';
                $alignmentMessage = 'Hay capacidades activas que ya no están incluidas en el plan actual. Se conservan temporalmente por compatibilidad.';
            } elseif ($manualOffFlags) {
                $alignmentCode = 'alineado_con_apagados_manuales';
                $alignmentLabel = 'Alineado con apagados manuales';
                $alignmentMessage = 'El servicio está dentro del plan, pero tiene capacidades apagadas manualmente.';
            } else {
                $alignmentCode = 'alineado';
                $alignmentLabel = 'Alineado';
                $alignmentMessage = 'El servicio ya está alineado por completo con su plan actual.';
            }
        }

        $profile = [
            'residencial_id' => (int)$row['residencial_id'],
            'preset_servicio' => $preset,
            'modo_operacion' => operational_normalize_mode((string)($row['modo_operacion'] ?? 'residencial')),
            'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
            'plan_id' => (int)($row['plan_id'] ?? 0),
            'estatus_plan' => (string)($row['estatus_plan'] ?? 'activo'),
            'fecha_inicio_plan' => (string)($row['fecha_inicio_plan'] ?? ''),
            'fecha_fin_plan' => (string)($row['fecha_fin_plan'] ?? ''),
            'max_casas' => isset($row['max_casas']) ? (int)$row['max_casas'] : null,
            'max_guardias' => isset($row['max_guardias']) ? (int)$row['max_guardias'] : null,
            'permite_qr' => (int)($row['permite_qr'] ?? 1),
            'permite_trabajadores_recurrentes' => (int)($row['permite_trabajadores_recurrentes'] ?? 1),
            'plan_operational' => service_profile_plan_status_operational((string)($row['estatus_plan'] ?? 'activo')),
            'entitlement_enforcement' => $enforcement,
            'plan' => $plan,
            'plan_valid' => $hasValidPlan,
            'core_flags' => $coreFlags,
            'plan_extra_flags' => $planExtraFlags,
            'plan_allowed_flags' => $planAllowedFlags,
            'stored_flags' => $storedFlags,
            'alignment' => [
                'code' => $alignmentCode,
                'label' => $alignmentLabel,
                'message' => $alignmentMessage,
                'out_of_plan_flags' => $outOfPlanFlags,
                'manual_off_flags' => $manualOffFlags,
            ],
        ];
        foreach (service_profile_all_flags() as $flag) {
            $profile[$flag] = (int)($effectiveFlags[$flag] ?? 0);
        }
        return $profile;
    }
}

if (!function_exists('service_profile_save')) {
    function service_profile_save(PDO $pdo, int $residencialId, array $input): array
    {
        service_profile_schema_ensure($pdo);
        $current = service_profile_get($pdo, $residencialId);
        $preset = service_profile_normalize_preset((string)($input['preset_servicio'] ?? $current['preset_servicio'] ?? $current['modo_operacion'] ?? 'residencial'));
        $storedFlags = (array)($current['stored_flags'] ?? []);
        $enforcement = service_profile_entitlement_mode_normalize((string)($input['entitlement_enforcement'] ?? $current['entitlement_enforcement'] ?? 'compat'));
        $plan = !empty($current['plan_valid']) ? ($current['plan'] ?? null) : null;
        $planAllowedFlags = $plan
            ? service_profile_plan_allowed_flags($plan, $preset, $current)
            : service_profile_core_flags($preset, $current);
        $merged = $storedFlags;
        $merged['preset_servicio'] = $preset;

        foreach (service_profile_all_flags() as $flag) {
            if (array_key_exists($flag, $input)) {
                $requested = operational_bool_int($input[$flag]);
                $storedEnabled = (int)($storedFlags[$flag] ?? 0) === 1;
                $planAllowed = (int)($planAllowedFlags[$flag] ?? 0) === 1;

                if ($planAllowed) {
                    $merged[$flag] = $requested;
                    continue;
                }

                if ($storedEnabled && $enforcement === 'compat') {
                    $merged[$flag] = 1;
                    continue;
                }

                $merged[$flag] = 0;
            } elseif (!array_key_exists($flag, $storedFlags)) {
                $merged[$flag] = 0;
            }
        }
        $merged = service_profile_sanitize_flags($merged);

        $stmt = $pdo->prepare("
            UPDATE residenciales_servicio_config
            SET preset_servicio = :preset_servicio,
                entitlement_enforcement = :entitlement_enforcement,
                habilita_admin_operativo = :habilita_admin_operativo,
                habilita_guardia = :habilita_guardia,
                habilita_residente = :habilita_residente,
                habilita_unidades = :habilita_unidades,
                habilita_residentes_catalogo = :habilita_residentes_catalogo,
                habilita_guardias_catalogo = :habilita_guardias_catalogo,
                habilita_guardias_admin_actions = :habilita_guardias_admin_actions,
                habilita_autos = :habilita_autos,
                habilita_visitas_residente = :habilita_visitas_residente,
                habilita_paqueteria = :habilita_paqueteria,
                habilita_pagos = :habilita_pagos,
                habilita_comunicados = :habilita_comunicados,
                habilita_servicios_directorio = :habilita_servicios_directorio,
                habilita_control_acceso = :habilita_control_acceso,
                habilita_incidencias = :habilita_incidencias,
                habilita_personal_recurrente = :habilita_personal_recurrente,
                habilita_visitantes_rapidos = :habilita_visitantes_rapidos,
                habilita_materiales = :habilita_materiales,
                habilita_solicitudes_pendientes = :habilita_solicitudes_pendientes,
                habilita_bitacora_operativa = :habilita_bitacora_operativa,
                updated_at = NOW()
            WHERE residencial_id = :residencial_id
            LIMIT 1
        ");
        $params = [
            'residencial_id' => $residencialId,
            'preset_servicio' => $preset,
            'entitlement_enforcement' => $enforcement,
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
                'roles' => ['admin_residencial'],
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

if (!function_exists('service_profile_plan_role_enabled')) {
    function service_profile_plan_role_enabled(array $profile, string $role): bool
    {
        $role = service_profile_normalize_role_name($role);
        $flag = service_profile_role_flag_map()[$role] ?? null;
        if ($flag === null) {
            return false;
        }
        $planAllowedFlags = (array)($profile['plan_allowed_flags'] ?? []);
        if (empty($profile['plan_valid'])) {
            return service_profile_role_enabled($profile, $role);
        }
        return (int)($planAllowedFlags[$flag] ?? 0) === 1;
    }
}

if (!function_exists('service_profile_plan_module_enabled')) {
    function service_profile_plan_module_enabled(array $profile, string $module): bool
    {
        $module = service_profile_normalize_module_name($module);
        if (in_array($module, ['home', 'perfil', 'reglamento', 'contexto'], true)) {
            return true;
        }

        if (empty($profile['plan_valid'])) {
            return service_profile_module_enabled($profile, $module);
        }

        $planAllowedFlags = (array)($profile['plan_allowed_flags'] ?? []);
        foreach (service_profile_module_catalog() as $flag => $meta) {
            if (service_profile_normalize_module_name((string)($meta['module'] ?? '')) !== $module) {
                continue;
            }
            return (int)($planAllowedFlags[$flag] ?? 0) === 1;
        }

        return false;
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
        if (!service_profile_plan_role_enabled($profile, $role)) {
            return false;
        }

        return !empty(service_profile_role_assignable_views($profile, $role));
    }
}

if (!function_exists('service_profile_role_pending_reason')) {
    function service_profile_role_pending_reason(array $profile, string $role): array
    {
        $role = service_profile_normalize_role_name($role);
        $labels = service_profile_labels();
        $roleFlagMap = service_profile_role_flag_map();
        $roleLabel = $labels['roles'][$roleFlagMap[$role] ?? ''] ?? ucfirst(str_replace('_', ' ', $role));

        if (!service_profile_plan_role_enabled($profile, $role)) {
            return [
                'code' => 'role_disabled',
                'message' => sprintf('El rol %s no está incluido en el plan actual de este servicio.', $roleLabel),
            ];
        }

        $operableViews = service_profile_role_assignable_views($profile, $role);
        if ($operableViews) {
            return [
                'code' => 'ready',
                'message' => sprintf('El rol %s ya tiene vistas operables incluidas en el plan para este servicio.', $roleLabel),
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

        $planAllowedFlags = (array)($profile['plan_allowed_flags'] ?? []);
        $enabledModuleLabels = [];
        foreach ($configurableModules as $moduleMeta) {
            if ((int)($planAllowedFlags[$moduleMeta['flag']] ?? 0) === 1) {
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
            'admin_residencial' => ['home', 'perfil', 'reglamento', 'contexto', 'unidades', 'residentes', 'guardias', 'guardias_admin_actions', 'incidencias', 'comunicados', 'autos', 'personal_recurrente', 'visitantes_rapidos', 'materiales', 'solicitudes_pendientes', 'bitacora_operativa'],
            'guardia' => ['home', 'perfil', 'reglamento', 'contexto', 'accesos', 'autos', 'incidencias', 'paqueteria', 'personal_recurrente', 'materiales', 'bitacora_operativa'],
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
                'guardias' => 'guardias',
                'incidencias' => 'incidencias',
                'comunicados' => 'comunicados',
                'autos' => 'autos',
                'personal_recurrente' => 'personal_recurrente',
                'visitantes_rapidos' => 'visitantes_rapidos',
                'materiales' => 'materiales',
                'solicitudes_pendientes' => 'solicitudes_pendientes',
                'bitacora_operativa' => 'bitacora_operativa',
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

if (!function_exists('service_profile_assignable_module_allowed_for_role_and_service')) {
    function service_profile_assignable_module_allowed_for_role_and_service(array $profile, string $role, string $module): bool
    {
        $role = service_profile_normalize_role_name($role);
        $module = service_profile_normalize_module_name($module);

        if (!service_profile_plan_role_enabled($profile, $role)) {
            return false;
        }

        $roleModules = service_profile_role_module_matrix()[$role] ?? [];
        if (!in_array($module, $roleModules, true)) {
            return false;
        }

        return service_profile_plan_module_enabled($profile, $module);
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

if (!function_exists('service_profile_assignable_views_raw')) {
    function service_profile_assignable_views_raw(array $profile, string $role): array
    {
        $role = service_profile_normalize_role_name($role);
        $views = service_profile_role_view_matrix()[$role] ?? [];
        if (!$views) {
            return [];
        }

        return array_values(array_keys(array_filter($views, static function (string $module, string $view) use ($profile, $role): bool {
            return service_profile_assignable_module_allowed_for_role_and_service($profile, $role, $module);
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

if (!function_exists('service_profile_role_assignable_views')) {
    function service_profile_role_assignable_views(array $profile, string $role): array
    {
        $rawViews = service_profile_assignable_views_raw($profile, $role);
        if (!$rawViews) {
            return [];
        }

        return array_values(array_filter($rawViews, static function (string $view): bool {
            return !in_array($view, service_profile_common_views(), true);
        }));
    }
}

if (!function_exists('service_profile_role_runtime_pending_reason')) {
    function service_profile_role_runtime_pending_reason(array $profile, string $role): array
    {
        $role = service_profile_normalize_role_name($role);
        $labels = service_profile_labels();
        $roleFlagMap = service_profile_role_flag_map();
        $roleLabel = $labels['roles'][$roleFlagMap[$role] ?? ''] ?? ucfirst(str_replace('_', ' ', $role));

        if (!service_profile_plan_status_operational((string)($profile['estatus_plan'] ?? 'activo'))) {
            return [
                'code' => 'plan_not_operational',
                'message' => 'El plan del servicio está suspendido o cancelado, así que este operador no puede usarlo por ahora.',
            ];
        }

        if (!service_profile_role_enabled($profile, $role)) {
            return [
                'code' => 'role_disabled',
                'message' => sprintf('El rol %s no está habilitado actualmente en este servicio.', $roleLabel),
            ];
        }

        $operableViews = service_profile_role_operable_views($profile, $role);
        if ($operableViews) {
            return [
                'code' => 'ready',
                'message' => sprintf('El rol %s ya tiene vistas operables reales para este servicio.', $roleLabel),
            ];
        }

        $roleModules = service_profile_role_module_matrix()[$role] ?? [];
        $enabledModuleLabels = [];
        foreach (service_profile_module_catalog() as $flag => $meta) {
            $moduleName = service_profile_normalize_module_name((string)($meta['module'] ?? ''));
            if ($moduleName === '' || !in_array($role, (array)($meta['roles'] ?? []), true)) {
                continue;
            }
            if (!in_array($moduleName, $roleModules, true) || !empty($meta['support_only'])) {
                continue;
            }
            if ((int)($profile[$flag] ?? 0) === 1) {
                $enabledModuleLabels[] = $labels['modules'][$flag] ?? $flag;
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

        return [
            'code' => 'missing_modules',
            'message' => sprintf('Activa al menos un módulo operable para %s dentro del servicio.', $roleLabel),
        ];
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

        if (!service_profile_plan_status_operational((string)($profile['estatus_plan'] ?? 'activo'))) {
            return [
                'code' => 'suspendido_plan',
                'label' => 'Suspendido por plan',
                'ready' => false,
                'reason_code' => 'plan_not_operational',
                'reason_message' => 'El servicio está suspendido o cancelado por plan y no puede operar en este momento.',
            ];
        }

        if (empty(service_profile_role_operable_views($profile, $role))) {
            $reason = service_profile_role_runtime_pending_reason($profile, $role);
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
        if ($role === '' || !service_profile_role_enabled($profile, $role) || !service_profile_plan_status_operational((string)($profile['estatus_plan'] ?? 'activo'))) {
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
        if (!service_profile_plan_status_operational((string)($profile['estatus_plan'] ?? 'activo'))) {
            $title = 'Servicio suspendido por plan';
            $msg = 'El plan de este servicio está suspendido o cancelado y no puede operar en este momento.';
            app_abort(403, $title, $msg, [
                ['label' => 'Cerrar sesión', 'href' => app_logout_url()],
            ]);
        }
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
            'plan_id' => $profile['plan_id'] ?? 0,
            'estatus_plan' => $profile['estatus_plan'] ?? 'activo',
            'plan_operational' => !empty($profile['plan_operational']),
            'plan_valid' => !empty($profile['plan_valid']),
            'plan' => $profile['plan'] ?? null,
            'entitlement_enforcement' => $profile['entitlement_enforcement'] ?? 'compat',
            'alignment' => $profile['alignment'] ?? null,
            'assignable_roles' => service_profile_assignable_roles($profile),
            'role_assignable' => service_profile_role_assignable_to_service($profile, $role),
            'roles' => array_reduce(service_profile_role_flags(), static function (array $carry, string $flag) use ($profile, $labels): array {
                $storedFlags = (array)($profile['stored_flags'] ?? []);
                $planAllowedFlags = (array)($profile['plan_allowed_flags'] ?? []);
                $coreFlags = (array)($profile['core_flags'] ?? []);
                $planExtraFlags = (array)($profile['plan_extra_flags'] ?? []);
                $storedEnabled = (int)($storedFlags[$flag] ?? 0) === 1;
                $entitled = empty($profile['plan_valid']) ? true : ((int)($planAllowedFlags[$flag] ?? 0) === 1);
                $isCore = (int)($coreFlags[$flag] ?? 0) === 1;
                $isPlanExtra = !$isCore && (int)($planExtraFlags[$flag] ?? 0) === 1;
                $carry[$flag] = [
                    'enabled' => (int)($profile[$flag] ?? 0) === 1,
                    'stored_enabled' => $storedEnabled,
                    'plan_allowed' => $entitled,
                    'entitled' => $entitled,
                    'is_core' => $isCore,
                    'is_plan_extra' => $isPlanExtra,
                    'out_of_plan' => !$entitled && $storedEnabled,
                    'source' => empty($profile['plan_valid'])
                        ? 'sin_plan'
                        : ($entitled
                            ? ($storedEnabled
                                ? ($isCore ? 'core' : 'plan_extra')
                                : 'apagado_manual')
                            : 'premium_no_incluido'),
                    'label' => $labels['roles'][$flag] ?? $flag,
                ];
                return $carry;
            }, []),
            'modules' => array_reduce(service_profile_module_flags(), static function (array $carry, string $flag) use ($profile, $labels): array {
                $storedFlags = (array)($profile['stored_flags'] ?? []);
                $planAllowedFlags = (array)($profile['plan_allowed_flags'] ?? []);
                $coreFlags = (array)($profile['core_flags'] ?? []);
                $planExtraFlags = (array)($profile['plan_extra_flags'] ?? []);
                $storedEnabled = (int)($storedFlags[$flag] ?? 0) === 1;
                $entitled = empty($profile['plan_valid']) ? true : ((int)($planAllowedFlags[$flag] ?? 0) === 1);
                $isCore = (int)($coreFlags[$flag] ?? 0) === 1;
                $isPlanExtra = !$isCore && (int)($planExtraFlags[$flag] ?? 0) === 1;
                $carry[$flag] = [
                    'enabled' => (int)($profile[$flag] ?? 0) === 1,
                    'stored_enabled' => $storedEnabled,
                    'plan_allowed' => $entitled,
                    'entitled' => $entitled,
                    'is_core' => $isCore,
                    'is_plan_extra' => $isPlanExtra,
                    'out_of_plan' => !$entitled && $storedEnabled,
                    'source' => empty($profile['plan_valid'])
                        ? 'sin_plan'
                        : ($entitled
                            ? ($storedEnabled
                                ? ($isCore ? 'core' : 'plan_extra')
                                : 'apagado_manual')
                            : 'premium_no_incluido'),
                    'label' => $labels['modules'][$flag] ?? $flag,
                ];
                return $carry;
            }, []),
            'operable_views' => service_profile_role_operable_views($profile, $role),
            'assignable_views' => service_profile_role_assignable_views($profile, $role),
            'allowed_views' => service_profile_allowed_views($profile, $role),
            'matrix' => service_profile_frontend_matrix(),
        ];
    }
}
