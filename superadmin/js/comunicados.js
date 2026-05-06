(function () {
    const root = document.getElementById('superadminComunicadosView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'comunicados.php';
    const PER_PAGE = 3;

    const els = {
        alert: document.getElementById('superadminComunicadosAlert'),
        summaryTotal: document.getElementById('saComSummaryTotal'),
        summaryPublicados: document.getElementById('saComSummaryPublicados'),
        summaryBorradores: document.getElementById('saComSummaryBorradores'),
        summaryArchivados: document.getElementById('saComSummaryArchivados'),
        filterForm: document.getElementById('saComunicadosFilterForm'),
        filterServicio: document.getElementById('saComFilterServicio'),
        filterEstado: document.getElementById('saComFilterEstado'),
        filterPrioridad: document.getElementById('saComFilterPrioridad'),
        filterQ: document.getElementById('saComFilterQ'),
        btnResetFilters: document.getElementById('btnResetSaComFilters'),
        btnRefresh: document.getElementById('btnRefreshSaComunicados'),
        btnAdd: document.getElementById('btnAddSaComunicado'),
        list: document.getElementById('saComunicadosList'),
        pagination: document.getElementById('saComunicadosPagination'),
        modal: document.getElementById('saComunicadoModal'),
        modalTitle: document.getElementById('saComunicadoModalTitle'),
        form: document.getElementById('saComunicadoForm'),
        formError: document.getElementById('saComunicadoFormError'),
        btnCloseModal: document.getElementById('btnCloseSaComModal'),
        btnCancelModal: document.getElementById('btnCancelSaComModal'),
        serviceSelect: document.getElementById('saComunicadoServiceSelect'),
    };

    const state = {
        csrf: '',
        services: [],
        items: [],
        page: 1,
        editingId: null,
        filters: {
            residencial_id: '',
            estado: '',
            prioridad: '',
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
            throw new Error(json?.error || 'No pudimos procesar los comunicados.');
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
        els.summaryPublicados.textContent = String(summary?.publicados ?? 0);
        els.summaryBorradores.textContent = String(summary?.borradores ?? 0);
        els.summaryArchivados.textContent = String(summary?.archivados ?? 0);
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

    function openCreateModal() {
        state.editingId = null;
        clearFormError();
        els.form.reset();
        els.modalTitle.textContent = 'Nuevo comunicado';
        fillServiceSelect(els.serviceSelect, false);
        const publication = els.form.querySelector('[name="fecha_publicacion"]');
        if (publication && !publication.value) {
            publication.value = new Date().toISOString().slice(0, 10);
        }
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

    function openEditModal(item) {
        state.editingId = item.id;
        clearFormError();
        els.form.reset();
        els.modalTitle.textContent = 'Editar comunicado';
        fillServiceSelect(els.serviceSelect, false);
        els.form.querySelector('[name="id"]').value = String(item.id);
        els.form.querySelector('[name="residencial_id"]').value = String(item.residencial_id);
        els.form.querySelector('[name="titulo"]').value = item.titulo || '';
        els.form.querySelector('[name="mensaje"]').value = item.mensaje || '';
        els.form.querySelector('[name="tipo"]').value = item.tipo || 'general';
        els.form.querySelector('[name="prioridad"]').value = item.prioridad || 'media';
        els.form.querySelector('[name="fecha_publicacion"]').value = item.fecha_publicacion || '';
        els.form.querySelector('[name="fecha_expiracion"]').value = item.fecha_expiracion || '';
        els.form.querySelector('[name="estado"]').value = item.estado || 'publicado';
        els.modal.classList.remove('hidden');
        els.modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    }

    function badgeStatus(status) {
        if (status === 'publicado') return 'bg-emerald-100 text-emerald-700';
        if (status === 'borrador') return 'bg-amber-100 text-amber-700';
        return 'bg-slate-100 text-slate-700';
    }

    function badgePriority(priority) {
        if (priority === 'alta') return 'bg-rose-100 text-rose-700';
        if (priority === 'media') return 'bg-amber-100 text-amber-700';
        return 'bg-slate-100 text-slate-700';
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
                  No encontramos comunicados con los filtros actuales.
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
                      <span class="rounded-full px-3 py-1 text-xs ${badgeStatus(item.estado)}">${escapeHtml(item.estado)}</span>
                      <span class="rounded-full px-3 py-1 text-xs ${badgePriority(item.prioridad)}">${escapeHtml(item.prioridad)}</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">${escapeHtml(item.residencial_nombre)}${item.residencial_codigo ? ' · ' + escapeHtml(item.residencial_codigo) : ''}</p>
                  </div>
                  <div class="text-sm leading-6 text-slate-700 whitespace-pre-wrap">${escapeHtml(item.mensaje)}</div>
                  <div class="grid grid-cols-1 gap-3 text-sm text-slate-700 md:grid-cols-4">
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Tipo</div>
                      <div class="mt-1">${escapeHtml(item.tipo)}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Publicación</div>
                      <div class="mt-1">${escapeHtml(item.fecha_publicacion || '—')}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Expiración</div>
                      <div class="mt-1">${escapeHtml(item.fecha_expiracion || '—')}</div>
                    </div>
                    <div>
                      <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">Actualizado por</div>
                      <div class="mt-1">${escapeHtml(item.actualizado_por_nombre || item.creado_por_nombre || '—')}</div>
                    </div>
                  </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                  ${item.estado !== 'archivado' ? `<button type="button" data-archive="${item.id}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Archivar</button>` : ''}
                  <button type="button" data-edit="${item.id}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Editar</button>
                  <button type="button" data-delete="${item.id}" class="rounded-full bg-rose-100 px-3 py-2 text-xs text-rose-700 hover:bg-rose-200">Borrar definitivo</button>
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

    async function submitForm(event) {
        event.preventDefault();
        clearFormError();
        const formData = new FormData(els.form);
        formData.append('csrf_token', state.csrf);
        formData.set('action', 'save');
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            closeModal();
            showAlert('success', json.message || 'Comunicado guardado.');
            await loadList();
        } catch (error) {
            showFormError(error.message || 'No pudimos guardar el comunicado.');
        }
    }

    async function archiveItem(id) {
        if (!window.confirm('¿Deseas archivar este comunicado?')) return;
        const formData = new FormData();
        formData.append('action', 'archive');
        formData.append('csrf_token', state.csrf);
        formData.append('id', String(id));
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            showAlert('success', json.message || 'Comunicado archivado.');
            await loadList();
        } catch (error) {
            showAlert('error', error.message || 'No pudimos archivar el comunicado.');
        }
    }

    async function deleteItem(id) {
        if (!window.confirm('¿Deseas borrar definitivamente este comunicado? Esta acción no se puede deshacer.')) return;
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('csrf_token', state.csrf);
        formData.append('id', String(id));
        try {
            const json = await fetchJson(API, { method: 'POST', body: formData });
            showAlert('success', json.message || 'Comunicado eliminado definitivamente.');
            await loadList();
        } catch (error) {
            showAlert('error', error.message || 'No pudimos eliminar el comunicado.');
        }
    }

    els.filterForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        state.filters = {
            residencial_id: els.filterServicio.value || '',
            estado: els.filterEstado.value || '',
            prioridad: els.filterPrioridad.value || '',
            q: (els.filterQ.value || '').trim(),
        };
        state.page = 1;
        await loadList();
    });

    els.btnResetFilters?.addEventListener('click', async () => {
        els.filterForm?.reset();
        state.filters = { residencial_id: '', estado: '', prioridad: '', q: '' };
        state.page = 1;
        await loadList();
    });

    els.btnRefresh?.addEventListener('click', loadList);
    els.btnAdd?.addEventListener('click', openCreateModal);
    els.btnCloseModal?.addEventListener('click', closeModal);
    els.btnCancelModal?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (event) => {
        if (event.target === els.modal) closeModal();
    });
    els.form?.addEventListener('submit', submitForm);

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
            if (item) openEditModal(item);
            return;
        }

        const archiveBtn = event.target.closest('[data-archive]');
        if (archiveBtn) {
            await archiveItem(Number(archiveBtn.dataset.archive || 0));
            return;
        }

        const deleteBtn = event.target.closest('[data-delete]');
        if (deleteBtn) {
            await deleteItem(Number(deleteBtn.dataset.delete || 0));
        }
    });

    refreshAll().catch((error) => {
        showAlert('error', error.message || 'No pudimos cargar los comunicados.');
    });
})();
