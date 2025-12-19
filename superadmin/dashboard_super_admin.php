<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_role(['super_admin']);
if (function_exists('require_login')) require_login();

$pageTitle  = 'Resumen general (Super Admin)';
$activeMenu = 'overview';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * Ejecuta query y regresa:
 * - fetch() si $mode = 'one'
 * - fetchAll() si $mode = 'all'
 * - null si falla
 */
function q($pdo, $sql, $params = [], $mode = 'one') {
  try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $mode === 'all' ? $st->fetchAll() : $st->fetch();
  } catch (Throwable $e) {
    return null;
  }
}

/**
 * Intenta varias queries en orden y regresa el primer resultado no-null.
 */
function first_ok($pdo, $tries, $mode = 'one') {
  foreach ($tries as $t) {
    $res = q($pdo, $t['sql'], $t['params'] ?? [], $mode);
    if ($res !== null) return $res;
  }
  return null;
}

/* =========================
   1) RESIDENCIALES ACTIVOS
   ========================= */

// total residenciales
$rowTotalRes = q($pdo, "SELECT COUNT(*) AS c FROM residenciales", [], 'one');
$totalResidenciales = (int)($rowTotalRes['c'] ?? 0);

// activos: probamos columnas comunes
$rowActivos = first_ok($pdo, [
  ['sql' => "SELECT COUNT(*) AS c FROM residenciales WHERE estado = 'activo'"],
  ['sql' => "SELECT COUNT(*) AS c FROM residenciales WHERE status = 'activo'"],
  ['sql' => "SELECT COUNT(*) AS c FROM residenciales WHERE activo = 1"],
  ['sql' => "SELECT COUNT(*) AS c FROM residenciales WHERE is_active = 1"],
], 'one');

// si no existe ninguna columna de “activo”, usamos total como “activos”
$activosResidenciales = $rowActivos ? (int)($rowActivos['c'] ?? 0) : $totalResidenciales;

