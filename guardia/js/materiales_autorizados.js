(function () {
  window.GuardiaViews = window.GuardiaViews || {};
  window.GuardiaViews.materiales_autorizados = function ({ API, fetchJSON, escapeHtml }) {
    const root = document.getElementById('materialesAutorizadosView');
    if (!root) return;
    const list = document.getElementById('materialesAutorizadosList');

    async function load() {
      try {
        const json = await fetchJSON(`${API}accesos.php?action=materiales_autorizados`);
        const items = json.data?.items || [];
        if (!items.length) {
          list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay permisos activos en este momento.</div>`;
          return;
        }
        list.innerHTML = items.map((item) => `
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
              <div>
                <div class="flex items-center gap-2">
                  <div class="text-lg font-semibold text-slate-800">${escapeHtml(item.tipo_movimiento === 'salida' ? 'Salida autorizada' : 'Entrada autorizada')}</div>
                  <span class="rounded-full px-2.5 py-1 text-xs ${item.estado === 'aprobado' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700'}">${escapeHtml(item.estado || '')}</span>
                </div>
                <div class="mt-1 text-sm text-slate-600">Responsable: ${escapeHtml(item.responsable_nombre || 'Sin responsable')} · Área: ${escapeHtml(item.area_nombre || 'Sin área')}</div>
                <div class="mt-3 space-y-2">
                  ${(item.items || []).map((row) => `<div class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">${escapeHtml(row.material_nombre || '')} · ${escapeHtml(row.cantidad_texto || '')}</div>`).join('')}
                </div>
              </div>
              <div class="text-xs text-slate-400">Aprobado: ${escapeHtml(item.aprobado_at || '—')}</div>
            </div>
          </div>
        `).join('');
      } catch (e) {
        list.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(e.message || 'No se pudo cargar la información.')}</div>`;
      }
    }

    load();
    return {};
  };
})();
