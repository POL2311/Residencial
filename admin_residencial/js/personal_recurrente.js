(function () {
  const root = document.getElementById('personalRecurrenteView');
  if (!root) return;

  const API = '/admin_residencial/php/api/personal_recurrente.php';
  const META_API = '/admin_residencial/php/api/areas_operativas.php';

  const els = {
    list: document.getElementById('personalList'),
    alert: document.getElementById('personalAlert'),
    search: document.getElementById('personalSearch'),
    btnRefresh: document.getElementById('btnPersonalRefresh'),
    btnNuevo: document.getElementById('btnPersonalNuevo'),
    modal: document.getElementById('personalModal'),
    modalTitle: document.getElementById('personalModalTitle'),
    btnClose: document.getElementById('btnPersonalClose'),
    btnCancel: document.getElementById('btnPersonalCancel'),
    form: document.getElementById('personalForm'),
    formError: document.getElementById('personalFormError'),
  };

  const state = { items: [], areas: [] };
  const syncDashboardOverlayState = () => window.AdminResidencialDashboard?.syncOverlayState?.();
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_options, task) => task());

  function escapeHtml(v = '') {
    return String(v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...rest, headers: { Accept: 'application/json', ...headers } });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'Error');
    return json;
  }

  function showAlert(msg = '', type = 'info') {
    if (!els.alert) return;
    if (!msg) {
      els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
      els.alert.textContent = '';
      return;
    }
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');
  }

  function renderAreas(selected = '') {
    const sel = els.form?.querySelector('[name="area_id"]');
    if (!sel) return;
    sel.innerHTML =
      `<option value="">Sin área</option>` +
      state.areas.map((area) => `
        <option value="${area.id}" ${String(area.id) === String(selected) ? 'selected' : ''}>
          ${escapeHtml(area.nombre)}
        </option>
      `).join('');
  }

  function qrPreview(payload) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(payload)}`;
  }

  function ensureQrModal() {
    let modal = document.getElementById('personalQrModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'personalQrModal';
    modal.className = 'fixed inset-0 z-[9999] hidden items-center justify-center bg-black/60 p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">QR personal</div>
            <h3 id="personalQrTitle" class="mt-1 text-xl font-semibold text-slate-900">Personal recurrente</h3>
          </div>
          <button type="button" id="personalQrClose" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-700 hover:bg-slate-200">×</button>
        </div>
        <div class="px-5 py-6 text-center">
          <div class="mx-auto inline-flex rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
            <img id="personalQrImage" src="" alt="QR personal" class="h-72 w-72 max-w-full rounded-2xl object-contain" />
          </div>
          <div id="personalQrPayload" class="mt-4 break-all rounded-2xl bg-slate-50 px-4 py-3 text-left text-xs text-slate-500"></div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);

    const close = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
      syncDashboardOverlayState();
    };

    modal.querySelector('#personalQrClose')?.addEventListener('click', close);
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) close();
    });
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    return modal;
  }

  function openQrModal(item) {
    if (!item?.qr_payload) return;
    const modal = ensureQrModal();
    const title = modal.querySelector('#personalQrTitle');
    const image = modal.querySelector('#personalQrImage');
    const payload = modal.querySelector('#personalQrPayload');

    if (title) title.textContent = item.nombre || 'Personal recurrente';
    if (image) {
      image.src = qrPreview(item.qr_payload);
      image.alt = `QR ${item.nombre || 'personal'}`;
    }
    if (payload) payload.textContent = item.qr_payload;

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    syncDashboardOverlayState();
  }

  function ensureDetailsModal() {
    let modal = document.getElementById('personalDetailsModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'personalDetailsModal';
    modal.className = 'fixed inset-0 z-[9998] hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="w-full max-w-2xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Detalle del personal</div>
            <h3 id="personalDetailsTitle" class="mt-1 text-xl font-semibold text-slate-900">Personal recurrente</h3>
          </div>
          <button type="button" id="personalDetailsClose" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-700 hover:bg-slate-200">×</button>
        </div>
        <div id="personalDetailsBody" class="max-h-[75vh] overflow-y-auto px-5 py-5"></div>
      </div>
    `;
    document.body.appendChild(modal);

    const close = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
      syncDashboardOverlayState();
    };

    modal.querySelector('#personalDetailsClose')?.addEventListener('click', close);
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) close();
    });
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });

    return modal;
  }

  function closeDetailsModal() {
    const modal = document.getElementById('personalDetailsModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  function openDetailsModal(item) {
    if (!item) return;
    const modal = ensureDetailsModal();
    modal.querySelector('#personalDetailsTitle').textContent = item.nombre || 'Personal recurrente';
    modal.querySelector('#personalDetailsBody').innerHTML = `
      <div class="space-y-4 text-sm text-slate-700">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Empresa</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.empresa || 'Sin empresa')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Puesto</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.puesto || 'Sin puesto')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Área</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.area_nombre || 'Sin área')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Teléfono</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.telefono || 'Sin teléfono')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Estado</div><div class="mt-1 font-semibold text-slate-900">${item.activo ? 'Activo' : 'Inactivo'}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Presencia</div><div class="mt-1 font-semibold text-slate-900">${item.esta_dentro ? 'Dentro' : 'Fuera'}</div></div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Última entrada</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.ultima_entrada_at || '—')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Última salida</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.ultima_salida_at || '—')}</div></div>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
          <div class="text-xs uppercase tracking-wide text-slate-400">Notas</div>
          <div class="mt-2 whitespace-pre-wrap text-slate-800">${escapeHtml(item.notas || 'Sin notas')}</div>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
          <div class="text-xs uppercase tracking-wide text-slate-400">QR personal</div>
          <div class="mt-2 break-all text-xs text-slate-500">${escapeHtml(item.qr_payload || 'Sin QR generado')}</div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <button type="button" data-detail-edit="${item.id}" class="rounded-xl border border-slate-200 px-4 py-3 font-medium text-slate-700 hover:bg-slate-50">Editar</button>
          <button type="button" data-detail-qr="${item.id}" class="rounded-xl bg-[#2E5D73] px-4 py-3 font-semibold text-white hover:opacity-95">Ver QR</button>
          <button type="button" data-detail-reset="${item.id}" class="rounded-xl border border-slate-200 px-4 py-3 font-medium text-slate-700 hover:bg-slate-50">Resetear PIN</button>
          <button type="button" data-detail-regenerate="${item.id}" class="rounded-xl border border-slate-200 px-4 py-3 font-medium text-slate-700 hover:bg-slate-50">Regenerar QR</button>
          <button type="button" data-detail-delete="${item.id}" class="rounded-xl bg-rose-600 px-4 py-3 font-semibold text-white hover:bg-rose-700 sm:col-span-2 lg:col-span-1">Eliminar</button>
        </div>
      </div>
    `;

    modal.querySelector('[data-detail-edit]')?.addEventListener('click', () => {
      closeDetailsModal();
      openModal(item);
    });
    modal.querySelector('[data-detail-qr]')?.addEventListener('click', () => openQrModal(item));
    modal.querySelector('[data-detail-reset]')?.addEventListener('click', () => resetPin(item.id));
    modal.querySelector('[data-detail-regenerate]')?.addEventListener('click', () => regenerateQr(item.id));
    modal.querySelector('[data-detail-delete]')?.addEventListener('click', async () => {
      closeDetailsModal();
      await removeItem(item.id);
    });

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    syncDashboardOverlayState();
  }

  function render() {
    const q = String(els.search?.value || '').trim().toLowerCase();
    const items = state.items.filter((item) => {
      if (!q) return true;
      return [item.nombre, item.empresa, item.telefono, item.area_nombre].some((value) =>
        String(value || '').toLowerCase().includes(q)
      );
    });

    if (!items.length) {
      els.list.innerHTML = `
        <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">
          No hay personal recurrente registrado todavía.
        </div>
      `;
      return;
    }

    els.list.innerHTML = items.map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="flex gap-4">
            <div class="h-16 w-16 shrink-0 overflow-hidden rounded-2xl bg-slate-100">
              ${item.foto_url ? `<img src="${escapeHtml(item.foto_url)}" alt="${escapeHtml(item.nombre)}" class="h-full w-full object-cover" loading="lazy" />` : `<div class="flex h-full items-center justify-center text-xs text-slate-400">Sin foto</div>`}
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.nombre)}</h3>
                <span class="rounded-full px-2.5 py-1 text-xs ${item.activo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">${item.activo ? 'Activo' : 'Inactivo'}</span>
                <span class="rounded-full px-2.5 py-1 text-xs ${item.esta_dentro ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-600'}">${item.esta_dentro ? 'Dentro' : 'Fuera'}</span>
              </div>
              <div class="mt-1 text-sm text-slate-600">
                ${escapeHtml(item.empresa || 'Sin empresa')} · ${escapeHtml(item.puesto || 'Sin puesto')}
              </div>
              <div class="mt-1 text-sm text-slate-500">
                Área: <b>${escapeHtml(item.area_nombre || 'Sin área')}</b> · ${escapeHtml(item.telefono || 'Sin teléfono')}
              </div>
            </div>
          </div>
          <div class="lg:w-[180px]">
            <button type="button" class="js-more rounded-xl border px-3 py-2 text-sm hover:bg-slate-50" data-id="${item.id}">Ver más</button>
          </div>
        </div>
      </div>
    `).join('');

    els.list.querySelectorAll('.js-more').forEach((btn) => {
      btn.addEventListener('click', () => openDetailsModal(state.items.find((item) => item.id === Number(btn.dataset.id)) || null));
    });
  }

  function openModal(item) {
    els.form?.reset();
    els.formError?.classList.add('hidden');
    if (els.modalTitle) els.modalTitle.textContent = item ? 'Editar personal' : 'Nuevo personal';
    if (els.form) {
      const idField = els.form.querySelector('[name="id"]');
      if (idField) idField.value = item?.id || '';
      els.form.nombre.value = item?.nombre || '';
      els.form.telefono.value = item?.telefono || '';
      els.form.empresa.value = item?.empresa || '';
      els.form.puesto.value = item?.puesto || '';
      els.form.notas.value = item?.notas || '';
      els.form.pin.value = '';
      els.form.activo.checked = item ? Boolean(Number(item.activo)) : true;
      renderAreas(item?.area_id || '');
    }
    els.modal?.classList.remove('hidden');
    els.modal?.classList.add('flex');
    syncDashboardOverlayState();
  }

  function closeModal() {
    els.modal?.classList.add('hidden');
    els.modal?.classList.remove('flex');
    syncDashboardOverlayState();
  }

  async function loadMeta() {
    const [personal, meta] = await Promise.all([
      fetchJSON(API),
      fetchJSON(`${API}?action=meta`),
    ]);
    state.items = personal.data?.items || [];
    state.areas = meta.data?.areas || [];
    renderAreas();
    render();
  }

  async function saveForm(ev) {
    ev.preventDefault();
    const submitBtn = ev.submitter || els.form?.querySelector('button[type="submit"]');
    await withPendingAction({
      button: submitBtn,
      scope: els.form,
      label: 'Guardando...',
      lock: [els.btnCancel, els.btnClose].filter(Boolean),
    }, async () => {
      try {
        const fd = new FormData(els.form);
        fd.set('activo', els.form.activo.checked ? '1' : '0');
        await fetchJSON(API, { method: 'POST', body: fd });
        closeModal();
        showAlert('Personal guardado correctamente.', 'success');
        await loadMeta();
      } catch (e) {
        if (els.formError) {
          els.formError.textContent = e.message || 'No se pudo guardar la información.';
          els.formError.classList.remove('hidden');
        }
      }
    });
  }

  async function removeItem(id) {
    if (!window.confirm('¿Eliminar este registro de personal recurrente?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', String(id));
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      showAlert('Personal eliminado.', 'success');
      await loadMeta();
    } catch (e) {
      showAlert(e.message || 'No se pudo eliminar.', 'error');
    }
  }

  async function resetPin(id) {
    const pin = window.prompt('Escribe el nuevo PIN numérico (4 a 8 dígitos).', '');
    if (pin === null) return;
    const fd = new FormData();
    fd.append('action', 'reset_pin');
    fd.append('id', String(id));
    fd.append('pin', pin);
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      showAlert('PIN actualizado.', 'success');
    } catch (e) {
      showAlert(e.message || 'No se pudo actualizar el PIN.', 'error');
    }
  }

  async function regenerateQr(id) {
    const fd = new FormData();
    fd.append('action', 'regenerate_qr');
    fd.append('id', String(id));
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      showAlert('QR regenerado.', 'success');
      await loadMeta();
    } catch (e) {
      showAlert(e.message || 'No se pudo regenerar el QR.', 'error');
    }
  }

  els.btnNuevo?.addEventListener('click', () => openModal(null));
  els.btnClose?.addEventListener('click', closeModal);
  els.btnCancel?.addEventListener('click', closeModal);
  els.btnRefresh?.addEventListener('click', loadMeta);
  els.search?.addEventListener('input', render);
  els.form?.addEventListener('submit', saveForm);
  els.modal?.addEventListener('click', (ev) => {
    if (ev.target === els.modal) closeModal();
  });

  loadMeta().catch((e) => showAlert(e.message || 'No se pudo cargar el personal.', 'error'));
})();
