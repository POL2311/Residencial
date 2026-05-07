(function () {
  window.GuardiaViews = window.GuardiaViews || {};
  window.GuardiaViews.personas_dentro = function ({ API, fetchJSON, escapeHtml, navigate, openModal }) {
    const root = document.getElementById('personasDentroView');
    if (!root) return;
    const list = document.getElementById('personasDentroList');
    const btnScan = document.getElementById('btnScanPersonalQr');
    const AUTO_SCAN_KEY = 'guardia:accesos:auto_scan';

    function openPersonDetail(item) {
      if (typeof openModal !== 'function' || !item) return;
      openModal('Detalle de personal dentro', `
        <div class="space-y-4">
          <div class="flex items-start gap-4">
            <div class="h-20 w-20 overflow-hidden rounded-2xl bg-slate-100">
              ${item.foto_url ? `<img src="${escapeHtml(item.foto_url)}" alt="${escapeHtml(item.nombre)}" class="h-full w-full object-cover" loading="lazy" />` : `<div class="flex h-full items-center justify-center text-xs text-slate-400">Sin foto</div>`}
            </div>
            <div class="min-w-0">
              <div class="text-lg font-semibold text-slate-800">${escapeHtml(item.nombre || '')}</div>
              <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.empresa || 'Sin empresa')} · ${escapeHtml(item.puesto || 'Sin puesto')}</div>
            </div>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Área</div>
              <div class="text-sm text-slate-700">${escapeHtml(item.area_nombre || 'Sin área')}</div>
            </div>
            <div>
              <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Teléfono</div>
              <div class="text-sm text-slate-700">${escapeHtml(item.telefono || 'Sin teléfono')}</div>
            </div>
            <div class="sm:col-span-2">
              <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Última entrada</div>
              <div class="text-sm text-sky-700">${escapeHtml(item.ultima_entrada_at || '—')}</div>
            </div>
          </div>
        </div>
      `);
    }

    async function load() {
      try {
        const json = await fetchJSON(`${API}accesos.php?action=personas_dentro`);
        const items = json.data?.items || [];
        if (!items.length) {
          list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay personal marcado dentro en este momento.</div>`;
          return;
        }
        list.innerHTML = items.map((item) => `
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex gap-4">
              <div class="h-16 w-16 overflow-hidden rounded-2xl bg-slate-100">
                ${item.foto_url ? `<img src="${escapeHtml(item.foto_url)}" alt="${escapeHtml(item.nombre)}" class="h-full w-full object-cover" loading="lazy" />` : `<div class="flex h-full items-center justify-center text-xs text-slate-400">Sin foto</div>`}
              </div>
              <div class="min-w-0 flex-1">
                <div class="text-lg font-semibold text-slate-800">${escapeHtml(item.nombre || '')}</div>
                <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.empresa || 'Sin empresa')} · ${escapeHtml(item.puesto || 'Sin puesto')}</div>
                <div class="app-mobile-secondary mt-1 text-sm text-slate-500">Área: <b>${escapeHtml(item.area_nombre || 'Sin área')}</b> · ${escapeHtml(item.telefono || 'Sin teléfono')}</div>
                <div class="mt-2 inline-flex rounded-full bg-sky-100 px-3 py-1 text-xs text-sky-700">Entró: ${escapeHtml(item.ultima_entrada_at || '—')}</div>
                <div class="mt-3">
                  <button type="button" class="app-mobile-more hidden rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50" data-persona-index="${items.indexOf(item)}">
                    Ver más
                  </button>
                </div>
              </div>
            </div>
          </div>
        `).join('');

        list.querySelectorAll('[data-persona-index]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const index = Number(btn.dataset.personaIndex || -1);
            if (index >= 0 && index < items.length) openPersonDetail(items[index]);
          });
        });
      } catch (e) {
        list.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(e.message || 'No se pudo cargar el listado.')}</div>`;
      }
    }

    load();
    btnScan?.addEventListener('click', () => {
      try {
        window.sessionStorage.setItem(AUTO_SCAN_KEY, 'persona_recurrente');
      } catch (_) {}

      if (window.location.hash.replace('#', '') !== 'accesos') {
        window.location.hash = 'accesos';
      } else if (typeof navigate === 'function') {
        navigate('accesos');
      }
    });
    return {};
  };
})();
