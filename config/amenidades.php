<?php
declare(strict_types=1);

require_once __DIR__ . '/operational_mode.php';

if (!function_exists('amenidades_schema_ensure')) {
    function amenidades_schema_ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        operational_schema_ensure($pdo);

        if (!operational_table_exists($pdo, 'amenidades')) {
            $pdo->exec("
                CREATE TABLE amenidades (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    nombre VARCHAR(150) NOT NULL,
                    descripcion TEXT DEFAULT NULL,
                    ubicacion VARCHAR(150) DEFAULT NULL,
                    capacidad INT(10) UNSIGNED DEFAULT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_amenidades_residencial (residencial_id),
                    KEY idx_amenidades_activo (activo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'amenidades', 'fk_amenidades_residencial')) {
            $pdo->exec("
                ALTER TABLE amenidades
                ADD CONSTRAINT fk_amenidades_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_table_exists($pdo, 'amenidad_reservas')) {
            $pdo->exec("
                CREATE TABLE amenidad_reservas (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    residencial_id INT(11) NOT NULL,
                    amenidad_id INT(11) NOT NULL,
                    residente_id INT(11) NOT NULL,
                    unidad_id INT(11) NOT NULL,
                    fecha DATE NOT NULL,
                    hora_inicio TIME NOT NULL,
                    hora_fin TIME NOT NULL,
                    estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
                    notas_residente TEXT DEFAULT NULL,
                    notas_admin TEXT DEFAULT NULL,
                    revisado_por_user_id INT(11) DEFAULT NULL,
                    revisado_at DATETIME DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY idx_amenidad_reservas_residencial (residencial_id),
                    KEY idx_amenidad_reservas_amenidad_fecha (amenidad_id, fecha),
                    KEY idx_amenidad_reservas_residente (residente_id),
                    KEY idx_amenidad_reservas_unidad (unidad_id),
                    KEY idx_amenidad_reservas_estado (estado),
                    KEY idx_amenidad_reservas_daily_active (residencial_id, amenidad_id, residente_id, fecha, estado)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        if (!operational_index_exists($pdo, 'amenidad_reservas', 'idx_amenidad_reservas_daily_active')) {
            $pdo->exec("
                ALTER TABLE amenidad_reservas
                ADD KEY idx_amenidad_reservas_daily_active (residencial_id, amenidad_id, residente_id, fecha, estado)
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'amenidad_reservas', 'fk_amenidad_reservas_residencial')) {
            $pdo->exec("
                ALTER TABLE amenidad_reservas
                ADD CONSTRAINT fk_amenidad_reservas_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'amenidad_reservas', 'fk_amenidad_reservas_amenidad')) {
            $pdo->exec("
                ALTER TABLE amenidad_reservas
                ADD CONSTRAINT fk_amenidad_reservas_amenidad
                FOREIGN KEY (amenidad_id) REFERENCES amenidades(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'amenidad_reservas', 'fk_amenidad_reservas_residente')) {
            $pdo->exec("
                ALTER TABLE amenidad_reservas
                ADD CONSTRAINT fk_amenidad_reservas_residente
                FOREIGN KEY (residente_id) REFERENCES users(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'amenidad_reservas', 'fk_amenidad_reservas_unidad')) {
            $pdo->exec("
                ALTER TABLE amenidad_reservas
                ADD CONSTRAINT fk_amenidad_reservas_unidad
                FOREIGN KEY (unidad_id) REFERENCES unidades(id)
                ON DELETE CASCADE
            ");
        }
        if (!operational_foreign_key_exists($pdo, 'amenidad_reservas', 'fk_amenidad_reservas_revisor')) {
            $pdo->exec("
                ALTER TABLE amenidad_reservas
                ADD CONSTRAINT fk_amenidad_reservas_revisor
                FOREIGN KEY (revisado_por_user_id) REFERENCES users(id)
                ON DELETE SET NULL
            ");
        }

        $done = true;
    }
}

if (!function_exists('amenidades_clean_str')) {
    function amenidades_clean_str($value, int $max = 255): string
    {
        $value = trim((string)($value ?? ''));
        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        if (mb_strlen($value) > $max) {
            $value = mb_substr($value, 0, $max);
        }
        return $value;
    }
}

if (!function_exists('amenidades_allowed_estados')) {
    function amenidades_allowed_estados(): array
    {
        return ['pendiente', 'aprobada', 'rechazada', 'cancelada', 'finalizada'];
    }
}

if (!function_exists('amenidades_validate_time_range')) {
    function amenidades_validate_time_range(string $fecha, string $horaInicio, string $horaFin): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return 'La fecha no es válida.';
        }
        if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}$/', $horaFin)) {
            return 'El horario no es válido.';
        }
        if ($horaInicio >= $horaFin) {
            return 'La hora final debe ser posterior a la hora inicial.';
        }
        return null;
    }
}

if (!function_exists('amenidades_has_daily_active_request')) {
    function amenidades_has_daily_active_request(PDO $pdo, int $residencialId, int $amenidadId, int $residenteId, string $fecha): bool
    {
        amenidades_schema_ensure($pdo);
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM amenidad_reservas
            WHERE residencial_id = :rid
              AND amenidad_id = :amenidad_id
              AND residente_id = :residente_id
              AND fecha = :fecha
              AND estado IN ('pendiente', 'aprobada')
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'amenidad_id' => $amenidadId,
            'residente_id' => $residenteId,
            'fecha' => $fecha,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('amenidades_has_overlap')) {
    function amenidades_has_overlap(PDO $pdo, int $amenidadId, string $fecha, string $horaInicio, string $horaFin, int $excludeReservaId = 0): bool
    {
        amenidades_schema_ensure($pdo);
        $sql = "
            SELECT COUNT(*)
            FROM amenidad_reservas
            WHERE amenidad_id = :amenidad_id
              AND fecha = :fecha
              AND estado = 'aprobada'
              AND :hora_inicio < hora_fin
              AND :hora_fin > hora_inicio
        ";
        $params = [
            'amenidad_id' => $amenidadId,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ];
        if ($excludeReservaId > 0) {
            $sql .= " AND id <> :exclude_id";
            $params['exclude_id'] = $excludeReservaId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}

if (!function_exists('amenidades_fetch_reserva')) {
    function amenidades_fetch_reserva(PDO $pdo, int $reservaId, int $residencialId): ?array
    {
        amenidades_schema_ensure($pdo);
        $stmt = $pdo->prepare("
            SELECT r.*, a.nombre AS amenidad_nombre, a.ubicacion AS amenidad_ubicacion, u.clave AS unidad_clave, usr.name AS residente_nombre
            FROM amenidad_reservas r
            JOIN amenidades a ON a.id = r.amenidad_id
            JOIN unidades u ON u.id = r.unidad_id
            JOIN users usr ON usr.id = r.residente_id
            WHERE r.id = :id
              AND r.residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute(['id' => $reservaId, 'rid' => $residencialId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('amenidades_normalize_reserva')) {
    function amenidades_normalize_reserva(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'residencial_id' => (int)$row['residencial_id'],
            'amenidad_id' => (int)$row['amenidad_id'],
            'amenidad_nombre' => (string)($row['amenidad_nombre'] ?? ''),
            'amenidad_ubicacion' => (string)($row['amenidad_ubicacion'] ?? ''),
            'residente_id' => (int)$row['residente_id'],
            'residente_nombre' => (string)($row['residente_nombre'] ?? ''),
            'unidad_id' => (int)$row['unidad_id'],
            'unidad_clave' => (string)($row['unidad_clave'] ?? ''),
            'fecha' => (string)$row['fecha'],
            'hora_inicio' => substr((string)$row['hora_inicio'], 0, 5),
            'hora_fin' => substr((string)$row['hora_fin'], 0, 5),
            'estado' => (string)$row['estado'],
            'notas_residente' => (string)($row['notas_residente'] ?? ''),
            'notas_admin' => (string)($row['notas_admin'] ?? ''),
            'revisado_por_user_id' => $row['revisado_por_user_id'] !== null ? (int)$row['revisado_por_user_id'] : null,
            'revisado_por_nombre' => (string)($row['revisado_por_nombre'] ?? ''),
            'revisado_at' => (string)($row['revisado_at'] ?? ''),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ];
    }
}
