<?php
// /residente/php/api/paqueteria.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['residente']);

$user = current_user();
$uid  = (int)($user['id'] ?? 0);

function json_out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function tableCols(PDO $pdo, string $table): array {
  $stmt = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
  ");
  $stmt->execute(['t' => $table]);
  return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function ensureMigrated(PDO $pdo): void {
  $cols = tableCols($pdo, 'paqueteria');
  $need = ['instruccion_entrega','fecha_estimada','foto_url','fecha_recepcion','fecha_entrega_final','motivo_final','motivo_detalle','incidencia_detalle','incidencia_foto_url'];
  foreach ($need as $c) {
    if (!in_array($c, $cols, true)) {
      json_out(false, ['error' => 'La tabla paqueteria no está migrada (falta columna: ' . $c . '). Ejecuta la migración SQL.']);
    }
  }
}

function getContext(PDO $pdo, int $uid): array {
  $stmt = $pdo->prepare("
    SELECT
      r.id AS residencial_id,
      r.nombre AS residencial_nombre,
      r.telefono_contacto AS caseta_phone,
      u.id AS unidad_id,
      u.clave AS unidad_clave
    FROM usuarios_residenciales ur
    JOIN residenciales r        ON r.id = ur.residencial_id
    JOIN residentes_unidades ru ON ru.user_id = ur.user_id
    JOIN unidades u             ON u.id = ru.unidad_id
    WHERE ur.user_id = :uid
    ORDER BY ur.es_principal DESC, ur.created_at ASC
    LIMIT 1
  ");
  $stmt->execute(['uid' => $uid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) {
    json_out(false, ['error' => 'Tu cuenta no está ligada a un residencial/unidad.']);
  }
  return $row;
}

function clean(?string $v): ?string {
  $v = trim((string)$v);
  return $v === '' ? null : $v;
}

function saveUpload(string $field, string $subdir = 'paqueteria'): ?string {
  if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
  if (($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;

  $tmp = (string)$_FILES[$field]['tmp_name'];
  $name = (string)$_FILES[$field]['name'];

  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
    json_out(false, ['error' => 'Formato de imagen no soportado. Usa JPG/PNG/WEBP.']);
  }

  $size = (int)($_FILES[$field]['size'] ?? 0);
  if ($size > 5 * 1024 * 1024) {
    json_out(false, ['error' => 'La imagen es demasiado grande (máx 5MB).']);
  }

  $root = realpath(__DIR__ . '/../../../');
  if (!$root) json_out(false, ['error' => 'No se encontró el root del proyecto.']);

  $dir = $root . '/uploads/' . $subdir;
  if (!is_dir($dir)) {
    @mkdir($dir, 0775, true);
  }
  if (!is_dir($dir)) {
    json_out(false, ['error' => 'No se pudo crear la carpeta de uploads.']);
  }

  $fname = $subdir . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
  $dest = $dir . '/' . $fname;

  if (!move_uploaded_file($tmp, $dest)) {
    json_out(false, ['error' => 'No se pudo guardar la imagen.']);
  }

  // URL pública relativa (ajusta si tu app sirve uploads distinto)
  return '/uploads/' . $subdir . '/' . $fname;
}

$action = $_POST['action'] ?? 'list';

try {
  ensureMigrated($pdo);
  $ctx = getContext($pdo, $uid);
  $residencial_id = (int)$ctx['residencial_id'];
  $unidad_id = (int)$ctx['unidad_id'];

  // LIST
  if ($action === 'list') {
    $stmt = $pdo->prepare("
      SELECT
        id, residencial_id, unidad_id, residente_id, guardia_id,
        empresa, descripcion, foto_url, codigo_rastreo,
        estado, instruccion_entrega, fecha_estimada,
        fecha_recepcion, fecha_entrega_final,
        motivo_final, motivo_detalle,
        incidencia_detalle, incidencia_foto_url,
        created_at, updated_at
      FROM paqueteria
      WHERE residencial_id = :rid AND unidad_id = :uid
      ORDER BY created_at DESC
      LIMIT 500
    ");
    $stmt->execute(['rid' => $residencial_id, 'uid' => $unidad_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    json_out(true, [
      'data' => [
        'ctx' => $ctx,
        'caseta_phone' => $ctx['caseta_phone'] ?? null,
        'items' => $items
      ]
    ]);
  }

  // CREATE esperado (residente)
  if ($action === 'create_expected') {
    $empresa = clean($_POST['empresa'] ?? null);
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $codigo = clean($_POST['codigo_rastreo'] ?? null);
    $instr = (string)($_POST['instruccion_entrega'] ?? 'recepcion_caseta');
    $fecha_estimada = clean($_POST['fecha_estimada'] ?? null);

    if ($descripcion === '') json_out(false, ['error' => 'La descripción es obligatoria.']);
    if (!in_array($instr, ['recepcion_caseta','acceso_domicilio'], true)) $instr = 'recepcion_caseta';

    $stmt = $pdo->prepare("
      INSERT INTO paqueteria
        (residencial_id, unidad_id, residente_id, guardia_id,
         empresa, descripcion, foto_url, codigo_rastreo,
         estado, instruccion_entrega, fecha_estimada,
         fecha_recepcion, fecha_entrega_final,
         motivo_final, motivo_detalle, incidencia_detalle, incidencia_foto_url,
         created_at, updated_at)
      VALUES
        (:rid, :uid, :residente_id, NULL,
         :empresa, :descripcion, NULL, :codigo,
         'esperado', :instr, :fecha_estimada,
         NULL, NULL,
         NULL, NULL, NULL, NULL,
         NOW(), NOW())
    ");
    $stmt->execute([
      'rid' => $residencial_id,
      'uid' => $unidad_id,
      'residente_id' => $uid,
      'empresa' => $empresa,
      'descripcion' => $descripcion,
      'codigo' => $codigo,
      'instr' => $instr,
      'fecha_estimada' => $fecha_estimada,
    ]);

    json_out(true, ['message' => 'Paquete esperado registrado ✅']);
  }

  // UPDATE esperado (solo si sigue esperado y pertenece al residente)
  if ($action === 'update_expected') {
    $paq_id = (int)($_POST['paq_id'] ?? 0);
    if ($paq_id <= 0) json_out(false, ['error' => 'Paquete inválido.']);

    $empresa = clean($_POST['empresa'] ?? null);
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $codigo = clean($_POST['codigo_rastreo'] ?? null);
    $instr = (string)($_POST['instruccion_entrega'] ?? 'recepcion_caseta');
    $fecha_estimada = clean($_POST['fecha_estimada'] ?? null);

    if ($descripcion === '') json_out(false, ['error' => 'La descripción es obligatoria.']);
    if (!in_array($instr, ['recepcion_caseta','acceso_domicilio'], true)) $instr = 'recepcion_caseta';

    $chk = $pdo->prepare("
      SELECT id, estado, residente_id
      FROM paqueteria
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
      LIMIT 1
    ");
    $chk->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.']);

    if (($row['estado'] ?? '') !== 'esperado') json_out(false, ['error' => 'Solo puedes editar cuando está en estado Esperado.']);
    if ((int)($row['residente_id'] ?? 0) !== $uid) json_out(false, ['error' => 'Solo el creador puede editar este esperado.']);

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET empresa = :empresa,
          descripcion = :descripcion,
          codigo_rastreo = :codigo,
          instruccion_entrega = :instr,
          fecha_estimada = :fecha_estimada,
          updated_at = NOW()
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
    ");
    $upd->execute([
      'empresa' => $empresa,
      'descripcion' => $descripcion,
      'codigo' => $codigo,
      'instr' => $instr,
      'fecha_estimada' => $fecha_estimada,
      'id' => $paq_id,
      'rid' => $residencial_id,
      'uid' => $unidad_id
    ]);

    json_out(true, ['message' => 'Paquete actualizado ✅']);
  }

  // CANCEL esperado (auditable): pasa a cancelado (solo si esperado y creador)
  if ($action === 'cancel_expected') {
    $paq_id = (int)($_POST['paq_id'] ?? 0);
    if ($paq_id <= 0) json_out(false, ['error' => 'Paquete inválido.']);

    $chk = $pdo->prepare("
      SELECT id, estado, residente_id
      FROM paqueteria
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
      LIMIT 1
    ");
    $chk->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.']);

    if (($row['estado'] ?? '') !== 'esperado') json_out(false, ['error' => 'Solo puedes cancelar cuando está en Esperado.']);
    if ((int)($row['residente_id'] ?? 0) !== $uid) json_out(false, ['error' => 'Solo el creador puede cancelar este esperado.']);

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET estado = 'cancelado',
          updated_at = NOW()
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
    ");
    $upd->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);

    json_out(true, ['message' => 'Esperado cancelado ✅']);
  }

  // DELETE esperado (hard delete): solo si esperado y creador
  if ($action === 'delete_expected') {
    $paq_id = (int)($_POST['paq_id'] ?? 0);
    if ($paq_id <= 0) json_out(false, ['error' => 'Paquete inválido.']);

    $chk = $pdo->prepare("
      SELECT id, estado, residente_id
      FROM paqueteria
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
      LIMIT 1
    ");
    $chk->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.']);

    if (($row['estado'] ?? '') !== 'esperado') json_out(false, ['error' => 'Solo puedes eliminar cuando está en Esperado.']);
    if ((int)($row['residente_id'] ?? 0) !== $uid) json_out(false, ['error' => 'Solo el creador puede eliminar este esperado.']);

    $del = $pdo->prepare("DELETE FROM paqueteria WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid");
    $del->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);

    json_out(true, ['message' => 'Esperado eliminado ✅']);
  }

  // CONFIRM ENTREGADO (residente)
  if ($action === 'confirm_entregado') {
    $paq_id = (int)($_POST['paq_id'] ?? 0);
    if ($paq_id <= 0) json_out(false, ['error' => 'Paquete inválido.']);

    $chk = $pdo->prepare("
      SELECT id, estado, residente_id
      FROM paqueteria
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
      LIMIT 1
    ");
    $chk->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.']);

    $estado = (string)($row['estado'] ?? '');
    if (!in_array($estado, ['registrado','acceso_otorgado'], true)) {
      json_out(false, ['error' => 'Solo puedes confirmar recibido si está En caseta o Acceso otorgado.']);
    }

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET estado = 'entregado',
          residente_id = COALESCE(residente_id, :residente_id),
          fecha_entrega_final = NOW(),
          motivo_final = NULL,
          motivo_detalle = NULL,
          incidencia_detalle = NULL,
          incidencia_foto_url = NULL,
          updated_at = NOW()
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
    ");
    $upd->execute([
      'residente_id' => $uid,
      'id' => $paq_id,
      'rid' => $residencial_id,
      'uid' => $unidad_id
    ]);

    json_out(true, ['message' => 'Confirmado como entregado ✅']);
  }

  // CONFIRM ENTREGADO CON INCIDENCIA (residente) -> foto obligatoria
  if ($action === 'confirm_entregado_incidencia') {
    $paq_id = (int)($_POST['paq_id'] ?? 0);
    if ($paq_id <= 0) json_out(false, ['error' => 'Paquete inválido.']);

    $detalle = trim((string)($_POST['incidencia_detalle'] ?? ''));
    if ($detalle === '') json_out(false, ['error' => 'Describe la incidencia.']);

    $foto = saveUpload('foto', 'paqueteria');
    if (!$foto) json_out(false, ['error' => 'La foto es obligatoria para incidencia.']);

    $chk = $pdo->prepare("
      SELECT id, estado
      FROM paqueteria
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
      LIMIT 1
    ");
    $chk->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.']);

    $estado = (string)($row['estado'] ?? '');
    if (!in_array($estado, ['registrado'], true)) {
      json_out(false, ['error' => 'La incidencia aplica cuando está En caseta.']);
    }

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET estado = 'entregado_incidencia',
          residente_id = COALESCE(residente_id, :residente_id),
          fecha_entrega_final = NOW(),
          incidencia_detalle = :detalle,
          incidencia_foto_url = :foto,
          updated_at = NOW()
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
    ");
    $upd->execute([
      'residente_id' => $uid,
      'detalle' => $detalle,
      'foto' => $foto,
      'id' => $paq_id,
      'rid' => $residencial_id,
      'uid' => $unidad_id
    ]);

    json_out(true, ['message' => 'Entregado con incidencia registrado ✅']);
  }

  // CONFIRM RECHAZADO (residente)
  if ($action === 'confirm_rechazado') {
    $paq_id = (int)($_POST['paq_id'] ?? 0);
    if ($paq_id <= 0) json_out(false, ['error' => 'Paquete inválido.']);

    $motivo = trim((string)($_POST['motivo_detalle'] ?? ''));
    $extra  = trim((string)($_POST['motivo_extra'] ?? ''));

    if ($motivo === '') json_out(false, ['error' => 'Selecciona un motivo.']);
    $final = $motivo . ($extra !== '' ? (': ' . $extra) : '');

    $chk = $pdo->prepare("
      SELECT id, estado
      FROM paqueteria
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
      LIMIT 1
    ");
    $chk->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.']);

    $estado = (string)($row['estado'] ?? '');
    if (!in_array($estado, ['registrado','acceso_otorgado'], true)) {
      json_out(false, ['error' => 'Solo puedes rechazar si está En caseta o Acceso otorgado.']);
    }

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET estado = 'rechazado',
          residente_id = COALESCE(residente_id, :residente_id),
          fecha_entrega_final = NOW(),
          motivo_final = 'rechazado',
          motivo_detalle = :motivo,
          updated_at = NOW()
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
    ");
    $upd->execute([
      'residente_id' => $uid,
      'motivo' => $final,
      'id' => $paq_id,
      'rid' => $residencial_id,
      'uid' => $unidad_id
    ]);

    json_out(true, ['message' => 'Marcado como rechazado ✅']);
  }

  // CONFIRM NO LLEGÓ (residente)
  if ($action === 'confirm_no_llego') {
    $paq_id = (int)($_POST['paq_id'] ?? 0);
    if ($paq_id <= 0) json_out(false, ['error' => 'Paquete inválido.']);

    $motivo = trim((string)($_POST['motivo_detalle'] ?? ''));
    $extra  = trim((string)($_POST['motivo_extra'] ?? ''));

    if ($motivo === '') json_out(false, ['error' => 'Selecciona un motivo.']);
    $final = $motivo . ($extra !== '' ? (': ' . $extra) : '');

    $chk = $pdo->prepare("
      SELECT id, estado
      FROM paqueteria
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
      LIMIT 1
    ");
    $chk->execute(['id' => $paq_id, 'rid' => $residencial_id, 'uid' => $unidad_id]);
    $row = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$row) json_out(false, ['error' => 'No se encontró el paquete.']);

    $estado = (string)($row['estado'] ?? '');
    if (!in_array($estado, ['acceso_otorgado'], true)) {
      json_out(false, ['error' => '“No llegó” aplica cuando está en Acceso otorgado.']);
    }

    $upd = $pdo->prepare("
      UPDATE paqueteria
      SET estado = 'no_llego',
          residente_id = COALESCE(residente_id, :residente_id),
          fecha_entrega_final = NOW(),
          motivo_final = 'no_llego',
          motivo_detalle = :motivo,
          updated_at = NOW()
      WHERE id = :id AND residencial_id = :rid AND unidad_id = :uid
    ");
    $upd->execute([
      'residente_id' => $uid,
      'motivo' => $final,
      'id' => $paq_id,
      'rid' => $residencial_id,
      'uid' => $unidad_id
    ]);

    json_out(true, ['message' => 'Marcado como “No llegó” ✅']);
  }

  json_out(false, ['error' => 'Acción no soportada.']);

} catch (Throwable $e) {
  json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
}