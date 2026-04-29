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

  function escapeHtml(v = '') {
    return String(v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options });
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
              <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-500">
                <span>Última entrada: ${escapeHtml(item.ultima_entrada_at || '—')}</span>
                <span>Última salida: ${escapeHtml(item.ultima_salida_at || '—')}</span>
              </div>
            </div>
          </div>
          <div class="grid gap-2 sm:grid-cols-2 lg:w-[340px]">
            <button type="button" class="js-edit rounded-xl border px-3 py-2 text-sm hover:bg-slate-50" data-id="${item.id}">Editar</button>
            <button type="button" class="js-reset-pin rounded-xl border px-3 py-2 text-sm hover:bg-slate-50" data-id="${item.id}">Resetear PIN</button>
            <button type="button" class="js-regenerate rounded-xl border px-3 py-2 text-sm hover:bg-slate-50" data-id="${item.id}">Regenerar QR</button>
            <button type="button" class="js-delete rounded-xl bg-rose-600 px-3 py-2 text-sm text-white hover:bg-rose-700" data-id="${item.id}">Eliminar</button>
          </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 rounded-2xl bg-slate-50 p-4 md:flex-row md:items-center md:justify-between">
          <div class="text-sm text-slate-700">
            <div class="font-medium">QR personal</div>
            <div class="text-xs text-slate-500 break-all">${escapeHtml(item.qr_payload)}</div>
          </div>
          <div class="flex items-center gap-3">
            <img src="${qrPreview(item.qr_payload)}" alt="QR ${escapeHtml(item.nombre)}" class="h-20 w-20 rounded-xl border bg-white object-contain p-1" loading="lazy" />
            <a href="${qrPreview(item.qr_payload)}" target="_blank" rel="noopener" class="rounded-xl bg-[#2E5D73] px-3 py-2 text-sm font-semibold text-white hover:opacity-95">Ver QR</a>
          </div>
        </div>
      </div>
    `).join('');

    els.list.querySelectorAll('.js-edit').forEach((btn) => {
      btn.addEventListener('click', () => openModal(state.items.find((item) => item.id === Number(btn.dataset.id)) || null));
    });
    els.list.querySelectorAll('.js-delete').forEach((btn) => {
      btn.addEventListener('click', () => removeItem(Number(btn.dataset.id)));
    });
    els.list.querySelectorAll('.js-reset-pin').forEach((btn) => {
      btn.addEventListener('click', () => resetPin(Number(btn.dataset.id)));
    });
    els.list.querySelectorAll('.js-regenerate').forEach((btn) => {
      btn.addEventListener('click', () => regenerateQr(Number(btn.dataset.id)));
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
  }

  function closeModal() {
    els.modal?.classList.add('hidden');
    els.modal?.classList.remove('flex');
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
