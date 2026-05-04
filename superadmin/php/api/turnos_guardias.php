<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../config/guardia_schedule.php';

$action = sa_post_action('list');
guardia_schedule_schema_ensure($pdo);

function sa_turnos_guardia_belongs(PDO $pdo, int $guardiaId, int $residencialId): array
{
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.name,
            u.email,
            u.is_active,
            r.id AS residencial_id,
            r.nombre AS residencial_nombre,
            r.codigo AS residencial_codigo
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        JOIN usuarios_residenciales ur ON ur.user_id = u.id
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE u.id = :uid
          AND ur.residencial_id = :rid
          AND t.nombre = 'guardia'
        LIMIT 1
    ");
    $stmt->execute([
        'uid' => $guardiaId,
        'rid' => $residencialId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        sa_json_out(false, ['error' => 'El guardia seleccionado no pertenece a este servicio.'], 422);
    }

    return $row;
}

function sa_turnos_fetch_guardias(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            u.id,
            u.name,
            u.email,
            u.is_active,
            r.id AS residencial_id,
            r.nombre AS residencial_nombre,
            r.codigo AS residencial_codigo
        FROM users u
        JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
        JOIN usuarios_residenciales ur ON ur.user_id = u.id
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE t.nombre = 'guardia'
        ORDER BY r.nombre ASC, u.name ASC
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static function (array $row): array {
        return [
            'id' => (int)($row['id'] ?? 0),
            'name' => (string)($row['name'] ?? ''),
            'email' => (string)($row['email'] ?? ''),
            'is_active' => (int)($row['is_active'] ?? 0),
            'residencial_id' => (int)($row['residencial_id'] ?? 0),
            'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
            'residencial_codigo' => (string)($row['residencial_codigo'] ?? ''),
            'label' => trim(sprintf(
                '%s (%s) · %s',
                (string)($row['name'] ?? ''),
                (string)($row['email'] ?? ''),
                (string)($row['residencial_nombre'] ?? '')
            )),
        ];
    }, $rows);
}

