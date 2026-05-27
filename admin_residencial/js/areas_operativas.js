(function () {
  const root = document.getElementById('areasOperativasView');
  if (!root || root.dataset.bound === '1') return;
  root.dataset.bound = '1';

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/areas_operativas.php';
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_opts, task) => task());

  const els = {
    alert: document.getElementById('areasOperativasAlert'),
    list: document.getElementById('areasOperativasList'),
    empty: document.getElementById('areasOperativasEmpty'),
    search: document.getElementById('areaOperativaSearch'),
    btnRefresh: document.getElementById('btnRefreshAreasOperativas'),
    btnNew: document.getElementById('btnNuevaAreaOperativa'),
    modal: document.getElementById('areaOperativaModal'),
    modalTitle: document.getElementById('areaOperativaModalTitle'),
    form: document.getElementById('areaOperativaForm'),
    formError: document.getElementById('areaOperativaFormError'),
    btnClose: document.getElementById('btnCloseAreaOperativaModal'),
    btnCancel: document.getElementById('btnCancelAreaOperativa'),
  };

  const state = {
    items: [],
    filtered: [],
    editingId: null,
  };

  function escapeHtml(value = '') {
    return String(value)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function currentMode() {
    const mode = String(window.AdminResidencialDashboard?.getOperationalMode?.() || '').trim();
    const preset = String(window.AdminResidencialDashboard?.getServiceProfile?.()?.preset_servicio || '').trim();
    return mode && mode !== 'residencial' ? mode : preset || mode || 'residencial';
  }

  function showAlert(message, type = 'success') {
    if (window.AppToast?.show) {
      window.AppToast.show({ type, message });
      return;
    }
    if (!els.alert) return;
    els.alert.textContent = message;
    els.alert.className = 'rounded-2xl px-4 py-3 text-sm ' + (type === 'error'
      ? 'border border-rose-200 bg-rose-50 text-rose-700'
      : 'border border-emerald-200 bg-emerald-50 text-emerald-700');
    els.alert.classList.remove('hidden');
  }

  function showFormError(message = '') {
    if (!els.formError) return;
    if (!message) {
      els.formError.classList.add('hidden');
      els.formError.textContent = '';
      return;
    }
    els.formError.textContent = message;
    els.formError.classList.remove('hidden');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', ...(options.headers || {}) },
      ...options,
    });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || json.ok === false) {
      throw new Error(json?.error || 'No se pudo completar la solicitud.');
    }
    return json;
  }

  function formatType(type = '') {
    return String(type || 'general')
      .replaceAll('_', ' ')
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function filterItems() {
    const q = String(els.search?.value || '').trim().toLowerCase();
    state.filtered = q
      ? state.items.filter((item) => [item.nombre, item.codigo, item.tipo, item.descripcion].some((value) => String(value || '').toLowerCase().includes(q)))
      : [...state.items];
  }

  function render() {
    filterItems();
    if (!els.list || !els.empty) return;

    els.empty.classList.toggle('hidden', state.filtered.length > 0);
    if (!state.filtered.length) {
      els.list.innerHTML = '';
      return;
    }

    els.list.innerHTML = state.filtered.map((item) => {
      const active = Number(item.activo || 0) === 1;
      return `
        <article class="rounded-[1.5rem] border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-base font-semibold text-slate-800">${escapeHtml(item.nombre || 'Área')}</h2>
                <span class="rounded-full border px-2.5 py-1 text-[11px] ${active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-500'}">
                  ${active ? 'Activa' : 'Inactiva'}
                </span>
              </div>
              <div class="mt-1 text-sm text-slate-500">
                ${escapeHtml(item.codigo || 'Sin código')} · ${escapeHtml(formatType(item.tipo))}
              </div>
            </div>
          </div>
          ${item.descripcion ? `<p class="mt-3 line-clamp-3 text-sm leading-6 text-slate-600">${escapeHtml(item.descripcion)}</p>` : ''}
          <div class="mt-4 flex flex-wrap justify-end gap-2">
            <button type="button" data-area-edit="${escapeHtml(item.id)}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Editar</button>
            <button type="button" data-area-toggle="${escapeHtml(item.id)}" data-active="${active ? '0' : '1'}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">
              ${active ? 'Desactivar' : 'Activar'}
            </button>
          </div>
        </article>
      `;
    }).join('');
  }

  function openModal(item = null) {
    if (!els.modal || !els.form) return;
    state.editingId = item ? Number(item.id || 0) : null;
    showFormError('');
    els.form.reset();
    if (els.modalTitle) els.modalTitle.textContent = item ? 'Editar área' : 'Nueva área';
    els.form.elements.id.value = item?.id || '';
    els.form.elements.nombre.value = item?.nombre || '';
    els.form.elements.codigo.value = item?.codigo || '';
    els.form.elements.tipo.value = item?.tipo || 'general';
    els.form.elements.descripcion.value = item?.descripcion || '';
    els.form.elements.activo.checked = Number(item?.activo ?? 1) === 1;
    if (window.OSGateModal?.open) {
      window.OSGateModal.open(els.modal);
    } else {
      els.modal.classList.remove('hidden');
      els.modal.classList.add('flex');
    }
    document.body.style.overflow = 'hidden';
    window.AdminResidencialDashboard?.syncOverlayState?.();
  }

  function closeModal() {
    if (window.OSGateModal?.close && els.modal) {
      window.OSGateModal.close(els.modal);
    } else {
      els.modal?.classList.add('hidden');
      els.modal?.classList.remove('flex');
    }
    document.body.style.overflow = '';
    state.editingId = null;
    window.AdminResidencialDashboard?.syncOverlayState?.();
  }

  async function loadAreas() {
    const json = await fetchJSON(API);
    state.items = json.data?.items || [];
    render();
  }

  async function saveArea(event) {
    event.preventDefault();
    if (!els.form) return;
    showFormError('');
    const submitBtn = event.submitter || els.form.querySelector('button[type="submit"]');
    await withPendingAction({ button: submitBtn, scope: els.form, label: 'Guardando...' }, async () => {
      const fd = new FormData(els.form);
      fd.set('action', 'save');
      fd.set('activo', els.form.elements.activo.checked ? '1' : '0');
      try {
        const json = await fetchJSON(API, { method: 'POST', body: fd });
        closeModal();
        await loadAreas();
        showAlert(json.message || 'Área guardada.');
      } catch (err) {
        showFormError(err.message || 'No se pudo guardar el área.');
      }
    });
  }

  async function toggleArea(button) {
    const fd = new FormData();
    fd.set('action', 'toggle');
    fd.set('id', button.getAttribute('data-area-toggle') || '');
    fd.set('activo', button.getAttribute('data-active') || '0');
    try {
      const json = await fetchJSON(API, { method: 'POST', body: fd });
      await loadAreas();
      showAlert(json.message || 'Área actualizada.');
    } catch (err) {
      showAlert(err.message || 'No se pudo actualizar el área.', 'error');
    }
  }

  els.btnNew?.addEventListener('click', () => openModal());
  els.btnRefresh?.addEventListener('click', () => loadAreas().then(() => showAlert('Áreas actualizadas.')).catch((err) => showAlert(err.message || 'No se pudo actualizar.', 'error')));
  els.btnClose?.addEventListener('click', closeModal);
  els.btnCancel?.addEventListener('click', closeModal);
  els.modal?.addEventListener('click', (event) => {
    if (event.target === els.modal) closeModal();
  });
  els.form?.addEventListener('submit', saveArea);
  els.search?.addEventListener('input', render);
  els.list?.addEventListener('click', (event) => {
    const edit = event.target.closest('[data-area-edit]');
    const toggle = event.target.closest('[data-area-toggle]');
    if (edit) {
      const id = Number(edit.getAttribute('data-area-edit') || 0);
      const item = state.items.find((row) => Number(row.id) === id);
      if (item) openModal(item);
      return;
    }
    if (toggle) {
      toggleArea(toggle);
    }
  });

  window.OSGateLabels?.apply?.(root, currentMode());
  loadAreas().catch((err) => showAlert(err.message || 'No se pudieron cargar las áreas.', 'error'));
})();
