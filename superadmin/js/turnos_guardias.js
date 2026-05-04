(function () {
    const root = document.getElementById('superadminTurnosGuardiasView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'turnos_guardias.php';
    const PER_PAGE = 6;

    const els = {
        alert: document.getElementById('turnosGuardiasAlert'),
        summaryTotal: document.getElementById('turnosSummaryTotal'),
        summaryActivos: document.getElementById('turnosSummaryActivos'),
        summaryGuardias: document.getElementById('turnosSummaryGuardias'),
        summaryServicios: document.getElementById('turnosSummaryServicios'),
        filterForm: document.getElementById('turnosGuardiasFilterForm'),
        filterServicio: document.getElementById('turnosFilterServicio'),
        filterGuardia: document.getElementById('turnosFilterGuardia'),
        filterActivo: document.getElementById('turnosFilterActivo'),
        filterQ: document.getElementById('turnosFilterQ'),
        btnResetFilters: document.getElementById('btnResetTurnosFilters'),
        btnRefresh: document.getElementById('btnRefreshTurnosGuardias'),
        btnAdd: document.getElementById('btnAddTurnoGuardia'),
        list: document.getElementById('turnosGuardiasList'),
        pagination: document.getElementById('turnosGuardiasPagination'),
        modal: document.getElementById('turnoGuardiaModal'),
        modalTitle: document.getElementById('turnoGuardiaModalTitle'),
        form: document.getElementById('turnoGuardiaForm'),
        formError: document.getElementById('turnoGuardiaFormError'),
        btnCloseModal: document.getElementById('btnCloseTurnoGuardiaModal'),
        btnCancelModal: document.getElementById('btnCancelTurnoGuardiaModal'),
        modalService: document.getElementById('turnoGuardiaServiceSelect'),
        modalGuardia: document.getElementById('turnoGuardiaUserSelect'),
        exceptionModal: document.getElementById('turnoGuardiaExceptionModal'),
        exceptionModalTitle: document.getElementById('turnoGuardiaExceptionModalTitle'),
        exceptionModalSubtitle: document.getElementById('turnoGuardiaExceptionModalSubtitle'),
        exceptionForm: document.getElementById('turnoGuardiaExceptionForm'),
        exceptionFormError: document.getElementById('turnoGuardiaExceptionFormError'),
        btnCloseExceptionModal: document.getElementById('btnCloseTurnoGuardiaExceptionModal'),
        btnCancelExceptionModal: document.getElementById('btnCancelTurnoGuardiaExceptionModal'),
        btnResetExceptionForm: document.getElementById('btnResetTurnoGuardiaExceptionForm'),
        exceptionList: document.getElementById('turnoGuardiaExceptionList'),
        exceptionFilterPeriod: document.getElementById('turnoGuardiaExceptionFilterPeriod'),
        exceptionFilterActive: document.getElementById('turnoGuardiaExceptionFilterActive'),
        btnRefreshExceptions: document.getElementById('btnRefreshTurnoGuardiaExceptions'),
    };

    const state = {
        csrf: '',
        services: [],
        guardias: [],
        items: [],
        page: 1,
        editingId: null,
        exceptionContext: null,
        filters: {
            residencial_id: '',
            guardia_id: '',
            solo_activos: '',
            q: '',
        },
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showAlert(type, message) {
        const normalizedType = type === 'ok' ? 'success' : type;
        if (window.AppToast?.show) {
            window.AppToast.show({ type: normalizedType, message });
            return;
        }
        if (!els.alert) return;
        els.alert.className = `rounded-2xl px-4 py-3 text-sm ${normalizedType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`;
        els.alert.textContent = message;
        els.alert.classList.remove('hidden');
    }

    function showFormError(message) {
        if (!els.formError) {
            showAlert('error', message);
            return;
        }
        els.formError.textContent = message;
        els.formError.classList.remove('hidden');
    }

    function clearFormError() {
        if (!els.formError) return;
        els.formError.textContent = '';
        els.formError.classList.add('hidden');
    }

    function showExceptionFormError(message) {
        if (!els.exceptionFormError) {
            showAlert('error', message);
            return;
        }
        els.exceptionFormError.textContent = message;
        els.exceptionFormError.classList.remove('hidden');
    }

    function clearExceptionFormError() {
        if (!els.exceptionFormError) return;
        els.exceptionFormError.textContent = '';
        els.exceptionFormError.classList.add('hidden');
    }

    function seedExceptionFormContext() {
        if (!state.exceptionContext || !els.exceptionForm) return;
        const residencialInput = els.exceptionForm.querySelector('[name="residencial_id"]');
        const guardiaInput = els.exceptionForm.querySelector('[name="guardia_id"]');
        const activeInput = els.exceptionForm.querySelector('[name="activo"]');
        const exceptionIdInput = els.exceptionForm.querySelector('[name="exception_id"]');

        if (exceptionIdInput) exceptionIdInput.value = '';
        if (residencialInput) residencialInput.value = String(state.exceptionContext.residencial_id || '');
        if (guardiaInput) guardiaInput.value = String(state.exceptionContext.guardia_id || '');
        if (activeInput) activeInput.checked = true;
    }

    async function fetchJson(url, options = {}) {
        const { headers = {}, ...rest } = options;
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...headers },
            ...rest,
        });
        const text = await response.text();
        let json = null;
        try {
            json = JSON.parse(text);
        } catch (error) {
            throw new Error(text || 'Respuesta inválida del servidor.');
        }
        if (!response.ok || !json?.ok) {
            throw new Error(json?.error || 'No pudimos procesar los turnos.');
        }
        return json;
    }

    function queryFromFilters() {
        const params = new URLSearchParams({ action: 'list' });
        Object.entries(state.filters).forEach(([key, value]) => {
            if (String(value ?? '').trim() !== '') {
                params.set(key, value);
            }
        });
        return params.toString();
    }

    function setSummary(summary) {
        els.summaryTotal.textContent = String(summary?.total ?? 0);
        els.summaryActivos.textContent = String(summary?.activos ?? 0);
        els.summaryGuardias.textContent = String(summary?.guardias ?? 0);
        els.summaryServicios.textContent = String(summary?.servicios ?? 0);
    }

    function getGuardiasForService(residencialId) {
        if (!residencialId) return state.guardias;
        return state.guardias.filter((guardia) => String(guardia.residencial_id) === String(residencialId));
    }

    function fillServiceSelect(select, includeAll = false) {
        if (!select) return;
        const baseOption = includeAll ? '<option value="">Todos</option>' : '<option value="">Selecciona un servicio</option>';
        select.innerHTML = baseOption + state.services.map((service) => (
            `<option value="${service.id}">${escapeHtml(service.label || service.nombre)}</option>`
        )).join('');
    }

    function fillGuardiaSelect(select, residencialId, includeAll = false) {
        if (!select) return;
        const guardias = getGuardiasForService(residencialId);
        const baseOption = includeAll ? '<option value="">Todos</option>' : '<option value="">Selecciona un guardia</option>';
        select.innerHTML = baseOption + guardias.map((guardia) => (
            `<option value="${guardia.id}">${escapeHtml(guardia.name)} (${escapeHtml(guardia.email)})</option>`
        )).join('');
    }

    function openCreateModal() {
        state.editingId = null;
        clearFormError();
        els.form.reset();
        els.modalTitle.textContent = 'Nuevo turno';
        fillServiceSelect(els.modalService, false);
        fillGuardiaSelect(els.modalGuardia, '', false);
        els.modal.classList.remove('hidden');
        els.modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.modal.classList.remove('flex');
        clearFormError();
        state.editingId = null;
        els.form.reset();
        document.body.style.overflow = '';
    }

    function openExceptionModal(item) {
        state.exceptionContext = item;
        clearExceptionFormError();
        els.exceptionForm?.reset();
        seedExceptionFormContext();
        if (els.exceptionModalTitle) {
            els.exceptionModalTitle.textContent = 'Excepciones del guardia';
        }
        if (els.exceptionModalSubtitle) {
            els.exceptionModalSubtitle.textContent = `${item.guardia_nombre} · ${item.residencial_nombre}`;
        }
        if (els.exceptionFilterPeriod) els.exceptionFilterPeriod.value = '';
        if (els.exceptionFilterActive) els.exceptionFilterActive.value = '';
        els.exceptionModal?.classList.remove('hidden');
        els.exceptionModal?.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeExceptionModal() {
        els.exceptionModal?.classList.add('hidden');
        els.exceptionModal?.classList.remove('flex');
        clearExceptionFormError();
        els.exceptionForm?.reset();
        state.exceptionContext = null;
        document.body.style.overflow = '';
    }

    function openEditModal(item) {
        state.editingId = item.id;
        clearFormError();
        els.form.reset();
        els.modalTitle.textContent = 'Editar turno';
        fillServiceSelect(els.modalService, false);
        els.modalService.value = String(item.residencial_id);
        fillGuardiaSelect(els.modalGuardia, item.residencial_id, false);
        els.modalGuardia.value = String(item.guardia_id);
        els.form.querySelector('[name="turno_id"]').value = String(item.id);
        els.form.querySelector('[name="nombre_turno"]').value = item.nombre_turno || '';
        els.form.querySelector('[name="hora_inicio"]').value = item.hora_inicio || '';
        els.form.querySelector('[name="hora_fin"]').value = item.hora_fin || '';
        els.form.querySelector('[name="dias_semana"]').value = item.dias_semana || '';
        els.form.querySelector('[name="activo"]').checked = Number(item.activo || 0) === 1;
        els.modal.classList.remove('hidden');
        els.modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function getPageInfo() {
        const total = state.items.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        const page = Math.min(Math.max(state.page, 1), totalPages);
        const start = (page - 1) * PER_PAGE;
        const visible = state.items.slice(start, start + PER_PAGE);
        return { total, totalPages, page, start, end: Math.min(total, start + visible.length), visible };
    }

    function renderPagination(pageInfo) {
        if (!els.pagination) return;
        if (pageInfo.total <= PER_PAGE) {
            els.pagination.innerHTML = '';
            return;
        }
        els.pagination.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
              <div>Mostrando ${pageInfo.start + 1}-${pageInfo.end} de ${pageInfo.total}</div>
              <div class="flex items-center gap-2">
                <button data-page="${pageInfo.page - 1}" ${pageInfo.page <= 1 ? 'disabled' : ''} class="rounded-lg border px-3 py-2 disabled:opacity-40">Anterior</button>
                <div class="rounded-lg bg-white px-3 py-2 text-slate-700">Página ${pageInfo.page} de ${pageInfo.totalPages}</div>
                <button data-page="${pageInfo.page + 1}" ${pageInfo.page >= pageInfo.totalPages ? 'disabled' : ''} class="rounded-lg border px-3 py-2 disabled:opacity-40">Siguiente</button>
              </div>
            </div>
        `;
    }

    function renderList() {
        if (!els.list) return;
        const pageInfo = getPageInfo();
        state.page = pageInfo.page;

        if (!pageInfo.visible.length) {
            els.list.innerHTML = `
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                  No encontramos turnos con los filtros actuales.
                </div>
            `;
            renderPagination(pageInfo);
            return;
        }

        els.list.innerHTML = pageInfo.visible.map((item) => `
            <article class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
              <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-3">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-xl font-semibold text-slate-800">${escapeHtml(item.nombre_turno)}</h3>
                      <span class="rounded-full px-3 py-1 text-xs ${Number(item.activo) === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">${Number(item.activo) === 1 ? 'Activo' : 'Inactivo'}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">${escapeHtml(item.residencial_nombre)}${item.residencial_codigo ? ' · ' + escapeHtml(item.residencial_codigo) : ''}</p>
                  </div>
                  <div class="grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-3">
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Guardia</div>
                      <div class="mt-1 font-medium">${escapeHtml(item.guardia_nombre)}</div>
                      <div class="text-xs text-slate-500">${escapeHtml(item.guardia_email)}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Horario</div>
                      <div class="mt-1 font-medium">${escapeHtml(item.hora_inicio)} - ${escapeHtml(item.hora_fin)}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Días</div>
                      <div class="mt-1">${escapeHtml(item.dias_semana || '—')}</div>
                    </div>
                  </div>
                  ${Number(item.exception_today || 0) === 1 ? `
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                      Ausente hoy${item.exception_today_reason ? ` · ${escapeHtml(item.exception_today_reason)}` : ''}
                    </div>
                  ` : ''}
                </div>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                  <button type="button" data-toggle="${item.id}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
                    ${Number(item.activo) === 1 ? 'Desactivar' : 'Activar'}
                  </button>
                  <button type="button" data-edit="${item.id}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
                    Editar
                  </button>
                  <button type="button" data-exceptions="${item.id}" class="rounded-full border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 hover:bg-amber-100">
                    Excepciones
                  </button>
                  <button type="button" data-delete="${item.id}" class="rounded-full bg-rose-100 px-3 py-2 text-xs text-rose-700 hover:bg-rose-200">
                    Eliminar
                  </button>
                </div>
              </div>
            </article>
        `).join('');

        renderPagination(pageInfo);
    }

    async function loadMeta() {
        const json = await fetchJson(`${API}?action=meta`);
        state.csrf = json.data?.csrf_token || '';
        state.services = json.data?.servicios || [];
        state.guardias = json.data?.guardias || [];
        fillServiceSelect(els.filterServicio, true);
        fillServiceSelect(els.modalService, false);
        fillGuardiaSelect(els.filterGuardia, '', true);
    }

    async function loadList() {
        const json = await fetchJson(`${API}?${queryFromFilters()}`);
        state.items = json.data?.items || [];
        setSummary(json.data?.summary || {});
        renderList();
    }

    async function refreshAll() {
        await loadMeta();
        await loadList();
    }

    function renderExceptionList(items) {
        if (!els.exceptionList) return;
        if (!items.length) {
            els.exceptionList.innerHTML = `
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                  No hay excepciones registradas con los filtros actuales.
                </div>
            `;
            return;
        }

        els.exceptionList.innerHTML = items.map((item) => `
            <article class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
              <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.motivo || 'Excepción')}</div>
                    <span class="rounded-full px-2.5 py-1 text-[11px] ${Number(item.activo || 0) === 1 ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">
                      ${Number(item.activo || 0) === 1 ? 'Activa' : 'Inactiva'}
                    </span>
                    ${String(item.period_status || '') === 'today' && Number(item.activo || 0) === 1 ? '<span class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] text-amber-800">Ausente hoy</span>' : ''}
                  </div>
                  <div class="mt-2 text-sm text-slate-500">${escapeHtml(item.fecha_inicio)} → ${escapeHtml(item.fecha_fin)}</div>
                  ${item.notas ? `<div class="mt-2 text-xs text-slate-500">${escapeHtml(item.notas)}</div>` : ''}
                </div>
                <div class="flex flex-wrap gap-2 md:justify-end">
                  <button type="button" data-exception-toggle="${item.id}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
                    ${Number(item.activo || 0) === 1 ? 'Desactivar' : 'Activar'}
                  </button>
                  <button type="button" data-exception-edit="${item.id}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
                    Editar
                  </button>
                  <button type="button" data-exception-delete="${item.id}" class="rounded-full bg-rose-100 px-3 py-2 text-xs text-rose-700 hover:bg-rose-200">
                    Eliminar
                  </button>
                </div>
              </div>
            </article>
        `).join('');
    }

    async function loadExceptions() {
        if (!state.exceptionContext) return;
        const params = new URLSearchParams({
            action: 'list_exceptions',
            residencial_id: String(state.exceptionContext.residencial_id),
            guardia_id: String(state.exceptionContext.guardia_id),
        });
        if (els.exceptionFilterPeriod?.value) {
            params.set('period', els.exceptionFilterPeriod.value);
        }
        if (els.exceptionFilterActive?.value) {
            params.set('only_active', els.exceptionFilterActive.value);
        }
        const json = await fetchJson(`${API}?${params.toString()}`);
        state.exceptionContext.exceptions = json.data?.exceptions || [];
        renderExceptionList(state.exceptionContext.exceptions);
    }

    function syncFiltersFromForm() {
        state.filters.residencial_id = els.filterServicio.value || '';
        state.filters.guardia_id = els.filterGuardia.value || '';
        state.filters.solo_activos = els.filterActivo.value || '';
        state.filters.q = (els.filterQ.value || '').trim();
        state.page = 1;
    }

    async function submitForm(event) {
        event.preventDefault();
        clearFormError();

        const formData = new FormData(els.form);
        formData.append('csrf_token', state.csrf);
        formData.set('action', state.editingId ? 'update' : 'create');
        formData.set('activo', els.form.querySelector('[name="activo"]').checked ? '1' : '0');

        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            closeModal();
            showAlert('success', json.message || 'Turno guardado correctamente.');
            await loadList();
        } catch (error) {
            showFormError(error.message || 'No pudimos guardar el turno.');
        }
    }

    function validateExceptionForm() {
        const fechaInicio = String(els.exceptionForm?.querySelector('[name="fecha_inicio"]')?.value || '').trim();
        const fechaFin = String(els.exceptionForm?.querySelector('[name="fecha_fin"]')?.value || '').trim();
        const motivo = String(els.exceptionForm?.querySelector('[name="motivo"]')?.value || '').trim();

        if (!fechaInicio || !fechaFin) {
            throw new Error('Debes indicar la fecha inicial y final de la excepción.');
        }
        if (fechaFin < fechaInicio) {
            throw new Error('La fecha final no puede ser menor a la fecha inicial.');
        }
        if (!motivo) {
            throw new Error('El motivo de la excepción es obligatorio.');
        }
    }

    async function submitExceptionForm(event) {
        event.preventDefault();
        clearExceptionFormError();
        if (!state.exceptionContext) return;

        const formData = new FormData(els.exceptionForm);
        formData.append('csrf_token', state.csrf);
        formData.set('action', formData.get('exception_id') ? 'update_exception' : 'create_exception');
        formData.set('activo', els.exceptionForm.querySelector('[name="activo"]').checked ? '1' : '0');

        try {
            validateExceptionForm();
            const json = await fetchJson(API, { method: 'POST', body: formData });
            els.exceptionForm.reset();
            seedExceptionFormContext();
            state.exceptionContext.exceptions = json.data?.exceptions || [];
            renderExceptionList(state.exceptionContext.exceptions);
            showAlert('success', json.message || 'Excepción guardada correctamente.');
            await loadList();
        } catch (error) {
            showExceptionFormError(error.message || 'No pudimos guardar la excepción.');
        }
    }

    async function handleToggle(id) {
        const formData = new FormData();
        formData.append('action', 'toggle_active');
        formData.append('csrf_token', state.csrf);
        formData.append('turno_id', String(id));
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            showAlert('success', json.message || 'Turno actualizado.');
            await loadList();
        } catch (error) {
            showAlert('error', error.message || 'No pudimos cambiar el estado del turno.');
        }
    }

    async function handleToggleException(id) {
        if (!state.exceptionContext) return;
        const formData = new FormData();
        formData.append('action', 'toggle_exception');
        formData.append('csrf_token', state.csrf);
        formData.append('exception_id', String(id));
        formData.append('residencial_id', String(state.exceptionContext.residencial_id));
        formData.append('guardia_id', String(state.exceptionContext.guardia_id));
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            state.exceptionContext.exceptions = json.data?.exceptions || [];
            renderExceptionList(state.exceptionContext.exceptions);
            showAlert('success', json.message || 'Excepción actualizada.');
            await loadList();
        } catch (error) {
            showAlert('error', error.message || 'No pudimos cambiar el estado de la excepción.');
        }
    }

    async function handleDelete(id) {
        if (!window.confirm('¿Deseas eliminar este turno? Esta acción no se puede deshacer.')) {
            return;
        }
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('csrf_token', state.csrf);
        formData.append('turno_id', String(id));
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            showAlert('success', json.message || 'Turno eliminado.');
            await loadList();
        } catch (error) {
            showAlert('error', error.message || 'No pudimos eliminar el turno.');
        }
    }

    async function handleDeleteException(id) {
        if (!state.exceptionContext) return;
        if (!window.confirm('¿Deseas eliminar esta excepción? Esta acción no se puede deshacer.')) {
            return;
        }
        const formData = new FormData();
        formData.append('action', 'delete_exception');
        formData.append('csrf_token', state.csrf);
        formData.append('exception_id', String(id));
        formData.append('residencial_id', String(state.exceptionContext.residencial_id));
        formData.append('guardia_id', String(state.exceptionContext.guardia_id));
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            state.exceptionContext.exceptions = json.data?.exceptions || [];
            renderExceptionList(state.exceptionContext.exceptions);
            showAlert('success', json.message || 'Excepción eliminada.');
            await loadList();
        } catch (error) {
            showAlert('error', error.message || 'No pudimos eliminar la excepción.');
        }
    }

    els.filterServicio?.addEventListener('change', () => {
        fillGuardiaSelect(els.filterGuardia, els.filterServicio.value || '', true);
    });

    els.modalService?.addEventListener('change', () => {
        fillGuardiaSelect(els.modalGuardia, els.modalService.value || '', false);
    });

    els.filterForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        syncFiltersFromForm();
        await loadList();
    });

    els.btnResetFilters?.addEventListener('click', async () => {
        els.filterForm?.reset();
        fillGuardiaSelect(els.filterGuardia, '', true);
        state.filters = { residencial_id: '', guardia_id: '', solo_activos: '', q: '' };
        state.page = 1;
        await loadList();
    });

    els.btnRefresh?.addEventListener('click', refreshAll);
    els.btnAdd?.addEventListener('click', openCreateModal);
    els.btnCloseModal?.addEventListener('click', closeModal);
    els.btnCancelModal?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (event) => {
        if (event.target === els.modal) closeModal();
    });
    els.form?.addEventListener('submit', submitForm);
    els.btnCloseExceptionModal?.addEventListener('click', closeExceptionModal);
    els.btnCancelExceptionModal?.addEventListener('click', closeExceptionModal);
    els.btnResetExceptionForm?.addEventListener('click', () => {
        els.exceptionForm?.reset();
        seedExceptionFormContext();
        clearExceptionFormError();
    });
    els.exceptionModal?.addEventListener('click', (event) => {
        if (event.target === els.exceptionModal) closeExceptionModal();
    });
    els.exceptionForm?.addEventListener('submit', submitExceptionForm);
    els.exceptionFilterPeriod?.addEventListener('change', loadExceptions);
    els.exceptionFilterActive?.addEventListener('change', loadExceptions);
    els.btnRefreshExceptions?.addEventListener('click', loadExceptions);

    els.list?.addEventListener('click', async (event) => {
        const pageBtn = event.target.closest('[data-page]');
        if (pageBtn) {
            state.page = Number(pageBtn.dataset.page || 1);
            renderList();
            return;
        }

        const editBtn = event.target.closest('[data-edit]');
        if (editBtn) {
            const item = state.items.find((row) => String(row.id) === String(editBtn.dataset.edit));
            if (item) {
                openEditModal(item);
            }
            return;
        }

        const exceptionsBtn = event.target.closest('[data-exceptions]');
        if (exceptionsBtn) {
            const item = state.items.find((row) => String(row.id) === String(exceptionsBtn.dataset.exceptions));
            if (item) {
                openExceptionModal(item);
                await loadExceptions();
            }
            return;
        }

        const toggleBtn = event.target.closest('[data-toggle]');
        if (toggleBtn) {
            await handleToggle(Number(toggleBtn.dataset.toggle || 0));
            return;
        }

        const deleteBtn = event.target.closest('[data-delete]');
        if (deleteBtn) {
            await handleDelete(Number(deleteBtn.dataset.delete || 0));
        }
    });

    els.exceptionList?.addEventListener('click', async (event) => {
        const editBtn = event.target.closest('[data-exception-edit]');
        if (editBtn && state.exceptionContext && els.exceptionForm) {
            const item = (state.exceptionContext.exceptions || []).find((row) => String(row.id) === String(editBtn.dataset.exceptionEdit));
            if (item) {
                els.exceptionForm.querySelector('[name="exception_id"]').value = String(item.id);
                els.exceptionForm.querySelector('[name="residencial_id"]').value = String(state.exceptionContext.residencial_id);
                els.exceptionForm.querySelector('[name="guardia_id"]').value = String(state.exceptionContext.guardia_id);
                els.exceptionForm.querySelector('[name="fecha_inicio"]').value = item.fecha_inicio || '';
                els.exceptionForm.querySelector('[name="fecha_fin"]').value = item.fecha_fin || '';
                els.exceptionForm.querySelector('[name="motivo"]').value = item.motivo || '';
                els.exceptionForm.querySelector('[name="notas"]').value = item.notas || '';
                els.exceptionForm.querySelector('[name="activo"]').checked = Number(item.activo || 0) === 1;
            }
            return;
        }

        const toggleBtn = event.target.closest('[data-exception-toggle]');
        if (toggleBtn) {
            await handleToggleException(Number(toggleBtn.dataset.exceptionToggle || 0));
            return;
        }

        const deleteBtn = event.target.closest('[data-exception-delete]');
        if (deleteBtn) {
            await handleDeleteException(Number(deleteBtn.dataset.exceptionDelete || 0));
        }
    });

    refreshAll().catch((error) => {
        showAlert('error', error.message || 'No pudimos cargar los turnos de guardias.');
    });
})();
