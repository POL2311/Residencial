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
        <div class="space-y-3">
          <input id="m_name"
            value="${escapeHtml(state.perfil?.user?.name || '')}"
            class="w-full rounded-xl border px-3 py-2 text-sm">
          <button id="m_save" class="w-full rounded-xl bg-[#2E5D73] text-white py-2">
            Guardar
          </button>
          <div id="m_error" class="text-sm text-rose-600"></div>
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
          if (errorEl) errorEl.textContent = e.message || 'No se pudo actualizar el nombre.';
        }
      });
    }

    function openEmailModal() {
      openModal('Actualizar correo', `
        <div class="space-y-3">
          <input id="m_email"
            type="email"
            value="${escapeHtml(state.perfil?.user?.email || '')}"
            class="w-full rounded-xl border px-3 py-2 text-sm">
          <input id="m_pwd"
            type="password"
            placeholder="Contraseña actual"
            class="w-full rounded-xl border px-3 py-2 text-sm">
          <button id="m_save" class="w-full rounded-xl bg-[#2E5D73] text-white py-2">
            Guardar
          </button>
          <div id="m_error" class="text-sm text-rose-600"></div>
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
          if (errorEl) errorEl.textContent = e.message || 'No se pudo actualizar el correo.';
        }
      });
    }

    function openPhoneModal() {
      openModal('Actualizar teléfono', `
        <div class="space-y-3">
          <input id="m_phone"
            value="${escapeHtml(state.perfil?.user?.telefono || '')}"
            class="w-full rounded-xl border px-3 py-2 text-sm"
            placeholder="Teléfono">
          <input id="m_pwd"
            type="password"
            placeholder="Contraseña actual"
            class="w-full rounded-xl border px-3 py-2 text-sm">
          <button id="m_save" class="w-full rounded-xl bg-[#2E5D73] text-white py-2">
            Guardar
          </button>
          <div id="m_error" class="text-sm text-rose-600"></div>
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
          if (errorEl) errorEl.textContent = e.message || 'No se pudo actualizar el teléfono.';
        }
      });
    }

    function openPasswordModal() {
      openModal('Cambiar contraseña', `
        <div class="space-y-3">
          <input id="m_cur" type="password" placeholder="Contraseña actual" class="w-full rounded-xl border px-3 py-2 text-sm">
          <input id="m_new" type="password" placeholder="Nueva contraseña" class="w-full rounded-xl border px-3 py-2 text-sm">
          <input id="m_new2" type="password" placeholder="Confirmar nueva contraseña" class="w-full rounded-xl border px-3 py-2 text-sm">
          <button id="m_save" class="w-full rounded-xl bg-[#2E5D73] text-white py-2">
            Guardar
          </button>
          <div id="m_error" class="text-sm text-rose-600"></div>
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
          if (errorEl) errorEl.textContent = e.message || 'No se pudo actualizar la contraseña.';
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
