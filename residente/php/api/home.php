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
require_once __DIR__ . '/../../../config/api_helpers.php';
require_once __DIR__ . '/../../../config/comunicados_helpers.php';

require_login();
require_role(['residente']);

$sessionUser = current_user();
$uid = (int)($sessionUser['id'] ?? 0);

try {
  comunicados_schema_ensure($pdo);

  $ctxStatus = resolve_resident_context($pdo, $uid, true);
  $ctx = $ctxStatus['ctx'] ?? null;

  $stmtRid = $pdo->prepare("
    SELECT residencial_id
    FROM usuarios_residenciales
    WHERE user_id = ?
    ORDER BY es_principal DESC, created_at ASC
    LIMIT 1
  ");
  $stmtRid->execute([$uid]);
  $residencialId = (int)$stmtRid->fetchColumn();

  $banners = [];
  if ($residencialId > 0 && tableExists($pdo, 'comunicados_residenciales')) {
    $stmtCom = $pdo->prepare("
      SELECT
        id,
        titulo,
        mensaje,
        imagen_url,
        tipo,
        prioridad,
        fecha_publicacion,
        fecha_expiracion
      FROM comunicados_residenciales
      WHERE residencial_id = :rid
        AND estado = 'publicado'
        AND fecha_publicacion <= CURDATE()
        AND (fecha_expiracion IS NULL OR fecha_expiracion >= CURDATE())
      ORDER BY
        CASE prioridad
          WHEN 'alta' THEN 3
          WHEN 'media' THEN 2
          ELSE 1
        END DESC,
        fecha_publicacion DESC,
        id DESC
      LIMIT 5
    ");
    $stmtCom->execute(['rid' => $residencialId]);
    $rows = $stmtCom->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as $row) {
      $raw = (string)($row['mensaje'] ?? '');
      $raw = preg_replace('/\\s+/u', ' ', trim($raw)) ?: '';
      $sub = function_exists('mb_substr') ? mb_substr($raw, 0, 110) : substr($raw, 0, 110);
      if (strlen($raw) > 110) {
        $sub .= '…';
      }
      $banners[] = [
        'id' => (int)($row['id'] ?? 0),
        'titulo' => (string)($row['titulo'] ?? ''),
        'subtitulo' => $sub,
        'imagen_url' => (string)($row['imagen_url'] ?? ''),
        'categoria' => (string)($row['tipo'] ?? 'general'),
        'link_url' => '#comunicados',
        'orden' => 0,
      ];
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
      'banners' => $banners,
      'tips' => $tips,
      'setup_incomplete' => !$ctxStatus['ok'],
      'setup_message' => !$ctxStatus['ok'] ? (string)($ctxStatus['error'] ?? 'Falta configurar el contexto del residente.') : null,
      'setup_missing' => !$ctxStatus['ok'] ? ($ctxStatus['missing'] ?? []) : [],
    ]
  ]);
} catch (Throwable $e) {
  app_json_exception($e, 'No pudimos cargar el inicio del residente.');
}
