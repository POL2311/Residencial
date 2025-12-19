<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['admin_supervisor', 'super_admin']);

$pageTitle  = 'Panel de supervisión';
$activeMenu = 'reports';

ob_start();
?>
<div class="space-y-4">
    <p class="text-sm text-slate-200/90">
        Aquí el supervisor puede visualizar reportes globales de los servicios sin modificar configuración.
    </p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-5">
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <h4 class="text-sm font-semibold mb-2">Resumen de accesos</h4>
            <p class="text-[13px] text-slate-200/90">Gráfica / tabla de accesos por día, tipo de usuario, etc.</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4">
            <h4 class="text-sm font-semibold mb-2">Reportes de incidencias</h4>
            <p class="text-[13px] text-slate-200/90">Listado de incidencias con filtros por residencial, estado y fecha.</p>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

include __DIR__ . '/../layouts/dashboard_layout.php';
