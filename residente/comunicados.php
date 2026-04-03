<?php
// residente/comunicados.php
require_once __DIR__ . '/../config/auth.php';
require_role(['residente']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Comunicados';
$activeMenu = 'comunicados';

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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

function pickCol(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

// contexto residencial
$ctx = null;
$error = '';
try {
    $stmt = $pdo->prepare("
        SELECT r.id AS residencial_id, r.nombre
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE ur.user_id = :uid
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmt->execute(['uid' => $user['id']]);
    $ctx = $stmt->fetch();
} catch (PDOException $e) {
    $ctx = null;
    $error = 'Error al obtener tu residencial: ' . $e->getMessage();
}

if (!$ctx) {
    ob_start(); ?>
    <div class="text-sm text-slate-200"><?= $error ? h($error) : 'No tienes residencial asignado.' ?></div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencial_id = (int)$ctx['residencial_id'];

// tabla real
$table = 'comunicados_residenciales';
if (!tableExists($pdo, $table)) {
    ob_start(); ?>
    <div class="space-y-6 text-xs">
      <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5">
        <h4 class="text-sm font-semibold">Comunicados</h4>
        <p class="text-[11px] text-slate-300/80">Residencial: <?= h($ctx['nombre']) ?></p>
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

// columnas flexibles
$colId          = pickCol($cols, ['id']);
$colResidencial = pickCol($cols, ['residencial_id']);
$colTitulo      = pickCol($cols, ['titulo', 'asunto', 'title']);
$colContenido   = pickCol($cols, ['contenido', 'mensaje', 'body', 'descripcion']);
$colFecha       = pickCol($cols, ['created_at', 'publicado_en', 'fecha', 'created']);
$colActivo      = pickCol($cols, ['activo', 'mostrar', 'mostrar_en_portal', 'publicado']);

// filtros
$q = trim($_GET['q'] ?? '');
$desde = trim($_GET['desde'] ?? '');
$hasta = trim($_GET['hasta'] ?? '');

$where = [];
$params = [];

if ($colResidencial) { $where[] = "$colResidencial = :rid"; $params['rid'] = $residencial_id; }
if ($colActivo) { $where[] = "($colActivo = 1 OR $colActivo = '1' OR $colActivo = 'si' OR $colActivo = 'true' OR 1=1)"; }

if ($q !== '' && ($colTitulo || $colContenido)) {
    $parts = [];
    if ($colTitulo)    $parts[] = "$colTitulo LIKE :q";
    if ($colContenido) $parts[] = "$colContenido LIKE :q";
    $where[] = '(' . implode(' OR ', $parts) . ')';
    $params['q'] = '%' . $q . '%';
}

if ($desde !== '' && $colFecha) {
    $where[] = "$colFecha >= :d";
    $params['d'] = $desde . ' 00:00:00';
}
if ($hasta !== '' && $colFecha) {
    $where[] = "$colFecha <= :h";
    $params['h'] = $hasta . ' 23:59:59';
}

$whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));
$orderCol = $colFecha ?: ($colId ?: null);
$orderSql = $orderCol ? "ORDER BY $orderCol DESC" : "";

$items = [];
try {
    $stmtC = $pdo->prepare("SELECT * FROM $table $whereSql $orderSql LIMIT 200");
    $stmtC->execute($params);
    $items = $stmtC->fetchAll();
} catch (PDOException $e) {
    $items = [];
    $error = 'Error al obtener comunicados: ' . $e->getMessage();
}

ob_start();
?>
<div class="space-y-6 text-xs">

  <?php if ($error): ?>
    <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100"><?= h($error) ?></div>
  <?php endif; ?>

  <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
      <h4 class="text-sm font-semibold">Comunicados</h4>
      <p class="text-[11px] text-slate-300/80">Residencial: <?= h($ctx['nombre']) ?></p>
    </div>
    <span class="text-[11px] text-slate-300/80">Total: <?= count($items) ?></span>
  </div>

  <!-- Filtros -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5">
    <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 text-[11px]">
      <div class="md:col-span-6">
        <label class="block mb-1 text-slate-200">Buscar</label>
        <input name="q" value="<?= h($q) ?>" placeholder="Buscar en títulos o contenido..."
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>
      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200">Desde</label>
        <input type="date" name="desde" value="<?= h($desde) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>
      <div class="md:col-span-2">
        <label class="block mb-1 text-slate-200">Hasta</label>
        <input type="date" name="hasta" value="<?= h($hasta) ?>"
               class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
      </div>
      <div class="md:col-span-2 flex items-end">
        <button class="w-full rounded-2xl bg-white/10 border border-white/20 px-3 py-2 text-xs font-medium hover:bg-white/15">
          Filtrar
        </button>
      </div>
    </form>
  </div>

  <!-- Lista -->
  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[45vh]">
    <?php if (empty($items)): ?>
      <p class="text-slate-300/80">No hay comunicados publicados.</p>
    <?php else: ?>
      <div class="space-y-3">
        <?php foreach ($items as $c): ?>
          <?php
            $titulo = $colTitulo ? ($c[$colTitulo] ?? 'Comunicado') : 'Comunicado';
            $body   = $colContenido ? ($c[$colContenido] ?? '') : '';
            $fecha  = $colFecha ? ($c[$colFecha] ?? '') : '';
            $short  = mb_substr((string)$body, 0, 220);
            $needMore = mb_strlen((string)$body) > 220;
            $id = $colId ? ($c[$colId] ?? uniqid('c')) : uniqid('c');
          ?>
          <div class="rounded-2xl bg-white/5 border border-white/12 px-4 py-3">
            <div class="flex items-start justify-between gap-2 mb-1">
              <div class="text-xs font-semibold text-slate-50"><?= h($titulo) ?></div>
              <div class="text-[11px] text-slate-400"><?= h($fecha) ?></div>
            </div>

            <div class="text-[11px] text-slate-200 leading-relaxed">
              <span id="short-<?= h($id) ?>"><?= nl2br(h($short)) ?><?= $needMore ? '…' : '' ?></span>
              <?php if ($needMore): ?>
                <span id="full-<?= h($id) ?>" class="hidden"><?= nl2br(h($body)) ?></span>
              <?php endif; ?>
            </div>

            <?php if ($needMore): ?>
              <div class="mt-2">
                <button type="button"
                        class="text-[11px] px-3 py-1.5 rounded-2xl border border-white/20 bg-white/5 hover:bg-white/10"
                        onclick="toggleComunicado('<?= h($id) ?>')"
                        id="btn-<?= h($id) ?>">
                  Leer más
                </button>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
function toggleComunicado(id){
  const s = document.getElementById('short-' + id);
  const f = document.getElementById('full-' + id);
  const b = document.getElementById('btn-' + id);
  if(!f) return;
  const hidden = f.classList.contains('hidden');
  if(hidden){
    s.classList.add('hidden');
    f.classList.remove('hidden');
    b.innerText = 'Ver menos';
  } else {
    f.classList.add('hidden');
    s.classList.remove('hidden');
    b.innerText = 'Leer más';
  }
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