function sa_turnos_normalize_row(array $row): array
{
    global $pdo;
    $exceptionToday = guardia_schedule_find_current_exception($pdo, (int)($row['residencial_id'] ?? 0), (int)($row['user_id'] ?? 0), null, (int)($row['id'] ?? 0));
    return [
        'id' => (int)($row['id'] ?? 0),
        'guardia_id' => (int)($row['user_id'] ?? 0),
        'guardia_nombre' => (string)($row['guardia_nombre'] ?? ''),
        'guardia_email' => (string)($row['guardia_email'] ?? ''),
        'guardia_activo' => (int)($row['guardia_activo'] ?? 0),
        'residencial_id' => (int)($row['residencial_id'] ?? 0),
        'residencial_nombre' => (string)($row['residencial_nombre'] ?? ''),
        'residencial_codigo' => (string)($row['residencial_codigo'] ?? ''),
        'nombre_turno' => (string)($row['nombre_turno'] ?? ''),
        'hora_inicio' => substr((string)($row['hora_inicio'] ?? ''), 0, 5),
        'hora_fin' => substr((string)($row['hora_fin'] ?? ''), 0, 5),
        'dias_semana' => (string)($row['dias_semana'] ?? ''),
        'activo' => (int)($row['activo'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
        'exception_today' => $exceptionToday ? 1 : 0,
        'exception_today_reason' => $exceptionToday['motivo'] ?? '',
    ];
}

try {
    if ($action === 'meta') {
        sa_json_out(true, [
            'data' => [
                'csrf_token' => sa_csrf_token(),
                'servicios' => sa_fetch_services($pdo),
                'guardias' => sa_turnos_fetch_guardias($pdo),
            ],
        ]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'list') {
        $residencialId = (int)($_GET['residencial_id'] ?? 0);
        $guardiaId = (int)($_GET['guardia_id'] ?? 0);
        $soloActivos = trim((string)($_GET['solo_activos'] ?? ''));
        $q = sa_clean_str($_GET['q'] ?? '', 120);

        $sql = "
            SELECT
                gt.*,
                u.name AS guardia_nombre,
                u.email AS guardia_email,
                u.is_active AS guardia_activo,
                r.nombre AS residencial_nombre,
                r.codigo AS residencial_codigo
            FROM guardias_turnos gt
            JOIN users u ON u.id = gt.user_id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            JOIN usuarios_residenciales ur
              ON ur.user_id = gt.user_id
             AND ur.residencial_id = gt.residencial_id
            JOIN residenciales r ON r.id = gt.residencial_id
            WHERE t.nombre = 'guardia'
        ";
        $params = [];

        if ($residencialId > 0) {
            $sql .= " AND gt.residencial_id = :rid";
            $params['rid'] = $residencialId;
        }

        if ($guardiaId > 0) {
            $sql .= " AND gt.user_id = :uid";
            $params['uid'] = $guardiaId;
        }

        if ($soloActivos === '1') {
            $sql .= " AND gt.activo = 1";
        } elseif ($soloActivos === '0') {
            $sql .= " AND gt.activo = 0";
        }

        if ($q !== '') {
            $sql .= " AND (u.name LIKE :q OR u.email LIKE :q OR r.nombre LIKE :q OR r.codigo LIKE :q OR gt.nombre_turno LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }

        $sql .= " ORDER BY r.nombre ASC, u.name ASC, gt.activo DESC, gt.hora_inicio ASC, gt.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = array_map('sa_turnos_normalize_row', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        $summary = [
            'total' => count($items),
            'activos' => 0,
            'guardias' => 0,
            'servicios' => 0,
        ];
        $guardias = [];
        $servicios = [];
        foreach ($items as $item) {
            if ((int)$item['activo'] === 1) {
                $summary['activos']++;
            }
            $guardias[$item['guardia_id']] = true;
            $servicios[$item['residencial_id']] = true;
        }
        $summary['guardias'] = count($guardias);
        $summary['servicios'] = count($servicios);

        sa_json_out(true, ['data' => ['items' => $items, 'summary' => $summary]]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'list_exceptions') {
        $residencialId = (int)($_GET['residencial_id'] ?? 0);
        $guardiaId = (int)($_GET['guardia_id'] ?? 0);
        if ($residencialId <= 0 || $guardiaId <= 0) {
            sa_json_out(false, ['error' => 'Servicio y guardia son obligatorios.'], 422);
        }

        sa_turnos_guardia_belongs($pdo, $guardiaId, $residencialId);
        $exceptions = guardia_schedule_list_exceptions($pdo, $residencialId, $guardiaId, [
            'period' => (string)($_GET['period'] ?? ''),
            'only_active' => (string)($_GET['only_active'] ?? ''),
        ]);

        sa_json_out(true, ['data' => ['exceptions' => $exceptions]]);
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        sa_require_csrf();

        if ($action === 'create' || $action === 'update') {
            $turnoId = (int)($_POST['turno_id'] ?? 0);
            $residencialId = (int)($_POST['residencial_id'] ?? 0);
            $guardiaId = (int)($_POST['guardia_id'] ?? 0);
            $nombreTurno = sa_clean_str($_POST['nombre_turno'] ?? '', 80);
            $horaInicio = trim((string)($_POST['hora_inicio'] ?? ''));
            $horaFin = trim((string)($_POST['hora_fin'] ?? ''));
            $diasSemana = guardia_schedule_normalize_days((string)($_POST['dias_semana'] ?? ''));
            $activo = isset($_POST['activo']) && (string)$_POST['activo'] === '1' ? 1 : 0;

            if ($residencialId <= 0 || $guardiaId <= 0) {
                sa_json_out(false, ['error' => 'Servicio y guardia son obligatorios.'], 422);
            }
            if ($nombreTurno === '') {
                sa_json_out(false, ['error' => 'El nombre del turno es obligatorio.'], 422);
            }
            if (!guardia_schedule_valid_time($horaInicio) || !guardia_schedule_valid_time($horaFin)) {
                sa_json_out(false, ['error' => 'La hora de inicio y fin deben tener formato HH:MM.'], 422);
            }
            if ($diasSemana === '') {
                sa_json_out(false, ['error' => 'Debes indicar los días de semana.'], 422);
            }

            sa_turnos_guardia_belongs($pdo, $guardiaId, $residencialId);

            $pdo->beginTransaction();

            if ($activo === 1) {
                $stmtDeactivate = $pdo->prepare("
                    UPDATE guardias_turnos
                    SET activo = 0
                    WHERE residencial_id = :rid
                      AND user_id = :uid
                ");
                $stmtDeactivate->execute([
                    'rid' => $residencialId,
                    'uid' => $guardiaId,
                ]);
            }

            if ($action === 'create') {
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
                $message = 'Turno creado correctamente.';
            } else {
                if ($turnoId <= 0) {
                    $pdo->rollBack();
                    sa_json_out(false, ['error' => 'Turno inválido.'], 422);
                }

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
                    sa_json_out(false, ['error' => 'Turno no encontrado.'], 404);
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
                $message = 'Turno actualizado correctamente.';
            }

            $pdo->commit();
            sa_json_out(true, ['message' => $message]);
        }

        if ($action === 'toggle_active') {
            $turnoId = (int)($_POST['turno_id'] ?? 0);
            if ($turnoId <= 0) {
                sa_json_out(false, ['error' => 'Turno inválido.'], 422);
            }

            $stmt = $pdo->prepare("
                SELECT id, user_id, residencial_id, activo
                FROM guardias_turnos
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $turnoId]);
            $turno = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$turno) {
                sa_json_out(false, ['error' => 'Turno no encontrado.'], 404);
            }

            $pdo->beginTransaction();

            $newActive = (int)($turno['activo'] ?? 0) === 1 ? 0 : 1;
            if ($newActive === 1) {
                $pdo->prepare("
                    UPDATE guardias_turnos
                    SET activo = 0
                    WHERE residencial_id = :rid
                      AND user_id = :uid
                ")->execute([
                    'rid' => (int)$turno['residencial_id'],
                    'uid' => (int)$turno['user_id'],
                ]);
            }

            $pdo->prepare("
                UPDATE guardias_turnos
                SET activo = :activo,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                'activo' => $newActive,
                'id' => $turnoId,
            ]);

            $pdo->commit();

            sa_json_out(true, [
                'message' => $newActive === 1 ? 'Turno activado.' : 'Turno desactivado.',
            ]);
        }

        if ($action === 'delete') {
            $turnoId = (int)($_POST['turno_id'] ?? 0);
            if ($turnoId <= 0) {
                sa_json_out(false, ['error' => 'Turno inválido.'], 422);
            }

            $stmt = $pdo->prepare("DELETE FROM guardias_turnos WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $turnoId]);

            if ($stmt->rowCount() <= 0) {
                sa_json_out(false, ['error' => 'No encontramos el turno a eliminar.'], 404);
            }

            sa_json_out(true, ['message' => 'Turno eliminado correctamente.']);
        }

        if ($action === 'create_exception' || $action === 'update_exception') {
            $exceptionId = (int)($_POST['exception_id'] ?? 0);
            $residencialId = (int)($_POST['residencial_id'] ?? 0);
            $guardiaId = (int)($_POST['guardia_id'] ?? 0);
            $turnoId = (int)($_POST['turno_id'] ?? 0);
            $fechaInicio = trim((string)($_POST['fecha_inicio'] ?? ''));
            $fechaFin = trim((string)($_POST['fecha_fin'] ?? ''));
            $motivo = sa_clean_str($_POST['motivo'] ?? '', 120);
            $notas = sa_clean_str($_POST['notas'] ?? '', 1000);
            $activo = isset($_POST['activo']) && (string)$_POST['activo'] === '1' ? 1 : 0;

            if ($residencialId <= 0 || $guardiaId <= 0) {
                sa_json_out(false, ['error' => 'Servicio y guardia son obligatorios.'], 422);
            }
            if (!guardia_schedule_valid_date($fechaInicio) || !guardia_schedule_valid_date($fechaFin)) {
                sa_json_out(false, ['error' => 'Debes indicar una fecha inicial y final válidas.'], 422);
            }
            if ($fechaFin < $fechaInicio) {
                sa_json_out(false, ['error' => 'La fecha final no puede ser menor a la fecha inicial.'], 422);
            }
            if ($motivo === '') {
                sa_json_out(false, ['error' => 'El motivo de la excepción es obligatorio.'], 422);
            }

            sa_turnos_guardia_belongs($pdo, $guardiaId, $residencialId);

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
                    sa_json_out(false, ['error' => 'No encontramos el turno asociado a esta excepción.'], 422);
                }
            }

            if ($activo === 1) {
                $overlap = guardia_schedule_validate_exception_overlap($pdo, $residencialId, $guardiaId, $fechaInicio, $fechaFin, $exceptionId);
                if ($overlap) {
                    sa_json_out(false, ['error' => 'Ya existe una excepción activa que se cruza con ese rango de fechas.'], 422);
                }
            }

            if ($action === 'create_exception') {
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
                        :uid, :rid, :tid, :ini, :fin, :motivo, :notas, :activo, NOW(), NOW()
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
                $message = 'Excepción creada correctamente.';
            } else {
                if ($exceptionId <= 0) {
                    sa_json_out(false, ['error' => 'Excepción inválida.'], 422);
                }
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
                    sa_json_out(false, ['error' => 'No encontramos la excepción a editar.'], 404);
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
                $message = 'Excepción actualizada correctamente.';
            }

            sa_json_out(true, [
                'message' => $message,
                'data' => [
                    'exceptions' => guardia_schedule_list_exceptions($pdo, $residencialId, $guardiaId),
                ],
            ]);
        }

        if ($action === 'toggle_exception') {
            $exceptionId = (int)($_POST['exception_id'] ?? 0);
            $residencialId = (int)($_POST['residencial_id'] ?? 0);
            $guardiaId = (int)($_POST['guardia_id'] ?? 0);

            if ($exceptionId <= 0 || $residencialId <= 0 || $guardiaId <= 0) {
                sa_json_out(false, ['error' => 'Datos inválidos para cambiar la excepción.'], 422);
            }

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
                sa_json_out(false, ['error' => 'No encontramos la excepción indicada.'], 404);
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
                    sa_json_out(false, ['error' => 'No pudimos activarla porque se cruza con otra excepción activa del mismo guardia.'], 422);
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

            sa_json_out(true, [
                'message' => $newActive === 1 ? 'Excepción activada correctamente.' : 'Excepción desactivada correctamente.',
                'data' => [
                    'exceptions' => guardia_schedule_list_exceptions($pdo, $residencialId, $guardiaId),
                ],
            ]);
        }

        if ($action === 'delete_exception') {
            $exceptionId = (int)($_POST['exception_id'] ?? 0);
            $residencialId = (int)($_POST['residencial_id'] ?? 0);
            $guardiaId = (int)($_POST['guardia_id'] ?? 0);

            if ($exceptionId <= 0 || $residencialId <= 0 || $guardiaId <= 0) {
                sa_json_out(false, ['error' => 'Datos inválidos para eliminar la excepción.'], 422);
            }

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
                sa_json_out(false, ['error' => 'No encontramos la excepción a eliminar.'], 404);
            }

            sa_json_out(true, [
                'message' => 'Excepción eliminada correctamente.',
                'data' => [
                    'exceptions' => guardia_schedule_list_exceptions($pdo, $residencialId, $guardiaId),
                ],
            ]);
        }
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sa_json_out(false, ['error' => 'No pudimos procesar los turnos de guardias.'], 500);
}
