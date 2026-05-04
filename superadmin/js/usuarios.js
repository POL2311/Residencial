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
        deleteUserModal: document.getElementById('deleteUserModal'),
        deleteUserName: document.getElementById('deleteUserName'),
        deleteUserEmail: document.getElementById('deleteUserEmail'),
        btnCloseDeleteUserModal: document.getElementById('btnCloseDeleteUserModal'),
        btnCancelDeleteUserModal: document.getElementById('btnCancelDeleteUserModal'),
        btnConfirmDeleteUser: document.getElementById('btnConfirmDeleteUser'),
        userModalError: document.getElementById('userModalError'),
        assignmentModalError: document.getElementById('assignmentModalError'),
        userWizardResidencialId: document.getElementById('userWizardResidencialId'),
        userWizardServiceWrap: document.getElementById('userWizardServiceWrap'),
        userWizardServiceName: document.getElementById('userWizardServiceName'),
        userAssignAutoWrap: document.getElementById('userAssignAutoWrap'),
        userAssignPrimaryWrap: document.getElementById('userAssignPrimaryWrap'),
        assignmentPresetWrap: document.getElementById('assignmentPresetWrap'),
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
        deleteUser: null,
        onboardingService: null,
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
        const normalizedType = type === 'ok' ? 'success' : type;
        if (window.AppToast?.show) {
            window.AppToast.show({ type: normalizedType, message: msg });
            return;
        }
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm ${normalizedType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`;
        els.alert.textContent = msg;
    }

    function showInlineError(el, msg) {
        if (!el) {
            showAlert('error', msg);
            return;
        }
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    function clearInlineError(el) {
        if (!el) return;
        el.textContent = '';
        el.classList.add('hidden');
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

        const resOptions = ['<option value="">Todos</option>'].concat(
            residencialesMeta.map((res) => `<option value="${escapeHtml(res.id)}">${escapeHtml(res.nombre)} (${escapeHtml(res.codigo)})</option>`)
        ).join('');
        els.assignRes.innerHTML = resOptions;

        els.assignResidencialSelect.innerHTML = ['<option value="">Selecciona cliente o servicio</option>'].concat(
            residencialesMeta.map((res) => `<option value="${escapeHtml(res.id)}">${escapeHtml(res.nombre)} (${escapeHtml(res.codigo)})</option>`)
        ).join('');

        renderRoleOptions(state.onboardingService?.id || '');
        renderAssignableUsers(els.assignResidencialSelect?.value || '');
    }

    function normalizeRoleName(value) {
        const raw = String(value || '').trim().toLowerCase();
        if (raw === '') return '';
        if (raw.includes('super')) return 'super_admin';
        if (raw.includes('guard')) return 'guardia';
        if (raw.includes('resident')) return 'residente';
        if (raw.includes('admin_residencial') || raw.includes('admin operativo') || raw.includes('administr')) return 'admin_residencial';
        return raw.replace(/\s+/g, '_');
    }

    function getAllowedRoleKeysForService(serviceId) {
        const service = residencialesMeta.find((item) => String(item.id) === String(serviceId));
        const explicit = Array.isArray(service?.service_profile?.assignable_roles) ? service.service_profile.assignable_roles : [];
        return new Set(explicit.map((role) => normalizeRoleName(role)).filter(Boolean));
    }

    function compatibleUsersForService(serviceId) {
        const allowedRoles = getAllowedRoleKeysForService(serviceId);
        if (!allowedRoles.size) return [];
        return usuariosMeta.filter((user) => allowedRoles.has(normalizeRoleName(user.rol_nombre)));
    }

    function compatibleTiposForService(serviceId) {
        const allowedRoles = getAllowedRoleKeysForService(serviceId);
        if (!allowedRoles.size) return [];
        return tipos.filter((tipo) => allowedRoles.has(normalizeRoleName(tipo.nombre)));
    }

    function renderRoleOptions(serviceId = '') {
        const options = serviceId ? compatibleTiposForService(serviceId) : tipos;
        const placeholder = serviceId
            ? (options.length ? 'Selecciona un rol compatible' : 'No hay roles compatibles para este servicio')
            : 'Selecciona un rol';
        els.userTipoSelect.innerHTML = [`<option value="">${placeholder}</option>`].concat(
            options.map((tipo) => `<option value="${escapeHtml(tipo.id)}">${escapeHtml(tipo.nombre)}</option>`)
        ).join('');
        els.userTipoSelect.disabled = serviceId ? !options.length : false;
    }

    function renderAssignableUsers(serviceId = '') {
        if (!els.assignUserSelect) return;

        if (!serviceId) {
            els.assignUserSelect.innerHTML = '<option value="">Selecciona primero un servicio</option>';
            els.assignUserSelect.disabled = true;
            return;
        }

        const users = compatibleUsersForService(serviceId);
        if (!users.length) {
            els.assignUserSelect.innerHTML = '<option value="">No hay usuarios compatibles para este servicio</option>';
            els.assignUserSelect.disabled = true;
            return;
        }

        els.assignUserSelect.disabled = false;
        els.assignUserSelect.innerHTML = ['<option value="">Selecciona usuario compatible</option>'].concat(
            users.map((user) => {
                const inactiveLabel = Number(user.is_active || 0) === 1 ? '' : ' · inactivo';
                return `<option value="${escapeHtml(user.id)}">${escapeHtml(user.name)} (${escapeHtml(user.email)})${inactiveLabel}</option>`;
            })
        ).join('');
    }

    function updateAssignmentHelper(serviceId = '', fromGuided = false) {
        if (!els.assignmentPresetWrap) return;
        if (!serviceId) {
            els.assignmentPresetWrap.classList.add('hidden');
            return;
        }

        const service = residencialesMeta.find((item) => String(item.id) === String(serviceId));
        const compatibleCount = compatibleUsersForService(serviceId).length;
        const baseMessage = fromGuided
            ? 'Estás asignando un usuario al servicio seleccionado desde el flujo guiado.'
            : 'Solo se muestran usuarios compatibles con los roles habilitados para este servicio.';
        const followup = compatibleCount > 0
            ? `${compatibleCount} usuario${compatibleCount === 1 ? '' : 's'} compatible${compatibleCount === 1 ? '' : 's'} disponible${compatibleCount === 1 ? '' : 's'}.`
            : 'No hay usuarios compatibles todavía; te conviene crear uno nuevo para este servicio.';

        els.assignmentPresetWrap.innerHTML = `
            <div class="font-medium text-sky-900">${escapeHtml(service?.nombre || 'Servicio')}</div>
            <div class="mt-1">${escapeHtml(baseMessage)}</div>
            <div class="mt-1 text-sky-700">${escapeHtml(followup)}</div>
        `;
        els.assignmentPresetWrap.classList.remove('hidden');
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
                  <div class="mt-4 flex justify-end">
                    <button type="button" data-user-delete="${escapeHtml(item.id)}" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 hover:bg-rose-100 ${Number(item.is_active || 0) === 1 ? '' : 'opacity-60'}">
                      Eliminar
                    </button>
                  </div>
                </article>
              `).join('')}
            </div>
            <div class="hidden md:block">
              <div class="min-w-[930px]">
                <div class="grid grid-cols-[minmax(220px,1.1fr)_minmax(240px,1.15fr)_180px_130px_140px_120px] items-center gap-4 rounded-t-[1.5rem] border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                  <div>Nombre</div>
                  <div>Email</div>
                  <div>Rol</div>
                  <div>Estado</div>
                  <div>Creado</div>
                  <div class="text-right">Acciones</div>
                </div>
                <div class="overflow-hidden rounded-b-[1.5rem] border-x border-b border-slate-200 bg-white shadow-sm">
                  ${visible.map((item, index) => `
                    <article class="grid grid-cols-[minmax(220px,1.1fr)_minmax(240px,1.15fr)_180px_130px_140px_120px] items-center gap-4 px-4 py-4 ${index < visible.length - 1 ? 'border-b border-slate-100' : ''}">
                      <div class="min-w-0">
                        <div class="font-semibold text-slate-800">${escapeHtml(item.name)}</div>
                        <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.telefono || 'Sin teléfono')}</div>
                      </div>
                      <div class="min-w-0 text-sm text-slate-700">${escapeHtml(item.email)}</div>
                      <div class="text-sm text-slate-700">${escapeHtml(item.rol_nombre)}</div>
                      <div>${userStatusPill(item)}</div>
                      <div class="text-sm text-slate-500">${escapeHtml(item.created_at || '')}</div>
                      <div class="flex justify-end">
                        <button type="button" data-user-delete="${escapeHtml(item.id)}" class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-100 ${Number(item.is_active || 0) === 1 ? '' : 'opacity-60'}">
                          Eliminar
                        </button>
                      </div>
                    </article>
                  `).join('')}
                </div>
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
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Cliente / servicio</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.residencial_nombre)}</dd><dd class="text-xs text-slate-500">${escapeHtml(item.residencial_codigo || '—')}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Rol</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.usuario_rol)}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Asignado</dt><dd class="mt-1 text-slate-700">${escapeHtml(item.created_at || '—')}</dd></div>
                  </dl>
                </article>
              `).join('')}
            </div>
            <div class="hidden md:block">
              <div class="min-w-[900px]">
                <div class="grid grid-cols-[minmax(220px,1.1fr)_minmax(220px,1.15fr)_160px_120px_160px] items-center gap-4 rounded-t-[1.5rem] border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                  <div>Usuario</div>
                  <div>Cliente / servicio</div>
                  <div>Rol</div>
                  <div>Principal</div>
                  <div>Asignado</div>
                </div>
                <div class="overflow-hidden rounded-b-[1.5rem] border-x border-b border-slate-200 bg-white shadow-sm">
                  ${visible.map((item, index) => `
                    <article class="grid grid-cols-[minmax(220px,1.1fr)_minmax(220px,1.15fr)_160px_120px_160px] items-center gap-4 px-4 py-4 ${index < visible.length - 1 ? 'border-b border-slate-100' : ''}">
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
        clearInlineError(els.userModalError);
        state.onboardingService = null;
        if (els.userWizardResidencialId) els.userWizardResidencialId.value = '';
        if (els.userWizardServiceWrap) els.userWizardServiceWrap.classList.add('hidden');
        if (els.userAssignAutoWrap) els.userAssignAutoWrap.classList.add('hidden');
        if (els.userAssignPrimaryWrap) els.userAssignPrimaryWrap.classList.add('hidden');
        if (els.userWizardServiceName) els.userWizardServiceName.value = '';
        renderRoleOptions('');
        const title = els.userModal?.querySelector('.text-sm.font-semibold.text-slate-800');
        if (title) title.textContent = 'Nuevo usuario';
        els.userModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openUserModalForService(service) {
        openUserModal();
        state.onboardingService = service || null;
        if (els.userWizardResidencialId) els.userWizardResidencialId.value = String(service?.id || '');
        if (els.userWizardServiceName) {
            const type = service?.preset_servicio ? String(service.preset_servicio) : 'servicio';
            els.userWizardServiceName.value = `${service?.nombre || 'Servicio'} · ${type}`;
        }
        if (els.userWizardServiceWrap) els.userWizardServiceWrap.classList.remove('hidden');
        if (els.userAssignAutoWrap) els.userAssignAutoWrap.classList.remove('hidden');
        if (els.userAssignPrimaryWrap) els.userAssignPrimaryWrap.classList.remove('hidden');
        renderRoleOptions(service?.id || '');
        const assignField = els.userForm?.querySelector('[name="assign_to_service"]');
        const principalField = els.userForm?.querySelector('[name="assign_as_principal"]');
        if (assignField) assignField.checked = true;
        if (principalField) principalField.checked = true;
        const title = els.userModal?.querySelector('.text-sm.font-semibold.text-slate-800');
        if (title) title.textContent = 'Crear usuario y asignarlo';
    }

    function closeUserModal() {
        els.userModal.classList.add('hidden');
        clearInlineError(els.userModalError);
        state.onboardingService = null;
        if (els.assignmentModal?.classList.contains('hidden')) document.body.style.overflow = '';
    }

    function openAssignmentModal() {
        els.assignmentForm.reset();
        clearInlineError(els.assignmentModalError);
        updateAssignmentHelper('', false);
        renderAssignableUsers('');
        const title = els.assignmentModal?.querySelector('.text-sm.font-semibold.text-slate-800');
        if (title) title.textContent = 'Asignar usuario a cliente / servicio';
        els.assignmentModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function openAssignmentModalForService(service) {
        openAssignmentModal();
        if (els.assignResidencialSelect) {
            els.assignResidencialSelect.value = String(service?.id || '');
        }
        renderAssignableUsers(service?.id || '');
        updateAssignmentHelper(service?.id || '', true);
        const title = els.assignmentModal?.querySelector('.text-sm.font-semibold.text-slate-800');
        if (title) title.textContent = 'Asignar usuario existente a este servicio';
    }

    function closeAssignmentModal() {
        els.assignmentModal.classList.add('hidden');
        clearInlineError(els.assignmentModalError);
        if (els.userModal?.classList.contains('hidden')) document.body.style.overflow = '';
    }

    function openDeleteUserModal(userId) {
        const user = state.users.find((item) => String(item.id) === String(userId));
        if (!user) {
            showAlert('error', 'No encontramos el usuario seleccionado.');
            return;
        }
        state.deleteUser = user;
        if (els.deleteUserName) els.deleteUserName.textContent = user.name || '—';
        if (els.deleteUserEmail) els.deleteUserEmail.textContent = `${user.email || '—'} · ${user.rol_nombre || 'Sin rol'}`;
        els.deleteUserModal?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeDeleteUserModal() {
        els.deleteUserModal?.classList.add('hidden');
        state.deleteUser = null;
        if (els.userModal?.classList.contains('hidden') && els.assignmentModal?.classList.contains('hidden')) {
            document.body.style.overflow = '';
        }
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
    els.btnCloseDeleteUserModal?.addEventListener('click', closeDeleteUserModal);
    els.btnCancelDeleteUserModal?.addEventListener('click', closeDeleteUserModal);
    els.deleteUserModal?.addEventListener('click', (e) => { if (e.target === els.deleteUserModal) closeDeleteUserModal(); });
    els.assignResidencialSelect?.addEventListener('change', () => {
        const serviceId = els.assignResidencialSelect.value || '';
        renderAssignableUsers(serviceId);
        updateAssignmentHelper(serviceId, false);
    });

    els.userForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearInlineError(els.userModalError);
        const fd = new FormData(els.userForm);
        const assignToService = String(fd.get('assign_to_service') || '') === '1';
        const wizardResidencialId = String(fd.get('wizard_residencial_id') || '');
        const payload = { action: assignToService && wizardResidencialId ? 'create_user_with_assignment' : 'create_user', csrf_token: csrf };
        fd.forEach((value, key) => {
            if (key === 'is_active' || key === 'assign_to_service' || key === 'assign_as_principal') {
                payload[key] = '1';
            } else if (key === 'wizard_residencial_id') {
                if (String(value || '').trim() !== '') {
                    payload.residencial_id = value;
                }
            } else {
                payload[key] = value;
            }
        });
        if (!Object.prototype.hasOwnProperty.call(payload, 'is_active')) payload.is_active = '0';
        if (payload.action === 'create_user_with_assignment') {
            payload.es_principal = Object.prototype.hasOwnProperty.call(payload, 'assign_as_principal') ? '1' : '0';
        }
        delete payload.assign_to_service;
        delete payload.assign_as_principal;
        delete payload.wizard_residencial_id;

        try {
            const json = await api(payload);
            closeUserModal();
            await loadMeta();
            await loadUsers(true);
            await loadAssignments(true);
            showAlert('ok', json.message || 'Usuario creado correctamente.');
            window.SuperadminDashboard?.loadContext?.();
        } catch (err) {
            showInlineError(els.userModalError, err.message || 'No se pudo crear el usuario.');
        }
    });

    els.assignmentForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearInlineError(els.assignmentModalError);
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
            showInlineError(els.assignmentModalError, err.message || 'No se pudo crear la asignación.');
        }
    });

    els.usersWrap?.addEventListener('click', (e) => {
        const button = e.target.closest('[data-user-delete]');
        if (!button) return;
        openDeleteUserModal(button.getAttribute('data-user-delete') || '');
    });

    els.btnConfirmDeleteUser?.addEventListener('click', async () => {
        if (!state.deleteUser) return;
        try {
            await api({
                action: 'disable_user',
                csrf_token: csrf,
                user_id: state.deleteUser.id,
            });
            closeDeleteUserModal();
            await loadMeta();
            await loadUsers(false);
            await loadAssignments(false);
            showAlert('ok', 'Usuario desactivado correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo desactivar el usuario.');
        }
    });

    window.SuperadminUsuarios = {
        openUserModalForService,
        openAssignmentModalForService,
        refreshAll: async () => {
            await loadMeta();
            await loadUsers(false);
            await loadAssignments(false);
        },
    };

    Promise.all([loadMeta(), loadUsers(true), loadAssignments(true)]).catch((err) => showAlert('error', err.message || 'No se pudo cargar el módulo de usuarios.'));
})();
