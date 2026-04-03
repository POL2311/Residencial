<?php
// /residente/php/api/autos.php
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

/**
 * Devuelve meta por columna:
 *  [
 *    'col' => ['name'=>..., 'nullable'=>bool, 'default'=>mixed, 'extra'=>string]
 *  ]
 */
function getColsMeta(PDO $pdo, string $table): array {
  try {
    $stmt = $pdo->prepare("
      SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
      ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute(['t' => $table]);

    $out = [];
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
      $name = (string)$r['COLUMN_NAME'];
      $out[$name] = [
        'name'     => $name,
        'nullable' => ((string)$r['IS_NULLABLE'] === 'YES'),
        'default'  => $r['COLUMN_DEFAULT'],
        'extra'    => (string)($r['EXTRA'] ?? ''),
      ];
    }
    return $out;
  } catch (Throwable $e) {
    return [];
  }
}

function pickCol(array $cols, array $candidates): ?string {
  foreach ($candidates as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return null;
}

function clean(string $v): string {
  $v = trim(preg_replace('/\s+/', ' ', $v));
  return $v;
}

function colRequired(array $meta, ?string $col): bool {
  if (!$col) return false;
  if (!isset($meta[$col])) return false;

  $extra = strtolower((string)($meta[$col]['extra'] ?? ''));
  if (str_contains($extra, 'auto_increment')) return false;

  $nullable = (bool)($meta[$col]['nullable'] ?? true);
  $default  = $meta[$col]['default'] ?? null;

  return ($nullable === false && $default === null);
}

/**
 * Contexto (residencial + unidad) del residente.
 */
function getResidentContext(PDO $pdo, int $uid): array {
  try {
    $stmt = $pdo->prepare("
      SELECT
        ur.residencial_id,
        ru.unidad_id
      FROM usuarios_residenciales ur
      LEFT JOIN residentes_unidades ru ON ru.user_id = ur.user_id
      WHERE ur.user_id = :uid
      ORDER BY ur.es_principal DESC, ur.created_at ASC
      LIMIT 1
    ");
    $stmt->execute(['uid' => $uid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    return [
      'residencial_id' => (int)($row['residencial_id'] ?? 0),
      'unidad_id'      => (int)($row['unidad_id'] ?? 0),
    ];
  } catch (Throwable $e) {
    return ['residencial_id' => 0, 'unidad_id' => 0];
  }
}

function isDuplicatePlates(PDOException $e): bool {
  $msg  = $e->getMessage();
  $code = (string)$e->getCode();

  // MySQL dup: SQLSTATE 23000 + error 1062 (suele venir en el mensaje)
  if ($code === '23000' && str_contains($msg, '1062')) return true;

  // Por si MySQL lo trae distinto, detectamos el nombre de tu unique
  if (str_contains($msg, 'uq_residencial_placas')) return true;

  return false;
}

function friendlyDbError(Throwable $e): string {
  if ($e instanceof PDOException && isDuplicatePlates($e)) {
    return 'Estas placas ya están registradas en este residencial. Verifica e intenta con otras.';
  }
  return 'Error: ' . $e->getMessage();
}

// 1) Detectar tabla
$candidateTables = [
  'autos',
  'autos_residentes',
  'autos_usuarios',
  'vehiculos',
  'vehiculos_residentes',
  'residentes_autos',
];

$table = null;
foreach ($candidateTables as $t) {
  if (tableExists($pdo, $t)) { $table = $t; break; }
}
if (!$table) {
  json_out(false, ['error' => 'No existe ninguna tabla de autos/vehículos (probé: ' . implode(', ', $candidateTables) . ').']);
}

$meta = getColsMeta($pdo, $table);
$cols = array_keys($meta);

// 2) Mapear columnas (incluye residencial/unidad)
$colId        = pickCol($cols, ['id']);
$colUser      = pickCol($cols, ['residente_id','user_id','propietario_user_id','owner_user_id','usuario_id']);
$colResid     = pickCol($cols, ['residencial_id','residencia_id','residential_id']);
$colUnidad    = pickCol($cols, ['unidad_id','unit_id']);
$colPlacas    = pickCol($cols, ['placas','placa','matricula']);
$colModelo    = pickCol($cols, ['modelo','model','marca_modelo','descripcion_modelo']);
$colColor     = pickCol($cols, ['color','colour']);
$colActivo    = pickCol($cols, ['activo','active','estatus','status']);
$colCreatedAt = pickCol($cols, ['created_at','fecha_creado','created']);
$colUpdatedAt = pickCol($cols, ['updated_at','fecha_actualizado','updated']);

if (!$colId || !$colPlacas) {
  json_out(false, ['error' => "La tabla detectada ($table) no tiene columnas mínimas (id/placas)."]);
}
if (!$colUser) {
  json_out(false, ['error' => "La tabla detectada ($table) no tiene columna para relacionar autos con el usuario (residente_id/user_id/propietario_user_id/etc)."]);
}

// 3) Contexto (necesario si residencial_id / unidad_id son requeridos)
$ctx = getResidentContext($pdo, $uid);
$residencial_id = (int)$ctx['residencial_id'];
$unidad_id      = (int)$ctx['unidad_id'];

if (colRequired($meta, $colResid) && $residencial_id <= 0) {
  json_out(false, ['error' => "Tu tabla ($table) requiere $colResid y no pude obtener residencial_id para este usuario."]);
}
if (colRequired($meta, $colUnidad) && $unidad_id <= 0) {
  json_out(false, ['error' => "Tu tabla ($table) requiere $colUnidad y no pude obtener unidad_id para este usuario."]);
}

// WHERE base: autos del usuario (y si existe, también del residencial/unidad del usuario)
$whereParts = ["$colUser = :uid"];
$params = ['uid' => $uid];

if ($colResid)  { $whereParts[] = "$colResid = :rid"; $params['rid'] = $residencial_id; }
if ($colUnidad) { $whereParts[] = "$colUnidad = :unid"; $params['unid'] = $unidad_id; }

$whereSql = "WHERE " . implode(' AND ', $whereParts);

function listAutos(PDO $pdo, string $table, string $whereSql, array $params,
                   string $colId, string $colPlacas, ?string $colModelo, ?string $colColor): array {

  $select = [
    "$colId AS id",
    "$colPlacas AS placas",
  ];
  $select[] = $colModelo ? "$colModelo AS modelo" : "NULL AS modelo";
  $select[] = $colColor  ? "$colColor  AS color"  : "NULL AS color";

  $sql = "SELECT " . implode(', ', $select) . " FROM $table $whereSql ORDER BY $colId DESC LIMIT 200";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);

  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  return array_map(function($r){
    return [
      'id'     => (int)($r['id'] ?? 0),
      'placas' => (string)($r['placas'] ?? ''),
      'modelo' => (string)($r['modelo'] ?? ''),
      'color'  => (string)($r['color'] ?? ''),
    ];
  }, $rows);
}

$action = $_POST['action'] ?? 'list';

// LIST
if ($action === 'list') {
  try {
    $autos = listAutos($pdo, $table, $whereSql, $params, $colId, $colPlacas, $colModelo, $colColor);
    json_out(true, ['data' => ['autos' => $autos, 'table' => $table]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => friendlyDbError($e)]);
  }
}

// CREATE
if ($action === 'create') {
  $placas = strtoupper(clean((string)($_POST['placas'] ?? '')));
  $modelo = clean((string)($_POST['modelo'] ?? ''));
  $color  = clean((string)($_POST['color'] ?? ''));

  if ($placas === '') json_out(false, ['error' => 'Las placas son obligatorias.']);

  $fields = [];
  $vals   = [];
  $insP   = [];

  $fields[] = $colUser;   $vals[] = ':uid';     $insP['uid'] = $uid;
  $fields[] = $colPlacas; $vals[] = ':placas';  $insP['placas'] = $placas;

  if ($colResid)  { $fields[] = $colResid;  $vals[] = ':rid';  $insP['rid']  = $residencial_id; }
  if ($colUnidad) { $fields[] = $colUnidad; $vals[] = ':unid'; $insP['unid'] = $unidad_id; }

  if ($colModelo) { $fields[] = $colModelo; $vals[] = ':modelo'; $insP['modelo'] = ($modelo !== '' ? $modelo : null); }
  if ($colColor)  { $fields[] = $colColor;  $vals[] = ':color';  $insP['color']  = ($color  !== '' ? $color  : null); }

  if ($colActivo && !in_array($colActivo, $fields, true)) {
    $fields[] = $colActivo; $vals[] = ':activo'; $insP['activo'] = 1;
  }

  if ($colCreatedAt && colRequired($meta, $colCreatedAt) && !in_array($colCreatedAt, $fields, true)) {
    $fields[] = $colCreatedAt;
    $vals[]   = "NOW()";
  }

  try {
    $sql = "INSERT INTO $table (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($insP);

    $autos = listAutos($pdo, $table, $whereSql, $params, $colId, $colPlacas, $colModelo, $colColor);
    json_out(true, ['message' => 'Auto agregado.', 'data' => ['autos' => $autos]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => friendlyDbError($e)]);
  }
}

// UPDATE
if ($action === 'update') {
  $auto_id = (int)($_POST['auto_id'] ?? 0);
  $placas  = strtoupper(clean((string)($_POST['placas'] ?? '')));
  $modelo  = clean((string)($_POST['modelo'] ?? ''));
  $color   = clean((string)($_POST['color'] ?? ''));

  if ($auto_id <= 0) json_out(false, ['error' => 'Auto inválido.']);
  if ($placas === '') json_out(false, ['error' => 'Las placas son obligatorias.']);

  $set = ["$colPlacas = :placas"];
  $upd = ['placas' => $placas, 'id' => $auto_id, 'uid' => $uid];

  if ($colModelo) { $set[] = "$colModelo = :modelo"; $upd['modelo'] = ($modelo !== '' ? $modelo : null); }
  if ($colColor)  { $set[] = "$colColor  = :color";  $upd['color']  = ($color  !== '' ? $color  : null); }

  if ($colUpdatedAt) $set[] = "$colUpdatedAt = NOW()";

  $whereUpd = ["$colId = :id", "$colUser = :uid"];
  if ($colResid)  { $whereUpd[] = "$colResid = :rid";  $upd['rid']  = $residencial_id; }
  if ($colUnidad) { $whereUpd[] = "$colUnidad = :unid"; $upd['unid'] = $unidad_id; }

  try {
    $stmt = $pdo->prepare("UPDATE $table SET " . implode(', ', $set) . " WHERE " . implode(' AND ', $whereUpd));
    $stmt->execute($upd);

    $autos = listAutos($pdo, $table, $whereSql, $params, $colId, $colPlacas, $colModelo, $colColor);
    json_out(true, ['message' => 'Auto actualizado.', 'data' => ['autos' => $autos]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => friendlyDbError($e)]);
  }
}

// DELETE
if ($action === 'delete') {
  $auto_id = (int)($_POST['auto_id'] ?? 0);
  if ($auto_id <= 0) json_out(false, ['error' => 'Auto inválido.']);

  $delP = ['id' => $auto_id, 'uid' => $uid];
  $whereDel = ["$colId = :id", "$colUser = :uid"];
  if ($colResid)  { $whereDel[] = "$colResid = :rid";  $delP['rid']  = $residencial_id; }
  if ($colUnidad) { $whereDel[] = "$colUnidad = :unid"; $delP['unid'] = $unidad_id; }

  try {
    $stmt = $pdo->prepare("DELETE FROM $table WHERE " . implode(' AND ', $whereDel));
    $stmt->execute($delP);

    $autos = listAutos($pdo, $table, $whereSql, $params, $colId, $colPlacas, $colModelo, $colColor);
    json_out(true, ['message' => 'Auto eliminado.', 'data' => ['autos' => $autos]]);
  } catch (Throwable $e) {
    json_out(false, ['error' => friendlyDbError($e)]);
  }
}

json_out(false, ['error' => 'Acción no soportada.']);