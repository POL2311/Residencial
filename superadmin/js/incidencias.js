(function () {
    const root = document.getElementById('superadminIncidenciasView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'incidencias.php';
    const PER_PAGE = 3;

    const els = {
        alert: document.getElementById('superadminIncidenciasAlert'),
        summaryTotal: document.getElementById('saIncSummaryTotal'),
        summaryAbiertas: document.getElementById('saIncSummaryAbiertas'),
        summaryProceso: document.getElementById('saIncSummaryProceso'),
        summaryCerradas: document.getElementById('saIncSummaryCerradas'),
        filterForm: document.getElementById('saIncidenciasFilterForm'),
        filterServicio: document.getElementById('saIncFilterServicio'),
        filterEstado: document.getElementById('saIncFilterEstado'),
        filterPrioridad: document.getElementById('saIncFilterPrioridad'),
        filterTipo: document.getElementById('saIncFilterTipo'),
        filterQ: document.getElementById('saIncFilterQ'),
        btnResetFilters: document.getElementById('btnResetSaIncFilters'),
        btnRefresh: document.getElementById('btnRefreshSaIncidencias'),
        btnAdd: document.getElementById('btnAddSaIncidencia'),
        list: document.getElementById('saIncidenciasList'),
        pagination: document.getElementById('saIncidenciasPagination'),
        modal: document.getElementById('saIncidenciaModal'),
        modalTitle: document.getElementById('saIncidenciaModalTitle'),
        form: document.getElementById('saIncidenciaForm'),
        formError: document.getElementById('saIncidenciaFormError'),
        btnCloseModal: document.getElementById('btnCloseSaIncModal'),
        btnCancelModal: document.getElementById('btnCancelSaIncModal'),
        serviceSelect: document.getElementById('saIncidenciaServiceSelect'),
        unidadWrap: document.getElementById('saIncUnidadWrap'),
        unidadSelect: document.getElementById('saIncUnidadSelect'),
        operationalWrap: document.getElementById('saIncOperationalFields'),
        areaSelect: document.getElementById('saIncAreaSelect'),
        personaSelect: document.getElementById('saIncPersonaSelect'),
        visitanteSelect: document.getElementById('saIncVisitanteSelect'),
        permisoSelect: document.getElementById('saIncPermisoSelect'),
        tipoSelect: document.getElementById('saIncTipoSelect'),
        editModal: document.getElementById('saIncidenciaEditModal'),
        editForm: document.getElementById('saIncidenciaEditForm'),
        editError: document.getElementById('saIncidenciaEditError'),
        btnCloseEditModal: document.getElementById('btnCloseSaIncEditModal'),
        btnCancelEditModal: document.getElementById('btnCancelSaIncEditModal'),
        editGuardiaSelect: document.getElementById('saIncEditGuardiaSelect'),
    };

    const state = {
        csrf: '',
        services: [],
        items: [],
        page: 1,
        filters: {
            residencial_id: '',
            estado: '',
            prioridad: '',
            tipo: '',
            q: '',
        },
        metaByService: {},
        editingItem: null,
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

    function showFormError(target, message) {
        if (!target) {
            showAlert('error', message);
            return;
        }
        target.textContent = message;
        target.classList.remove('hidden');
    }

    function clearFormError(target) {
        if (!target) return;
        target.textContent = '';
        target.classList.add('hidden');
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
            throw new Error(json?.error || 'No pudimos procesar las incidencias.');
        }
        return json;
    }

    function fillServiceSelect(select, includeAll = false) {
        if (!select) return;
        const baseOption = includeAll ? '<option value="">Todos</option>' : '<option value="">Selecciona un servicio</option>';
        select.innerHTML = baseOption + state.services.map((service) => (
            `<option value="${service.id}">${escapeHtml(service.label || service.nombre)}</option>`
        )).join('');
    }

    function setSummary(summary) {
        els.summaryTotal.textContent = String(summary?.total ?? 0);
        els.summaryAbiertas.textContent = String(summary?.abiertas ?? 0);
        els.summaryProceso.textContent = String(summary?.en_proceso ?? 0);
        els.summaryCerradas.textContent = String(summary?.cerradas ?? 0);
    }

    function setSummary(summary) {
        els.summaryTotal.textContent = String(summary?.total ?? 0);
        els.summaryAbiertas.textContent = String(summary?.abiertas ?? 0);
        els.summaryProceso.textContent = String(summary?.en_proceso ?? 0);
        els.summaryCerradas.textContent = String(summary?.cerradas ?? 0);
    }

    function queryString() {
        const params = new URLSearchParams({ action: 'list' });
        Object.entries(state.filters).forEach(([key, value]) => {
            if (String(value ?? '').trim() !== '') {
                params.set(key, value);
            }
        });
        return params.toString();
    }

    function humanize(value) {
        return String(value || '')
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
    }

    function badgeStatus(status) {
        if (status === 'cerrada') return 'bg-emerald-100 text-emerald-700';
        if (status === 'en_proceso') return 'bg-amber-100 text-amber-700';
        return 'bg-rose-100 text-rose-700';
    }

    function badgePriority(priority) {
        if (priority === 'alta') return 'bg-rose-100 text-rose-700';
        if (priority === 'media') return 'bg-amber-100 text-amber-700';
        return 'bg-slate-100 text-slate-700';
    }

    function getServiceMeta(residencialId) {
        return state.metaByService[String(residencialId)] || null;
    }

    async function loadServiceMeta(residencialId) {
        if (!residencialId) return null;
        const key = String(residencialId);
        if (state.metaByService[key]) {
            return state.metaByService[key];
        }
        const json = await fetchJson(`${API}?action=meta&residencial_id=${encodeURIComponent(residencialId)}`);
        const meta = json.data?.service_meta || null;
        state.metaByService[key] = meta;
        return meta;
    }

    function fillSelect(select, rows, labelKey, emptyLabel) {
        if (!select) return;
        select.innerHTML = `<option value="">${emptyLabel}</option>` + (rows || []).map((row) => {
            const label = row?.[labelKey] || row?.nombre_visitante || row?.tipo_movimiento || row?.name || row?.clave || '—';
            return `<option value="${row.id}">${escapeHtml(label)}</option>`;
        }).join('');
    }

    function applyCreateMode(meta) {
        const isOperational = !!meta?.is_operational;
        els.unidadWrap?.classList.toggle('hidden', isOperational);
        els.operationalWrap?.classList.toggle('hidden', !isOperational);

        fillSelect(els.unidadSelect, meta?.unidades || [], 'clave', 'Sin unidad');
        fillSelect(els.areaSelect, meta?.areas || [], 'nombre', 'Sin área');
        fillSelect(els.personaSelect, meta?.personas || [], 'nombre', 'Sin persona');
        fillSelect(els.visitanteSelect, meta?.visitantes || [], 'nombre_visitante', 'Sin visitante');
        fillSelect(els.permisoSelect, meta?.permisos || [], 'tipo_movimiento', 'Sin permiso');

        const options = isOperational
            ? [
                ['seguridad', 'Seguridad'],
                ['robo', 'Robo'],
                ['conflicto', 'Conflicto'],
                ['salida_sin_permiso', 'Salida sin permiso'],
                ['visitante_sin_ine', 'Visitante sin INE'],
                ['material_no_coincide', 'Material no coincide'],
                ['evento_general', 'Evento general'],
                ['otro', 'Otro'],
            ]
            : [
                ['seguridad', 'Seguridad'],
                ['servicio', 'Servicio'],
                ['vecino', 'Vecino'],
                ['infraestructura', 'Infraestructura'],
                ['otro', 'Otro'],
            ];

        if (els.tipoSelect) {
            els.tipoSelect.innerHTML = options.map(([value, label]) => `<option value="${value}">${escapeHtml(label)}</option>`).join('');
        }
    }

    async function handleServiceChange() {
        const residencialId = els.serviceSelect?.value || '';
        if (!residencialId) {
            applyCreateMode({ is_operational: false, unidades: [], areas: [], personas: [], visitantes: [], permisos: [] });
            return;
        }
        try {
            const meta = await loadServiceMeta(residencialId);
            applyCreateMode(meta);
        } catch (error) {
            showFormError(els.formError, error.message || 'No pudimos cargar el contexto del servicio.');
        }
    }

    function openCreateModal() {
        clearFormError(els.formError);
        els.form.reset();
        els.modalTitle.textContent = 'Nueva incidencia';
        fillServiceSelect(els.serviceSelect, false);
        applyCreateMode({ is_operational: false, unidades: [], areas: [], personas: [], visitantes: [], permisos: [] });
        els.modal.classList.remove('hidden');
        els.modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateModal() {
        els.modal.classList.add('hidden');
        els.modal.classList.remove('flex');
        clearFormError(els.formError);
        els.form.reset();
        document.body.style.overflow = '';
    }

    function closeEditModal() {
        els.editModal.classList.add('hidden');
        els.editModal.classList.remove('flex');
        clearFormError(els.editError);
        els.editForm.reset();
        state.editingItem = null;
        document.body.style.overflow = '';
    }

    async function openEditModal(item) {
        state.editingItem = item;
        clearFormError(els.editError);
        els.editForm.reset();
        els.editForm.querySelector('[name="id"]').value = String(item.id);

        try {
            const meta = await loadServiceMeta(item.residencial_id);
            fillSelect(els.editGuardiaSelect, meta?.guardias || [], 'name', '-- Sin asignar --');
            els.editGuardiaSelect.value = item.guardia_id ? String(item.guardia_id) : '';
        } catch (error) {
            fillSelect(els.editGuardiaSelect, [], 'name', '-- Sin asignar --');
        }

        els.editForm.querySelector('[name="estado"]').value = item.estado || 'abierta';
        els.editForm.querySelector('[name="prioridad"]').value = item.prioridad || 'media';
        els.editModal.classList.remove('hidden');
        els.editModal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function pageInfo() {
        const total = state.items.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        const page = Math.min(Math.max(state.page, 1), totalPages);
        const start = (page - 1) * PER_PAGE;
        const visible = state.items.slice(start, start + PER_PAGE);
        return { total, totalPages, page, start, end: Math.min(total, start + visible.length), visible };
    }

    function renderPagination(info) {
        if (info.total <= PER_PAGE) {
            els.pagination.innerHTML = '';
            return;
        }
        els.pagination.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
              <div>Mostrando ${info.start + 1}-${info.end} de ${info.total}</div>
              <div class="flex items-center gap-2">
                <button data-page="${info.page - 1}" ${info.page <= 1 ? 'disabled' : ''} class="rounded-lg border px-3 py-2 disabled:opacity-40">Anterior</button>
                <div class="rounded-lg bg-white px-3 py-2 text-slate-700">Página ${info.page} de ${info.totalPages}</div>
                <button data-page="${info.page + 1}" ${info.page >= info.totalPages ? 'disabled' : ''} class="rounded-lg border px-3 py-2 disabled:opacity-40">Siguiente</button>
              </div>
            </div>
        `;
    }

    function renderList() {
        const info = pageInfo();
        state.page = info.page;

        if (!info.visible.length) {
            els.list.innerHTML = `
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-600">
                  No encontramos incidencias con los filtros actuales.
                </div>
            `;
            renderPagination(info);
            return;
        }

        els.list.innerHTML = info.visible.map((item) => `
            <article class="rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm">
              <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-3">
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <h3 class="text-xl font-semibold text-slate-800">${escapeHtml(item.titulo)}</h3>
                      <span class="rounded-full px-3 py-1 text-xs ${badgePriority(item.prioridad)}">${escapeHtml(humanize(item.prioridad))}</span>
                      <span class="rounded-full px-3 py-1 text-xs ${badgeStatus(item.estado)}">${escapeHtml(humanize(item.estado))}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">${escapeHtml(item.residencial_nombre)}${item.residencial_codigo ? ' · ' + escapeHtml(item.residencial_codigo) : ''}</p>
                  </div>
                  <div class="text-sm leading-6 text-slate-700 whitespace-pre-wrap">${escapeHtml(item.descripcion)}</div>
                  <div class="grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-4">
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Tipo</div>
                      <div class="mt-1">${escapeHtml(humanize(item.tipo))}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Contexto</div>
                      <div class="mt-1">${escapeHtml(item.unidad_clave || item.area_nombre || '—')}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Relación</div>
                      <div class="mt-1">${escapeHtml(item.residente_nombre || item.persona_recurrente_nombre || item.visitante_rapido_nombre || '—')}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Guardia</div>
                      <div class="mt-1">${escapeHtml(item.guardia_nombre || '—')}</div>
                    </div>
                  </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                  <button type="button" data-edit="${item.id}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
                    Editar
                  </button>
                  <button type="button" data-delete="${item.id}" class="rounded-full bg-rose-100 px-3 py-2 text-xs text-rose-700 hover:bg-rose-200">
                    Eliminar
                  </button>
                </div>
              </div>
            </article>
        `).join('');

        renderPagination(info);
    }

    async function loadMeta() {
        const json = await fetchJson(`${API}?action=meta`);
        state.csrf = json.data?.csrf_token || '';
        state.services = json.data?.servicios || [];
        fillServiceSelect(els.filterServicio, true);
        fillServiceSelect(els.serviceSelect, false);
    }

    async function loadList() {
        const json = await fetchJson(`${API}?${queryString()}`);
        state.items = json.data?.items || [];
        setSummary(json.data?.summary || {});
        renderList();
    }

    async function refreshAll() {
        await loadMeta();
        await loadList();
    }

    function validateCreateForm(formData) {
        const residencialId = String(formData.get('residencial_id') || '').trim();
        const titulo = String(formData.get('titulo') || '').trim();
        const descripcion = String(formData.get('descripcion') || '').trim();
        if (!residencialId) throw new Error('Debes seleccionar un servicio.');
        if (titulo.length < 3) throw new Error('El título debe tener al menos 3 caracteres.');
        if (descripcion.length < 5) throw new Error('La descripción debe tener al menos 5 caracteres.');
    }

    async function submitCreateForm(event) {
        event.preventDefault();
        clearFormError(els.formError);
        const formData = new FormData(els.form);
        formData.append('csrf_token', state.csrf);
        formData.set('action', 'create');

        try {
            validateCreateForm(formData);
            const json = await fetchJson(API, { method: 'POST', body: formData });
            closeCreateModal();
            showAlert('success', json.message || 'Incidencia registrada.');
            await loadList();
        } catch (error) {
            showFormError(els.formError, error.message || 'No pudimos registrar la incidencia.');
        }
    }

    async function submitEditForm(event) {
        event.preventDefault();
        clearFormError(els.editError);
        const formData = new FormData(els.editForm);
        formData.append('csrf_token', state.csrf);
        formData.set('action', 'update');

        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            closeEditModal();
            showAlert('success', json.message || 'Incidencia actualizada.');
            await loadList();
        } catch (error) {
            showFormError(els.editError, error.message || 'No pudimos actualizar la incidencia.');
        }
    }

    async function deleteItem(id) {
        if (!window.confirm('¿Deseas eliminar esta incidencia? Esta acción no se puede deshacer.')) return;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('csrf_token', state.csrf);
        formData.append('id', String(id));
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            showAlert('success', json.message || 'Incidencia eliminada.');
            await loadList();
        } catch (error) {
            showAlert('error', error.message || 'No pudimos eliminar la incidencia.');
        }
    }

    els.serviceSelect?.addEventListener('change', handleServiceChange);

    els.filterForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        state.filters = {
            residencial_id: els.filterServicio.value || '',
            estado: els.filterEstado.value || '',
            prioridad: els.filterPrioridad.value || '',
            tipo: (els.filterTipo.value || '').trim(),
            q: (els.filterQ.value || '').trim(),
        };
        state.page = 1;
        await loadList();
    });

    els.btnResetFilters?.addEventListener('click', async () => {
        els.filterForm?.reset();
        state.filters = { residencial_id: '', estado: '', prioridad: '', tipo: '', q: '' };
        state.page = 1;
        await loadList();
    });

    els.btnRefresh?.addEventListener('click', loadList);
    els.btnAdd?.addEventListener('click', openCreateModal);
    els.btnCloseModal?.addEventListener('click', closeCreateModal);
    els.btnCancelModal?.addEventListener('click', closeCreateModal);
    els.modal?.addEventListener('click', (event) => {
        if (event.target === els.modal) closeCreateModal();
    });
    els.form?.addEventListener('submit', submitCreateForm);

    els.btnCloseEditModal?.addEventListener('click', closeEditModal);
    els.btnCancelEditModal?.addEventListener('click', closeEditModal);
    els.editModal?.addEventListener('click', (event) => {
        if (event.target === els.editModal) closeEditModal();
    });
    els.editForm?.addEventListener('submit', submitEditForm);

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
                await openEditModal(item);
            }
            return;
        }

        const deleteBtn = event.target.closest('[data-delete]');
        if (deleteBtn) {
            await deleteItem(Number(deleteBtn.dataset.delete || 0));
        }
    });

    refreshAll().catch((error) => {
        showAlert('error', error.message || 'No pudimos cargar las incidencias.');
    });
})();
