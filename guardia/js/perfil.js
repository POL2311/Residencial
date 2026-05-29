(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.perfil = function ({ API, fetchJSON, openModal, closeModal }) {
    const root = document.getElementById('perfilView');
    if (!root) return;

    const els = {
      alert: document.getElementById('perfilAlert'),
      vName: document.getElementById('v_name'),
      vEmail: document.getElementById('v_email'),
      vTelefono: document.getElementById('v_telefono'),
      accordionButtons: root.querySelectorAll('[data-acc-btn]'),
      actionButtons: root.querySelectorAll('[data-open]'),
    };

    const state = {
      destroyed: false,
      perfil: null,
    };

    function isAlive() {
      return !state.destroyed;
    }

    function showAlert(type, message) {
      if (!els.alert || !isAlive()) return;

      els.alert.classList.remove('hidden');
      els.alert.textContent = message;
      els.alert.className =
        'rounded-2xl px-4 py-3 text-sm ' +
        (type === 'ok'
          ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
          : 'bg-rose-50 text-rose-800 border border-rose-200');
    }

    function hideAlert() {
      if (!els.alert || !isAlive()) return;
      els.alert.classList.add('hidden');
      els.alert.textContent = '';
    }

    function escapeHtml(value) {
      return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    async function apiPost(action, data = {}) {
      const fd = new FormData();
      fd.set('action', action);

      Object.entries(data).forEach(([key, value]) => {
        fd.set(key, String(value ?? ''));
      });

      return fetchJSON(`${API}perfil.php`, {
        method: 'POST',
        body: fd,
      });
    }

    function setExpanded(keyToOpen) {
      if (!isAlive()) return;

      els.accordionButtons.forEach((btn) => {
        const key = btn.getAttribute('data-acc-btn');
        const body = root.querySelector(`[data-acc-body="${key}"]`);
        const open = key === keyToOpen;

        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        body?.classList.toggle('hidden', !open);
      });
    }

    function renderPerfil() {
      if (!isAlive()) return;

      const user = state.perfil?.user || {};
      if (els.vName) els.vName.textContent = user.name || '—';
      if (els.vEmail) els.vEmail.textContent = user.email || '—';
      if (els.vTelefono) els.vTelefono.textContent = user.telefono || '—';
    }

    async function loadPerfil() {
      const json = await apiPost('get');
      if (!isAlive()) return;

      state.perfil = json.data || {};
      renderPerfil();
    }

    function attachModalSave(handler) {
      const saveBtn = document.getElementById('m_save');
      saveBtn?.addEventListener('click', handler, { once: true });
    }

    function openNameModal() {
      openModal('Actualizar nombre', `
        <div class="flex min-h-0 flex-1 flex-col">
          <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            ${'<div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Nombre</label></div><input id="m_name" value="' + escapeHtml(state.perfil?.user?.name || '') + '" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15">'}
            <div id="m_error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
          </div>
          <div class="flex shrink-0 items-center gap-2 border-t border-slate-100 bg-white px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <button type="button" onclick="closeModal()" class="min-h-11 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancelar</button>
            <button type="button" id="m_save" class="min-h-11 flex-1 rounded-xl bg-[#4E7287] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">Guardar</button>
          </div>
        </div>
      `);

      attachModalSave(async () => {
        try {
          const name = document.getElementById('m_name')?.value?.trim() || '';
          await apiPost('update_name', { name });
          closeModal();
          await loadPerfil();
          window.GuardiaDashboard?.loadContext?.();
          showAlert('ok', 'Nombre actualizado.');
        } catch (e) {
          const errorEl = document.getElementById('m_error');
          if (errorEl) { errorEl.textContent = e.message || 'No se pudo actualizar el nombre.'; errorEl.classList.remove('hidden'); }
        }
      });
    }

    function openEmailModal() {
      openModal('Actualizar correo', `
        <div class="flex min-h-0 flex-1 flex-col">
          <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            ${'<div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Nuevo correo</label></div><input id="m_email" type="email" value="' + escapeHtml(state.perfil?.user?.email || '') + '" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15 mb-3"><div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Contraseña actual</label></div><input id="m_pwd" type="password" placeholder="Requerida para confirmar" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15">'}
            <div id="m_error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
          </div>
          <div class="flex shrink-0 items-center gap-2 border-t border-slate-100 bg-white px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <button type="button" onclick="closeModal()" class="min-h-11 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancelar</button>
            <button type="button" id="m_save" class="min-h-11 flex-1 rounded-xl bg-[#4E7287] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">Guardar</button>
          </div>
        </div>
      `);

      attachModalSave(async () => {
        try {
          const email = document.getElementById('m_email')?.value?.trim() || '';
          const currentPassword = document.getElementById('m_pwd')?.value || '';
          await apiPost('update_email', {
            email,
            current_password: currentPassword,
          });
          closeModal();
          await loadPerfil();
          window.GuardiaDashboard?.loadContext?.();
          showAlert('ok', 'Correo actualizado.');
        } catch (e) {
          const errorEl = document.getElementById('m_error');
          if (errorEl) { errorEl.textContent = e.message || 'No se pudo actualizar el correo.'; errorEl.classList.remove('hidden'); }
        }
      });
    }

    function openPhoneModal() {
      openModal('Actualizar teléfono', `
        <div class="flex min-h-0 flex-1 flex-col">
          <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            ${'<div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Nuevo teléfono</label></div><input id="m_phone" value="' + escapeHtml(state.perfil?.user?.telefono || '') + '" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15 mb-3" placeholder="10 dígitos"><div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Contraseña actual</label></div><input id="m_pwd" type="password" placeholder="Requerida para confirmar" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15">'}
            <div id="m_error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
          </div>
          <div class="flex shrink-0 items-center gap-2 border-t border-slate-100 bg-white px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <button type="button" onclick="closeModal()" class="min-h-11 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancelar</button>
            <button type="button" id="m_save" class="min-h-11 flex-1 rounded-xl bg-[#4E7287] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">Guardar</button>
          </div>
        </div>
      `);

      attachModalSave(async () => {
        try {
          const telefono = document.getElementById('m_phone')?.value?.trim() || '';
          const currentPassword = document.getElementById('m_pwd')?.value || '';
          await apiPost('update_phone', {
            telefono,
            current_password: currentPassword,
          });
          closeModal();
          await loadPerfil();
          window.GuardiaDashboard?.loadContext?.();
          showAlert('ok', 'Teléfono actualizado.');
        } catch (e) {
          const errorEl = document.getElementById('m_error');
          if (errorEl) { errorEl.textContent = e.message || 'No se pudo actualizar el teléfono.'; errorEl.classList.remove('hidden'); }
        }
      });
    }

    function openPasswordModal() {
      openModal('Cambiar contraseña', `
        <div class="flex min-h-0 flex-1 flex-col">
          <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            ${'<div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Contraseña actual</label></div><input id="m_cur" type="password" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15 mb-3"><div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Nueva contraseña</label></div><input id="m_new" type="password" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15 mb-3"><div class="mb-1 flex items-center justify-between"><label class="text-xs font-medium text-slate-600">Confirmar nueva contraseña</label></div><input id="m_new2" type="password" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition focus:border-[#4E7287] focus:ring-2 focus:ring-[#4E7287]/15">'}
            <div id="m_error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
          </div>
          <div class="flex shrink-0 items-center gap-2 border-t border-slate-100 bg-white px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <button type="button" onclick="closeModal()" class="min-h-11 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancelar</button>
            <button type="button" id="m_save" class="min-h-11 flex-1 rounded-xl bg-[#4E7287] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">Guardar</button>
          </div>
        </div>
      `);

      attachModalSave(async () => {
        try {
          await apiPost('change_password', {
            current_password: document.getElementById('m_cur')?.value || '',
            new_password: document.getElementById('m_new')?.value || '',
            new_password_confirm: document.getElementById('m_new2')?.value || '',
          });
          closeModal();
          showAlert('ok', 'Contraseña actualizada.');
        } catch (e) {
          const errorEl = document.getElementById('m_error');
          if (errorEl) { errorEl.textContent = e.message || 'No se pudo actualizar la contraseña.'; errorEl.classList.remove('hidden'); }
        }
      });
    }

    function onAccordionClick(ev) {
      const btn = ev.currentTarget;
      const key = btn.getAttribute('data-acc-btn');
      const expanded = btn.getAttribute('aria-expanded') === 'true';
      setExpanded(expanded ? null : key);
    }

    function onActionClick(ev) {
      hideAlert();
      const action = ev.currentTarget.getAttribute('data-open');

      if (action === 'update_name') return openNameModal();
      if (action === 'update_email') return openEmailModal();
      if (action === 'update_phone') return openPhoneModal();
      if (action === 'change_password') return openPasswordModal();
    }

    function bindEvents() {
      els.accordionButtons.forEach((btn) => btn.addEventListener('click', onAccordionClick));
      els.actionButtons.forEach((btn) => btn.addEventListener('click', onActionClick));
    }

    function unbindEvents() {
      els.accordionButtons.forEach((btn) => btn.removeEventListener('click', onAccordionClick));
      els.actionButtons.forEach((btn) => btn.removeEventListener('click', onActionClick));
    }

    setExpanded(null);
    bindEvents();

    loadPerfil().catch(() => {
      if (isAlive()) showAlert('error', 'No se pudo cargar el perfil.');
    });

    return {
      unmount() {
        state.destroyed = true;
        unbindEvents();
      }
    };
  };
})();
