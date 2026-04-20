<?php
// /residente/php/api/home.php
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

try {
  $ctxStatus = resolve_resident_context($pdo, $uid, true);
  $ctx = $ctxStatus['ctx'] ?? null;

  $residencialId = (int)($ctx['residencial_id'] ?? 0);
  $unidadId = (int)($ctx['unidad_id'] ?? 0);

  $stats = [
    'autos' => 0,
    'paquetes_pendientes' => 0,
    'paquetes_entregados' => 0,
    'contactos_emergencia' => 0,
  ];

  try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM autos WHERE propietario_user_id = :uid");
    $stmt->execute(['uid' => $uid]);
    $stats['autos'] = (int)$stmt->fetchColumn();
  } catch (Throwable $e) {
    $stats['autos'] = 0;
  }

  if ($ctxStatus['ok']) {
    try {
      $stmt = $pdo->prepare("
        SELECT
          SUM(CASE WHEN estado = 'registrado' THEN 1 ELSE 0 END) AS pendientes,
          SUM(CASE WHEN estado = 'entregado' THEN 1 ELSE 0 END) AS entregados
        FROM paqueteria
        WHERE residencial_id = :rid AND unidad_id = :uid
      ");
      $stmt->execute(['rid' => $residencialId, 'uid' => $unidadId]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
      $stats['paquetes_pendientes'] = (int)($row['pendientes'] ?? 0);
      $stats['paquetes_entregados'] = (int)($row['entregados'] ?? 0);
    } catch (Throwable $e) {
      $stats['paquetes_pendientes'] = 0;
      $stats['paquetes_entregados'] = 0;
    }
  }

  if (table_exists($pdo, 'contactos_emergencia')) {
    try {
      $stmt = $pdo->prepare("SELECT COUNT(*) FROM contactos_emergencia WHERE user_id = :uid");
      $stmt->execute(['uid' => $uid]);
      $stats['contactos_emergencia'] = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
      $stats['contactos_emergencia'] = 0;
    }
  }

  $announcements = [];
  if ($ctxStatus['ok'] && table_exists($pdo, 'comunicados_residenciales')) {
    try {
      $stmt = $pdo->prepare("
        SELECT titulo, mensaje, tipo, prioridad, fecha_publicacion
        FROM comunicados_residenciales
        WHERE residencial_id = :rid
          AND visible_para_residentes = 1
          AND estado = 'publicado'
        ORDER BY prioridad DESC, fecha_publicacion DESC, id DESC
        LIMIT 4
      ");
      $stmt->execute(['rid' => $residencialId]);
      $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      $announcements = [];
    }
  }

  $services = [];
  if ($ctxStatus['ok'] && table_exists($pdo, 'home_servicios_residenciales')) {
    try {
      $stmt = $pdo->prepare("
        SELECT nombre, descripcion, telefono, whatsapp, categoria
        FROM home_servicios_residenciales
        WHERE residencial_id = :rid
          AND activo = 1
        ORDER BY orden ASC, id ASC
        LIMIT 6
      ");
      $stmt->execute(['rid' => $residencialId]);
      $services = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      $services = [];
    }
  }

  $recentPackages = [];
  if ($ctxStatus['ok']) {
    try {
      $stmt = $pdo->prepare("
        SELECT id, empresa, descripcion, estado, created_at, updated_at
        FROM paqueteria
        WHERE residencial_id = :rid AND unidad_id = :uid
        ORDER BY updated_at DESC, id DESC
        LIMIT 5
      ");
      $stmt->execute(['rid' => $residencialId, 'uid' => $unidadId]);
      $recentPackages = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
      $recentPackages = [];
    }
  }

  $tips = [
    [
      'title' => 'Mantén tu perfil actualizado',
      'text' => 'Revisa tu teléfono y contactos de emergencia para responder más rápido ante cualquier incidente.',
    ],
    [
      'title' => 'Confirma tus paquetes',
      'text' => 'Marca entregas o devoluciones para que caseta y administración tengan el mismo estado.',
    ],
    [
      'title' => 'Revisa tus autos registrados',
      'text' => 'Tener placas actualizadas evita retrasos en accesos y validaciones internas.',
    ],
  ];

  json_out(true, [
    'data' => [
      'ctx' => $ctx,
      'stats' => $stats,
      'announcements' => $announcements,
      'services' => $services,
      'recent_packages' => $recentPackages,
      'tips' => $tips,
      'setup_incomplete' => !$ctxStatus['ok'],
      'setup_message' => !$ctxStatus['ok'] ? (string)($ctxStatus['error'] ?? 'Falta configurar el contexto del residente.') : null,
      'setup_missing' => !$ctxStatus['ok'] ? ($ctxStatus['missing'] ?? []) : [],
    ]
  ]);
} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos cargar el inicio del residente.');
}
