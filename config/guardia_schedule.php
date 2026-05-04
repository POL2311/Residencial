<?php
declare(strict_types=1);

require_once __DIR__ . '/operational_mode.php';

if (!function_exists('guardia_schedule_schema_ensure')) {
    function guardia_schedule_schema_ensure(PDO $pdo): void
    {
        static $done = false;
        if ($done) {
            return;
        }

        if (!operational_table_exists($pdo, 'guardias_turnos_excepciones')) {
            $pdo->exec("
                CREATE TABLE guardias_turnos_excepciones (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    user_id INT(11) NOT NULL,
                    residencial_id INT(11) NOT NULL,
                    turno_id INT(11) NULL DEFAULT NULL,
                    fecha_inicio DATE NOT NULL,
                    fecha_fin DATE NOT NULL,
                    motivo VARCHAR(120) NOT NULL,
                    notas TEXT NULL,
                    activo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");
        }

        if (!operational_index_exists($pdo, 'guardias_turnos_excepciones', 'idx_guardias_turnos_ex_user_service_dates')) {
            $pdo->exec("
                ALTER TABLE guardias_turnos_excepciones
                ADD KEY idx_guardias_turnos_ex_user_service_dates (user_id, residencial_id, fecha_inicio, fecha_fin)
            ");
        }

        if (!operational_index_exists($pdo, 'guardias_turnos_excepciones', 'idx_guardias_turnos_ex_turno')) {
            $pdo->exec("
                ALTER TABLE guardias_turnos_excepciones
                ADD KEY idx_guardias_turnos_ex_turno (turno_id)
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'guardias_turnos_excepciones', 'fk_guardias_turnos_ex_user')) {
            $pdo->exec("
                ALTER TABLE guardias_turnos_excepciones
                ADD CONSTRAINT fk_guardias_turnos_ex_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'guardias_turnos_excepciones', 'fk_guardias_turnos_ex_residencial')) {
            $pdo->exec("
                ALTER TABLE guardias_turnos_excepciones
                ADD CONSTRAINT fk_guardias_turnos_ex_residencial
                FOREIGN KEY (residencial_id) REFERENCES residenciales(id)
                ON DELETE CASCADE
            ");
        }

        if (!operational_foreign_key_exists($pdo, 'guardias_turnos_excepciones', 'fk_guardias_turnos_ex_turno')) {
            $pdo->exec("
                ALTER TABLE guardias_turnos_excepciones
                ADD CONSTRAINT fk_guardias_turnos_ex_turno
                FOREIGN KEY (turno_id) REFERENCES guardias_turnos(id)
                ON DELETE SET NULL
            ");
        }

        $done = true;
    }
}

if (!function_exists('guardia_schedule_normalize_days')) {
    function guardia_schedule_normalize_days(string $days): string
    {
        $days = strtoupper(trim($days));
        return (string)preg_replace('/\s+/', '', $days);
    }
}

if (!function_exists('guardia_schedule_valid_time')) {
    function guardia_schedule_valid_time(string $value): bool
    {
        return (bool)preg_match('/^\d{2}:\d{2}$/', $value);
    }
}

if (!function_exists('guardia_schedule_valid_date')) {
    function guardia_schedule_valid_date(string $value): bool
    {
        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}

if (!function_exists('guardia_schedule_current_day_code')) {
    function guardia_schedule_current_day_code(?string $date = null): string
    {
        $map = [
            1 => 'LUN',
            2 => 'MAR',
            3 => 'MIE',
            4 => 'JUE',
            5 => 'VIE',
            6 => 'SAB',
            7 => 'DOM',
        ];

        $timestamp = $date ? strtotime($date . ' 12:00:00') : time();
        $n = (int)date('N', $timestamp ?: time());
        return $map[$n] ?? 'LUN';
    }
}

if (!function_exists('guardia_schedule_time_to_minutes')) {
    function guardia_schedule_time_to_minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', substr($hhmm, 0, 5)));
        return ($h * 60) + $m;
    }
}

if (!function_exists('guardia_schedule_is_in_shift')) {
    function guardia_schedule_is_in_shift(string $horaInicio, string $horaFin, string $diasSemana, ?string $date = null, ?string $time = null): bool
    {
        $diaActual = guardia_schedule_current_day_code($date);
        $dias = array_filter(explode(',', guardia_schedule_normalize_days($diasSemana)));

        if (!in_array($diaActual, $dias, true)) {
            return false;
        }

        $now = $time ? substr($time, 0, 5) : date('H:i');
        $nowMin = guardia_schedule_time_to_minutes($now);
        $iniMin = guardia_schedule_time_to_minutes($horaInicio);
        $finMin = guardia_schedule_time_to_minutes($horaFin);

        if ($iniMin < $finMin) {
            return $nowMin >= $iniMin && $nowMin < $finMin;
        }

        return $nowMin >= $iniMin || $nowMin < $finMin;
    }
}

if (!function_exists('guardia_schedule_exception_period_status')) {
    function guardia_schedule_exception_period_status(string $fechaInicio, string $fechaFin, ?string $today = null): string
    {
        $today = $today ?: date('Y-m-d');
        if ($fechaInicio <= $today && $fechaFin >= $today) {
            return 'today';
        }
        if ($fechaInicio > $today) {
            return 'upcoming';
        }
        return 'past';
    }
}

if (!function_exists('guardia_schedule_normalize_exception_row')) {
    function guardia_schedule_normalize_exception_row(array $row, ?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        $fechaInicio = (string)($row['fecha_inicio'] ?? '');
        $fechaFin = (string)($row['fecha_fin'] ?? '');
        $periodStatus = guardia_schedule_exception_period_status($fechaInicio, $fechaFin, $today);

        return [
            'id' => (int)($row['id'] ?? 0),
            'user_id' => (int)($row['user_id'] ?? 0),
            'residencial_id' => (int)($row['residencial_id'] ?? 0),
            'turno_id' => isset($row['turno_id']) ? (int)$row['turno_id'] : null,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'motivo' => (string)($row['motivo'] ?? ''),
            'notas' => (string)($row['notas'] ?? ''),
            'activo' => (int)($row['activo'] ?? 0),
            'created_at' => (string)($row['created_at'] ?? ''),
            'updated_at' => (string)($row['updated_at'] ?? ''),
            'period_status' => $periodStatus,
            'is_today' => $periodStatus === 'today' && (int)($row['activo'] ?? 0) === 1 ? 1 : 0,
        ];
    }
}

if (!function_exists('guardia_schedule_list_exceptions')) {
    function guardia_schedule_list_exceptions(PDO $pdo, int $residencialId, int $guardiaId, array $filters = []): array
    {
        guardia_schedule_schema_ensure($pdo);

        $period = trim((string)($filters['period'] ?? ''));
        $onlyActive = trim((string)($filters['only_active'] ?? ''));
        $today = trim((string)($filters['today'] ?? date('Y-m-d')));

        $sql = "
            SELECT *
            FROM guardias_turnos_excepciones
            WHERE residencial_id = :rid
              AND user_id = :uid
        ";
        $params = [
            'rid' => $residencialId,
            'uid' => $guardiaId,
        ];

        if ($onlyActive === '1') {
            $sql .= " AND activo = 1";
        } elseif ($onlyActive === '0') {
            $sql .= " AND activo = 0";
        }

        if ($period === 'today') {
            $sql .= " AND fecha_inicio <= :today AND fecha_fin >= :today";
            $params['today'] = $today;
        } elseif ($period === 'upcoming') {
            $sql .= " AND fecha_inicio > :today";
            $params['today'] = $today;
        } elseif ($period === 'past') {
            $sql .= " AND fecha_fin < :today";
            $params['today'] = $today;
        }

        $sql .= " ORDER BY fecha_inicio DESC, id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn(array $row): array => guardia_schedule_normalize_exception_row($row, $today), $rows);
    }
}

if (!function_exists('guardia_schedule_find_current_exception')) {
    function guardia_schedule_find_current_exception(PDO $pdo, int $residencialId, int $guardiaId, ?string $today = null, ?int $turnoId = null): ?array
    {
        guardia_schedule_schema_ensure($pdo);
        $today = $today ?: date('Y-m-d');

        $sql = "
            SELECT *
            FROM guardias_turnos_excepciones
            WHERE residencial_id = :rid
              AND user_id = :uid
              AND activo = 1
              AND fecha_inicio <= :today
              AND fecha_fin >= :today
        ";
        $params = [
            'rid' => $residencialId,
            'uid' => $guardiaId,
            'today' => $today,
        ];

        if ($turnoId !== null && $turnoId > 0) {
            $sql .= " AND (turno_id IS NULL OR turno_id = :tid)";
            $params['tid'] = $turnoId;
        }

        $sql .= " ORDER BY CASE WHEN turno_id IS NULL THEN 0 ELSE 1 END DESC, fecha_inicio ASC, id ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? guardia_schedule_normalize_exception_row($row, $today) : null;
    }
}

if (!function_exists('guardia_schedule_find_active_turn')) {
    function guardia_schedule_find_active_turn(PDO $pdo, int $residencialId, int $guardiaId, ?string $date = null, ?string $time = null): ?array
    {
        $stmt = $pdo->prepare("
            SELECT id, nombre_turno, hora_inicio, hora_fin, dias_semana, activo
            FROM guardias_turnos
            WHERE residencial_id = :rid
              AND user_id = :uid
              AND activo = 1
            ORDER BY hora_inicio ASC, id ASC
        ");
        $stmt->execute([
            'rid' => $residencialId,
            'uid' => $guardiaId,
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as $row) {
            $inicio = substr((string)($row['hora_inicio'] ?? ''), 0, 5);
            $fin = substr((string)($row['hora_fin'] ?? ''), 0, 5);
            $dias = (string)($row['dias_semana'] ?? '');
            if (guardia_schedule_is_in_shift($inicio, $fin, $dias, $date, $time)) {
                return [
                    'id' => (int)($row['id'] ?? 0),
                    'nombre_turno' => (string)($row['nombre_turno'] ?? ''),
                    'hora_inicio' => $inicio,
                    'hora_fin' => $fin,
                    'dias_semana' => $dias,
                    'activo' => (int)($row['activo'] ?? 0),
                ];
            }
        }

        return null;
    }
}

if (!function_exists('guardia_schedule_validate_exception_overlap')) {
    function guardia_schedule_validate_exception_overlap(PDO $pdo, int $residencialId, int $guardiaId, string $fechaInicio, string $fechaFin, int $excludeId = 0): ?array
    {
        guardia_schedule_schema_ensure($pdo);

        $sql = "
            SELECT *
            FROM guardias_turnos_excepciones
            WHERE residencial_id = :rid
              AND user_id = :uid
              AND activo = 1
              AND fecha_inicio <= :fin
              AND fecha_fin >= :ini
        ";
        $params = [
            'rid' => $residencialId,
            'uid' => $guardiaId,
            'ini' => $fechaInicio,
            'fin' => $fechaFin,
        ];

        if ($excludeId > 0) {
            $sql .= " AND id <> :id";
            $params['id'] = $excludeId;
        }

        $sql .= " ORDER BY fecha_inicio ASC, id ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? guardia_schedule_normalize_exception_row($row) : null;
    }
}
