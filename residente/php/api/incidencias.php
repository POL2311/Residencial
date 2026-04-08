<?php
// /residente/php/api/incidencias.php
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
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

function json_out(bool $ok, array $extra = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function clean_string($value, int $max = 255): string {
  $value = trim((string)($value ?? ''));
  return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
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

try {
  $ctx = get_context($pdo, $uid);
  $rid = (int)$ctx['residencial_id'];
  $unidadId = (int)$ctx['unidad_id'];

  if ($action === 'list') {
    $stmt = $pdo->prepare("
      SELECT i.*, g.name AS guardia_nombre
      FROM incidencias i
      LEFT JOIN users g ON g.id = i.guardia_id
      WHERE i.residencial_id = :rid
        AND i.unidad_id = :unidad
        AND i.residente_id = :uid
      ORDER BY i.updated_at DESC, i.id DESC
      LIMIT 200
    ");
    $stmt->execute([
      'rid' => $rid,
      'unidad' => $unidadId,
      'uid' => $uid,
    ]);
    json_out(true, [
      'data' => [
        'ctx' => $ctx,
        'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
      ]
    ]);
  }

  if ($action === 'create') {
    $tipo = clean_string($_POST['tipo'] ?? 'seguridad', 40);
    $titulo = clean_string($_POST['titulo'] ?? '', 150);
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $prioridad = clean_string($_POST['prioridad'] ?? 'media', 20);

    if (!in_array($tipo, ['seguridad', 'ruido', 'mantenimiento', 'otros'], true)) $tipo = 'seguridad';
    if (!in_array($prioridad, ['baja', 'media', 'alta'], true)) $prioridad = 'media';
    if ($titulo === '') json_out(false, ['error' => 'El titulo es obligatorio.'], 422);
    if ($descripcion === '') json_out(false, ['error' => 'La descripcion es obligatoria.'], 422);

    $stmt = $pdo->prepare("
      INSERT INTO incidencias (
        residencial_id, unidad_id, residente_id, guardia_id,
        tipo, titulo, descripcion, prioridad, estado, created_at, updated_at
      ) VALUES (
        :rid, :unidad, :uid, NULL,
        :tipo, :titulo, :descripcion, :prioridad, 'abierta', NOW(), NOW()
      )
    ");
    $stmt->execute([
      'rid' => $rid,
      'unidad' => $unidadId,
      'uid' => $uid,
      'tipo' => $tipo,
      'titulo' => $titulo,
      'descripcion' => $descripcion,
      'prioridad' => $prioridad,
    ]);

    json_out(true, ['message' => 'Incidencia registrada correctamente.']);
  }

  json_out(false, ['error' => 'Accion no soportada.'], 400);
} catch (Throwable $e) {
  json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}
