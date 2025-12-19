<?php
// guardia/incidencias.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();
require_role(['guardia']);

$user       = current_user();
$pageTitle  = 'Incidencias';
$activeMenu = 'incidencias';

$success   = '';
$error     = '';
$showModal = false;

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// 1) Residencial del guardia
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

// 2) Unidades
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

// 4) Crear incidencia (POST)
$old = [
  'unidad_id'   => '',
  'tipo'        => 'seguridad',
  'titulo'      => '',
  'descripcion' => '',
  'prioridad'   => 'media',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $old['unidad_id']   = (string)($_POST['unidad_id'] ?? '');
        $old['tipo']        = $_POST['tipo'] ?? 'seguridad';
        $old['titulo']      = trim($_POST['titulo'] ?? '');
        $old['descripcion'] = trim($_POST['descripcion'] ?? '');
        $old['prioridad']   = $_POST['prioridad'] ?? 'media';

        $unidad_id = (int)$old['unidad_id'];

        $errores = [];
        if ($unidad_id <= 0)            $errores[] = 'Debes seleccionar una unidad.';
        if ($old['titulo'] === '')      $errores[] = 'El título es obligatorio.';
        if ($old['descripcion'] === '') $errores[] = 'La descripción es obligatoria.';

        if (!empty($errores)) {
            $error = implode(' ', $errores);
            $showModal = true;
        } else {
            try {
                $stmtIns = $pdo->prepare("
                    INSERT INTO incidencias (
                        residencial_id, unidad_id, residente_id,
                        titulo, descripcion, tipo, prioridad, estado
                    ) VALUES (
                        :residencial_id, :unidad_id, NULL,
                        :titulo, :descripcion, :tipo, :prioridad, 'abierta'
                    )
                ");
                $stmtIns->execute([
                    'residencial_id' => $residencial_id,
                    'unidad_id'      => $unidad_id,
                    'titulo'         => $old['titulo'],
                    'descripcion'    => $old['descripcion'],
                    'tipo'           => $old['tipo'],
                    'prioridad'      => $old['prioridad'],
                ]);

                header('Location: incidencias.php?ok=created#historial');
                exit;
            } catch (PDOException $e) {
                $error = 'Error al registrar la incidencia: ' . $e->getMessage();
                $showModal = true;
            }
        }
    }
}

// 5) Mensajes GET
if (isset($_GET['ok']) && $_GET['ok'] === 'created') {
    $success = 'Incidencia registrada correctamente.';
}

