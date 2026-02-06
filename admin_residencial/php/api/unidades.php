<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['admin_residencial']);

$user = current_user();
$adminId = (int)($user['id'] ?? 0);

function json_out(bool $ok, array $data = []): void {
  echo json_encode(array_merge(['ok' => $ok], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $stmtRes = $pdo->prepare("
    SELECT residencial_id
    FROM usuarios_residenciales
    WHERE user_id = ?
    ORDER BY es_principal DESC
    LIMIT 1
  ");
  $stmtRes->execute([$adminId]);
  $residencialId = (int)$stmtRes->fetchColumn();
  if (!$residencialId) json_out(false, ['error' => 'No tienes un residencial asignado.']);
} catch (PDOException $e) {
  json_out(false, ['error' => 'Error al obtener residencial: ' . $e->getMessage()]);
}

/* =========================
   GET: LIST (con titular)
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  try {
    $stmt = $pdo->prepare("
      SELECT
        u.id, u.tipo, u.clave, u.calle, u.numero_exterior, u.numero_interior,
        u.torre, u.nivel, u.notas, u.activo,
        (
          SELECT us.name
          FROM residentes_unidades ru
          JOIN users us ON us.id = ru.user_id
          WHERE ru.unidad_id = u.id
            AND ru.es_titular = 1
          ORDER BY ru.id ASC
          LIMIT 1
        ) AS titular
      FROM unidades u
      WHERE u.residencial_id = ?
      ORDER BY u.clave ASC
    ");
    $stmt->execute([$residencialId]);
    $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    json_out(true, ['unidades' => $unidades]);
  } catch (PDOException $e) {
    json_out(false, ['error' => 'Error al obtener unidades: ' . $e->getMessage()]);
  }
}

/* =========================
   POST: CREATE / UPDATE / DELETE
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');

  // DELETE
  if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_out(false, ['error' => 'ID inválido.']);

    try {
      $stmt = $pdo->prepare("DELETE FROM unidades WHERE id = ? AND residencial_id = ?");
      $stmt->execute([$id, $residencialId]);

      if ($stmt->rowCount() === 0) {
        json_out(false, ['error' => 'No se pudo eliminar (no existe o no pertenece a tu residencial).']);
      }
      json_out(true, ['message' => 'Unidad eliminada.']);
    } catch (PDOException $e) {
      json_out(false, ['error' => 'Error al eliminar: ' . $e->getMessage()]);
    }
  }

  // CREATE / UPDATE
  $id   = (int)($_POST['id'] ?? 0);
  $clave = trim((string)($_POST['clave'] ?? ''));
  $torre = trim((string)($_POST['torre'] ?? ''));
  $tipo  = trim((string)($_POST['tipo'] ?? ''));

  if ($clave === '') json_out(false, ['error' => 'Clave es requerida.']);
  if ($tipo === '') json_out(false, ['error' => 'Tipo es requerido.']);

  try {
    if ($action === 'update') {
      if ($id <= 0) json_out(false, ['error' => 'ID inválido para actualizar.']);

      $stmt = $pdo->prepare("
        UPDATE unidades
        SET clave = :clave, torre = :torre, tipo = :tipo
        WHERE id = :id AND residencial_id = :rid
      ");
      $stmt->execute([
        'clave' => $clave,
        'torre' => ($torre !== '' ? $torre : null),
        'tipo'  => $tipo,
        'id'    => $id,
        'rid'   => $residencialId
      ]);

      if ($stmt->rowCount() === 0) {
        json_out(false, ['error' => 'No se actualizó (no existe o no pertenece a tu residencial).']);
      }
      json_out(true, ['message' => 'Unidad actualizada.']);
    }

    if ($action === 'create') {
      $stmtChk = $pdo->prepare("SELECT id FROM unidades WHERE residencial_id = ? AND clave = ? LIMIT 1");
      $stmtChk->execute([$residencialId, $clave]);
      if ($stmtChk->fetchColumn()) json_out(false, ['error' => 'Ya existe una unidad con esa clave.']);

$stmt = $pdo->prepare("
  INSERT INTO unidades (
    residencial_id,
    tipo,
    clave,
    torre,
    activo,
    created_at
  )
  VALUES (
    :rid,
    :tipo,
    :clave,
    :torre,
    1,
    NOW()
  )
");

      $stmt->execute([
        'rid'  => $residencialId,
        'tipo' => $tipo,
        'clave'=> $clave,
        'torre'=> ($torre !== '' ? $torre : null),
      ]);

      json_out(true, ['message' => 'Unidad creada.']);
    }

    json_out(false, ['error' => 'Acción POST no soportada.']);

  } catch (PDOException $e) {
    json_out(false, ['error' => 'Error al guardar: ' . $e->getMessage()]);
  }
}

json_out(false, ['error' => 'Método no soportado.']);
