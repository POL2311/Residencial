<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';
require_once __DIR__ . '/../../../config/service_profile.php';

require_login();
require_role(['residente']);

$uid = (int)(current_user()['id'] ?? 0);

function json_out(bool $ok, array $extra = [], int $status = 200): void {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
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
  residential_home_services_schema_ensure($pdo);
  service_profile_schema_ensure($pdo);
  $status = resolve_resident_context($pdo, $uid, false);
  if (!$status['ok']) {
    json_out(false, [
      'error' => $status['error'] ?? 'No se pudo resolver el contexto del residente.',
      'missing' => $status['missing'] ?? [],
      'ctx' => $status['ctx'] ?? null,
    ], (int)($status['http_status'] ?? 403));
  }

  $ctx = $status['ctx'] ?? [];
  $rid = (int)($ctx['residencial_id'] ?? 0);
  service_profile_api_require_module($pdo, $rid, 'residente', 'servicios', 'Los servicios no están habilitados para este cliente.');

  $items = [];

  if (table_exists($pdo, 'home_servicios_globales')) {
    try {
      $stmt = $pdo->query("
        SELECT id, nombre, descripcion, imagen_url, telefono, whatsapp, link_url, perfil_url, categoria, orden
        FROM home_servicios_globales
        WHERE activo = 1
        ORDER BY orden ASC, id DESC
      ");
      foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        $row['origen'] = 'global';
        $items[] = $row;
      }
    } catch (Throwable $e) {
      // ignore and continue with residential services
    }
  }

  if ($rid > 0 && table_exists($pdo, 'home_servicios_residenciales')) {
    try {
      $stmt = $pdo->prepare("
        SELECT id, nombre, descripcion, imagen_url, telefono, whatsapp, link_url, perfil_url, categoria, orden
        FROM home_servicios_residenciales
        WHERE residencial_id = :rid
          AND activo = 1
        ORDER BY orden ASC, id DESC
      ");
      $stmt->execute(['rid' => $rid]);
      foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        $row['origen'] = 'residencial';
        $items[] = $row;
      }
    } catch (Throwable $e) {
      // ignore and return what is available
    }
  }

  json_out(true, [
    'data' => [
      'ctx' => $ctx,
      'items' => $items,
    ]
  ]);
} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos cargar los servicios.');
}
