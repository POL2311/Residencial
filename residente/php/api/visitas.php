<?php
// /residente/php/api/visitas.php
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

function build_code(): string {
  return bin2hex(random_bytes(5));
}

try {
  $ctx = get_context($pdo, $uid);
  $rid = (int)$ctx['residencial_id'];
  $unidadId = (int)$ctx['unidad_id'];

  if ($action === 'list') {
    $stmt = $pdo->prepare("
      SELECT *
      FROM visitas
      WHERE residencial_id = :rid
        AND unidad_id = :unidad
        AND residente_id = :uid
      ORDER BY created_at DESC, id DESC
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
    $tipo = clean_string($_POST['tipo'] ?? 'visita', 40);
    $nombre = clean_string($_POST['nombre_visitante'] ?? '', 150);
    $motivo = clean_string($_POST['motivo'] ?? '', 255);
    $placa = clean_string($_POST['placa_vehiculo'] ?? '', 20);
    $fechaDesde = clean_string($_POST['fecha_desde'] ?? '', 20);
    $fechaHasta = clean_string($_POST['fecha_hasta'] ?? '', 20);
    $horaDesde = clean_string($_POST['hora_desde'] ?? '', 20);
    $horaHasta = clean_string($_POST['hora_hasta'] ?? '', 20);
    $usoUnico = (int)($_POST['uso_unico'] ?? 1) === 1 ? 1 : 0;
    $notas = trim((string)($_POST['notas'] ?? ''));

    if (!in_array($tipo, ['visita', 'servicio', 'trabajador_recurrente'], true)) $tipo = 'visita';
    if ($nombre === '') json_out(false, ['error' => 'El nombre del visitante es obligatorio.'], 422);
    if ($fechaDesde === '' || $fechaHasta === '') json_out(false, ['error' => 'Las fechas son obligatorias.'], 422);

    $stmt = $pdo->prepare("
      INSERT INTO visitas (
        residencial_id, unidad_id, residente_id, tipo, nombre_visitante, motivo,
        placa_vehiculo, fecha_desde, fecha_hasta, hora_desde, hora_hasta, uso_unico,
        codigo_acceso, estado, notas, created_at, updated_at
      ) VALUES (
        :rid, :unidad, :uid, :tipo, :nombre, :motivo,
        :placa, :fecha_desde, :fecha_hasta, :hora_desde, :hora_hasta, :uso_unico,
        :codigo, 'pendiente', :notas, NOW(), NOW()
      )
    ");
    $stmt->execute([
      'rid' => $rid,
      'unidad' => $unidadId,
      'uid' => $uid,
      'tipo' => $tipo,
      'nombre' => $nombre,
      'motivo' => ($motivo !== '' ? $motivo : null),
      'placa' => ($placa !== '' ? $placa : null),
      'fecha_desde' => $fechaDesde,
      'fecha_hasta' => $fechaHasta,
      'hora_desde' => ($horaDesde !== '' ? $horaDesde : null),
      'hora_hasta' => ($horaHasta !== '' ? $horaHasta : null),
      'uso_unico' => $usoUnico,
      'codigo' => build_code(),
      'notas' => ($notas !== '' ? $notas : null),
    ]);

    json_out(true, ['message' => 'Visita registrada correctamente.']);
  }

  if ($action === 'cancel') {
    $visitId = (int)($_POST['visit_id'] ?? 0);
    if ($visitId <= 0) json_out(false, ['error' => 'Visita inválida.'], 422);

    $stmt = $pdo->prepare("
      UPDATE visitas
      SET estado = 'cancelado', updated_at = NOW()
      WHERE id = :id
        AND residencial_id = :rid
        AND unidad_id = :unidad
        AND residente_id = :uid
        AND estado = 'pendiente'
    ");
    $stmt->execute([
      'id' => $visitId,
      'rid' => $rid,
      'unidad' => $unidadId,
      'uid' => $uid,
    ]);

    if ($stmt->rowCount() < 1) {
      json_out(false, ['error' => 'Solo puedes cancelar visitas pendientes.'], 422);
    }

    json_out(true, ['message' => 'Visita cancelada correctamente.']);
  }

  json_out(false, ['error' => 'Accion no soportada.'], 400);
} catch (Throwable $e) {
  json_out(false, ['error' => 'Error: ' . $e->getMessage()], 500);
}
