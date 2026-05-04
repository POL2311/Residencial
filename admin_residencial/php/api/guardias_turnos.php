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
require_once __DIR__ . '/../../../config/service_profile.php';
require_once __DIR__ . '/../../../config/guardia_schedule.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);
$serviceProfile = service_profile_api_require_module($pdo, $residencialId, 'admin_residencial', 'guardias', 'La gestión de guardias no está habilitada para este cliente.');
$canManageGuardias = (int)($serviceProfile['habilita_guardias_admin_actions'] ?? 1) === 1;
guardia_schedule_schema_ensure($pdo);

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

    return array_map(function ($r) use ($pdo) {
        $inicio = substr((string)$r['hora_inicio'], 0, 5);
        $fin = substr((string)$r['hora_fin'], 0, 5);
        $dias = (string)($r['dias_semana'] ?? '');
        $turnoId = (int)$r['id'];
        $exceptionToday = guardia_schedule_find_current_exception($pdo, (int)$r['residencial_id'], (int)$r['user_id'], null, $turnoId);

        return [
            'id' => $turnoId,
            'user_id' => (int)$r['user_id'],
            'residencial_id' => (int)$r['residencial_id'],
            'nombre_turno' => (string)$r['nombre_turno'],
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'dias_semana' => $dias,
            'activo' => (int)$r['activo'],
            'en_servicio_horario' => $exceptionToday ? 0 : (guardia_schedule_is_in_shift($inicio, $fin, $dias) ? 1 : 0),
            'exception_today' => $exceptionToday ? 1 : 0,
            'exception_today_reason' => $exceptionToday['motivo'] ?? '',
            'exception_today_id' => $exceptionToday['id'] ?? 0,
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

function list_exceptions(PDO $pdo, int $residencialId, int $guardiaId, array $filters = []): array {
    return guardia_schedule_list_exceptions($pdo, $residencialId, $guardiaId, $filters);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    if ($method === 'POST' && !$canManageGuardias) {
        json_out(false, ['error' => 'Superadmin deshabilitó las acciones de guardias para este cliente.'], 403);
    }

    if ($method === 'GET' && $action === 'list') {
        $guardiaId = (int)($_GET['guardia_id'] ?? 0);
        if ($guardiaId <= 0) {
            json_out(false, ['error' => 'guardia_id es obligatorio.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $turnos = list_turnos($pdo, $residencialId, $guardiaId);

        json_out(true, ['turnos' => $turnos]);
    }

    if ($method === 'GET' && $action === 'list_exceptions') {
        $guardiaId = (int)($_GET['guardia_id'] ?? 0);
        if ($guardiaId <= 0) {
            json_out(false, ['error' => 'guardia_id es obligatorio.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $exceptions = list_exceptions($pdo, $residencialId, $guardiaId, [
            'period' => (string)($_GET['period'] ?? ''),
            'only_active' => (string)($_GET['only_active'] ?? ''),
        ]);

        json_out(true, ['exceptions' => $exceptions]);
    }

    if ($method === 'POST' && $action === 'create') {
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);
        $nombreTurno = trim((string)($_POST['nombre_turno'] ?? ''));
        $horaInicio = trim((string)($_POST['hora_inicio'] ?? ''));
        $horaFin = trim((string)($_POST['hora_fin'] ?? ''));
        $diasSemana = guardia_schedule_normalize_days((string)($_POST['dias_semana'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($guardiaId <= 0) {
            json_out(false, ['error' => 'guardia_id inválido.']);
        }
        if ($nombreTurno === '') {
            json_out(false, ['error' => 'El nombre del turno es obligatorio.']);
        }
        if (!guardia_schedule_valid_time($horaInicio) || !guardia_schedule_valid_time($horaFin)) {
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
        $diasSemana = guardia_schedule_normalize_days((string)($_POST['dias_semana'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($turnoId <= 0 || $guardiaId <= 0) {
            json_out(false, ['error' => 'Datos de turno inválidos.']);
        }
        if ($nombreTurno === '') {
            json_out(false, ['error' => 'El nombre del turno es obligatorio.']);
        }
        if (!guardia_schedule_valid_time($horaInicio) || !guardia_schedule_valid_time($horaFin)) {
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

    if ($method === 'POST' && $action === 'create_exception') {
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        $notas = trim((string)($_POST['notas'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($guardiaId <= 0) {
            json_out(false, ['error' => 'guardia_id inválido.']);
        }
        if (!guardia_schedule_valid_date($fechaInicio) || !guardia_schedule_valid_date($fechaFin)) {
            json_out(false, ['error' => 'Debes indicar una fecha inicial y final válidas.']);
        }
        if ($fechaFin < $fechaInicio) {
            json_out(false, ['error' => 'La fecha final no puede ser menor a la fecha inicial.']);
        }
        if ($motivo === '') {
            json_out(false, ['error' => 'El motivo de la excepción es obligatorio.']);
        }
        if (mb_strlen($motivo) > 120) {
            json_out(false, ['error' => 'El motivo no puede exceder 120 caracteres.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        if ($turnoId > 0) {
            $stmtTurno = $pdo->prepare("
                SELECT id
                FROM guardias_turnos
                WHERE id = :id
                  AND user_id = :uid
                  AND residencial_id = :rid
                LIMIT 1
            ");
            $stmtTurno->execute([
                'id' => $turnoId,
                'uid' => $guardiaId,
                'rid' => $residencialId,
            ]);
            if (!$stmtTurno->fetchColumn()) {
                json_out(false, ['error' => 'No encontramos el turno asociado a esta excepción.']);
            }
        }

        if ($activo === 1) {
            $overlap = guardia_schedule_validate_exception_overlap($pdo, $residencialId, $guardiaId, $fechaInicio, $fechaFin);
            if ($overlap) {
                json_out(false, ['error' => 'Ya existe una excepción activa que se cruza con ese rango de fechas.']);
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO guardias_turnos_excepciones (
                user_id,
                residencial_id,
                turno_id,
                fecha_inicio,
                fecha_fin,
                motivo,
                notas,
                activo,
                created_at,
                updated_at
            ) VALUES (
                :uid,
                :rid,
                :tid,
                :ini,
                :fin,
                :motivo,
                :notas,
                :activo,
                NOW(),
                NOW()
            )
        ");
        $stmt->execute([
            'uid' => $guardiaId,
            'rid' => $residencialId,
            'tid' => $turnoId > 0 ? $turnoId : null,
            'ini' => $fechaInicio,
            'fin' => $fechaFin,
            'motivo' => $motivo,
            'notas' => $notas !== '' ? $notas : null,
            'activo' => $activo,
        ]);

        json_out(true, [
            'message' => 'Excepción guardada correctamente.',
            'exceptions' => list_exceptions($pdo, $residencialId, $guardiaId),
            'turnos' => list_turnos($pdo, $residencialId, $guardiaId),
        ]);
    }

    if ($method === 'POST' && $action === 'update_exception') {
        $exceptionId = (int)($_POST['exception_id'] ?? 0);
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);
        $turnoId = (int)($_POST['turno_id'] ?? 0);
        $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
        $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
        $motivo = trim((string)($_POST['motivo'] ?? ''));
        $notas = trim((string)($_POST['notas'] ?? ''));
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($exceptionId <= 0 || $guardiaId <= 0) {
            json_out(false, ['error' => 'Datos inválidos para actualizar la excepción.']);
        }
        if (!guardia_schedule_valid_date($fechaInicio) || !guardia_schedule_valid_date($fechaFin)) {
            json_out(false, ['error' => 'Debes indicar una fecha inicial y final válidas.']);
        }
        if ($fechaFin < $fechaInicio) {
            json_out(false, ['error' => 'La fecha final no puede ser menor a la fecha inicial.']);
        }
        if ($motivo === '') {
            json_out(false, ['error' => 'El motivo de la excepción es obligatorio.']);
        }
        if (mb_strlen($motivo) > 120) {
            json_out(false, ['error' => 'El motivo no puede exceder 120 caracteres.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $stmtCheck = $pdo->prepare("
            SELECT id
            FROM guardias_turnos_excepciones
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmtCheck->execute([
            'id' => $exceptionId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);
        if (!$stmtCheck->fetchColumn()) {
            json_out(false, ['error' => 'No encontramos la excepción a editar.']);
        }

        if ($turnoId > 0) {
            $stmtTurno = $pdo->prepare("
                SELECT id
                FROM guardias_turnos
                WHERE id = :id
                  AND user_id = :uid
                  AND residencial_id = :rid
                LIMIT 1
            ");
            $stmtTurno->execute([
                'id' => $turnoId,
                'uid' => $guardiaId,
                'rid' => $residencialId,
            ]);
            if (!$stmtTurno->fetchColumn()) {
                json_out(false, ['error' => 'No encontramos el turno asociado a esta excepción.']);
            }
        }

        if ($activo === 1) {
            $overlap = guardia_schedule_validate_exception_overlap($pdo, $residencialId, $guardiaId, $fechaInicio, $fechaFin, $exceptionId);
            if ($overlap) {
                json_out(false, ['error' => 'Ya existe otra excepción activa que se cruza con ese rango de fechas.']);
            }
        }

        $stmt = $pdo->prepare("
            UPDATE guardias_turnos_excepciones
            SET turno_id = :tid,
                fecha_inicio = :ini,
                fecha_fin = :fin,
                motivo = :motivo,
                notas = :notas,
                activo = :activo,
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
        ");
        $stmt->execute([
            'tid' => $turnoId > 0 ? $turnoId : null,
            'ini' => $fechaInicio,
            'fin' => $fechaFin,
            'motivo' => $motivo,
            'notas' => $notas !== '' ? $notas : null,
            'activo' => $activo,
            'id' => $exceptionId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);

        json_out(true, [
            'message' => 'Excepción actualizada correctamente.',
            'exceptions' => list_exceptions($pdo, $residencialId, $guardiaId),
            'turnos' => list_turnos($pdo, $residencialId, $guardiaId),
        ]);
    }

    if ($method === 'POST' && $action === 'toggle_exception') {
        $exceptionId = (int)($_POST['exception_id'] ?? 0);
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);

        if ($exceptionId <= 0 || $guardiaId <= 0) {
            json_out(false, ['error' => 'Datos inválidos para cambiar la excepción.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $stmt = $pdo->prepare("
            SELECT *
            FROM guardias_turnos_excepciones
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $exceptionId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);
        $exception = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exception) {
            json_out(false, ['error' => 'No encontramos la excepción indicada.']);
        }

        $newActive = (int)($exception['activo'] ?? 0) === 1 ? 0 : 1;
        if ($newActive === 1) {
            $overlap = guardia_schedule_validate_exception_overlap(
                $pdo,
                $residencialId,
                $guardiaId,
                (string)$exception['fecha_inicio'],
                (string)$exception['fecha_fin'],
                $exceptionId
            );
            if ($overlap) {
                json_out(false, ['error' => 'No pudimos activarla porque se cruza con otra excepción activa del mismo guardia.']);
            }
        }

        $pdo->prepare("
            UPDATE guardias_turnos_excepciones
            SET activo = :activo,
                updated_at = NOW()
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
        ")->execute([
            'activo' => $newActive,
            'id' => $exceptionId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);

        json_out(true, [
            'message' => $newActive === 1 ? 'Excepción activada correctamente.' : 'Excepción desactivada correctamente.',
            'exceptions' => list_exceptions($pdo, $residencialId, $guardiaId),
            'turnos' => list_turnos($pdo, $residencialId, $guardiaId),
        ]);
    }

    if ($method === 'POST' && $action === 'delete_exception') {
        $exceptionId = (int)($_POST['exception_id'] ?? 0);
        $guardiaId = (int)($_POST['guardia_id'] ?? 0);

        if ($exceptionId <= 0 || $guardiaId <= 0) {
            json_out(false, ['error' => 'Datos inválidos para eliminar la excepción.']);
        }

        validate_guardia_belongs($pdo, $guardiaId, $residencialId);

        $stmt = $pdo->prepare("
            DELETE FROM guardias_turnos_excepciones
            WHERE id = :id
              AND user_id = :uid
              AND residencial_id = :rid
        ");
        $stmt->execute([
            'id' => $exceptionId,
            'uid' => $guardiaId,
            'rid' => $residencialId,
        ]);

        if ($stmt->rowCount() <= 0) {
            json_out(false, ['error' => 'No encontramos la excepción a eliminar.']);
        }

        json_out(true, [
            'message' => 'Excepción eliminada correctamente.',
            'exceptions' => list_exceptions($pdo, $residencialId, $guardiaId),
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
