(function () {
  const root = document.getElementById('reportesOperativosView');
  if (!root) return;

  function basePath() {
    try {
      const p = window.location.pathname || '';
      const idx = p.indexOf('/admin_residencial/');
      if (idx !== -1) return p.slice(0, idx) + '/admin_residencial/';
    } catch (_) {}
    return '/admin_residencial/';
  }

  const API = basePath() + 'php/api/reportes_operativos.php';
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_opts, task) => task());

  const state = {
    mode: window.AdminResidencialDashboard?.getOperationalMode?.() || 'retailops',
    filtersLoaded: false,
    items: [],
  };

  const els = {
    form: document.getElementById('reportesOperativosFilters'),
    desde: document.getElementById('reportesDesde'),
    hasta: document.getElementById('reportesHasta'),
    area: document.getElementById('reportesArea'),
    operador: document.getElementById('reportesOperador'),
    tipo: document.getElementById('reportesTipo'),
    q: document.getElementById('reportesSearch'),
    clear: document.getElementById('reportesClear'),
    exportCsv: document.getElementById('reportesExportCsv'),
    alert: document.getElementById('reportesAlert'),
    summary: document.getElementById('reportesSummary'),
    tableBody: document.getElementById('reportesTableBody'),
    cards: document.getElementById('reportesCards'),
    empty: document.getElementById('reportesEmpty'),
    count: document.getElementById('reportesCount'),
    rangeHint: document.getElementById('reportesRangeHint'),
  };

  function escapeHtml(value = '') {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function label(term, fallback = term) {
    return window.OSGateLabels?.label?.(term, state.mode) || fallback;
  }

  function today() {
    return new Date().toISOString().slice(0, 10);
  }

  function showAlert(message = '', type = 'info') {
    if (!els.alert) return;
    if (!message) {
      els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
      els.alert.textContent = '';
      return;
    }
    els.alert.className = 'rounded-2xl px-4 py-3 text-sm ' + (
      type === 'error'
        ? 'border border-rose-200 bg-rose-50 text-rose-700'
        : 'border border-emerald-200 bg-emerald-50 text-emerald-700'
    );
    els.alert.textContent = message;
    els.alert.classList.remove('hidden');
  }

  async function fetchJSON(url) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json' },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) {
      throw new Error(json.error || 'No se pudo cargar la información.');
    }
    return json;
  }

  function currentParams(extra = {}) {
    const params = new URLSearchParams({
      action: 'list',
      desde: els.desde?.value || today(),
      hasta: els.hasta?.value || today(),
      limit: '100',
      ...extra,
    });
    if (els.area?.value) params.set('area_id', els.area.value);
    if (els.operador?.value) params.set('operador_id', els.operador.value);
    if (els.tipo?.value) params.set('tipo_evento', els.tipo.value);
    if (els.q?.value?.trim()) params.set('q', els.q.value.trim());
    return params;
  }

  function fillSelect(select, items, config) {
    if (!select) return;
    const current = select.value;
    const firstLabel = config.firstLabel || 'Todos';
    select.innerHTML = `<option value="">${escapeHtml(firstLabel)}</option>` + items.map((item) => {
      const value = config.value(item);
      const text = config.text(item);
      return `<option value="${escapeHtml(value)}">${escapeHtml(text)}</option>`;
    }).join('');
    if (current && Array.from(select.options).some((option) => option.value === current)) {
      select.value = current;
    }
  }

  async function loadFilters() {
    const json = await fetchJSON(`${API}?action=filters`);
    const data = json.data || {};
    const filters = data.filters || {};
    state.mode = data.context?.modo_operacion || state.mode;

    fillSelect(els.area, filters.areas || [], {
      firstLabel: `Todas las ${label('areas', 'áreas').toLowerCase()}`,
      value: (item) => item.id,
      text: (item) => item.codigo ? `${item.nombre} · ${item.codigo}` : item.nombre,
    });
    fillSelect(els.operador, filters.operadores || [], {
      firstLabel: 'Todos',
      value: (item) => item.id,
      text: (item) => item.nombre || item.email || `Operador ${item.id}`,
    });
    fillSelect(els.tipo, filters.tipos_evento || [], {
      firstLabel: 'Todos',
      value: (item) => item.value,
      text: (item) => item.label || item.value,
    });

    window.OSGateLabels?.apply?.(root, state.mode);
    state.filtersLoaded = true;
  }

  function resultClass(result = '') {
    const value = String(result || '').toLowerCase();
    if (value.includes('denegado') || value.includes('rechazado')) return 'bg-rose-50 text-rose-700 border-rose-100';
    if (value.includes('permitido') || value.includes('autorizado')) return 'bg-emerald-50 text-emerald-700 border-emerald-100';
    return 'bg-slate-100 text-slate-600 border-slate-200';
  }

  function renderSummary(summary = {}) {
    const cards = [
      ['Eventos', summary.total || 0, 'text-slate-900'],
      ['Permitidos', summary.permitidos || 0, 'text-emerald-600'],
      ['Denegados', summary.denegados || 0, 'text-rose-600'],
      ['Otros', summary.otros || 0, 'text-[#2E5D73]'],
    ];
    if (!els.summary) return;
    els.summary.innerHTML = cards.map(([title, value, cls]) => `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">${escapeHtml(title)}</div>
        <div class="mt-2 text-3xl font-bold ${cls}">${escapeHtml(value)}</div>
      </div>
    `).join('');
  }

  function renderRows(items = []) {
    state.items = items;
    const hasItems = items.length > 0;
    els.empty?.classList.toggle('hidden', hasItems);
    if (els.count) {
      els.count.textContent = `${items.length} ${items.length === 1 ? 'registro' : 'registros'}`;
    }
    if (els.rangeHint) {
      els.rangeHint.textContent = `${els.desde?.value || ''} a ${els.hasta?.value || ''}`;
    }

    if (!hasItems) {
      if (els.tableBody) els.tableBody.innerHTML = '';
      if (els.cards) els.cards.innerHTML = '';
      return;
    }

    if (els.tableBody) {
      els.tableBody.innerHTML = items.map((item) => `
        <tr class="align-top hover:bg-slate-50/70">
          <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">${escapeHtml(item.fecha_hora || '')}</td>
          <td class="px-4 py-3">
            <div class="font-semibold text-slate-800">${escapeHtml(item.tipo_evento || 'Evento')}</div>
            <div class="text-xs text-slate-400">${escapeHtml(item.tipo_origen || '')}</div>
          </td>
          <td class="px-4 py-3 text-slate-600">${escapeHtml(item.area_nombre || 'Sin área')}</td>
          <td class="px-4 py-3 text-slate-600">${escapeHtml(item.persona_responsable || 'Evento general')}</td>
          <td class="px-4 py-3 text-slate-600">${escapeHtml(item.operador_nombre || '—')}</td>
          <td class="px-4 py-3">
            <div class="max-w-[22rem] text-slate-700">${escapeHtml(item.descripcion || 'Sin observaciones')}</div>
            ${item.herramienta_nombre ? `<div class="mt-1 text-xs text-slate-400">${escapeHtml(item.herramienta_nombre)}</div>` : ''}
          </td>
          <td class="px-4 py-3">
            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${resultClass(item.estado || item.resultado)}">${escapeHtml(item.estado || item.resultado || '—')}</span>
          </td>
        </tr>
      `).join('');
    }

    if (els.cards) {
      els.cards.innerHTML = items.map((item) => `
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-xs text-slate-400">${escapeHtml(item.fecha_hora || '')}</div>
              <h3 class="mt-1 text-base font-semibold text-slate-900">${escapeHtml(item.tipo_evento || 'Evento')}</h3>
            </div>
            <span class="rounded-full border px-2.5 py-1 text-xs font-semibold ${resultClass(item.estado || item.resultado)}">${escapeHtml(item.estado || item.resultado || '—')}</span>
          </div>
          <div class="mt-3 grid gap-2 text-sm text-slate-600">
            <div><span class="text-slate-400">${escapeHtml(label('area', 'Área'))}:</span> ${escapeHtml(item.area_nombre || 'Sin área')}</div>
            <div><span class="text-slate-400">Responsable:</span> ${escapeHtml(item.persona_responsable || 'Evento general')}</div>
            <div><span class="text-slate-400">${escapeHtml(label('guard', 'Operador'))}:</span> ${escapeHtml(item.operador_nombre || '—')}</div>
            <div class="rounded-xl bg-slate-50 px-3 py-2">${escapeHtml(item.descripcion || 'Sin observaciones')}</div>
          </div>
        </article>
      `).join('');
    }
  }

  async function loadList() {
    showAlert('');
    const json = await fetchJSON(`${API}?${currentParams().toString()}`);
    const data = json.data || {};
    state.mode = data.context?.modo_operacion || state.mode;
    renderSummary(data.summary || {});
    renderRows(data.items || []);
    window.OSGateLabels?.apply?.(root, state.mode);
  }

  function resetFilters() {
    const current = today();
    if (els.desde) els.desde.value = current;
    if (els.hasta) els.hasta.value = current;
    if (els.area) els.area.value = '';
    if (els.operador) els.operador.value = '';
    if (els.tipo) els.tipo.value = '';
    if (els.q) els.q.value = '';
  }

  function exportCsv() {
    const params = currentParams({ action: 'export_csv', limit: '1000' });
    const a = document.createElement('a');
    a.href = `${API}?${params.toString()}`;
    a.download = `auditoria-operativa-${els.desde?.value || today()}-${els.hasta?.value || today()}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
  }

  async function init() {
    resetFilters();
    try {
      await loadFilters();
      await loadList();
    } catch (error) {
      showAlert(error.message || 'No se pudo cargar auditoría operativa.', 'error');
      renderSummary({});
      renderRows([]);
    }
  }

  els.form?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.form.querySelector('button[type="submit"]'), scope: els.form, label: 'Cargando...' }, loadList)
      .catch((error) => showAlert(error.message || 'No se pudo cargar auditoría operativa.', 'error'));
  });

  els.clear?.addEventListener('click', () => {
    resetFilters();
    loadList().catch((error) => showAlert(error.message || 'No se pudo limpiar el reporte.', 'error'));
  });

  els.exportCsv?.addEventListener('click', exportCsv);

  init();
})();
