<?php
// /residente/php/api/paqueteria.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['residente']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  app_require_write_guard();
}

$user = current_user();
$uid  = (int)($user['id'] ?? 0);

function json_out(bool $ok, array $extra = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function clean_string($value, int $max = 255): string {
  $value = trim((string)($value ?? ''));
  if (mb_strlen($value) > $max) {
    $value = mb_substr($value, 0, $max);
  }
  return $value;
}

function get_context(PDO $pdo, int $uid): array {
  $status = resolve_resident_context($pdo, $uid, true);
  if (!$status['ok']) {
    json_out(false, [
      'error' => $status['error'] ?? 'No se pudo resolver el contexto del residente.',
      'missing' => $status['missing'] ?? [],
      'ctx' => $status['ctx'] ?? null,
    ], (int)($status['http_status'] ?? 403));
  }
  return $status['ctx'];
}

function list_items(PDO $pdo, int $residencialId, int $unidadId, int $residenteId): array {
  $stmt = $pdo->prepare("
    SELECT
      p.*,
      rg.name AS residente_nombre,
      gg.name AS guardia_nombre
    FROM paqueteria p
    LEFT JOIN users rg ON rg.id = p.residente_id
    LEFT JOIN users gg ON gg.id = p.guardia_id
    WHERE p.residencial_id = :rid
      AND p.unidad_id = :uid
      AND p.residente_id = :residente_id
    ORDER BY p.created_at DESC, p.id DESC
    LIMIT 300
  ");
  $stmt->execute([
    'rid' => $residencialId,
    'uid' => $unidadId,
    'residente_id' => $residenteId,
  ]);

  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

try {
  service_profile_schema_ensure($pdo);
  $ctx = get_context($pdo, $uid);
  $residencialId = (int)$ctx['residencial_id'];
  $unidadId = (int)$ctx['unidad_id'];
  service_profile_api_require_module($pdo, $residencialId, 'residente', 'paqueteria', 'La paquetería no está habilitada para este cliente.');

  if ($action === 'list') {
    json_out(true, [
      'data' => [
        'ctx' => $ctx,
        'caseta_phone' => $ctx['caseta_phone'] ?? null,
        'items' => list_items($pdo, $residencialId, $unidadId, $uid),
      ]
    ]);
  }

  if ($method === 'POST' && $action === 'confirm_entregado') {
    $paqId = (int)($_POST['paq_id'] ?? 0);
    if ($paqId <= 0) json_out(false, ['error' => 'Paquete inválido.'], 422);

    $chk = $pdo->prepare("
      SELECT id, estado
      FROM paqueteria
      WHERE id = :id
        AND residencial_id = :rid
        AND unidad_id = :uid
        AND residente_id = :residente_id
      LIMIT 1
    ");
    $chk->execute([
      'id' => $paqId,
      'rid' => $residencialId,
      'uid' => $unidadId,
      'residente_id' => $uid,
    ]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.'], 404);
    if ((string)($row['estado'] ?? '') !== 'registrado') {
      json_out(false, ['error' => 'Solo puedes confirmar paquetes pendientes.'], 422);
    }

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET estado = 'entregado',
          residente_id = COALESCE(residente_id, :set_residente_id),
          updated_at = NOW()
      WHERE id = :id
        AND residencial_id = :rid
        AND unidad_id = :uid
        AND residente_id = :where_residente_id
    ");
    $upd->execute([
      'set_residente_id' => $uid,
      'id' => $paqId,
      'rid' => $residencialId,
      'uid' => $unidadId,
      'where_residente_id' => $uid,
    ]);

    json_out(true, ['message' => 'Paquete confirmado como entregado.']);
  }

  if ($method === 'POST' && $action === 'confirm_devuelto') {
    $paqId = (int)($_POST['paq_id'] ?? 0);
    $motivo = clean_string($_POST['motivo'] ?? '', 255);
    if ($paqId <= 0) json_out(false, ['error' => 'Paquete inválido.'], 422);
    if ($motivo === '') json_out(false, ['error' => 'Indica el motivo de la devolución.'], 422);

    $chk = $pdo->prepare("
      SELECT id, estado
      FROM paqueteria
      WHERE id = :id
        AND residencial_id = :rid
        AND unidad_id = :uid
        AND residente_id = :residente_id
      LIMIT 1
    ");
    $chk->execute([
      'id' => $paqId,
      'rid' => $residencialId,
      'uid' => $unidadId,
      'residente_id' => $uid,
    ]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.'], 404);
    if ((string)($row['estado'] ?? '') !== 'registrado') {
      json_out(false, ['error' => 'Solo puedes devolver paquetes pendientes.'], 422);
    }

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET estado = 'devuelto',
          residente_id = COALESCE(residente_id, :set_residente_id),
          notas = TRIM(CONCAT(COALESCE(notas, ''), CASE WHEN COALESCE(notas, '') = '' THEN '' ELSE '\n' END, :motivo)),
          updated_at = NOW()
      WHERE id = :id
        AND residencial_id = :rid
        AND unidad_id = :uid
        AND residente_id = :where_residente_id
    ");
    $upd->execute([
      'set_residente_id' => $uid,
      'motivo' => 'Devuelto por residente: ' . $motivo,
      'id' => $paqId,
      'rid' => $residencialId,
      'uid' => $unidadId,
      'where_residente_id' => $uid,
    ]);

    json_out(true, ['message' => 'Paquete marcado como devuelto.']);
  }

  json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos procesar la paquetería.');
}
