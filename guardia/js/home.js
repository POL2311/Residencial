window.GuardiaViews = window.GuardiaViews || {};

window.GuardiaViews.home = function (ctx) {
  const els = {
    turnoInfo: document.getElementById('homeTurnoInfo'),
    accesosHoy: document.getElementById('statAccesosHoy'),
    incidenciasAbiertas: document.getElementById('statIncidenciasAbiertas'),
    paquetesPendientes: document.getElementById('statPaquetesPendientes'),
    autosHoy: document.getElementById('statAutosHoy'),
    recentActivity: document.getElementById('homeRecentActivity'),
    actionButtons: document.querySelectorAll('[data-home-action]'),
  };

  function renderStats(data) {
    const stats = data?.stats || {};

    if (els.turnoInfo) {
      els.turnoInfo.textContent = `Turno actual: ${data?.turno_actual || '—'}`;
    }

    if (els.accesosHoy) els.accesosHoy.textContent = stats.accesos_hoy ?? 0;
    if (els.incidenciasAbiertas) els.incidenciasAbiertas.textContent = stats.incidencias_abiertas ?? 0;
    if (els.paquetesPendientes) els.paquetesPendientes.textContent = stats.paquetes_pendientes ?? 0;
    if (els.autosHoy) els.autosHoy.textContent = stats.autos_registrados_hoy ?? 0;
  }

  function renderRecentActivity() {
    if (!els.recentActivity) return;

    els.recentActivity.innerHTML = `
      <div class="rounded-2xl border border-slate-200 p-4 flex items-start justify-between gap-3">
        <div>
          <div class="font-medium text-slate-800">Sistema listo para operar</div>
          <div class="text-sm text-slate-500">Tu panel de guardia está activo y preparado.</div>
        </div>
        <div class="text-xs text-slate-400">ahora</div>
      </div>

      <div class="rounded-2xl border border-slate-200 p-4 flex items-start justify-between gap-3">
        <div>
          <div class="font-medium text-slate-800">Consulta accesos e incidencias</div>
          <div class="text-sm text-slate-500">Usa los accesos rápidos para registrar movimiento.</div>
        </div>
        <div class="text-xs text-slate-400">turno</div>
      </div>
    `;
  }

  function bindActions() {
    els.actionButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        const view = btn.dataset.homeAction;
        if (view) ctx.navigate(view);
      });
    });
  }

  async function mount() {
    const data = ctx.getContext?.() || await ctx.loadContext?.();
    renderStats(data || {});
    renderRecentActivity();
    bindActions();
  }

  mount();

  return {
    unmount() {}
  };
};