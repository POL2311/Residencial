(function () {
  const root = document.getElementById('proveedoresView');
  if (!root) return;

  function basePath() {
    try {
      const p = window.location.pathname || '';
      const idx = p.indexOf('/admin_residencial/');
      if (idx !== -1) return p.slice(0, idx) + '/admin_residencial/';
    } catch (_) {}
    return '/admin_residencial/';
  }

  const API = basePath() + 'php/api/proveedores.php';
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_opts, task) => task());
  const syncOverlay = () => window.AdminResidencialDashboard?.syncOverlayState?.();

  const state = {
    mode: window.AdminResidencialDashboard?.getOperationalMode?.() || 'retailops',
    proveedores: [],
    personal: [],
    selectedId: 0,
    detail: null,
  };

  const els = {
    alert: document.getElementById('proveedoresAlert'),
    stats: document.getElementById('proveedoresStats'),
    search: document.getElementById('proveedoresSearch'),
    statusFilter: document.getElementById('proveedoresStatusFilter'),
    clearFilters: document.getElementById('proveedoresClearFilters'),
    refresh: document.getElementById('proveedoresRefresh'),
    newProvider: document.getElementById('proveedoresNew'),
    list: document.getElementById('proveedoresList'),
    empty: document.getElementById('proveedoresEmpty'),
    count: document.getElementById('proveedoresCount'),
    detail: document.getElementById('proveedorDetail'),
    detailEmpty: document.getElementById('proveedorDetailEmpty'),
    detailBody: document.getElementById('proveedorDetailBody'),
    providerModal: document.getElementById('proveedorModal'),
    providerTitle: document.getElementById('proveedorModalTitle'),
    providerForm: document.getElementById('proveedorForm'),
    personModal: document.getElementById('proveedorPersonModal'),
    personForm: document.getElementById('proveedorPersonForm'),
    docModal: document.getElementById('proveedorDocModal'),
    docTitle: document.getElementById('proveedorDocModalTitle'),
    docForm: document.getElementById('proveedorDocForm'),
    complianceModal: document.getElementById('proveedorComplianceModal'),
    complianceTitle: document.getElementById('proveedorComplianceTitle'),
    complianceForm: document.getElementById('proveedorComplianceForm'),
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

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...rest,
      headers: { Accept: 'application/json', ...headers },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'No se pudo procesar la solicitud.');
    return json;
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

  function statusText(status = '') {
    const map = {
      activo: label('active', 'Activo'),
      pendiente: label('pending', 'Pendiente'),
      bloqueado: label('blocked', 'Bloqueado'),
      autorizado: label('authorized', 'Autorizado'),
      documento_vencido: label('expired_document', 'Documento vencido'),
      fuera_de_horario: label('out_of_hours', 'Fuera de horario'),
      vigente: 'Vigente',
      vencido: 'Vencido',
    };
    return map[String(status || '').toLowerCase()] || status || '—';
  }

  function statusClass(status = '') {
    const value = String(status || '').toLowerCase();
    if (value === 'bloqueado' || value === 'vencido') return 'border-rose-100 bg-rose-50 text-rose-700';
    if (value === 'pendiente' || value === 'documento_vencido' || value === 'fuera_de_horario') return 'border-amber-100 bg-amber-50 text-amber-700';
    if (value === 'activo' || value === 'vigente' || value === 'autorizado') return 'border-emerald-100 bg-emerald-50 text-emerald-700';
    return 'border-slate-200 bg-slate-100 text-slate-600';
  }

  function formDataWithAction(form, action) {
    const fd = new FormData(form);
    fd.set('action', action);
    form.querySelectorAll('input[type="checkbox"]').forEach((input) => {
      if (input.name) fd.set(input.name, input.checked ? '1' : '0');
    });
    return fd;
  }

  function providerById(id) {
    return state.proveedores.find((item) => Number(item.id) === Number(id)) || null;
  }

  function currentProvider() {
    return providerById(state.selectedId) || state.detail?.proveedor || null;
  }

  function renderStats() {
    if (!els.stats) return;
    const total = state.proveedores.length;
    const activos = state.proveedores.filter((p) => p.estatus_cumplimiento === 'autorizado' && Number(p.activo) === 1).length;
    const pendientes = state.proveedores.filter((p) => p.estatus_cumplimiento === 'pendiente').length;
    const bloqueados = state.proveedores.filter((p) => p.estatus_cumplimiento === 'bloqueado').length;
    const cards = [
      [label('providers', 'Proveedores'), total, 'text-slate-900'],
      [label('authorized', 'Autorizado'), activos, 'text-emerald-600'],
      [label('pending', 'Pendiente'), pendientes, 'text-amber-600'],
      [label('blocked', 'Bloqueado'), bloqueados, 'text-rose-600'],
    ];
    els.stats.innerHTML = cards.map(([title, value, cls]) => `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">${escapeHtml(title)}</div>
        <div class="mt-2 text-3xl font-bold ${cls}">${escapeHtml(value)}</div>
      </div>
    `).join('');
  }

  function renderList() {
    renderStats();
    const items = state.proveedores;
    if (els.count) els.count.textContent = `${items.length} ${items.length === 1 ? 'registro' : 'registros'}`;
    els.empty?.classList.toggle('hidden', items.length > 0);
    if (!els.list) return;
    els.list.innerHTML = items.map((item) => {
      const active = Number(item.id) === Number(state.selectedId);
      return `
        <button type="button" data-select-provider="${item.id}" class="block w-full px-4 py-4 text-left transition ${active ? 'bg-[#2E5D73]/5' : 'hover:bg-slate-50'}">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="truncate text-base font-semibold text-slate-900">${escapeHtml(item.nombre_comercial)}</h3>
              <p class="mt-1 truncate text-sm text-slate-500">${escapeHtml(item.tipo_servicio || item.razon_social || 'Sin tipo de servicio')}</p>
            </div>
            <div class="flex shrink-0 flex-col items-end gap-1">
              <span class="rounded-full border px-3 py-1 text-xs font-semibold ${statusClass(item.estatus_cumplimiento)}">${escapeHtml(statusText(item.estatus_cumplimiento))}</span>
              <span class="rounded-full border px-2 py-0.5 text-[11px] font-semibold ${statusClass(item.estatus)}">${escapeHtml(statusText(item.estatus))}</span>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
            <span>${Number(item.personas_count || 0)} personas</span>
            <span>·</span>
            <span>${Number(item.documentos_count || 0)} docs</span>
            <span>·</span>
            <span>${Number(item.eventos_count || 0)} eventos</span>
            ${Number(item.activo) === 0 ? '<span class="font-semibold text-rose-600">Inactivo</span>' : ''}
          </div>
        </button>
      `;
    }).join('');
  }

  function renderPersonOptions(selected = '') {
    const select = els.personForm?.querySelector('[name="persona_recurrente_id"]');
    if (!select) return;
    select.innerHTML = '<option value="">Selecciona personal</option>' + state.personal.map((person) => {
      const detail = [person.empresa, person.area_nombre].filter(Boolean).join(' · ');
      return `<option value="${person.id}" ${String(person.id) === String(selected) ? 'selected' : ''}>${escapeHtml(person.nombre)}${detail ? ` — ${escapeHtml(detail)}` : ''}</option>`;
    }).join('');
  }

  function renderDetail() {
    const data = state.detail;
    const provider = data?.proveedor || null;
    els.detailEmpty?.classList.toggle('hidden', !!provider);
    els.detailBody?.classList.toggle('hidden', !provider);
    if (!provider || !els.detailBody) return;

    const personas = data.personas || [];
    const documentos = data.documentos || [];
    const eventos = data.eventos || [];
    els.detailBody.innerHTML = `
      <div class="border-b border-slate-100 p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-xl font-semibold text-slate-900">${escapeHtml(provider.nombre_comercial)}</h2>
              <span class="rounded-full border px-3 py-1 text-xs font-semibold ${statusClass(provider.estatus_cumplimiento)}">${escapeHtml(statusText(provider.estatus_cumplimiento))}</span>
              <span class="rounded-full border px-3 py-1 text-xs font-semibold ${statusClass(provider.estatus)}">${escapeHtml(statusText(provider.estatus))}</span>
              ${Number(provider.activo) === 0 ? '<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">Inactivo</span>' : ''}
            </div>
            <p class="mt-1 text-sm text-slate-500">${escapeHtml(provider.razon_social || provider.tipo_servicio || 'Sin razón social capturada')}</p>
            <p class="mt-2 text-sm text-slate-600">${escapeHtml(provider.notas || '')}</p>
            ${provider.motivo_bloqueo ? `<p class="mt-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">${escapeHtml(provider.motivo_bloqueo)}</p>` : ''}
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" data-edit-provider="${provider.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Editar</button>
            <button type="button" data-open-compliance-modal="${provider.id}" class="rounded-xl border border-amber-200 px-3 py-2 text-xs font-semibold text-amber-700">Semáforo</button>
            <button type="button" data-toggle-provider="${provider.id}" data-active="${Number(provider.activo) === 1 ? '0' : '1'}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">${Number(provider.activo) === 1 ? 'Desactivar' : 'Activar'}</button>
          </div>
        </div>
        <div class="mt-4 grid gap-3 md:grid-cols-3">
          <div class="rounded-2xl bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-400">Contacto</div><div class="mt-1 font-semibold text-slate-800">${escapeHtml(provider.contacto_nombre || '—')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-400">Teléfono</div><div class="mt-1 font-semibold text-slate-800">${escapeHtml(provider.contacto_telefono || '—')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-3 text-sm"><div class="text-xs text-slate-400">Email</div><div class="mt-1 truncate font-semibold text-slate-800">${escapeHtml(provider.contacto_email || '—')}</div></div>
        </div>
      </div>

      <div class="grid gap-4 p-5 2xl:grid-cols-2">
        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500" data-os-label="associated_personnel">Personal asociado</h3>
            <button type="button" data-open-person-modal="${provider.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Asociar</button>
          </div>
          <div class="space-y-2">
            ${personas.map((person) => `
              <article class="rounded-2xl border border-slate-200 p-3">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="font-semibold text-slate-900">${escapeHtml(person.nombre)}</div>
                    <div class="mt-1 text-xs text-slate-500">${escapeHtml([person.rol, person.area_nombre, person.puesto].filter(Boolean).join(' · ') || 'Sin detalle')}</div>
                    ${person.cumplimiento_efectivo?.proveedor_nombre ? `<div class="mt-1 text-xs text-slate-500">Proveedor efectivo: ${escapeHtml(person.cumplimiento_efectivo.proveedor_nombre)}</div>` : ''}
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="rounded-full border px-2 py-1 text-[11px] font-semibold ${statusClass(person.cumplimiento_efectivo?.cumplimiento_estado || person.estatus_cumplimiento)}">${escapeHtml(statusText(person.cumplimiento_efectivo?.cumplimiento_estado || person.estatus_cumplimiento))}</span>
                    ${Number(person.asociacion_activa) === 1 ? '<span class="rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700">Activo</span>' : '<span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500">Inactivo</span>'}
                    ${Number(person.asociacion_activa) === 1 ? `<button type="button" data-remove-person="${person.id}" class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-500">Quitar</button>` : ''}
                  </div>
                </div>
              </article>
            `).join('') || '<div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aún no hay personal asociado.</div>'}
          </div>
        </section>

        <section class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Documentos</h3>
            <button type="button" data-open-doc-modal="${provider.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Nuevo documento</button>
          </div>
          <div class="space-y-2">
            ${documentos.map((doc) => `
              <article class="rounded-2xl border border-slate-200 p-3">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <div class="font-semibold text-slate-900">${escapeHtml(doc.tipo_documento)}</div>
                    <div class="mt-1 text-xs text-slate-500">${escapeHtml(doc.fecha_vencimiento || 'Sin vencimiento')}</div>
                    ${doc.archivo_url ? `<a href="${escapeHtml(doc.archivo_url)}" target="_blank" class="mt-2 inline-flex text-xs font-semibold text-[#2E5D73]">Abrir archivo</a>` : ''}
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="rounded-full border px-2 py-1 text-[11px] font-semibold ${statusClass(doc.estatus)}">${escapeHtml(statusText(doc.estatus))}</span>
                    <button type="button" data-edit-doc="${doc.id}" class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-500">Editar</button>
                  </div>
                </div>
              </article>
            `).join('') || '<div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Aún no hay documentos registrados.</div>'}
          </div>
        </section>
      </div>

      <section class="border-t border-slate-100 p-5">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Eventos recientes</h3>
        <div class="mt-3 space-y-2">
          ${eventos.map((event) => `
            <article class="rounded-2xl border border-slate-200 p-3">
              <div class="flex flex-col gap-1 md:flex-row md:items-start md:justify-between">
                <div>
                  <div class="text-xs text-slate-400">${escapeHtml(event.fecha_hora || '')}</div>
                  <div class="font-semibold text-slate-900">${escapeHtml([event.tipo_origen, event.tipo_evento].filter(Boolean).join(' / ') || 'Evento')}</div>
                  <div class="mt-1 text-sm text-slate-500">${escapeHtml(event.observaciones || '')}</div>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">${escapeHtml(event.resultado || '—')}</span>
              </div>
            </article>
          `).join('') || '<div class="rounded-2xl border border-dashed border-slate-200 p-4 text-sm text-slate-500">Sin eventos recientes ligados al personal asociado.</div>'}
        </div>
      </section>
    `;
  }

  async function loadMeta() {
    const json = await fetchJSON(`${API}?action=meta`);
    const data = json.data || {};
    state.mode = data.context?.modo_operacion || state.mode;
    state.personal = data.personal || [];
    renderPersonOptions();
    window.OSGateLabels?.apply?.(root, state.mode);
  }

  async function loadList({ keepSelection = true } = {}) {
    const params = new URLSearchParams({ action: 'list' });
    const q = els.search?.value?.trim() || '';
    if (q) params.set('q', q);
    if (els.statusFilter?.value) params.set('estatus', els.statusFilter.value);

    const json = await fetchJSON(`${API}?${params.toString()}`);
    state.mode = json.data?.context?.modo_operacion || state.mode;
    state.proveedores = json.data?.proveedores || [];
    if (!keepSelection || !state.proveedores.some((item) => Number(item.id) === Number(state.selectedId))) {
      state.selectedId = Number(state.proveedores[0]?.id || 0);
    }
    renderList();
    if (state.selectedId) {
      await loadDetail(state.selectedId);
    } else {
      state.detail = null;
      renderDetail();
    }
  }

  async function loadDetail(id) {
    const json = await fetchJSON(`${API}?action=detail&id=${encodeURIComponent(id)}`);
    state.detail = json.data || null;
    state.selectedId = Number(id);
    renderList();
    renderDetail();
  }

  function openProviderForm(provider = null) {
    const form = els.providerForm;
    if (!form) return;
    form.reset();
    form.elements.id.value = provider?.id || '';
    form.elements.nombre_comercial.value = provider?.nombre_comercial || '';
    form.elements.razon_social.value = provider?.razon_social || '';
    form.elements.tipo_servicio.value = provider?.tipo_servicio || '';
    form.elements.contacto_nombre.value = provider?.contacto_nombre || '';
    form.elements.contacto_telefono.value = provider?.contacto_telefono || '';
    form.elements.contacto_email.value = provider?.contacto_email || '';
    form.elements.estatus.value = provider?.estatus || 'activo';
    form.elements.notas.value = provider?.notas || '';
    form.elements.activo.checked = Number(provider?.activo ?? 1) === 1;
    if (els.providerTitle) els.providerTitle.textContent = provider ? 'Editar proveedor' : 'Nuevo proveedor';
    openModal(els.providerModal);
  }

  function openPersonForm(providerId) {
    const form = els.personForm;
    if (!form) return;
    form.reset();
    form.elements.proveedor_id.value = providerId || state.selectedId || '';
    renderPersonOptions();
    openModal(els.personModal);
  }

  function openDocForm(providerId, doc = null) {
    const form = els.docForm;
    if (!form) return;
    form.reset();
    form.elements.id.value = doc?.id || '';
    form.elements.proveedor_id.value = providerId || state.selectedId || '';
    form.elements.tipo_documento.value = doc?.tipo_documento || '';
    form.elements.archivo_url.value = doc?.archivo_url || '';
    form.elements.fecha_vencimiento.value = doc?.fecha_vencimiento || '';
    form.elements.estatus.value = doc?.estatus || 'pendiente';
    if (els.docTitle) els.docTitle.textContent = doc ? 'Editar documento' : 'Nuevo documento';
    openModal(els.docModal);
  }

  function openComplianceForm(provider = null) {
    const form = els.complianceForm;
    if (!form || !provider) return;
    form.reset();
    form.elements.id.value = provider.id || '';
    form.elements.estatus_cumplimiento.value = provider.estatus_cumplimiento || 'autorizado';
    form.elements.motivo_bloqueo.value = provider.motivo_bloqueo || '';
    if (els.complianceTitle) els.complianceTitle.textContent = `Semáforo · ${provider.nombre_comercial || 'Proveedor'}`;
    openModal(els.complianceModal);
  }

  async function postAction(formData, successMessage = '') {
    const json = await fetchJSON(API, { method: 'POST', body: formData });
    showAlert(json.message || successMessage || 'Cambios guardados.');
    await loadMeta();
    await loadList({ keepSelection: true });
  }

  async function init() {
    try {
      await loadMeta();
      await loadList({ keepSelection: false });
    } catch (error) {
      showAlert(error.message || 'No se pudo cargar Proveedores.', 'error');
    }
  }

  let searchTimer = 0;
  els.search?.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadList({ keepSelection: false }).catch((error) => showAlert(error.message, 'error')), 250);
  });
  els.statusFilter?.addEventListener('change', () => loadList({ keepSelection: false }).catch((error) => showAlert(error.message, 'error')));
  els.clearFilters?.addEventListener('click', () => {
    if (els.search) els.search.value = '';
    if (els.statusFilter) els.statusFilter.value = '';
    loadList({ keepSelection: false }).catch((error) => showAlert(error.message, 'error'));
  });
  els.refresh?.addEventListener('click', () => loadList({ keepSelection: true }).catch((error) => showAlert(error.message, 'error')));
  els.newProvider?.addEventListener('click', () => openProviderForm());

  root.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    if (target.closest('[data-close-provider-modal]')) return closeModal(els.providerModal);
    if (target.closest('[data-close-person-modal]')) return closeModal(els.personModal);
    if (target.closest('[data-close-doc-modal]')) return closeModal(els.docModal);
    if (target.closest('[data-close-compliance-modal]')) return closeModal(els.complianceModal);

    const select = target.closest('[data-select-provider]');
    if (select) {
      const id = Number(select.dataset.selectProvider || 0);
      return loadDetail(id).catch((error) => showAlert(error.message || 'No se pudo cargar el proveedor.', 'error'));
    }

    const edit = target.closest('[data-edit-provider]');
    if (edit) return openProviderForm(providerById(edit.dataset.editProvider) || state.detail?.proveedor || null);

    const openPerson = target.closest('[data-open-person-modal]');
    if (openPerson) return openPersonForm(openPerson.dataset.openPersonModal);

    const openDoc = target.closest('[data-open-doc-modal]');
    if (openDoc) return openDocForm(openDoc.dataset.openDocModal);

    const openCompliance = target.closest('[data-open-compliance-modal]');
    if (openCompliance) return openComplianceForm(providerById(openCompliance.dataset.openComplianceModal) || state.detail?.proveedor || null);

    const editDoc = target.closest('[data-edit-doc]');
    if (editDoc) {
      const doc = (state.detail?.documentos || []).find((item) => Number(item.id) === Number(editDoc.dataset.editDoc));
      return openDocForm(state.selectedId, doc);
    }

    const removePerson = target.closest('[data-remove-person]');
    if (removePerson) {
      const provider = currentProvider();
      if (!provider || !window.confirm('¿Quitar esta asociación de personal?')) return null;
      const fd = new FormData();
      fd.set('action', 'remove_person');
      fd.set('proveedor_id', provider.id);
      fd.set('persona_recurrente_id', removePerson.dataset.removePerson || '');
      return withPendingAction({ button: removePerson, label: 'Quitando...' }, () => postAction(fd, 'Asociación desactivada.'))
        .catch((error) => showAlert(error.message || 'No se pudo quitar la asociación.', 'error'));
    }

    const statusBtn = target.closest('[data-status-provider]');
    if (statusBtn) {
      const fd = new FormData();
      fd.set('action', 'set_status');
      fd.set('id', statusBtn.dataset.statusProvider || '');
      fd.set('estatus', statusBtn.dataset.status || 'bloqueado');
      return withPendingAction({ button: statusBtn, label: 'Actualizando...' }, () => postAction(fd, 'Estatus actualizado.'))
        .catch((error) => showAlert(error.message || 'No se pudo actualizar el estatus.', 'error'));
    }

    const toggleBtn = target.closest('[data-toggle-provider]');
    if (toggleBtn) {
      const fd = new FormData();
      fd.set('action', 'toggle_active');
      fd.set('id', toggleBtn.dataset.toggleProvider || '');
      fd.set('activo', toggleBtn.dataset.active || '0');
      return withPendingAction({ button: toggleBtn, label: 'Actualizando...' }, () => postAction(fd, 'Proveedor actualizado.'))
        .catch((error) => showAlert(error.message || 'No se pudo actualizar el proveedor.', 'error'));
    }
    return null;
  });

  els.providerForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.providerForm.querySelector('button[type="submit"]'), scope: els.providerForm, label: 'Guardando...' }, async () => {
      const fd = formDataWithAction(els.providerForm, 'save');
      const json = await fetchJSON(API, { method: 'POST', body: fd });
      state.selectedId = Number(json.data?.id || state.selectedId || 0);
      closeModal(els.providerModal);
      showAlert(json.message || 'Proveedor guardado.');
      await loadList({ keepSelection: true });
    }).catch((error) => showAlert(error.message || 'No se pudo guardar el proveedor.', 'error'));
  });

  els.personForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.personForm.querySelector('button[type="submit"]'), scope: els.personForm, label: 'Asociando...' }, async () => {
      await postAction(formDataWithAction(els.personForm, 'associate_person'), 'Personal asociado.');
      closeModal(els.personModal);
    }).catch((error) => showAlert(error.message || 'No se pudo asociar el personal.', 'error'));
  });

  els.docForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.docForm.querySelector('button[type="submit"]'), scope: els.docForm, label: 'Guardando...' }, async () => {
      await postAction(formDataWithAction(els.docForm, 'save_document'), 'Documento guardado.');
      closeModal(els.docModal);
    }).catch((error) => showAlert(error.message || 'No se pudo guardar el documento.', 'error'));
  });

  els.complianceForm?.addEventListener('submit', (event) => {
    event.preventDefault();
    withPendingAction({ button: els.complianceForm.querySelector('button[type="submit"]'), scope: els.complianceForm, label: 'Guardando...' }, async () => {
      await postAction(formDataWithAction(els.complianceForm, 'set_compliance'), 'Semáforo actualizado.');
      closeModal(els.complianceModal);
    }).catch((error) => showAlert(error.message || 'No se pudo actualizar el semáforo.', 'error'));
  });

  init();
})();
