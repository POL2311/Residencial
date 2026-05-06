(function () {
  const root = document.getElementById('bitacoraOperativaView');
  if (!root) return;

  const API = '/admin_residencial/php/api/bitacora_operativa.php';
  const els = {
    form: document.getElementById('bitacoraFilters'),
    desde: document.getElementById('bitacoraDesde'),
    hasta: document.getElementById('bitacoraHasta'),
    tipo: document.getElementById('bitacoraTipo'),
    alert: document.getElementById('bitacoraAlert'),
    summary: document.getElementById('bitacoraSummary'),
    list: document.getElementById('bitacoraList'),
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

  function renderSummary(summary) {
    els.summary.innerHTML = `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Movimientos</div><div class="mt-2 text-3xl font-bold text-slate-800">${summary.total || 0}</div></div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Permitidos</div><div class="mt-2 text-3xl font-bold text-emerald-600">${summary.permitidos || 0}</div></div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Denegados</div><div class="mt-2 text-3xl font-bold text-rose-600">${summary.denegados || 0}</div></div>
    `;
  }

  function renderList(items) {
    if (!items.length) {
      els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay movimientos en el rango seleccionado.</div>`;
      return;
    }

    els.list.innerHTML = items.map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-base font-semibold text-slate-800">${escapeHtml(item.tipo_evento)} · ${escapeHtml(item.tipo_origen)}</h3>
              <span class="rounded-full px-2.5 py-1 text-xs ${item.resultado === 'permitido' ? 'bg-emerald-100 text-emerald-700' : item.resultado === 'denegado' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'}">${escapeHtml(item.resultado)}</span>
            </div>
            <div class="mt-1 text-sm text-slate-600">
              Guardia: ${escapeHtml(item.guardia_nombre || '—')} · Área: ${escapeHtml(item.area_nombre || '—')}
            </div>
            <div class="mt-1 text-sm text-slate-500">
              ${escapeHtml(item.persona_nombre || item.nombre_visitante || item.permiso_tipo_movimiento || 'Evento general')}
            </div>
            <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.observaciones || 'Sin observaciones')}</div>
            ${Array.isArray(item.evidencias) && item.evidencias.length ? `
              <div class="mt-3 flex flex-wrap gap-2">
                ${item.evidencias.slice(0, 3).map((url) => `
                  <a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="block h-16 w-16 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
                    <img src="${escapeHtml(url)}" alt="Evidencia" class="h-full w-full object-cover" loading="lazy" />
                  </a>
                `).join('')}
              </div>
            ` : ''}
          </div>
          <div class="text-xs text-slate-400">${escapeHtml(item.fecha_hora)}</div>
        </div>
      </div>
    `).join('');
  }

  async function load() {
    try {
      const query = new URLSearchParams({
        desde: els.desde.value,
        hasta: els.hasta.value,
      });
      if (els.tipo.value) query.set('tipo_origen', els.tipo.value);

      const json = await fetchJSON(`${API}?${query.toString()}`);
      renderSummary(json.data?.summary || {});
      renderList(json.data?.items || []);
    } catch (e) {
      showAlert(e.message || 'No se pudo cargar la bitácora.', 'error');
    }
  }

  const today = new Date().toISOString().slice(0, 10);
  els.desde.value = today;
  els.hasta.value = today;
  els.form?.addEventListener('submit', (ev) => {
    ev.preventDefault();
    load();
  });

  load();
})();
