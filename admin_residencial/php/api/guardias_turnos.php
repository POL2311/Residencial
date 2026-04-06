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

require_login();
require_role(['admin_residencial']);

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);

function normalize_dias_semana(string $dias): string {
    $dias = strtoupper(trim($dias));
    $dias = preg_replace('/\s+/', '', $dias);
    return $dias;
}

function valid_time_hhmm(string $value): bool {
    return (bool)preg_match('/^\d{2}:\d{2}$/', $value);
}

function current_day_code(): string {
    $map = [
        1 => 'LUN',
        2 => 'MAR',
        3 => 'MIE',
        4 => 'JUE',
        5 => 'VIE',
        6 => 'SAB',
        7 => 'DOM',
    ];
    $n = (int)date('N');
    return $map[$n] ?? 'LUN';
}

function time_to_minutes(string $hhmm): int {
    [$h, $m] = array_map('intval', explode(':', $hhmm));
    return ($h * 60) + $m;
}

function is_guardia_in_shift(string $horaInicio, string $horaFin, string $diasSemana): bool {
    $diaActual = current_day_code();
    $dias = array_filter(explode(',', normalize_dias_semana($diasSemana)));

    if (!in_array($diaActual, $dias, true)) {
        return false;
    }

    $now = date('H:i');
    $nowMin = time_to_minutes($now);
    $iniMin = time_to_minutes(substr($horaInicio, 0, 5));
    $finMin = time_to_minutes(substr($horaFin, 0, 5));

    // turno normal: 08:00 -> 16:00
    if ($iniMin < $finMin) {
        return $nowMin >= $iniMin && $nowMin < $finMin;
    }

    // turno nocturno: 22:00 -> 06:00
    return $nowMin >= $iniMin || $nowMin < $finMin;
}

