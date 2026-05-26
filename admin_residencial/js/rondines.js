(function () {
  const root = document.getElementById('adminRondinesView');
  if (!root) return;

  const API = '/admin_residencial/php/api/rondines.php';
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_opts, task) => task());
  const syncOverlay = () => window.AdminResidencialDashboard?.syncOverlayState?.();

  const els = {
    alert: document.getElementById('rondinesAlert'),
    tabs: Array.from(document.querySelectorAll('[data-rondines-tab]')),
    routesPanel: document.getElementById('rondinesRutasPanel'),
    pointsPanel: document.getElementById('rondinesPuntosPanel'),
    historyPanel: document.getElementById('rondinesHistorialPanel'),
    routesList: document.getElementById('rondinesRutasList'),
    pointsList: document.getElementById('rondinesPuntosList'),
    historyList: document.getElementById('rondinesHistorialList'),
    btnNewRoute: document.getElementById('btnRondinNewRoute'),
    btnNewPoint: document.getElementById('btnRondinNewPoint'),
    routeModal: document.getElementById('rondinRouteModal'),
    routeTitle: document.getElementById('rondinRouteModalTitle'),
    routeForm: document.getElementById('rondinRouteForm'),
    pointModal: document.getElementById('rondinPointModal'),
    pointTitle: document.getElementById('rondinPointModalTitle'),
    pointForm: document.getElementById('rondinPointForm'),
    btnUseGps: document.getElementById('btnUseCurrentGps'),
    pointGpsHint: document.getElementById('rondinPointGpsHint'),
    assignModal: document.getElementById('rondinAssignModal'),
    assignTitle: document.getElementById('rondinAssignTitle'),
    assignList: document.getElementById('rondinAssignList'),
    btnSaveRoutePoints: document.getElementById('btnSaveRoutePoints'),
    qrModal: document.getElementById('rondinQrModal'),
    qrTitle: document.getElementById('rondinQrTitle'),
    qrImage: document.getElementById('rondinQrImage'),
    qrPayload: document.getElementById('rondinQrPayload'),
    detailModal: document.getElementById('rondinDetailModal'),
    detailTitle: document.getElementById('rondinDetailTitle'),
    detailBody: document.getElementById('rondinDetailBody'),
  };

  const state = {
    tab: 'rutas',
    areas: [],
    routes: [],
    points: [],
    history: [],
    assignRouteId: 0,
  };

  function escapeHtml(value = '') {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...rest,
      headers: { Accept: 'application/json', ...headers },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'Error');
    return json;
  }

  function showAlert(message = '', type = 'success') {
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

  function qrPreview(payload) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(payload)}`;
  }

  function setTab(tab) {
    state.tab = tab;
    els.tabs.forEach((btn) => {
      const active = btn.dataset.rondinesTab === tab;
      btn.classList.toggle('bg-[#2E5D73]', active);
      btn.classList.toggle('text-white', active);
      btn.classList.toggle('bg-slate-50', !active);
    });
    els.routesPanel?.classList.toggle('hidden', tab !== 'rutas');
    els.pointsPanel?.classList.toggle('hidden', tab !== 'puntos');
    els.historyPanel?.classList.toggle('hidden', tab !== 'historial');
  }

  function openModal(modal) {
    modal?.classList.remove('hidden');
    modal?.classList.add('flex');
    document.body.style.overflow = 'hidden';
    syncOverlay();
  }

  function closeModal(modal) {
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    document.body.style.overflow = '';
    syncOverlay();
  }

  function areaName(id) {
    const area = state.areas.find((item) => Number(item.id) === Number(id));
    return area?.nombre || '';
  }

  function renderAreaOptions(selected = '') {
    const select = els.pointForm?.querySelector('[name="area_id"]');
    if (!select) return;
    select.innerHTML = `<option value="">Sin área</option>` + state.areas.map((area) => `
      <option value="${area.id}" ${String(area.id) === String(selected) ? 'selected' : ''}>${escapeHtml(area.nombre)}</option>
    `).join('');
  }

  function renderRoutes() {
    if (!els.routesList) return;
    if (!state.routes.length) {
      els.routesList.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">Aún no hay rutas de rondín.</div>`;
      return;
    }
    els.routesList.innerHTML = state.routes.map((route) => `
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">${Number(route.activo) === 1 ? 'Activa' : 'Inactiva'}</div>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">${escapeHtml(route.nombre)}</h3>
            <p class="mt-1 text-sm text-slate-500">${escapeHtml(route.descripcion || 'Sin descripción')}</p>
          </div>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">${Number(route.puntos_count || 0)} puntos</span>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" data-edit-route="${route.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Editar</button>
          <button type="button" data-assign-route="${route.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Puntos</button>
          <button type="button" data-toggle-route="${route.id}" data-active="${Number(route.activo) === 1 ? '0' : '1'}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">${Number(route.activo) === 1 ? 'Desactivar' : 'Activar'}</button>
        </div>
      </article>
    `).join('');
  }

  function renderPoints() {
    if (!els.pointsList) return;
    if (!state.points.length) {
      els.pointsList.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">Aún no hay puntos de revisión.</div>`;
      return;
    }
    els.pointsList.innerHTML = state.points.map((point) => `
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">${escapeHtml(point.area_nombre || areaName(point.area_id) || 'Sin área')}</div>
            <h3 class="mt-1 text-lg font-semibold text-slate-900">${escapeHtml(point.nombre)}</h3>
            <p class="mt-1 text-sm text-slate-500">${escapeHtml(point.descripcion || 'Sin descripción')}</p>
          </div>
          <span class="rounded-full ${Number(point.activo) === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'} px-3 py-1 text-xs font-semibold">${Number(point.activo) === 1 ? 'Activo' : 'Inactivo'}</span>
        </div>
        <div class="mt-3 grid gap-2 text-xs text-slate-500 sm:grid-cols-3">
          <div class="rounded-xl bg-slate-50 px-3 py-2">Radio: ${escapeHtml(point.radio_metros || 50)}m</div>
          <div class="rounded-xl bg-slate-50 px-3 py-2">Foto: ${Number(point.requiere_foto) === 1 ? 'Sí' : 'No'}</div>
          <div class="rounded-xl bg-slate-50 px-3 py-2">Obs: ${Number(point.requiere_observacion) === 1 ? 'Sí' : 'No'}</div>
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
          <button type="button" data-edit-point="${point.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Editar</button>
          <button type="button" data-qr-point="${point.id}" class="rounded-xl bg-[#2E5D73] px-3 py-2 text-xs font-semibold text-white">Ver QR</button>
          <button type="button" data-regenerate-point="${point.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Regenerar QR</button>
          <button type="button" data-toggle-point="${point.id}" data-active="${Number(point.activo) === 1 ? '0' : '1'}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">${Number(point.activo) === 1 ? 'Desactivar' : 'Activar'}</button>
        </div>
      </article>
    `).join('');
  }

  function renderHistory() {
    if (!els.historyList) return;
    if (!state.history.length) {
      els.historyList.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">Todavía no hay ejecuciones registradas.</div>`;
      return;
    }
    els.historyList.innerHTML = state.history.map((item) => `
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h3 class="text-lg font-semibold text-slate-900">${escapeHtml(item.ruta_nombre || 'Ruta')}</h3>
            <div class="mt-1 text-sm text-slate-500">Operador: ${escapeHtml(item.guardia_nombre || '—')} · ${escapeHtml(item.inicio_at || '')}</div>
            <div class="mt-2 flex flex-wrap gap-2 text-xs">
              <span class="rounded-full bg-slate-100 px-3 py-1 text-slate-600">${escapeHtml(item.estado || '—')}</span>
              <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">${escapeHtml(item.cumplimiento || 0)}% cumplimiento</span>
              <span class="rounded-full bg-amber-50 px-3 py-1 text-amber-700">${escapeHtml(item.anomalias || 0)} anomalías</span>
            </div>
          </div>
          <button type="button" data-detail-exec="${item.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Ver detalle</button>
        </div>
      </article>
    `).join('');
  }

  async function loadMeta() {
    const json = await fetchJSON(`${API}?action=meta`);
    state.areas = json.data?.areas || [];
    renderAreaOptions();
  }

  async function loadRoutes() {
    const json = await fetchJSON(`${API}?action=rutas`);
    state.routes = json.data?.items || [];
    renderRoutes();
  }

  async function loadPoints() {
    const json = await fetchJSON(`${API}?action=puntos`);
    state.points = json.data?.items || [];
    renderPoints();
  }

  async function loadHistory() {
    const json = await fetchJSON(`${API}?action=historial`);
    state.history = json.data?.items || [];
    renderHistory();
  }

  async function reloadAll() {
    await Promise.all([loadRoutes(), loadPoints(), loadHistory()]);
  }

  function openRouteForm(route = null) {
    els.routeForm?.reset();
    if (els.routeTitle) els.routeTitle.textContent = route ? 'Editar ruta' : 'Nueva ruta';
    if (route && els.routeForm) {
      els.routeForm.elements.id.value = route.id || '';
      els.routeForm.elements.nombre.value = route.nombre || '';
      els.routeForm.elements.descripcion.value = route.descripcion || '';
      els.routeForm.elements.activo.checked = Number(route.activo) === 1;
    }
    openModal(els.routeModal);
  }

  function openPointForm(point = null) {
    els.pointForm?.reset();
    renderAreaOptions(point?.area_id || '');
    if (els.pointTitle) els.pointTitle.textContent = point ? 'Editar punto' : 'Nuevo punto';
    if (point && els.pointForm) {
      els.pointForm.elements.id.value = point.id || '';
      els.pointForm.elements.nombre.value = point.nombre || '';
      els.pointForm.elements.area_id.value = point.area_id || '';
      els.pointForm.elements.descripcion.value = point.descripcion || '';
      els.pointForm.elements.latitud.value = point.latitud ?? '';
      els.pointForm.elements.longitud.value = point.longitud ?? '';
      els.pointForm.elements.radio_metros.value = point.radio_metros || 50;
      els.pointForm.elements.requiere_foto.checked = Number(point.requiere_foto) === 1;
      els.pointForm.elements.requiere_observacion.checked = Number(point.requiere_observacion) === 1;
      els.pointForm.elements.activo.checked = Number(point.activo) === 1;
    }
    if (els.pointGpsHint) els.pointGpsHint.classList.add('hidden');
    openModal(els.pointModal);
  }

  function openQr(point) {
    if (!point) return;
    if (els.qrTitle) els.qrTitle.textContent = point.nombre || 'Punto';
    if (els.qrImage) els.qrImage.src = qrPreview(point.qr_payload);
    if (els.qrPayload) els.qrPayload.textContent = point.qr_payload || '';
    openModal(els.qrModal);
  }

  function openAssign(route) {
    if (!route) return;
    state.assignRouteId = Number(route.id);
    if (els.assignTitle) els.assignTitle.textContent = `Puntos de ${route.nombre}`;
    const selected = new Set();
    fetchJSON(`${API}?action=detalle&ejecucion_id=0`).catch(() => null);
    const routePoints = []; // Filled from existing route map below via separate route points endpoint fallback.
    const currentRoutePoints = state.points.filter((point) => Number(point.rutas_count || 0) > 0);
    currentRoutePoints.forEach((point) => {
      if (routePoints.some((row) => Number(row.id) === Number(point.id))) selected.add(Number(point.id));
    });

    if (els.assignList) {
      els.assignList.innerHTML = state.points.map((point, index) => `
        <label class="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700">
          <input type="checkbox" value="${point.id}" ${selected.has(Number(point.id)) ? 'checked' : ''} />
          <span class="flex-1">
            <span class="font-semibold text-slate-900">${escapeHtml(point.nombre)}</span>
            <span class="mt-0.5 block text-xs text-slate-500">${escapeHtml(point.area_nombre || 'Sin área')}</span>
          </span>
          <input type="number" min="1" value="${index + 1}" class="w-20 rounded-xl border border-slate-200 px-2 py-1 text-xs" />
        </label>
      `).join('') || `<div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Crea puntos antes de asignarlos a una ruta.</div>`;
    }
    openModal(els.assignModal);
  }

  async function openAssignWithCurrent(route) {
    if (!route) return;
    state.assignRouteId = Number(route.id);
    if (els.assignTitle) els.assignTitle.textContent = `Puntos de ${route.nombre}`;
    const json = await fetchJSON(`${API}?action=detalle_ruta&ruta_id=${encodeURIComponent(route.id)}`).catch(() => null);
    const selectedIds = new Set((json?.data?.puntos || []).map((point) => Number(point.id)));
    const orderById = {};
    (json?.data?.puntos || []).forEach((point, index) => {
      orderById[Number(point.id)] = Number(point.orden || index + 1);
    });
    if (els.assignList) {
      els.assignList.innerHTML = state.points.map((point, index) => `
        <label class="flex items-center gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700">
          <input type="checkbox" value="${point.id}" ${selectedIds.has(Number(point.id)) ? 'checked' : ''} />
          <span class="flex-1">
            <span class="font-semibold text-slate-900">${escapeHtml(point.nombre)}</span>
            <span class="mt-0.5 block text-xs text-slate-500">${escapeHtml(point.area_nombre || 'Sin área')}</span>
          </span>
          <input type="number" min="1" value="${orderById[Number(point.id)] || index + 1}" class="w-20 rounded-xl border border-slate-200 px-2 py-1 text-xs" />
        </label>
      `).join('') || `<div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Crea puntos antes de asignarlos a una ruta.</div>`;
    }
    openModal(els.assignModal);
  }

  async function openDetail(id) {
    const json = await fetchJSON(`${API}?action=detalle&ejecucion_id=${encodeURIComponent(id)}`);
    const data = json.data || {};
    const exec = data.ejecucion || {};
    const events = data.eventos || [];
    if (els.detailTitle) els.detailTitle.textContent = exec.ruta_nombre || 'Detalle de rondín';
    if (els.detailBody) {
      els.detailBody.innerHTML = `
        <div class="grid gap-3 md:grid-cols-4">
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs text-slate-400">Estado</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(exec.estado || '—')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs text-slate-400">Inicio</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(exec.inicio_at || '—')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs text-slate-400">Fin</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(exec.fin_at || '—')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs text-slate-400">Operador</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(exec.guardia_nombre || '—')}</div></div>
        </div>
        <div class="mt-4 space-y-3">
          ${events.map((event) => `
            <article class="rounded-2xl border ${event.estado === 'anomalia' ? 'border-amber-200 bg-amber-50' : 'border-slate-200 bg-white'} p-4">
              <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div>
                  <div class="text-xs text-slate-400">${escapeHtml(event.escaneado_at || '')}</div>
                  <h3 class="font-semibold text-slate-900">${escapeHtml(event.punto_nombre || 'Punto')}</h3>
                  <p class="mt-1 text-sm text-slate-600">${escapeHtml(event.observacion || 'Sin observación')}</p>
                </div>
                <span class="rounded-full ${event.estado === 'anomalia' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'} px-3 py-1 text-xs font-semibold">${escapeHtml(event.estado || 'correcto')}</span>
              </div>
              <div class="mt-3 text-xs text-slate-500">GPS: ${Number(event.gps_valido) === 1 ? 'válido' : 'advertencia'} · Distancia: ${escapeHtml(event.distancia_punto_metros ?? '—')}m</div>
              ${event.evidencia_url ? `<a href="${escapeHtml(event.evidencia_url)}" target="_blank" class="mt-3 inline-flex rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Ver evidencia</a>` : ''}
            </article>
          `).join('') || `<div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Sin puntos escaneados.</div>`}
        </div>
      `;
    }
    openModal(els.detailModal);
  }

  function formDataWithAction(form, action) {
    const fd = new FormData(form);
    fd.set('action', action);
    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      if (input.name) fd.set(input.name, input.checked ? '1' : '0');
    });
    return fd;
  }

  async function init() {
    setTab('rutas');
    try {
      await loadMeta();
      await reloadAll();
    } catch (error) {
      showAlert(error.message || 'No se pudo cargar Rondines.', 'error');
    }
  }

  els.tabs.forEach((btn) => btn.addEventListener('click', () => setTab(btn.dataset.rondinesTab || 'rutas')));
  els.btnNewRoute?.addEventListener('click', () => openRouteForm());
  els.btnNewPoint?.addEventListener('click', () => openPointForm());

  root.addEventListener('click', async (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (target.closest('[data-close-route-modal]')) return closeModal(els.routeModal);
    if (target.closest('[data-close-point-modal]')) return closeModal(els.pointModal);
    if (target.closest('[data-close-assign-modal]')) return closeModal(els.assignModal);
    if (target.closest('[data-close-qr-modal]')) return closeModal(els.qrModal);
    if (target.closest('[data-close-detail-modal]')) return closeModal(els.detailModal);

    const editRoute = target.closest('[data-edit-route]');
    if (editRoute) return openRouteForm(state.routes.find((route) => Number(route.id) === Number(editRoute.dataset.editRoute)));

    const editPoint = target.closest('[data-edit-point]');
    if (editPoint) return openPointForm(state.points.find((point) => Number(point.id) === Number(editPoint.dataset.editPoint)));

    const qrPoint = target.closest('[data-qr-point]');
    if (qrPoint) return openQr(state.points.find((point) => Number(point.id) === Number(qrPoint.dataset.qrPoint)));

    const assignRoute = target.closest('[data-assign-route]');
    if (assignRoute) {
      const route = state.routes.find((item) => Number(item.id) === Number(assignRoute.dataset.assignRoute));
      return openAssignWithCurrent(route).catch((error) => showAlert(error.message || 'No se pudo cargar la ruta.', 'error'));
    }

    const detail = target.closest('[data-detail-exec]');
    if (detail) return openDetail(detail.dataset.detailExec).catch((error) => showAlert(error.message || 'No se pudo cargar el detalle.', 'error'));

    const toggleRoute = target.closest('[data-toggle-route]');
    if (toggleRoute) {
      const fd = new FormData();
      fd.set('action', 'toggle_ruta');
      fd.set('id', toggleRoute.dataset.toggleRoute || '');
      fd.set('activo', toggleRoute.dataset.active || '0');
      return withPendingAction({ button: toggleRoute, label: 'Actualizando...' }, async () => {
        const json = await fetchJSON(API, { method: 'POST', body: fd });
        showAlert(json.message || 'Ruta actualizada.');
        await loadRoutes();
      }).catch((error) => showAlert(error.message || 'No se pudo actualizar.', 'error'));
    }

    const togglePoint = target.closest('[data-toggle-point]');
    if (togglePoint) {
      const fd = new FormData();
      fd.set('action', 'toggle_punto');
      fd.set('id', togglePoint.dataset.togglePoint || '');
      fd.set('activo', togglePoint.dataset.active || '0');
      return withPendingAction({ button: togglePoint, label: 'Actualizando...' }, async () => {
        const json = await fetchJSON(API, { method: 'POST', body: fd });
        showAlert(json.message || 'Punto actualizado.');
        await loadPoints();
      }).catch((error) => showAlert(error.message || 'No se pudo actualizar.', 'error'));
    }

    const regen = target.closest('[data-regenerate-point]');
    if (regen) {
      const fd = new FormData();
      fd.set('action', 'regenerate_punto_qr');
      fd.set('id', regen.dataset.regeneratePoint || '');
      return withPendingAction({ button: regen, label: 'Regenerando...' }, async () => {
        const json = await fetchJSON(API, { method: 'POST', body: fd });
        showAlert(json.message || 'QR regenerado.');
        await loadPoints();
      }).catch((error) => showAlert(error.message || 'No se pudo regenerar QR.', 'error'));
    }
  });

  els.routeForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.routeForm.querySelector('button[type="submit"]'), scope: els.routeForm, label: 'Guardando...' }, async () => {
      const json = await fetchJSON(API, { method: 'POST', body: formDataWithAction(els.routeForm, 'save_ruta') });
      closeModal(els.routeModal);
      showAlert(json.message || 'Ruta guardada.');
      await loadRoutes();
    }).catch((error) => showAlert(error.message || 'No se pudo guardar la ruta.', 'error'));
  });

  els.pointForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.pointForm.querySelector('button[type="submit"]'), scope: els.pointForm, label: 'Guardando...' }, async () => {
      const json = await fetchJSON(API, { method: 'POST', body: formDataWithAction(els.pointForm, 'save_punto') });
      closeModal(els.pointModal);
      showAlert(json.message || 'Punto guardado.');
      await loadPoints();
    }).catch((error) => showAlert(error.message || 'No se pudo guardar el punto.', 'error'));
  });

  els.btnUseGps?.addEventListener('click', () => {
    if (!navigator.geolocation) {
      if (els.pointGpsHint) {
        els.pointGpsHint.textContent = 'Este navegador no tiene GPS disponible.';
        els.pointGpsHint.classList.remove('hidden');
      }
      return;
    }
    navigator.geolocation.getCurrentPosition((pos) => {
      if (!els.pointForm) return;
      els.pointForm.elements.latitud.value = Number(pos.coords.latitude).toFixed(7);
      els.pointForm.elements.longitud.value = Number(pos.coords.longitude).toFixed(7);
      if (els.pointGpsHint) {
        els.pointGpsHint.textContent = `Ubicación capturada con precisión aproximada de ${Math.round(pos.coords.accuracy || 0)}m.`;
        els.pointGpsHint.classList.remove('hidden');
      }
    }, () => {
      if (els.pointGpsHint) {
        els.pointGpsHint.textContent = 'No se pudo obtener ubicación. Revisa permisos de GPS.';
        els.pointGpsHint.classList.remove('hidden');
      }
    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
  });

  els.btnSaveRoutePoints?.addEventListener('click', () => {
    const rows = Array.from(els.assignList?.querySelectorAll('label') || []);
    const selected = rows
      .map((row) => {
        const checkbox = row.querySelector('input[type="checkbox"]');
        const order = Number(row.querySelector('input[type="number"]')?.value || 0);
        return checkbox?.checked ? { id: Number(checkbox.value), order } : null;
      })
      .filter(Boolean)
      .sort((a, b) => a.order - b.order)
      .map((row) => row.id);
    const fd = new FormData();
    fd.set('action', 'save_ruta_puntos');
    fd.set('ruta_id', String(state.assignRouteId || 0));
    fd.set('puntos', JSON.stringify(selected));
    withPendingAction({ button: els.btnSaveRoutePoints, label: 'Guardando...' }, async () => {
      const json = await fetchJSON(API, { method: 'POST', body: fd });
      closeModal(els.assignModal);
      showAlert(json.message || 'Puntos actualizados.');
      await reloadAll();
    }).catch((error) => showAlert(error.message || 'No se pudieron guardar los puntos.', 'error'));
  });

  window.OSGateLabels?.apply?.(root, window.AdminResidencialDashboard?.getOperationalMode?.() || 'retailops');
  init();
})();
