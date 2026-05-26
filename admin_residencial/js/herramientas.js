(function () {
  const root = document.getElementById('adminHerramientasView');
  if (!root || root.dataset.bound === '1') return;
  root.dataset.bound = '1';

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/herramientas.php';
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_opts, task) => task());

  const els = {
    alert: document.getElementById('herramientasAlert'),
    btnReload: document.getElementById('btnReloadHerramientas'),
    form: document.getElementById('herramientaForm'),
    formTitle: document.getElementById('toolFormTitle'),
    id: document.getElementById('herramientaId'),
    nombre: document.getElementById('herramientaNombre'),
    descripcion: document.getElementById('herramientaDescripcion'),
    btnCancelEdit: document.getElementById('btnCancelHerramientaEdit'),
    catalogo: document.getElementById('herramientasCatalogoList'),
    prestamos: document.getElementById('herramientasPrestamosList'),
    estado: document.getElementById('herrPrestamoEstado'),
    search: document.getElementById('herrPrestamoSearch'),
    btnFilter: document.getElementById('btnFiltrarPrestamosHerr'),
    prev: document.getElementById('herrPrestamosPrev'),
    next: document.getElementById('herrPrestamosNext'),
    pageInfo: document.getElementById('herrPrestamosPage'),
  };

  const state = {
    catalogo: [],
    prestamos: [],
    page: 1,
    totalPages: 1,
  };

  function escapeHtml(value = '') {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function showAlert(message, type = 'success') {
    if (window.AppToast?.show) {
      window.AppToast.show({ type, message });
      return;
    }
    if (!els.alert) return;
    els.alert.textContent = message;
    els.alert.className = 'rounded-2xl px-4 py-3 text-sm ' + (type === 'error'
      ? 'border border-rose-200 bg-rose-50 text-rose-700'
      : 'border border-emerald-200 bg-emerald-50 text-emerald-700');
    els.alert.classList.remove('hidden');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options,
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || json.ok === false) {
      throw new Error(json?.error || 'No se pudo completar la solicitud.');
    }
    return json;
  }

  function statusBadge(estado) {
    if (estado === 'prestado') return 'border-amber-200 bg-amber-50 text-amber-700';
    if (estado === 'devuelto') return 'border-emerald-200 bg-emerald-50 text-emerald-700';
    return 'border-slate-200 bg-slate-100 text-slate-600';
  }

  function currentMode() {
    const dashboardMode = String(window.AdminResidencialDashboard?.getOperationalMode?.() || '').trim();
    const dashboardPreset = String(window.AdminResidencialDashboard?.getServiceProfile?.()?.preset_servicio || '').trim();
    return dashboardMode && dashboardMode !== 'residencial' ? dashboardMode : dashboardPreset || dashboardMode || 'residencial';
  }

  function label(term, fallback = '') {
    return window.OSGateLabels?.label?.(term, currentMode()) || fallback || term;
  }

  function resetForm() {
    els.form?.reset();
    if (els.id) els.id.value = '';
    if (els.formTitle) els.formTitle.textContent = 'Nueva herramienta';
    els.btnCancelEdit?.classList.add('hidden');
  }

  function renderCatalogo() {
    if (!els.catalogo) return;
    if (!state.catalogo.length) {
      els.catalogo.innerHTML = `
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500 md:col-span-2">
          No hay herramientas registradas todavía.
        </div>
      `;
      return;
    }

    els.catalogo.innerHTML = state.catalogo.map((tool) => {
      const active = Number(tool.activo ?? 0) === 1;
      return `
        <article class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="font-semibold text-slate-800">${escapeHtml(tool.nombre || 'Herramienta')}</div>
              <div class="mt-1 line-clamp-2 text-sm text-slate-500">${escapeHtml(tool.descripcion || 'Sin descripción')}</div>
            </div>
            <span class="shrink-0 rounded-full border px-2.5 py-1 text-[11px] ${active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'}">
              ${active ? 'Activa' : 'Inactiva'}
            </span>
          </div>
          <div class="mt-4 flex flex-wrap justify-end gap-2">
            <button type="button" data-tool-edit="${escapeHtml(tool.id)}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Editar</button>
            <button type="button" data-tool-toggle="${escapeHtml(tool.id)}" data-active="${active ? '0' : '1'}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">${active ? 'Desactivar' : 'Activar'}</button>
            <button type="button" data-tool-delete="${escapeHtml(tool.id)}" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 hover:bg-rose-100">Eliminar</button>
          </div>
        </article>
      `;
    }).join('');
  }

  function renderPrestamos() {
    if (!els.prestamos) return;
    if (!state.prestamos.length) {
      els.prestamos.innerHTML = `
        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
          No hay préstamos que coincidan con el filtro.
        </div>
      `;
    } else {
      els.prestamos.innerHTML = state.prestamos.map((loan) => {
        const contextLabel = loan.area_nombre ? label('area', 'Área') : label('unit', 'Unidad');
        const contextValue = loan.area_nombre || loan.unidad_clave || '—';
        const responsibleValue = loan.persona_recurrente_nombre || loan.responsable_nombre || loan.residente_nombre || '—';
        return `
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <div class="font-semibold text-slate-800">${escapeHtml(loan.herramienta_nombre || 'Herramienta')}</div>
                <span class="rounded-full border px-2.5 py-1 text-[11px] ${statusBadge(loan.estado)}">${escapeHtml(loan.estado || '—')}</span>
              </div>
              <div class="mt-2 grid gap-1 text-sm text-slate-500 sm:grid-cols-2">
                <div>${escapeHtml(contextLabel)}: <span class="font-medium text-slate-700">${escapeHtml(contextValue)}</span></div>
                <div>${escapeHtml(label('responsible', 'Responsable'))}: <span class="font-medium text-slate-700">${escapeHtml(responsibleValue)}</span></div>
                <div>${escapeHtml(label('guard', 'Operador'))}: <span class="font-medium text-slate-700">${escapeHtml(loan.guardia_nombre || '—')}</span></div>
                <div>Prestado: <span class="font-medium text-slate-700">${escapeHtml(loan.prestado_at || '—')}</span></div>
              </div>
              ${loan.notas ? `<div class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(loan.notas)}</div>` : ''}
            </div>
            ${loan.estado === 'prestado' ? `
              <button type="button" data-loan-return="${escapeHtml(loan.id)}" class="rounded-xl bg-[#2E5D73] px-4 py-2 text-sm font-semibold text-white hover:opacity-95">
                Marcar devuelto
              </button>
            ` : ''}
          </div>
        </article>
      `;
      }).join('');
    }

    if (els.pageInfo) {
      els.pageInfo.textContent = `Página ${state.page} de ${Math.max(1, state.totalPages)}`;
    }
    if (els.prev) els.prev.disabled = state.page <= 1;
    if (els.next) els.next.disabled = state.page >= state.totalPages;
  }

  async function loadCatalogo() {
    const json = await fetchJSON(`${API}?action=catalogo`);
    state.catalogo = json.data?.items || [];
    renderCatalogo();
  }

  async function loadPrestamos(reset = false) {
    if (reset) state.page = 1;
    const params = new URLSearchParams({
      action: 'prestamos',
      page: String(state.page),
      estado: els.estado?.value || '',
      q: els.search?.value || '',
    });
    const json = await fetchJSON(`${API}?${params.toString()}`);
    state.prestamos = json.data?.items || [];
    const pagination = json.data?.pagination || {};
    state.page = Number(pagination.page || state.page || 1);
    state.totalPages = Math.max(1, Number(pagination.total_pages || 1));
    renderPrestamos();
  }

  async function refreshAll() {
    await Promise.all([loadCatalogo(), loadPrestamos()]);
  }

  els.form?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.form.querySelector('button[type="submit"]'), label: 'Guardando...' }, async () => {
      const fd = new FormData(els.form);
      fd.set('action', els.id?.value ? 'update_tool' : 'create_tool');
      const json = await fetchJSON(API, { method: 'POST', body: fd });
      resetForm();
      await loadCatalogo();
      showAlert(json.message || 'Herramienta guardada.');
    }).catch((err) => showAlert(err.message || 'No se pudo guardar la herramienta.', 'error'));
  });

  els.btnCancelEdit?.addEventListener('click', resetForm);
  els.btnReload?.addEventListener('click', () => {
    refreshAll().then(() => showAlert('Herramientas actualizadas.')).catch((err) => showAlert(err.message || 'No se pudo actualizar.', 'error'));
  });
  els.btnFilter?.addEventListener('click', () => {
    loadPrestamos(true).catch((err) => showAlert(err.message || 'No se pudieron filtrar los préstamos.', 'error'));
  });
  els.estado?.addEventListener('change', () => loadPrestamos(true).catch((err) => showAlert(err.message || 'No se pudieron filtrar los préstamos.', 'error')));
  els.search?.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      loadPrestamos(true).catch((err) => showAlert(err.message || 'No se pudieron filtrar los préstamos.', 'error'));
    }
  });
  els.prev?.addEventListener('click', () => {
    if (state.page <= 1) return;
    state.page -= 1;
    loadPrestamos().catch((err) => showAlert(err.message || 'No se pudo cambiar de página.', 'error'));
  });
  els.next?.addEventListener('click', () => {
    if (state.page >= state.totalPages) return;
    state.page += 1;
    loadPrestamos().catch((err) => showAlert(err.message || 'No se pudo cambiar de página.', 'error'));
  });

  els.catalogo?.addEventListener('click', (event) => {
    const edit = event.target.closest('[data-tool-edit]');
    const toggle = event.target.closest('[data-tool-toggle]');
    const del = event.target.closest('[data-tool-delete]');

    if (edit) {
      const id = Number(edit.getAttribute('data-tool-edit') || 0);
      const tool = state.catalogo.find((item) => Number(item.id) === id);
      if (!tool) return;
      if (els.id) els.id.value = String(tool.id);
      if (els.nombre) els.nombre.value = tool.nombre || '';
      if (els.descripcion) els.descripcion.value = tool.descripcion || '';
      if (els.formTitle) els.formTitle.textContent = 'Editar herramienta';
      els.btnCancelEdit?.classList.remove('hidden');
      els.nombre?.focus();
      return;
    }

    if (toggle) {
      const fd = new FormData();
      fd.set('action', 'toggle_tool');
      fd.set('tool_id', toggle.getAttribute('data-tool-toggle') || '');
      fd.set('activo', toggle.getAttribute('data-active') || '0');
      fetchJSON(API, { method: 'POST', body: fd })
        .then((json) => loadCatalogo().then(() => showAlert(json.message || 'Herramienta actualizada.')))
        .catch((err) => showAlert(err.message || 'No se pudo actualizar la herramienta.', 'error'));
      return;
    }

    if (del) {
      if (!window.confirm('¿Eliminar o desactivar esta herramienta?')) return;
      const fd = new FormData();
      fd.set('action', 'delete_tool');
      fd.set('tool_id', del.getAttribute('data-tool-delete') || '');
      fetchJSON(API, { method: 'POST', body: fd })
        .then((json) => loadCatalogo().then(() => showAlert(json.message || 'Herramienta eliminada.')))
        .catch((err) => showAlert(err.message || 'No se pudo eliminar la herramienta.', 'error'));
    }
  });

  els.prestamos?.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-loan-return]');
    if (!btn) return;
    if (!window.confirm('¿Marcar este préstamo como devuelto?')) return;
    const fd = new FormData();
    fd.set('action', 'marcar_devuelto');
    fd.set('prestamo_id', btn.getAttribute('data-loan-return') || '');
    fetchJSON(API, { method: 'POST', body: fd })
      .then((json) => refreshAll().then(() => showAlert(json.message || 'Préstamo actualizado.')))
      .catch((err) => showAlert(err.message || 'No se pudo marcar la devolución.', 'error'));
  });

  const dashboardMode = String(window.AdminResidencialDashboard?.getOperationalMode?.() || '').trim();
  const dashboardPreset = String(window.AdminResidencialDashboard?.getServiceProfile?.()?.preset_servicio || '').trim();
  window.OSGateLabels?.apply?.(root, dashboardMode && dashboardMode !== 'residencial' ? dashboardMode : dashboardPreset || dashboardMode);
  refreshAll().catch((err) => showAlert(err.message || 'No se pudo cargar herramientas.', 'error'));
})();
