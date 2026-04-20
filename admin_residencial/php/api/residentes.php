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

$sessionUser = current_user();
$adminId = (int)($sessionUser['id'] ?? 0);
$residencialId = require_residencial_id($pdo, $adminId);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function map_residente(array $r): array {
    $unidadDetalleParts = [];

    if (!empty($r['torre'])) {
        $unidadDetalleParts[] = 'Torre ' . $r['torre'];
    }
    if (!empty($r['nivel'])) {
        $unidadDetalleParts[] = 'Nivel ' . $r['nivel'];
    }
    if (!empty($r['numero_interior'])) {
        $unidadDetalleParts[] = 'Int ' . $r['numero_interior'];
    }

    return [
        'id'              => (int)($r['resid_unid_id'] ?? 0),
        'resid_unid_id'   => (int)($r['resid_unid_id'] ?? 0),
        'user_id'         => (int)($r['user_id'] ?? 0),

        'nombre'          => (string)($r['name'] ?? ''),
        'email'           => (string)($r['email'] ?? ''),
        'telefono'        => (string)($r['telefono'] ?? ''),
        'is_active'       => (int)($r['is_active'] ?? 1),

        'es_titular'      => (int)($r['es_titular'] ?? 0),
        'activo_servicio' => (int)($r['activo_servicio'] ?? 1),

        'unidad_id'       => (int)($r['unidad_id'] ?? 0),
        'unidad_clave'    => (string)($r['clave'] ?? '—'),
        'unidad_detalle'  => implode(' · ', $unidadDetalleParts),
    ];
}

