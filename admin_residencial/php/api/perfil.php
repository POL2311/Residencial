<?php
// /admin_residencial/php/api/perfil.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();
$uid  = (int)$user['id'];
$residencialId = service_profile_resolve_residencial_id_for_user($pdo, $uid);
if ($residencialId > 0) {
  service_profile_api_require_module($pdo, $residencialId, 'admin_residencial', 'perfil', 'El perfil no está habilitado para este cliente.');
}

function json_out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? 'get');

if ($action === 'get') {
  try {
    // Residencial principal
    $stmt = $pdo->prepare("
      SELECT r.*
      FROM usuarios_residenciales ur
      JOIN residenciales r ON r.id = ur.residencial_id
      WHERE ur.user_id = :uid
      ORDER BY ur.es_principal DESC
      LIMIT 1
    ");
    $stmt->execute(['uid' => $uid]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$res) json_out(false, ['error' => 'No tienes residencial asignado.']);

    // Stats
    $stats = [];

    $stats['residentes'] = (int)$pdo->query("
      SELECT COUNT(*)
      FROM usuarios_residenciales ur
      JOIN users u ON u.id = ur.user_id
      JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
      WHERE ur.residencial_id = {$res['id']} AND t.nombre = 'residente'
    ")->fetchColumn();

    $stats['guardias'] = (int)$pdo->query("
      SELECT COUNT(*)
      FROM usuarios_residenciales ur
      JOIN users u ON u.id = ur.user_id
      JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
      WHERE ur.residencial_id = {$res['id']} AND t.nombre = 'guardia'
    ")->fetchColumn();

    json_out(true, [
      'data' => [
        'user' => [
          'id' => (int)$uid,
          'name' => (string)($user['name'] ?? ''),
          'email' => (string)($user['email'] ?? ''),
          'telefono' => (string)($user['telefono'] ?? ''),
        ],
        'residencial' => $res,
        'stats' => $stats
      ]
    ]);
  } catch (Throwable $e) {
    json_out(false, ['error' => $e->getMessage()]);
  }
}

if ($action === 'update_user') {
  try {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $telefono = trim((string)($_POST['telefono'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($name === '') {
      json_out(false, ['error' => 'El nombre es obligatorio.']);
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      json_out(false, ['error' => 'Email inválido.']);
    }
    if ($password !== '' || $passwordConfirm !== '') {
      if (strlen($password) < 8) {
        json_out(false, ['error' => 'La contraseña debe tener al menos 8 caracteres.']);
      }
      if ($password !== $passwordConfirm) {
        json_out(false, ['error' => 'La confirmación de contraseña no coincide.']);
      }
    }

    $stmtEmail = $pdo->prepare("
      SELECT id
      FROM users
      WHERE email = :email AND id <> :id
      LIMIT 1
    ");
    $stmtEmail->execute([
      'email' => $email,
      'id' => $uid,
    ]);
    if ($stmtEmail->fetchColumn()) {
      json_out(false, ['error' => 'Ese email ya está en uso por otro usuario.']);
    }

    $sql = "UPDATE users SET name = :name, email = :email, telefono = :telefono";
    $params = [
      'name' => $name,
      'email' => $email,
      'telefono' => ($telefono !== '' ? $telefono : null),
      'id' => $uid,
    ];
    if ($password !== '') {
      $sql .= ", password_hash = :password_hash";
      $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }
    $sql .= " WHERE id = :id LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_out(true, ['data' => ['message' => 'Perfil actualizado correctamente.']]);
  } catch (Throwable $e) {
    json_out(false, ['error' => $e->getMessage()]);
  }
}

json_out(false, ['error' => 'Acción no soportada']);
