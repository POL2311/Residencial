(function () {
  const root = document.getElementById('visitantesRapidosView');
  if (!root) return;

  const API = '/admin_residencial/php/api/visitantes_rapidos.php';
  const els = {
    list: document.getElementById('visitantesList'),
    alert: document.getElementById('visitantesAlert'),
    modal: document.getElementById('visitantesModal'),
    modalTitle: document.getElementById('visitantesModalTitle'),
    btnNew: document.getElementById('btnVisitanteNuevo'),
    btnClose: document.getElementById('btnVisitantesClose'),
    btnCancel: document.getElementById('btnVisitantesCancel'),
    form: document.getElementById('visitantesForm'),
    formError: document.getElementById('visitantesFormError'),
  };

  const state = { items: [], areas: [], responsables: [] };

  function escapeHtml(v = '') {
    return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'Error');
    return json;
  }

  function qrPreview(payload) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(payload)}`;
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

  function fillSelects(item) {
    const areaSel = els.form?.querySelector('[name="area_id"]');
    const respSel = els.form?.querySelector('[name="responsable_user_id"]');
    if (areaSel) {
      areaSel.innerHTML = `<option value="">Sin área</option>` + state.areas.map((area) => `<option value="${area.id}" ${String(item?.area_id || '') === String(area.id) ? 'selected' : ''}>${escapeHtml(area.nombre)}</option>`).join('');
    }
    if (respSel) {
      respSel.innerHTML = `<option value="">Sin responsable</option>` + state.responsables.map((user) => `<option value="${user.id}" ${String(item?.responsable_user_id || '') === String(user.id) ? 'selected' : ''}>${escapeHtml(user.name)}</option>`).join('');
    }
  }

  function render() {
    if (!state.items.length) {
      els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay accesos rápidos registrados.</div>`;
      return;
    }

    els.list.innerHTML = state.items.map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.nombre_visitante)}</h3>
              <span class="rounded-full px-2.5 py-1 text-xs ${item.estado === 'pendiente' ? 'bg-amber-100 text-amber-700' : item.estado === 'en_curso' ? 'bg-sky-100 text-sky-700' : item.estado === 'finalizado' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">${escapeHtml(item.estado)}</span>
            </div>
            <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.empresa || 'Sin empresa')} · Responsable: ${escapeHtml(item.responsable_nombre || 'Sin responsable')}</div>
            <div class="mt-1 text-sm text-slate-500">Área: <b>${escapeHtml(item.area_nombre || 'Sin área')}</b> · Placa: ${escapeHtml(item.placa_vehiculo || '—')}</div>
            <div class="mt-3 rounded-2xl bg-slate-50 p-3 text-sm text-slate-600">${escapeHtml(item.motivo)}</div>
          </div>
          <div class="grid gap-2 sm:grid-cols-2 lg:w-[340px]">
            <button type="button" class="js-edit rounded-xl border px-3 py-2 text-sm hover:bg-slate-50" data-id="${item.id}">Editar</button>
            <button type="button" class="js-cancel rounded-xl border px-3 py-2 text-sm hover:bg-slate-50" data-id="${item.id}">Cancelar</button>
          </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 rounded-2xl bg-slate-50 p-4 md:flex-row md:items-center md:justify-between">
          <div class="text-sm text-slate-700">
            <div class="font-medium">QR de visitante</div>
            <div class="text-xs text-slate-500 break-all">${escapeHtml(item.qr_payload)}</div>
            <div class="mt-1 text-xs text-slate-500">Vigencia: ${escapeHtml(item.fecha_desde)} → ${escapeHtml(item.fecha_hasta)}</div>
          </div>
          <div class="flex items-center gap-3">
            <img src="${qrPreview(item.qr_payload)}" alt="QR visitante" class="h-20 w-20 rounded-xl border bg-white object-contain p-1" loading="lazy" />
            <a href="${qrPreview(item.qr_payload)}" target="_blank" rel="noopener" class="rounded-xl bg-[#2E5D73] px-3 py-2 text-sm font-semibold text-white hover:opacity-95">Ver QR</a>
          </div>
        </div>
      </div>
    `).join('');

    els.list.querySelectorAll('.js-edit').forEach((btn) => {
      btn.addEventListener('click', () => openModal(state.items.find((item) => item.id === Number(btn.dataset.id)) || null));
    });
    els.list.querySelectorAll('.js-cancel').forEach((btn) => {
      btn.addEventListener('click', () => cancelItem(Number(btn.dataset.id)));
    });
  }

  function openModal(item) {
    els.form?.reset();
    if (els.modalTitle) els.modalTitle.textContent = item ? 'Editar acceso rápido' : 'Nuevo acceso rápido';
    if (els.form) {
      const idField = els.form.querySelector('[name="id"]');
      if (idField) idField.value = item?.id || '';
      els.form.nombre_visitante.value = item?.nombre_visitante || '';
      els.form.empresa.value = item?.empresa || '';
      els.form.placa_vehiculo.value = item?.placa_vehiculo || '';
      els.form.motivo.value = item?.motivo || '';
      els.form.notas_admin.value = item?.notas_admin || '';
      els.form.fecha_desde.value = item?.fecha_desde || new Date().toISOString().slice(0, 10);
      els.form.fecha_hasta.value = item?.fecha_hasta || new Date().toISOString().slice(0, 10);
      fillSelects(item);
    }
    els.formError?.classList.add('hidden');
    els.modal?.classList.remove('hidden');
    els.modal?.classList.add('flex');
  }

  function closeModal() {
    els.modal?.classList.add('hidden');
    els.modal?.classList.remove('flex');
  }

  async function loadData() {
    const [list, meta] = await Promise.all([
      fetchJSON(API),
      fetchJSON(`${API}?action=meta`),
    ]);
    state.items = list.data?.items || [];
    state.areas = meta.data?.areas || [];
    state.responsables = meta.data?.responsables || [];
    render();
  }

  async function submitForm(ev) {
    ev.preventDefault();
    try {
      const fd = new FormData(els.form);
      await fetchJSON(API, { method: 'POST', body: fd });
      closeModal();
      showAlert('Acceso rápido guardado correctamente.', 'success');
      await loadData();
    } catch (e) {
      if (els.formError) {
        els.formError.textContent = e.message || 'No se pudo guardar el acceso.';
        els.formError.classList.remove('hidden');
      }
    }
  }

  async function cancelItem(id) {
    if (!window.confirm('¿Cancelar este acceso rápido?')) return;
    const fd = new FormData();
    fd.append('action', 'cancel');
    fd.append('id', String(id));
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      showAlert('Acceso cancelado.', 'success');
      await loadData();
    } catch (e) {
      showAlert(e.message || 'No se pudo cancelar.', 'error');
    }
  }

  els.btnNew?.addEventListener('click', () => openModal(null));
  els.btnClose?.addEventListener('click', closeModal);
  els.btnCancel?.addEventListener('click', closeModal);
  els.modal?.addEventListener('click', (ev) => {
    if (ev.target === els.modal) closeModal();
  });
  els.form?.addEventListener('submit', submitForm);

  loadData().catch((e) => showAlert(e.message || 'No se pudieron cargar los accesos rápidos.', 'error'));
})();
