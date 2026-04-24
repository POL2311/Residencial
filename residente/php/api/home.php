<?php
// /residente/php/api/home.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/residencial_helpers.php';

require_login();
require_role(['residente']);

$sessionUser = current_user();
$uid = (int)($sessionUser['id'] ?? 0);

function json_out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $ctxStatus = resolve_resident_context($pdo, $uid, true);
  $ctx = $ctxStatus['ctx'] ?? null;

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
      'tips' => $tips,
      'setup_incomplete' => !$ctxStatus['ok'],
      'setup_message' => !$ctxStatus['ok'] ? (string)($ctxStatus['error'] ?? 'Falta configurar el contexto del residente.') : null,
      'setup_missing' => !$ctxStatus['ok'] ? ($ctxStatus['missing'] ?? []) : [],
    ]
  ]);
} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos cargar el inicio del residente.');
}
