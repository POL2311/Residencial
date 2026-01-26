<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['guardia','admin_residencial','super_admin']);

$user = current_user();
$uid  = (int)($user['id'] ?? 0);

function out(bool $ok, array $extra=[], int $code=200){ http_response_code($code); echo json_encode(array_merge(['ok'=>$ok],$extra), JSON_UNESCAPED_UNICODE); exit; }

$stmt = $pdo->prepare("SELECT residencial_id FROM usuarios_residenciales WHERE user_id=:uid ORDER BY es_principal DESC, created_at ASC LIMIT 1");
$stmt->execute(['uid'=>$uid]);
$rid = (int)($stmt->fetchColumn() ?: 0);
if ($rid<=0) out(false, ['error'=>'No tienes residencial asignado.'], 403);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
  $q = trim((string)($_GET['q'] ?? ''));
  $params = ['rid'=>$rid];
  $and = "";
  if ($q !== '') { $and = " AND (clave LIKE :q OR torre LIKE :q OR calle LIKE :q)"; $params['q']="%$q%"; }

  $stmt = $pdo->prepare("
    SELECT id, clave, tipo, calle, numero_exterior, numero_interior, torre, nivel
    FROM unidades
    WHERE residencial_id=:rid AND activo=1 $and
    ORDER BY clave ASC
    LIMIT 400
  ");
  $stmt->execute($params);
  out(true, ['data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
}

$action = $_POST['action'] ?? '';
if ($method === 'POST' && $action === 'create') {
  $tipo  = (string)($_POST['tipo'] ?? 'casa');
  $clave = trim((string)($_POST['clave'] ?? ''));
  $calle = trim((string)($_POST['calle'] ?? ''));
  $torre = trim((string)($_POST['torre'] ?? ''));
  $nivel = trim((string)($_POST['nivel'] ?? ''));

  $allowed = ['casa','departamento','local','otro'];
  if (!in_array($tipo, $allowed, true)) out(false, ['error'=>'Tipo inválido.'], 422);
  if ($clave === '') out(false, ['error'=>'La clave es obligatoria.'], 422);

  // evita duplicado por residencial (UX)
  $chk = $pdo->prepare("SELECT COUNT(*) FROM unidades WHERE residencial_id=:rid AND clave=:c AND activo=1");
  $chk->execute(['rid'=>$rid,'c'=>$clave]);
  if ((int)$chk->fetchColumn() > 0) out(false, ['error'=>'Ya existe una unidad con esa clave en este residencial.'], 409);

  $stmt = $pdo->prepare("
    INSERT INTO unidades (residencial_id, tipo, clave, calle, torre, nivel, activo, created_at)
    VALUES (:rid, :tipo, :clave, :calle, :torre, :nivel, 1, NOW())
  ");
  $stmt->execute([
    'rid'=>$rid,'tipo'=>$tipo,'clave'=>$clave,
    'calle'=>($calle!==''?$calle:null),
    'torre'=>($torre!==''?$torre:null),
    'nivel'=>($nivel!==''?$nivel:null),
  ]);

  $newId = (int)$pdo->lastInsertId();
  out(true, ['message'=>'Unidad creada.', 'data'=>['item'=>[
    'id'=>$newId,'clave'=>$clave,'tipo'=>$tipo,'calle'=>$calle,'torre'=>$torre,'nivel'=>$nivel
  ]]]);
}

out(false, ['error'=>'Acción no soportada.'], 400);
