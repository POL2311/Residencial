<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['admin_residencial']);

$sessionUser = current_user();
$adminId = (int)($sessionUser['id'] ?? 0);

function json_out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function clean_str(?string $v): string {
  $v = trim((string)($v ?? ''));
  $v = preg_replace('/\s+/', ' ', $v);
  return $v ?: '';
}

function digits(?string $v): string {
  return preg_replace('/\D+/', '', (string)($v ?? ''));
}

function require_residencial_id(PDO $pdo, int $adminId): int {
  $stmt = $pdo->prepare("
    SELECT residencial_id
    FROM usuarios_residenciales
    WHERE user_id = ?
    ORDER BY es_principal DESC, created_at ASC
    LIMIT 1
  ");
  $stmt->execute([$adminId]);
  $rid = (int)$stmt->fetchColumn();
  if (!$rid) json_out(false, ['error' => 'No tienes residencial asignado.']);
  return $rid;
}

$residencialId = require_residencial_id($pdo, $adminId);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Normaliza un row de DB a llaves estables para el front:
 * id, user_id, nombre, email, telefono, is_active, es_titular, activo_servicio,
 * unidad_id, unidad_clave, unidad_detalle
 */
function map_residente(array $r): array {
  $unidadDetalleParts = [];
  if (!empty($r['torre'])) $unidadDetalleParts[] = 'Torre ' . $r['torre'];
  if (!empty($r['nivel'])) $unidadDetalleParts[] = 'Nivel ' . $r['nivel'];
  if (!empty($r['numero_interior'])) $unidadDetalleParts[] = 'Int ' . $r['numero_interior'];

  return [
    // IDs
    'id'               => (int)($r['resid_unid_id'] ?? 0),        // id de residentes_unidades (útil para toggle/update)
    'resid_unid_id'    => (int)($r['resid_unid_id'] ?? 0),
    'user_id'          => (int)($r['user_id'] ?? 0),

    // Perfil
    'nombre'           => (string)($r['name'] ?? ''),             // <- NORMALIZADO
    'email'            => (string)($r['email'] ?? ''),
    'telefono'         => (string)($r['telefono'] ?? ''),
    'is_active'        => (int)($r['is_active'] ?? 1),

    // Estado en la unidad
    'es_titular'       => (int)($r['es_titular'] ?? 0),
    'activo_servicio'  => (int)($r['activo_servicio'] ?? 1),

    // Unidad
    'unidad_id'        => (int)($r['unidad_id'] ?? 0),
    'unidad_clave'     => (string)($r['clave'] ?? '—'),
    'unidad_detalle'   => implode(' · ', $unidadDetalleParts),
  ];
}


try {

  // =========================
  // GET: LIST / GET ONE
  // =========================
  if ($method === 'GET') {

    // LIST
    if ($action === '' || $action === 'list') {

      // 1) Unidades del residencial (para el modal)
      $stmtU = $pdo->prepare("
        SELECT id, clave
        FROM unidades
        WHERE residencial_id = :rid AND (activo = 1 OR activo IS NULL)
        ORDER BY clave ASC
      ");
      $stmtU->execute(['rid' => $residencialId]);
      $unidades = $stmtU->fetchAll(PDO::FETCH_ASSOC) ?: [];

      // 2) Residentes del residencial
      $stmt = $pdo->prepare("
        SELECT
          u.id AS user_id,
          u.name, u.email, u.telefono, u.is_active,

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

    // GET ONE
    if ($action === 'get') {
      $residUnidId = (int)($_GET['id'] ?? 0);
      if ($residUnidId <= 0) json_out(false, ['error' => 'ID inválido.']);

      $stmt = $pdo->prepare("
        SELECT
          u.id AS user_id,
          u.name, u.email, u.telefono, u.is_active,
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
        JOIN usuarios_residenciales ur ON ur.user_id = u.id AND ur.residencial_id = un.residencial_id
        WHERE ru.id = :id
          AND un.residencial_id = :rid
          AND t.nombre = 'residente'
        LIMIT 1
      ");
      $stmt->execute(['id' => $residUnidId, 'rid' => $residencialId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$row) json_out(false, ['error' => 'Residente no encontrado.']);
      json_out(true, ['residente' => map_residente($row)]);
    }
    // =========================
// GET: PAGOS LIST
// =========================

if ($action === 'payments_list') {
  $userId = (int)($_GET['user_id'] ?? 0);
  if ($userId <= 0) json_out(false, ['error' => 'user_id inválido']);

  $stmt = $pdo->prepare("
    SELECT monto, fecha_pago, metodo, concepto, created_at
    FROM pagos_residentes
    WHERE residencial_id = :rid AND user_id = :uid
    ORDER BY fecha_pago DESC, created_at DESC
  ");
  $stmt->execute(['rid' => $residencialId, 'uid' => $userId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $total = 0;
  foreach ($rows as $r) $total += (float)$r['monto'];

  json_out(true, [
    'total' => $total,
    'pagos' => $rows
  ]);
}


    json_out(false, ['error' => 'Acción GET no soportada.']);
  }


  // =========================
  // POST: CREATE / UPDATE / DELETE / TOGGLE
  // =========================
  if ($method === 'POST') {

    // TOGGLE servicio (ru.activo)
    if ($action === 'toggle_active') {
      $residUnitId = (int)($_POST['id'] ?? 0);
      $newStatus = isset($_POST['active']) ? (int)$_POST['active'] : 0;

      if ($residUnitId <= 0) json_out(false, ['error' => 'ID inválido.']);

      $stmt = $pdo->prepare("
        UPDATE residentes_unidades ru
        JOIN unidades un ON un.id = ru.unidad_id
        SET ru.activo = :act
        WHERE ru.id = :ru_id
          AND un.residencial_id = :rid
      ");
      $stmt->execute(['act' => $newStatus, 'ru_id' => $residUnitId, 'rid' => $residencialId]);

      if ($stmt->rowCount() === 0) {
        json_out(false, ['error' => 'Residente no encontrado o fuera de tu residencial.']);
      }

      json_out(true, ['message' => $newStatus ? 'Residente reactivado.' : 'Residente suspendido.']);
    }

    // DELETE (quita relación con unidad; y si ya no tiene nada, opcionalmente desactiva user)
    if ($action === 'delete') {
      $residUnitId = (int)($_POST['id'] ?? 0);
      if ($residUnitId <= 0) json_out(false, ['error' => 'ID inválido.']);

      $pdo->beginTransaction();

      // 1) Asegurar que pertenece al residencial y obtener user_id
      $stmtFind = $pdo->prepare("
        SELECT ru.user_id
        FROM residentes_unidades ru
        JOIN unidades un ON un.id = ru.unidad_id
        WHERE ru.id = :id AND un.residencial_id = :rid
        LIMIT 1
      ");
      $stmtFind->execute(['id' => $residUnitId, 'rid' => $residencialId]);
      $uid = (int)$stmtFind->fetchColumn();
      if (!$uid) {
        $pdo->rollBack();
        json_out(false, ['error' => 'No encontrado o fuera de tu residencial.']);
      }

      // 2) Borrar relación residentes_unidades
      $stmtDelRU = $pdo->prepare("DELETE FROM residentes_unidades WHERE id = :id");
      $stmtDelRU->execute(['id' => $residUnitId]);

      // 3) Si el usuario ya no tiene unidades en ESTE residencial, borrar usuarios_residenciales (opcional)
      $stmtHasAny = $pdo->prepare("
        SELECT COUNT(*)
        FROM residentes_unidades ru
        JOIN unidades un ON un.id = ru.unidad_id
        WHERE ru.user_id = :uid AND un.residencial_id = :rid
      ");
      $stmtHasAny->execute(['uid' => $uid, 'rid' => $residencialId]);
      $count = (int)$stmtHasAny->fetchColumn();

      if ($count === 0) {
        $stmtDelUR = $pdo->prepare("DELETE FROM usuarios_residenciales WHERE user_id = :uid AND residencial_id = :rid");
        $stmtDelUR->execute(['uid' => $uid, 'rid' => $residencialId]);
      }

      $pdo->commit();
      json_out(true, ['message' => 'Residente eliminado.']);
    }

    // =========================
// POST: REGISTRAR PAGO
// =========================
if ($action === 'payment_create') {
  $userId  = (int)($_POST['user_id'] ?? 0);
  $monto   = (float)($_POST['monto'] ?? 0);
  $fecha   = $_POST['fecha'] ?? '';
  $metodo  = clean_str($_POST['metodo'] ?? 'efectivo');
  $concept = clean_str($_POST['concepto'] ?? '');

  if ($userId <= 0 || $monto <= 0 || $fecha === '') {
    json_out(false, ['error' => 'Datos de pago inválidos']);
  }

  // validar pertenencia
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM usuarios_residenciales
    WHERE user_id = :uid AND residencial_id = :rid
  ");
  $stmt->execute(['uid' => $userId, 'rid' => $residencialId]);
  if (!(int)$stmt->fetchColumn()) {
    json_out(false, ['error' => 'No autorizado']);
  }

  $stmt = $pdo->prepare("
    INSERT INTO pagos_residentes
    (residencial_id, user_id, monto, fecha_pago, metodo, concepto)
    VALUES (:rid, :uid, :m, :f, :me, :c)
  ");
  $stmt->execute([
    'rid' => $residencialId,
    'uid' => $userId,
    'm'   => $monto,
    'f'   => $fecha,
    'me'  => $metodo,
    'c'   => ($concept ?: null),
  ]);

  json_out(true, ['message' => 'Pago registrado correctamente']);
}

    // CREATE / UPDATE comparten validaciones
    $name  = clean_str($_POST['name'] ?? $_POST['nombre'] ?? '');
    $email = clean_str($_POST['email'] ?? '');
    $phone = digits($_POST['telefono'] ?? '');
    $unitId = (int)($_POST['unidad_id'] ?? 0);
    $isTitular = isset($_POST['es_titular']) ? 1 : 0;

    if ($name === '') json_out(false, ['error' => 'Nombre es requerido.']);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(false, ['error' => 'Correo inválido.']);
    if ($unitId <= 0) json_out(false, ['error' => 'Unidad es requerida.']);

    // Validar unidad pertenece a este residencial
    $stmtU = $pdo->prepare("
      SELECT id
      FROM unidades
      WHERE id = :uid AND residencial_id = :rid AND (activo = 1 OR activo IS NULL)
      LIMIT 1
    ");
    $stmtU->execute(['uid' => $unitId, 'rid' => $residencialId]);
    if (!$stmtU->fetchColumn()) json_out(false, ['error' => 'Unidad no válida en este residencial.']);

    // CREATE
    if ($action === 'create') {
      $pdo->beginTransaction();

      // Reutilizar user si existe por email y es residente
      $stmtCheck = $pdo->prepare("SELECT id, tipo_usuario_id FROM users WHERE email = :email LIMIT 1");
      $stmtCheck->execute(['email' => $email]);
      $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);

      $newUserId = 0;
      $plainPassword = null;

      // tipo_usuario_id de residente (en tu sistema parece ser 5)
      $RESIDENTE_TIPO_ID = 5;

      if ($existing) {
        if ((int)$existing['tipo_usuario_id'] !== $RESIDENTE_TIPO_ID) {
          $pdo->rollBack();
          json_out(false, ['error' => 'El correo ya está en uso por otro tipo de usuario.']);
        }
        $newUserId = (int)$existing['id'];
      } else {
        // crear password temporal
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $plainPassword = '';
        for ($i = 0; $i < 8; $i++) $plainPassword .= $chars[random_int(0, strlen($chars) - 1)];

        $hash = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmtNewU = $pdo->prepare("
          INSERT INTO users (tipo_usuario_id, name, email, telefono, password_hash, is_active)
          VALUES (:tipo, :name, :email, :tel, :pass, 1)
        ");
        $stmtNewU->execute([
          'tipo' => $RESIDENTE_TIPO_ID,
          'name' => $name,
          'email' => $email,
          'tel' => ($phone !== '' ? $phone : null),
          'pass' => $hash
        ]);
        $newUserId = (int)$pdo->lastInsertId();
      }

      // ligar a residencial
      $stmtLinkRes = $pdo->prepare("
        INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
        VALUES (:uid, :rid, 1)
        ON DUPLICATE KEY UPDATE residencial_id = residencial_id
      ");
      $stmtLinkRes->execute(['uid' => $newUserId, 'rid' => $residencialId]);

      // evitar duplicar la misma unidad al mismo user
      $stmtDupe = $pdo->prepare("SELECT COUNT(*) FROM residentes_unidades WHERE user_id = :uid AND unidad_id = :unid");
      $stmtDupe->execute(['uid' => $newUserId, 'unid' => $unitId]);
      if ((int)$stmtDupe->fetchColumn() > 0) {
        $pdo->rollBack();
        json_out(false, ['error' => 'Este residente ya está asignado a esa unidad.']);
      }

      // asignar unidad
      $stmtLinkUnit = $pdo->prepare("
        INSERT INTO residentes_unidades (user_id, unidad_id, es_titular, activo)
        VALUES (:uid, :unid, :tit, 1)
      ");
      $stmtLinkUnit->execute(['uid' => $newUserId, 'unid' => $unitId, 'tit' => $isTitular]);

      $pdo->commit();

      $msg = 'Residente agregado correctamente.';
      if ($plainPassword) $msg .= " Contraseña temporal: {$plainPassword}";
      json_out(true, ['message' => $msg]);
    }

    // UPDATE (por resid_unid_id)
    if ($action === 'update') {
      $residUnitId = (int)($_POST['id'] ?? $_POST['resid_unid_id'] ?? 0);
      if ($residUnitId <= 0) json_out(false, ['error' => 'ID inválido.']);

      $pdo->beginTransaction();

      // 1) obtener user_id y validar pertenencia
      $stmtFind = $pdo->prepare("
        SELECT ru.user_id
        FROM residentes_unidades ru
        JOIN unidades un ON un.id = ru.unidad_id
        WHERE ru.id = :id AND un.residencial_id = :rid
        LIMIT 1
      ");
      $stmtFind->execute(['id' => $residUnitId, 'rid' => $residencialId]);
      $uid = (int)$stmtFind->fetchColumn();
      if (!$uid) {
        $pdo->rollBack();
        json_out(false, ['error' => 'Residente no encontrado o fuera de tu residencial.']);
      }

      // 2) asegurar email único (excepto el mismo usuario)
      $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1");
      $stmtEmail->execute(['email' => $email, 'id' => $uid]);
      if ($stmtEmail->fetchColumn()) {
        $pdo->rollBack();
        json_out(false, ['error' => 'Ese correo ya está en uso.']);
      }

      // 3) update user
      $stmtUpU = $pdo->prepare("
        UPDATE users
        SET name = :name, email = :email, telefono = :tel
        WHERE id = :id
      ");
      $stmtUpU->execute([
        'name' => $name,
        'email' => $email,
        'tel' => ($phone !== '' ? $phone : null),
        'id' => $uid
      ]);

      // 4) update residentes_unidades (unidad + titular)
      // evitar duplicar user+unidad
      $stmtDupe = $pdo->prepare("
        SELECT COUNT(*)
        FROM residentes_unidades
        WHERE user_id = :uid AND unidad_id = :unid AND id <> :id
      ");
      $stmtDupe->execute(['uid' => $uid, 'unid' => $unitId, 'id' => $residUnitId]);
      if ((int)$stmtDupe->fetchColumn() > 0) {
        $pdo->rollBack();
        json_out(false, ['error' => 'Este residente ya está asignado a esa unidad.']);
      }

      $stmtUpRU = $pdo->prepare("
        UPDATE residentes_unidades ru
        JOIN unidades un ON un.id = ru.unidad_id
        SET ru.unidad_id = :unid,
            ru.es_titular = :tit
        WHERE ru.id = :id AND un.residencial_id = :rid
      ");
      $stmtUpRU->execute(['unid' => $unitId, 'tit' => $isTitular, 'id' => $residUnitId, 'rid' => $residencialId]);

      $pdo->commit();
      json_out(true, ['message' => 'Residente actualizado.']);
    }

    json_out(false, ['error' => 'Acción POST no soportada.']);
  }

  json_out(false, ['error' => 'Método no soportado.']);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
}