// 6) Lista incidencias del residencial
$incidencias = [];
try {
    $stmtI = $pdo->prepare("
        SELECT i.*, u.clave AS unidad_clave
        FROM incidencias i
        JOIN unidades u ON u.id = i.unidad_id
        WHERE i.residencial_id = :residencial_id
        ORDER BY i.created_at DESC
        LIMIT 100
    ");
    $stmtI->execute(['residencial_id' => $residencial_id]);
    $incidencias = $stmtI->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener incidencias: ' . $e->getMessage();
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
        <h4 class="text-sm font-semibold">Incidencias</h4>
        <p class="text-[11px] text-slate-300/80">
          <?= h($residencial['nombre']) ?>
        </p>
      </div>
      <div class="flex items-center gap-2">
        <a href="incidencias.php?new=1#historial"
           class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-rose-500 to-orange-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-rose-900/40">
          + Nueva incidencia
        </a>
      </div>
    </div>

    <?php if (empty($incidencias)): ?>
      <p class="text-xs text-slate-400">Aún no hay incidencias registradas.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs border-separate border-spacing-y-1">
          <thead class="text-[11px] uppercase text-slate-300/80">
            <tr>
              <th class="text-left px-3 py-2">Título</th>
              <th class="text-left px-3 py-2">Unidad</th>
              <th class="text-left px-3 py-2">Tipo</th>
              <th class="text-left px-3 py-2">Prioridad</th>
              <th class="text-left px-3 py-2">Estado</th>
              <th class="text-right px-3 py-2">Fecha</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($incidencias as $inc): ?>
            <?php
              $estado = (string)($inc['estado'] ?? 'abierta');
              $prio   = (string)($inc['prioridad'] ?? 'media');

              $badgeEstado = 'bg-amber-500/15 border-amber-400/40 text-amber-100';
              if ($estado === 'en_proceso') $badgeEstado = 'bg-sky-500/15 border-sky-400/40 text-sky-100';
              if ($estado === 'cerrada')    $badgeEstado = 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';

              $badgePrio = 'bg-white/10 border-white/20 text-slate-100';
              if ($prio === 'alta') $badgePrio = 'bg-rose-500/15 border-rose-400/40 text-rose-100';
              if ($prio === 'baja') $badgePrio = 'bg-slate-500/20 border-slate-400/40 text-slate-200';
            ?>
            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
              <td class="px-3 py-2 rounded-l-xl">
                <div class="font-medium text-slate-50"><?= h($inc['titulo'] ?? '') ?></div>
                <?php if (!empty($inc['descripcion'])): ?>
                  <div class="text-[11px] text-slate-300/80"><?= nl2br(h($inc['descripcion'])) ?></div>
                <?php endif; ?>
              </td>
              <td class="px-3 py-2 text-slate-200/90"><?= h($inc['unidad_clave'] ?? '') ?></td>
              <td class="px-3 py-2 text-slate-200/90"><?= h($inc['tipo'] ?? '') ?></td>
              <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded-full border <?= $badgePrio ?> text-[11px]">
                  <?= h($prio) ?>
                </span>
              </td>
              <td class="px-3 py-2">
                <span class="inline-flex px-2 py-0.5 rounded-full border <?= $badgeEstado ?> text-[11px]">
                  <?= h($estado) ?>
                </span>
              </td>
              <td class="px-3 py-2 rounded-r-xl text-right text-slate-200/90">
                <?= h($inc['created_at'] ?? '') ?>
              </td>
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
          <h3 class="text-base font-semibold text-slate-50">Nueva incidencia</h3>
          <p class="text-[11px] text-slate-300/80"><?= h($residencial['nombre']) ?></p>
        </div>
        <button type="button"
                onclick="window.location.href='incidencias.php#historial'"
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
          <label class="block mb-1 text-slate-200" for="tipo">Tipo</label>
          <select name="tipo" id="tipo"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <option value="seguridad"       <?= $old['tipo']==='seguridad' ? 'selected' : '' ?>>Seguridad</option>
            <option value="servicio"        <?= $old['tipo']==='servicio' ? 'selected' : '' ?>>Servicios</option>
            <option value="vecino"          <?= $old['tipo']==='vecino' ? 'selected' : '' ?>>Vecino</option>
            <option value="infraestructura" <?= $old['tipo']==='infraestructura' ? 'selected' : '' ?>>Infraestructura</option>
            <option value="otro"            <?= $old['tipo']==='otro' ? 'selected' : '' ?>>Otro</option>
          </select>
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="titulo">Título *</label>
          <input type="text" id="titulo" name="titulo" required
                 value="<?= h($old['titulo']) ?>"
                 class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                 placeholder="Ej. Falla en portón de acceso">
        </div>

        <div>
          <label class="block mb-1 text-slate-200" for="prioridad">Prioridad</label>
          <select name="prioridad" id="prioridad"
                  class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
            <option value="baja"  <?= $old['prioridad']==='baja' ? 'selected' : '' ?>>Baja</option>
            <option value="media" <?= $old['prioridad']==='media' ? 'selected' : '' ?>>Media</option>
            <option value="alta"  <?= $old['prioridad']==='alta' ? 'selected' : '' ?>>Alta</option>
          </select>
        </div>

        <div class="md:col-span-2">
          <label class="block mb-1 text-slate-200" for="descripcion">Descripción *</label>
          <textarea id="descripcion" name="descripcion" rows="4" required
                    class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                    placeholder="Describe lo que ocurrió, ubicación exacta, etc."><?= h($old['descripcion']) ?></textarea>
        </div>

        <div class="md:col-span-2 flex justify-end gap-2 mt-2">
          <button type="button"
                  onclick="window.location.href='incidencias.php#historial'"
                  class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">
            Cancelar
          </button>
          <button type="submit"
                  class="px-4 py-2 rounded-2xl bg-gradient-to-r from-rose-500 to-orange-500 text-[11px] font-medium text-white shadow-lg shadow-rose-900/40">
            Reportar incidencia
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
