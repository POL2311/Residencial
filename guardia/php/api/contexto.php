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
require_once __DIR__ . '/../../../config/guardia_schedule.php';

require_login();
require_role(['guardia','super_admin']);

function out(bool $ok, array $data = [], int $code = 200): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>$ok], $data), JSON_UNESCAPED_UNICODE);
  exit;
}

function default_guardia_stats(): array {
  return [
    'accesos_hoy' => 0,
    'incidencias_abiertas' => 0,
    'paquetes_pendientes' => 0,
    'autos_registrados_hoy' => 0,
  ];
}

function guardia_help_steps(string $reasonCode): array {
  return match ($reasonCode) {
    'missing_assignment' => [
      'Confirma que la cuenta del guardia esté activa.',
      'Asigna este guardia al cliente o servicio correcto.',
      'Vuelve a iniciar sesión cuando la asignación esté lista.',
    ],
    'guard_role_disabled' => [
      'Confirma que la cuenta del guardia esté activa.',
      'En Superadmin, abre el perfil del servicio asignado.',
      'Habilita el rol Guardia para ese servicio y guarda cambios.',
    ],
    'guard_role_incompatible' => [
      'Confirma que la cuenta del guardia esté activa.',
      'En Superadmin, abre el perfil del servicio asignado.',
      'Habilita al menos un módulo operable para Guardia, como Control de accesos, Incidencias, Autos o Paquetería.',
      'Guarda cambios y vuelve a iniciar sesión.',
    ],
    'guard_absence_exception' => [
      'Tu cuenta está activa y asignada correctamente.',
      'Hoy tienes una excepción de turno vigente y por eso no puedes operar.',
      'Si necesitas trabajar hoy, pide al administrador o a Superadmin que quite o ajuste la ausencia programada.',
    ],
    default => [
      'Confirma que la cuenta del guardia esté activa.',
      'Verifica que el guardia tenga un servicio asignado.',
      'Revisa que el rol Guardia esté habilitado en el perfil del servicio.',
      'Si el servicio ya aparece como listo en Superadmin, esto puede ser un error interno y conviene recargar o revisar el sistema.',
    ],
  };
}

function pending_payload(array $user, ?int $residencialId, ?string $headerLine, ?string $direccion, string $reasonCode, string $reasonMessage, ?array $profile = null): array {
  return [
    'user' => $user,
    'residencial_id' => $residencialId,
    'header_line' => $headerLine,
    'direccion' => $direccion,
    'turno_actual' => null,
    'guardia_en_servicio' => false,
    'stats' => default_guardia_stats(),
    'notifications' => [
      'total' => 0,
      'latest_id' => 0,
      'latest_updated_at' => null,
      'items' => [],
    ],
    'modo_operacion' => operational_normalize_mode((string)($profile['modo_operacion'] ?? 'residencial')),
    'service_profile' => $profile,
    'can_operate' => false,
    'activation_pending' => true,
    'reason_code' => $reasonCode,
    'reason_message' => $reasonMessage,
    'help_title' => 'Como activarlo',
    'help_steps' => guardia_help_steps($reasonCode),
  ];
}

try {
  operational_schema_ensure($pdo);
  service_profile_schema_ensure($pdo);
  guardia_schedule_schema_ensure($pdo);
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

  if (!$res) {
    out(true, [
      'data' => pending_payload(
        $user,
        null,
        null,
        null,
        'missing_assignment',
        'Tu cuenta esta activa, pero todavia no tiene un servicio asignado.'
      ),
    ]);
  }

  $residencialId = (int)$res['id'];
  $profile = service_profile_get($pdo, $residencialId);
  $header_line = 'Residencial: ' . $res['nombre'];

  $direccion = implode(', ', array_filter([
    $res['calle'] ?? '',
    $res['colonia'] ?? '',
    $res['ciudad'] ?? '',
    $res['estado'] ?? '',
  ]));

  if (!service_profile_role_enabled($profile, 'guardia')) {
    out(true, [
      'data' => pending_payload(
        $user,
        $residencialId,
        $header_line,
        $direccion,
        'guard_role_disabled',
        'Tu cuenta esta activa y asignada, pero el rol Guardia esta deshabilitado para este servicio.',
        service_profile_frontend_payload($pdo, $residencialId, 'guardia')
      ),
    ]);
  }

  if (!service_profile_role_assignable_to_service($profile, 'guardia')) {
    $pendingReason = service_profile_role_pending_reason($profile, 'guardia');
    out(true, [
      'data' => pending_payload(
        $user,
        $residencialId,
        $header_line,
        $direccion,
        'guard_role_incompatible',
        (string)($pendingReason['message'] ?? 'Tu cuenta está asignada, pero sigue pendiente: este servicio todavía no tiene módulos operables para Guardia.'),
        service_profile_frontend_payload($pdo, $residencialId, 'guardia')
      ),
    ]);
  }

  $notifications = resident_access_notifications($pdo, $residencialId, 10);
  $currentTurn = guardia_schedule_find_active_turn($pdo, $residencialId, $uid);
  $currentException = guardia_schedule_find_current_exception($pdo, $residencialId, $uid, null, (int)($currentTurn['id'] ?? 0));

  if ($currentException) {
    out(true, [
      'data' => [
        'user' => $user,
        'residencial_id' => $residencialId,
        'header_line' => $header_line,
        'direccion' => $direccion,
        'turno_actual' => 'Ausente hoy',
        'guardia_en_servicio' => false,
        'stats' => default_guardia_stats(),
        'notifications' => $notifications,
        'modo_operacion' => operational_normalize_mode((string)($res['modo_operacion'] ?? 'residencial')),
        'service_profile' => service_profile_frontend_payload($pdo, $residencialId, 'guardia'),
        'can_operate' => false,
        'activation_pending' => true,
        'reason_code' => 'guard_absence_exception',
        'reason_message' => 'Tienes una ausencia programada hoy' . (!empty($currentException['motivo']) ? ': ' . (string)$currentException['motivo'] : '.') ,
        'help_title' => 'Ausencia programada',
        'help_steps' => guardia_help_steps('guard_absence_exception'),
      ],
    ]);
  }

  $turnoActual = $currentTurn
    ? trim((string)($currentTurn['nombre_turno'] ?? 'Turno activo'))
    : 'Sin turno actual';
  $guardiaEnServicio = $currentTurn ? true : false;

  // métricas demo
  $stats = default_guardia_stats();

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
      'service_profile' => service_profile_frontend_payload($pdo, $residencialId, 'guardia'),
      'can_operate' => true,
      'activation_pending' => false,
      'reason_code' => null,
      'reason_message' => null,
      'help_title' => null,
      'help_steps' => [],
    ]
  ]);

} catch (Throwable $e) {
  app_log_exception($e, 'guardia-contexto');
  out(false, [
    'error' => 'No pudimos cargar el contexto del guardia.',
    'reason_code' => 'context_load_error',
    'reason_message' => 'Hubo un error al cargar tu servicio de guardia.',
    'help_title' => 'Como activarlo',
    'help_steps' => guardia_help_steps('context_load_error'),
  ], 500);
}
