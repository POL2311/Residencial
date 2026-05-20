(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.amenidades = function ({ API, fetchJSON, escapeHtml }) {
    const els = {
      alert: document.getElementById('guardAmenidadesAlert'),
      fecha: document.getElementById('guardAmenidadesFecha'),
      estado: document.getElementById('guardAmenidadesEstado'),
      btnFiltrar: document.getElementById('btnFiltrarGuardAmenidades'),
      list: document.getElementById('guardAmenidadesList'),
    };

    let destroyed = false;

    function todayInput() {
      const d = new Date();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${d.getFullYear()}-${m}-${day}`;
    }

    function showAlert(message = '', type = 'error') {
      if (!els.alert || destroyed) return;
      if (!message) {
        els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
        els.alert.textContent = '';
        return;
      }
      els.alert.className = `rounded-2xl border px-4 py-3 text-sm ${
        type === 'success'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
          : 'border-rose-200 bg-rose-50 text-rose-700'
      }`;
      els.alert.textContent = message;
    }

    function statusBadge(status) {
      const map = {
        pendiente: 'bg-amber-50 text-amber-700 border-amber-200',
        aprobada: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        rechazada: 'bg-rose-50 text-rose-700 border-rose-200',
        cancelada: 'bg-slate-100 text-slate-600 border-slate-200',
        finalizada: 'bg-slate-100 text-slate-600 border-slate-200',
      };
      return map[status] || map.pendiente;
    }

    function render(items) {
      if (!els.list || destroyed) return;
      if (!items.length) {
        els.list.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay reservas con los filtros actuales.</div>';
        return;
      }

      els.list.innerHTML = items.map((item) => `
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.amenidad_nombre || 'Amenidad')}</h3>
                <span class="rounded-full border px-2.5 py-1 text-xs ${statusBadge(item.estado)}">${escapeHtml(item.estado)}</span>
              </div>
              <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.fecha)} · ${escapeHtml(item.hora_inicio)}-${escapeHtml(item.hora_fin)}</div>
              <div class="mt-1 text-sm text-slate-500">Unidad ${escapeHtml(item.unidad_clave || '—')} · ${escapeHtml(item.residente_nombre || '—')}</div>
              ${item.notas_residente ? `<div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.notas_residente)}</div>` : ''}
              ${item.notas_admin ? `<div class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-500">Admin: ${escapeHtml(item.notas_admin)}</div>` : ''}
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-500">
              Solo consulta
            </div>
          </div>
        </article>
      `).join('');
    }

    async function load() {
      if (els.list) els.list.innerHTML = '<div class="rounded-2xl bg-white p-4 text-sm text-slate-500">Cargando reservas…</div>';
      const qs = new URLSearchParams();
      if (els.fecha?.value) qs.set('fecha', els.fecha.value);
      if (els.estado?.value) qs.set('estado', els.estado.value);
      const json = await fetchJSON(`${API}amenidades.php?${qs.toString()}`);
      render(Array.isArray(json.data?.items) ? json.data.items : []);
    }

    if (els.fecha && !els.fecha.value) els.fecha.value = todayInput();
    els.btnFiltrar?.addEventListener('click', () => load().catch((e) => showAlert(e.message || 'No se pudieron cargar reservas.')));
    load().catch((e) => showAlert(e.message || 'No se pudieron cargar reservas.'));

    return {
      unmount() {
        destroyed = true;
      },
    };
  };
})();
