(function () {
  if (window.__admin_perfil_init) return;
  window.__admin_perfil_init = true;

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const BASE = basePath();
  const API  = BASE + 'php/api/';

  const $ = id => document.getElementById(id);
  const root = $('perfilView');
  if (!root) return;

  const els = {
    alert: $('perfilAlert'),
    v_res_nombre: $('v_res_nombre'),
    v_res_ubicacion: $('v_res_ubicacion'),
    v_stat_residentes: $('v_stat_residentes'),
    v_stat_guardias: $('v_stat_guardias'),
    userForm: $('perfilUserForm'),
    userName: $('perfilUserName'),
    userEmail: $('perfilUserEmail'),
    userTelefono: $('perfilUserTelefono'),
    userPassword: $('perfilUserPassword'),
    userPasswordConfirm: $('perfilUserPasswordConfirm'),
    userSaveBtn: $('perfilUserSaveBtn'),
  };

  function showError(msg) {
    els.alert.textContent = msg;
    els.alert.className = 'rounded-2xl px-4 py-3 text-sm bg-rose-50 border border-rose-200 text-rose-800';
    els.alert.classList.remove('hidden');
  }

  function showSuccess(msg) {
    els.alert.textContent = msg;
    els.alert.className = 'rounded-2xl px-4 py-3 text-sm bg-emerald-50 border border-emerald-200 text-emerald-800';
    els.alert.classList.remove('hidden');
  }

  function rotate(key, open) {
    const ico = document.querySelector(`[data-acc-ico="${key}"]`);
    ico?.classList.toggle('rotate-90', open);
  }

  function setAcc(key) {
    document.querySelectorAll('[data-acc-btn]').forEach(btn => {
      const k = btn.getAttribute('data-acc-btn');
      const body = document.querySelector(`[data-acc-body="${k}"]`);
      const open = k === key;
      body?.classList.toggle('hidden', !open);
      rotate(k, open);
    });
  }

  document.querySelectorAll('[data-acc-btn]').forEach(btn => {
    btn.addEventListener('click', () => {
      const k = btn.getAttribute('data-acc-btn');
      const body = document.querySelector(`[data-acc-body="${k}"]`);
      setAcc(body?.classList.contains('hidden') ? k : null);
    });
  });

  async function apiPost(data) {
    const fd = new FormData();
    Object.keys(data).forEach(k => fd.append(k, data[k]));
    const res = await fetch(API + 'perfil.php', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error');
    return json.data;
  }

  async function loadPerfil() {
    const data = await apiPost({ action: 'get' });

    els.v_res_nombre.textContent = data.residencial.nombre;
    els.v_res_ubicacion.textContent =
      `${data.residencial.ciudad}, ${data.residencial.estado}`;

    els.v_stat_residentes.textContent = data.stats.residentes;
    els.v_stat_guardias.textContent = data.stats.guardias;

    if (els.userName) els.userName.value = data.user?.name || '';
    if (els.userEmail) els.userEmail.value = data.user?.email || '';
    if (els.userTelefono) els.userTelefono.value = data.user?.telefono || '';
  }

  async function saveUserPerfil(ev) {
    ev.preventDefault();
    const name = (els.userName?.value || '').trim();
    const email = (els.userEmail?.value || '').trim();
    const telefono = (els.userTelefono?.value || '').trim();
    const password = (els.userPassword?.value || '').trim();
    const passwordConfirm = (els.userPasswordConfirm?.value || '').trim();

    if (!name) throw new Error('El nombre es obligatorio.');
    if (!email) throw new Error('El email es obligatorio.');
    if (password || passwordConfirm) {
      if (password.length < 8) throw new Error('La contraseña debe tener al menos 8 caracteres.');
      if (password !== passwordConfirm) throw new Error('La confirmación de contraseña no coincide.');
    }

    await apiPost({
      action: 'update_user',
      name,
      email,
      telefono,
      password,
      password_confirm: passwordConfirm,
    });

    if (els.userPassword) els.userPassword.value = '';
    if (els.userPasswordConfirm) els.userPasswordConfirm.value = '';
    showSuccess('Perfil actualizado correctamente.');
  }

  (async function init() {
    try {
      await loadPerfil();
      els.userForm?.addEventListener('submit', async (ev) => {
        try {
          const withPendingAction = window.AdminResidencialDashboard?.withPendingAction;
          if (typeof withPendingAction === 'function') {
            await withPendingAction({ button: els.userSaveBtn, scope: els.userForm, label: 'Guardando...' }, async () => {
              await saveUserPerfil(ev);
            });
          } else {
            await saveUserPerfil(ev);
          }
        } catch (e) {
          showError(e.message || 'No se pudo actualizar tu perfil.');
        }
      });
    } catch (e) {
      showError(e.message);
    }
  })();
})();