try {
    if ($method === 'GET') {
        if ($action === '' || $action === 'list') {
            $stmtU = $pdo->prepare("
                SELECT id, clave
                FROM unidades
                WHERE residencial_id = :rid
                  AND (activo = 1 OR activo IS NULL)
                ORDER BY clave ASC
            ");
            $stmtU->execute(['rid' => $residencialId]);
            $unidades = $stmtU->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $stmt = $pdo->prepare("
                SELECT
                    u.id AS user_id,
                    u.name,
                    u.email,
                    u.telefono,
                    u.is_active,

                    ru.id AS resid_unid_id,
                    ru.es_titular,
                    ru.activo AS activo_servicio,

                    un.id AS unidad_id,
                    un.clave,
                    un.torre,
                    un.nivel,
                    un.numero_interior
                FROM usuarios_residenciales ur
                JOIN users u ON u.id = ur.user_id
                JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                JOIN residentes_unidades ru ON ru.user_id = u.id
                JOIN unidades un ON un.id = ru.unidad_id
                WHERE ur.residencial_id = :rid
                  AND t.nombre = 'residente'
                ORDER BY un.clave ASC, u.name ASC
            ");
            $stmt->execute(['rid' => $residencialId]);

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $residentes = array_map('map_residente', $rows);

            json_out(true, [
                'residentes' => $residentes,
                'unidades'   => $unidades,
            ]);
        }

        if ($action === 'get') {
            $residUnidId = (int)($_GET['id'] ?? 0);
            if ($residUnidId <= 0) {
                json_out(false, ['error' => 'ID inválido.']);
            }

            $stmt = $pdo->prepare("
                SELECT
                    u.id AS user_id,
                    u.name,
                    u.email,
                    u.telefono,
                    u.is_active,
                    ru.id AS resid_unid_id,
                    ru.es_titular,
                    ru.activo AS activo_servicio,
                    un.id AS unidad_id,
                    un.clave,
                    un.torre,
                    un.nivel,
                    un.numero_interior
                FROM residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                JOIN users u ON u.id = ru.user_id
                JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
                JOIN usuarios_residenciales ur
                  ON ur.user_id = u.id
                 AND ur.residencial_id = un.residencial_id
                WHERE ru.id = :id
                  AND un.residencial_id = :rid
                  AND t.nombre = 'residente'
                LIMIT 1
            ");
            $stmt->execute([
                'id'  => $residUnidId,
                'rid' => $residencialId,
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_out(false, ['error' => 'Residente no encontrado.']);
            }

            json_out(true, ['residente' => map_residente($row)]);
        }

        json_out(false, ['error' => 'Acción GET no soportada.']);
    }

    if ($method === 'POST') {
        if ($action === 'toggle_active') {
            $residUnitId = (int)($_POST['id'] ?? 0);
            $newStatus = isset($_POST['active']) ? (int)$_POST['active'] : 0;

            if ($residUnitId <= 0) {
                json_out(false, ['error' => 'ID inválido.']);
            }

            $stmtCheck = $pdo->prepare("
                SELECT ru.id
                FROM residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                WHERE ru.id = :ru_id
                  AND un.residencial_id = :rid
                LIMIT 1
            ");
            $stmtCheck->execute([
                'ru_id' => $residUnitId,
                'rid'   => $residencialId,
            ]);

            if (!$stmtCheck->fetchColumn()) {
                json_out(false, ['error' => 'Residente no encontrado o fuera de tu residencial.']);
            }

            $stmt = $pdo->prepare("
                UPDATE residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                SET ru.activo = :act
                WHERE ru.id = :ru_id
                  AND un.residencial_id = :rid
            ");
            $stmt->execute([
                'act'   => $newStatus,
                'ru_id' => $residUnitId,
                'rid'   => $residencialId,
            ]);

            json_out(true, [
                'message' => $newStatus ? 'Residente reactivado.' : 'Residente suspendido.',
            ]);
        }

        if ($action === 'delete') {
            $residUnitId = (int)($_POST['id'] ?? 0);
            if ($residUnitId <= 0) {
                json_out(false, ['error' => 'ID inválido.']);
            }

            $pdo->beginTransaction();

            $stmtFind = $pdo->prepare("
                SELECT ru.user_id
                FROM residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                WHERE ru.id = :id
                  AND un.residencial_id = :rid
                LIMIT 1
            ");
            $stmtFind->execute([
                'id'  => $residUnitId,
                'rid' => $residencialId,
            ]);

            $uid = (int)$stmtFind->fetchColumn();
            if (!$uid) {
                $pdo->rollBack();
                json_out(false, ['error' => 'No encontrado o fuera de tu residencial.']);
            }

            $stmtDelRU = $pdo->prepare("DELETE FROM residentes_unidades WHERE id = :id");
            $stmtDelRU->execute(['id' => $residUnitId]);

            $stmtHasAny = $pdo->prepare("
                SELECT COUNT(*)
                FROM residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                WHERE ru.user_id = :uid
                  AND un.residencial_id = :rid
            ");
            $stmtHasAny->execute([
                'uid' => $uid,
                'rid' => $residencialId,
            ]);

            $count = (int)$stmtHasAny->fetchColumn();

            if ($count === 0) {
                $stmtDelUR = $pdo->prepare("
                    DELETE FROM usuarios_residenciales
                    WHERE user_id = :uid
                      AND residencial_id = :rid
                ");
                $stmtDelUR->execute([
                    'uid' => $uid,
                    'rid' => $residencialId,
                ]);
            }

            $pdo->commit();
            json_out(true, ['message' => 'Residente eliminado.']);
        }

        if ($action === 'create' || $action === 'update') {
            $name = clean_str($_POST['name'] ?? $_POST['nombre'] ?? '');
            $email = clean_str($_POST['email'] ?? '');
            $phone = digits($_POST['telefono'] ?? '');
            $unitId = (int)($_POST['unidad_id'] ?? 0);
            $isTitular = isset($_POST['es_titular']) ? 1 : 0;
            $password = trim((string)($_POST['password'] ?? ''));
            $passwordConfirm = trim((string)($_POST['password_confirm'] ?? ''));

            if ($name === '') {
                json_out(false, ['error' => 'Nombre es requerido.']);
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                json_out(false, ['error' => 'Correo inválido.']);
            }
            if ($unitId <= 0) {
                json_out(false, ['error' => 'Unidad es requerida.']);
            }

            if ($password !== '' || $passwordConfirm !== '') {
                if (strlen($password) < 8) {
                    json_out(false, ['error' => 'La contraseña debe tener al menos 8 caracteres.']);
                }
                if ($password !== $passwordConfirm) {
                    json_out(false, ['error' => 'Las contraseñas no coinciden.']);
                }
            }

            $stmtU = $pdo->prepare("
                SELECT id
                FROM unidades
                WHERE id = :uid
                  AND residencial_id = :rid
                  AND (activo = 1 OR activo IS NULL)
                LIMIT 1
            ");
            $stmtU->execute([
                'uid' => $unitId,
                'rid' => $residencialId,
            ]);

            if (!$stmtU->fetchColumn()) {
                json_out(false, ['error' => 'Unidad no válida en este residencial.']);
            }
        }

        if ($action === 'create') {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("
                SELECT id, tipo_usuario_id
                FROM users
                WHERE email = :email
                LIMIT 1
            ");
            $stmtCheck->execute(['email' => $email]);
            $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            $newUserId = 0;
            $plainPassword = null;
            $RESIDENTE_TIPO_ID = 5;

            if ($existing) {
                if ((int)$existing['tipo_usuario_id'] !== $RESIDENTE_TIPO_ID) {
                    $pdo->rollBack();
                    json_out(false, ['error' => 'El correo ya está en uso por otro tipo de usuario.']);
                }
                $newUserId = (int)$existing['id'];
            } else {
                if ($password !== '') {
                    $plainPassword = null;
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                } else {
                    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
                    $plainPassword = '';
                    for ($i = 0; $i < 8; $i++) {
                        $plainPassword .= $chars[random_int(0, strlen($chars) - 1)];
                    }
                    $hash = password_hash($plainPassword, PASSWORD_DEFAULT);
                }

                $stmtNewU = $pdo->prepare("
                    INSERT INTO users (
                        tipo_usuario_id,
                        name,
                        email,
                        telefono,
                        password_hash,
                        is_active
                    ) VALUES (
                        :tipo,
                        :name,
                        :email,
                        :tel,
                        :pass,
                        1
                    )
                ");
                $stmtNewU->execute([
                    'tipo'  => $RESIDENTE_TIPO_ID,
                    'name'  => $name,
                    'email' => $email,
                    'tel'   => ($phone !== '' ? $phone : null),
                    'pass'  => $hash,
                ]);

                $newUserId = (int)$pdo->lastInsertId();
            }

            $stmtLinkRes = $pdo->prepare("
                INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
                VALUES (:uid, :rid, 1)
                ON DUPLICATE KEY UPDATE residencial_id = residencial_id
            ");
            $stmtLinkRes->execute([
                'uid' => $newUserId,
                'rid' => $residencialId,
            ]);

            $stmtDupe = $pdo->prepare("
                SELECT COUNT(*)
                FROM residentes_unidades
                WHERE user_id = :uid
                  AND unidad_id = :unid
            ");
            $stmtDupe->execute([
                'uid'  => $newUserId,
                'unid' => $unitId,
            ]);

            if ((int)$stmtDupe->fetchColumn() > 0) {
                $pdo->rollBack();
                json_out(false, ['error' => 'Este residente ya está asignado a esa unidad.']);
            }

            $stmtLinkUnit = $pdo->prepare("
                INSERT INTO residentes_unidades (user_id, unidad_id, es_titular, activo)
                VALUES (:uid, :unid, :tit, 1)
            ");
            $stmtLinkUnit->execute([
                'uid'  => $newUserId,
                'unid' => $unitId,
                'tit'  => $isTitular,
            ]);

            $pdo->commit();

            $msg = 'Residente agregado correctamente.';
            if ($plainPassword) {
                $msg .= " Contraseña temporal: {$plainPassword}";
            } elseif ($password !== '') {
                $msg .= ' Contraseña asignada correctamente.';
            }

            json_out(true, ['message' => $msg]);
        }

        if ($action === 'update') {
            $residUnitId = (int)($_POST['id'] ?? $_POST['resid_unid_id'] ?? 0);
            if ($residUnitId <= 0) {
                json_out(false, ['error' => 'ID inválido.']);
            }

            $pdo->beginTransaction();

            $stmtFind = $pdo->prepare("
                SELECT ru.user_id
                FROM residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                WHERE ru.id = :id
                  AND un.residencial_id = :rid
                LIMIT 1
            ");
            $stmtFind->execute([
                'id'  => $residUnitId,
                'rid' => $residencialId,
            ]);

            $uid = (int)$stmtFind->fetchColumn();
            if (!$uid) {
                $pdo->rollBack();
                json_out(false, ['error' => 'Residente no encontrado o fuera de tu residencial.']);
            }

            $stmtEmail = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = :email
                  AND id <> :id
                LIMIT 1
            ");
            $stmtEmail->execute([
                'email' => $email,
                'id'    => $uid,
            ]);

            if ($stmtEmail->fetchColumn()) {
                $pdo->rollBack();
                json_out(false, ['error' => 'Ese correo ya está en uso.']);
            }

            $updateSql = "
                UPDATE users
                SET name = :name,
                    email = :email,
                    telefono = :tel
            ";

            $updateParams = [
                'name'  => $name,
                'email' => $email,
                'tel'   => ($phone !== '' ? $phone : null),
                'id'    => $uid,
            ];

            if ($password !== '') {
                $updateSql .= ", password_hash = :pass";
                $updateParams['pass'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $updateSql .= " WHERE id = :id";

            $stmtUpU = $pdo->prepare($updateSql);
            $stmtUpU->execute($updateParams);

            $stmtDupe = $pdo->prepare("
                SELECT COUNT(*)
                FROM residentes_unidades
                WHERE user_id = :uid
                  AND unidad_id = :unid
                  AND id <> :id
            ");
            $stmtDupe->execute([
                'uid'  => $uid,
                'unid' => $unitId,
                'id'   => $residUnitId,
            ]);

            if ((int)$stmtDupe->fetchColumn() > 0) {
                $pdo->rollBack();
                json_out(false, ['error' => 'Este residente ya está asignado a esa unidad.']);
            }

            $stmtUpRU = $pdo->prepare("
                UPDATE residentes_unidades ru
                JOIN unidades un ON un.id = ru.unidad_id
                SET ru.unidad_id = :unid,
                    ru.es_titular = :tit
                WHERE ru.id = :id
                  AND un.residencial_id = :rid
            ");
            $stmtUpRU->execute([
                'unid' => $unitId,
                'tit'  => $isTitular,
                'id'   => $residUnitId,
                'rid'  => $residencialId,
            ]);

            $pdo->commit();

            json_out(true, [
                'message' => $password !== ''
                    ? 'Residente y contraseña actualizados correctamente.'
                    : 'Residente actualizado correctamente.',
            ]);
        }

        json_out(false, ['error' => 'Acción POST no soportada.']);
    }

    json_out(false, ['error' => 'Método no soportado.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    app_json_exception($e, 'No pudimos procesar la información de residentes.');
}
