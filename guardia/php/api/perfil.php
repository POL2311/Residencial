<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['guardia','super_admin']);

$user = current_user();
$uid  = (int)$user['id'];
$residencialId = service_profile_resolve_residencial_id_for_user($pdo, $uid);
if ($residencialId > 0 && ($user['role'] ?? '') !== 'super_admin') {
  service_profile_api_require_module($pdo, $residencialId, 'guardia', 'perfil', 'El perfil no está habilitado para este cliente.');
}

function out(bool $ok, array $extra = []) {
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function refresh_session_user(array $newUser): void {
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

  if (array_key_exists('name', $newUser)) {
    $_SESSION['user_name'] = (string)$newUser['name'];
  }
  if (array_key_exists('id', $newUser)) {
    $_SESSION['user_id'] = (int)$newUser['id'];
  }

  if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    foreach (['name','email','telefono'] as $k) {
      if (array_key_exists($k, $newUser)) $_SESSION['user'][$k] = $newUser[$k];
    }
  }
  if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
    foreach (['name','email','telefono'] as $k) {
      if (array_key_exists($k, $newUser)) $_SESSION['auth_user'][$k] = $newUser[$k];
    }
  }
}

function fetch_user(PDO $pdo, int $uid): array {
  $stmt = $pdo->prepare("
    SELECT id, name, email, telefono
    FROM users
    WHERE id = ?
    LIMIT 1
  ");
  $stmt->execute([$uid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$row) {
    out(false, ['error' => 'Usuario no encontrado']);
  }

  return $row;
}

function verify_password(PDO $pdo, int $uid, string $plain): bool {
  $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
  $stmt->execute([$uid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row || empty($row['password_hash'])) return false;
  return password_verify($plain, $row['password_hash']);
}

$action = $_POST['action'] ?? 'get';

/* =======================
   GET PERFIL
======================= */
if ($action === 'get') {
  $dbUser = fetch_user($pdo, $uid);

  out(true, [
    'data' => [
      'user' => [
        'name'     => $dbUser['name'] ?? '',
        'email'    => $dbUser['email'] ?? '',
        'telefono' => $dbUser['telefono'] ?? '',
      ],
      // esto viene del contexto, no editable
      'direccion' => $user['direccion'] ?? '—'
    ]
  ]);
}

/* =======================
   UPDATE NAME
======================= */
if ($action === 'update_name') {
  $name = trim($_POST['name'] ?? '');
  if ($name === '') out(false, ['error'=>'Nombre requerido']);

  $pdo->prepare("UPDATE users SET name=? WHERE id=?")->execute([$name, $uid]);
  refresh_session_user(['id' => $uid, 'name' => $name]);

  out(true, ['message'=>'Nombre actualizado']);
}

/* =======================
   UPDATE PHONE
======================= */
if ($action === 'update_phone') {
  $telefono = trim($_POST['telefono'] ?? '');
  $pwd = $_POST['current_password'] ?? '';

  if ($telefono === '') out(false, ['error'=>'Teléfono requerido']);
  if ($pwd === '') out(false, ['error'=>'Contraseña requerida']);

  if (!verify_password($pdo, $uid, $pwd)) {
    out(false, ['error'=>'Contraseña incorrecta']);
  }

  $pdo->prepare("UPDATE users SET telefono=? WHERE id=?")->execute([$telefono, $uid]);
  refresh_session_user(['telefono' => $telefono]);

  out(true, ['message'=>'Teléfono actualizado']);
}

/* =======================
   UPDATE EMAIL
======================= */
if ($action === 'update_email') {
  $email = trim($_POST['email'] ?? '');
  $pwd   = $_POST['current_password'] ?? '';

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    out(false, ['error'=>'Correo inválido']);
  }
  if ($pwd === '') out(false, ['error'=>'Contraseña requerida']);

  if (!verify_password($pdo, $uid, $pwd)) {
    out(false, ['error'=>'Contraseña incorrecta']);
  }

  $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
  $stmtEmail->execute([$email, $uid]);
  if ($stmtEmail->fetchColumn()) {
    out(false, ['error' => 'Ese correo ya está en uso.']);
  }

  $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email, $uid]);
  refresh_session_user(['email' => $email]);

  out(true, ['message'=>'Correo actualizado']);
}

/* =======================
   CHANGE PASSWORD
======================= */
if ($action === 'change_password') {
  $cur = $_POST['current_password'] ?? '';
  $n1  = $_POST['new_password'] ?? '';
  $n2  = $_POST['new_password_confirm'] ?? '';

  if ($cur==='' || $n1==='' || $n2==='') {
    out(false, ['error'=>'Completa todos los campos']);
  }
  if ($n1 !== $n2) {
    out(false, ['error'=>'La confirmación no coincide']);
  }
  if (strlen($n1) < 6) {
    out(false, ['error'=>'La contraseña debe tener al menos 6 caracteres']);
  }

  if (!verify_password($pdo, $uid, $cur)) {
    out(false, ['error'=>'Contraseña actual incorrecta']);
  }

  $hash = password_hash($n1, PASSWORD_DEFAULT);
  $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);

  out(true, ['message'=>'Contraseña actualizada']);
}

out(false, ['error'=>'Acción no válida']);
