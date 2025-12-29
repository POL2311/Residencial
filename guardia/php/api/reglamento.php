<?php
// guardia/php/api/reglamento.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';

require_login();
require_role(['guardia','super_admin']);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(bool $ok, array $payload = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok'=>$ok], $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

$user = current_user();
$uid  = (int)($user['id'] ?? 0);

try {
  // 1️⃣ Obtener residencial del guardia
  $stmt = $pdo->prepare("
    SELECT residencial_id
    FROM usuarios_residenciales
    WHERE user_id = :uid
    ORDER BY es_principal DESC, created_at ASC
    LIMIT 1
  ");
  $stmt->execute(['uid'=>$uid]);
  $residencialId = (int)($stmt->fetchColumn() ?: 0);

  if ($residencialId <= 0) {
    out(false, ['error'=>'Guardia sin residencial asignado'], 403);
  }

  // 2️⃣ Traer reglamento público más reciente
  $stmt = $pdo->prepare("
    SELECT titulo, contenido, version_label, updated_at
    FROM reglamentos_residenciales
    WHERE residencial_id = :rid
      AND es_publico = 1
    ORDER BY updated_at DESC
    LIMIT 1
  ");
  $stmt->execute(['rid'=>$residencialId]);
  $reglamento = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$reglamento) {
    out(true, ['data'=>null]);
  }

  out(true, ['data'=>$reglamento]);

} catch (Throwable $e) {
  out(false, ['error'=>'Error: '.$e->getMessage()], 500);
}
