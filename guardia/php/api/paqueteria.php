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
require_role(['guardia', 'super_admin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  app_require_write_guard();
}

$user = current_user();
$uid = (int)($user['id'] ?? 0);

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

function residencial_id_for_guard(PDO $pdo, int $uid): int {
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

function validate_unit(PDO $pdo, int $unidadId, int $residencialId): ?array {
  $stmt = $pdo->prepare("
    SELECT id, clave
    FROM unidades
    WHERE id = :id
      AND residencial_id = :rid
      AND activo = 1
    LIMIT 1
  ");
  $stmt->execute([
    'id' => $unidadId,
    'rid' => $residencialId,
  ]);

  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function residentes_for_unit(PDO $pdo, int $unidadId, int $residencialId): array {
  $stmt = $pdo->prepare("
    SELECT
      ru.user_id AS id,
      us.name,
      us.email,
      ru.es_titular
    FROM residentes_unidades ru
    JOIN unidades u ON u.id = ru.unidad_id
    JOIN users us ON us.id = ru.user_id
    WHERE ru.unidad_id = :unidad_id
      AND ru.activo = 1
      AND u.residencial_id = :rid
    ORDER BY ru.es_titular DESC, us.name ASC
  ");
  $stmt->execute([
    'unidad_id' => $unidadId,
    'rid' => $residencialId,
  ]);

  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function residente_in_unit(PDO $pdo, int $residenteId, int $unidadId, int $residencialId): ?array {
  $stmt = $pdo->prepare("
    SELECT
      ru.user_id AS id,
      us.name,
      us.email
    FROM residentes_unidades ru
    JOIN unidades u ON u.id = ru.unidad_id
    JOIN users us ON us.id = ru.user_id
    WHERE ru.user_id = :residente_id
      AND ru.unidad_id = :unidad_id
      AND ru.activo = 1
      AND u.residencial_id = :rid
    LIMIT 1
  ");
  $stmt->execute([
    'residente_id' => $residenteId,
    'unidad_id' => $unidadId,
    'rid' => $residencialId,
  ]);

  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function list_items(PDO $pdo, int $residencialId): array {
  $stmt = $pdo->prepare("
    SELECT
      p.*,
      u.clave AS unidad_clave,
      rg.name AS residente_nombre,
      gg.name AS guardia_nombre
    FROM paqueteria p
    JOIN unidades u
      ON u.id = p.unidad_id
     AND u.residencial_id = p.residencial_id
    LEFT JOIN users rg
      ON rg.id = p.residente_id
    LEFT JOIN users gg
      ON gg.id = p.guardia_id
    WHERE p.residencial_id = :rid
    ORDER BY p.created_at DESC, p.id DESC
    LIMIT 300
  ");
  $stmt->execute(['rid' => $residencialId]);
  return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$residencialId = residencial_id_for_guard($pdo, $uid);
if ($residencialId <= 0) {
  json_out(false, ['error' => 'Guardia no asociado a residencial.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
  if ($method === 'GET' && $action === 'unidades') {
    $stmt = $pdo->prepare("
      SELECT
        u.id,
        u.clave,
        owner.name AS residente_nombre
      FROM unidades u
      LEFT JOIN residentes_unidades ru
        ON ru.unidad_id = u.id
       AND ru.activo = 1
       AND ru.es_titular = 1
      LEFT JOIN users owner
        ON owner.id = ru.user_id
      WHERE u.residencial_id = :rid
        AND u.activo = 1
      ORDER BY u.clave ASC
    ");
    $stmt->execute(['rid' => $residencialId]);
    json_out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
  }

  if ($method === 'GET' && $action === 'residentes') {
    $unidadId = (int)($_GET['unidad_id'] ?? 0);
    if ($unidadId <= 0) {
      json_out(false, ['error' => 'Unidad inválida.'], 422);
    }

    $unidad = validate_unit($pdo, $unidadId, $residencialId);
    if (!$unidad) {
      json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
    }

    json_out(true, ['data' => ['items' => residentes_for_unit($pdo, $unidadId, $residencialId)]]);
  }

  if ($method === 'GET' && $action === 'list') {
    json_out(true, ['data' => ['items' => list_items($pdo, $residencialId)]]);
  }

  if ($method === 'POST' && $action === 'create') {
    $unidadId = (int)($_POST['unidad_id'] ?? 0);
    $residenteId = (int)($_POST['residente_id'] ?? 0);
    $empresa = clean_string($_POST['empresa'] ?? '', 100);
    $descripcion = clean_string($_POST['descripcion'] ?? '', 255);
    $codigoRastreo = clean_string($_POST['codigo_rastreo'] ?? '', 100);
    $notas = clean_string($_POST['notas'] ?? '', 1000);

    if ($unidadId <= 0) {
      json_out(false, ['error' => 'Debes seleccionar una unidad.'], 422);
    }

    if ($descripcion === '') {
      json_out(false, ['error' => 'La descripción del paquete es obligatoria.'], 422);
    }

    if ($residenteId <= 0) {
      json_out(false, ['error' => 'Debes seleccionar al residente destinatario.'], 422);
    }

    $unidad = validate_unit($pdo, $unidadId, $residencialId);
    if (!$unidad) {
      json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
    }

    $residente = residente_in_unit($pdo, $residenteId, $unidadId, $residencialId);
    if (!$residente) {
      json_out(false, ['error' => 'El residente seleccionado no pertenece a esta unidad.'], 422);
    }

    $stmt = $pdo->prepare("
      INSERT INTO paqueteria (
        residencial_id,
        unidad_id,
        residente_id,
        guardia_id,
        empresa,
        descripcion,
        codigo_rastreo,
        estado,
        notas
      ) VALUES (
        :rid,
        :unidad_id,
        :residente_id,
        :guardia_id,
        :empresa,
        :descripcion,
        :codigo_rastreo,
        'registrado',
        :notas
      )
    ");
    $stmt->execute([
      'rid' => $residencialId,
      'unidad_id' => $unidadId,
      'residente_id' => (int)$residente['id'],
      'guardia_id' => $uid,
      'empresa' => $empresa !== '' ? $empresa : null,
      'descripcion' => $descripcion,
      'codigo_rastreo' => $codigoRastreo !== '' ? $codigoRastreo : null,
      'notas' => $notas !== '' ? $notas : null,
    ]);

    json_out(true, [
      'message' => 'Paquete registrado correctamente.',
      'data' => ['items' => list_items($pdo, $residencialId)],
    ]);
  }

  if ($method === 'POST' && $action === 'update_status') {
    $id = (int)($_POST['id'] ?? 0);
    $estado = clean_string($_POST['estado'] ?? '', 20);
    $allowedEstados = ['registrado', 'entregado', 'devuelto'];

    if ($id <= 0) {
      json_out(false, ['error' => 'Paquete inválido.'], 422);
    }

    if (!in_array($estado, $allowedEstados, true)) {
      json_out(false, ['error' => 'Estado inválido.'], 422);
    }

    $stmt = $pdo->prepare("
      UPDATE paqueteria
      SET estado = :estado, updated_at = NOW()
      WHERE id = :id
        AND residencial_id = :rid
      LIMIT 1
    ");
    $stmt->execute([
      'estado' => $estado,
      'id' => $id,
      'rid' => $residencialId,
    ]);

    if ($stmt->rowCount() <= 0) {
      json_out(false, ['error' => 'No se pudo actualizar el paquete.'], 404);
    }

    json_out(true, [
      'message' => 'Estado actualizado correctamente.',
      'data' => ['items' => list_items($pdo, $residencialId)],
    ]);
  }

  json_out(false, ['error' => 'Acción no soportada.'], 400);
} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos procesar la paquetería.');
}
