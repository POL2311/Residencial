<?php
// guardia/paqueteria.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();
require_role(['guardia']);

$user       = current_user();
$pageTitle  = 'Paquetería';
$activeMenu = 'paqueteria';

$success   = '';
$error     = '';
$showModal = false;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// 1) Obtener residencial del guardia
try {
    $stmtR = $pdo->prepare("
        SELECT r.*
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmtR->execute(['user_id' => $user['id']]);
    $residencial = $stmtR->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener el residencial: ' . $e->getMessage();
    $residencial = null;
}

if (!$residencial) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        Tu cuenta de guardia no está asociada a un residencial. Contacta al administrador.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencial_id = (int)$residencial['id'];

// 2) Obtener unidades del residencial
$unidades = [];
try {
    $stmtU = $pdo->prepare("
        SELECT id, clave
        FROM unidades
        WHERE residencial_id = :residencial_id
        ORDER BY clave ASC
    ");
    $stmtU->execute(['residencial_id' => $residencial_id]);
    $unidades = $stmtU->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener unidades: ' . $e->getMessage();
}

// 3) Abrir modal por GET
if (isset($_GET['new'])) {
    $showModal = true;
}

// 4) Registrar paquete (POST)
$old = [
  'unidad_id'      => '',
  'empresa'        => '',
  'descripcion'    => '',
  'codigo_rastreo' => '',
  'notas'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $old['unidad_id']      = (string)($_POST['unidad_id'] ?? '');
        $old['empresa']        = trim($_POST['empresa'] ?? '');
        $old['descripcion']    = trim($_POST['descripcion'] ?? '');
        $old['codigo_rastreo'] = trim($_POST['codigo_rastreo'] ?? '');
        $old['notas']          = trim($_POST['notas'] ?? '');

        $unidad_id = (int)$old['unidad_id'];

        $errores = [];
        if ($unidad_id <= 0)            $errores[] = 'Debes seleccionar una unidad.';
        if ($old['descripcion'] === '') $errores[] = 'La descripción del paquete es obligatoria.';

        if (!empty($errores)) {
            $error = implode(' ', $errores);
            $showModal = true;
        } else {
            try {
                $stmtIns = $pdo->prepare("
                    INSERT INTO paqueteria (
                        residencial_id, unidad_id, residente_id, guardia_id,
                        empresa, descripcion, codigo_rastreo, estado, notas
                    ) VALUES (
                        :residencial_id, :unidad_id, NULL, :guardia_id,
                        :empresa, :descripcion, :codigo_rastreo, 'registrado', :notas
                    )
                ");
                $stmtIns->execute([
                    'residencial_id' => $residencial_id,
                    'unidad_id'      => $unidad_id,
                    'guardia_id'     => $user['id'],
                    'empresa'        => $old['empresa'] !== '' ? $old['empresa'] : null,
                    'descripcion'    => $old['descripcion'],
                    'codigo_rastreo' => $old['codigo_rastreo'] !== '' ? $old['codigo_rastreo'] : null,
                    'notas'          => $old['notas'] !== '' ? $old['notas'] : null,
                ]);

                header('Location: paqueteria.php?ok=created#historial');
                exit;
            } catch (PDOException $e) {
                $error = 'Error al registrar paquete: ' . $e->getMessage();
                $showModal = true;
            }
        }
    }
}

// 5) Mensajes GET
if (isset($_GET['ok']) && $_GET['ok'] === 'created') {
    $success = 'Paquete registrado correctamente.';
}

// 6) Últimos paquetes
$paquetes = [];
try {
    $stmtP = $pdo->prepare("
        SELECT p.*, u.clave AS unidad_clave
        FROM paqueteria p
        JOIN unidades u ON u.id = p.unidad_id
        WHERE p.residencial_id = :residencial_id
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmtP->execute(['residencial_id' => $residencial_id]);
    $paquetes = $stmtP->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener la lista de paquetería: ' . $e->getMessage();
}

ob_start();
?>
<div class="space-y-6 text-xs" id="historial">

  <?php if ($success): ?>
    <div class="rounded-2xl bg-emerald-500/15 border border-emerald-400/50 px-4 py-3 text-emerald-100">
      <?= h($success) ?>
    </div>
  <?php endif; ?>

  <?php if ($error && !$showModal): ?>
    <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100">
      <?= h($error) ?>
    </div>
  <?php endif; ?>

  <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5 min-h-[60vh]">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
      <div>
        <h4 class="text-sm font-semibold">Paquetería</h4>
        <p class="text-[11px] text-slate-300/80"><?= h($residencial['nombre']) ?></p>
      </div>
      <div class="flex items-center gap-2">
        <a href="paqueteria.php?new=1#historial"
           class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-indigo-500 to-sky-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
          + Nuevo paquete
        </a>
      </div>
    </div>

    <?php if (empty($paquetes)): ?>
      <p class="text-xs text-slate-400">Aún no hay paquetes registrados.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs border-separate border-spacing-y-1">
          <thead class="text-[11px] uppercase text-slate-300/80">
            <tr>
              <th class="text-left px-3 py-2">Unidad</th>
              <th class="text-left px-3 py-2">Descripción</th>
              <th class="text-left px-3 py-2">Empresa</th>
              <th class="text-left px-3 py-2">Rastreo</th>
              <th class="text-left px-3 py-2">Estado</th>
              <th class="text-right px-3 py-2">Fecha</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($paquetes as $p): ?>
            <?php
              $estado = (string)($p['estado'] ?? 'registrado');
              $badgeEstado = 'bg-sky-500/15 border-sky-400/40 text-sky-100';
              if ($estado === 'entregado') $badgeEstado = 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';
              if ($estado === 'cancelado') $badgeEstado = 'bg-rose-500/15 border-rose-400/40 text-rose-100';
            ?>
            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
              <td class="px-3 py-2 rounded-l-xl text-slate-50 font-medium"><?= h($p['unidad_clave'] ?? '') ?></td>
              <td class="px-3 py-2 text-slate-200/90"><?= h($p['descripcion'] ?? '') ?></td>
              <td class="px-3 py-2 text-slate-200/90"><?= h($p['empresa'] ?? '-') ?></td>
              <td class="px-3 py-2 text-slate-200/90">
                <?php if (!empty($p['codigo_rastreo'])): ?>
                  <code class="px-2 py-1 rounded-lg bg-black/40 text-[11px]"><?= h($p['codigo_rastreo']) ?></code>
                <?php else: ?>
                  <span class="text-slate-400">—</span>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded-full border <?= $badgeEstado ?> text-[11px]">
                  <?= h($estado) ?>
                </span>
              </td>
              <td class="px-3 py-2 rounded-r-xl text-right text-slate-200/90"><?= h($p['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($showModal): ?>
  <div class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm flex justify-center items-start overflow-y-auto">
    <div class="w-full max-w-5xl my-8 mx-2 md:mx-4 rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-base font-semibold text-slate-50">Nuevo paquete</h3>
          <p class="text-[11px] text-slate-300/80"><?= h($residencial['nombre']) ?></p>
        </div>
        <button type="button"
                onclick="window.location.href='paqueteria.php#historial'"
                class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center">
          ✕
        </button>
      </div>

      <?php if ($error): ?>
        <div class="mb-4 rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100">
          <?= h($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <input type="hidden" name="action" value="create">

        <div>
          <label class="block mb-1 text-slate-200" for="unidad_id">Unidad *</label>
          <select name="unidad_id" id="unidad_id"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <option value="">Selecciona una unidad</option>
            <?php foreach ($unidades as $u): ?>
              <option value="<?= (int)$u['id'] ?>" <?= ((string)$u['id'] === (string)$old['unidad_id']) ? 'selected' : '' ?>>
                <?= h($u['clave']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="empresa">Empresa / mensajería</label>
          <input type="text" id="empresa" name="empresa"
                 value="<?= h($old['empresa']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                 placeholder="Ej. Amazon, DHL...">
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-slate-200" for="descripcion">Descripción del paquete *</label>
          <input type="text" id="descripcion" name="descripcion" required
                 value="<?= h($old['descripcion']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                 placeholder="Ej. Caja chica, sobre, paquete grande...">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="codigo_rastreo">Código de rastreo</label>
          <input type="text" id="codigo_rastreo" name="codigo_rastreo"
                 value="<?= h($old['codigo_rastreo']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                 placeholder="Opcional">
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-slate-200" for="notas">Notas internas</label>
          <textarea id="notas" name="notas" rows="3"
                    class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                    placeholder="Ej. Dejar en caseta, frágil, etc."><?= h($old['notas']) ?></textarea>
        </div>

        <div class="md:col-span-2 flex justify-end gap-2 mt-2">
          <button type="button"
                  onclick="window.location.href='paqueteria.php#historial'"
                  class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">
            Cancelar
          </button>
          <button type="submit"
                  class="px-4 py-2 rounded-2xl bg-gradient-to-r from-indigo-500 to-sky-500 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
            Registrar paquete
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
