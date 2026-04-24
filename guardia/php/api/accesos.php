<?php
// guardia/php/api/accesos.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/resident_access.php';

require_login();
require_role(['guardia','super_admin']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  app_require_write_guard();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function out(bool $ok, array $payload = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $payload), JSON_UNESCAPED_UNICODE);
  exit;
}

function strv($v, int $max = 255): string {
  $s = trim((string)($v ?? ''));
  if (mb_strlen($s) > $max) $s = mb_substr($s, 0, $max);
  return $s;
}

function intv_safe($v, int $default = 0): int {
  if ($v === null || $v === '') return $default;
  return (int)$v;
}

function get_residencial_id(PDO $pdo, int $uid): int {
  $stmt = $pdo->prepare("
    SELECT ur.residencial_id
    FROM usuarios_residenciales ur
    WHERE ur.user_id = :uid
    ORDER BY ur.es_principal DESC, ur.created_at ASC
    LIMIT 1
  ");
  $stmt->execute(['uid' => $uid]);
  $rid = (int)($stmt->fetchColumn() ?: 0);

  if ($rid <= 0) {
    out(false, ['error' => 'Tu usuario guardia no está asignado a un residencial.'], 403);
  }

  return $rid;
}

function now_mx(): DateTime {
  return new DateTime('now');
}

function time_in_range(?string $hDesde, ?string $hHasta, DateTime $now): bool {
  if (!$hDesde && !$hHasta) return true;

  $cur = $now->format('H:i:s');

  if ($hDesde && !$hHasta) return $cur >= $hDesde;
  if (!$hDesde && $hHasta) return $cur <= $hHasta;

  return ($cur >= $hDesde && $cur <= $hHasta);
}

function evaluar_visita(array $visita): array {
  $now = now_mx();
  $today = $now->format('Y-m-d');

  $permitido = true;
  $motivo = '';

  if (($visita['estado'] ?? '') === 'cancelado') {
    $permitido = false;
    $motivo = 'Acceso cancelado.';
  }

  if (($visita['estado'] ?? '') === 'vencido') {
    $permitido = false;
    $motivo = 'Acceso vencido.';
  }

  if (($visita['estado'] ?? '') === 'usado' && (int)($visita['uso_unico'] ?? 0) === 1) {
    $permitido = false;
    $motivo = 'Acceso de uso único ya utilizado.';
  }

  if ($permitido) {
    if ($today < ($visita['fecha_desde'] ?? '0000-00-00') || $today > ($visita['fecha_hasta'] ?? '9999-12-31')) {
      $permitido = false;
      $motivo = 'Fuera del rango de fechas permitido.';
    }
  }

  if ($permitido) {
    $hDesde = $visita['hora_desde'] ?: null;
    $hHasta = $visita['hora_hasta'] ?: null;

    if (!time_in_range($hDesde, $hHasta, $now)) {
      $permitido = false;
      $motivo = 'Fuera del horario permitido.';
    }
  }

  return [
    'permitido' => $permitido,
    'motivo' => $motivo,
    'fecha_actual' => $today,
    'hora_actual' => $now->format('H:i:s'),
  ];
}

$user = current_user();
$guardiaId = (int)($user['id'] ?? 0);
$residencialId = get_residencial_id($pdo, $guardiaId);
resident_access_ensure_schema($pdo);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'buscar';

try {
  if ($method === 'GET' && $action === 'buscar') {
    $code = strv($_GET['code'] ?? '', 64);
    if ($code === '') out(false, ['error' => 'Falta code'], 422);

    $stmt = $pdo->prepare("
      SELECT
        v.*,
        u.clave AS unidad_clave,
        u.id AS unidad_id,
        r.name AS residente_nombre
      FROM visitas v
      JOIN unidades u ON u.id = v.unidad_id
      JOIN users r ON r.id = v.residente_id
      WHERE v.residencial_id = :rid
        AND v.codigo_acceso = :code
      LIMIT 1
    ");
    $stmt->execute([
      'rid' => $residencialId,
      'code' => $code
    ]);

    $visita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visita) {
      out(false, ['error' => 'Código no encontrado o no pertenece a este residencial.'], 404);
    }

    $ev = evaluar_visita($visita);

    out(true, [
      'data' => [
        'visita' => $visita,
        'eval' => $ev,
      ]
    ]);
  }

  if ($method === 'GET' && $action === 'buscar_residente') {
    $q = strv($_GET['q'] ?? '', 120);
    if ($q === '') {
      out(false, ['error' => 'Escribe algo para buscar al residente.'], 422);
    }

    $stmt = $pdo->prepare("
      SELECT
        u.id AS user_id,
        u.name,
        u.email,
        u.telefono,
        u.is_active,
        ur.residencial_id,
        ru.id AS resid_unid_id,
        ru.unidad_id,
        ru.activo AS activo_servicio,
        ru.acceso_baneado_manual,
        ru.acceso_baneo_motivo,
        un.clave AS unidad_clave
      FROM users u
      JOIN tipos_usuario t ON t.id = u.tipo_usuario_id
      JOIN usuarios_residenciales ur ON ur.user_id = u.id
      LEFT JOIN residentes_unidades ru ON ru.user_id = u.id
      LEFT JOIN unidades un ON un.id = ru.unidad_id
      WHERE ur.residencial_id = :rid
        AND t.nombre = 'residente'
        AND (
          u.name LIKE :q
          OR u.email LIKE :q
          OR u.telefono LIKE :q
          OR un.clave LIKE :q
        )
      ORDER BY u.name ASC
      LIMIT 20
    ");
    $stmt->execute([
      'rid' => $residencialId,
      'q' => '%' . $q . '%',
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $items = array_map(function (array $row) use ($pdo) {
      $access = resident_access_status($pdo, $row);
      return [
        'user_id' => (int)$row['user_id'],
        'resid_unid_id' => (int)($row['resid_unid_id'] ?? 0),
        'name' => (string)($row['name'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'telefono' => (string)($row['telefono'] ?? ''),
        'unidad_id' => (int)($row['unidad_id'] ?? 0),
        'unidad_clave' => (string)($row['unidad_clave'] ?? '—'),
        'access' => $access,
      ];
    }, $rows);

    out(true, ['data' => ['items' => $items]]);
  }

  if ($method === 'POST' && $action === 'scan') {
    $code = strv($_POST['code'] ?? '', 64);
    if ($code === '') out(false, ['error' => 'Falta code'], 422);

    $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
    $allowedTipoEvento = ['entrada', 'salida', 'verificacion'];
    if (!in_array($tipoEvento, $allowedTipoEvento, true)) {
      out(false, ['error' => 'tipo_evento inválido'], 422);
    }

    $override = strv($_POST['override'] ?? '', 20);
    if ($override !== '' && !in_array($override, ['permitido', 'denegado'], true)) {
      out(false, ['error' => 'override inválido'], 422);
    }

    $obsUser = strv($_POST['observaciones'] ?? '', 255);

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
      SELECT
        v.*,
        u.clave AS unidad_clave,
        r.name AS residente_nombre
      FROM visitas v
      JOIN unidades u ON u.id = v.unidad_id
      JOIN users r ON r.id = v.residente_id
      WHERE v.residencial_id = :rid
        AND v.codigo_acceso = :code
      LIMIT 1
      FOR UPDATE
    ");
    $stmt->execute([
      'rid' => $residencialId,
      'code' => $code
    ]);

    $visita = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visita) {
      $ins = $pdo->prepare("
        INSERT INTO accesos_guardia (visita_id, guardia_id, tipo_evento, resultado, observaciones)
        VALUES (0, :gid, :tipo, 'denegado', :obs)
      ");
      $ins->execute([
        'gid' => $guardiaId,
        'tipo' => $tipoEvento,
        'obs' => ('Código no encontrado en este residencial. ' . $obsUser),
      ]);

      $pdo->commit();
      out(false, ['error' => 'Código no encontrado o no pertenece a este residencial.'], 404);
    }

    $now = now_mx();
    $today = $now->format('Y-m-d');

    if ($today > ($visita['fecha_hasta'] ?? $today) && ($visita['estado'] ?? '') === 'pendiente') {
      $u = $pdo->prepare("UPDATE visitas SET estado='vencido' WHERE id=:id");
      $u->execute(['id' => (int)$visita['id']]);
      $visita['estado'] = 'vencido';
    }

    $ev = evaluar_visita($visita);

    $permitido = ($override !== '') ? ($override === 'permitido') : (bool)$ev['permitido'];
    $motivoDenegado = $permitido ? '' : ($ev['motivo'] ?: 'Acceso denegado.');

    $resultado = $permitido ? 'permitido' : 'denegado';
    $obs = trim(($motivoDenegado ? $motivoDenegado . ' ' : '') . $obsUser);

    $ins = $pdo->prepare("
      INSERT INTO accesos_guardia (visita_id, guardia_id, tipo_evento, resultado, observaciones)
      VALUES (:vid, :gid, :tipo, :res, :obs)
    ");
    $ins->execute([
      'vid' => (int)$visita['id'],
      'gid' => $guardiaId,
      'tipo' => $tipoEvento,
      'res' => $resultado,
      'obs' => ($obs !== '' ? $obs : null),
    ]);

    if ($permitido && (int)($visita['uso_unico'] ?? 0) === 1 && ($visita['estado'] ?? '') !== 'usado') {
      $up = $pdo->prepare("UPDATE visitas SET estado='usado' WHERE id=:id");
      $up->execute(['id' => (int)$visita['id']]);
      $visita['estado'] = 'usado';
    }

    $pdo->commit();

    out(true, [
      'message' => $permitido ? 'Acceso permitido' : 'Acceso denegado',
      'permitido' => $permitido,
      'visita' => $visita,
      'resultado' => $resultado,
      'eval' => $ev,
    ]);
  }

  if ($method === 'POST' && $action === 'scan_residente') {
    $residentId = intv_safe($_POST['resident_id'] ?? 0, 0);
    $tipoEvento = strv($_POST['tipo_evento'] ?? 'entrada', 20);
    $allowedTipoEvento = ['entrada', 'salida', 'verificacion'];

    if ($residentId <= 0) {
      out(false, ['error' => 'Falta seleccionar al residente.'], 422);
    }
    if (!in_array($tipoEvento, $allowedTipoEvento, true)) {
      out(false, ['error' => 'tipo_evento inválido'], 422);
    }

    $resident = resident_access_status_for_user($pdo, $residentId, $residencialId);
    if (!$resident) {
      out(false, ['error' => 'Residente no encontrado en este residencial.'], 404);
    }

    $access = $resident['access'];
    $permitido = (bool)($access['allow_direct_access'] ?? false);
    $resultado = $permitido ? 'permitido' : 'denegado';
    $obs = $permitido ? 'Acceso directo de residente validado.' : (string)($access['reason'] ?? 'Acceso directo denegado.');

    $stmt = $pdo->prepare("
      INSERT INTO accesos_guardia (
        visita_id, guardia_id, fecha_hora, tipo_evento, resultado, observaciones, origen_acceso, residente_id, unidad_id
      ) VALUES (
        0, :gid, NOW(), :tipo, :resultado, :obs, 'residente_directo', :resident_id, :unidad_id
      )
    ");
    $stmt->execute([
      'gid' => $guardiaId,
      'tipo' => $tipoEvento,
      'resultado' => $resultado,
      'obs' => $obs,
      'resident_id' => $residentId,
      'unidad_id' => (int)($resident['unidad_id'] ?? 0) ?: null,
    ]);

    out(true, [
      'message' => $permitido ? 'Acceso directo permitido.' : 'Acceso directo denegado.',
      'permitido' => $permitido,
      'resident' => [
        'user_id' => (int)$resident['user_id'],
        'name' => (string)$resident['name'],
        'email' => (string)$resident['email'],
        'telefono' => (string)$resident['telefono'],
        'unidad_clave' => (string)($resident['unidad_clave'] ?? '—'),
      ],
      'access' => $access,
      'resultado' => $resultado,
    ]);
  }

  if ($method === 'GET' && $action === 'hist') {
    $limit = max(1, min(100, intv_safe($_GET['limit'] ?? 50, 50)));

    $stmt = $pdo->prepare("
      SELECT
        ag.id,
        ag.fecha_hora,
        ag.tipo_evento,
        ag.resultado,
        ag.observaciones,
        v.codigo_acceso,
        v.nombre_visitante,
        v.placa_vehiculo,
        COALESCE(u.clave, uru.clave) AS unidad_clave,
        ag.origen_acceso,
        ru.name AS residente_nombre
      FROM accesos_guardia ag
      LEFT JOIN visitas v ON v.id = ag.visita_id
      LEFT JOIN unidades u ON u.id = v.unidad_id
      LEFT JOIN users ru ON ru.id = ag.residente_id
      LEFT JOIN unidades uru ON uru.id = ag.unidad_id
      WHERE ag.guardia_id = :gid
      ORDER BY ag.fecha_hora DESC, ag.id DESC
      LIMIT :lim
    ");
    $stmt->bindValue(':gid', $guardiaId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();

    out(true, ['data' => ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
  }

  out(false, ['error' => 'Acción no soportada'], 400);

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  app_json_exception($e, 'No pudimos procesar el control de accesos.');
}
