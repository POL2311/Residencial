<?php
// guardia/php/api/paqueteria.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['guardia','super_admin']);

$user = current_user();
$uid = (int)($user['id'] ?? 0);

function json_out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function rid(PDO $pdo, int $uid): int {
  $stmt = $pdo->prepare("
    SELECT r.id
    FROM usuarios_residenciales ur
    JOIN residenciales r ON r.id = ur.residencial_id
    WHERE ur.user_id = :uid
    ORDER BY ur.es_principal DESC, ur.created_at ASC
    LIMIT 1
  ");
  $stmt->execute(['uid' => $uid]);
  return (int)($stmt->fetchColumn() ?: 0);
}

try {
  $residencial_id = rid($pdo, $uid);
  if ($residencial_id <= 0) json_out(false, ['error' => 'Guardia no asociado a residencial.']);

  // GET unidades
  if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'unidades')) {
    $stmt = $pdo->prepare("SELECT id, clave FROM unidades WHERE residencial_id = :rid ORDER BY clave ASC");
    $stmt->execute(['rid' => $residencial_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    json_out(true, ['data' => ['items' => $items]]);
  }

  // GET list
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("
      SELECT p.*, u.clave AS unidad_clave
      FROM paqueteria p
      JOIN unidades u ON u.id = p.unidad_id
      WHERE p.residencial_id = :rid
      ORDER BY p.created_at DESC
      LIMIT 100
    ");
    $stmt->execute(['rid' => $residencial_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    json_out(true, ['data' => ['items' => $items]]);
  }

  // POST create
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action !== 'create') json_out(false, ['error' => 'Acción inválida.']);

    $unidad_id = (int)($_POST['unidad_id'] ?? 0);
    $empresa = trim((string)($_POST['empresa'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $codigo_rastreo = trim((string)($_POST['codigo_rastreo'] ?? ''));
    $notas = trim((string)($_POST['notas'] ?? ''));

    if ($unidad_id <= 0) json_out(false, ['error' => 'Debes seleccionar una unidad.']);
    if ($descripcion === '') json_out(false, ['error' => 'La descripción del paquete es obligatoria.']);

    $stmt = $pdo->prepare("
      INSERT INTO paqueteria (
        residencial_id, unidad_id, residente_id, guardia_id,
        empresa, descripcion, codigo_rastreo, estado, notas
      ) VALUES (
        :rid, :unidad_id, NULL, :guardia_id,
        :empresa, :descripcion, :codigo_rastreo, 'registrado', :notas
      )
    ");
    $stmt->execute([
      'rid' => $residencial_id,
      'unidad_id' => $unidad_id,
      'guardia_id' => $uid,
      'empresa' => $empresa !== '' ? $empresa : null,
      'descripcion' => $descripcion,
      'codigo_rastreo' => $codigo_rastreo !== '' ? $codigo_rastreo : null,
      'notas' => $notas !== '' ? $notas : null,
    ]);

    json_out(true, ['data' => ['created' => true]]);
  }

  json_out(false, ['error' => 'Método no soportado.']);

} catch (Throwable $e) {
  json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
}
