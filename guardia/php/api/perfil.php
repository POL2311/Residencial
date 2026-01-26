<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['guardia','super_admin']);

$user = current_user();
$uid  = (int)$user['id'];

function out(bool $ok, array $extra = []) {
  echo json_encode(array_merge(['ok'=>$ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
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
  out(true, [
    'data' => [
      'user' => [
        'name'     => $user['name'] ?? '',
        'email'    => $user['email'] ?? '',
        'telefono' => $user['telefono'] ?? '',
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
  $_SESSION['user']['name'] = $name;

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
  $_SESSION['user']['telefono'] = $telefono;

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

  $pdo->prepare("UPDATE users SET email=? WHERE id=?")->execute([$email, $uid]);
  $_SESSION['user']['email'] = $email;

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
