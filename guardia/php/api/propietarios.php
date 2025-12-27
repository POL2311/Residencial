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
  if ($q !== '') { $and = " AND (u.name LIKE :q OR u.email LIKE :q)"; $params['q']="%$q%"; }

  $stmt = $pdo->prepare("
    SELECT DISTINCT u.id, u.name, u.email, u.telefono
    FROM usuarios_residenciales ur
    JOIN users u ON u.id = ur.user_id
    WHERE ur.residencial_id = :rid AND u.is_active=1
    $and
    ORDER BY u.name ASC
    LIMIT 600
  ");
  $stmt->execute($params);
  out(true, ['data'=>['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []]]);
}

$action = $_POST['action'] ?? '';
if ($method === 'POST' && $action === 'create') {
  $name     = trim((string)($_POST['name'] ?? ''));
  $email    = strtolower(trim((string)($_POST['email'] ?? '')));
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $unidadId = (int)($_POST['unidad_id'] ?? 0);

  if ($name === '') out(false, ['error'=>'El nombre es obligatorio.'], 422);
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, ['error'=>'Email inválido.'], 422);

  // generar password temporal (para que el guardia se lo comparta)
  $tempPass = substr(bin2hex(random_bytes(4)), 0, 8); // 8 chars
  $hash = password_hash($tempPass, PASSWORD_DEFAULT);

  try {
    $pdo->beginTransaction();

    // 5 = residente
    $stmt = $pdo->prepare("
      INSERT INTO users (tipo_usuario_id, name, email, telefono, password_hash, is_active, created_at)
      VALUES (5, :name, :email, :tel, :ph, 1, NOW())
    ");
    $stmt->execute(['name'=>$name,'email'=>$email,'tel'=>($telefono!==''?$telefono:null),'ph'=>$hash]);
    $newUid = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
      INSERT INTO usuarios_residenciales (user_id, residencial_id, es_principal, created_at)
      VALUES (:u, :rid, 1, NOW())
    ");
    $stmt->execute(['u'=>$newUid,'rid'=>$rid]);

    if ($unidadId > 0) {
      $stmt = $pdo->prepare("
        INSERT INTO residentes_unidades (user_id, unidad_id, es_titular, activo, created_at)
        VALUES (:u, :un, 1, 1, NOW())
      ");
      $stmt->execute(['u'=>$newUid,'un'=>$unidadId]);
    }

    $pdo->commit();

    out(true, [
      'message'=>'Propietario creado.',
      'data'=>[
        'item'=>['id'=>$newUid,'name'=>$name,'email'=>$email,'telefono'=>$telefono],
        'temp_password'=>$tempPass
      ]
    ]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    // email unique
    if ((string)$e->getCode()==='23000' && str_contains($e->getMessage(),'email')) {
      out(false, ['error'=>'Ese email ya existe. Usa otro o búscalo en la lista.'], 409);
    }
    out(false, ['error'=>'Error: '.$e->getMessage()], 500);
  }
}

out(false, ['error'=>'Acción no soportada.'], 400);
