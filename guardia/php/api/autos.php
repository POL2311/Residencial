<?php
// guardia/php/api/autos.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['guardia', 'super_admin']);

$user = current_user();
$uid  = (int)($user['id'] ?? 0);

function json_out(bool $ok, array $extra = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}
function clean(string $v): string {
  return trim((string)preg_replace('/\s+/', ' ', $v));
}
function norm_placas(string $p): string {
  $p = strtoupper(clean($p));
  $p = str_replace([' ', '-', '_'], '', $p);
  return $p;
}
function getGuardResidencialId(PDO $pdo, int $uid): int {
  $stmt = $pdo->prepare("
    SELECT ur.residencial_id
    FROM usuarios_residenciales ur
    WHERE ur.user_id = :uid
    ORDER BY ur.es_principal DESC, ur.created_at ASC
    LIMIT 1
  ");
  $stmt->execute(['uid' => $uid]);
  $rid = $stmt->fetchColumn();
  return (int)($rid ?: 0);
}

$residencial_id = getGuardResidencialId($pdo, $uid);
if ($residencial_id <= 0) json_out(false, ['error' => 'Tu usuario guardia no está asignado a un residencial.'], 403);

function listItems(PDO $pdo, int $rid, array $filters = []): array {
  $where = ["a.residencial_id = :rid", "a.activo = 1"];
  $p = ['rid' => $rid];

  $placas = clean((string)($filters['placas'] ?? ''));
  $modelo = clean((string)($filters['modelo'] ?? ''));
  $color  = clean((string)($filters['color'] ?? ''));

  if ($placas !== '') {
    $np = norm_placas($placas);
    $where[] = "REPLACE(REPLACE(UPPER(a.placas), '-', ''), ' ', '') LIKE :placas";
    $p['placas'] = '%' . $np . '%';
  }
  if ($modelo !== '') {
    $where[] = "a.modelo LIKE :modelo";
    $p['modelo'] = '%' . $modelo . '%';
  }
  if ($color !== '') {
    $where[] = "a.color LIKE :color";
    $p['color'] = '%' . $color . '%';
  }

  $sql = "
    SELECT
      a.*,
      u.clave AS unidad_clave,
      owner.name AS propietario_nombre
    FROM autos a
    LEFT JOIN unidades u
      ON u.id = a.unidad_id AND u.residencial_id = a.residencial_id
    LEFT JOIN users owner
      ON owner.id = a.propietario_user_id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT 200
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($p);
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

/**
 * LIST (GET)
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && $action === 'list') {
  try {
    $items = listItems($pdo, $residencial_id, [
      'placas' => $_GET['placas'] ?? '',
      'modelo' => $_GET['modelo'] ?? '',
      'color'  => $_GET['color'] ?? '',
    ]);
    json_out(true, ['data' => ['items' => $items]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
  }
}

/**
 * CREATE (POST)
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action === 'create') {
  $placas = norm_placas((string)($_POST['placas'] ?? ''));
  $modelo = clean((string)($_POST['modelo'] ?? ''));
  $color  = clean((string)($_POST['color'] ?? ''));
  $notas  = clean((string)($_POST['notas'] ?? ''));

  $unidad_id = ($_POST['unidad_id'] ?? '') !== '' ? (int)$_POST['unidad_id'] : null;
  $prop_id   = ($_POST['propietario_user_id'] ?? '') !== '' ? (int)$_POST['propietario_user_id'] : null;

  if ($placas === '') json_out(false, ['error' => 'Las placas son obligatorias.'], 422);

  try {
    $chk = $pdo->prepare("
      SELECT 1
      FROM autos
      WHERE residencial_id = :rid
        AND activo = 1
        AND REPLACE(REPLACE(UPPER(placas), '-', ''), ' ', '') = :p
      LIMIT 1
    ");
    $chk->execute(['rid' => $residencial_id, 'p' => $placas]);
    if ($chk->fetchColumn()) json_out(false, ['error' => 'Estas placas ya están registradas en este residencial.'], 409);

    if ($unidad_id !== null) {
      $vu = $pdo->prepare("SELECT 1 FROM unidades WHERE id=:id AND residencial_id=:rid AND activo=1 LIMIT 1");
      $vu->execute(['id' => $unidad_id, 'rid' => $residencial_id]);
      if (!$vu->fetchColumn()) json_out(false, ['error' => 'Unidad inválida para este residencial.'], 422);
    }

    $stmt = $pdo->prepare("
      INSERT INTO autos (residencial_id, unidad_id, propietario_user_id, placas, modelo, color, notas, activo)
      VALUES (:rid, :unidad_id, :prop_id, :placas, :modelo, :color, :notas, 1)
    ");
    $stmt->execute([
      'rid' => $residencial_id,
      'unidad_id' => $unidad_id,
      'prop_id' => $prop_id,
      'placas' => $placas,
      'modelo' => ($modelo !== '' ? $modelo : null),
      'color'  => ($color !== '' ? $color : null),
      'notas'  => ($notas !== '' ? $notas : null),
    ]);

    $items = listItems($pdo, $residencial_id);
    json_out(true, ['message' => 'Auto agregado.', 'data' => ['items' => $items]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
  }
}

/**
 * UPDATE (POST)
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action === 'update') {
  $auto_id = (int)($_POST['auto_id'] ?? 0);
  if ($auto_id <= 0) json_out(false, ['error' => 'Auto inválido.'], 422);

  $placas = norm_placas((string)($_POST['placas'] ?? ''));
  $modelo = clean((string)($_POST['modelo'] ?? ''));
  $color  = clean((string)($_POST['color'] ?? ''));
  $notas  = clean((string)($_POST['notas'] ?? ''));

  $unidad_id = array_key_exists('unidad_id', $_POST) ? (($_POST['unidad_id'] === '' ? null : (int)$_POST['unidad_id'])) : null;
  $prop_id   = array_key_exists('propietario_user_id', $_POST) ? (($_POST['propietario_user_id'] === '' ? null : (int)$_POST['propietario_user_id'])) : null;

  if ($placas === '') json_out(false, ['error' => 'Las placas son obligatorias.'], 422);

  try {
    $st = $pdo->prepare("SELECT * FROM autos WHERE id=:id AND residencial_id=:rid LIMIT 1");
    $st->execute(['id' => $auto_id, 'rid' => $residencial_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'Auto no encontrado.'], 404);

    $currentP = norm_placas((string)($row['placas'] ?? ''));
    if ($placas !== $currentP) {
      $chk = $pdo->prepare("
        SELECT 1
        FROM autos
        WHERE residencial_id = :rid
          AND activo = 1
          AND id <> :id
          AND REPLACE(REPLACE(UPPER(placas), '-', ''), ' ', '') = :p
        LIMIT 1
      ");
      $chk->execute(['rid' => $residencial_id, 'id' => $auto_id, 'p' => $placas]);
      if ($chk->fetchColumn()) json_out(false, ['error' => 'Estas placas ya están registradas en este residencial.'], 409);
    }

    if (array_key_exists('unidad_id', $_POST) && $unidad_id !== null) {
      $vu = $pdo->prepare("SELECT 1 FROM unidades WHERE id=:id AND residencial_id=:rid AND activo=1 LIMIT 1");
      $vu->execute(['id' => $unidad_id, 'rid' => $residencial_id]);
      if (!$vu->fetchColumn()) json_out(false, ['error' => 'Unidad inválida para este residencial.'], 422);
    }

    $set = [
      "placas = :placas",
      "modelo = :modelo",
      "color = :color",
      "notas = :notas",
      "updated_at = NOW()"
    ];
    $upd = [
      'placas' => $placas,
      'modelo' => ($modelo !== '' ? $modelo : null),
      'color'  => ($color !== '' ? $color : null),
      'notas'  => ($notas !== '' ? $notas : null),
      'id' => $auto_id,
      'rid' => $residencial_id,
    ];

    if (array_key_exists('unidad_id', $_POST)) {
      $set[] = "unidad_id = :unidad_id";
      $upd['unidad_id'] = $unidad_id;
    }
    if (array_key_exists('propietario_user_id', $_POST)) {
      $set[] = "propietario_user_id = :prop_id";
      $upd['prop_id'] = $prop_id;
    }

    $stmt = $pdo->prepare("UPDATE autos SET " . implode(', ', $set) . " WHERE id=:id AND residencial_id=:rid");
    $stmt->execute($upd);

    $items = listItems($pdo, $residencial_id);
    json_out(true, ['message' => 'Auto actualizado.', 'data' => ['items' => $items]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
  }
}

/**
 * DELETE (SOFT)
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action === 'delete') {
  $auto_id = (int)($_POST['auto_id'] ?? 0);
  if ($auto_id <= 0) json_out(false, ['error' => 'Auto inválido.'], 422);

  try {
    $stmt = $pdo->prepare("
      UPDATE autos
      SET activo = 0, updated_at = NOW()
      WHERE id = :id AND residencial_id = :rid
    ");
    $stmt->execute(['id' => $auto_id, 'rid' => $residencial_id]);

    $items = listItems($pdo, $residencial_id);
    json_out(true, ['message' => 'Auto desactivado.', 'data' => ['items' => $items]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
  }
}

json_out(false, ['error' => 'Acción no soportada.'], 400);