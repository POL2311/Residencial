<?php
// guardia/php/api/incidencias.php
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

function json_out(bool $ok, array $extra = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function clean(string $v): string {
  return trim((string)preg_replace('/\s+/', ' ', $v));
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

function getIncidencia(PDO $pdo, int $id, int $residencialId): ?array {
  $stmt = $pdo->prepare("
    SELECT i.*, u.clave AS unidad_clave
    FROM incidencias i
    JOIN unidades u ON u.id = i.unidad_id
    WHERE i.id = :id
      AND i.residencial_id = :rid
    LIMIT 1
  ");
  $stmt->execute([
    'id' => $id,
    'rid' => $residencialId,
  ]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ?: null;
}

function validateUnidad(PDO $pdo, int $unidadId, int $residencialId): bool {
  $stmt = $pdo->prepare("
    SELECT 1
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
  return (bool)$stmt->fetchColumn();
}

function validateResidenteUnidad(PDO $pdo, int $residenteId, int $unidadId, int $residencialId): bool {
  $stmt = $pdo->prepare("
    SELECT 1
    FROM residentes_unidades ru
    JOIN unidades u ON u.id = ru.unidad_id
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
  return (bool)$stmt->fetchColumn();
}

try {
  $residencial_id = rid($pdo, $uid);
  if ($residencial_id <= 0) {
    json_out(false, ['error' => 'Guardia no asociado a residencial.'], 403);
  }

  // GET unidades
  if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'unidades')) {
    $stmt = $pdo->prepare("
      SELECT id, clave
      FROM unidades
      WHERE residencial_id = :rid
        AND activo = 1
      ORDER BY clave ASC
    ");
    $stmt->execute(['rid' => $residencial_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    json_out(true, ['data' => ['items' => $items]]);
  }

  // GET residentes por unidad
  if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'residentes_unidad')) {
    $unidad_id = (int)($_GET['unidad_id'] ?? 0);
    if ($unidad_id <= 0) {
      json_out(false, ['error' => 'Unidad inválida.'], 422);
    }

    if (!validateUnidad($pdo, $unidad_id, $residencial_id)) {
      json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
    }

    $stmt = $pdo->prepare("
      SELECT
        ru.user_id AS id,
        us.name,
        us.email,
        ru.es_titular
      FROM residentes_unidades ru
      JOIN users us ON us.id = ru.user_id
      JOIN unidades u ON u.id = ru.unidad_id
      WHERE ru.unidad_id = :unidad_id
        AND ru.activo = 1
        AND u.residencial_id = :rid
      ORDER BY ru.es_titular DESC, us.name ASC
    ");
    $stmt->execute([
      'unidad_id' => $unidad_id,
      'rid' => $residencial_id,
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    json_out(true, ['data' => ['items' => $items]]);
  }

  // GET listado
  if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $q = clean((string)($_GET['q'] ?? ''));
    $estado = clean((string)($_GET['estado'] ?? ''));
    $prioridad = clean((string)($_GET['prioridad'] ?? ''));
    $tipo = clean((string)($_GET['tipo'] ?? ''));

    $where = ["i.residencial_id = :rid"];
    $params = ['rid' => $residencial_id];

    if ($q !== '') {
      $where[] = "(i.titulo LIKE :q OR i.descripcion LIKE :q OR u.clave LIKE :q)";
      $params['q'] = '%' . $q . '%';
    }

    if ($estado !== '') {
      $where[] = "i.estado = :estado";
      $params['estado'] = $estado;
    }

    if ($prioridad !== '') {
      $where[] = "i.prioridad = :prioridad";
      $params['prioridad'] = $prioridad;
    }

    if ($tipo !== '') {
      $where[] = "i.tipo = :tipo";
      $params['tipo'] = $tipo;
    }

    $sql = "
      SELECT
        i.*,
        u.clave AS unidad_clave
      FROM incidencias i
      JOIN unidades u ON u.id = i.unidad_id
      WHERE " . implode(' AND ', $where) . "
      ORDER BY i.created_at DESC, i.id DESC
      LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    json_out(true, ['data' => ['items' => $items]]);
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = clean((string)($_POST['action'] ?? ''));

    if ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        json_out(false, ['error' => 'Incidencia inválida.'], 422);
      }

      $stmt = $pdo->prepare("
        DELETE FROM incidencias
        WHERE id = :id
          AND residencial_id = :rid
        LIMIT 1
      ");
      $stmt->execute([
        'id' => $id,
        'rid' => $residencial_id,
      ]);

      if ($stmt->rowCount() <= 0) {
        json_out(false, ['error' => 'No se pudo eliminar la incidencia.'], 404);
      }

      json_out(true, ['message' => 'Incidencia eliminada correctamente.']);
    }

    if ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $unidad_id = (int)($_POST['unidad_id'] ?? 0);
      $residente_id = (int)($_POST['residente_id'] ?? 0);
      $tipo = clean((string)($_POST['tipo'] ?? 'seguridad'));
      $prioridad = clean((string)($_POST['prioridad'] ?? 'media'));
      $estado = clean((string)($_POST['estado'] ?? 'abierta'));
      $titulo = clean((string)($_POST['titulo'] ?? ''));
      $descripcion = clean((string)($_POST['descripcion'] ?? ''));

      if ($id <= 0) json_out(false, ['error' => 'Incidencia inválida.'], 422);
      if ($unidad_id <= 0) json_out(false, ['error' => 'Debes seleccionar una unidad.'], 422);
      if ($residente_id <= 0) json_out(false, ['error' => 'Debes seleccionar un residente.'], 422);
      if ($titulo === '') json_out(false, ['error' => 'El título es obligatorio.'], 422);
      if ($descripcion === '') json_out(false, ['error' => 'La descripción es obligatoria.'], 422);

      $allowedTipos = ['seguridad', 'ruido', 'mantenimiento', 'otros'];
      $allowedPrioridades = ['baja', 'media', 'alta'];
      $allowedEstados = ['abierta', 'en_proceso', 'cerrada'];

      if (!in_array($tipo, $allowedTipos, true)) json_out(false, ['error' => 'Tipo inválido.'], 422);
      if (!in_array($prioridad, $allowedPrioridades, true)) json_out(false, ['error' => 'Prioridad inválida.'], 422);
      if (!in_array($estado, $allowedEstados, true)) json_out(false, ['error' => 'Estado inválido.'], 422);

      $existing = getIncidencia($pdo, $id, $residencial_id);
      if (!$existing) json_out(false, ['error' => 'Incidencia no encontrada.'], 404);

      if (!validateUnidad($pdo, $unidad_id, $residencial_id)) {
        json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
      }

      if (!validateResidenteUnidad($pdo, $residente_id, $unidad_id, $residencial_id)) {
        json_out(false, ['error' => 'El residente seleccionado no pertenece a la unidad.'], 422);
      }

      $stmt = $pdo->prepare("
        UPDATE incidencias
        SET
          unidad_id = :unidad_id,
          residente_id = :residente_id,
          titulo = :titulo,
          descripcion = :descripcion,
          tipo = :tipo,
          prioridad = :prioridad,
          estado = :estado
        WHERE id = :id
          AND residencial_id = :rid
        LIMIT 1
      ");
      $stmt->execute([
        'unidad_id' => $unidad_id,
        'residente_id' => $residente_id,
        'titulo' => $titulo,
        'descripcion' => $descripcion,
        'tipo' => $tipo,
        'prioridad' => $prioridad,
        'estado' => $estado,
        'id' => $id,
        'rid' => $residencial_id,
      ]);

      json_out(true, ['message' => 'Incidencia actualizada correctamente.']);
    }

    if ($action !== 'create') {
      json_out(false, ['error' => 'Acción inválida.'], 422);
    }

    $unidad_id = (int)($_POST['unidad_id'] ?? 0);
    $residente_id = (int)($_POST['residente_id'] ?? 0);
    $tipo = clean((string)($_POST['tipo'] ?? 'seguridad'));
    $prioridad = clean((string)($_POST['prioridad'] ?? 'media'));
    $titulo = clean((string)($_POST['titulo'] ?? ''));
    $descripcion = clean((string)($_POST['descripcion'] ?? ''));

    if ($unidad_id <= 0) json_out(false, ['error' => 'Debes seleccionar una unidad.'], 422);
    if ($residente_id <= 0) json_out(false, ['error' => 'Debes seleccionar un residente.'], 422);
    if ($titulo === '') json_out(false, ['error' => 'El título es obligatorio.'], 422);
    if ($descripcion === '') json_out(false, ['error' => 'La descripción es obligatoria.'], 422);

    $allowedTipos = ['seguridad', 'ruido', 'mantenimiento', 'otros'];
    $allowedPrioridades = ['baja', 'media', 'alta'];

    if (!in_array($tipo, $allowedTipos, true)) json_out(false, ['error' => 'Tipo inválido.'], 422);
    if (!in_array($prioridad, $allowedPrioridades, true)) json_out(false, ['error' => 'Prioridad inválida.'], 422);

    if (!validateUnidad($pdo, $unidad_id, $residencial_id)) {
      json_out(false, ['error' => 'La unidad no pertenece a este residencial.'], 422);
    }

    if (!validateResidenteUnidad($pdo, $residente_id, $unidad_id, $residencial_id)) {
      json_out(false, ['error' => 'El residente seleccionado no pertenece a la unidad.'], 422);
    }

    $stmt = $pdo->prepare("
      INSERT INTO incidencias (
        residencial_id,
        unidad_id,
        residente_id,
        titulo,
        descripcion,
        tipo,
        prioridad,
        estado
      )
      VALUES (
        :rid,
        :unidad_id,
        :residente_id,
        :titulo,
        :descripcion,
        :tipo,
        :prioridad,
        'abierta'
      )
    ");
    $stmt->execute([
      'rid' => $residencial_id,
      'unidad_id' => $unidad_id,
      'residente_id' => $residente_id,
      'titulo' => $titulo,
      'descripcion' => $descripcion,
      'tipo' => $tipo,
      'prioridad' => $prioridad,
    ]);

    json_out(true, [
      'data' => ['created' => true],
      'message' => 'Incidencia creada correctamente.'
    ]);
  }

  json_out(false, ['error' => 'Método no soportado.'], 405);

} catch (Throwable $e) {
  json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}