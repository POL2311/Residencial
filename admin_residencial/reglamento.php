<?php
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_residencial']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Reglamento del residencial';
$activeMenu = 'reglamento';

$success = '';
$error   = '';

// 1) Obtener residencial principal del admin_residencial
try {
    $stmt = $pdo->prepare("
        SELECT r.*, ur.id AS ur_id
        FROM usuarios_residenciales ur
        JOIN residenciales r ON r.id = ur.residencial_id
        WHERE ur.user_id = :user_id
        ORDER BY ur.es_principal DESC, ur.created_at ASC
        LIMIT 1
    ");
    $stmt->execute(['user_id' => $user['id']]);
    $residencial = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener tu residencial: ' . $e->getMessage();
}

if (!$residencial) {
    ob_start(); ?>
    <div class="text-sm text-slate-200">
        Tu usuario aún no está asignado a un residencial.
        Pide al superadmin que te vincule a uno.
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/../layouts/dashboard_layout.php';
    exit;
}

$residencialId = (int) $residencial['id'];

// 2) Crear / actualizar reglamento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = trim($_POST['contenido'] ?? '');
    $version   = trim($_POST['version_label'] ?? 'v1');
    $esPublico = isset($_POST['es_publico']) ? 1 : 0;

    if ($titulo === '' || $contenido === '') {
        $error = 'El título y el contenido del reglamento son obligatorios.';
    } else {
        try {
            // ¿Ya existe reglamento para este residencial?
            $stmtCheck = $pdo->prepare("
                SELECT id FROM reglamentos_residenciales
                WHERE residencial_id = :id
                LIMIT 1
            ");
            $stmtCheck->execute(['id' => $residencialId]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                // update
                $stmtUp = $pdo->prepare("
                    UPDATE reglamentos_residenciales
                    SET titulo = :titulo,
                        contenido = :contenido,
                        version_label = :version_label,
                        es_publico = :es_publico,
                        updated_by = :user_id
                    WHERE residencial_id = :residencial_id
                    LIMIT 1
                ");
                $stmtUp->execute([
                    'titulo'         => $titulo,
                    'contenido'      => $contenido,
                    'version_label'  => $version,
                    'es_publico'     => $esPublico,
                    'user_id'        => $user['id'],
                    'residencial_id' => $residencialId,
                ]);
                $success = 'Reglamento actualizado correctamente.';
            } else {
                // insert
                $stmtIns = $pdo->prepare("
                    INSERT INTO reglamentos_residenciales
                    (residencial_id, titulo, contenido, version_label, es_publico, created_by, updated_by)
                    VALUES
                    (:residencial_id, :titulo, :contenido, :version_label, :es_publico, :user_id, :user_id)
                ");
                $stmtIns->execute([
                    'residencial_id' => $residencialId,
                    'titulo'         => $titulo,
                    'contenido'      => $contenido,
                    'version_label'  => $version,
                    'es_publico'     => $esPublico,
                    'user_id'        => $user['id'],
                ]);
                $success = 'Reglamento creado correctamente.';
            }

            // redirigir para evitar reenvío del formulario
            header('Location: reglamento.php?ok=1');
            exit;

        } catch (PDOException $e) {
            $error = 'Error al guardar el reglamento: ' . $e->getMessage();
        }
    }
}

if (isset($_GET['ok']) && !$success) {
    $success = 'Cambios guardados correctamente.';
}

// 3) Obtener reglamento actual (si existe)
$reglamento = null;
try {
    $stmtReg = $pdo->prepare("
        SELECT *
        FROM reglamentos_residenciales
        WHERE residencial_id = :id
        LIMIT 1
    ");
    $stmtReg->execute(['id' => $residencialId]);
    $reglamento = $stmtReg->fetch();
} catch (PDOException $e) {
    $error = 'Error al obtener el reglamento: ' . $e->getMessage();
}

// 4) ¿mostramos modal?
$showModal = isset($_GET['modal']) && $_GET['modal'] === 'edit';

// 5) Render
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
                Reglamento de <?= htmlspecialchars($residencial['nombre'], ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p class="text-[11px] text-slate-300/80">
                Aquí defines las reglas internas que verán los residentes en su portal.
            </p>
        </div>
        <div class="flex gap-2">
            <a href="reglamento.php?modal=edit"
               class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                ✏️ Editar reglamento
            </a>
        </div>
    </div>

    <div class="rounded-3xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4 md:p-6 min-h-[65vh] md:min-h-[50vh] flex flex-col">
        <?php if (!$reglamento): ?>
            <p class="text-xs text-slate-300/90">
                Aún no has registrado un reglamento para este residencial.
                Haz clic en <span class="font-semibold">“Editar reglamento”</span> para crear uno.
            </p>
        <?php else: ?>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h5 class="text-sm font-semibold text-slate-50">
                        <?= htmlspecialchars($reglamento['titulo'], ENT_QUOTES, 'UTF-8') ?>
                    </h5>
                    <p class="text-[11px] text-slate-300/80">
                        Versión <?= htmlspecialchars($reglamento['version_label'], ENT_QUOTES, 'UTF-8') ?>
                        · <?= $reglamento['es_publico'] ? 'Visible para residentes' : 'Solo administración' ?>
                    </p>
                </div>
            </div>

            <!-- Contenido ocupa el resto de la card -->
            <div class="rounded-2xl border border-white/10 bg-black/30 p-4 text-xs text-slate-100 flex-1 overflow-y-auto leading-relaxed whitespace-pre-wrap">
                <?= nl2br(htmlspecialchars($reglamento['contenido'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// 6) Modal grande para crear / editar
if ($showModal):

    $titulo    = $reglamento['titulo']        ?? 'Reglamento interno';
    $version   = $reglamento['version_label'] ?? 'v1';
    $contenido = $reglamento['contenido']     ?? '';
    $esPublico = isset($reglamento['es_publico']) ? (int)$reglamento['es_publico'] : 1;
    ?>
    <div class="fixed inset-0 z-40 bg-slate-950/80 backdrop-blur-sm overflow-y-auto">
        <div class="min-h-screen flex items-stretch px-2 md:px-8 py-4">
            <div class="w-full h-full mx-auto rounded-3xl border border-white/15 bg-slate-950/95 p-6 md:p-8 shadow-2xl  flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-50">
                            Editar reglamento – <?= htmlspecialchars($residencial['nombre'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                    </div>
                    <button type="button"
                            onclick="window.location.href='reglamento.php'"
                            class="h-8 w-8 rounded-full border border-white/20 bg-white/5 text-sm flex items-center justify-center">
                        ✕
                    </button>
                </div>

                <form method="post" class="flex-1 flex flex-col gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block mb-1 text-slate-200" for="titulo">Título *</label>
                            <input type="text" id="titulo" name="titulo" required
                                   value="<?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        </div>
                        <div>
                            <label class="block mb-1 text-slate-200" for="version_label">Versión</label>
                            <input type="text" id="version_label" name="version_label"
                                   value="<?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?>"
                                   class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50">
                        </div>
                    </div>

                    <div class="flex-1">
                        <label class="block mb-1 text-slate-100" for="contenido">
                            Contenido del reglamento *
                        </label>
                        <textarea id="contenido" name="contenido" required
                                  class="w-full h-full min-h-[260px] md:min-h-[360px] rounded-2xl border border-white/15 bg-black/40 px-3 py-2 text-xs text-slate-50 leading-relaxed"
                                  placeholder="Escribe aquí las reglas, horarios, sanciones, uso de amenidades, etc."><?= htmlspecialchars($contenido, ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div>
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="es_publico"
                                   class="rounded border-white/30 bg-white/5 text-sky-500"
                                <?= $esPublico ? 'checked' : '' ?>>
                            <span class="text-[11px] text-slate-200">
                                Mostrar este reglamento en el portal del residente
                            </span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button"
                                onclick="window.location.href='reglamento.php'"
                                class="px-4 py-2 rounded-2xl border border-white/20 bg-white/5 text-[11px]">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="px-4 py-2 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 text-[11px] font-medium text-white shadow-lg shadow-sky-900/40">
                            Guardar reglamento
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
<?php
endif;

$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
