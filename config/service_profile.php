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
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
                'habilita_personal_recurrente' => 1,
                'habilita_visitantes_rapidos' => 1,
                'habilita_materiales' => 1,
                'habilita_solicitudes_pendientes' => 1,
                'habilita_bitacora_operativa' => 1,
            ]),
            'obra' => array_merge($base, [
                'habilita_admin_operativo' => 1,
                'habilita_guardia' => 1,
                'habilita_guardias_catalogo' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
                'habilita_personal_recurrente' => 1,
                'habilita_visitantes_rapidos' => 1,
                'habilita_materiales' => 1,
                'habilita_solicitudes_pendientes' => 1,
                'habilita_bitacora_operativa' => 1,
            ]),
            'comercio' => array_merge($base, [
                'habilita_admin_operativo' => 1,
                'habilita_guardia' => 1,
                'habilita_guardias_catalogo' => 1,
                'habilita_control_acceso' => 1,
                'habilita_incidencias' => 1,
                'habilita_visitantes_rapidos' => 1,
                'habilita_bitacora_operativa' => 1,
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

        $stmt = $pdo->prepare("
            UPDATE residenciales_servicio_config
            SET preset_servicio = :preset_servicio,
                habilita_admin_operativo = :habilita_admin_operativo,
                habilita_guardia = :habilita_guardia,
                habilita_residente = :habilita_residente,
                habilita_unidades = :habilita_unidades,
                habilita_residentes_catalogo = :habilita_residentes_catalogo,
                habilita_guardias_catalogo = :habilita_guardias_catalogo,
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
        return match ($role) {
            'admin_residencial' => (int)($profile['habilita_admin_operativo'] ?? 0) === 1,
            'guardia' => (int)($profile['habilita_guardia'] ?? 0) === 1,
            'residente' => (int)($profile['habilita_residente'] ?? 0) === 1,
            default => true,
        };
    }
}

if (!function_exists('service_profile_module_enabled')) {
    function service_profile_module_enabled(array $profile, string $module): bool
    {
        return match ($module) {
            'home', 'perfil', 'reglamento', 'contexto' => true,
            'unidades' => (int)($profile['habilita_unidades'] ?? 0) === 1,
            'residentes' => (int)($profile['habilita_residentes_catalogo'] ?? 0) === 1,
            'guardias' => (int)($profile['habilita_guardias_catalogo'] ?? 0) === 1,
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
    }
}

if (!function_exists('service_profile_allowed_views')) {
    function service_profile_allowed_views(array $profile, string $role): array
    {
        $base = match ($role) {
            'admin_residencial' => ['home', 'perfil', 'reglamento', 'unidades', 'residentes', 'guardias', 'incidencias', 'comunicados', 'autos', 'personal_recurrente', 'visitantes_rapidos', 'materiales', 'solicitudes_pendientes', 'bitacora_operativa'],
            'guardia' => ['home', 'perfil', 'reglamento', 'accesos', 'autos', 'incidencias', 'paqueteria', 'personas_dentro', 'materiales_autorizados', 'bitacora_hoy'],
            'residente' => ['home', 'perfil', 'reglamento', 'visitas', 'incidencias', 'paqueteria', 'autos', 'pagos', 'comunicados', 'servicios'],
            default => [],
        };

        if (!service_profile_role_enabled($profile, $role)) {
            return [];
        }

        return array_values(array_filter($base, static function (string $view) use ($profile): bool {
            return service_profile_module_enabled($profile, $view);
        }));
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
        if (!service_profile_module_enabled($profile, $module)) {
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
            'allowed_views' => service_profile_allowed_views($profile, $role),
        ];
    }
}
