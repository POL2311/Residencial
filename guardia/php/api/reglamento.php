<?php
// guardia/php/api/reglamento.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/service_profile.php';

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
  service_profile_schema_ensure($pdo);
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
  if (($user['role'] ?? '') !== 'super_admin') {
    service_profile_api_require_module($pdo, $residencialId, 'guardia', 'reglamento', 'El reglamento no está habilitado para este cliente.');
  }

  // 2️⃣ Traer reglamento más reciente del residencial.
  // En el esquema actual no existe la columna `es_publico`,
  // así que guardia consume el documento oficial más reciente.
  $stmt = $pdo->prepare("
    SELECT id, titulo, contenido, version_label, updated_at
    FROM reglamentos_residenciales
    WHERE residencial_id = :rid
    ORDER BY id DESC, updated_at DESC
    LIMIT 1
  ");
  $stmt->execute(['rid'=>$residencialId]);
  $reglamento = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$reglamento) {
    out(true, ['data'=>null, 'reglamento'=>null]);
  }

  out(true, ['data'=>$reglamento, 'reglamento'=>$reglamento]);

} catch (Throwable $e) {
  out(false, ['error'=>'Error: '.$e->getMessage()], 500);
}
