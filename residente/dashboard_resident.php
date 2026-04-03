<?php
require_once __DIR__ . '/../config/auth.php';
require_role(['residente']);
require_once __DIR__ . '/../config/config.php';

$user       = current_user();
$pageTitle  = 'Portal del residente';
$activeMenu = 'overview';

// obtener residencial/unidad para mostrar contexto (opcional)
$ctx = null;
try {
    $stmtRU = $pdo->prepare("
        SELECT r.nombre AS residencial_nombre, u.clave AS unidad_clave
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
} catch (PDOException $e) { $ctx = null; }
?>
<?php ob_start(); ?>

<div class="space-y-5 text-xs">

    <?php if ($ctx): ?>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <div class="text-[11px] text-slate-300/80">
                Residencial: <span class="text-slate-100 font-medium"><?= htmlspecialchars($ctx['residencial_nombre'], ENT_QUOTES, 'UTF-8') ?></span>
                · Unidad: <span class="text-slate-100 font-medium"><?= htmlspecialchars($ctx['unidad_clave'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <!-- Estado de mantenimiento (placeholder por ahora) -->
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-5">
            <h3 class="text-sm font-semibold mb-2">Estado de mantenimiento</h3>
            <p class="text-xs text-slate-100 mb-1">Al corriente</p>
            <p class="text-[11px] text-emerald-300">No tienes adeudos pendientes.</p>
        </div>

        <!-- Accesos rápidos -->
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-5">
            <h3 class="text-sm font-semibold mb-3">Accesos rápidos</h3>
            <div class="flex flex-wrap gap-2">

                <a href="visitas.php?new=1#historial"
                   class="inline-flex items-center px-4 py-2 rounded-2xl bg-sky-500/90 hover:bg-sky-400 text-xs font-medium text-white shadow-lg shadow-sky-900/40">
                    Generar QR de visita
                </a>

                <a href="visitas.php#historial"
                   class="inline-flex items-center px-4 py-2 rounded-2xl bg-indigo-500/80 hover:bg-indigo-400 text-xs font-medium text-white shadow-lg shadow-indigo-900/40">
                    Ver historial de visitas
                </a>

                <a href="incidencias.php"
                   class="inline-flex items-center px-4 py-2 rounded-2xl bg-rose-500/80 hover:bg-rose-400 text-xs font-medium text-white shadow-lg shadow-rose-900/40">
                    Reportar incidencia
                </a>

                <a href="reglamento.php"
                   class="inline-flex items-center px-4 py-2 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/15 text-xs font-medium text-slate-50">
                    Ver reglamento
                </a>

                <a href="comunicados.php"
                   class="inline-flex items-center px-4 py-2 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/15 text-xs font-medium text-slate-50">
                    Comunicados
                </a>

                <a href="pagos.php"
                   class="inline-flex items-center px-4 py-2 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/15 text-xs font-medium text-slate-50">
                    Historial de pagos
                </a>

                <a href="perfil.php"
                   class="inline-flex items-center px-4 py-2 rounded-2xl bg-white/10 border border-white/20 hover:bg-white/15 text-xs font-medium text-slate-50">
                    Editar perfil
                </a>
            </div>
        </div>

    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layouts/dashboard_layout.php';