// creados este mes
$rowNuevosMes = first_ok($pdo, [
  ['sql' => "SELECT COUNT(*) AS c
            FROM residenciales
            WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"],
], 'one');
$nuevosEsteMes = $rowNuevosMes ? (int)($rowNuevosMes['c'] ?? 0) : null;

/* =========================
   2) USUARIOS TOTALES
   ========================= */

$rowUsersTotal = q($pdo, "SELECT COUNT(*) AS c FROM users", [], 'one');
$totalUsuarios = (int)($rowUsersTotal['c'] ?? 0);

// desglose por rol 
$rolesBreakdown = q($pdo, "SELECT role, COUNT(*) AS c FROM users GROUP BY role ORDER BY c DESC", [], 'all');
if (!is_array($rolesBreakdown)) $rolesBreakdown = [];

/* =========================
   3) ACCESOS HOY
   ========================= */

$rowAccesosHoy = first_ok($pdo, [
  ['sql' => "SELECT COUNT(*) AS c
            FROM accesos_guardia
            WHERE DATE(fecha_hora) = CURDATE()"],
], 'one');
$accesosHoy = $rowAccesosHoy ? (int)($rowAccesosHoy['c'] ?? 0) : 0;

/* =========================
   4) RESIDENCIALES RECIENTES
   ========================= */

// intentamos traer plan/tier si existe, si no solo nombre
$resRecientes = first_ok($pdo, [
  ['sql' => "SELECT id, nombre, plan, created_at
            FROM residenciales
            ORDER BY created_at DESC
            LIMIT 3", 'params' => []],
  ['sql' => "SELECT id, nombre, tier, created_at
            FROM residenciales
            ORDER BY created_at DESC
            LIMIT 3", 'params' => []],
  ['sql' => "SELECT id, nombre, created_at
            FROM residenciales
            ORDER BY created_at DESC
            LIMIT 3", 'params' => []],
], 'all');
if (!is_array($resRecientes)) $resRecientes = [];

/* =========================
   5) ACTIVIDAD RECIENTE
   ========================= */

$actividad = [];

// 5.1 último residencial creado
$lastRes = first_ok($pdo, [
  ['sql' => "SELECT nombre, created_at FROM residenciales ORDER BY created_at DESC LIMIT 1"],
], 'one');
if ($lastRes && !empty($lastRes['nombre'])) {
  $actividad[] = "Se creó el residencial <strong>" . h($lastRes['nombre']) . "</strong>.";
}

$rowGuardias7d = first_ok($pdo, [
  ['sql' => "SELECT COUNT(*) AS c
            FROM users
            WHERE role = 'guardia'
              AND created_at >= (NOW() - INTERVAL 7 DAY)"],
], 'one');
if ($rowGuardias7d !== null) {
  $actividad[] = "Se registraron <strong>" . (int)($rowGuardias7d['c'] ?? 0) . "</strong> guardias en los últimos 7 días.";
}

// 5.3 incidencias abiertas hoy 
$rowIncHoy = first_ok($pdo, [
  ['sql' => "SELECT COUNT(*) AS c
            FROM incidencias
            WHERE DATE(created_at) = CURDATE()"],
], 'one');
if ($rowIncHoy !== null) {
  $actividad[] = "Incidencias registradas hoy: <strong>" . (int)($rowIncHoy['c'] ?? 0) . "</strong>.";
}

// 5.4 paquetería hoy 
$rowPaqHoy = first_ok($pdo, [
  ['sql' => "SELECT COUNT(*) AS c
            FROM paqueteria
            WHERE DATE(created_at) = CURDATE()"],
], 'one');
if ($rowPaqHoy !== null) {
  $actividad[] = "Paquetes registrados hoy: <strong>" . (int)($rowPaqHoy['c'] ?? 0) . "</strong>.";
}

if (empty($actividad)) {
  $actividad[] = "No hay actividad reciente para mostrar (o faltan columnas/tabla).";
}

/* =========================
   RENDER
   ========================= */

ob_start();
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-5">

  <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 shadow-lg shadow-sky-900/30">
    <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Residenciales activos</p>
    <p class="text-2xl font-semibold mb-1"><?= (int)$activosResidenciales ?></p>
    <p class="text-[11px] text-slate-300/80">
      <?php if ($nuevosEsteMes !== null): ?>
        +<?= (int)$nuevosEsteMes ?> este mes
      <?php else: ?>
        Total en sistema: <?= (int)$totalResidenciales ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 shadow-lg shadow-sky-900/30">
    <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Usuarios totales</p>
    <p class="text-2xl font-semibold mb-1"><?= (int)$totalUsuarios ?></p>
    <p class="text-[11px] text-slate-300/80">
      <?php if (!empty($rolesBreakdown)): ?>
        <?php
          // muestra top 3 roles
          $top = array_slice($rolesBreakdown, 0, 3);
          $txt = [];
          foreach ($top as $r) {
            $txt[] = h($r['role'] ?? 'rol') . ": " . (int)($r['c'] ?? 0);
          }
          echo implode(' · ', $txt);
        ?>
      <?php else: ?>
        Admins, guardias y residentes
      <?php endif; ?>
    </p>
  </div>

  <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 shadow-lg shadow-sky-900/30">
    <p class="text-[11px] uppercase tracking-wide text-slate-200/80 mb-1">Accesos hoy</p>
    <p class="text-2xl font-semibold mb-1"><?= (int)$accesosHoy ?></p>
    <p class="text-[11px] text-slate-300/80">Registros en accesos_guardia</p>
  </div>

</div>

<div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-5">

  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
    <h4 class="text-sm font-semibold mb-3">Residenciales recientes</h4>

    <?php if (empty($resRecientes)): ?>
      <p class="text-xs text-slate-400">Aún no hay residenciales registrados.</p>
    <?php else: ?>
      <ul class="space-y-2 text-xs text-slate-200/90">
        <?php foreach ($resRecientes as $r): ?>
          <?php
            $plan = $r['plan'] ?? ($r['tier'] ?? null);
            $planLabel = $plan ? 'Plan ' . h($plan) : '—';
          ?>
          <li class="flex justify-between gap-3">
            <span><?= h($r['nombre'] ?? '') ?></span>
            <span class="text-slate-400"><?= $planLabel ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
    <h4 class="text-sm font-semibold mb-3">Actividad reciente</h4>
    <ul class="space-y-2 text-xs text-slate-200/90">
      <?php foreach ($actividad as $a): ?>
        <li>• <?= $a ?></li>
      <?php endforeach; ?>
    </ul>
  </div>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
