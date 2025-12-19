<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['super_admin']);

$pageTitle  = 'Configuración general (Super Admin)';
$activeMenu = 'settings';

$success = '';
$error   = '';

// Cargar config
try {
    $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
    $config = $stmt->fetch();
    if (!$config) {
        $pdo->exec("
            INSERT INTO config_general (nombre_sistema, empresa, email_soporte)
            VALUES ('Sistema Residencial', 'Tu Empresa', 'soporte@tuempresa.com')
        ");
        $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
        $config = $stmt->fetch();
    }
} catch (PDOException $e) {
    $error = 'Error al cargar la configuración general: ' . $e->getMessage();
    $config = null;
}

// Guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config) {
    $nombre_sistema  = trim($_POST['nombre_sistema'] ?? '');
    $empresa         = trim($_POST['empresa'] ?? '');
    $email_soporte   = trim($_POST['email_soporte'] ?? '');
    $logo_url        = trim($_POST['logo_url'] ?? '');
    $color_primario  = trim($_POST['color_primario'] ?? '');
    $color_secundario= trim($_POST['color_secundario'] ?? '');

    if ($nombre_sistema === '') {
        $nombre_sistema = 'Sistema Residencial';
    }

    if ($email_soporte !== '' && !filter_var($email_soporte, FILTER_VALIDATE_EMAIL)) {
        $error = 'El email de soporte no tiene un formato válido.';
    } else {
        try {
            $stmtUpd = $pdo->prepare("
                UPDATE config_general
                SET nombre_sistema = :nombre_sistema,
                    empresa = :empresa,
                    email_soporte = :email_soporte,
                    logo_url = :logo_url,
                    color_primario = :color_primario,
                    color_secundario = :color_secundario
                WHERE id = :id
            ");
            $stmtUpd->execute([
                'nombre_sistema'   => $nombre_sistema,
                'empresa'          => $empresa !== '' ? $empresa : null,
                'email_soporte'    => $email_soporte !== '' ? $email_soporte : null,
                'logo_url'         => $logo_url !== '' ? $logo_url : null,
                'color_primario'   => $color_primario !== '' ? $color_primario : null,
                'color_secundario' => $color_secundario !== '' ? $color_secundario : null,
                'id'               => $config['id'],
            ]);

            $success = 'Configuración general actualizada.';
            $stmt = $pdo->query("SELECT * FROM config_general ORDER BY id ASC LIMIT 1");
            $config = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Error al guardar configuración general: ' . $e->getMessage();
        }
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
            <h4 class="text-sm font-semibold mb-3">Branding y datos generales</h4>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block mb-1 text-slate-200" for="nombre_sistema">
                        Nombre del sistema
                    </label>
                    <input
                        type="text"
                        id="nombre_sistema"
                        name="nombre_sistema"
                        value="<?= htmlspecialchars($config['nombre_sistema'], ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                    >
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="empresa">
                        Nombre de la empresa
                    </label>
                    <input
                        type="text"
                        id="empresa"
                        name="empresa"
                        value="<?= htmlspecialchars($config['empresa'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                    >
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="email_soporte">
                        Email de soporte
                    </label>
                    <input
                        type="email"
                        id="email_soporte"
                        name="email_soporte"
                        value="<?= htmlspecialchars($config['email_soporte'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                    >
                </div>

                <div class="md:col-span-2">
                    <label class="block mb-1 text-slate-200" for="logo_url">
                        URL del logo principal
                    </label>
                    <input
                        type="text"
                        id="logo_url"
                        name="logo_url"
                        value="<?= htmlspecialchars($config['logo_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                        placeholder="https://..."
                    >
                    <p class="mt-1 text-[11px] text-slate-300/80">
                        Más adelante puedes usar esto para mostrar el logo en el login y dashboard.
                    </p>
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="color_primario">
                        Color primario (hex)
                    </label>
                    <input
                        type="text"
                        id="color_primario"
                        name="color_primario"
                        value="<?= htmlspecialchars($config['color_primario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                        placeholder="#0ea5e9"
                    >
                </div>

                <div>
                    <label class="block mb-1 text-slate-200" for="color_secundario">
                        Color secundario (hex)
                    </label>
                    <input
                        type="text"
                        id="color_secundario"
                        name="color_secundario"
                        value="<?= htmlspecialchars($config['color_secundario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        class="w-full rounded-2xl border border-white/15 bg-white/5 px-3 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-400/60"
                        placeholder="#1e293b"
                    >
                </div>

                <div class="md:col-span-2 flex justify-end mt-2">
                    <button
                        type="submit"
                        class="inline-flex items-center gap-1 rounded-2xl bg-gradient-to-r from-sky-500 to-indigo-500 px-4 py-2 text-xs font-medium text-white shadow-lg shadow-sky-900/40 hover:from-sky-400 hover:to-indigo-400 transition"
                    >
                        Guardar configuración
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
