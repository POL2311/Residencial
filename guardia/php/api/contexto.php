<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/operational_mode.php';
require_once __DIR__ . '/../../../config/service_profile.php';
require_once __DIR__ . '/../../../config/resident_access.php';

require_login();
require_role(['guardia','super_admin']);

function out(bool $ok, array $data = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  operational_schema_ensure($pdo);
  service_profile_schema_ensure($pdo);
  $session = current_user();
  $uid = (int)($session['id'] ?? 0);

  $stmtU = $pdo->prepare("
    SELECT id, name, email
    FROM users
    WHERE id = :id
    LIMIT 1
  ");
  $stmtU->execute(['id'=>$uid]);
  $user = $stmtU->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    out(false, ['error'=>'Usuario no encontrado'], 404);
  }

  $stmtR = $pdo->prepare("
    SELECT
      r.id,
      r.nombre,
      r.modo_operacion,
      r.calle,
      r.colonia,
      r.ciudad,
      r.estado
    FROM usuarios_residenciales ur
    JOIN residenciales r ON r.id = ur.residencial_id
    WHERE ur.user_id = :uid
    ORDER BY ur.es_principal DESC, ur.created_at ASC
    LIMIT 1
  ");
  $stmtR->execute(['uid'=>$uid]);
  $res = $stmtR->fetch(PDO::FETCH_ASSOC);

  $header_line = null;
  $direccion = null;
  $residencialId = null;
  $notifications = [
    'total' => 0,
    'latest_id' => 0,
    'latest_updated_at' => null,
    'items' => [],
  ];

  if ($res) {
    $residencialId = (int)$res['id'];
    $profile = service_profile_api_require_module($pdo, $residencialId, 'guardia', 'contexto', 'El panel de guardia no está habilitado para este cliente.');
    $header_line = 'Residencial: ' . $res['nombre'];

    $direccion = implode(', ', array_filter([
      $res['calle'] ?? '',
      $res['colonia'] ?? '',
      $res['ciudad'] ?? '',
      $res['estado'] ?? '',
    ]));

    $notifications = resident_access_notifications($pdo, $residencialId, 10);
  }

  // turno actual demo
  $turnoActual = 'Turno activo';
  $guardiaEnServicio = true;

  // métricas demo
  $stats = [
    'accesos_hoy' => 0,
    'incidencias_abiertas' => 0,
    'paquetes_pendientes' => 0,
    'autos_registrados_hoy' => 0,
  ];

  out(true, [
    'data' => [
      'user' => $user,
      'residencial_id' => $residencialId,
      'header_line' => $header_line,
      'direccion' => $direccion,
      'turno_actual' => $turnoActual,
      'guardia_en_servicio' => $guardiaEnServicio,
      'stats' => $stats,
      'notifications' => $notifications,
      'modo_operacion' => operational_normalize_mode((string)($res['modo_operacion'] ?? 'residencial')),
      'service_profile' => isset($profile) ? service_profile_frontend_payload($pdo, $residencialId, 'guardia') : null,
    ]
  ]);

} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos cargar el contexto del guardia.');
}
