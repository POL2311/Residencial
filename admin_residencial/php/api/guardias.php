<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();

/* =============================
   RESIDENCIAL DEL ADMIN
============================= */
$stmtR = $pdo->prepare("
  SELECT residencial_id
  FROM usuarios_residenciales
  WHERE user_id = ?
  ORDER BY es_principal DESC
  LIMIT 1
");
$stmtR->execute([$user['id']]);
$residencialId = (int)$stmtR->fetchColumn();

if (!$residencialId) {
  echo json_encode(['ok'=>false,'error'=>'Sin residencial']);
  exit;
}

/* =============================
   GET → LISTAR GUARDIAS
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $stmt = $pdo->prepare("
    SELECT u.id, u.name, u.email, u.telefono,
           u.is_active,
           IFNULL(u.guardia_en_servicio,0) AS guardia_en_servicio
    FROM usuarios_residenciales ur
    JOIN users u ON u.id = ur.user_id
    JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
    WHERE ur.residencial_id = ?
      AND t.nombre = 'guardia'
    ORDER BY u.name
  ");
  $stmt->execute([$residencialId]);
  echo json_encode(['ok'=>true,'guardias'=>$stmt->fetchAll()]);
  exit;
}

/* =============================
   POST → CREAR GUARDIA
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'create_guardia') {

  $nombre    = trim($_POST['nombre'] ?? '');
  $email     = trim($_POST['email'] ?? '');
  $telefono  = trim($_POST['telefono'] ?? '');
  $password  = $_POST['password'] ?? '';
  $password2 = $_POST['password_confirm'] ?? '';
  $es_activo = isset($_POST['es_activo']) ? 1 : 0;

  if ($nombre === '') {
    echo json_encode(['ok'=>false,'error'=>'El nombre es obligatorio']);
    exit;
  }

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'error'=>'Email inválido']);
    exit;
  }

  if ($password === '' || $password !== $password2 || strlen($password) < 6) {
    echo json_encode(['ok'=>false,'error'=>'Contraseña inválida o no coincide']);
    exit;
  }

  // Email único
  $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
  $stmtCheck->execute([$email]);
  if ($stmtCheck->fetchColumn()) {
    echo json_encode(['ok'=>false,'error'=>'Ya existe un usuario con ese correo']);
    exit;
  }

  try {
    $pdo->beginTransaction();

    // tipo guardia
    $stmtTipo = $pdo->prepare("SELECT id FROM tipos_usuario WHERE nombre = 'guardia' LIMIT 1");
    $stmtTipo->execute();
    $tipoId = (int)$stmtTipo->fetchColumn();

    if (!$tipoId) {
      throw new Exception('Tipo guardia no existe');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmtUser = $pdo->prepare("
      INSERT INTO users
      (tipo_usuario_id, name, email, telefono, password_hash, is_active, guardia_en_servicio)
      VALUES (?,?,?,?,?,?,0)
    ");
    $stmtUser->execute([
      $tipoId,
      $nombre,
      $email,
      $telefono ?: null,
      $hash,
      $es_activo
    ]);

    $guardiaId = (int)$pdo->lastInsertId();

    $stmtUR = $pdo->prepare("
      INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal)
      VALUES (?,?,1)
    ");
    $stmtUR->execute([$guardiaId, $residencialId]);

    $pdo->commit();

    echo json_encode(['ok'=>true,'message'=>'Guardia creado correctamente']);
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    exit;
  }
}

/* =============================
   POST → TOGGLE SERVICIO
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'toggle_servicio') {

  $id = (int)($_POST['guardia_id'] ?? 0);
  $estado = (int)($_POST['nuevo_estado'] ?? -1);

  if ($id <= 0 || !in_array($estado,[0,1],true)) {
    echo json_encode(['ok'=>false,'error'=>'Datos inválidos']);
    exit;
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
  $stmt->execute([$estado,$id,$residencialId]);

  echo json_encode(['ok'=>true,'estado'=>$estado]);
  exit;
}
/* =============================
   POST → ELIMINAR GUARDIA
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete_guardia') {

  $id = (int)($_POST['guardia_id'] ?? 0);

  if ($id <= 0) {
    echo json_encode(['ok'=>false,'error'=>'ID inválido']);
    exit;
  }

  try {
    $pdo->beginTransaction();

    // Verificar que pertenezca al residencial
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

    // Eliminar relación
    $pdo->prepare("DELETE FROM usuarios_residenciales WHERE user_id = ? AND residencial_id = ?")
        ->execute([$id, $residencialId]);

    // Eliminar usuario
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);

    $pdo->commit();

    echo json_encode(['ok'=>true,'message'=>'Guardia eliminado']);
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    exit;
  }
}
/* =============================
   POST → EDITAR GUARDIA
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'edit_guardia') {

  $id       = (int)($_POST['guardia_id'] ?? 0);
  $nombre   = trim($_POST['nombre'] ?? '');
  $email    = trim($_POST['email'] ?? '');
  $telefono = trim($_POST['telefono'] ?? '');

  if ($id <= 0 || $nombre === '' || $email === '') {
    echo json_encode(['ok'=>false,'error'=>'Datos incompletos']);
    exit;
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok'=>false,'error'=>'Email inválido']);
    exit;
  }

  try {
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
      $telefono ?: null,
      $id,
      $residencialId
    ]);

    echo json_encode(['ok'=>true,'message'=>'Guardia actualizado']);
    exit;

  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    exit;
  }
}

echo json_encode(['ok'=>false,'error'=>'Método no permitido']);
