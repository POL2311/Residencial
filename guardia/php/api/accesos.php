<?php
// guardia/php/api/accesos.php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/auth.php';

require_login();
require_role(['guardia','super_admin']);

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
        u.clave AS unidad_clave
      FROM accesos_guardia ag
      LEFT JOIN visitas v ON v.id = ag.visita_id
      LEFT JOIN unidades u ON u.id = v.unidad_id
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
  out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}