<?php
// /residente/php/api/pagos.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';

require_login();
require_role(['residente']);

$uid = (int)(current_user()['id'] ?? 0);

function json_out(bool $ok, array $extra = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function get_context(PDO $pdo, int $uid): array {
  $status = resolve_resident_context($pdo, $uid, false);
  if (!$status['ok']) {
    json_out(false, [
      'error' => $status['error'] ?? 'No se pudo resolver el contexto del residente.',
      'missing' => $status['missing'] ?? [],
      'ctx' => $status['ctx'] ?? null,
    ], (int)($status['http_status'] ?? 403));
  }
  return $status['ctx'];
}

try {
  $ctx = get_context($pdo, $uid);
  $rid = (int)($ctx['residencial_id'] ?? 0);
  $unidadId = (int)($ctx['unidad_id'] ?? 0);

  $stmt = $pdo->prepare("
    SELECT id, monto, fecha, metodo, concepto, activo, created_at, updated_at
    FROM pagos
    WHERE user_id = :uid
      AND (:rid = 0 OR residencial_id = :rid)
      AND (:unidad = 0 OR unidad_id = :unidad)
    ORDER BY fecha DESC, id DESC
    LIMIT 200
  ");
  $stmt->execute([
    'uid' => $uid,
    'rid' => $rid,
    'unidad' => $unidadId,
  ]);
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $summary = [
    'total' => 0.0,
    'count' => count($items),
    'ultimo_pago' => $items[0]['fecha'] ?? null,
  ];
  foreach ($items as $item) {
    $summary['total'] += (float)($item['monto'] ?? 0);
  }

  json_out(true, [
    'data' => [
      'ctx' => $ctx,
      'summary' => $summary,
      'items' => $items,
    ]
  ]);
} catch (Throwable $e) {
  json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}
