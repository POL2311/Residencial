(function () {
    const root = document.getElementById('superadminResidencialesView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'residenciales.php';
    const PER_PAGE = 3;
    const els = {
        alert: document.getElementById('residencialesAlert'),
        summaryTotal: document.getElementById('resSummaryTotal'),
        summaryActivos: document.getElementById('resSummaryActivos'),
        summaryPrueba: document.getElementById('resSummaryPrueba'),
        summaryPlan: document.getElementById('resSummaryPlan'),
        filterForm: document.getElementById('residencialesFilterForm'),
        q: document.getElementById('resFiltroQ'),
        plan: document.getElementById('resFiltroPlan'),
        estatus: document.getElementById('resFiltroEstatus'),
        modo: document.getElementById('resFiltroModo'),
        btnClear: document.getElementById('btnLimpiarResidenciales'),
        btnRefresh: document.getElementById('btnRefreshResidenciales'),
        tableWrap: document.getElementById('residencialesTableWrap'),
        pagination: document.getElementById('residencialesPagination'),
        btnNuevo: document.getElementById('btnNuevoResidencial'),
        modal: document.getElementById('residencialModal'),
        form: document.getElementById('residencialForm'),
        btnCloseModal: document.getElementById('btnCloseResidencialModal'),
        btnCancelModal: document.getElementById('btnCancelResidencialModal'),
        planSelectModal: document.getElementById('resPlanSelect'),
        createPreset: document.getElementById('resServicePreset'),
        createRoleFields: document.getElementById('resServiceRoleFields'),
        createModuleFields: document.getElementById('resServiceModuleFields'),
        serviceModal: document.getElementById('serviceProfileModal'),
        serviceModalName: document.getElementById('serviceProfileModalName'),
        serviceForm: document.getElementById('serviceProfileForm'),
        serviceId: document.getElementById('serviceProfileResidencialId'),
        servicePreset: document.getElementById('serviceProfilePreset'),
        serviceRoleFields: document.getElementById('serviceProfileRoleFields'),
        serviceModuleFields: document.getElementById('serviceProfileModuleFields'),
        btnCloseServiceModal: document.getElementById('btnCloseServiceProfileModal'),
        btnCancelServiceModal: document.getElementById('btnCancelServiceProfileModal'),
    };

    let csrf = '';
    let planes = [];
    let serviceLabels = { roles: {}, modules: {} };
    let servicePresets = [];
    const state = {
        items: [],
        page: 1,
        serviceProfileItem: null,
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm ${type === 'ok' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`;
        els.alert.textContent = msg;
    }

    async function api(data) {
        const fd = new FormData();
        Object.entries(data || {}).forEach(([key, value]) => fd.append(key, value));
        const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.ok) throw new Error(json?.error || 'No se pudo procesar la solicitud.');
        return json;
    }

    function fillPlans() {
        const options = ['<option value="">Todos</option>'].concat(
            planes.map((plan) => `<option value="${escapeHtml(plan.id)}">${escapeHtml(plan.nombre)} (${escapeHtml(plan.codigo)})</option>`)
        );
        els.plan.innerHTML = options.join('');

        const modalOptions = ['<option value="">Sin asignar</option>'].concat(
            planes.map((plan) => `<option value="${escapeHtml(plan.id)}">${escapeHtml(plan.nombre)} (${escapeHtml(plan.codigo)})</option>`)
        );
        els.planSelectModal.innerHTML = modalOptions.join('');
    }

    function getPageInfo(items, page) {
        const total = items.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        const safePage = Math.min(Math.max(page, 1), totalPages);
        const start = (safePage - 1) * PER_PAGE;
        const visible = items.slice(start, start + PER_PAGE);
        return {
            total,
            totalPages,
            page: safePage,
            start,
            end: Math.min(start + visible.length, total),
            visible,
        };
    }

    function statusClass(status) {
        if (status === 'activo') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        if (status === 'prueba') return 'bg-sky-50 text-sky-700 border border-sky-200';
        if (status === 'suspendido') return 'bg-amber-50 text-amber-700 border border-amber-200';
        return 'bg-rose-50 text-rose-700 border border-rose-200';
    }

    function presetLabel(preset) {
        const key = String(preset || 'residencial');
        return key.charAt(0).toUpperCase() + key.slice(1);
    }

    function modeLabel(mode) {
        return presetLabel(mode || 'residencial');
    }

    function enabledSummary(profile, fieldGroup) {
        const source = profile?.[fieldGroup] || {};
        return Object.values(source)
            .filter((meta) => meta?.enabled)
            .map((meta) => meta.label)
            .slice(0, fieldGroup === 'roles' ? 3 : 4)
            .join(', ');
    }

    function renderPagination() {
        if (!els.pagination) return;
        const { total, totalPages, page, start, end } = getPageInfo(state.items, state.page);
        state.page = page;

        if (total <= PER_PAGE) {
            els.pagination.innerHTML = '';
            return;
        }

        els.pagination.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
              <div>Mostrando ${start + 1}-${end} de ${total}</div>
              <div class="flex items-center gap-2">
                <button type="button" data-page-action="prev" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page <= 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Anterior</button>
                <div class="rounded-lg bg-white px-3 py-1.5 text-slate-700">Página ${page} de ${totalPages}</div>
                <button type="button" data-page-action="next" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page >= totalPages ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Siguiente</button>
              </div>
            </div>
        `;
    }

    function renderTable(items = state.items) {
        state.items = Array.isArray(items) ? items : [];

        if (!state.items.length) {
            els.tableWrap.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay servicios que coincidan con el filtro actual.</div>`;
            if (els.pagination) els.pagination.innerHTML = '';
            return;
        }

        const { visible } = getPageInfo(state.items, state.page);
        els.tableWrap.innerHTML = `
            <div class="space-y-3 md:hidden">
              ${visible.map((item) => {
                  const roles = enabledSummary(item.service_profile, 'roles');
                  const modules = enabledSummary(item.service_profile, 'modules');
                  return `
                    <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                      <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                          <div class="font-semibold text-slate-800">${escapeHtml(item.nombre)}</div>
                          <div class="mt-1 text-xs text-slate-500">Código: ${escapeHtml(item.codigo || '—')}</div>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${statusClass(item.estatus_plan)}">${escapeHtml(item.estatus_label || item.estatus_plan || '—')}</span>
                      </div>
                      <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Ubicación</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml((item.ciudad || '') + (item.estado ? ', ' + item.estado : ''))}</dd>
                          <dd class="text-xs text-slate-500">${escapeHtml(item.pais || '')}</dd>
                        </div>
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Tipo de servicio</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml(presetLabel(item.preset_servicio || 'residencial'))}</dd>
                          <dd class="text-xs text-slate-500">Modo base: ${escapeHtml(modeLabel(item.modo_operacion || 'residencial'))}</dd>
                        </div>
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Roles habilitados</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml(roles || 'Sin roles visibles')}</dd>
                        </div>
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Módulos habilitados</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml(modules || 'Sin módulos visibles')}</dd>
                        </div>
                      </dl>
                      <div class="mt-4 flex justify-end">
                        <button type="button" data-service-profile-id="${escapeHtml(item.id)}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Configurar</button>
                      </div>
                    </article>
                  `;
              }).join('')}
            </div>
            <div class="hidden md:block">
              <div class="grid grid-cols-[minmax(220px,1.05fr)_minmax(150px,0.75fr)_minmax(160px,0.8fr)_minmax(190px,1fr)_minmax(220px,1.15fr)_120px_130px] items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                <div>Servicio / cliente</div>
                <div>Ubicación</div>
                <div>Tipo de servicio</div>
                <div>Roles habilitados</div>
                <div>Módulos habilitados</div>
                <div>Estatus</div>
                <div>Acciones</div>
              </div>
              <div class="mt-3 space-y-3">
                ${visible.map((item) => {
                    const roles = enabledSummary(item.service_profile, 'roles');
                    const modules = enabledSummary(item.service_profile, 'modules');
                    return `
                      <article class="grid grid-cols-[minmax(220px,1.05fr)_minmax(150px,0.75fr)_minmax(160px,0.8fr)_minmax(190px,1fr)_minmax(220px,1.15fr)_120px_130px] items-center gap-4 rounded-[1.75rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                        <div class="min-w-0">
                          <div class="font-semibold text-slate-800">${escapeHtml(item.nombre)}</div>
                          <div class="mt-1 text-xs text-slate-500">Código: ${escapeHtml(item.codigo || '—')}</div>
                        </div>
                        <div class="min-w-0">
                          <div class="text-sm text-slate-700">${escapeHtml((item.ciudad || '') + ((item.estado ? ', ' + item.estado : '')))}</div>
                          <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.pais || '')}</div>
                        </div>
                        <div class="min-w-0">
                          <div class="text-sm text-slate-700">${escapeHtml(presetLabel(item.preset_servicio || 'residencial'))}</div>
                          <div class="mt-1 text-xs text-slate-500">Modo base: ${escapeHtml(modeLabel(item.modo_operacion || 'residencial'))}</div>
                        </div>
                        <div class="min-w-0 text-sm text-slate-700">
                          <div>${escapeHtml(roles || 'Sin roles visibles')}</div>
                        </div>
                        <div class="min-w-0 text-sm text-slate-700">
                          <div>${escapeHtml(modules || 'Sin módulos visibles')}</div>
                        </div>
                        <div><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${statusClass(item.estatus_plan)}">${escapeHtml(item.estatus_label || item.estatus_plan || '—')}</span></div>
                        <div class="flex justify-end">
                          <button type="button" data-service-profile-id="${escapeHtml(item.id)}" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-600 hover:bg-slate-50">Configurar</button>
                        </div>
                      </article>
                    `;
                }).join('')}
              </div>
            </div>
        `;

        renderPagination();
    }

    function renderServiceFields(container, labels, values = {}) {
        if (!container) return;
        container.innerHTML = Object.entries(labels).map(([key, label]) => `
            <label class="flex items-center gap-2 text-sm text-slate-600">
              <input type="checkbox" name="${escapeHtml(key)}" value="1" ${values[key] ? 'checked' : ''}>
              <span>${escapeHtml(label)}</span>
            </label>
        `).join('');
    }

    function applyPresetToForm(form, presetKey) {
        const preset = servicePresets.find((item) => item.key === presetKey);
        const defaults = preset?.defaults || {};
        Object.keys(serviceLabels.roles || {}).forEach((key) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) field.checked = Number(defaults[key] || 0) === 1;
        });
        Object.keys(serviceLabels.modules || {}).forEach((key) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) field.checked = Number(defaults[key] || 0) === 1;
        });
    }

    function fillServicePresetOptions(select) {
        if (!select) return;
        select.innerHTML = servicePresets.map((preset) => `<option value="${escapeHtml(preset.key)}">${escapeHtml(preset.label)}</option>`).join('');
    }

    function initServiceForms() {
        fillServicePresetOptions(els.createPreset);
        fillServicePresetOptions(els.servicePreset);
        renderServiceFields(els.createRoleFields, serviceLabels.roles || {});
        renderServiceFields(els.createModuleFields, serviceLabels.modules || {});
        renderServiceFields(els.serviceRoleFields, serviceLabels.roles || {});
        renderServiceFields(els.serviceModuleFields, serviceLabels.modules || {});
        if (els.createPreset) {
            applyPresetToForm(els.form, els.createPreset.value || 'residencial');
        }
    }

    function openCreateModal() {
        els.form.reset();
        if (els.form.pais) els.form.pais.value = 'México';
        if (els.form.zona_horaria) els.form.zona_horaria.value = 'America/Mexico_City';
        if (els.form.estatus_plan) els.form.estatus_plan.value = 'activo';
        if (els.form.modo_operacion) els.form.modo_operacion.value = 'residencial';
        if (els.createPreset) {
            els.createPreset.value = 'residencial';
            applyPresetToForm(els.form, 'residencial');
        }
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateModal() {
        els.modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function openServiceModal(id) {
        const json = await api({ action: 'get_service_profile', id });
        const item = json.data?.item || null;
        if (!item) throw new Error('No encontramos el cliente seleccionado.');

        state.serviceProfileItem = item;
        els.serviceId.value = String(item.id);
        els.serviceModalName.textContent = `${item.nombre} · ${presetLabel(item.service_profile?.preset_servicio || item.modo_operacion || 'residencial')}`;
        if (els.servicePreset) {
            els.servicePreset.value = item.service_profile?.preset_servicio || item.modo_operacion || 'residencial';
        }

        Object.keys(serviceLabels.roles || {}).forEach((key) => {
            const field = els.serviceForm.querySelector(`[name="${key}"]`);
            if (field) field.checked = !!item.service_profile?.roles?.[key]?.enabled;
        });
        Object.keys(serviceLabels.modules || {}).forEach((key) => {
            const field = els.serviceForm.querySelector(`[name="${key}"]`);
            if (field) field.checked = !!item.service_profile?.modules?.[key]?.enabled;
        });

        els.serviceModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeServiceModal() {
        els.serviceModal.classList.add('hidden');
        document.body.style.overflow = '';
        state.serviceProfileItem = null;
    }

    async function loadMeta() {
        const json = await api({ action: 'meta' });
        planes = json.data?.planes || [];
        csrf = json.data?.csrf_token || '';
        serviceLabels = json.data?.service_labels || { roles: {}, modules: {} };
        servicePresets = json.data?.service_presets || [];
        fillPlans();
        initServiceForms();
    }

    async function loadList(resetPage = true) {
        if (resetPage) state.page = 1;
        const json = await api({
            action: 'list',
            q: els.q.value,
            plan_id: els.plan.value,
            estatus_plan: els.estatus.value,
            modo_operacion: els.modo?.value || '',
        });

        const summary = json.data?.summary || {};
        els.summaryTotal.textContent = String(summary.total ?? 0);
        els.summaryActivos.textContent = String(summary.activos ?? 0);
        els.summaryPrueba.textContent = String(summary.prueba ?? 0);
        els.summaryPlan.textContent = String(summary.con_plan ?? 0);

        renderTable(json.data?.items || []);
    }

    function collectServicePayload(form) {
        const payload = {};
        payload.preset_servicio = form.querySelector('[name="preset_servicio"]')?.value || 'residencial';
        Object.keys(serviceLabels.roles || {}).forEach((key) => {
            payload[key] = form.querySelector(`[name="${key}"]`)?.checked ? '1' : '0';
        });
        Object.keys(serviceLabels.modules || {}).forEach((key) => {
            payload[key] = form.querySelector(`[name="${key}"]`)?.checked ? '1' : '0';
        });
        return payload;
    }

    els.pagination?.addEventListener('click', (e) => {
        const button = e.target.closest('button[data-page-action]');
        if (!button) return;
        const action = button.getAttribute('data-page-action');
        const { totalPages } = getPageInfo(state.items, state.page);
        if (action === 'prev' && state.page > 1) state.page -= 1;
        if (action === 'next' && state.page < totalPages) state.page += 1;
        renderTable();
    });

    els.filterForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await loadList(true);
        } catch (err) {
            showAlert('error', err.message || 'No se pudo filtrar.');
        }
    });

    els.btnClear?.addEventListener('click', () => {
        els.filterForm.reset();
        if (els.modo) els.modo.value = '';
        loadList(true).catch((err) => showAlert('error', err.message || 'No se pudo limpiar.'));
    });

    els.btnRefresh?.addEventListener('click', () => {
        loadList(true).then(() => showAlert('ok', 'Listado actualizado.')).catch((err) => showAlert('error', err.message || 'No se pudo actualizar.'));
    });

    els.btnNuevo?.addEventListener('click', openCreateModal);
    els.btnCloseModal?.addEventListener('click', closeCreateModal);
    els.btnCancelModal?.addEventListener('click', closeCreateModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeCreateModal(); });

    els.createPreset?.addEventListener('change', (e) => {
        const nextPreset = e.target.value || 'residencial';
        const modeField = els.form?.querySelector('[name="modo_operacion"]');
        if (modeField) modeField.value = nextPreset;
        applyPresetToForm(els.form, e.target.value || 'residencial');
    });
    els.form?.querySelector('[name="modo_operacion"]')?.addEventListener('change', (e) => {
        if (els.createPreset) {
            els.createPreset.value = e.target.value || 'residencial';
            applyPresetToForm(els.form, els.createPreset.value);
        }
    });

    els.tableWrap?.addEventListener('click', (e) => {
        const button = e.target.closest('[data-service-profile-id]');
        if (!button) return;
        openServiceModal(Number(button.getAttribute('data-service-profile-id') || 0)).catch((err) => {
            showAlert('error', err.message || 'No se pudo abrir el perfil de servicio.');
        });
    });

    els.btnCloseServiceModal?.addEventListener('click', closeServiceModal);
    els.btnCancelServiceModal?.addEventListener('click', closeServiceModal);
    els.serviceModal?.addEventListener('click', (e) => { if (e.target === els.serviceModal) closeServiceModal(); });
    els.servicePreset?.addEventListener('change', (e) => {
        applyPresetToForm(els.serviceForm, e.target.value || 'residencial');
    });

    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(els.form);
        const payload = { action: 'create', csrf_token: csrf };
        fd.forEach((value, key) => {
            if (key === 'permite_qr' || key === 'permite_trabajadores_recurrentes' || key === 'requiere_placa_vehiculo' || key === 'requiere_identificacion_visita') {
                payload[key] = '1';
            } else {
                payload[key] = value;
            }
        });
        ['permite_qr', 'permite_trabajadores_recurrentes', 'requiere_placa_vehiculo', 'requiere_identificacion_visita'].forEach((field) => {
            if (!Object.prototype.hasOwnProperty.call(payload, field)) payload[field] = '0';
        });
        Object.assign(payload, collectServicePayload(els.form));

        try {
            await api(payload);
            closeCreateModal();
            await loadList(true);
            window.SuperadminDashboard?.loadContext?.();
            showAlert('ok', 'Cliente creado correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo crear el cliente.');
        }
    });

    els.serviceForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            action: 'update_service_profile',
            csrf_token: csrf,
            id: els.serviceId?.value || '',
            ...collectServicePayload(els.serviceForm),
        };

        try {
            await api(payload);
            closeServiceModal();
            await loadList(false);
            showAlert('ok', 'Perfil de servicio actualizado correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar el perfil de servicio.');
        }
    });

    Promise.all([loadMeta(), loadList(true)]).catch((err) => showAlert('error', err.message || 'No se pudo cargar residenciales.'));
})();
