window.GuardiaViews = window.GuardiaViews || {};

window.GuardiaViews.home = function (ctx) {
  const els = {
    accesosHoy: document.getElementById('statAccesosHoy'),
    incidenciasAbiertas: document.getElementById('statIncidenciasAbiertas'),
    paquetesPendientes: document.getElementById('statPaquetesPendientes'),
    autosHoy: document.getElementById('statAutosHoy'),
    actionButtons: document.querySelectorAll('[data-home-action]'),
  };

  function renderStats(data) {
    const stats = data?.stats || {};

    if (els.accesosHoy) els.accesosHoy.textContent = stats.accesos_hoy ?? 0;
    if (els.incidenciasAbiertas) els.incidenciasAbiertas.textContent = stats.incidencias_abiertas ?? 0;
    if (els.paquetesPendientes) els.paquetesPendientes.textContent = stats.paquetes_pendientes ?? 0;
    if (els.autosHoy) els.autosHoy.textContent = stats.autos_registrados_hoy ?? 0;
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
    bindActions();
  }

  mount();

  return {
    unmount() {}
  };
};
