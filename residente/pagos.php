<?php
// residente/pagos.php
require_once __DIR__ . '/../config/auth.php';
require_role(['residente']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Historial de pagos';
$activeMenu = 'pagos';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$success = '';
$error   = '';

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
        ");
        $stmt->execute(['t' => $table]);
        return ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        return false;
    }
}

function getTableColumns(PDO $pdo, string $table): array {
    try {
        $stmt = $pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
            ORDER BY ORDINAL_POSITION
        ");
        $stmt->execute(['t' => $table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function hasCol(array $cols, string $c): bool {
    return in_array($c, $cols, true);
}

/**
 * Mapea un “candidato” (columna real) si existe.
 * Te permite usar diferentes esquemas sin romper.
 */
function pickCol(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

// 1) Contexto residencial + unidad del residente (para filtrar y mostrar)
$ctx = null;
try {
    $stmtRU = $pdo->prepare("
        SELECT r.id AS residencial_id, r.nombre AS residencial_nombre,
               u.id AS unidad_id, u.clave AS unidad_clave
        FROM usuarios_residenciales ur
        JOIN residenciales r        ON r.id = ur.residencial_id
        JOIN residentes_unidades ru ON ru.user_id = ur.user_id
        JOIN unidades u             ON u.id = ru.unidad_id
        WHERE ur.user_id = :uid
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmtRU->execute(['uid' => $user['id']]);
    $ctx = $stmtRU->fetch();
} catch (PDOException $e) {
    $ctx = null;
    $error = 'Error al obtener tu contexto: ' . $e->getMessage();
}

if (!$ctx) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        <?= $error ? h($error) : 'Tu cuenta aún no está ligada a un residencial y una unidad.' ?>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencial_id = (int)$ctx['residencial_id'];
$unidad_id      = (int)$ctx['unidad_id'];

// 2) Tabla real
$table = 'pagos_residentes';
if (!tableExists($pdo, $table)) {
    ob_start(); ?>
    <div class="space-y-6 text-xs">
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5">
            <h4 class="text-sm font-semibold">Historial de pagos</h4>
            <p class="text-[11px] text-slate-300/80">Consulta tus pagos registrados.</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6">
            <p class="text-slate-300/80">
                No existe la tabla <code class="px-2 py-1 rounded bg-black/40"><?= h($table) ?></code>.
            </p>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$cols = getTableColumns($pdo, $table);

// 3) Columnas “típicas” (si existen)
$colId            = pickCol($cols, ['id']);
$colResidencial   = pickCol($cols, ['residencial_id']);
$colUnidad        = pickCol($cols, ['unidad_id']);
$colResidente     = pickCol($cols, ['residente_id', 'user_id']);
$colConcepto      = pickCol($cols, ['concepto', 'descripcion', 'detalle', 'titulo']);
$colMonto         = pickCol($cols, ['monto', 'importe', 'total', 'cantidad']);
$colEstatus       = pickCol($cols, ['estado', 'estatus', 'status']);
$colMetodo        = pickCol($cols, ['metodo', 'metodo_pago', 'forma_pago']);
$colReferencia    = pickCol($cols, ['referencia', 'folio', 'transaccion', 'txid']);
$colPeriodo       = pickCol($cols, ['periodo', 'mes', 'anio']);
$colFechaPago     = pickCol($cols, ['fecha_pago', 'pagado_en', 'paid_at']);
$colFechaCreado   = pickCol($cols, ['created_at', 'fecha', 'created']);
$colFechaActual   = pickCol($cols, ['updated_at']);

// 4) Filtros
$f_estado   = trim($_GET['estado'] ?? 'todos');
$f_desde    = trim($_GET['desde'] ?? '');
$f_hasta    = trim($_GET['hasta'] ?? '');
$f_q        = trim($_GET['q'] ?? '');
$f_lim      = (int)($_GET['lim'] ?? 50);
$f_lim      = max(10, min(200, $f_lim));

$page       = (int)($_GET['p'] ?? 1);
$page       = max(1, $page);
$offset     = ($page - 1) * $f_lim;

$export = ($_GET['export'] ?? '') === 'csv';

// 5) Construcción SQL dinámica y segura
$where = [];
$params = [];

if ($colResidencial) { $where[] = "$colResidencial = :rid"; $params['rid'] = $residencial_id; }
if ($colUnidad)      { $where[] = "$colUnidad = :uid";      $params['uid'] = $unidad_id; }
if ($colResidente)   { $where[] = "$colResidente = :res";   $params['res'] = $user['id']; }

if ($f_estado !== '' && $f_estado !== 'todos' && $colEstatus) {
    $where[] = "$colEstatus = :estado";
    $params['estado'] = $f_estado;
}

if ($f_desde !== '' && ($colFechaPago || $colFechaCreado)) {
    $c = $colFechaPago ?: $colFechaCreado;
    $where[] = "$c >= :desde";
    $params['desde'] = $f_desde . ' 00:00:00';
}
if ($f_hasta !== '' && ($colFechaPago || $colFechaCreado)) {
    $c = $colFechaPago ?: $colFechaCreado;
    $where[] = "$c <= :hasta";
    $params['hasta'] = $f_hasta . ' 23:59:59';
}

if ($f_q !== '' && ($colConcepto || $colReferencia || $colMetodo)) {
    $likeParts = [];
    if ($colConcepto)   $likeParts[] = "$colConcepto LIKE :q";
    if ($colReferencia) $likeParts[] = "$colReferencia LIKE :q";
    if ($colMetodo)     $likeParts[] = "$colMetodo LIKE :q";
    if (!empty($likeParts)) {
        $where[] = '(' . implode(' OR ', $likeParts) . ')';
        $params['q'] = '%' . $f_q . '%';
    }
}

$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

// Seleccionar columnas (siempre seleccionamos todas para “modo compatibilidad”)
$selectCols = '*';

// Orden (preferimos fecha_pago, luego created_at, si no existe: id desc)
$orderCol = $colFechaPago ?: ($colFechaCreado ?: ($colId ?: null));
$orderSql = $orderCol ? "ORDER BY $orderCol DESC" : "";

// 6) Count + data
$totalRows = 0;
$rows = [];

try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM $table $whereSql");
    $stmtCount->execute($params);
    $totalRows = (int)$stmtCount->fetchColumn();

    if ($export) {
        $stmt = $pdo->prepare("SELECT $selectCols FROM $table $whereSql $orderSql");
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT $selectCols FROM $table $whereSql $orderSql LIMIT :lim OFFSET :off");
        foreach ($params as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':lim', $f_lim, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = 'Error al obtener pagos: ' . $e->getMessage();
}

// 7) Export CSV
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pagos_residente.csv"');

    $out = fopen('php://output', 'w');
    if (!empty($rows)) {
        // encabezados
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) {
            fputcsv($out, array_values($r));
        }
    } else {
        fputcsv($out, ['sin_resultados']);
    }
    fclose($out);
    exit;
}

// Helpers UI
function badgeEstado($estado) {
    $estado = (string)$estado;
    $e = strtolower($estado);

    if ($e === 'pagado' || $e === 'paid' || $e === 'completado') {
        return 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';
    }
    if ($e === 'pendiente' || $e === 'pending') {
        return 'bg-amber-500/15 border-amber-400/40 text-amber-100';
    }
    if ($e === 'vencido' || $e === 'overdue') {
        return 'bg-rose-500/15 border-rose-400/40 text-rose-100';
    }
    if ($e === 'cancelado' || $e === 'canceled') {
        return 'bg-slate-500/20 border-slate-400/40 text-slate-200';
    }
    return 'bg-white/10 border-white/20 text-slate-100';
}

$totalPages = max(1, (int)ceil($totalRows / $f_lim));

ob_start();
?>
<div class="space-y-6 text-xs">

  <?php if ($error): ?>
    <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100">
      <?= h($error) ?>
    </div>
  <?php endif; ?>

  <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h4 class="text-sm font-semibold">Historial de pagos</h4>
      <p class="text-[11px] text-slate-300/80">
        Residencial: <?= h($ctx['residencial_nombre']) ?> · unidad <?= h($ctx['unidad_clave']) ?>
      </p>
    </div>
    <div class="flex items-center gap-2">
      <span class="text-[11px] text-slate-300/80">Total: <?= (int)$totalRows ?></span>
      <a href="?<?= h(http_build_query(array_merge($_GET, ['export'=>'csv']))) ?>"
         class="inline-flex items-center gap-1 rounded-2xl bg-white/10 border border-white/20 px-4 py-2 text-[11px] text-slate-50 hover:bg-white/15">
        ⬇ Exportar CSV
      </a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
    <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 text-[11px]">
      <div class="md:col-span-3">
        <label class="block mb-1 text-slate-200">Buscar</label>
        <input name="q" value="<?= h($f_q) ?>" placeholder="Concepto, referencia, método..."
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200">Estado</label>
        <select name="estado"
                class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
          <option value="todos" <?= $f_estado==='todos'?'selected':'' ?>>Todos</option>
          <option value="pagado" <?= $f_estado==='pagado'?'selected':'' ?>>Pagado</option>
          <option value="pendiente" <?= $f_estado==='pendiente'?'selected':'' ?>>Pendiente</option>
          <option value="vencido" <?= $f_estado==='vencido'?'selected':'' ?>>Vencido</option>
          <option value="cancelado" <?= $f_estado==='cancelado'?'selected':'' ?>>Cancelado</option>
        </select>
        <?php if (!$colEstatus): ?>
          <div class="text-[10px] text-slate-400 mt-1">*Tu tabla no trae columna de estado.</div>
        <?php endif; ?>
      </div>

      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200">Desde</label>
        <input type="date" name="desde" value="<?= h($f_desde) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200">Hasta</label>
        <input type="date" name="hasta" value="<?= h($f_hasta) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>

      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200">Mostrar</label>
        <select name="lim"
                class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
          <?php foreach ([25,50,100,200] as $opt): ?>
            <option value="<?= $opt ?>" <?= $f_lim===$opt?'selected':'' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="md:col-span-1 flex items-end">
        <button class="w-full rounded-2xl bg-white/10 border border-white/20 px-3 py-2 text-xs font-medium hover:bg-white/15">
          Filtrar
        </button>
      </div>
    </form>
  </div>

  <!-- Lista -->
  <div class="rounded-3xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[45vh]">
    <?php if (empty($rows)): ?>
      <p class="text-xs text-slate-400">No hay pagos con los filtros seleccionados.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs border-separate border-spacing-y-1">
          <thead class="text-[11px] uppercase text-slate-300/80">
            <tr>
              <th class="text-left px-3 py-2">Concepto</th>
              <th class="text-left px-3 py-2">Monto</th>
              <th class="text-left px-3 py-2">Periodo</th>
              <th class="text-left px-3 py-2">Referencia</th>
              <th class="text-left px-3 py-2">Estado</th>
              <th class="text-left px-3 py-2">Fecha</th>
              <th class="text-right px-3 py-2">Detalle</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $concepto = $colConcepto ? ($r[$colConcepto] ?? '') : '';
              $monto    = $colMonto ? ($r[$colMonto] ?? '') : '';
              $periodo  = $colPeriodo ? ($r[$colPeriodo] ?? '') : '';
              $ref      = $colReferencia ? ($r[$colReferencia] ?? '') : '';
              $est      = $colEstatus ? ($r[$colEstatus] ?? '') : '';
              $fecha    = $colFechaPago ? ($r[$colFechaPago] ?? '') : ($colFechaCreado ? ($r[$colFechaCreado] ?? '') : '');
              $idRow    = $colId ? ($r[$colId] ?? '') : '';
            ?>
            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
              <td class="px-3 py-2 rounded-l-xl">
                <div class="font-medium text-slate-50">
                  <?= h($concepto !== '' ? $concepto : ('Pago #' . h($idRow))) ?>
                </div>
                <?php if ($colMetodo && !empty($r[$colMetodo])): ?>
                  <div class="text-[11px] text-slate-300/80">Método: <?= h($r[$colMetodo]) ?></div>
                <?php endif; ?>
              </td>

              <td class="px-3 py-2 text-slate-200/90">
                <?= $monto !== '' ? h($monto) : '<span class="text-slate-400">--</span>' ?>
              </td>

              <td class="px-3 py-2 text-slate-200/90">
                <?= $periodo !== '' ? h($periodo) : '<span class="text-slate-400">--</span>' ?>
              </td>

              <td class="px-3 py-2 text-slate-200/90">
                <?php if ($ref !== ''): ?>
                  <code class="px-2 py-1 rounded-lg bg-black/40 text-[11px]"><?= h($ref) ?></code>
                <?php else: ?>
                  <span class="text-slate-400">--</span>
                <?php endif; ?>
              </td>

              <td class="px-3 py-2">
                <?php if ($colEstatus && $est !== ''): ?>
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border <?= badgeEstado($est) ?> text-[11px]">
                    <?= h($est) ?>
                  </span>
                <?php else: ?>
                  <span class="text-slate-400">--</span>
                <?php endif; ?>
              </td>

              <td class="px-3 py-2 text-slate-300/80">
                <?= $fecha !== '' ? h($fecha) : '<span class="text-slate-400">--</span>' ?>
              </td>

              <td class="px-3 py-2 rounded-r-xl text-right">
                <button type="button"
                        class="px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 text-[11px] hover:bg-white/10"
                        onclick='showPagoDetail(<?= json_encode($r, JSON_UNESCAPED_UNICODE) ?>)'>
                  Ver
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-4 text-[11px] text-slate-300/80">
        <div>
          Página <?= (int)$page ?> de <?= (int)$totalPages ?> · Mostrando <?= count($rows) ?> de <?= (int)$totalRows ?>
        </div>
        <div class="flex items-center gap-2">
          <?php
            $base = $_GET;
            $base['p'] = max(1, $page - 1);
          ?>
          <a class="px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 hover:bg-white/10 <?= $page<=1?'pointer-events-none opacity-40':'' ?>"
             href="?<?= h(http_build_query($base)) ?>">← Anterior</a>

          <?php
            $base = $_GET;
            $base['p'] = min($totalPages, $page + 1);
          ?>
          <a class="px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 hover:bg-white/10 <?= $page>=$totalPages?'pointer-events-none opacity-40':'' ?>"
             href="?<?= h(http_build_query($base)) ?>">Siguiente →</a>
        </div>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- Modal detalle pago -->
<div id="pagoModal" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm">
  <div class="min-h-full flex items-start justify-center p-4 sm:p-6">
    <div class="w-full max-w-3xl rounded-3xl border border-white/15 bg-slate-950/95 shadow-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-white/10 flex items-center justify-between">
        <div class="text-sm font-semibold text-slate-50">Detalle del pago</div>
        <button type="button" class="h-9 w-9 rounded-full bg-white/5 border border-white/15 hover:bg-white/10"
                onclick="closePagoModal()">✕</button>
      </div>
      <div class="p-5">
        <div id="pagoDetail" class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px]"></div>
      </div>
    </div>
  </div>
</div>

<script>
function showPagoDetail(row) {
  const modal = document.getElementById('pagoModal');
  const box = document.getElementById('pagoDetail');
  box.innerHTML = '';
  Object.keys(row).forEach(k => {
    const v = row[k];
    const item = document.createElement('div');
    item.className = "rounded-2xl border border-white/12 bg-white/5 p-3";
    item.innerHTML = `
      <div class="text-slate-300/80 text-[10px] mb-1">${escapeHtml(k)}</div>
      <div class="text-slate-50 break-words">${escapeHtml(String(v ?? ''))}</div>
    `;
    box.appendChild(item);
  });
  modal.classList.remove('hidden');
}
function closePagoModal() {
  document.getElementById('pagoModal').classList.add('hidden');
}
function escapeHtml(str) {
  return str
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
