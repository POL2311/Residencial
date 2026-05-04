<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../../config/service_profile.php';

$action = sa_post_action('meta');

try {
    service_profile_schema_ensure($pdo);

    $resolveTipoRol = static function (int $tipoId) use ($pdo): string {
        if ($tipoId <= 0) {
            return '';
        }
        $stmtTipo = $pdo->prepare("SELECT nombre FROM tipos_usuario WHERE id = :id LIMIT 1");
        $stmtTipo->execute(['id' => $tipoId]);
        return service_profile_normalize_role_name((string)($stmtTipo->fetchColumn() ?: ''));
    };

    $resolveUserRol = static function (int $userId) use ($pdo): string {
        if ($userId <= 0) {
            return '';
        }
        $stmtUserRole = $pdo->prepare("
            SELECT t.nombre
            FROM users u
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmtUserRole->execute(['id' => $userId]);
        return service_profile_normalize_role_name((string)($stmtUserRole->fetchColumn() ?: ''));
    };

    $operatorStatusForAssignment = static function (int $residencialId, string $roleKey, bool $isActive) use ($pdo): array {
        if ($residencialId <= 0 || $roleKey === '') {
            return [
                'code' => $isActive ? 'pendiente' : 'inactivo',
                'label' => $isActive ? 'Pendiente' : 'Inactivo',
                'ready' => false,
            ];
        }

        $profile = service_profile_get($pdo, $residencialId);
        return service_profile_operator_status_for_service($profile, $roleKey, $isActive);
    };

    $operatorStatusMessage = static function (array $status): string {
        return match ((string)($status['code'] ?? '')) {
            'listo' => 'El operador quedó listo para entrar.',
            'pendiente' => 'El operador quedó asignado, pero sigue pendiente de activación para este servicio.',
            default => 'El operador quedó creado, pero su cuenta sigue inactiva.',
        };
    };

    if ($action === 'meta') {
        $tipos = $pdo->query("SELECT id, nombre, descripcion FROM tipos_usuario ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $usuarios = $pdo->query("
            SELECT u.id, u.name, u.email, u.is_active, t.nombre AS rol_nombre
            FROM users u
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            ORDER BY u.name ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $residenciales = $pdo->query("
            SELECT r.id, r.nombre, r.codigo
            FROM residenciales r
            ORDER BY r.nombre ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($residenciales as &$residencial) {
            $residencial['service_profile'] = service_profile_frontend_payload($pdo, (int)$residencial['id'], 'admin_residencial');
        }
        unset($residencial);

        sa_json_out(true, [
            'data' => [
                'tipos' => $tipos,
                'usuarios' => $usuarios,
                'residenciales' => $residenciales,
                'csrf_token' => sa_csrf_token(),
            ],
        ]);
    }

    if ($action === 'list_users') {
        $q = sa_clean_str($_POST['uq'] ?? $_GET['uq'] ?? '', 120);
        $rol = (string)($_POST['urol'] ?? $_GET['urol'] ?? '');
        $status = (string)($_POST['ustatus'] ?? $_GET['ustatus'] ?? '');

        $sql = "
            SELECT u.id, u.name, u.email, u.telefono, u.is_active, u.created_at,
                   t.nombre AS rol_nombre, t.id AS rol_id
            FROM users u
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE 1 = 1
        ";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (u.name LIKE :q OR u.email LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }
        if ($rol !== '') {
            $sql .= " AND u.tipo_usuario_id = :rol";
            $params['rol'] = (int)$rol;
        }
        if ($status !== '') {
            $sql .= " AND u.is_active = :status";
            $params['status'] = (int)$status;
        }

        $sql .= " ORDER BY u.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        sa_json_out(true, [
            'data' => [
                'items' => $items,
                'summary' => [
                    'total' => count($items),
                    'activos' => count(array_filter($items, static fn(array $item): bool => (int)$item['is_active'] === 1)),
                ],
            ],
        ]);
    }

    if ($action === 'list_assignments') {
        $q = sa_clean_str($_POST['aq'] ?? $_GET['aq'] ?? '', 120);
        $residencial = (string)($_POST['ares'] ?? $_GET['ares'] ?? '');

        $sql = "
            SELECT ur.id,
                   u.name AS usuario_nombre,
                   u.email AS usuario_email,
                   t.nombre AS usuario_rol,
                   r.nombre AS residencial_nombre,
                   r.codigo AS residencial_codigo,
                   r.id AS residencial_id,
                   ur.es_principal,
                   ur.created_at
            FROM usuarios_residenciales ur
            JOIN users u ON u.id = ur.user_id
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            JOIN residenciales r ON r.id = ur.residencial_id
            WHERE 1 = 1
        ";
        $params = [];

        if ($q !== '') {
            $sql .= " AND (u.name LIKE :q OR u.email LIKE :q OR r.nombre LIKE :q OR r.codigo LIKE :q)";
            $params['q'] = '%' . $q . '%';
        }
        if ($residencial !== '') {
            $sql .= " AND r.id = :residencial";
            $params['residencial'] = (int)$residencial;
        }

        $sql .= " ORDER BY ur.created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        sa_json_out(true, [
            'data' => [
                'items' => $items,
                'summary' => ['total' => count($items)],
            ],
        ]);
    }

    if ($action === 'create_user' || $action === 'create_user_with_assignment') {
        sa_require_csrf();

        $nombre = sa_clean_str($_POST['nombre'] ?? '', 100);
        $email = sa_clean_str($_POST['email'] ?? '', 100);
        $telefono = sa_clean_str($_POST['telefono'] ?? '', 30);
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        $tipoId = (int)($_POST['tipo_usuario_id'] ?? 0);
        $isActive = isset($_POST['is_active']) && (string)$_POST['is_active'] === '1' ? 1 : 0;
        $withAssignment = $action === 'create_user_with_assignment';
        $residencialId = (int)($_POST['residencial_id'] ?? 0);
        $esPrincipal = isset($_POST['es_principal']) && (string)$_POST['es_principal'] === '1' ? 1 : 0;

        $errors = [];
        if ($nombre === '') $errors[] = 'El nombre del usuario es obligatorio.';
        if ($email === '') $errors[] = 'El email es obligatorio.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'El email no tiene un formato válido.';
        if ($password === '' || $passwordConfirm === '') {
            $errors[] = 'La contraseña y su confirmación son obligatorias.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Las contraseñas no coinciden.';
        } elseif (strlen($password) < 6) {
            $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
        }
        if ($tipoId <= 0) $errors[] = 'Debes seleccionar un tipo de usuario.';
        if ($withAssignment && $residencialId <= 0) {
            $errors[] = 'Debes indicar el servicio al que se asignará este usuario.';
        }

        $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmtCheck->execute(['email' => $email]);
        if ($stmtCheck->fetch()) {
            $errors[] = 'Ya existe un usuario con ese correo.';
        }

        if ($withAssignment && $residencialId > 0) {
            $stmtRes = $pdo->prepare("SELECT id FROM residenciales WHERE id = :id LIMIT 1");
            $stmtRes->execute(['id' => $residencialId]);
            if (!$stmtRes->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = 'El servicio seleccionado ya no existe.';
            } else {
                $profile = service_profile_get($pdo, $residencialId);
                $roleKey = $resolveTipoRol($tipoId);
                if ($roleKey === '' || !service_profile_role_assignable_to_service($profile, $roleKey)) {
                    $errors[] = 'El rol seleccionado no es compatible con este servicio.';
                }
            }
        }

        if ($errors) {
            sa_json_out(false, ['error' => implode(' ', $errors)], 422);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO users (tipo_usuario_id, name, email, telefono, password_hash, is_active)
                VALUES (:tipo, :name, :email, :telefono, :hash, :active)
            ");
            $stmt->execute([
                'tipo' => $tipoId,
                'name' => $nombre,
                'email' => $email,
                'telefono' => ($telefono !== '' ? $telefono : null),
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'active' => $isActive,
            ]);

            $userId = (int)$pdo->lastInsertId();

            if ($withAssignment) {
                if ($esPrincipal === 1) {
                    $stmtReset = $pdo->prepare("
                        UPDATE usuarios_residenciales
                        SET es_principal = 0
                        WHERE user_id = :user_id
                    ");
                    $stmtReset->execute(['user_id' => $userId]);
                }

                $stmtAssign = $pdo->prepare("
                    INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
                    VALUES (:user_id, :residencial_id, :es_principal)
                ");
                $stmtAssign->execute([
                    'user_id' => $userId,
                    'residencial_id' => $residencialId,
                    'es_principal' => $esPrincipal,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $roleKey = $resolveTipoRol($tipoId);
        $assignmentStatus = $withAssignment
            ? $operatorStatusForAssignment($residencialId, $roleKey, $isActive === 1)
            : null;

        sa_json_out(true, [
            'message' => $withAssignment
                ? 'Usuario creado y asignado correctamente. ' . $operatorStatusMessage($assignmentStatus ?? [])
                : 'Usuario creado correctamente.',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'name' => $nombre,
                    'email' => $email,
                ],
                'assignment' => $withAssignment ? [
                    'residencial_id' => $residencialId,
                    'es_principal' => $esPrincipal,
                    'operator_status' => $assignmentStatus,
                ] : null,
            ],
        ]);
    }

    if ($action === 'assign_residencial') {
        sa_require_csrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        $residencialId = (int)($_POST['residencial_id'] ?? 0);
        $esPrincipal = isset($_POST['es_principal']) && (string)$_POST['es_principal'] === '1' ? 1 : 0;

        if ($userId <= 0 || $residencialId <= 0) {
            sa_json_out(false, ['error' => 'Debes seleccionar un usuario y un residencial válidos.'], 422);
        }

        $profile = service_profile_get($pdo, $residencialId);
        $roleKey = $resolveUserRol($userId);
        if ($roleKey === '' || !service_profile_role_assignable_to_service($profile, $roleKey)) {
            sa_json_out(false, ['error' => 'Ese usuario no es compatible con los roles habilitados para este servicio.'], 422);
        }

        $stmtCheck = $pdo->prepare("
            SELECT id
            FROM usuarios_residenciales
            WHERE user_id = :user_id AND residencial_id = :residencial_id
            LIMIT 1
        ");
        $stmtCheck->execute([
            'user_id' => $userId,
            'residencial_id' => $residencialId,
        ]);
        if ($stmtCheck->fetch()) {
            sa_json_out(false, ['error' => 'Ese usuario ya está asignado a ese residencial.'], 422);
        }

        if ($esPrincipal === 1) {
            $stmtReset = $pdo->prepare("
                UPDATE usuarios_residenciales
                SET es_principal = 0
                WHERE user_id = :user_id
            ");
            $stmtReset->execute(['user_id' => $userId]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
            VALUES (:user_id, :residencial_id, :es_principal)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'residencial_id' => $residencialId,
            'es_principal' => $esPrincipal,
        ]);

        $stmtUser = $pdo->prepare("SELECT is_active FROM users WHERE id = :id LIMIT 1");
        $stmtUser->execute(['id' => $userId]);
        $isActive = (int)($stmtUser->fetchColumn() ?: 0) === 1;
        $status = $operatorStatusForAssignment($residencialId, $roleKey, $isActive);

        sa_json_out(true, [
            'message' => 'Asignación creada correctamente. ' . $operatorStatusMessage($status),
            'data' => [
                'operator_status' => $status,
            ],
        ]);
    }

    if ($action === 'remove_assignment') {
        sa_require_csrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        $residencialId = (int)($_POST['residencial_id'] ?? 0);

        if ($userId <= 0 || $residencialId <= 0) {
            sa_json_out(false, ['error' => 'Debes indicar un operador y un servicio válidos.'], 422);
        }

        $stmtAssignment = $pdo->prepare("
            SELECT ur.id, ur.es_principal, u.name, u.email
            FROM usuarios_residenciales ur
            JOIN users u ON u.id = ur.user_id
            WHERE ur.user_id = :user_id
              AND ur.residencial_id = :residencial_id
            LIMIT 1
        ");
        $stmtAssignment->execute([
            'user_id' => $userId,
            'residencial_id' => $residencialId,
        ]);
        $assignment = $stmtAssignment->fetch(PDO::FETCH_ASSOC);

        if (!$assignment) {
            sa_json_out(false, ['error' => 'No encontramos la asignación de ese operador en el servicio seleccionado.'], 404);
        }

        $delete = $pdo->prepare("
            DELETE FROM usuarios_residenciales
            WHERE user_id = :user_id
              AND residencial_id = :residencial_id
            LIMIT 1
        ");
        $delete->execute([
            'user_id' => $userId,
            'residencial_id' => $residencialId,
        ]);

        sa_json_out(true, [
            'message' => 'Operador removido del servicio correctamente. La cuenta se conservó.',
            'data' => [
                'user' => [
                    'id' => $userId,
                    'name' => (string)($assignment['name'] ?? ''),
                    'email' => (string)($assignment['email'] ?? ''),
                ],
                'residencial_id' => $residencialId,
            ],
        ]);
    }

    if ($action === 'disable_user') {
        sa_require_csrf();

        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            sa_json_out(false, ['error' => 'Debes indicar un usuario válido.'], 422);
        }

        $currentUser = sa_current_user($pdo);
        if ((int)$currentUser['id'] === $userId) {
            sa_json_out(false, ['error' => 'No puedes desactivar tu propio usuario desde esta sesión.'], 422);
        }

        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.is_active, t.nombre AS rol_nombre
            FROM users u
            JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
            WHERE u.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            sa_json_out(false, ['error' => 'No encontramos el usuario seleccionado.'], 404);
        }

        if ((int)$user['is_active'] !== 1) {
            sa_json_out(false, ['error' => 'Ese usuario ya se encuentra inactivo.'], 422);
        }

        $disable = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = :id LIMIT 1");
        $disable->execute(['id' => $userId]);

        sa_json_out(true, [
            'message' => 'Usuario desactivado correctamente. Se conservó su historial y relaciones.',
            'data' => [
                'user' => [
                    'id' => (int)$user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'rol_nombre' => $user['rol_nombre'],
                    'is_active' => 0,
                ],
            ],
        ]);
    }

    sa_json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
    app_json_exception($e, 'No pudimos procesar la información de usuarios.');
}
