(function () {
  const root = document.getElementById('adminAutosView');
  if (!root || root.dataset.bound === '1') return;
  root.dataset.bound = '1';

  const API_BASE = (window.AdminResidencialDashboard?.API || '/admin_residencial/php/api/');
  const API_AUTOS = API_BASE + 'autos_admin.php';
  const API_ACCESS = API_BASE + 'accesos_residentes.php';

  const els = {
    alert: document.getElementById('adminAutosAlert'),
    btnRefresh: document.getElementById('btnRefreshAdminAutos'),
    list: document.getElementById('adminAutosList'),
    accessType: document.getElementById('residentAccessTypeFilter'),
    accessList: document.getElementById('residentAccessList'),
    modal: document.getElementById('adminAutoEditModal'),
    modalTitle: document.getElementById('adminAutoEditModalTitle'),
    modalBody: document.getElementById('adminAutoEditBody'),
    form: document.getElementById('adminAutoEditForm'),
    btnCloseModal: document.getElementById('btnCloseAdminAutoModal'),
    btnCancelModal: document.getElementById('btnCancelAdminAutoModal'),
  };

  const state = {
    autos: [],
    accesses: [],
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
    const normalized = type === 'ok' ? 'success' : type;
    if (window.AppToast?.show) {
      window.AppToast.show({ type: normalized, message: msg });
      return;
    }
    if (!els.alert) return;
    els.alert.classList.remove('hidden');
    els.alert.className = `rounded-2xl px-4 py-3 text-sm ${normalized === 'success' ? 'border border-emerald-200 bg-emerald-50 text-emerald-800' : 'border border-rose-200 bg-rose-50 text-rose-800'}`;
    els.alert.textContent = msg;
  }

  async function fetchJson(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      cache: 'no-store',
      ...options,
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || !json.ok) {
      throw new Error(json?.error || 'No pudimos completar la operación.');
    }
    return json;
  }

  function normalizePlacas(value) {
    return String(value || '').trim().toUpperCase();
  }

  function closeModal() {
    els.modal?.classList.add('hidden');
    els.form?.reset();
  }

  function openEditModal(auto) {
    els.modalTitle.textContent = `Editar auto: ${auto.placas || 'Sin placas'}`;
    els.modalBody.innerHTML = `
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="auto_id" value="${escapeHtml(auto.auto_id)}">
      <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs text-slate-600">Placas *</label>
          <input name="placas" required maxlength="15" value="${escapeHtml(auto.placas || '')}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-600">Tag ID</label>
          <input name="tag_id" maxlength="120" value="${escapeHtml(auto.tag_id || '')}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-600">Modelo</label>
          <input name="modelo" maxlength="80" value="${escapeHtml(auto.modelo || '')}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="mb-1 block text-xs text-slate-600">Color</label>
          <input name="color" maxlength="40" value="${escapeHtml(auto.color || '')}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
        </div>
      </div>
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
        <div><span class="text-slate-500">Residente:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.user_name || 'Sin residente')}</span></div>
        <div class="mt-1"><span class="text-slate-500">Unidad:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.unidad_clave || 'Sin unidad')}</span></div>
      </div>
    `;
    els.modal.classList.remove('hidden');
  }

  function renderAutos() {
    if (!els.list) return;
    if (!state.autos.length) {
      els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Aún no hay autos registrados en este residencial.</div>`;
      return;
    }

    els.list.innerHTML = state.autos.map((auto) => `
      <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="text-lg font-semibold text-slate-800">${escapeHtml(auto.placas || 'Sin placas')}</div>
            <div class="mt-1 text-sm text-slate-500">${escapeHtml(auto.user_name || 'Sin residente')} · ${escapeHtml(auto.unidad_clave || 'Sin unidad')}</div>
            <div class="mt-4 grid grid-cols-1 gap-2 text-sm text-slate-600 md:grid-cols-2">
              <div><span class="text-slate-500">Modelo:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.modelo || 'Sin modelo')}</span></div>
              <div><span class="text-slate-500">Color:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.color || 'Sin color')}</span></div>
              <div><span class="text-slate-500">Tag:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.tag_id || 'Sin tag')}</span></div>
              <div><span class="text-slate-500">Creado:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.created_at || '—')}</span></div>
            </div>
          </div>
          <div class="flex gap-2">
            <button type="button" data-edit-auto="${escapeHtml(auto.auto_id)}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Editar</button>
          </div>
        </div>
      </article>
    `).join('');

    els.list.querySelectorAll('[data-edit-auto]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const auto = state.autos.find((item) => String(item.auto_id) === String(btn.dataset.editAuto));
        if (auto) openEditModal(auto);
      });
    });
  }

  function renderAccesses() {
    if (!els.accessList) return;
    if (!state.accesses.length) {
      els.accessList.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Todavía no hay lecturas cargadas para este residencial.</div>`;
      return;
    }

    els.accessList.innerHTML = state.accesses.map((item) => `
      <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-base font-semibold text-slate-800">${escapeHtml(item.placas || 'Sin placas')}</div>
              <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${item.tipo_movimiento === 'ingreso' ? 'border border-emerald-200 bg-emerald-50 text-emerald-700' : 'border border-amber-200 bg-amber-50 text-amber-700'}">${item.tipo_movimiento === 'ingreso' ? 'Ingreso' : 'Egreso'}</span>
            </div>
            <div class="mt-1 text-sm text-slate-500">${escapeHtml(item.residente_nombre || 'Sin residente')} · ${escapeHtml(item.unidad_clave || 'Sin unidad')}</div>
            <div class="mt-4 grid grid-cols-1 gap-2 text-sm text-slate-600 md:grid-cols-2">
              <div><span class="text-slate-500">Tag:</span> <span class="font-medium text-slate-800">${escapeHtml(item.tag_id || 'Sin tag')}</span></div>
              <div><span class="text-slate-500">Fecha:</span> <span class="font-medium text-slate-800">${escapeHtml(item.fecha_hora || '—')}</span></div>
              <div><span class="text-slate-500">Fuente:</span> <span class="font-medium text-slate-800">${escapeHtml(item.fuente || 'lector_tag')}</span></div>
              <div><span class="text-slate-500">Check lector:</span> <span class="font-medium text-slate-800">${escapeHtml(item.lector_check_id || '—')}</span></div>
            </div>
          </div>
        </div>
      </article>
    `).join('');
  }

  async function loadAutos() {
    const json = await fetchJson(`${API_AUTOS}?action=list_all`);
    state.autos = Array.isArray(json.data?.autos) ? json.data.autos : [];
    renderAutos();
  }

  async function loadAccesses() {
    const url = new URL(API_ACCESS, window.location.origin);
    url.searchParams.set('action', 'list');
    if (els.accessType?.value) {
      url.searchParams.set('tipo_movimiento', els.accessType.value);
    }
    const json = await fetchJson(url.toString());
    state.accesses = Array.isArray(json.data?.items) ? json.data.items : [];
    renderAccesses();
  }

  async function loadAll(showSuccess = false) {
    await Promise.all([loadAutos(), loadAccesses()]);
    if (showSuccess) {
      showAlert('ok', 'Autos y accesos actualizados.');
    }
  }

  els.btnRefresh?.addEventListener('click', () => {
    loadAll(true).catch((err) => showAlert('error', err.message || 'No se pudo refrescar la vista.'));
  });

  els.accessType?.addEventListener('change', () => {
    loadAccesses().catch((err) => showAlert('error', err.message || 'No se pudieron cargar los accesos.'));
  });

  els.btnCloseModal?.addEventListener('click', closeModal);
  els.btnCancelModal?.addEventListener('click', closeModal);
  els.modal?.addEventListener('click', (e) => {
    if (e.target === els.modal) closeModal();
  });

  els.form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(els.form);
    const placas = normalizePlacas(formData.get('placas'));
    const modelo = String(formData.get('modelo') || '').trim();
    const color = String(formData.get('color') || '').trim();
    const tagId = String(formData.get('tag_id') || '').trim();

    formData.set('placas', placas);

    if (placas.length < 5 || placas.length > 15) {
      showAlert('error', 'Las placas deben tener entre 5 y 15 caracteres.');
      return;
    }
    if (modelo.length > 80) {
      showAlert('error', 'El modelo no puede exceder 80 caracteres.');
      return;
    }
    if (color.length > 40) {
      showAlert('error', 'El color no puede exceder 40 caracteres.');
      return;
    }
    if (tagId.length > 120) {
      showAlert('error', 'El Tag ID no puede exceder 120 caracteres.');
      return;
    }

    try {
      await fetchJson(API_AUTOS, {
        method: 'POST',
        body: formData,
      });
      closeModal();
      await loadAutos();
      showAlert('ok', 'Auto actualizado correctamente.');
    } catch (err) {
      showAlert('error', err.message || 'No se pudo actualizar el auto.');
    }
  });

  loadAll().catch((err) => showAlert('error', err.message || 'No se pudo cargar la vista de autos.'));
})();
