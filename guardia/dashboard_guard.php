<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/auth.php';
require_role(['guardia', 'super_admin']);

$pageTitle  = 'Panel de guardia';
$activeMenu = 'security';

ob_start();
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 md:gap-5">
    <div class="rounded-2xl border border-white/15 bg-white/5 backdrop-blur-xl p-4 flex flex-col gap-3">
        <h4 class="text-sm font-semibold">Accesos rápidos</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
<a href="accesos.php"
       class="w-full inline-flex items-center justify-center rounded-2xl border border-sky-400/40 bg-sky-500/10 px-4 py-2 text-xs font-medium text-sky-50 hover:bg-sky-500/20 transition">
        Escanear QR de visita
    </a>

    <!-- Registrar entrada manual (por ahora mismo módulo pero con query param) -->
    <a href="accesos.php?modo=manual"
       class="w-full inline-flex items-center justify-center rounded-2xl border border-emerald-400/40 bg-emerald-500/10 px-4 py-2 text-xs font-medium text-emerald-50 hover:bg-emerald-500/20 transition">
        Registrar entrada manual
    </a>

    <a href="paqueteria.php"
       class="w-full inline-flex items-center justify-center rounded-2xl border border-indigo-400/40 bg-indigo-500/10 px-4 py-2 text-xs font-medium text-indigo-50 hover:bg-indigo-500/20 transition">
        Registrar paquetería
    </a>
    <a href="incidencias.php"
       class="w-full inline-flex items-center justify-center rounded-2xl border border-rose-400/40 bg-rose-500/10 px-4 py-2 text-xs font-medium text-rose-50 hover:bg-rose-500/20 transition">
        Reportar incidencia
    </a>
        </div>

    </div>

    <div class="rounded-2xl border border-white/15 bg-slate-950/40 backdrop-blur-xl p-4">
        <h4 class="text-sm font-semibold mb-3">Visitas en curso</h4>
        <p class="text-xs text-slate-300/80">
            Aquí aparecerá la lista de visitas que ya entraron pero aún no han registrado salida.
        </p>
    </div>
</div>
<?php
$content = ob_get_clean();

include __DIR__ . '/../layouts/dashboard_layout.php';
