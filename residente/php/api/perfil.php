<?php
// /residente/php/api/perfil.php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

require_login();
require_role(['residente']);

$user = current_user();
$uid  = (int)($user['id'] ?? 0);

function json_out(bool $ok, array $extra = []): void {
  echo json_encode(array_merge(['ok' => $ok], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function clean_str(?string $v): string {
  $v = (string)($v ?? '');
  $v = trim(preg_replace('/\s+/', ' ', $v));
  return $v;
}

function join_parts(array $parts, string $sep = ' · '): string {
  $out = [];
  foreach ($parts as $p) {
    $p = clean_str((string)$p);
    if ($p !== '') $out[] = $p;
  }
  return implode($sep, $out);
}

function tableExists(PDO $pdo, string $table): bool {
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

function refresh_session_user(array $newUser): void {
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

  if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    foreach (['name','email','telefono'] as $k) {
      if (array_key_exists($k, $newUser)) $_SESSION['user'][$k] = $newUser[$k];
    }
  }
  if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
    foreach (['name','email','telefono'] as $k) {
      if (array_key_exists($k, $newUser)) $_SESSION['auth_user'][$k] = $newUser[$k];
    }
  }
}

function build_residencial_direccion(array $ctx): ?string {
  $calle = clean_str($ctx['res_calle'] ?? '');
  $ext   = clean_str($ctx['res_numero_exterior'] ?? '');
  $int   = clean_str($ctx['res_numero_interior'] ?? '');
  $col   = clean_str($ctx['res_colonia'] ?? '');
  $cd    = clean_str($ctx['res_ciudad'] ?? '');
  $edo   = clean_str($ctx['res_estado'] ?? '');
  $pais  = clean_str($ctx['res_pais'] ?? '');
  $cp    = clean_str($ctx['res_codigo_postal'] ?? '');

  $line1 = trim($calle . ' ' . $ext . ($int !== '' ? (' Int ' . $int) : ''));
  $line2 = join_parts([
    $col,
    $cd,
    $edo,
    ($cp !== '' ? ('CP ' . $cp) : ''),
    $pais
  ], ', ');

  $full = join_parts([$line1, $line2], ' · ');
  return $full !== '' ? $full : null;
}

function build_unidad_direccion(array $ctx): ?string {
  // Dirección interna (si existe en tu tabla unidades)
  $calle = clean_str($ctx['u_calle'] ?? '');
  $ext   = clean_str($ctx['u_numero_exterior'] ?? '');
  $int   = clean_str($ctx['u_numero_interior'] ?? '');

  $line = trim($calle . ' ' . $ext . ($int !== '' ? (' Int ' . $int) : ''));
  return $line !== '' ? $line : null;
}

function build_unidad_detalle(array $ctx): ?string {
  $torre = clean_str($ctx['u_torre'] ?? '');
  $nivel = clean_str($ctx['u_nivel'] ?? '');

  $parts = [];
  if ($torre !== '') $parts[] = 'Torre ' . $torre;
  if ($nivel !== '') $parts[] = 'Nivel ' . $nivel;

  $full = join_parts($parts, ' · ');
  return $full !== '' ? $full : null;
}

$action = $_POST['action'] ?? ($_GET['action'] ?? 'get');

// ---------- GET ----------
if ($action === 'get') {
  try {
    // User fresh
    $stmtU = $pdo->prepare("SELECT id, name, email, telefono FROM users WHERE id = :id LIMIT 1");
    $stmtU->execute(['id' => $uid]);
    $u = $stmtU->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$u) json_out(false, ['error' => 'Usuario no encontrado.']);

    // Contexto: residencial + unidad
    $stmtC = $pdo->prepare("
      SELECT
        r.id AS residencial_id,
        r.nombre AS residencial_nombre,
        r.pais AS res_pais,
        r.estado AS res_estado,
        r.ciudad AS res_ciudad,
        r.colonia AS res_colonia,
        r.calle AS res_calle,
        r.numero_exterior AS res_numero_exterior,
        r.numero_interior AS res_numero_interior,
        r.codigo_postal AS res_codigo_postal,

        un.id AS unidad_id,
        un.clave AS unidad_clave,
        un.calle AS u_calle,
        un.numero_exterior AS u_numero_exterior,
        un.numero_interior AS u_numero_interior,
        un.torre AS u_torre,
        un.nivel AS u_nivel

      FROM usuarios_residenciales ur
      JOIN residenciales r             ON r.id = ur.residencial_id
      LEFT JOIN residentes_unidades ru ON ru.user_id = ur.user_id
      LEFT JOIN unidades un            ON un.id = ru.unidad_id
      WHERE ur.user_id = :uid
      ORDER BY ur.es_principal DESC, ur.created_at ASC
      LIMIT 1
    ");
    $stmtC->execute(['uid' => $uid]);
    $ctx = $stmtC->fetch(PDO::FETCH_ASSOC) ?: [];

    $residencial_nombre = clean_str($ctx['residencial_nombre'] ?? '');
    $unidad_clave       = clean_str($ctx['unidad_clave'] ?? '');

    // Para header del dashboard
    $header_line = trim(
      ($residencial_nombre !== '' ? ('Residencial: ' . $residencial_nombre) : '') .
      (($residencial_nombre !== '' && $unidad_clave !== '') ? ' · ' : '') .
      ($unidad_clave !== '' ? ('Unidad: ' . $unidad_clave) : '')
    );

    $residencial_direccion = !empty($ctx) ? build_residencial_direccion($ctx) : null;
    $unidad_direccion      = !empty($ctx) ? build_unidad_direccion($ctx) : null;
    $unidad_detalle        = !empty($ctx) ? build_unidad_detalle($ctx) : null;

    // ✅ PERFIL: "direccion" DEBE SER DE LA UNIDAD (interna)
    // 1) Si unidades trae calle/número -> usamos eso
    // 2) Si no trae -> al menos "Unidad C-100"
    // 3) Si ni eso -> vacío
    $direccion = '';
    if ($unidad_direccion) {
      // Si quieres incluir la clave aquí, descomenta:
      // $direccion = $unidad_direccion . ($unidad_clave !== '' ? (' · Unidad ' . $unidad_clave) : '');
      $direccion = $unidad_direccion;
    } elseif ($unidad_clave !== '') {
      $direccion = 'Unidad ' . $unidad_clave;
    } else {
      $direccion = '';
    }

    // Contactos de emergencia
    $hasCE = tableExists($pdo, 'contactos_emergencia');
    $contactos = [];
    $ceSql = '';

    if ($hasCE) {
      $stmtCE = $pdo->prepare("
        SELECT id, nombre, telefono, relacion, created_at
        FROM contactos_emergencia
        WHERE user_id = :uid
        ORDER BY created_at DESC
      ");
      $stmtCE->execute(['uid' => $uid]);
      $contactos = $stmtCE->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
      $ceSql = "CREATE TABLE contactos_emergencia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  telefono VARCHAR(40) NOT NULL,
  relacion VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ce_user (user_id),
  CONSTRAINT fk_ce_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);";
    }

    json_out(true, [
      'data' => [
        'user' => $u,

        // ✅ Lo que pintas en Perfil como "Dirección"
        'direccion' => $direccion,

        // ✅ para header dashboard
        'header_line' => $header_line,

        // Extras (por si luego los quieres mostrar en otra parte)
        'residencial_direccion' => $residencial_direccion,
        'unidad_direccion' => $unidad_direccion,
        'unidad_detalle' => $unidad_detalle,

        'ctx' => [
          'residencial_id' => (int)($ctx['residencial_id'] ?? 0),
          'residencial_nombre' => $residencial_nombre,
          'unidad_id' => (int)($ctx['unidad_id'] ?? 0),
          'unidad_clave' => $unidad_clave
        ],

        'hasCE' => $hasCE,
        'contactos' => $contactos,
        'ceSql' => $ceSql
      ]
    ]);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

// ---------- UPDATE NAME ----------
if ($action === 'update_name') {
  $name = trim((string)($_POST['name'] ?? ''));
  if ($name === '') json_out(false, ['error' => 'El nombre es obligatorio.']);

  try {
    $stmt = $pdo->prepare("UPDATE users SET name = :n WHERE id = :id");
    $stmt->execute(['n' => $name, 'id' => $uid]);

    refresh_session_user(['name' => $name]);
    json_out(true, ['message' => 'Nombre actualizado.']);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

// ---------- UPDATE PHONE (requiere password) ----------
if ($action === 'update_phone') {
  $telefono = trim((string)($_POST['telefono'] ?? ''));
  $current  = (string)($_POST['current_password'] ?? '');

  if ($telefono === '') json_out(false, ['error' => 'El teléfono es obligatorio.']);
  if ($current === '')  json_out(false, ['error' => 'Necesitas tu contraseña actual para autorizar el cambio.']);

  try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['password_hash'])) json_out(false, ['error' => 'No se pudo validar tu contraseña.']);
    if (!password_verify($current, (string)$row['password_hash'])) json_out(false, ['error' => 'Contraseña actual incorrecta.']);

    $upd = $pdo->prepare("UPDATE users SET telefono = :t WHERE id = :id");
    $upd->execute(['t' => $telefono, 'id' => $uid]);

    refresh_session_user(['telefono' => $telefono]);
    json_out(true, ['message' => 'Teléfono actualizado.']);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

// ---------- UPDATE EMAIL (requiere password) ----------
if ($action === 'update_email') {
  $email   = trim((string)($_POST['email'] ?? ''));
  $current = (string)($_POST['current_password'] ?? '');

  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_out(false, ['error' => 'Correo inválido.']);
  }
  if ($current === '') json_out(false, ['error' => 'Necesitas tu contraseña actual para autorizar el cambio.']);

  try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['password_hash'])) json_out(false, ['error' => 'No se pudo validar tu contraseña.']);
    if (!password_verify($current, (string)$row['password_hash'])) json_out(false, ['error' => 'Contraseña actual incorrecta.']);

    $chk = $pdo->prepare("SELECT id FROM users WHERE email = :e AND id <> :id LIMIT 1");
    $chk->execute(['e' => $email, 'id' => $uid]);
    if ($chk->fetch()) json_out(false, ['error' => 'Ese correo ya está en uso.']);

    $upd = $pdo->prepare("UPDATE users SET email = :e WHERE id = :id");
    $upd->execute(['e' => $email, 'id' => $uid]);

    refresh_session_user(['email' => $email]);
    json_out(true, ['message' => 'Correo actualizado.']);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

// ---------- CHANGE PASSWORD ----------
if ($action === 'change_password') {
  $current = (string)($_POST['current_password'] ?? '');
  $new1    = (string)($_POST['new_password'] ?? '');
  $new2    = (string)($_POST['new_password_confirm'] ?? '');

  if ($current === '' || $new1 === '' || $new2 === '') json_out(false, ['error' => 'Completa todos los campos.']);
  if ($new1 !== $new2) json_out(false, ['error' => 'La confirmación no coincide.']);
  if (strlen($new1) < 6) json_out(false, ['error' => 'La nueva contraseña debe tener al menos 6 caracteres.']);

  try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['password_hash'])) json_out(false, ['error' => 'No se pudo validar tu contraseña.']);
    if (!password_verify($current, (string)$row['password_hash'])) json_out(false, ['error' => 'Contraseña actual incorrecta.']);

    $hash = password_hash($new1, PASSWORD_DEFAULT);
    $upd = $pdo->prepare("UPDATE users SET password_hash = :h WHERE id = :id");
    $upd->execute(['h' => $hash, 'id' => $uid]);

    json_out(true, ['message' => 'Contraseña actualizada.']);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

// ---------- CONTACTOS EMERGENCIA ----------
$hasCE = tableExists($pdo, 'contactos_emergencia');
if (!$hasCE && in_array($action, ['add_ce','update_ce','delete_ce'], true)) {
  json_out(false, ['error' => 'No existe la tabla contactos_emergencia.']);
}

if ($action === 'add_ce') {
  $nombre = trim((string)($_POST['ce_nombre'] ?? ''));
  $tel    = trim((string)($_POST['ce_telefono'] ?? ''));
  $rel    = trim((string)($_POST['ce_relacion'] ?? ''));

  if ($nombre === '' || $tel === '') json_out(false, ['error' => 'Nombre y teléfono son obligatorios.']);

  try {
    $stmt = $pdo->prepare("
      INSERT INTO contactos_emergencia (user_id, nombre, telefono, relacion, created_at)
      VALUES (:uid, :n, :t, :r, NOW())
    ");
    $stmt->execute([
      'uid' => $uid,
      'n' => $nombre,
      't' => $tel,
      'r' => ($rel !== '' ? $rel : null),
    ]);
    json_out(true, ['message' => 'Contacto agregado.']);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

if ($action === 'update_ce') {
  $id     = (int)($_POST['ce_id'] ?? 0);
  $nombre = trim((string)($_POST['ce_nombre'] ?? ''));
  $tel    = trim((string)($_POST['ce_telefono'] ?? ''));
  $rel    = trim((string)($_POST['ce_relacion'] ?? ''));

  if ($id <= 0) json_out(false, ['error' => 'Contacto inválido.']);
  if ($nombre === '' || $tel === '') json_out(false, ['error' => 'Nombre y teléfono son obligatorios.']);

  try {
    $stmt = $pdo->prepare("
      UPDATE contactos_emergencia
      SET nombre = :n, telefono = :t, relacion = :r
      WHERE id = :id AND user_id = :uid
    ");
    $stmt->execute([
      'n' => $nombre,
      't' => $tel,
      'r' => ($rel !== '' ? $rel : null),
      'id' => $id,
      'uid' => $uid
    ]);
    json_out(true, ['message' => 'Contacto actualizado.']);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

if ($action === 'delete_ce') {
  $id = (int)($_POST['ce_id'] ?? 0);
  if ($id <= 0) json_out(false, ['error' => 'Contacto inválido.']);

  try {
    $stmt = $pdo->prepare("DELETE FROM contactos_emergencia WHERE id = :id AND user_id = :uid");
    $stmt->execute(['id' => $id, 'uid' => $uid]);
    json_out(true, ['message' => 'Contacto eliminado.']);
  } catch (Throwable $e) {
    json_out(false, ['error' => 'Error: ' . $e->getMessage()]);
  }
}

json_out(false, ['error' => 'Acción no soportada.']);