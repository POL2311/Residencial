<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['super_admin']);

$pageTitle  = 'Seguridad y accesos (Super Admin)';
$activeMenu = 'security';

$success = '';
$error   = '';

try {
    $stmt = $pdo->query("SELECT * FROM config_seguridad ORDER BY id ASC LIMIT 1");
    $config = $stmt->fetch();
    if (!$config) {
        $pdo->exec("
            INSERT INTO config_seguridad (max_intentos_login, minutos_bloqueo_login, tiempo_sesion_minutos, registrar_logs_acceso)
            VALUES (5, 15, 60, 1)
        ");
        $stmt = $pdo->query("SELECT * FROM config_seguridad ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = 'Error al cargar la configuración de seguridad: ' . $e->getMessage();
    $config = null;
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config) {
    $max_intentos   = (int)($_POST['max_intentos_login'] ?? 5);
    $min_bloqueo    = (int)($_POST['minutos_bloqueo_login'] ?? 15);
    $min_sesion     = (int)($_POST['tiempo_sesion_minutos'] ?? 60);
    $logs           = isset($_POST['registrar_logs_acceso']) ? 1 : 0;

    if ($max_intentos <= 0) $max_intentos = 1;
    if ($min_bloqueo < 0)   $min_bloqueo = 0;
    if ($min_sesion <= 0)   $min_sesion = 10;

    try {
        $stmtUpd = $pdo->prepare("
            UPDATE config_seguridad
            SET max_intentos_login = :max_intentos,
                minutos_bloqueo_login = :min_bloqueo,
                tiempo_sesion_minutos = :min_sesion,
                registrar_logs_acceso = :logs
            WHERE id = :id
        ");
        $stmtUpd->execute([
            'max_intentos' => $max_intentos,
            'min_bloqueo'  => $min_bloqueo,
            'min_sesion'   => $min_sesion,
            'logs'         => $logs,
            'id'           => $config['id'],
        ]);

        $success = 'Configuración de seguridad actualizada.';
        $stmt = $pdo->query("SELECT * FROM config_seguridad ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Error al guardar configuración: ' . $e->getMessage();
    }
}

ob_start();
?>

<div class="space-y-6">

    <?php if ($success): ?>
        <div class="rounded-2xl bg-emerald-500/15 border border-emerald-400/50 px-4 py-3 text-xs text-emerald-100">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="rounded-2xl bg-rose-500/15 border border-rose-400/50 px-4 py-3 text-xs text-rose-100">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($config): ?>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 md:p-5 text-xs">
            <h4 class="text-sm font-semibold mb-3">Políticas de seguridad</h4>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-1 text-slate-200" for="max_intentos_login">
                        Máximo de intentos fallidos de login
                    </label>
                    <input
                        type="number"
                        id="max_intentos_login"
                        name="max_intentos_login"
                        min="1"
                        value="<?= (int)$config['max_intentos_login'] ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                    >
                    <p class="mt-1 text-[11px] text-slate-300/80">
                        Después de este número de intentos, la cuenta podrá ser bloqueada temporalmente.
                    </p>
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="minutos_bloqueo_login">
                        Minutos de bloqueo tras exceder intentos
                    </label>
                    <input
                        type="number"
                        id="minutos_bloqueo_login"
                        name="minutos_bloqueo_login"
                        min="0"
                        value="<?= (int)$config['minutos_bloqueo_login'] ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                    >
                    <p class="mt-1 text-[11px] text-slate-300/80">
                        0 = sin bloqueo automático, solo registro en logs.
                    </p>
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="tiempo_sesion_minutos">
                        Tiempo de sesión (minutos)
                    </label>
                    <input
                        type="number"
                        id="tiempo_sesion_minutos"
                        name="tiempo_sesion_minutos"
                        min="10"
                        value="<?= (int)$config['tiempo_sesion_minutos'] ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                    >
                    <p class="mt-1 text-[11px] text-slate-300/80">
                        Tiempo máximo de inactividad antes de cerrar sesión automáticamente.
                    </p>
                </div>

                <div class="flex items-center gap-2 mt-4">
                    <label class="inline-flex items-center gap-2">
                        <input
                            type="checkbox"
                            name="registrar_logs_acceso"
                            class="rounded border-white/30 bg-white/5 text-sky-500 focus:ring-sky-400/60"
                            <?= (int)$config['registrar_logs_acceso'] === 1 ? 'checked' : '' ?>
                        >
                        <span class="text-[11px] text-slate-200">
                            Registrar logs de acceso al sistema
                        </span>
                    </label>
                </div>

                <div class="md:col-span-2 flex justify-end mt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition"
                    >
                        Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
