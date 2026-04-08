<?php
// /residente/php/api/reglamento.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['residente']);

$uid = (int)(current_user()['id'] ?? 0);

function json_out(bool $ok, array $extra = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmtCtx = $pdo->prepare("
    SELECT r.id AS residencial_id, r.nombre AS residencial_nombre
    FROM usuarios_residenciales ur
    JOIN residenciales r ON r.id = ur.residencial_id
    WHERE ur.user_id = :uid
    ORDER BY ur.es_principal DESC, ur.created_at ASC
    LIMIT 1
  ");
  $stmtCtx->execute(['uid' => $uid]);
  $ctx = $stmtCtx->fetch(PDO::FETCH_ASSOC);
  if (!$ctx) json_out(false, ['error' => 'No se encontró el residencial del residente.'], 404);

  $stmt = $pdo->prepare("
    SELECT id, titulo, contenido, version_label, created_at, updated_at
    FROM reglamentos_residenciales
    WHERE residencial_id = :rid
    ORDER BY updated_at DESC, id DESC
    LIMIT 1
  ");
  $stmt->execute(['rid' => (int)$ctx['residencial_id']]);
  $item = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$item) json_out(false, ['error' => 'No hay reglamento disponible.'], 404);

  json_out(true, ['data' => ['ctx' => $ctx, 'item' => $item]]);
} catch (Throwable $e) {
  json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}
