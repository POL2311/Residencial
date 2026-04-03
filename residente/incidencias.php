<?php
// residente/incidencias.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';

require_login();
require_role(['residente']);

$user       = current_user();
$pageTitle  = 'Mis incidencias';
$activeMenu = 'incidencias';

$success   = '';
$error     = '';
$showModal = false;
$modalMode = 'create';

// helper
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// 1) Obtener residencial + unidad principal del residente
try {
    $stmtRU = $pdo->prepare("
        SELECT r.*, u.id AS unidad_id, u.clave AS unidad_clave
        FROM usuarios_residenciales ur
        JOIN residenciales r        ON r.id = ur.residencial_id
        JOIN residentes_unidades ru ON ru.user_id = ur.user_id
        JOIN unidades u             ON u.id = ru.unidad_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmtRU->execute(['user_id' => $user['id']]);
    $ctx = $stmtRU->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener tu residencial: ' . $e->getMessage();
    $ctx = null;
}

if (!$ctx) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        Tu cuenta de residente aún no está ligada a un residencial y una unidad.<br>
        Contacta al administrador del residencial para que te asigne.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencial = $ctx;
$unidad_id   = (int)$ctx['unidad_id'];

// 2) Abrir modal por GET
if (isset($_GET['new'])) {
    $showModal = true;
    $modalMode = 'create';
}

// 3) Crear nueva incidencia (POST)
$old = [
    'titulo'      => '',
    'tipo'        => 'otro',
    'descripcion' => '',
    'prioridad'   => 'media',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'create') {
        $old['tipo']        = $_POST['tipo'] ?? 'otro';
        $old['titulo']      = trim($_POST['titulo'] ?? '');
        $old['descripcion'] = trim($_POST['descripcion'] ?? '');
        $old['prioridad']   = $_POST['prioridad'] ?? 'media';

        $errores = [];
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
                        tipo, titulo, descripcion, prioridad, estado
                    ) VALUES (
                        :residencial_id, :unidad_id, :residente_id,
                        :tipo, :titulo, :descripcion, :prioridad, 'abierta'
                    )
                ");
                $stmtIns->execute([
                    'residencial_id' => $residencial['id'],
                    'unidad_id'      => $unidad_id,
                    'residente_id'   => $user['id'],
                    'tipo'           => $old['tipo'],
                    'titulo'         => $old['titulo'],
                    'descripcion'    => $old['descripcion'],
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

// 4) Mensajes por GET
if (isset($_GET['ok']) && $_GET['ok'] === 'created') {
    $success = 'Incidencia registrada correctamente.';
}

// 5) Listar incidencias del residente
$incidencias = [];
try {
    $stmtL = $pdo->prepare("
        SELECT *
        FROM incidencias
        WHERE residente_id = :residente_id
        ORDER BY created_at DESC
        LIMIT 100
    ");
    $stmtL->execute(['residente_id' => $user['id']]);
    $incidencias = $stmtL->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener tus incidencias: ' . $e->getMessage();
}

// 6) Render
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

    <!-- Encabezado + botón modal -->
    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-5 min-h-[50vh] ">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <div>
                <h4 class="text-sm font-semibold">Historial de incidencias</h4>
                <p class="text-[11px] text-slate-300/80">
                    Residencial <?= h($residencial['nombre']) ?> · unidad <?= h($ctx['unidad_clave']) ?>
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
            <p class="text-xs text-slate-400">Aún no has reportado incidencias.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                    <tr>
                        <th class="text-left px-3 py-2">Título</th>
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
                            $tipo   = (string)($inc['tipo'] ?? '');
                            $prio   = (string)($inc['prioridad'] ?? '');

                            $badgeEstado = 'bg-amber-500/15 border-amber-400/40 text-amber-100';
                            if ($estado === 'en_proceso') $badgeEstado = 'bg-sky-500/15 border-sky-400/40 text-sky-100';
                            if ($estado === 'cerrada')    $badgeEstado = 'bg-emerald-500/15 border-emerald-400/40 text-emerald-100';

                            $badgePrio = 'bg-white/10 border-white/20 text-slate-100';
                            if ($prio === 'alta')  $badgePrio = 'bg-rose-500/15 border-rose-400/40 text-rose-100';
                            if ($prio === 'baja')  $badgePrio = 'bg-slate-500/20 border-slate-400/40 text-slate-200';
                        ?>
                        <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                            <td class="px-3 py-2 rounded-l-xl">
                                <div class="font-medium text-slate-50"><?= h($inc['titulo'] ?? '') ?></div>
                                <?php if (!empty($inc['descripcion'])): ?>
                                    <div class="text-[11px] text-slate-300/80 line-clamp-2">
                                        <?= nl2br(h($inc['descripcion'])) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2">
                                <span class="inline-flex px-2 py-0.5 rounded-full border bg-white/10 border-white/20 text-[11px] text-slate-100">
                                    <?= h($tipo) ?>
                                </span>
                            </td>
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
                    <h3 class="text-base font-semibold text-slate-50">
                        Nueva incidencia – <?= h($residencial['nombre']) ?> · unidad <?= h($ctx['unidad_clave']) ?>
                    </h3>
                    <p class="text-[11px] text-slate-300/80">Llena el reporte y se registrará en tu historial.</p>
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

            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="hidden" name="action" value="create">

                <div class="md:col-span-2">
                    <label class="block mb-1 text-slate-200" for="titulo">Título *</label>
                    <input type="text" id="titulo" name="titulo" required
                           value="<?= h($old['titulo']) ?>"
                           class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                           placeholder="Ej. Fuga de agua, ruido excesivo, portón no abre...">
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="tipo">Tipo</label>
                    <select id="tipo" name="tipo"
                            class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        <option value="seguridad"      <?= $old['tipo']==='seguridad' ? 'selected' : '' ?>>Seguridad</option>
                        <option value="servicio"       <?= $old['tipo']==='servicio' ? 'selected' : '' ?>>Servicios (agua, luz, etc.)</option>
                        <option value="vecino"         <?= $old['tipo']==='vecino' ? 'selected' : '' ?>>Problema con vecino</option>
                        <option value="infraestructura"<?= $old['tipo']==='infraestructura' ? 'selected' : '' ?>>Infraestructura</option>
                        <option value="otro"           <?= $old['tipo']==='otro' ? 'selected' : '' ?>>Otro</option>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block mb-1 text-slate-200" for="descripcion">Descripción *</label>
                    <textarea id="descripcion" name="descripcion" rows="4" required
                              class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50"
                              placeholder="Describe claramente lo que sucede, horarios, ubicación, etc."><?= h($old['descripcion']) ?></textarea>
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="prioridad">Prioridad</label>
                    <select id="prioridad" name="prioridad"
                            class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        <option value="baja"  <?= $old['prioridad']==='baja' ? 'selected' : '' ?>>Baja</option>
                        <option value="media" <?= $old['prioridad']==='media' ? 'selected' : '' ?>>Media</option>
                        <option value="alta"  <?= $old['prioridad']==='alta' ? 'selected' : '' ?>>Alta</option>
                    </select>
                </div>

                <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                    <button type="button"
                            onclick="window.location.href='incidencias.php#historial'"
                            class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-2xl bg-gradient-to-r from-rose-500 to-orange-500 text-[11px] font-medium text-white shadow-lg shadow-rose-900/40">
                        Enviar reporte
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
