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
    };

    let csrf = '';
    let planes = [];
    const state = {
        items: [],
        page: 1,
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

    function statusClass(status) {
        if (status === 'activo') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        if (status === 'prueba') return 'bg-sky-50 text-sky-700 border border-sky-200';
        if (status === 'suspendido') return 'bg-amber-50 text-amber-700 border border-amber-200';
        return 'bg-rose-50 text-rose-700 border border-rose-200';
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
            els.tableWrap.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay residenciales que coincidan con el filtro actual.</div>`;
            if (els.pagination) els.pagination.innerHTML = '';
            return;
        }

        const { visible } = getPageInfo(state.items, state.page);
        els.tableWrap.innerHTML = `
            <div class="space-y-3 md:hidden">
              ${visible.map((item) => `
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
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
                      <dt class="text-xs uppercase tracking-wide text-slate-400">Plan</dt>
                      <dd class="mt-1 text-slate-700">${escapeHtml(item.nombre_plan || 'Sin plan')}</dd>
                      <dd class="text-xs text-slate-500">${escapeHtml(item.codigo_plan || 'Sin código')}</dd>
                    </div>
                    <div>
                      <dt class="text-xs uppercase tracking-wide text-slate-400">Creado</dt>
                      <dd class="mt-1 text-slate-700">${escapeHtml(item.created_at || '—')}</dd>
                    </div>
                  </dl>
                </article>
              `).join('')}
            </div>
            <div class="hidden md:block">
              <table class="min-w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-400">
                  <tr>
                    <th class="px-3 py-2">Residencial</th>
                    <th class="px-3 py-2">Ubicación</th>
                    <th class="px-3 py-2">Plan</th>
                    <th class="px-3 py-2">Estatus</th>
                    <th class="px-3 py-2">Creado</th>
                  </tr>
                </thead>
                <tbody>
                  ${visible.map((item) => `
                    <tr class="border-t border-slate-100">
                      <td class="px-3 py-3">
                        <div class="font-medium text-slate-800">${escapeHtml(item.nombre)}</div>
                        <div class="text-xs text-slate-500">Código: ${escapeHtml(item.codigo || '—')}</div>
                      </td>
                      <td class="px-3 py-3 text-slate-600">${escapeHtml((item.ciudad || '') + ((item.estado ? ', ' + item.estado : '')))}<div class="text-xs text-slate-400">${escapeHtml(item.pais || '')}</div></td>
                      <td class="px-3 py-3 text-slate-600">${escapeHtml(item.nombre_plan || 'Sin plan')}<div class="text-xs text-slate-400">${escapeHtml(item.codigo_plan || '')}</div></td>
                      <td class="px-3 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${statusClass(item.estatus_plan)}">${escapeHtml(item.estatus_label || item.estatus_plan || '—')}</span></td>
                      <td class="px-3 py-3 text-slate-500">${escapeHtml(item.created_at || '')}</td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>
        `;

        renderPagination();
    }

    async function loadMeta() {
        const json = await api({ action: 'meta' });
        planes = json.data?.planes || [];
        csrf = json.data?.csrf_token || '';
        fillPlans();
    }

    async function loadList(resetPage = true) {
        if (resetPage) state.page = 1;
        const json = await api({
            action: 'list',
            q: els.q.value,
            plan_id: els.plan.value,
            estatus_plan: els.estatus.value,
        });

        const summary = json.data?.summary || {};
        els.summaryTotal.textContent = String(summary.total ?? 0);
        els.summaryActivos.textContent = String(summary.activos ?? 0);
        els.summaryPrueba.textContent = String(summary.prueba ?? 0);
        els.summaryPlan.textContent = String(summary.con_plan ?? 0);

        renderTable(json.data?.items || []);
    }

    function openModal() {
        els.form.reset();
        if (els.form.pais) els.form.pais.value = 'México';
        if (els.form.zona_horaria) els.form.zona_horaria.value = 'America/Mexico_City';
        if (els.form.estatus_plan) els.form.estatus_plan.value = 'activo';
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        document.body.style.overflow = '';
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
        loadList(true).catch((err) => showAlert('error', err.message || 'No se pudo limpiar.'));
    });

    els.btnRefresh?.addEventListener('click', () => {
        loadList(true).then(() => showAlert('ok', 'Listado actualizado.')).catch((err) => showAlert('error', err.message || 'No se pudo actualizar.'));
    });

    els.btnNuevo?.addEventListener('click', openModal);
    els.btnCloseModal?.addEventListener('click', closeModal);
    els.btnCancelModal?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });

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

        try {
            await api(payload);
            closeModal();
            await loadList(true);
            window.SuperadminDashboard?.loadContext?.();
            showAlert('ok', 'Residencial creado correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo crear el residencial.');
        }
    });

    Promise.all([loadMeta(), loadList(true)]).catch((err) => showAlert('error', err.message || 'No se pudo cargar residenciales.'));
})();
