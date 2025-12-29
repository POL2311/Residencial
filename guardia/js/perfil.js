(function () {
  if (window.__guardia_perfil_init_v1) return;
  window.__guardia_perfil_init_v1 = true;

  /* ===============================
     Paths & helpers
  =============================== */
  function baseGuardiaPath() {
    const p = window.location.pathname;
    const idx = p.indexOf('/guardia/');
    return idx === -1 ? '/guardia/' : p.slice(0, idx) + '/guardia/';
  }

  const BASE = baseGuardiaPath();
  const API  = BASE + 'php/api/';

  const root = document.getElementById('perfilView');
  if (!root) return;

  const $ = (id) => document.getElementById(id);

  const els = {
    alert: $('perfilAlert'),

    v_name: $('v_name'),
    v_direccion: $('v_direccion'),
    v_telefono: $('v_telefono'),
    v_email: $('v_email'),

    modal: $('gModal'),
    modalTitle: $('gModalTitle'),
    modalBody: $('gModalBody'),
    modalClose: $('gModalClose'),
  };

  let state = {
    perfil: null,
  };

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  /* ===============================
     Alerts
  =============================== */
  function showAlert(type, msg) {
    if (!els.alert) return;
    els.alert.classList.remove('hidden');
    els.alert.textContent = msg;
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'ok'
        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
        : 'bg-rose-50 text-rose-800 border border-rose-200');
  }

  function hideAlert() {
    if (!els.alert) return;
    els.alert.classList.add('hidden');
  }

  /* ===============================
     Modal helpers
  =============================== */
  function openModal(title, bodyHtml) {
    els.modalTitle.textContent = title;
    els.modalBody.innerHTML = bodyHtml;
    els.modal.classList.remove('hidden');
  }

  function closeModal() {
    els.modal.classList.add('hidden');
    els.modalBody.innerHTML = '';
  }

  els.modalClose?.addEventListener('click', closeModal);
  els.modal?.addEventListener('click', (e) => {
    if (e.target === els.modal) closeModal();
  });

  /* ===============================
     Accordion logic (FIXED)
  =============================== */
  function setAccExpanded(keyToOpen) {
    document.querySelectorAll('[data-acc-btn]').forEach(btn => {
      const key = btn.getAttribute('data-acc-btn');
      const body = document.querySelector(`[data-acc-body="${key}"]`);
      const open = key === keyToOpen;

      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (body) body.classList.toggle('hidden', !open);
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

  /* ===============================
     API
  =============================== */
  async function apiPost(action, data = {}) {
    const fd = new FormData();
    fd.append('action', action);
    Object.keys(data).forEach(k => fd.append(k, data[k]));

    const res = await fetch(`${API}perfil.php`, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });

    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error');
    return json;
  }

  /* ===============================
     Load perfil
  =============================== */
  async function loadPerfil() {
    const json = await apiPost('get');
    state.perfil = json.data || {};

    if (els.v_name) {
      els.v_name.textContent = state.perfil.user?.name || '—';
    }
    if (els.v_email) {
      els.v_email.textContent = state.perfil.user?.email || '—';
    }
    if (els.v_telefono) {
      els.v_telefono.textContent = state.perfil.user?.telefono || '—';
    }
    if (els.v_direccion) {
      els.v_direccion.textContent = state.perfil.direccion || '—';
    }
  }

  /* ===============================
     Clicks → open modals
  =============================== */
  root.querySelectorAll('[data-open]').forEach(btn => {
    btn.addEventListener('click', async () => {
      hideAlert();
      const act = btn.getAttribute('data-open');

      try {
        if (act === 'update_name') {
          openModal('Actualizar nombre', `
            <div class="space-y-3">
              <input id="m_name"
                value="${escapeHtml(state.perfil?.user?.name || '')}"
                class="w-full rounded-xl border px-3 py-2 text-sm">
              <button id="m_save"
                class="w-full rounded-xl bg-[#2E5D73] text-white py-2">
                Guardar
              </button>
            </div>
          `);

          document.getElementById('m_save').onclick = async () => {
            const name = document.getElementById('m_name').value.trim();
            await apiPost('update_name', { name });
            closeModal();
            await loadPerfil();
            window.GuardiaDashboard?.loadContext();
            showAlert('ok', 'Nombre actualizado');
          };
        }

        if (act === 'update_email') {
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
              <button id="m_save"
                class="w-full rounded-xl bg-[#2E5D73] text-white py-2">
                Guardar
              </button>
              <div id="m_error" class="text-sm text-rose-600"></div>
            </div>
          `);

          document.getElementById('m_save').onclick = async () => {
            try {
              await apiPost('update_email', {
                email: document.getElementById('m_email').value.trim(),
                current_password: document.getElementById('m_pwd').value
              });
              closeModal();
              await loadPerfil();
              window.GuardiaDashboard?.loadContext();
              showAlert('ok', 'Correo actualizado');
            } catch (e) {
              document.getElementById('m_error').textContent = e.message;
            }
          };
        }

        if (act === 'change_password') {
          openModal('Cambiar contraseña', `
            <div class="space-y-3">
              <input id="m_cur" type="password"
                placeholder="Contraseña actual"
                class="w-full rounded-xl border px-3 py-2 text-sm">
              <input id="m_new" type="password"
                placeholder="Nueva contraseña"
                class="w-full rounded-xl border px-3 py-2 text-sm">
              <input id="m_new2" type="password"
                placeholder="Confirmar nueva contraseña"
                class="w-full rounded-xl border px-3 py-2 text-sm">
              <button id="m_save"
                class="w-full rounded-xl bg-[#2E5D73] text-white py-2">
                Guardar
              </button>
              <div id="m_error" class="text-sm text-rose-600"></div>
            </div>
          `);

          document.getElementById('m_save').onclick = async () => {
            try {
              await apiPost('change_password', {
                current_password: document.getElementById('m_cur').value,
                new_password: document.getElementById('m_new').value,
                new_password_confirm: document.getElementById('m_new2').value,
              });
              closeModal();
              showAlert('ok', 'Contraseña actualizada');
            } catch (e) {
              document.getElementById('m_error').textContent = e.message;
            }
          };
        }

      } catch (e) {
        showAlert('error', e.message);
      }
    });
  });

  /* ===============================
     Init
  =============================== */
  (async function init() {
    try {
      await loadPerfil();
    } catch (e) {
      showAlert('error', 'No se pudo cargar el perfil');
    }
  })();
})();
