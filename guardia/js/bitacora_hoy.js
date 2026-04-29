(function () {
  window.GuardiaViews = window.GuardiaViews || {};
  window.GuardiaViews.bitacora_hoy = function ({ API, fetchJSON, escapeHtml }) {
    const root = document.getElementById('bitacoraHoyView');
    if (!root) return;
    const list = document.getElementById('bitacoraHoyList');

    async function load() {
      try {
        const json = await fetchJSON(`${API}accesos.php?action=bitacora_hoy`);
        const items = json.data?.items || [];
        if (!items.length) {
          list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">Todavía no hay movimientos registrados hoy.</div>`;
          return;
        }
        list.innerHTML = items.map((item) => `
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <div class="text-base font-semibold text-slate-800">${escapeHtml(item.tipo_evento || '')} · ${escapeHtml(item.tipo_origen || '')}</div>
                  <span class="rounded-full px-2.5 py-1 text-xs ${item.resultado === 'permitido' ? 'bg-emerald-100 text-emerald-700' : item.resultado === 'denegado' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'}">${escapeHtml(item.resultado || '')}</span>
                </div>
                <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.persona_nombre || item.nombre_visitante || item.permiso_tipo_movimiento || 'Evento general')}</div>
                <div class="mt-1 text-sm text-slate-500">Área: ${escapeHtml(item.area_nombre || 'Sin área')} · Guardia: ${escapeHtml(item.guardia_nombre || '—')}</div>
                ${item.observaciones ? `<div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.observaciones)}</div>` : ''}
              </div>
              <div class="text-xs text-slate-400">${escapeHtml(item.fecha_hora || '')}</div>
            </div>
          </div>
        `).join('');
      } catch (e) {
        list.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(e.message || 'No se pudo cargar la bitácora.')}</div>`;
      }
    }

    load();
    return {};
  };
})();
