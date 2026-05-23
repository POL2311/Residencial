(function () {
  const root = document.getElementById('solicitudesPendientesView');
  if (!root) return;

  const API = '/admin_residencial/php/api/permisos_materiales.php?estado=pendiente';
  const ACTION_API = '/admin_residencial/php/api/permisos_materiales.php';
  const els = {
    list: document.getElementById('solicitudesList'),
    alert: document.getElementById('solicitudesAlert'),
  };

  function escapeHtml(v = '') {
    return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...rest, headers: { Accept: 'application/json', ...headers } });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'Error');
    return json;
  }

  function showAlert(msg = '', type = 'info') {
    if (!els.alert) return;
    if (!msg) {
      els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
      els.alert.textContent = '';
      return;
    }
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');
  }

  async function load() {
    try {
      const json = await fetchJSON(API);
      const items = json.data?.items || [];
      if (!items.length) {
        els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay solicitudes pendientes por aprobar.</div>`;
        return;
      }

      els.list.innerHTML = items.map((item) => `
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.tipo_movimiento === 'salida' ? 'Salida pendiente' : 'Entrada pendiente')}</h3>
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs text-amber-700">Pendiente</span>
              </div>
              <div class="mt-1 text-sm text-slate-600">Responsable: ${escapeHtml(item.responsable_nombre || 'Sin responsable')} · Área: ${escapeHtml(item.area_nombre || 'Sin área')}</div>
              <div class="mt-3 space-y-2">
                ${(item.items || []).map((row) => `<div class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">${escapeHtml(row.material_nombre)} · ${escapeHtml(row.cantidad_texto)}</div>`).join('')}
              </div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2">
              <button type="button" class="js-approve rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700" data-id="${item.id}">Aprobar</button>
              <button type="button" class="js-cancel rounded-2xl border px-3 py-2 text-sm hover:bg-slate-50" data-id="${item.id}">Cancelar</button>
            </div>
          </div>
        </div>
      `).join('');

      els.list.querySelectorAll('.js-approve').forEach((btn) => btn.addEventListener('click', () => update('approve', Number(btn.dataset.id))));
      els.list.querySelectorAll('.js-cancel').forEach((btn) => btn.addEventListener('click', () => update('cancel', Number(btn.dataset.id))));
    } catch (e) {
      showAlert(e.message || 'No se pudieron cargar las solicitudes.', 'error');
    }
  }

  async function update(action, id) {
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', String(id));
    try {
      await fetchJSON(ACTION_API, { method: 'POST', body: fd });
      showAlert(action === 'approve' ? 'Solicitud aprobada.' : 'Solicitud cancelada.', 'success');
      await load();
    } catch (e) {
      showAlert(e.message || 'No se pudo actualizar la solicitud.', 'error');
    }
  }

  load();
})();
