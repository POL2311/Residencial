<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/api_helpers.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['admin_residencial']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  app_require_write_guard();
}

$adminId = (int)(current_user()['id'] ?? 0);

/* =========================
   CONTEXTO RESIDENCIAL (helper)
========================= */
$residencialId = require_residencial_id($pdo, $adminId);
service_profile_api_require_module($pdo, $residencialId, 'admin_residencial', 'unidades', 'Las unidades no están habilitadas para este cliente.');

/* =========================
   GET: LIST
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

  try {
    $stmt = $pdo->prepare("
      SELECT
        u.id, u.tipo, u.clave, u.torre, u.nivel, u.activo,
        (
          SELECT us.name
          FROM residentes_unidades ru
          JOIN users us ON us.id = ru.user_id
          WHERE ru.unidad_id = u.id
            AND ru.es_titular = 1
          LIMIT 1
        ) AS titular
      FROM unidades u
      WHERE u.residencial_id = ?
      ORDER BY u.clave ASC
    ");

    $stmt->execute([$residencialId]);

    json_out(true, [
      'unidades' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
    ]);

  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error al obtener unidades']);
  }
}

/* =========================
   POST: ACTIONS
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $action = $_POST['action'] ?? '';

  /* =========================
     DELETE
  ========================= */
  if ($action === 'delete') {

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
      json_out(false, ['error' => 'ID inválido']);
    }

    $stmt = $pdo->prepare("
      DELETE FROM unidades
      WHERE id = ? AND residencial_id = ?
    ");

    $stmt->execute([$id, $residencialId]);

    json_out(true, ['message' => 'Unidad eliminada']);
  }

  /* =========================
     VALIDACIÓN
  ========================= */
  $id    = (int)($_POST['id'] ?? 0);
  $clave = trim($_POST['clave'] ?? '');
  $torre = trim($_POST['torre'] ?? '');
  $tipo  = trim($_POST['tipo'] ?? '');

  if ($clave === '') {
    json_out(false, ['error' => 'Clave requerida']);
  }

  if (!in_array($tipo, ['casa','departamento','local','otro'])) {
    json_out(false, ['error' => 'Tipo inválido']);
  }

  /* =========================
     UPDATE
  ========================= */
  if ($action === 'update') {

    if ($id <= 0) {
      json_out(false, ['error' => 'ID inválido']);
    }

    $stmt = $pdo->prepare("
      UPDATE unidades
      SET clave = ?, torre = ?, tipo = ?
      WHERE id = ? AND residencial_id = ?
    ");

    $stmt->execute([
      $clave,
      $torre ?: null,
      $tipo,
      $id,
      $residencialId
    ]);

    json_out(true, ['message' => 'Unidad actualizada']);
  }

  /* =========================
     CREATE
  ========================= */
  if ($action === 'create') {

    $exists = $pdo->prepare("
      SELECT id FROM unidades
      WHERE residencial_id = ? AND clave = ?
      LIMIT 1
    ");
    $exists->execute([$residencialId, $clave]);

    if ($exists->fetchColumn()) {
      json_out(false, ['error' => 'Clave ya existe']);
    }

    $stmt = $pdo->prepare("
      INSERT INTO unidades (
        residencial_id,
        tipo,
        clave,
        torre,
        activo,
        created_at
      ) VALUES (?, ?, ?, ?, 1, NOW())
    ");

    $stmt->execute([
      $residencialId,
      $tipo,
      $clave,
      $torre ?: null
    ]);

    json_out(true, ['message' => 'Unidad creada']);
  }

  json_out(false, ['error' => 'Acción no válida']);
}

json_out(false, ['error' => 'Método no permitido']);
