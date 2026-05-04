(function () {
  const root = document.getElementById('materialesOperativosView');
  if (!root) return;

  const API_CATALOGO = '/admin_residencial/php/api/materiales_catalogo.php';
  const API_PERMISOS = '/admin_residencial/php/api/permisos_materiales.php';

  const els = {
    catalogoList: document.getElementById('catalogoList'),
    permisosList: document.getElementById('permisosList'),
    alert: document.getElementById('materialesAlert'),
    btnCatalogoNew: document.getElementById('btnMaterialCatalogoNuevo'),
    btnPermisoNew: document.getElementById('btnPermisoNuevo'),
    catalogoModal: document.getElementById('catalogoModal'),
    catalogoModalTitle: document.getElementById('catalogoModalTitle'),
    btnCatalogoClose: document.getElementById('btnCatalogoClose'),
    btnCatalogoCancel: document.getElementById('btnCatalogoCancel'),
    catalogoForm: document.getElementById('catalogoForm'),
    catalogoError: document.getElementById('catalogoError'),
    permisoModal: document.getElementById('permisoModal'),
    permisoModalTitle: document.getElementById('permisoModalTitle'),
    btnPermisoClose: document.getElementById('btnPermisoClose'),
    btnPermisoCancel: document.getElementById('btnPermisoCancel'),
    btnAddPermisoItem: document.getElementById('btnAddPermisoItem'),
    permisoForm: document.getElementById('permisoForm'),
    permisoError: document.getElementById('permisoError'),
    permisoItems: document.getElementById('permisoItems'),
  };

  const state = { catalogo: [], permisos: [], areas: [], responsables: [] };

  function escapeHtml(v = '') {
    return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...rest, headers: { Accept: 'application/json', ...headers } });
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

  function fillOperationalSelects(item) {
    const areaSel = els.permisoForm?.querySelector('[name="area_id"]');
    const respSel = els.permisoForm?.querySelector('[name="responsable_user_id"]');
    if (areaSel) {
      areaSel.innerHTML = `<option value="">Sin área</option>` + state.areas.map((area) => `<option value="${area.id}" ${String(item?.area_id || '') === String(area.id) ? 'selected' : ''}>${escapeHtml(area.nombre)}</option>`).join('');
    }
    if (respSel) {
      respSel.innerHTML = `<option value="">Sin responsable</option>` + state.responsables.map((user) => `<option value="${user.id}" ${String(item?.responsable_user_id || '') === String(user.id) ? 'selected' : ''}>${escapeHtml(user.name)}</option>`).join('');
    }
  }

  function newItemRow(item = {}) {
    return `
      <div class="permiso-item rounded-2xl border border-slate-200 bg-white p-3">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-[1.2fr_1fr_auto]">
          <div>
            <label class="mb-1 block text-xs text-slate-600">Material del catálogo</label>
            <select class="permiso-material-id w-full rounded-xl border px-3 py-2 text-sm">
              <option value="">Otro / escribir manualmente</option>
              ${state.catalogo.map((material) => `<option value="${material.id}" ${String(item.material_id || '') === String(material.id) ? 'selected' : ''}>${escapeHtml(material.nombre)}</option>`).join('')}
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Cantidad / detalle</label>
            <input type="text" class="permiso-cantidad w-full rounded-xl border px-3 py-2 text-sm" value="${escapeHtml(item.cantidad_texto || '')}" placeholder="Ej. 2 cajas / 1 compresor" />
          </div>
          <div class="flex items-end">
            <button type="button" class="permiso-remove rounded-xl border px-3 py-2 text-sm hover:bg-slate-50">Quitar</button>
          </div>
        </div>
        <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
          <div>
            <label class="mb-1 block text-xs text-slate-600">Nombre manual</label>
            <input type="text" class="permiso-material-nombre w-full rounded-xl border px-3 py-2 text-sm" value="${escapeHtml(item.material_nombre || '')}" placeholder="Escribe si no está en el catálogo" />
          </div>
          <label class="flex items-center gap-2 text-sm text-slate-700 md:pb-2">
            <input type="checkbox" class="permiso-agregar-catalogo" ${item.agregar_a_catalogo ? 'checked' : ''} />
            Agregar al catálogo
          </label>
        </div>
      </div>
    `;
  }

  function collectItems() {
    return Array.from(els.permisoItems?.querySelectorAll('.permiso-item') || []).map((row) => ({
      material_id: row.querySelector('.permiso-material-id')?.value || '',
      material_nombre: row.querySelector('.permiso-material-nombre')?.value || '',
      cantidad_texto: row.querySelector('.permiso-cantidad')?.value || '',
      agregar_a_catalogo: row.querySelector('.permiso-agregar-catalogo')?.checked ? 1 : 0,
    }));
  }

  function bindItemRowEvents(container = els.permisoItems) {
    container?.querySelectorAll('.permiso-remove').forEach((btn) => {
      btn.onclick = () => btn.closest('.permiso-item')?.remove();
    });
  }

  function renderCatalogo() {
    if (!state.catalogo.length) {
      els.catalogoList.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Todavía no hay materiales en el catálogo.</div>`;
      return;
    }

    els.catalogoList.innerHTML = state.catalogo.map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-semibold text-slate-800">${escapeHtml(item.nombre)}</div>
            <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.categoria || 'Sin categoría')}</div>
            <div class="mt-1 text-sm text-slate-500">${escapeHtml(item.descripcion || 'Sin descripción')}</div>
          </div>
          <div class="flex flex-col gap-2">
            <button type="button" class="js-cat-edit rounded-xl border px-3 py-2 text-xs hover:bg-white" data-id="${item.id}">Editar</button>
            <button type="button" class="js-cat-delete rounded-xl bg-rose-600 px-3 py-2 text-xs text-white hover:bg-rose-700" data-id="${item.id}">Eliminar</button>
          </div>
        </div>
      </div>
    `).join('');

    els.catalogoList.querySelectorAll('.js-cat-edit').forEach((btn) => {
      btn.addEventListener('click', () => openCatalogoModal(state.catalogo.find((item) => item.id === Number(btn.dataset.id)) || null));
    });
    els.catalogoList.querySelectorAll('.js-cat-delete').forEach((btn) => {
      btn.addEventListener('click', () => deleteCatalogo(Number(btn.dataset.id)));
    });
  }

  function renderPermisos() {
    if (!state.permisos.length) {
      els.permisosList.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Todavía no hay permisos registrados.</div>`;
      return;
    }

    els.permisosList.innerHTML = state.permisos.map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.tipo_movimiento === 'salida' ? 'Salida autorizada' : 'Entrada autorizada')}</h3>
              <span class="rounded-full px-2.5 py-1 text-xs ${item.estado === 'pendiente' ? 'bg-amber-100 text-amber-700' : item.estado === 'aprobado' ? 'bg-emerald-100 text-emerald-700' : item.estado === 'en_proceso' ? 'bg-sky-100 text-sky-700' : item.estado === 'usado' ? 'bg-slate-200 text-slate-700' : 'bg-rose-100 text-rose-700'}">${escapeHtml(item.estado)}</span>
            </div>
            <div class="mt-1 text-sm text-slate-600">Responsable: ${escapeHtml(item.responsable_nombre || 'Sin responsable')} · Área: ${escapeHtml(item.area_nombre || 'Sin área')}</div>
            <div class="mt-3 space-y-2">
              ${(item.items || []).map((material) => `
                <div class="rounded-xl bg-white px-3 py-2 text-sm text-slate-700">
                  ${escapeHtml(material.material_nombre)} <span class="text-slate-400">·</span> ${escapeHtml(material.cantidad_texto)}
                </div>
              `).join('')}
            </div>
          </div>
          <div class="grid gap-2 sm:grid-cols-2 lg:w-[340px]">
            <button type="button" class="js-perm-edit rounded-xl border px-3 py-2 text-sm hover:bg-white" data-id="${item.id}">Editar</button>
            <button type="button" class="js-perm-approve rounded-xl border px-3 py-2 text-sm hover:bg-white" data-id="${item.id}">Aprobar</button>
            <button type="button" class="js-perm-cancel rounded-xl border px-3 py-2 text-sm hover:bg-white" data-id="${item.id}">Cancelar</button>
            <button type="button" class="js-perm-delete rounded-xl bg-rose-600 px-3 py-2 text-sm text-white hover:bg-rose-700" data-id="${item.id}">Eliminar</button>
          </div>
        </div>
        <div class="mt-4 flex flex-col gap-3 rounded-2xl bg-white p-4 md:flex-row md:items-center md:justify-between">
          <div class="text-sm text-slate-700">
            <div class="font-medium">QR del permiso</div>
            <div class="text-xs text-slate-500 break-all">${escapeHtml(item.qr_payload)}</div>
            <div class="mt-1 text-xs text-slate-500">Aprobó: ${escapeHtml(item.aprobado_por_nombre || 'Pendiente')} · ${escapeHtml(item.aprobado_at || '—')}</div>
          </div>
          <div class="flex items-center gap-3">
            <img src="${qrPreview(item.qr_payload)}" alt="QR permiso" class="h-20 w-20 rounded-xl border bg-white object-contain p-1" loading="lazy" />
            <a href="${qrPreview(item.qr_payload)}" target="_blank" rel="noopener" class="rounded-xl bg-[#2E5D73] px-3 py-2 text-sm font-semibold text-white hover:opacity-95">Ver QR</a>
          </div>
        </div>
      </div>
    `).join('');

    els.permisosList.querySelectorAll('.js-perm-edit').forEach((btn) => btn.addEventListener('click', () => openPermisoModal(state.permisos.find((item) => item.id === Number(btn.dataset.id)) || null)));
    els.permisosList.querySelectorAll('.js-perm-approve').forEach((btn) => btn.addEventListener('click', () => approvePermiso(Number(btn.dataset.id))));
    els.permisosList.querySelectorAll('.js-perm-cancel').forEach((btn) => btn.addEventListener('click', () => updatePermisoAction('cancel', Number(btn.dataset.id))));
    els.permisosList.querySelectorAll('.js-perm-delete').forEach((btn) => btn.addEventListener('click', () => updatePermisoAction('delete', Number(btn.dataset.id))));
  }

  async function loadAll() {
    const [catalogoRes, metaRes, permisosRes] = await Promise.all([
      fetchJSON(API_CATALOGO),
      fetchJSON(`${API_PERMISOS}?action=meta`),
      fetchJSON(API_PERMISOS),
    ]);
    state.catalogo = catalogoRes.data?.items || [];
    state.areas = metaRes.data?.areas || [];
    state.responsables = metaRes.data?.responsables || [];
    state.permisos = permisosRes.data?.items || [];
    renderCatalogo();
    renderPermisos();
  }

  function openCatalogoModal(item) {
    els.catalogoForm?.reset();
    if (els.catalogoModalTitle) els.catalogoModalTitle.textContent = item ? 'Editar material' : 'Nuevo material';
    if (els.catalogoForm) {
      const idField = els.catalogoForm.querySelector('[name="id"]');
      if (idField) idField.value = item?.id || '';
      els.catalogoForm.nombre.value = item?.nombre || '';
      els.catalogoForm.categoria.value = item?.categoria || '';
      els.catalogoForm.descripcion.value = item?.descripcion || '';
      els.catalogoForm.activo.checked = item ? Boolean(Number(item.activo)) : true;
    }
    els.catalogoError?.classList.add('hidden');
    els.catalogoModal?.classList.remove('hidden');
    els.catalogoModal?.classList.add('flex');
  }

  function closeCatalogoModal() {
    els.catalogoModal?.classList.add('hidden');
    els.catalogoModal?.classList.remove('flex');
  }

  function openPermisoModal(item) {
    els.permisoForm?.reset();
    if (els.permisoModalTitle) els.permisoModalTitle.textContent = item ? 'Editar permiso' : 'Nuevo permiso';
    if (els.permisoForm) {
      const idField = els.permisoForm.querySelector('[name="id"]');
      if (idField) idField.value = item?.id || '';
      els.permisoForm.tipo_movimiento.value = item?.tipo_movimiento || 'entrada';
      els.permisoForm.notas.value = item?.notas || '';
      fillOperationalSelects(item);
    }
    els.permisoItems.innerHTML = '';
    (item?.items?.length ? item.items : [{}]).forEach((row) => {
      els.permisoItems.insertAdjacentHTML('beforeend', newItemRow(row));
    });
    bindItemRowEvents();
    els.permisoError?.classList.add('hidden');
    els.permisoModal?.classList.remove('hidden');
    els.permisoModal?.classList.add('flex');
  }

  function closePermisoModal() {
    els.permisoModal?.classList.add('hidden');
    els.permisoModal?.classList.remove('flex');
  }

  async function saveCatalogo(ev) {
    ev.preventDefault();
    try {
      const fd = new FormData(els.catalogoForm);
      fd.set('activo', els.catalogoForm.activo.checked ? '1' : '0');
      await fetchJSON(API_CATALOGO, { method: 'POST', body: fd });
      closeCatalogoModal();
      showAlert('Catálogo actualizado.', 'success');
      await loadAll();
    } catch (e) {
      if (els.catalogoError) {
        els.catalogoError.textContent = e.message || 'No se pudo guardar el material.';
        els.catalogoError.classList.remove('hidden');
      }
    }
  }

  async function savePermiso(ev) {
    ev.preventDefault();
    try {
      const fd = new FormData(els.permisoForm);
      fd.set('items_json', JSON.stringify(collectItems()));
      await fetchJSON(API_PERMISOS, { method: 'POST', body: fd });
      closePermisoModal();
      showAlert('Permiso guardado.', 'success');
      await loadAll();
    } catch (e) {
      if (els.permisoError) {
        els.permisoError.textContent = e.message || 'No se pudo guardar el permiso.';
        els.permisoError.classList.remove('hidden');
      }
    }
  }

  async function deleteCatalogo(id) {
    if (!window.confirm('¿Eliminar este material del catálogo?')) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', String(id));
    try {
      await fetchJSON(API_CATALOGO, { method: 'POST', body: fd });
      showAlert('Material eliminado.', 'success');
      await loadAll();
    } catch (e) {
      showAlert(e.message || 'No se pudo eliminar el material.', 'error');
    }
  }

  async function approvePermiso(id) {
    const fd = new FormData();
    fd.append('action', 'approve');
    fd.append('id', String(id));
    try {
      await fetchJSON(API_PERMISOS, { method: 'POST', body: fd });
      showAlert('Permiso aprobado.', 'success');
      await loadAll();
    } catch (e) {
      showAlert(e.message || 'No se pudo aprobar el permiso.', 'error');
    }
  }

  async function updatePermisoAction(action, id) {
    if (!window.confirm(`¿Seguro que quieres ${action === 'delete' ? 'eliminar' : 'cancelar'} este permiso?`)) return;
    const fd = new FormData();
    fd.append('action', action);
    fd.append('id', String(id));
    try {
      await fetchJSON(API_PERMISOS, { method: 'POST', body: fd });
      showAlert(`Permiso ${action === 'delete' ? 'eliminado' : 'cancelado'}.`, 'success');
      await loadAll();
    } catch (e) {
      showAlert(e.message || 'No se pudo actualizar el permiso.', 'error');
    }
  }

  els.btnCatalogoNew?.addEventListener('click', () => openCatalogoModal(null));
  els.btnPermisoNew?.addEventListener('click', () => openPermisoModal(null));
  els.btnCatalogoClose?.addEventListener('click', closeCatalogoModal);
  els.btnCatalogoCancel?.addEventListener('click', closeCatalogoModal);
  els.btnPermisoClose?.addEventListener('click', closePermisoModal);
  els.btnPermisoCancel?.addEventListener('click', closePermisoModal);
  els.catalogoForm?.addEventListener('submit', saveCatalogo);
  els.permisoForm?.addEventListener('submit', savePermiso);
  els.btnAddPermisoItem?.addEventListener('click', () => {
    els.permisoItems.insertAdjacentHTML('beforeend', newItemRow());
    bindItemRowEvents();
  });
  els.catalogoModal?.addEventListener('click', (ev) => {
    if (ev.target === els.catalogoModal) closeCatalogoModal();
  });
  els.permisoModal?.addEventListener('click', (ev) => {
    if (ev.target === els.permisoModal) closePermisoModal();
  });

  loadAll().catch((e) => showAlert(e.message || 'No se pudo cargar materiales y permisos.', 'error'));
})();
