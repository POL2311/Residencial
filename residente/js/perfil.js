(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }
    const BASE = baseResidentPath();
    const API = BASE + 'php/api/';

    function $(id) { return document.getElementById(id); }

    const root = $('perfilView');
    if (!root) return;
    if (root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const els = {
        alert: $('perfilAlert'),

        v_name: $('v_name'),
        v_direccion: $('v_direccion'),
        v_telefono: $('v_telefono'),
        v_email: $('v_email'),

        btnAddCE: $('btnAddCE'),
        ceNoTable: $('ceNoTable'),
        ceSql: $('ceSql'),
        ceEmpty: $('ceEmpty'),
        ceList: $('ceList'),

        btnAddAuto: $('btnAddAuto'),
        autosEmpty: $('autosEmpty'),
        autosList: $('autosList'),

        modal: $('modal'),
        modalTitle: $('modalTitle'),
        modalForm: $('modalForm'),
        modalAction: $('modalAction'),
        modalBody: $('modalBody'),
        btnCloseModal: $('btnCloseModal'),
        btnCancelModal: $('btnCancelModal'),
        btnSaveModal: $('btnSaveModal'),
    };

    let state = {
        perfil: null,
        contactos: [],
        autos: [],
    };

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.textContent = msg;

        els.alert.className = 'rounded-2xl px-4 py-3 text-sm';

        if (type === 'ok') {
            els.alert.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
        } else {
            els.alert.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
        }
    }

    function hideAlert() {
        if (!els.alert) return;
        els.alert.classList.add('hidden');
    }

    function openModal(title, action, bodyHtml, saveText = 'Guardar', danger = false) {
        els.modalTitle.textContent = title;
        els.modalAction.value = action;
        els.modalBody.innerHTML = bodyHtml;
        els.btnSaveModal.textContent = saveText;

        els.btnSaveModal.className = danger
            ? 'text-sm px-4 py-2 rounded-xl bg-rose-600 text-white hover:opacity-95'
            : 'text-sm px-4 py-2 rounded-xl bg-[#2E5D73] text-white hover:opacity-95';

        if (window.OSGateModal?.open) {
            window.OSGateModal.open(els.modal);
        } else {
            els.modal.classList.remove('hidden');
        }
    }

    function closeModal() {
        if (window.OSGateModal?.close) {
            window.OSGateModal.close(els.modal);
        } else {
            els.modal.classList.add('hidden');
        }
        els.modalBody.innerHTML = '';
        els.modalAction.value = '';
    }

    function rotateIcon(key, expanded) {
        const ico = document.querySelector(`[data-acc-ico="${key}"]`);
        if (!ico) return;
        ico.classList.toggle('rotate-90', expanded);
    }

    function setAccExpanded(keyToOpen) {
        const btns = document.querySelectorAll('[data-acc-btn]');
        btns.forEach(btn => {
            const key = btn.getAttribute('data-acc-btn');
            const body = document.querySelector(`[data-acc-body="${key}"]`);
            const open = key === keyToOpen;

            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (body) body.classList.toggle('hidden', !open);
            rotateIcon(key, open);
        });
    }

    setAccExpanded(null);

    document.querySelectorAll('[data-acc-btn]').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.getAttribute('data-acc-btn');
            const expanded = btn.getAttribute('aria-expanded') === 'true';
            setAccExpanded(expanded ? null : key);
        });
    });

    async function apiPost(url, data) {
        const fd = new FormData();
        Object.keys(data || {}).forEach(k => fd.append(k, data[k]));
        const res = await fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        });
        const json = await res.json().catch(() => null);
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    // ✅ AHORA refresca TODO el header (incluye autos)
    async function refreshHeader() {
        try {
            if (window.ResidenteDashboard && typeof window.ResidenteDashboard.loadContext === 'function') {
                await window.ResidenteDashboard.loadContext();
                return;
            }
        } catch { /* noop */ }

        try {
            const res = await fetch(`${API}contexto.php`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            const json = await res.json();
            if (!json.ok) return;

            const data = json.data || {};
            const nameEl = document.getElementById('residentName');
            const addrEl = document.getElementById('residentAddress');

            if (nameEl) nameEl.textContent = data.user?.name || 'Residente';
            if (addrEl) addrEl.textContent = data.direccion || '—';
        } catch { /* silencioso */ }
    }

    function renderContactos() {
        els.ceList.innerHTML = '';
        const hasCE = !!state.perfil?.hasCE;

        if (!hasCE) {
            els.ceNoTable.classList.remove('hidden');
            els.ceEmpty.classList.add('hidden');
            if (els.ceSql) els.ceSql.textContent = state.perfil?.ceSql || '';
            return;
        }

        els.ceNoTable.classList.add('hidden');

        const items = state.contactos || [];
        if (!items.length) {
            els.ceEmpty.classList.remove('hidden');
            return;
        }
        els.ceEmpty.classList.add('hidden');

        items.forEach(c => {
            const div = document.createElement('div');
            div.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 flex items-center justify-between gap-3';
            div.innerHTML = `
        <div class="min-w-0">
          <div class="text-sm font-semibold text-slate-800 truncate">${escapeHtml(c.nombre || 'Contacto')}</div>
          <div class="text-xs text-slate-500">
            ${escapeHtml(c.telefono || '')}${c.relacion ? ' · ' + escapeHtml(c.relacion) : ''}
          </div>
        </div>
        <div class="shrink-0 flex items-center gap-2">
          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
            data-ce-edit="${c.id}">Actualizar</button>

          <button type="button"
            class="h-9 w-9 rounded-full bg-white border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center"
            title="Eliminar"
            data-ce-del="${c.id}">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
              <path d="M9 3h6m-8 4h10m-9 0l1 14h6l1-14M10 11v6m4-6v6"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      `;
            els.ceList.appendChild(div);
        });

        els.ceList.querySelectorAll('[data-ce-edit]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-ce-edit'));
                const c = (state.contactos || []).find(x => Number(x.id) === id);
                if (!c) return;

                openModal(
                    'Actualizar contacto',
                    'update_ce',
                    `
            <input type="hidden" name="ce_id" value="${escapeHtml(String(c.id))}">
            <div>
              <label class="block text-xs text-slate-600 mb-1">Nombre</label>
              <input name="ce_nombre" value="${escapeHtml(c.nombre || '')}"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
            </div>
            <div>
              <label class="block text-xs text-slate-600 mb-1">Teléfono</label>
              <input name="ce_telefono" value="${escapeHtml(c.telefono || '')}"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
            </div>
            <div>
              <label class="block text-xs text-slate-600 mb-1">Relación (opcional)</label>
              <input name="ce_relacion" value="${escapeHtml(c.relacion || '')}"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
            </div>
          `
                );
            });
        });

        els.ceList.querySelectorAll('[data-ce-del]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-ce-del'));
                const c = (state.contactos || []).find(x => Number(x.id) === id);
                if (!c) return;

                openModal(
                    'Eliminar contacto',
                    'delete_ce',
                    `
            <input type="hidden" name="ce_id" value="${escapeHtml(String(c.id))}">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
              ¿Seguro que deseas eliminar a <b>${escapeHtml(c.nombre || 'este contacto')}</b>?
            </div>
          `,
                    'Eliminar',
                    true
                );
            });
        });
    }

    function renderAutos() {
        els.autosList.innerHTML = '';

        const items = state.autos || [];
        if (!items.length) {
            els.autosEmpty.classList.remove('hidden');
            return;
        }
        els.autosEmpty.classList.add('hidden');

        items.forEach(a => {
            const div = document.createElement('div');
            div.className = 'rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 flex items-center justify-between gap-3';
            div.innerHTML = `
        <div class="min-w-0">
          <div class="text-sm font-semibold text-slate-800 truncate">${escapeHtml(a.placas || '—')}</div>
          <div class="text-xs text-slate-500">
            ${escapeHtml(a.modelo || '—')} · ${escapeHtml(a.color || '—')}
          </div>
        </div>

        <div class="shrink-0 flex items-center gap-2">
          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
            data-auto-edit="${a.id}">Actualizar</button>

          <button type="button"
            class="h-9 w-9 rounded-full bg-white border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center"
            title="Eliminar"
            data-auto-del="${a.id}">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
              <path d="M9 3h6m-8 4h10m-9 0l1 14h6l1-14M10 11v6m4-6v6"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      `;
            els.autosList.appendChild(div);
        });

        els.autosList.querySelectorAll('[data-auto-edit]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-auto-edit'));
                const a = (state.autos || []).find(x => Number(x.id) === id);
                if (!a) return;

                openModal(
                    'Actualizar automóvil',
                    'auto_update',
                    `
            <input type="hidden" name="auto_id" value="${escapeHtml(String(a.id))}">
            <div>
              <label class="block text-xs text-slate-600 mb-1">Placas *</label>
              <input name="placas" value="${escapeHtml(a.placas || '')}"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
            </div>
            <div>
              <label class="block text-xs text-slate-600 mb-1">Modelo</label>
              <input name="modelo" value="${escapeHtml(a.modelo || '')}"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
            </div>
            <div>
              <label class="block text-xs text-slate-600 mb-1">Color</label>
              <input name="color" value="${escapeHtml(a.color || '')}"
                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
            </div>
          `
                );
            });
        });

        els.autosList.querySelectorAll('[data-auto-del]').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = Number(btn.getAttribute('data-auto-del'));
                const a = (state.autos || []).find(x => Number(x.id) === id);
                if (!a) return;

                openModal(
                    'Eliminar automóvil',
                    'auto_delete',
                    `
            <input type="hidden" name="auto_id" value="${escapeHtml(String(a.id))}">
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
              ¿Seguro que deseas eliminar el auto con placas <b>${escapeHtml(a.placas || '—')}</b>?
            </div>
          `,
                    'Eliminar',
                    true
                );
            });
        });
    }

    async function loadPerfil() {
        const json = await apiPost(`${API}perfil.php`, { action: 'get' });
        state.perfil = json.data || {};
        state.contactos = state.perfil.contactos || [];

        els.v_name.textContent = state.perfil.user?.name || '—';
        els.v_telefono.textContent = state.perfil.user?.telefono || '—';
        els.v_email.textContent = state.perfil.user?.email || '—';
        els.v_direccion.textContent = state.perfil.direccion || '—';

        renderContactos();
    }

    async function loadAutos() {
        const json = await apiPost(`${API}autos.php`, { action: 'list' });
        state.autos = json.data?.autos || [];
        renderAutos();
    }

    root.querySelectorAll('[data-open]').forEach(btn => {
        btn.addEventListener('click', () => {
            const act = btn.getAttribute('data-open');

            if (act === 'update_name') {
                openModal('Actualizar nombre', 'update_name', `
          <div>
            <label class="block text-xs text-slate-600 mb-1">Nombre</label>
            <input name="name" value="${escapeHtml(state.perfil?.user?.name || '')}"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
        `);
            }

            if (act === 'update_phone') {
                openModal('Actualizar teléfono', 'update_phone', `
          <div>
            <label class="block text-xs text-slate-600 mb-1">Teléfono</label>
            <input name="telefono" value="${escapeHtml(state.perfil?.user?.telefono || '')}"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Contraseña actual</label>
            <input type="password" name="current_password"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
        `);
            }

            if (act === 'update_email') {
                openModal('Actualizar correo', 'update_email', `
          <div>
            <label class="block text-xs text-slate-600 mb-1">Correo</label>
            <input type="email" name="email" value="${escapeHtml(state.perfil?.user?.email || '')}"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Contraseña actual</label>
            <input type="password" name="current_password"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
        `);
            }

            if (act === 'change_password') {
                openModal('Cambiar contraseña', 'change_password', `
          <div>
            <label class="block text-xs text-slate-600 mb-1">Contraseña actual</label>
            <input type="password" name="current_password"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Nueva contraseña</label>
            <input type="password" name="new_password"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Confirmar nueva contraseña</label>
            <input type="password" name="new_password_confirm"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
          </div>
        `);
            }
        });
    });

    els.btnAddCE?.addEventListener('click', () => {
        openModal('Agregar contacto', 'add_ce', `
      <div>
        <label class="block text-xs text-slate-600 mb-1">Nombre</label>
        <input name="ce_nombre"
          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Teléfono</label>
        <input name="ce_telefono"
          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Relación (opcional)</label>
        <input name="ce_relacion"
          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
      </div>
    `);
    });

    els.btnAddAuto?.addEventListener('click', () => {
        openModal('Agregar automóvil', 'auto_create', `
      <div>
        <label class="block text-xs text-slate-600 mb-1">Placas *</label>
        <input name="placas"
          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Modelo</label>
        <input name="modelo"
          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
      </div>
      <div>
        <label class="block text-xs text-slate-600 mb-1">Color</label>
        <input name="color"
          class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
      </div>
    `);
    });

    els.btnCloseModal.addEventListener('click', closeModal);
    els.btnCancelModal.addEventListener('click', closeModal);
    els.modal.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });

    els.modalForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();

        const action = els.modalAction.value;
        const fd = new FormData(els.modalForm);

        try {
            if (['update_name', 'update_phone', 'update_email', 'change_password', 'add_ce', 'update_ce', 'delete_ce'].includes(action)) {
                fd.set('action', action);
                const json = await fetch(`${API}perfil.php`, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json' }
                }).then(r => r.json());

                if (!json.ok) throw new Error(json.error || 'Error');
                showAlert('ok', json.message || 'Actualizado ✅');

                closeModal();
                await loadPerfil();
                await refreshHeader();
                return;
            }

            if (action === 'auto_create') {
                await apiPost(`${API}autos.php`, {
                    action: 'create',
                    placas: fd.get('placas') || '',
                    modelo: fd.get('modelo') || '',
                    color: fd.get('color') || '',
                });
                showAlert('ok', 'Auto agregado ✅');
                closeModal();
                await loadAutos();
                await refreshHeader();
                return;
            }

            if (action === 'auto_update') {
                await apiPost(`${API}autos.php`, {
                    action: 'update',
                    auto_id: fd.get('auto_id') || '',
                    placas: fd.get('placas') || '',
                    modelo: fd.get('modelo') || '',
                    color: fd.get('color') || '',
                });
                showAlert('ok', 'Auto actualizado ✅');
                closeModal();
                await loadAutos();
                await refreshHeader();
                return;
            }

            if (action === 'auto_delete') {
                await apiPost(`${API}autos.php`, { action: 'delete', auto_id: fd.get('auto_id') || '' });
                showAlert('ok', 'Auto eliminado ✅');
                closeModal();
                await loadAutos();
                await refreshHeader();
                return;
            }

            throw new Error('Acción no soportada');
        } catch (err) {
            // ✅ Si el error viene del modal de placas (crear/editar), cerramos para que se vea el alert
            if (action === 'auto_create' || action === 'auto_update') {
                closeModal();
            }
            showAlert('error', err.message || 'Error');
        }
    });

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    (async function init() {
        try {
            await loadPerfil();
            await loadAutos();
        } catch (e) {
            showAlert('error', e.message || 'No se pudo cargar perfil.');
        }
    })();
})();
