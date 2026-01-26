<?php
// admin_residencial/unidades.php
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_residencial']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Unidades (casas / departamentos)';
$activeMenu = 'unidades';

$success = '';
$error   = '';

// 1) Obtener residencial principal del admin
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
}

if (!$residencial) {
    ob_start();
    ?>
    <div class="text-sm text-slate-200">
        No tienes residencial asignado. Pide al super admin que te asigne uno.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

// Estado del modal + valores del formulario
$showModalUnidad = isset($_GET['modal']) && $_GET['modal'] === 'new';
$formUnidad = [
    'tipo'             => 'casa',
    'clave'            => '',
    'calle'            => '',
    'numero_exterior'  => '',
    'numero_interior'  => '',
    'torre'            => '',
    'nivel'            => '',
    'notas'            => '',
    'activo'           => 1,
];

// 2) Alta de unidad
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo      = $_POST['tipo'] ?? 'casa';
    $clave     = trim($_POST['clave'] ?? '');
    $calle     = trim($_POST['calle'] ?? '');
    $num_ext   = trim($_POST['numero_exterior'] ?? '');
    $num_int   = trim($_POST['numero_interior'] ?? '');
    $torre     = trim($_POST['torre'] ?? '');
    $nivel     = trim($_POST['nivel'] ?? '');
    $notas     = trim($_POST['notas'] ?? '');
    $activo    = isset($_POST['activo']) ? 1 : 0;

    // mantener valores en el modal
    $formUnidad['tipo']            = $tipo;
    $formUnidad['clave']           = $clave;
    $formUnidad['calle']           = $calle;
    $formUnidad['numero_exterior'] = $num_ext;
    $formUnidad['numero_interior'] = $num_int;
    $formUnidad['torre']           = $torre;
    $formUnidad['nivel']           = $nivel;
    $formUnidad['notas']           = $notas;
    $formUnidad['activo']          = $activo;

    $errores = [];
    if ($clave === '') {
        $errores[] = 'La clave de la unidad es obligatoria.';
    }

    if (empty($errores)) {
        try {
            $stmtIns = $pdo->prepare("
                INSERT INTO unidades (
                  residencial_id, tipo, clave, calle, numero_exterior,
                  numero_interior, torre, nivel, notas, activo
                ) VALUES (
                  :residencial_id, :tipo, :clave, :calle, :num_ext,
                  :num_int, :torre, :nivel, :notas, :activo
                )
            ");
            $stmtIns->execute([
                'residencial_id' => $residencial['id'],
                'tipo'           => $tipo,
                'clave'          => $clave,
                'calle'          => $calle ?: null,
                'num_ext'        => $num_ext ?: null,
                'num_int'        => $num_int ?: null,
                'torre'          => $torre ?: null,
                'nivel'          => $nivel ?: null,
                'notas'          => $notas ?: null,
                'activo'         => $activo,
            ]);

            // redirect para evitar reenvío
            header('Location: unidades.php?ok=1');
            exit;
        } catch (PDOException $e) {
            $error = 'Error al crear la unidad: ' . $e->getMessage();
            $showModalUnidad = true;
        }
    } else {
        $error = implode(' ', $errores);
        $showModalUnidad = true;
    }
}

// mensaje tras redirect
if (isset($_GET['ok']) && !$success) {
    $success = 'Unidad creada correctamente.';
}

// 3) Listado de unidades del residencial
$unidades = [];
try {
    $stmtU = $pdo->prepare("
        SELECT * FROM unidades
        WHERE residencial_id = :residencial_id
        ORDER BY clave ASC
    ");
    $stmtU->execute(['residencial_id' => $residencial['id']]);
    $unidades = $stmtU->fetchAll();
} catch (PDOException $e) {
    $error = 'Error al obtener las unidades: ' . $e->getMessage();
}

ob_start();
?>

<div class="space-y-6 text-xs">

    <?php if ($success): ?>
        <div class="rounded-2xl bg-emerald-500/15 border border-emerald-400/50 px-4 py-3 text-emerald-100">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-rose-100">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Encabezado -->
    <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
            <h4 class="text-sm font-semibold">
                Unidades de <?= htmlspecialchars($residencial['nombre'], ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p class="text-[11px] text-slate-300/80">
                Administra las casas, departamentos o locales que forman parte de tu residencial.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="unidades.php?modal=new"
               class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                + Nueva unidad
            </a>
        </div>
    </div>

    <!-- Lista de unidades -->
    <div class="rounded-3xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[60vh] flex flex-col">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-semibold">Unidades registradas</h4>
            <span class="text-[11px] text-slate-300/80">Total: <?= count($unidades) ?></span>
        </div>

        <?php if (empty($unidades)): ?>
            <p class="text-xs text-slate-400">Aún no hay unidades registradas.</p>
        <?php else: ?>
            <div class="flex-1 overflow-x-auto">
                <table class="min-w-full text-xs border-separate border-spacing-y-1">
                    <thead class="text-[11px] uppercase text-slate-300/80">
                        <tr>
                            <th class="text-left px-3 py-2">Clave</th>
                            <th class="text-left px-3 py-2">Tipo</th>
                            <th class="text-left px-3 py-2">Ubicación</th>
                            <th class="text-left px-3 py-2">Notas</th>
                            <th class="text-left px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unidades as $u): ?>
                            <tr class="bg-white/5 hover:bg-white/10 transition rounded-xl">
                                <td class="px-3 py-2 rounded-l-xl font-medium text-slate-50">
                                    <?= htmlspecialchars($u['clave'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-3 py-2 text-slate-200/90">
                                    <?= htmlspecialchars($u['tipo'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-3 py-2 text-slate-200/90">
                                    <?php
                                    $ubicacion = trim(
                                        ($u['calle'] ?? '') . ' ' .
                                        ($u['numero_exterior'] ?? '')
                                    );
                                    if ($u['torre'])  $ubicacion .= ' · Torre ' . $u['torre'];
                                    if ($u['nivel'])  $ubicacion .= ' · Nivel ' . $u['nivel'];
                                    ?>
                                    <?= htmlspecialchars($ubicacion !== '' ? $ubicacion : 'Sin dirección detallada', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="px-3 py-2 text-slate-200/90">
                                    <?php if (!empty($u['notas'])): ?>
                                        <span class="line-clamp-2">
                                            <?= htmlspecialchars($u['notas'], ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-slate-400">Sin notas</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 rounded-r-xl">
                                    <?php if ((int)$u['activo'] === 1): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/40 text-[11px] text-emerald-100">
                                            Activa
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-slate-500/20 border border-slate-400/40 text-[11px] text-slate-200">
                                            Inactiva
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
// MODAL: nueva unidad
if ($showModalUnidad):
?>
    <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-xl">
        <div class="min-h-full flex items-center justify-center px-4 sm:px-6 py-8">
            <div class="relative w-full max-w-3xl rounded-3xl border border-white/15 bg-slate-950/95 shadow-2xl overflow-hidden">
                <div class="px-6 sm:px-8 pt-5 sm:pt-6 pb-6 sm:pb-7 flex flex-col gap-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg sm:text-xl font-semibold text-white">
                                Nueva unidad
                            </h2>
                            <p class="text-[11px] sm:text-xs text-slate-300/85">
                                Registra una casa, departamento, local u otra unidad dentro del residencial.
                            </p>
                        </div>
                        <button
                            type="button"
                            onclick="window.location.href='unidades.php'"
                            class="shrink-0 h-9 w-9 rounded-full bg-slate-900/80 border border-white/15 flex items-center justify-center text-slate-300 hover:bg-slate-800 hover:text-white transition"
                        >
                            ✕
                        </button>
                    </div>

                    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs sm:text-sm text-slate-100">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="clave">Clave / número *</label>
                            <input
                                type="text"
                                id="clave"
                                name="clave"
                                required
                                value="<?= htmlspecialchars($formUnidad['clave'], ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                                placeholder="Ej. C-101, T1-302"
                            >
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="tipo">Tipo</label>
                            <select
                                id="tipo"
                                name="tipo"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                            >
                                <option value="casa" <?= $formUnidad['tipo']==='casa'?'selected':'' ?>>Casa</option>
                                <option value="departamento" <?= $formUnidad['tipo']==='departamento'?'selected':'' ?>>Departamento</option>
                                <option value="local" <?= $formUnidad['tipo']==='local'?'selected':'' ?>>Local</option>
                                <option value="otro" <?= $formUnidad['tipo']==='otro'?'selected':'' ?>>Otro</option>
                            </select>
                        </div>

                        <div class="flex items-center mt-1 md:mt-5">
                            <label class="inline-flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="activo"
                                    class="rounded border-white/30 bg-slate-900 text-sky-500 focus:ring-sky-500/70"
                                    <?= $formUnidad['activo'] ? 'checked' : '' ?>
                                >
                                <span class="text-[11px] text-slate-200">Unidad activa</span>
                            </label>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="calle">Calle</label>
                            <input
                                type="text"
                                id="calle"
                                name="calle"
                                value="<?= htmlspecialchars($formUnidad['calle'], ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                            >
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="numero_exterior">Número exterior</label>
                            <input
                                type="text"
                                id="numero_exterior"
                                name="numero_exterior"
                                value="<?= htmlspecialchars($formUnidad['numero_exterior'], ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                            >
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="numero_interior">Número interior</label>
                            <input
                                type="text"
                                id="numero_interior"
                                name="numero_interior"
                                value="<?= htmlspecialchars($formUnidad['numero_interior'], ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                            >
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="torre">Torre / edificio</label>
                            <input
                                type="text"
                                id="torre"
                                name="torre"
                                value="<?= htmlspecialchars($formUnidad['torre'], ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                            >
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="nivel">Piso / nivel</label>
                            <input
                                type="text"
                                id="nivel"
                                name="nivel"
                                value="<?= htmlspecialchars($formUnidad['nivel'], ENT_QUOTES, 'UTF-8') ?>"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                            >
                        </div>

                        <div class="md:col-span-3 flex flex-col gap-1.5">
                            <label class="text-[11px] font-medium text-slate-200" for="notas">Notas</label>
                            <textarea
                                id="notas"
                                name="notas"
                                rows="2"
                                class="w-full rounded-2xl bg-slate-900/70 border border-white/12 px-3.5 py-2.5 text-xs sm:text-sm text-slate-50 leading-relaxed focus:outline-none focus:ring-2 focus:ring-sky-500/60 focus:border-sky-500/60"
                            ><?= htmlspecialchars($formUnidad['notas'], ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                            <button
                                type="button"
                                onclick="window.location.href='unidades.php'"
                                class="rounded-2xl border border-white/15 bg-slate-900/80 px-4 py-2.5 text-[11px] sm:text-xs font-medium text-slate-100 hover:bg-slate-800 transition"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-5 sm:px-6 py-2.5 text-[11px] sm:text-xs font-semibold text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition"
                            >
                                Crear unidad
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php
endif;

$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
