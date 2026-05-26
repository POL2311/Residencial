<?php
declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

if (!function_exists('operational_allowed_modes')) {
    function operational_allowed_modes(): array
    {
        return ['residencial', 'empresa', 'obra', 'comercio', 'servicio', 'retailops'];
    }
}

if (!function_exists('operational_normalize_mode')) {
    function operational_normalize_mode(?string $mode): string
    {
        $mode = trim((string)($mode ?? ''));
        return in_array($mode, operational_allowed_modes(), true) ? $mode : 'residencial';
    }
}

if (!function_exists('operational_is_operational_mode')) {
    function operational_is_operational_mode(?string $mode): bool
    {
        return operational_normalize_mode($mode) !== 'residencial';
    }
}

if (!function_exists('operational_generate_token')) {
    function operational_generate_token(int $bytes = 18): string
    {
        return bin2hex(random_bytes(max(12, $bytes)));
    }
}

if (!function_exists('operational_bool_int')) {
    function operational_bool_int($value): int
    {
        return (int)(filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? ((string)$value === '1'));
    }
}

if (!function_exists('operational_table_exists')) {
    function operational_table_exists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
        ");
        $stmt->execute(['table_name' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('operational_column_exists')) {
    function operational_column_exists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('operational_column_is_nullable')) {
    function operational_column_is_nullable(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare("
            SELECT IS_NULLABLE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND COLUMN_NAME = :column_name
            LIMIT 1
        ");
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);
        return strtoupper((string)($stmt->fetchColumn() ?: '')) === 'YES';
    }
}

if (!function_exists('operational_index_exists')) {
    function operational_index_exists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name
        ");
        $stmt->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('operational_foreign_key_exists')) {
    function operational_foreign_key_exists(PDO $pdo, string $table, string $constraint): bool
    {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table_name
              AND CONSTRAINT_NAME = :constraint_name
              AND CONSTRAINT_TYPE = 'FOREIGN KEY'
        ");
        $stmt->execute([
            'table_name' => $table,
            'constraint_name' => $constraint,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('operational_schema_ensure')) {
    function operational_schema_ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        if (!operational_column_exists($pdo, 'residenciales', 'modo_operacion')) {
            $pdo->exec("ALTER TABLE residenciales ADD COLUMN modo_operacion VARCHAR(20) NOT NULL DEFAULT 'residencial' AFTER tipo");
        }

        if (!operational_table_exists($pdo, 'areas_operativas')) {
            $pdo->exec("
                CREATE TABLE areas_operativas (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    codigo VARCHAR(50) DEFAULT NULL,
                    tipo VARCHAR(50) DEFAULT NULL,
                    descripcion TEXT DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_areas_operativas_residencial (residencial_id),
                    KEY idx_areas_operativas_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'areas_operativas', 'fk_areas_operativas_residencial')) {
            $pdo->exec("
                ALTER TABLE areas_operativas
                ADD CONSTRAINT fk_areas_operativas_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'personas_recurrentes')) {
            $pdo->exec("
                CREATE TABLE personas_recurrentes (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    area_id INT(11) DEFAULT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    foto_url VARCHAR(255) DEFAULT NULL,
                    telefono VARCHAR(30) DEFAULT NULL,
                    empresa VARCHAR(120) DEFAULT NULL,
                    puesto VARCHAR(120) DEFAULT NULL,
                    notas TEXT DEFAULT NULL,
                    qr_token VARCHAR(80) NOT NULL,
                    pin_hash VARCHAR(255) NOT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    esta_dentro TINYINT(1) NOT NULL DEFAULT 0,
                    estatus_cumplimiento VARCHAR(40) NOT NULL DEFAULT 'autorizado',
                    motivo_bloqueo VARCHAR(255) DEFAULT NULL,
                    cumplimiento_actualizado_at DATETIME DEFAULT NULL,
                    cumplimiento_actualizado_por INT(11) DEFAULT NULL,
                    ultima_entrada_at DATETIME DEFAULT NULL,
                    ultima_salida_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_personas_recurrentes_qr_token (qr_token),
                    KEY idx_personas_recurrentes_residencial (residencial_id),
                    KEY idx_personas_recurrentes_area (area_id),
                    KEY idx_personas_recurrentes_activo (activo),
                    KEY idx_personas_recurrentes_dentro (esta_dentro),
                    KEY idx_personas_recurrentes_cumplimiento (estatus_cumplimiento),
                    KEY idx_personas_recurrentes_cumplimiento_por (cumplimiento_actualizado_por)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'personas_recurrentes', 'fk_personas_recurrentes_residencial')) {
            $pdo->exec("
                ALTER TABLE personas_recurrentes
                ADD CONSTRAINT fk_personas_recurrentes_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'personas_recurrentes', 'fk_personas_recurrentes_area')) {
            $pdo->exec("
                ALTER TABLE personas_recurrentes
                ADD CONSTRAINT fk_personas_recurrentes_area
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_column_exists($pdo, 'personas_recurrentes', 'estatus_cumplimiento')) {
            $pdo->exec("ALTER TABLE personas_recurrentes ADD COLUMN estatus_cumplimiento VARCHAR(40) NOT NULL DEFAULT 'autorizado' AFTER esta_dentro");
        }
        if (!operational_column_exists($pdo, 'personas_recurrentes', 'motivo_bloqueo')) {
            $pdo->exec("ALTER TABLE personas_recurrentes ADD COLUMN motivo_bloqueo VARCHAR(255) DEFAULT NULL AFTER estatus_cumplimiento");
        }
        if (!operational_column_exists($pdo, 'personas_recurrentes', 'cumplimiento_actualizado_at')) {
            $pdo->exec("ALTER TABLE personas_recurrentes ADD COLUMN cumplimiento_actualizado_at DATETIME DEFAULT NULL AFTER motivo_bloqueo");
        }
        if (!operational_column_exists($pdo, 'personas_recurrentes', 'cumplimiento_actualizado_por')) {
            $pdo->exec("ALTER TABLE personas_recurrentes ADD COLUMN cumplimiento_actualizado_por INT(11) DEFAULT NULL AFTER cumplimiento_actualizado_at");
        }
        if (!operational_index_exists($pdo, 'personas_recurrentes', 'idx_personas_recurrentes_cumplimiento')) {
            $pdo->exec("ALTER TABLE personas_recurrentes ADD KEY idx_personas_recurrentes_cumplimiento (estatus_cumplimiento)");
        }
        if (!operational_index_exists($pdo, 'personas_recurrentes', 'idx_personas_recurrentes_cumplimiento_por')) {
            $pdo->exec("ALTER TABLE personas_recurrentes ADD KEY idx_personas_recurrentes_cumplimiento_por (cumplimiento_actualizado_por)");
        }
        if (!operational_foreign_key_exists($pdo, 'personas_recurrentes', 'fk_personas_recurrentes_cumplimiento_por')) {
            $pdo->exec("
                ALTER TABLE personas_recurrentes
                ADD CONSTRAINT fk_personas_recurrentes_cumplimiento_por
                FOREIGN KEY (cumplimiento_actualizado_por) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'visitantes_rapidos')) {
            $pdo->exec("
                CREATE TABLE visitantes_rapidos (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    responsable_user_id INT(11) NOT NULL,
                    area_id INT(11) DEFAULT NULL,
                    nombre_visitante VARCHAR(150) NOT NULL,
                    empresa VARCHAR(120) DEFAULT NULL,
                    placa_vehiculo VARCHAR(20) DEFAULT NULL,
                    motivo VARCHAR(255) DEFAULT NULL,
                    qr_token VARCHAR(80) NOT NULL,
                    estado VARCHAR(30) NOT NULL DEFAULT 'activo',
                    notas_admin TEXT DEFAULT NULL,
                    fecha_desde DATETIME DEFAULT NULL,
                    fecha_hasta DATETIME DEFAULT NULL,
                    esta_dentro TINYINT(1) NOT NULL DEFAULT 0,
                    ultimo_evento_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_visitantes_rapidos_qr_token (qr_token),
                    KEY idx_visitantes_rapidos_residencial (residencial_id),
                    KEY idx_visitantes_rapidos_responsable (responsable_user_id),
                    KEY idx_visitantes_rapidos_area (area_id),
                    KEY idx_visitantes_rapidos_estado (estado)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'visitantes_rapidos', 'fk_visitantes_rapidos_residencial')) {
            $pdo->exec("
                ALTER TABLE visitantes_rapidos
                ADD CONSTRAINT fk_visitantes_rapidos_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'visitantes_rapidos', 'fk_visitantes_rapidos_responsable')) {
            $pdo->exec("
                ALTER TABLE visitantes_rapidos
                ADD CONSTRAINT fk_visitantes_rapidos_responsable
                FOREIGN KEY (responsable_user_id) REFERENCES users(id)
                ON DELETE RESTRICT
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'visitantes_rapidos', 'fk_visitantes_rapidos_area')) {
            $pdo->exec("
                ALTER TABLE visitantes_rapidos
                ADD CONSTRAINT fk_visitantes_rapidos_area
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'catalogo_materiales')) {
            $pdo->exec("
                CREATE TABLE catalogo_materiales (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    categoria VARCHAR(80) DEFAULT NULL,
                    descripcion TEXT DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_catalogo_materiales_residencial (residencial_id),
                    KEY idx_catalogo_materiales_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'catalogo_materiales', 'fk_catalogo_materiales_residencial')) {
            $pdo->exec("
                ALTER TABLE catalogo_materiales
                ADD CONSTRAINT fk_catalogo_materiales_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'permisos_materiales')) {
            $pdo->exec("
                CREATE TABLE permisos_materiales (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    area_id INT(11) DEFAULT NULL,
                    responsable_user_id INT(11) NOT NULL,
                    tipo_movimiento VARCHAR(20) NOT NULL DEFAULT 'entrada',
                    qr_token VARCHAR(80) NOT NULL,
                    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
                    solicitado_por_user_id INT(11) DEFAULT NULL,
                    aprobado_por_user_id INT(11) DEFAULT NULL,
                    aprobado_at DATETIME DEFAULT NULL,
                    notas TEXT DEFAULT NULL,
                    fecha_desde DATETIME DEFAULT NULL,
                    fecha_hasta DATETIME DEFAULT NULL,
                    ejecutado_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_permisos_materiales_qr_token (qr_token),
                    KEY idx_permisos_materiales_residencial (residencial_id),
                    KEY idx_permisos_materiales_area (area_id),
                    KEY idx_permisos_materiales_responsable (responsable_user_id),
                    KEY idx_permisos_materiales_estado (estado)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'permisos_materiales', 'fk_permisos_materiales_residencial')) {
            $pdo->exec("
                ALTER TABLE permisos_materiales
                ADD CONSTRAINT fk_permisos_materiales_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'permisos_materiales', 'fk_permisos_materiales_area')) {
            $pdo->exec("
                ALTER TABLE permisos_materiales
                ADD CONSTRAINT fk_permisos_materiales_area
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'permisos_materiales', 'fk_permisos_materiales_responsable')) {
            $pdo->exec("
                ALTER TABLE permisos_materiales
                ADD CONSTRAINT fk_permisos_materiales_responsable
                FOREIGN KEY (responsable_user_id) REFERENCES users(id)
                ON DELETE RESTRICT
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'permisos_materiales', 'fk_permisos_materiales_solicitado')) {
            $pdo->exec("
                ALTER TABLE permisos_materiales
                ADD CONSTRAINT fk_permisos_materiales_solicitado
                FOREIGN KEY (solicitado_por_user_id) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'permisos_materiales', 'fk_permisos_materiales_aprobado')) {
            $pdo->exec("
                ALTER TABLE permisos_materiales
                ADD CONSTRAINT fk_permisos_materiales_aprobado
                FOREIGN KEY (aprobado_por_user_id) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'permisos_materiales_items')) {
            $pdo->exec("
                CREATE TABLE permisos_materiales_items (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    permiso_id INT(11) NOT NULL,
                    material_id INT(11) DEFAULT NULL,
                    material_nombre VARCHAR(150) NOT NULL,
                    cantidad_texto VARCHAR(120) NOT NULL,
                    agregar_a_catalogo TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_permisos_materiales_items_permiso (permiso_id),
                    KEY idx_permisos_materiales_items_material (material_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'permisos_materiales_items', 'fk_permisos_materiales_items_permiso')) {
            $pdo->exec("
                ALTER TABLE permisos_materiales_items
                ADD CONSTRAINT fk_permisos_materiales_items_permiso
                FOREIGN KEY (permiso_id) REFERENCES permisos_materiales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'permisos_materiales_items', 'fk_permisos_materiales_items_material')) {
            $pdo->exec("
                ALTER TABLE permisos_materiales_items
                ADD CONSTRAINT fk_permisos_materiales_items_material
                FOREIGN KEY (material_id) REFERENCES catalogo_materiales(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'catalogo_herramientas')) {
            $pdo->exec("
                CREATE TABLE catalogo_herramientas (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    descripcion TEXT DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_catalogo_herramientas_residencial (residencial_id),
                    KEY idx_catalogo_herramientas_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'catalogo_herramientas', 'fk_catalogo_herramientas_residencial')) {
            $pdo->exec("
                ALTER TABLE catalogo_herramientas
                ADD CONSTRAINT fk_catalogo_herramientas_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'prestamos_herramientas')) {
            $pdo->exec("
                CREATE TABLE prestamos_herramientas (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    herramienta_id INT(11) NOT NULL,
                    guardia_id INT(11) NOT NULL,
                    unidad_id INT(11) DEFAULT NULL,
                    residente_id INT(11) DEFAULT NULL,
                    area_id INT(11) DEFAULT NULL,
                    persona_recurrente_id INT(11) DEFAULT NULL,
                    responsable_nombre VARCHAR(180) DEFAULT NULL,
                    estado VARCHAR(20) NOT NULL DEFAULT 'prestado',
                    notas TEXT DEFAULT NULL,
                    prestado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    devuelto_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_prestamos_herramientas_residencial (residencial_id),
                    KEY idx_prestamos_herramientas_estado (estado),
                    KEY idx_prestamos_herramientas_guardia (guardia_id),
                    KEY idx_prestamos_herramientas_herramienta (herramienta_id),
                    KEY idx_prestamos_herramientas_unidad (unidad_id),
                    KEY idx_prestamos_herramientas_residente (residente_id),
                    KEY idx_prestamos_herramientas_area (area_id),
                    KEY idx_prestamos_herramientas_persona (persona_recurrente_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (operational_column_exists($pdo, 'prestamos_herramientas', 'unidad_id') && !operational_column_is_nullable($pdo, 'prestamos_herramientas', 'unidad_id')) {
            $pdo->exec("ALTER TABLE prestamos_herramientas MODIFY unidad_id INT(11) DEFAULT NULL");
        }
        if (!operational_column_exists($pdo, 'prestamos_herramientas', 'area_id')) {
            $pdo->exec("ALTER TABLE prestamos_herramientas ADD COLUMN area_id INT(11) DEFAULT NULL AFTER residente_id");
        }
        if (!operational_column_exists($pdo, 'prestamos_herramientas', 'persona_recurrente_id')) {
            $pdo->exec("ALTER TABLE prestamos_herramientas ADD COLUMN persona_recurrente_id INT(11) DEFAULT NULL AFTER area_id");
        }
        if (!operational_column_exists($pdo, 'prestamos_herramientas', 'responsable_nombre')) {
            $pdo->exec("ALTER TABLE prestamos_herramientas ADD COLUMN responsable_nombre VARCHAR(180) DEFAULT NULL AFTER persona_recurrente_id");
        }
        if (!operational_index_exists($pdo, 'prestamos_herramientas', 'idx_prestamos_herramientas_area')) {
            $pdo->exec("ALTER TABLE prestamos_herramientas ADD KEY idx_prestamos_herramientas_area (area_id)");
        }
        if (!operational_index_exists($pdo, 'prestamos_herramientas', 'idx_prestamos_herramientas_persona')) {
            $pdo->exec("ALTER TABLE prestamos_herramientas ADD KEY idx_prestamos_herramientas_persona (persona_recurrente_id)");
        }
        if (!operational_foreign_key_exists($pdo, 'prestamos_herramientas', 'fk_prestamos_herramientas_residencial')) {
            $pdo->exec("
                ALTER TABLE prestamos_herramientas
                ADD CONSTRAINT fk_prestamos_herramientas_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'prestamos_herramientas', 'fk_prestamos_herramientas_herramienta')) {
            $pdo->exec("
                ALTER TABLE prestamos_herramientas
                ADD CONSTRAINT fk_prestamos_herramientas_herramienta
                FOREIGN KEY (herramienta_id) REFERENCES catalogo_herramientas(id)
                ON DELETE RESTRICT
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'prestamos_herramientas', 'fk_prestamos_herramientas_guardia')) {
            $pdo->exec("
                ALTER TABLE prestamos_herramientas
                ADD CONSTRAINT fk_prestamos_herramientas_guardia
                FOREIGN KEY (guardia_id) REFERENCES users(id)
                ON DELETE RESTRICT
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'prestamos_herramientas', 'fk_prestamos_herramientas_unidad')) {
            $pdo->exec("
                ALTER TABLE prestamos_herramientas
                ADD CONSTRAINT fk_prestamos_herramientas_unidad
                FOREIGN KEY (unidad_id) REFERENCES unidades(id)
                ON DELETE RESTRICT
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'prestamos_herramientas', 'fk_prestamos_herramientas_residente')) {
            $pdo->exec("
                ALTER TABLE prestamos_herramientas
                ADD CONSTRAINT fk_prestamos_herramientas_residente
                FOREIGN KEY (residente_id) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'prestamos_herramientas', 'fk_prestamos_herramientas_area')) {
            $pdo->exec("
                ALTER TABLE prestamos_herramientas
                ADD CONSTRAINT fk_prestamos_herramientas_area
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'prestamos_herramientas', 'fk_prestamos_herramientas_persona')) {
            $pdo->exec("
                ALTER TABLE prestamos_herramientas
                ADD CONSTRAINT fk_prestamos_herramientas_persona
                FOREIGN KEY (persona_recurrente_id) REFERENCES personas_recurrentes(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'archivos_operativos')) {
            $pdo->exec("
                CREATE TABLE archivos_operativos (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    entidad_tipo VARCHAR(50) NOT NULL,
                    entidad_id INT(11) NOT NULL,
                    subtipo VARCHAR(50) DEFAULT NULL,
                    storage_disk VARCHAR(40) NOT NULL DEFAULT 'local_public',
                    storage_path VARCHAR(255) NOT NULL,
                    public_url VARCHAR(255) NOT NULL,
                    mime_type VARCHAR(80) NOT NULL,
                    size_bytes INT(11) NOT NULL DEFAULT 0,
                    width INT(11) DEFAULT NULL,
                    height INT(11) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_archivos_operativos_residencial (residencial_id),
                    KEY idx_archivos_operativos_entidad (entidad_tipo, entidad_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'archivos_operativos', 'fk_archivos_operativos_residencial')) {
            $pdo->exec("
                ALTER TABLE archivos_operativos
                ADD CONSTRAINT fk_archivos_operativos_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'bitacora_operativa')) {
            $pdo->exec("
                CREATE TABLE bitacora_operativa (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    guardia_id INT(11) DEFAULT NULL,
                    tipo_origen VARCHAR(50) NOT NULL,
                    origen_id INT(11) DEFAULT NULL,
                    tipo_evento VARCHAR(50) NOT NULL,
                    resultado VARCHAR(30) NOT NULL DEFAULT 'permitido',
                    persona_recurrente_id INT(11) DEFAULT NULL,
                    visitante_rapido_id INT(11) DEFAULT NULL,
                    permiso_material_id INT(11) DEFAULT NULL,
                    area_id INT(11) DEFAULT NULL,
                    observaciones TEXT DEFAULT NULL,
                    metadata_json LONGTEXT DEFAULT NULL,
                    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_bitacora_operativa_residencial (residencial_id),
                    KEY idx_bitacora_operativa_guardia (guardia_id),
                    KEY idx_bitacora_operativa_fecha (fecha_hora),
                    KEY idx_bitacora_operativa_area (area_id),
                    KEY idx_bitacora_operativa_tipo (tipo_origen, tipo_evento)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'bitacora_operativa', 'fk_bitacora_operativa_residencial')) {
            $pdo->exec("
                ALTER TABLE bitacora_operativa
                ADD CONSTRAINT fk_bitacora_operativa_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'bitacora_operativa', 'fk_bitacora_operativa_guardia')) {
            $pdo->exec("
                ALTER TABLE bitacora_operativa
                ADD CONSTRAINT fk_bitacora_operativa_guardia
                FOREIGN KEY (guardia_id) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'bitacora_operativa', 'fk_bitacora_operativa_persona')) {
            $pdo->exec("
                ALTER TABLE bitacora_operativa
                ADD CONSTRAINT fk_bitacora_operativa_persona
                FOREIGN KEY (persona_recurrente_id) REFERENCES personas_recurrentes(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'bitacora_operativa', 'fk_bitacora_operativa_visitante')) {
            $pdo->exec("
                ALTER TABLE bitacora_operativa
                ADD CONSTRAINT fk_bitacora_operativa_visitante
                FOREIGN KEY (visitante_rapido_id) REFERENCES visitantes_rapidos(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'bitacora_operativa', 'fk_bitacora_operativa_permiso')) {
            $pdo->exec("
                ALTER TABLE bitacora_operativa
                ADD CONSTRAINT fk_bitacora_operativa_permiso
                FOREIGN KEY (permiso_material_id) REFERENCES permisos_materiales(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'bitacora_operativa', 'fk_bitacora_operativa_area')) {
            $pdo->exec("
                ALTER TABLE bitacora_operativa
                ADD CONSTRAINT fk_bitacora_operativa_area
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_column_exists($pdo, 'incidencias', 'area_id')) {
            $pdo->exec("ALTER TABLE incidencias ADD COLUMN area_id INT(11) DEFAULT NULL AFTER guardia_id");
        }
        if (!operational_column_exists($pdo, 'incidencias', 'persona_recurrente_id')) {
            $pdo->exec("ALTER TABLE incidencias ADD COLUMN persona_recurrente_id INT(11) DEFAULT NULL AFTER area_id");
        }
        if (!operational_column_exists($pdo, 'incidencias', 'visitante_rapido_id')) {
            $pdo->exec("ALTER TABLE incidencias ADD COLUMN visitante_rapido_id INT(11) DEFAULT NULL AFTER persona_recurrente_id");
        }
        if (!operational_column_exists($pdo, 'incidencias', 'permiso_material_id')) {
            $pdo->exec("ALTER TABLE incidencias ADD COLUMN permiso_material_id INT(11) DEFAULT NULL AFTER visitante_rapido_id");
        }
        if (!operational_column_exists($pdo, 'incidencias', 'origen_tipo')) {
            $pdo->exec("ALTER TABLE incidencias ADD COLUMN origen_tipo VARCHAR(50) DEFAULT NULL AFTER permiso_material_id");
        }

        $pdo->exec("ALTER TABLE incidencias MODIFY COLUMN unidad_id INT(11) DEFAULT NULL");
        $pdo->exec("ALTER TABLE incidencias MODIFY COLUMN residente_id INT(11) DEFAULT NULL");
        $pdo->exec("ALTER TABLE incidencias MODIFY COLUMN tipo VARCHAR(50) NOT NULL DEFAULT 'seguridad'");

        if (!operational_index_exists($pdo, 'incidencias', 'idx_incidencias_area')) {
            $pdo->exec("ALTER TABLE incidencias ADD KEY idx_incidencias_area (area_id)");
        }
        if (!operational_index_exists($pdo, 'incidencias', 'idx_incidencias_persona')) {
            $pdo->exec("ALTER TABLE incidencias ADD KEY idx_incidencias_persona (persona_recurrente_id)");
        }
        if (!operational_index_exists($pdo, 'incidencias', 'idx_incidencias_visitante')) {
            $pdo->exec("ALTER TABLE incidencias ADD KEY idx_incidencias_visitante (visitante_rapido_id)");
        }
        if (!operational_index_exists($pdo, 'incidencias', 'idx_incidencias_permiso')) {
            $pdo->exec("ALTER TABLE incidencias ADD KEY idx_incidencias_permiso (permiso_material_id)");
        }
        if (!operational_foreign_key_exists($pdo, 'incidencias', 'fk_incidencias_area_operativa')) {
            $pdo->exec("
                ALTER TABLE incidencias
                ADD CONSTRAINT fk_incidencias_area_operativa
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'incidencias', 'fk_incidencias_persona_operativa')) {
            $pdo->exec("
                ALTER TABLE incidencias
                ADD CONSTRAINT fk_incidencias_persona_operativa
                FOREIGN KEY (persona_recurrente_id) REFERENCES personas_recurrentes(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'incidencias', 'fk_incidencias_visitante_operativa')) {
            $pdo->exec("
                ALTER TABLE incidencias
                ADD CONSTRAINT fk_incidencias_visitante_operativa
                FOREIGN KEY (visitante_rapido_id) REFERENCES visitantes_rapidos(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'incidencias', 'fk_incidencias_permiso_operativa')) {
            $pdo->exec("
                ALTER TABLE incidencias
                ADD CONSTRAINT fk_incidencias_permiso_operativa
                FOREIGN KEY (permiso_material_id) REFERENCES permisos_materiales(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'rondines_rutas')) {
            $pdo->exec("
                CREATE TABLE rondines_rutas (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    descripcion TEXT DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_by INT(11) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_rondines_rutas_residencial (residencial_id),
                    KEY idx_rondines_rutas_activo (activo),
                    KEY idx_rondines_rutas_created_by (created_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_rutas', 'fk_rondines_rutas_residencial')) {
            $pdo->exec("
                ALTER TABLE rondines_rutas
                ADD CONSTRAINT fk_rondines_rutas_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_rutas', 'fk_rondines_rutas_created_by')) {
            $pdo->exec("
                ALTER TABLE rondines_rutas
                ADD CONSTRAINT fk_rondines_rutas_created_by
                FOREIGN KEY (created_by) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'rondines_puntos')) {
            $pdo->exec("
                CREATE TABLE rondines_puntos (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    area_id INT(11) DEFAULT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    descripcion TEXT DEFAULT NULL,
                    codigo_qr VARCHAR(90) NOT NULL,
                    latitud DECIMAL(10,7) DEFAULT NULL,
                    longitud DECIMAL(10,7) DEFAULT NULL,
                    radio_metros INT(11) NOT NULL DEFAULT 50,
                    requiere_foto TINYINT(1) NOT NULL DEFAULT 0,
                    requiere_observacion TINYINT(1) NOT NULL DEFAULT 0,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_rondines_puntos_codigo_qr (codigo_qr),
                    KEY idx_rondines_puntos_residencial (residencial_id),
                    KEY idx_rondines_puntos_area (area_id),
                    KEY idx_rondines_puntos_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_puntos', 'fk_rondines_puntos_residencial')) {
            $pdo->exec("
                ALTER TABLE rondines_puntos
                ADD CONSTRAINT fk_rondines_puntos_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_puntos', 'fk_rondines_puntos_area')) {
            $pdo->exec("
                ALTER TABLE rondines_puntos
                ADD CONSTRAINT fk_rondines_puntos_area
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'rondines_ruta_puntos')) {
            $pdo->exec("
                CREATE TABLE rondines_ruta_puntos (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    ruta_id INT(11) NOT NULL,
                    punto_id INT(11) NOT NULL,
                    orden INT(11) NOT NULL DEFAULT 1,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_rondines_ruta_puntos_ruta_punto (ruta_id, punto_id),
                    KEY idx_rondines_ruta_puntos_ruta (ruta_id),
                    KEY idx_rondines_ruta_puntos_punto (punto_id),
                    KEY idx_rondines_ruta_puntos_orden (ruta_id, orden)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_ruta_puntos', 'fk_rondines_ruta_puntos_ruta')) {
            $pdo->exec("
                ALTER TABLE rondines_ruta_puntos
                ADD CONSTRAINT fk_rondines_ruta_puntos_ruta
                FOREIGN KEY (ruta_id) REFERENCES rondines_rutas(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_ruta_puntos', 'fk_rondines_ruta_puntos_punto')) {
            $pdo->exec("
                ALTER TABLE rondines_ruta_puntos
                ADD CONSTRAINT fk_rondines_ruta_puntos_punto
                FOREIGN KEY (punto_id) REFERENCES rondines_puntos(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'rondines_ejecuciones')) {
            $pdo->exec("
                CREATE TABLE rondines_ejecuciones (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    ruta_id INT(11) NOT NULL,
                    guardia_id INT(11) DEFAULT NULL,
                    estado VARCHAR(30) NOT NULL DEFAULT 'en_proceso',
                    inicio_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    fin_at DATETIME DEFAULT NULL,
                    latitud_inicio DECIMAL(10,7) DEFAULT NULL,
                    longitud_inicio DECIMAL(10,7) DEFAULT NULL,
                    precision_inicio_metros DECIMAL(10,2) DEFAULT NULL,
                    latitud_fin DECIMAL(10,7) DEFAULT NULL,
                    longitud_fin DECIMAL(10,7) DEFAULT NULL,
                    precision_fin_metros DECIMAL(10,2) DEFAULT NULL,
                    observaciones TEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_rondines_ejecuciones_residencial (residencial_id),
                    KEY idx_rondines_ejecuciones_ruta (ruta_id),
                    KEY idx_rondines_ejecuciones_guardia (guardia_id),
                    KEY idx_rondines_ejecuciones_estado (estado),
                    KEY idx_rondines_ejecuciones_inicio (inicio_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_ejecuciones', 'fk_rondines_ejecuciones_residencial')) {
            $pdo->exec("
                ALTER TABLE rondines_ejecuciones
                ADD CONSTRAINT fk_rondines_ejecuciones_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_ejecuciones', 'fk_rondines_ejecuciones_ruta')) {
            $pdo->exec("
                ALTER TABLE rondines_ejecuciones
                ADD CONSTRAINT fk_rondines_ejecuciones_ruta
                FOREIGN KEY (ruta_id) REFERENCES rondines_rutas(id)
                ON DELETE RESTRICT
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_ejecuciones', 'fk_rondines_ejecuciones_guardia')) {
            $pdo->exec("
                ALTER TABLE rondines_ejecuciones
                ADD CONSTRAINT fk_rondines_ejecuciones_guardia
                FOREIGN KEY (guardia_id) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'rondines_eventos')) {
            $pdo->exec("
                CREATE TABLE rondines_eventos (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    ejecucion_id INT(11) NOT NULL,
                    residencial_id INT(11) NOT NULL,
                    punto_id INT(11) NOT NULL,
                    guardia_id INT(11) DEFAULT NULL,
                    escaneado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    estado VARCHAR(30) NOT NULL DEFAULT 'correcto',
                    latitud DECIMAL(10,7) DEFAULT NULL,
                    longitud DECIMAL(10,7) DEFAULT NULL,
                    precision_metros DECIMAL(10,2) DEFAULT NULL,
                    distancia_punto_metros DECIMAL(10,2) DEFAULT NULL,
                    gps_valido TINYINT(1) NOT NULL DEFAULT 0,
                    observacion TEXT DEFAULT NULL,
                    evidencia_url VARCHAR(255) DEFAULT NULL,
                    metadata_json LONGTEXT DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_rondines_eventos_ejecucion_punto (ejecucion_id, punto_id),
                    KEY idx_rondines_eventos_residencial (residencial_id),
                    KEY idx_rondines_eventos_punto (punto_id),
                    KEY idx_rondines_eventos_guardia (guardia_id),
                    KEY idx_rondines_eventos_estado (estado),
                    KEY idx_rondines_eventos_escaneado (escaneado_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_eventos', 'fk_rondines_eventos_ejecucion')) {
            $pdo->exec("
                ALTER TABLE rondines_eventos
                ADD CONSTRAINT fk_rondines_eventos_ejecucion
                FOREIGN KEY (ejecucion_id) REFERENCES rondines_ejecuciones(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_eventos', 'fk_rondines_eventos_residencial')) {
            $pdo->exec("
                ALTER TABLE rondines_eventos
                ADD CONSTRAINT fk_rondines_eventos_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_eventos', 'fk_rondines_eventos_punto')) {
            $pdo->exec("
                ALTER TABLE rondines_eventos
                ADD CONSTRAINT fk_rondines_eventos_punto
                FOREIGN KEY (punto_id) REFERENCES rondines_puntos(id)
                ON DELETE RESTRICT
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'rondines_eventos', 'fk_rondines_eventos_guardia')) {
            $pdo->exec("
                ALTER TABLE rondines_eventos
                ADD CONSTRAINT fk_rondines_eventos_guardia
                FOREIGN KEY (guardia_id) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'proveedores')) {
            $pdo->exec("
                CREATE TABLE proveedores (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    nombre_comercial VARCHAR(180) NOT NULL,
                    razon_social VARCHAR(220) DEFAULT NULL,
                    tipo_servicio VARCHAR(120) DEFAULT NULL,
                    contacto_nombre VARCHAR(150) DEFAULT NULL,
                    contacto_telefono VARCHAR(40) DEFAULT NULL,
                    contacto_email VARCHAR(160) DEFAULT NULL,
                    estatus VARCHAR(30) NOT NULL DEFAULT 'activo',
                    estatus_cumplimiento VARCHAR(40) NOT NULL DEFAULT 'autorizado',
                    motivo_bloqueo VARCHAR(255) DEFAULT NULL,
                    cumplimiento_actualizado_at DATETIME DEFAULT NULL,
                    cumplimiento_actualizado_por INT(11) DEFAULT NULL,
                    notas TEXT DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_proveedores_residencial (residencial_id),
                    KEY idx_proveedores_estatus (estatus),
                    KEY idx_proveedores_activo (activo),
                    KEY idx_proveedores_cumplimiento (estatus_cumplimiento),
                    KEY idx_proveedores_cumplimiento_por (cumplimiento_actualizado_por)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'proveedores', 'fk_proveedores_residencial')) {
            $pdo->exec("
                ALTER TABLE proveedores
                ADD CONSTRAINT fk_proveedores_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_column_exists($pdo, 'proveedores', 'estatus_cumplimiento')) {
            $pdo->exec("ALTER TABLE proveedores ADD COLUMN estatus_cumplimiento VARCHAR(40) NOT NULL DEFAULT 'autorizado' AFTER estatus");
        }
        if (!operational_column_exists($pdo, 'proveedores', 'motivo_bloqueo')) {
            $pdo->exec("ALTER TABLE proveedores ADD COLUMN motivo_bloqueo VARCHAR(255) DEFAULT NULL AFTER estatus_cumplimiento");
        }
        if (!operational_column_exists($pdo, 'proveedores', 'cumplimiento_actualizado_at')) {
            $pdo->exec("ALTER TABLE proveedores ADD COLUMN cumplimiento_actualizado_at DATETIME DEFAULT NULL AFTER motivo_bloqueo");
        }
        if (!operational_column_exists($pdo, 'proveedores', 'cumplimiento_actualizado_por')) {
            $pdo->exec("ALTER TABLE proveedores ADD COLUMN cumplimiento_actualizado_por INT(11) DEFAULT NULL AFTER cumplimiento_actualizado_at");
        }
        if (!operational_index_exists($pdo, 'proveedores', 'idx_proveedores_cumplimiento')) {
            $pdo->exec("ALTER TABLE proveedores ADD KEY idx_proveedores_cumplimiento (estatus_cumplimiento)");
        }
        if (!operational_index_exists($pdo, 'proveedores', 'idx_proveedores_cumplimiento_por')) {
            $pdo->exec("ALTER TABLE proveedores ADD KEY idx_proveedores_cumplimiento_por (cumplimiento_actualizado_por)");
        }
        if (!operational_foreign_key_exists($pdo, 'proveedores', 'fk_proveedores_cumplimiento_por')) {
            $pdo->exec("
                ALTER TABLE proveedores
                ADD CONSTRAINT fk_proveedores_cumplimiento_por
                FOREIGN KEY (cumplimiento_actualizado_por) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'proveedor_personas')) {
            $pdo->exec("
                CREATE TABLE proveedor_personas (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    proveedor_id INT(11) NOT NULL,
                    persona_recurrente_id INT(11) NOT NULL,
                    rol VARCHAR(120) DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_proveedor_personas_proveedor_persona (proveedor_id, persona_recurrente_id),
                    KEY idx_proveedor_personas_proveedor (proveedor_id),
                    KEY idx_proveedor_personas_persona (persona_recurrente_id),
                    KEY idx_proveedor_personas_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'proveedor_personas', 'fk_proveedor_personas_proveedor')) {
            $pdo->exec("
                ALTER TABLE proveedor_personas
                ADD CONSTRAINT fk_proveedor_personas_proveedor
                FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'proveedor_personas', 'fk_proveedor_personas_persona')) {
            $pdo->exec("
                ALTER TABLE proveedor_personas
                ADD CONSTRAINT fk_proveedor_personas_persona
                FOREIGN KEY (persona_recurrente_id) REFERENCES personas_recurrentes(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'proveedor_documentos')) {
            $pdo->exec("
                CREATE TABLE proveedor_documentos (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    proveedor_id INT(11) NOT NULL,
                    tipo_documento VARCHAR(120) NOT NULL,
                    archivo_url VARCHAR(255) DEFAULT NULL,
                    fecha_vencimiento DATE DEFAULT NULL,
                    estatus VARCHAR(30) NOT NULL DEFAULT 'pendiente',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_proveedor_documentos_proveedor (proveedor_id),
                    KEY idx_proveedor_documentos_estatus (estatus),
                    KEY idx_proveedor_documentos_vencimiento (fecha_vencimiento)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_column_exists($pdo, 'proveedor_documentos', 'fecha_vencimiento')) {
            $pdo->exec("ALTER TABLE proveedor_documentos ADD COLUMN fecha_vencimiento DATE DEFAULT NULL AFTER archivo_url");
        }
        if (!operational_column_exists($pdo, 'proveedor_documentos', 'estatus')) {
            $pdo->exec("ALTER TABLE proveedor_documentos ADD COLUMN estatus VARCHAR(40) NOT NULL DEFAULT 'pendiente' AFTER fecha_vencimiento");
        }
        if (!operational_index_exists($pdo, 'proveedor_documentos', 'idx_proveedor_documentos_estatus')) {
            $pdo->exec("ALTER TABLE proveedor_documentos ADD KEY idx_proveedor_documentos_estatus (estatus)");
        }
        if (!operational_index_exists($pdo, 'proveedor_documentos', 'idx_proveedor_documentos_vencimiento')) {
            $pdo->exec("ALTER TABLE proveedor_documentos ADD KEY idx_proveedor_documentos_vencimiento (fecha_vencimiento)");
        }
        if (!operational_foreign_key_exists($pdo, 'proveedor_documentos', 'fk_proveedor_documentos_proveedor')) {
            $pdo->exec("
                ALTER TABLE proveedor_documentos
                ADD CONSTRAINT fk_proveedor_documentos_proveedor
                FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'ordenes_servicio')) {
            $pdo->exec("
                CREATE TABLE ordenes_servicio (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    proveedor_id INT(11) DEFAULT NULL,
                    persona_recurrente_id INT(11) DEFAULT NULL,
                    area_id INT(11) DEFAULT NULL,
                    folio VARCHAR(50) NOT NULL,
                    tipo_servicio VARCHAR(120) DEFAULT NULL,
                    descripcion TEXT DEFAULT NULL,
                    fecha_programada DATE NOT NULL,
                    hora_inicio TIME DEFAULT NULL,
                    hora_fin TIME DEFAULT NULL,
                    qr_token VARCHAR(120) NOT NULL,
                    estatus VARCHAR(40) NOT NULL DEFAULT 'programada',
                    prioridad VARCHAR(40) NOT NULL DEFAULT 'media',
                    creado_por INT(11) DEFAULT NULL,
                    cerrado_por INT(11) DEFAULT NULL,
                    inicio_real_at DATETIME DEFAULT NULL,
                    cierre_at DATETIME DEFAULT NULL,
                    observaciones_cierre TEXT DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_ordenes_servicio_residencial_folio (residencial_id, folio),
                    UNIQUE KEY uq_ordenes_servicio_qr_token (qr_token),
                    KEY idx_ordenes_servicio_residencial (residencial_id),
                    KEY idx_ordenes_servicio_proveedor (proveedor_id),
                    KEY idx_ordenes_servicio_persona (persona_recurrente_id),
                    KEY idx_ordenes_servicio_area (area_id),
                    KEY idx_ordenes_servicio_estatus (estatus),
                    KEY idx_ordenes_servicio_fecha (fecha_programada),
                    KEY idx_ordenes_servicio_creado_por (creado_por),
                    KEY idx_ordenes_servicio_cerrado_por (cerrado_por)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio', 'fk_ordenes_servicio_residencial')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio
                ADD CONSTRAINT fk_ordenes_servicio_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio', 'fk_ordenes_servicio_proveedor')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio
                ADD CONSTRAINT fk_ordenes_servicio_proveedor
                FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio', 'fk_ordenes_servicio_persona')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio
                ADD CONSTRAINT fk_ordenes_servicio_persona
                FOREIGN KEY (persona_recurrente_id) REFERENCES personas_recurrentes(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio', 'fk_ordenes_servicio_area')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio
                ADD CONSTRAINT fk_ordenes_servicio_area
                FOREIGN KEY (area_id) REFERENCES areas_operativas(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio', 'fk_ordenes_servicio_creado_por')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio
                ADD CONSTRAINT fk_ordenes_servicio_creado_por
                FOREIGN KEY (creado_por) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio', 'fk_ordenes_servicio_cerrado_por')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio
                ADD CONSTRAINT fk_ordenes_servicio_cerrado_por
                FOREIGN KEY (cerrado_por) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        if (!operational_table_exists($pdo, 'ordenes_servicio_evidencias')) {
            $pdo->exec("
                CREATE TABLE ordenes_servicio_evidencias (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    orden_id INT(11) NOT NULL,
                    tipo VARCHAR(40) NOT NULL DEFAULT 'general',
                    archivo_url VARCHAR(255) DEFAULT NULL,
                    descripcion TEXT DEFAULT NULL,
                    created_by INT(11) DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_ordenes_servicio_evidencias_orden (orden_id),
                    KEY idx_ordenes_servicio_evidencias_tipo (tipo),
                    KEY idx_ordenes_servicio_evidencias_created_by (created_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio_evidencias', 'fk_ordenes_servicio_evidencias_orden')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio_evidencias
                ADD CONSTRAINT fk_ordenes_servicio_evidencias_orden
                FOREIGN KEY (orden_id) REFERENCES ordenes_servicio(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'ordenes_servicio_evidencias', 'fk_ordenes_servicio_evidencias_created_by')) {
            $pdo->exec("
                ALTER TABLE ordenes_servicio_evidencias
                ADD CONSTRAINT fk_ordenes_servicio_evidencias_created_by
                FOREIGN KEY (created_by) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        $done = true;
    }
}

if (!function_exists('operational_get_mode')) {
    function operational_get_mode(PDO $pdo, int $residencialId): string
    {
        operational_schema_ensure($pdo);
        $stmt = $pdo->prepare("SELECT modo_operacion FROM residenciales WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $residencialId]);
        return operational_normalize_mode((string)$stmt->fetchColumn());
    }
}

if (!function_exists('operational_get_context')) {
    function operational_get_context(PDO $pdo, int $residencialId): array
    {
        $mode = operational_get_mode($pdo, $residencialId);
        return [
            'residencial_id' => $residencialId,
            'modo_operacion' => $mode,
            'es_operativo' => operational_is_operational_mode($mode),
        ];
    }
}

if (!function_exists('operational_user_options')) {
    function operational_user_options(PDO $pdo, int $residencialId, bool $excludeGuardias = true): array
    {
        $sql = "
            SELECT u.id, u.name, u.email, t.nombre AS tipo_usuario
            FROM users u
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE ur.residencial_id = :rid
              AND u.is_active = 1
        ";
        if ($excludeGuardias) {
            $sql .= " AND t.nombre <> 'guardia'";
        }
        $sql .= " ORDER BY u.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['rid' => $residencialId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('operational_area_options')) {
    function operational_area_options(PDO $pdo, int $residencialId, bool $onlyActive = true): array
    {
        operational_schema_ensure($pdo);
        $sql = "
            SELECT id, nombre, codigo, tipo, descripcion, activo
            FROM areas_operativas
            WHERE residencial_id = :rid
        ";
        if ($onlyActive) {
            $sql .= " AND activo = 1";
        }
        $sql .= " ORDER BY nombre ASC, id ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['rid' => $residencialId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

if (!function_exists('operational_validate_area')) {
    function operational_validate_area(PDO $pdo, int $residencialId, ?int $areaId): bool
    {
        if (!$areaId) {
            return true;
        }
        operational_schema_ensure($pdo);
        $stmt = $pdo->prepare("
            SELECT 1
            FROM areas_operativas
            WHERE id = :id
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $areaId,
            'rid' => $residencialId,
        ]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('operational_validate_internal_user')) {
    function operational_validate_internal_user(PDO $pdo, int $residencialId, ?int $userId): bool
    {
        if (!$userId) {
            return false;
        }

        $stmt = $pdo->prepare("
            SELECT 1
            FROM users u
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE u.id = :uid
              AND ur.residencial_id = :rid
              AND u.is_active = 1
              AND t.nombre <> 'guardia'
            LIMIT 1
        ");
        $stmt->execute([
            'uid' => $userId,
            'rid' => $residencialId,
        ]);
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('operational_write_bitacora')) {
    function operational_write_bitacora(PDO $pdo, array $data): int
    {
        operational_schema_ensure($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO bitacora_operativa (
                residencial_id, guardia_id, tipo_origen, origen_id, tipo_evento, resultado,
                persona_recurrente_id, visitante_rapido_id, permiso_material_id, area_id,
                observaciones, metadata_json, fecha_hora
            ) VALUES (
                :residencial_id, :guardia_id, :tipo_origen, :origen_id, :tipo_evento, :resultado,
                :persona_recurrente_id, :visitante_rapido_id, :permiso_material_id, :area_id,
                :observaciones, :metadata_json, :fecha_hora
            )
        ");
        $stmt->execute([
            'residencial_id' => (int)($data['residencial_id'] ?? 0),
            'guardia_id' => ($data['guardia_id'] ?? null) !== null ? (int)$data['guardia_id'] : null,
            'tipo_origen' => clean_str((string)($data['tipo_origen'] ?? 'general')),
            'origen_id' => ($data['origen_id'] ?? null) !== null ? (int)$data['origen_id'] : null,
            'tipo_evento' => clean_str((string)($data['tipo_evento'] ?? 'evento')),
            'resultado' => clean_str((string)($data['resultado'] ?? 'permitido')),
            'persona_recurrente_id' => ($data['persona_recurrente_id'] ?? null) !== null ? (int)$data['persona_recurrente_id'] : null,
            'visitante_rapido_id' => ($data['visitante_rapido_id'] ?? null) !== null ? (int)$data['visitante_rapido_id'] : null,
            'permiso_material_id' => ($data['permiso_material_id'] ?? null) !== null ? (int)$data['permiso_material_id'] : null,
            'area_id' => ($data['area_id'] ?? null) !== null ? (int)$data['area_id'] : null,
            'observaciones' => ($data['observaciones'] ?? null) !== null ? trim((string)$data['observaciones']) : null,
            'metadata_json' => ($data['metadata_json'] ?? null) !== null ? json_encode($data['metadata_json'], JSON_UNESCAPED_UNICODE) : null,
            'fecha_hora' => ($data['fecha_hora'] ?? null) ?: date('Y-m-d H:i:s'),
        ]);
        return (int)$pdo->lastInsertId();
    }
}
