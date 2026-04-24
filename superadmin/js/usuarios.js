(function () {
    const root = document.getElementById('superadminUsuariosView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'usuarios.php';
    const PER_PAGE = 3;
    const els = {
        alert: document.getElementById('usuariosAlert'),
        summaryTotal: document.getElementById('usersSummaryTotal'),
        summaryActive: document.getElementById('usersSummaryActive'),
        summaryAssignments: document.getElementById('usersSummaryAssignments'),
        summaryResidenciales: document.getElementById('usersSummaryResidenciales'),
        usersForm: document.getElementById('usuariosFilterForm'),
        q: document.getElementById('usersFiltroQ'),
        rol: document.getElementById('usersFiltroRol'),
        status: document.getElementById('usersFiltroStatus'),
        btnClearUsers: document.getElementById('btnLimpiarUsers'),
        btnRefreshUsers: document.getElementById('btnRefreshUsers'),
        usersWrap: document.getElementById('usersTableWrap'),
        usersPagination: document.getElementById('usersPagination'),
        assignQ: document.getElementById('assignFiltroQ'),
        assignRes: document.getElementById('assignFiltroRes'),
        btnClearAssignments: document.getElementById('btnLimpiarAssignments'),
        btnFilterAssignments: document.getElementById('btnFiltrarAssignments'),
        btnRefreshAssignments: document.getElementById('btnRefreshAssignments'),
        assignmentsWrap: document.getElementById('assignmentsTableWrap'),
        assignmentsPagination: document.getElementById('assignmentsPagination'),
        btnNuevoUsuario: document.getElementById('btnNuevoUsuario'),
        btnNuevaAsignacion: document.getElementById('btnNuevaAsignacion'),
        userModal: document.getElementById('userModal'),
        assignmentModal: document.getElementById('assignmentModal'),
        userForm: document.getElementById('userForm'),
        assignmentForm: document.getElementById('assignmentForm'),
        btnCloseUserModal: document.getElementById('btnCloseUserModal'),
        btnCancelUserModal: document.getElementById('btnCancelUserModal'),
        btnCloseAssignmentModal: document.getElementById('btnCloseAssignmentModal'),
        btnCancelAssignmentModal: document.getElementById('btnCancelAssignmentModal'),
        userTipoSelect: document.getElementById('userTipoSelect'),
        assignUserSelect: document.getElementById('assignUserSelect'),
        assignResidencialSelect: document.getElementById('assignResidencialSelect'),
    };

    let csrf = '';
    let tipos = [];
    let usuariosMeta = [];
    let residencialesMeta = [];
    const state = {
        users: [],
        usersPage: 1,
        assignments: [],
        assignmentsPage: 1,
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

    function fillSelects() {
        els.rol.innerHTML = ['<option value="">Todos</option>'].concat(
            tipos.map((tipo) => `<option value="${escapeHtml(tipo.id)}">${escapeHtml(tipo.nombre)}</option>`)
        ).join('');

        els.userTipoSelect.innerHTML = ['<option value="">Selecciona un rol</option>'].concat(
            tipos.map((tipo) => `<option value="${escapeHtml(tipo.id)}">${escapeHtml(tipo.nombre)}</option>`)
        ).join('');

        els.assignUserSelect.innerHTML = ['<option value="">Selecciona usuario</option>'].concat(
            usuariosMeta.map((user) => `<option value="${escapeHtml(user.id)}">${escapeHtml(user.name)} (${escapeHtml(user.email)})</option>`)
        ).join('');

        const resOptions = ['<option value="">Todos</option>'].concat(
            residencialesMeta.map((res) => `<option value="${escapeHtml(res.id)}">${escapeHtml(res.nombre)} (${escapeHtml(res.codigo)})</option>`)
        ).join('');
        els.assignRes.innerHTML = resOptions;

        els.assignResidencialSelect.innerHTML = ['<option value="">Selecciona residencial</option>'].concat(
            residencialesMeta.map((res) => `<option value="${escapeHtml(res.id)}">${escapeHtml(res.nombre)} (${escapeHtml(res.codigo)})</option>`)
        ).join('');
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

    function renderPager(container, items, page, key) {
        if (!container) return;
        const { total, totalPages, start, end } = getPageInfo(items, page);
        if (total <= PER_PAGE) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
              <div>Mostrando ${start + 1}-${end} de ${total}</div>
              <div class="flex items-center gap-2">
                <button type="button" data-page-group="${key}" data-page-action="prev" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page <= 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Anterior</button>
                <div class="rounded-lg bg-white px-3 py-1.5 text-slate-700">Página ${page} de ${totalPages}</div>
                <button type="button" data-page-group="${key}" data-page-action="next" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page >= totalPages ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Siguiente</button>
              </div>
            </div>
        `;
    }

    function userStatusPill(item) {
        return Number(item.is_active || 0) === 1
            ? '<span class="inline-flex rounded-full px-2.5 py-1 text-[11px] bg-emerald-50 text-emerald-700 border border-emerald-200">Activo</span>'
            : '<span class="inline-flex rounded-full px-2.5 py-1 text-[11px] bg-slate-100 text-slate-600 border border-slate-200">Inactivo</span>';
    }

    function renderUsers(items = state.users) {
        state.users = Array.isArray(items) ? items : [];
        const { visible, page } = getPageInfo(state.users, state.usersPage);
        state.usersPage = page;

        els.usersWrap.innerHTML = state.users.length ? `
            <div class="space-y-3 md:hidden">
              ${visible.map((item) => `
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="font-semibold text-slate-800">${escapeHtml(item.name)}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.telefono || 'Sin teléfono')}</div>
                    </div>
                    ${userStatusPill(item)}
                  </div>
                  <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Email</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.email)}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Rol</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.rol_nombre)}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Creado</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.created_at || '—')}</dd></div>
                  </dl>
                </article>
              `).join('')}
            </div>
            <div class="hidden md:block">
              <div class="grid grid-cols-[minmax(220px,1.1fr)_minmax(220px,1.15fr)_180px_130px_140px] items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                <div>Nombre</div>
                <div>Email</div>
                <div>Rol</div>
                <div>Estado</div>
                <div>Creado</div>
              </div>
              <div class="mt-3 space-y-3">
                ${visible.map((item) => `
                  <article class="grid grid-cols-[minmax(220px,1.1fr)_minmax(220px,1.15fr)_180px_130px_140px] items-center gap-4 rounded-[1.75rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="min-w-0">
                      <div class="font-semibold text-slate-800">${escapeHtml(item.name)}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.telefono || 'Sin teléfono')}</div>
                    </div>
                    <div class="text-sm text-slate-700 min-w-0">${escapeHtml(item.email)}</div>
                    <div class="text-sm text-slate-700">${escapeHtml(item.rol_nombre)}</div>
                    <div>${userStatusPill(item)}</div>
                    <div class="text-sm text-slate-500">${escapeHtml(item.created_at || '')}</div>
                  </article>
                `).join('')}
              </div>
            </div>
        ` : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay usuarios que coincidan con el filtro actual.</div>`;

        renderPager(els.usersPagination, state.users, state.usersPage, 'users');
    }

    function renderAssignments(items = state.assignments) {
        state.assignments = Array.isArray(items) ? items : [];
        const { visible, page } = getPageInfo(state.assignments, state.assignmentsPage);
        state.assignmentsPage = page;

        els.assignmentsWrap.innerHTML = state.assignments.length ? `
            <div class="space-y-3 md:hidden">
              ${visible.map((item) => `
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="font-semibold text-slate-800">${escapeHtml(item.usuario_nombre)}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.usuario_email)}</div>
                    </div>
                    ${Number(item.es_principal || 0) === 1 ? '<span class="inline-flex rounded-full px-2.5 py-1 text-[11px] bg-sky-50 text-sky-700 border border-sky-200">Principal</span>' : '<span class="inline-flex rounded-full px-2.5 py-1 text-[11px] bg-slate-100 text-slate-600 border border-slate-200">Secundario</span>'}
                  </div>
                  <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Residencial</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.residencial_nombre)}</dd><dd class="text-xs text-slate-500">${escapeHtml(item.residencial_codigo || '—')}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Rol</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.usuario_rol)}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Asignado</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.created_at || '—')}</dd></div>
                  </dl>
                </article>
              `).join('')}
            </div>
            <div class="hidden md:block">
              <div class="grid grid-cols-[minmax(220px,1.1fr)_minmax(220px,1.15fr)_160px_120px_140px] items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                <div>Usuario</div>
                <div>Residencial</div>
                <div>Rol</div>
                <div>Principal</div>
                <div>Asignado</div>
              </div>
              <div class="mt-3 space-y-3">
                ${visible.map((item) => `
                  <article class="grid grid-cols-[minmax(220px,1.1fr)_minmax(220px,1.15fr)_160px_120px_140px] items-center gap-4 rounded-[1.75rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="min-w-0">
                      <div class="font-semibold text-slate-800">${escapeHtml(item.usuario_nombre)}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.usuario_email)}</div>
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm text-slate-700">${escapeHtml(item.residencial_nombre)}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.residencial_codigo)}</div>
                    </div>
                    <div class="text-sm text-slate-700">${escapeHtml(item.usuario_rol)}</div>
                    <div>${Number(item.es_principal || 0) === 1 ? '<span class="inline-flex rounded-full px-2.5 py-1 text-[11px] bg-sky-50 text-sky-700 border border-sky-200">Sí</span>' : '<span class="text-sm text-slate-500">No</span>'}</div>
                    <div class="text-sm text-slate-500">${escapeHtml(item.created_at || '')}</div>
                  </article>
                `).join('')}
              </div>
            </div>
        ` : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Aún no hay asignaciones registradas.</div>`;

        els.summaryAssignments.textContent = String(state.assignments.length);
        els.summaryResidenciales.textContent = String(new Set(state.assignments.map((item) => String(item.residencial_id || ''))).size);
        renderPager(els.assignmentsPagination, state.assignments, state.assignmentsPage, 'assignments');
    }

    async function loadMeta() {
        const json = await api({ action: 'meta' });
        tipos = json.data?.tipos || [];
        usuariosMeta = json.data?.usuarios || [];
        residencialesMeta = json.data?.residenciales || [];
        csrf = json.data?.csrf_token || '';
        fillSelects();
    }

    async function loadUsers(resetPage = true) {
        if (resetPage) state.usersPage = 1;
        const json = await api({
            action: 'list_users',
            uq: els.q.value,
            urol: els.rol.value,
            ustatus: els.status.value,
        });

        const summary = json.data?.summary || {};
        els.summaryTotal.textContent = String(summary.total ?? 0);
        els.summaryActive.textContent = String(summary.activos ?? 0);
        renderUsers(json.data?.items || []);
    }

    async function loadAssignments(resetPage = true) {
        if (resetPage) state.assignmentsPage = 1;
        const json = await api({
            action: 'list_assignments',
            aq: els.assignQ.value,
            ares: els.assignRes.value,
        });
        renderAssignments(json.data?.items || []);
    }

    function openUserModal() {
        els.userForm.reset();
        els.userModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeUserModal() {
        els.userModal.classList.add('hidden');
        if (els.assignmentModal?.classList.contains('hidden')) document.body.style.overflow = '';
    }

    function openAssignmentModal() {
        els.assignmentForm.reset();
        els.assignmentModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeAssignmentModal() {
        els.assignmentModal.classList.add('hidden');
        if (els.userModal?.classList.contains('hidden')) document.body.style.overflow = '';
    }

    function handlePagerClick(e) {
        const button = e.target.closest('button[data-page-group]');
        if (!button) return;
        const group = button.getAttribute('data-page-group');
        const action = button.getAttribute('data-page-action');

        if (group === 'users') {
            const { totalPages } = getPageInfo(state.users, state.usersPage);
            if (action === 'prev' && state.usersPage > 1) state.usersPage -= 1;
            if (action === 'next' && state.usersPage < totalPages) state.usersPage += 1;
            renderUsers();
            return;
        }

        const { totalPages } = getPageInfo(state.assignments, state.assignmentsPage);
        if (action === 'prev' && state.assignmentsPage > 1) state.assignmentsPage -= 1;
        if (action === 'next' && state.assignmentsPage < totalPages) state.assignmentsPage += 1;
        renderAssignments();
    }

    els.usersPagination?.addEventListener('click', handlePagerClick);
    els.assignmentsPagination?.addEventListener('click', handlePagerClick);

    els.usersForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await loadUsers(true);
        } catch (err) {
            showAlert('error', err.message || 'No se pudo filtrar usuarios.');
        }
    });

    els.btnClearUsers?.addEventListener('click', () => {
        els.usersForm.reset();
        loadUsers(true).catch((err) => showAlert('error', err.message || 'No se pudo limpiar.'));
    });

    els.btnRefreshUsers?.addEventListener('click', () => {
        loadUsers(true).then(() => showAlert('ok', 'Usuarios actualizados.')).catch((err) => showAlert('error', err.message || 'No se pudo refrescar.'));
    });

    els.btnFilterAssignments?.addEventListener('click', () => {
        loadAssignments(true).catch((err) => showAlert('error', err.message || 'No se pudo filtrar asignaciones.'));
    });

    els.btnClearAssignments?.addEventListener('click', () => {
        els.assignQ.value = '';
        els.assignRes.value = '';
        loadAssignments(true).catch((err) => showAlert('error', err.message || 'No se pudo limpiar asignaciones.'));
    });

    els.btnRefreshAssignments?.addEventListener('click', () => {
        loadAssignments(true).then(() => showAlert('ok', 'Asignaciones actualizadas.')).catch((err) => showAlert('error', err.message || 'No se pudo refrescar.'));
    });

    els.btnNuevoUsuario?.addEventListener('click', openUserModal);
    els.btnNuevaAsignacion?.addEventListener('click', openAssignmentModal);

    els.btnCloseUserModal?.addEventListener('click', closeUserModal);
    els.btnCancelUserModal?.addEventListener('click', closeUserModal);
    els.userModal?.addEventListener('click', (e) => { if (e.target === els.userModal) closeUserModal(); });

    els.btnCloseAssignmentModal?.addEventListener('click', closeAssignmentModal);
    els.btnCancelAssignmentModal?.addEventListener('click', closeAssignmentModal);
    els.assignmentModal?.addEventListener('click', (e) => { if (e.target === els.assignmentModal) closeAssignmentModal(); });

    els.userForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(els.userForm);
        const payload = { action: 'create_user', csrf_token: csrf };
        fd.forEach((value, key) => {
            if (key === 'is_active') {
                payload[key] = '1';
            } else {
                payload[key] = value;
            }
        });
        if (!Object.prototype.hasOwnProperty.call(payload, 'is_active')) payload.is_active = '0';

        try {
            await api(payload);
            closeUserModal();
            await loadMeta();
            await loadUsers(true);
            showAlert('ok', 'Usuario creado correctamente.');
            window.SuperadminDashboard?.loadContext?.();
        } catch (err) {
            showAlert('error', err.message || 'No se pudo crear el usuario.');
        }
    });

    els.assignmentForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(els.assignmentForm);
        const payload = { action: 'assign_residencial', csrf_token: csrf };
        fd.forEach((value, key) => {
            if (key === 'es_principal') {
                payload[key] = '1';
            } else {
                payload[key] = value;
            }
        });
        if (!Object.prototype.hasOwnProperty.call(payload, 'es_principal')) payload.es_principal = '0';

        try {
            await api(payload);
            closeAssignmentModal();
            await loadMeta();
            await loadAssignments(true);
            showAlert('ok', 'Asignación creada correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo crear la asignación.');
        }
    });

    Promise.all([loadMeta(), loadUsers(true), loadAssignments(true)]).catch((err) => showAlert('error', err.message || 'No se pudo cargar el módulo de usuarios.'));
})();
