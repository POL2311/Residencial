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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    app_require_write_guard();
}

$user = current_user();
$adminId = (int)($user['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);

function hasColumn(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
    ");
    $stmt->execute([
        'table' => $table,
        'column' => $column,
    ]);
    return (int)$stmt->fetchColumn() > 0;
}

$hasGuardiaServicio = hasColumn($pdo, 'users', 'guardia_en_servicio');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $guardiaServicioSelect = $hasGuardiaServicio
            ? "IFNULL(u.guardia_en_servicio, 0) AS guardia_en_servicio"
            : "0 AS guardia_en_servicio";

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.name,
                u.email,
                u.telefono,
                u.is_active,
                $guardiaServicioSelect,

                gt.id AS turno_id,
                gt.nombre_turno,
                gt.hora_inicio,
                gt.hora_fin,
                gt.dias_semana,
                gt.activo AS turno_activo

            FROM usuarios_residenciales ur
            JOIN users u ON u.id = ur.user_id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            LEFT JOIN guardias_turnos gt
              ON gt.user_id = u.id
            AND gt.residencial_id = ur.residencial_id
            AND gt.activo = 1
            WHERE ur.residencial_id = ?
              AND t.nombre = 'guardia'
            ORDER BY u.name
        ");
        $stmt->execute([$residencialId]);

        json_out(true, [
            'guardias' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'supports_guardia_servicio' => $hasGuardiaServicio,
        ]);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_guardia') {
        $nombre    = trim((string)($_POST['nombre'] ?? ''));
        $email     = trim((string)($_POST['email'] ?? ''));
        $telefono  = trim((string)($_POST['telefono'] ?? ''));
        $password  = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password_confirm'] ?? '');
        $esActivo  = isset($_POST['es_activo']) ? 1 : 0;

        if ($nombre === '') {
            json_out(false, ['error' => 'El nombre es obligatorio']);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(false, ['error' => 'Email inválido']);
        }

        if ($password === '' || $password !== $password2 || strlen($password) < 6) {
            json_out(false, ['error' => 'Contraseña inválida o no coincide']);
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmtCheck->execute([$email]);
        if ($stmtCheck->fetchColumn()) {
            json_out(false, ['error' => 'Ya existe un usuario con ese correo']);
        }

        $pdo->beginTransaction();

        $stmtTipo = $pdo->prepare("SELECT id FROM tipos_usuario WHERE nombre = 'guardia' LIMIT 1");
        $stmtTipo->execute();
        $tipoId = (int)$stmtTipo->fetchColumn();

        if (!$tipoId) {
            throw new Exception('Tipo guardia no existe');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        if ($hasGuardiaServicio) {
            $stmtUser = $pdo->prepare("
                INSERT INTO users
                (tipo_usuario_id, name, email, telefono, password_hash, is_active, guardia_en_servicio)
                VALUES (?,?,?,?,?,?,0)
            ");
            $stmtUser->execute([
                $tipoId,
                $nombre,
                $email,
                $telefono !== '' ? $telefono : null,
                $hash,
                $esActivo,
            ]);
        } else {
            $stmtUser = $pdo->prepare("
                INSERT INTO users
                (tipo_usuario_id, name, email, telefono, password_hash, is_active)
                VALUES (?,?,?,?,?,?)
            ");
            $stmtUser->execute([
                $tipoId,
                $nombre,
                $email,
                $telefono !== '' ? $telefono : null,
                $hash,
                $esActivo,
            ]);
        }

        $guardiaId = (int)$pdo->lastInsertId();

        $stmtUR = $pdo->prepare("
            INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
            VALUES (?,?,1)
        ");
        $stmtUR->execute([$guardiaId, $residencialId]);

        $pdo->commit();

        json_out(true, ['message' => 'Guardia creado correctamente']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_servicio') {
        if (!$hasGuardiaServicio) {
            json_out(false, ['error' => 'La base de datos no tiene soporte para guardia_en_servicio.']);
        }

        $id = (int)($_POST['guardia_id'] ?? 0);
        $estado = (int)($_POST['nuevo_estado'] ?? -1);

        if ($id <= 0 || !in_array($estado, [0, 1], true)) {
            json_out(false, ['error' => 'Datos inválidos']);
        }

        $stmt = $pdo->prepare("
            UPDATE users u
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            SET u.guardia_en_servicio = ?
            WHERE u.id = ?
              AND ur.residencial_id = ?
              AND t.nombre = 'guardia'
        ");
        $stmt->execute([$estado, $id, $residencialId]);

        json_out(true, [
            'estado' => $estado,
            'message' => $estado ? 'Guardia en servicio.' : 'Guardia en descanso.'
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_guardia') {
        $id = (int)($_POST['guardia_id'] ?? 0);

        if ($id <= 0) {
            json_out(false, ['error' => 'ID inválido']);
        }

        $pdo->beginTransaction();

        $stmtCheck = $pdo->prepare("
            SELECT u.id
            FROM users u
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE u.id = ?
              AND ur.residencial_id = ?
              AND t.nombre = 'guardia'
        ");
        $stmtCheck->execute([$id, $residencialId]);

        if (!$stmtCheck->fetch()) {
            throw new Exception('Guardia no válido');
        }

        $pdo->prepare("
            DELETE FROM usuarios_residenciales
            WHERE user_id = ? AND residencial_id = ?
        ")->execute([$id, $residencialId]);

        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

        $pdo->commit();

        json_out(true, ['message' => 'Guardia eliminado']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_guardia') {
        $id       = (int)($_POST['guardia_id'] ?? 0);
        $nombre   = trim((string)($_POST['nombre'] ?? ''));
        $email    = trim((string)($_POST['email'] ?? ''));
        $telefono = trim((string)($_POST['telefono'] ?? ''));

        if ($id <= 0 || $nombre === '' || $email === '') {
            json_out(false, ['error' => 'Datos incompletos']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_out(false, ['error' => 'Email inválido']);
        }

        $stmtEmail = $pdo->prepare("
            SELECT id
            FROM users
            WHERE email = ?
              AND id <> ?
            LIMIT 1
        ");
        $stmtEmail->execute([$email, $id]);

        if ($stmtEmail->fetchColumn()) {
            json_out(false, ['error' => 'Ya existe otro usuario con ese correo']);
        }

        $stmt = $pdo->prepare("
            UPDATE users u
            JOIN usuarios_residenciales ur ON ur.user_id = u.id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            SET u.name = ?, u.email = ?, u.telefono = ?
            WHERE u.id = ?
              AND ur.residencial_id = ?
              AND t.nombre = 'guardia'
        ");
        $stmt->execute([
            $nombre,
            $email,
            $telefono !== '' ? $telefono : null,
            $id,
            $residencialId,
        ]);

        json_out(true, ['message' => 'Guardia actualizado']);
    }

    json_out(false, ['error' => 'Método no permitido']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    app_json_exception($e, 'No pudimos procesar la operación de guardias.');
}
