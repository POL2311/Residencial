<?php
// /residente/php/api/contexto.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['residente']);

$sessionUser = current_user();
$uid = (int)($sessionUser['id'] ?? 0);

function json_out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function clean_str(?string $v): string {
  $v = (string)($v ?? '');
  $v = trim(preg_replace('/\s+/', ' ', $v));
  return $v;
}

function table_exists(PDO $pdo, string $table): bool {
  try {
    $stmt = $pdo->prepare("
      SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
    ");
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
  } catch (Throwable $e) {
    return false;
  }
}

function join_parts(array $parts, string $sep = ' · '): string {
  $out = [];
  foreach ($parts as $p) {
    $p = clean_str((string)$p);
    if ($p !== '') $out[] = $p;
  }
  return implode($sep, $out);
}

function service_entity_label(?string $mode): string {
  $normalized = operational_normalize_mode((string)($mode ?? 'residencial'));
  return $normalized === 'residencial' ? 'Residencial' : 'Servicio';
}

try {
  service_profile_schema_ensure($pdo);
  // 1) Usuario fresco desde BD (evita datos viejos de sesión)
  $stmtU = $pdo->prepare("SELECT id, name, email, telefono FROM users WHERE id = :id LIMIT 1");
  $stmtU->execute(['id' => $uid]);
  $user = $stmtU->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    json_out(false, ['error' => 'Usuario no encontrado.']);
  }

  // 2) Contexto: residencial + unidad (+ campos de dirección general)
  $ctxStatus = resolve_resident_context($pdo, $uid, true);
  $ctx = null;
  $setupIncomplete = !$ctxStatus['ok'];

  if (!$setupIncomplete) {
    $stmtRU = $pdo->prepare("
      SELECT
        r.id     AS residencial_id,
        r.nombre AS residencial_nombre,
        r.modo_operacion AS modo_operacion,
        r.calle  AS res_calle,
        r.numero_exterior AS res_numero_exterior,
        r.numero_interior AS res_numero_interior,
        r.colonia AS res_colonia,
        r.ciudad  AS res_ciudad,
        r.estado  AS res_estado,
        r.codigo_postal AS res_codigo_postal,

        u.id     AS unidad_id,
        u.clave  AS unidad_clave,
        u.torre  AS unidad_torre,
        u.nivel  AS unidad_nivel,
        u.numero_interior AS unidad_numero_interior

      FROM usuarios_residenciales ur
      JOIN residenciales r        ON r.id = ur.residencial_id
      JOIN residentes_unidades ru ON ru.user_id = ur.user_id AND ru.activo = 1
      JOIN unidades u             ON u.id = ru.unidad_id
      WHERE ur.user_id = :user_id
      ORDER BY ur.es_principal DESC, ur.created_at ASC
      LIMIT 1
    ");
    $stmtRU->execute(['user_id' => $uid]);
    $ctx = $stmtRU->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  // 3) Header line (lo “correcto” para tu app)
  $header_line = null;
  $direccion = null; // compatibilidad: lo que ya estabas usando
  $residencial_direccion = null;
  $unidad_detalle = null;

  if ($ctx) {
    $profile = service_profile_api_require_module($pdo, (int)$ctx['residencial_id'], 'residente', 'contexto', 'El portal de residente no está habilitado para este cliente.');
    $resName = clean_str($ctx['residencial_nombre'] ?? '');
    $unidad  = clean_str($ctx['unidad_clave'] ?? '');
    $entityLabel = service_entity_label((string)($ctx['modo_operacion'] ?? 'residencial'));

    $header_line = trim(
      ($resName !== '' ? ($entityLabel . ': ' . $resName) : '') .
      (($resName !== '' && $unidad !== '') ? ' · ' : '') .
      ($unidad !== '' ? ('Unidad: ' . $unidad) : '')
    );

    // compatibilidad
    $direccion = $header_line;

    // Dirección GENERAL del residencial (si existe)
    $line1 = join_parts([
      $ctx['res_calle'] ?? '',
      $ctx['res_numero_exterior'] ?? '',
      ($ctx['res_numero_interior'] ?? '') !== '' ? ('Int ' . $ctx['res_numero_interior']) : ''
    ], ' ');

    $line2 = join_parts([
      $ctx['res_colonia'] ?? '',
      $ctx['res_ciudad'] ?? '',
      $ctx['res_estado'] ?? '',
      ($ctx['res_codigo_postal'] ?? '') !== '' ? ('CP ' . $ctx['res_codigo_postal']) : ''
    ], ', ');

    $residencial_direccion = join_parts([$line1, $line2], ' · ');
    if ($residencial_direccion === '') $residencial_direccion = null;

    // Detalle interno de unidad (torre/nivel/interior si existe)
    $unidad_detalle = join_parts([
      ($ctx['unidad_torre'] ?? '') !== '' ? ('Torre ' . $ctx['unidad_torre']) : '',
      ($ctx['unidad_nivel'] ?? '') !== '' ? ('Nivel ' . $ctx['unidad_nivel']) : '',
      ($ctx['unidad_numero_interior'] ?? '') !== '' ? ('Int ' . $ctx['unidad_numero_interior']) : '',
    ], ' · ');
    if ($unidad_detalle === '') $unidad_detalle = null;
  }

  // 4) Autos del usuario
  $autos = [];
  try {
    $stmtA = $pdo->prepare("
      SELECT id, placas, modelo, color
      FROM autos
      WHERE propietario_user_id = :uid
      ORDER BY created_at DESC
      LIMIT 20
    ");
    $stmtA->execute(['uid' => $uid]);
    $autos = $stmtA->fetchAll(PDO::FETCH_ASSOC) ?: [];
  } catch (Throwable $e) { $autos = []; }

  $notifications = [
    'total' => 0,
    'latest_id' => 0,
    'latest_updated_at' => null,
  ];

  if ($ctx && table_exists($pdo, 'comunicados_residenciales')) {
    try {
      $stmtN = $pdo->prepare("
        SELECT
          COUNT(*) AS total,
          MAX(id) AS latest_id,
          MAX(COALESCE(updated_at, fecha_publicacion)) AS latest_updated_at
        FROM comunicados_residenciales
        WHERE residencial_id = :rid
          AND visible_para_residentes = 1
          AND estado = 'publicado'
      ");
      $stmtN->execute(['rid' => (int)$ctx['residencial_id']]);
      $rowN = $stmtN->fetch(PDO::FETCH_ASSOC) ?: [];
      $notifications = [
        'total' => (int)($rowN['total'] ?? 0),
        'latest_id' => (int)($rowN['latest_id'] ?? 0),
        'latest_updated_at' => $rowN['latest_updated_at'] ?? null,
      ];
    } catch (Throwable $e) {
      $notifications = [
        'total' => 0,
        'latest_id' => 0,
        'latest_updated_at' => null,
      ];
    }
  }

  json_out(true, [
    'data' => [
      'user' => $user,
      'ctx'  => $ctx,
      // lo que ya usas en el header
      'direccion' => $direccion,
      'header_line' => $header_line,

      // extras por si luego los quieres mostrar en perfil u otro módulo
      'residencial_direccion' => $residencial_direccion,
      'unidad_detalle' => $unidad_detalle,
      'autos' => $autos,
      'notifications' => $notifications,
      'service_profile' => isset($profile) ? service_profile_frontend_payload($pdo, (int)$ctx['residencial_id'], 'residente') : null,
      'setup_incomplete' => $setupIncomplete,
      'setup_message' => $setupIncomplete ? (string)($ctxStatus['error'] ?? 'Falta configurar el contexto del residente.') : null,
      'setup_missing' => $setupIncomplete ? ($ctxStatus['missing'] ?? []) : []
    ]
  ]);

} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos cargar el contexto del residente.');
}