function list_turnos(PDO $pdo, int $residencialId, int $guardiaId): array {
    $stmt = $pdo->prepare("
        SELECT
            id,
            user_id,
            residencial_id,
            nombre_turno,
            hora_inicio,
            hora_fin,
            dias_semana,
            activo,
            created_at,
            updated_at
        FROM guardias_turnos
        WHERE residencial_id = :rid
          AND user_id = :uid
        ORDER BY activo DESC, hora_inicio ASC, id DESC
    ");
    $stmt->execute([
        'rid' => $residencialId,
        'uid' => $guardiaId,
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(function ($r) {
        $inicio = substr((string)$r['hora_inicio'], 0, 5);
        $fin = substr((string)$r['hora_fin'], 0, 5);
        $dias = (string)($r['dias_semana'] ?? '');

        return [
            'id' => (int)$r['id'],
            'user_id' => (int)$r['user_id'],
            'residencial_id' => (int)$r['residencial_id'],
            'nombre_turno' => (string)$r['nombre_turno'],
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'dias_semana' => $dias,
            'activo' => (int)$r['activo'],
            'en_servicio_horario' => is_guardia_in_shift($inicio, $fin, $dias) ? 1 : 0,
        ];
    }, $rows);
}

function validate_guardia_belongs(PDO $pdo, int $guardiaId, int $residencialId): void {
    $stmt = $pdo->prepare("
        SELECT u.id
        FROM users u
        JOIN usuarios_residenciales ur ON ur.user_id = u.id
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        WHERE u.id = :id
          AND ur.residencial_id = :rid
          AND t.nombre = 'guardia'
        LIMIT 1
    ");
    $stmt->execute([
        'id' => $guardiaId,
        'rid' => $residencialId,
    ]);

    if (!$stmt->fetchColumn()) {
        json_out(false, ['error' => 'El guardia no pertenece a tu residencial.']);
    }
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($method === 'GET' && $action === 'list') {
        $guardiaId = (int)($_GET['guardia_id'] ?? 0);
        if ($guardiaId <= 0) {
            json_out(false, ['error' => 'guardia_id es obligatorio.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $turnos = list_turnos($pdo, $residencialId, $guardiaId);

        json_out(true, ['turnos' => $turnos]);
    }

    if ($method === 'POST' && $action === 'create') {
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);
        $nombreTurno = trim((string)($_POST['nombre_turno'] ?? ''));
        $horaInicio = trim((string)($_POST['hora_inicio'] ?? ''));
        $horaFin = trim((string)($_POST['hora_fin'] ?? ''));
        $diasSemana = normalize_dias_semana((string)($_POST['dias_semana'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($guardiaId <= 0) {
            json_out(false, ['error' => 'guardia_id inválido.']);
        }
        if ($nombreTurno === '') {
            json_out(false, ['error' => 'El nombre del turno es obligatorio.']);
        }
        if (!valid_time_hhmm($horaInicio) || !valid_time_hhmm($horaFin)) {
            json_out(false, ['error' => 'La hora de inicio y fin deben tener formato HH:MM.']);
        }
        if ($diasSemana === '') {
            json_out(false, ['error' => 'Debes indicar los días de semana.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $pdo->beginTransaction();

        if ($activo === 1) {
            $pdo->prepare("
                UPDATE guardias_turnos
                SET activo = 0
                WHERE residencial_id = :rid
                  AND user_id = :uid
            ")->execute([
                'rid' => $residencialId,
                'uid' => $guardiaId,
            ]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO guardias_turnos (
                user_id,
                residencial_id,
                nombre_turno,
                hora_inicio,
                hora_fin,
                dias_semana,
                activo,
                created_at,
                updated_at
            ) VALUES (
                :uid,
                :rid,
                :nombre,
                :hini,
                :hfin,
                :dias,
                :activo,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'uid' => $guardiaId,
            'rid' => $residencialId,
            'nombre' => $nombreTurno,
            'hini' => $horaInicio,
            'hfin' => $horaFin,
            'dias' => $diasSemana,
            'activo' => $activo,
        ]);

        $pdo->commit();

        json_out(true, [
            'message' => 'Turno guardado correctamente.',
            'turnos' => list_turnos($pdo, $residencialId, $guardiaId),
        ]);
    }

    if ($method === 'POST' && $action === 'update') {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);
        $nombreTurno = trim((string)($_POST['nombre_turno'] ?? ''));
        $horaInicio = trim((string)($_POST['hora_inicio'] ?? ''));
        $horaFin = trim((string)($_POST['hora_fin'] ?? ''));
        $diasSemana = normalize_dias_semana((string)($_POST['dias_semana'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($turnoId <= 0 || $guardiaId <= 0) {
            json_out(false, ['error' => 'Datos de turno inválidos.']);
        }
        if ($nombreTurno === '') {
            json_out(false, ['error' => 'El nombre del turno es obligatorio.']);
        }
        if (!valid_time_hhmm($horaInicio) || !valid_time_hhmm($horaFin)) {
            json_out(false, ['error' => 'La hora de inicio y fin deben tener formato HH:MM.']);
        }
        if ($diasSemana === '') {
            json_out(false, ['error' => 'Debes indicar los días de semana.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $pdo->beginTransaction();

        $stmtCheck = $pdo->prepare("
            SELECT id
            FROM guardias_turnos
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmtCheck->execute([
            'id' => $turnoId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);

        if (!$stmtCheck->fetchColumn()) {
            $pdo->rollBack();
            json_out(false, ['error' => 'Turno no encontrado.']);
        }

        if ($activo === 1) {
            $pdo->prepare("
                UPDATE guardias_turnos
                SET activo = 0
                WHERE residencial_id = :rid
                  AND user_id = :uid
            ")->execute([
                'rid' => $residencialId,
                'uid' => $guardiaId,
            ]);
        }

        $stmt = $pdo->prepare("
            UPDATE guardias_turnos
            SET nombre_turno = :nombre,
                hora_inicio = :hini,
                hora_fin = :hfin,
                dias_semana = :dias,
                activo = :activo,
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
        ");
        $stmt->execute([
            'nombre' => $nombreTurno,
            'hini' => $horaInicio,
            'hfin' => $horaFin,
            'dias' => $diasSemana,
            'activo' => $activo,
            'id' => $turnoId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);

        $pdo->commit();

        json_out(true, [
            'message' => 'Turno actualizado correctamente.',
            'turnos' => list_turnos($pdo, $residencialId, $guardiaId),
        ]);
    }

    if ($method === 'POST' && $action === 'delete') {
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);

        if ($turnoId <= 0 || $guardiaId <= 0) {
            json_out(false, ['error' => 'Datos inválidos para eliminar turno.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $stmt = $pdo->prepare("
            DELETE FROM guardias_turnos
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
        ");
        $stmt->execute([
            'id' => $turnoId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);

        json_out(true, [
            'message' => 'Turno eliminado correctamente.',
            'turnos' => list_turnos($pdo, $residencialId, $guardiaId),
        ]);
    }

    json_out(false, ['error' => 'Acción no soportada.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_out(false, ['error' => 'Error interno: ' . $e->getMessage()]);
}