<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['guardia','super_admin']);

function out(bool $ok, array $data = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $session = current_user();
  $uid = (int)($session['id'] ?? 0);

  /* ===============================
     1) Usuario (guardia)
     =============================== */
  $stmtU = $pdo->prepare("
    SELECT id, name, email
    FROM users
    WHERE id = :id
    LIMIT 1
  ");
  $stmtU->execute(['id'=>$uid]);
  $user = $stmtU->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    out(false, ['error'=>'Usuario no encontrado'], 404);
  }

  /* ===============================
     2) Residencial asignado
     =============================== */
  $stmtR = $pdo->prepare("
    SELECT
      r.id,
      r.nombre,
      r.calle,
      r.colonia,
      r.ciudad,
      r.estado
    FROM usuarios_residenciales ur
    JOIN residenciales r ON r.id = ur.residencial_id
    WHERE ur.user_id = :uid
    ORDER BY ur.es_principal DESC, ur.created_at ASC
    LIMIT 1
  ");
  $stmtR->execute(['uid'=>$uid]);
  $res = $stmtR->fetch(PDO::FETCH_ASSOC);

  $header_line = null;
  $direccion = null;

  if ($res) {
    $header_line = 'Residencial: ' . $res['nombre'];

    $direccion = implode(', ', array_filter([
      $res['calle'] ?? '',
      $res['colonia'] ?? '',
      $res['ciudad'] ?? '',
      $res['estado'] ?? '',
    ]));
  }

  /* ===============================
     RESPONSE
     =============================== */
  out(true, [
    'data' => [
      'user' => $user,
      'header_line' => $header_line,
      'direccion' => $direccion,
    ]
  ]);

} catch (Throwable $e) {
  out(false, ['error'=>'Error: '.$e->getMessage()], 500);
}
