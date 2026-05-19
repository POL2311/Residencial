(function () {
  const root = document.getElementById('materialesOperativosView');
  if (!root) return;

  const API_CATALOGO = '/admin_residencial/php/api/materiales_catalogo.php';
  const API_PERMISOS = '/admin_residencial/php/api/permisos_materiales.php';
  const MANUAL_VALUE = '__manual__';

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
    permisoItemsList: document.getElementById('permisoItemsList'),
    permisoItemMaterial: document.getElementById('permisoItemMaterial'),
    permisoItemCantidad: document.getElementById('permisoItemCantidad'),
    permisoItemManualWrap: document.getElementById('permisoItemManualWrap'),
    permisoItemNombreManual: document.getElementById('permisoItemNombreManual'),
    permisoItemAgregarCatalogo: document.getElementById('permisoItemAgregarCatalogo'),
  };

  const state = {
    catalogo: [],
    permisos: [],
    areas: [],
    responsables: [],
    permisoDraftItems: [],
  };
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
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...rest,
      headers: { Accept: 'application/json', ...headers },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'Error');
    return json;
  }

  function qrPreview(payload) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(payload)}`;
  }

  function ensureQrModal() {
    let modal = document.getElementById('permisoQrModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'permisoQrModal';
    modal.className = 'fixed inset-0 z-[9999] hidden items-center justify-center bg-black/60 p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">QR del permiso</div>
            <h3 id="permisoQrTitle" class="mt-1 text-xl font-semibold text-slate-900">Permiso registrado</h3>
          </div>
          <button type="button" id="permisoQrClose" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-700 hover:bg-slate-200">×</button>
        </div>
        <div class="px-5 py-6 text-center">
          <div class="mx-auto inline-flex rounded-3xl border border-slate-200 bg-white p-3 shadow-sm">
            <img id="permisoQrImage" src="" alt="QR permiso" class="h-72 w-72 max-w-full rounded-2xl object-contain" />
          </div>
          <div id="permisoQrPayload" class="mt-4 break-all rounded-2xl bg-slate-50 px-4 py-3 text-left text-xs text-slate-500"></div>
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
    modal.querySelector('#permisoQrClose')?.addEventListener('click', close);
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
    modal.querySelector('#permisoQrTitle').textContent = item.tipo_movimiento === 'salida' ? 'Salida autorizada' : 'Entrada autorizada';
    const image = modal.querySelector('#permisoQrImage');
    image.src = qrPreview(item.qr_payload);
    image.alt = `QR permiso ${item.id || ''}`;
    modal.querySelector('#permisoQrPayload').textContent = item.qr_payload;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    syncDashboardOverlayState();
  }

  function ensureDetailsModal() {
    let modal = document.getElementById('permisoDetailsModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'permisoDetailsModal';
    modal.className = 'fixed inset-0 z-[9998] hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="w-full max-w-3xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Detalle del permiso</div>
            <h3 id="permisoDetailsTitle" class="mt-1 text-xl font-semibold text-slate-900">Permiso registrado</h3>
          </div>
          <button type="button" id="permisoDetailsClose" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-700 hover:bg-slate-200">×</button>
        </div>
        <div id="permisoDetailsBody" class="max-h-[90%] overflow-y-auto px-5 py-5"></div>
      </div>
    `;
    document.body.appendChild(modal);

    const close = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
      syncDashboardOverlayState();
    };
    modal.querySelector('#permisoDetailsClose')?.addEventListener('click', close);
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) close();
    });
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
    return modal;
  }

  function closePermisoDetailsModal() {
    const modal = document.getElementById('permisoDetailsModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  function openPermisoDetails(item) {
    if (!item) return;
    const modal = ensureDetailsModal();
    modal.querySelector('#permisoDetailsTitle').textContent = item.tipo_movimiento === 'salida' ? 'Salida autorizada' : 'Entrada autorizada';
    modal.querySelector('#permisoDetailsBody').innerHTML = `
      <div class="space-y-4 text-sm text-slate-700">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Estado</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.estado || 'pendiente')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Responsable</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.responsable_nombre || 'Sin responsable')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Área</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.area_nombre || 'Sin área')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Aprobó</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.aprobado_por_nombre || 'Pendiente')}</div></div>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
          <div class="text-xs uppercase tracking-wide text-slate-400">Materiales</div>
          <div class="mt-3 space-y-2">
            ${(item.items || []).map((material) => `
              <div class="rounded-xl bg-white px-3 py-2 text-sm text-slate-700">
                ${escapeHtml(material.material_nombre)} <span class="text-slate-400">·</span> ${escapeHtml(material.cantidad_texto)}
              </div>
            `).join('') || '<div class="text-slate-500">Sin materiales capturados.</div>'}
          </div>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
          <div class="text-xs uppercase tracking-wide text-slate-400">Notas</div>
          <div class="mt-2 whitespace-pre-wrap text-slate-800">${escapeHtml(item.notas || 'Sin notas')}</div>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
          <div class="text-xs uppercase tracking-wide text-slate-400">QR del permiso</div>
          <div class="mt-2 break-all text-xs text-slate-500">${escapeHtml(item.qr_payload || 'Sin QR generado')}</div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <button type="button" data-detail-edit="${item.id}" class="rounded-xl border border-slate-200 px-4 py-3 font-medium text-slate-700 hover:bg-slate-50">Editar</button>
          <button type="button" data-detail-qr="${item.id}" class="rounded-xl bg-[#2E5D73] px-4 py-3 font-semibold text-white hover:opacity-95">Ver QR</button>
          <button type="button" data-detail-approve="${item.id}" class="rounded-xl border border-slate-200 px-4 py-3 font-medium text-slate-700 hover:bg-slate-50">Aprobar</button>
          <button type="button" data-detail-cancel="${item.id}" class="rounded-xl border border-slate-200 px-4 py-3 font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
          <button type="button" data-detail-delete="${item.id}" class="rounded-xl bg-rose-600 px-4 py-3 font-semibold text-white hover:bg-rose-700 sm:col-span-2 lg:col-span-1">Eliminar</button>
        </div>
      </div>
    `;

    modal.querySelector('[data-detail-edit]')?.addEventListener('click', () => {
      closePermisoDetailsModal();
      openPermisoModal(item);
    });
    modal.querySelector('[data-detail-qr]')?.addEventListener('click', () => openQrModal(item));
    modal.querySelector('[data-detail-approve]')?.addEventListener('click', async () => {
      closePermisoDetailsModal();
      await approvePermiso(item.id);
    });
    modal.querySelector('[data-detail-cancel]')?.addEventListener('click', async () => {
      closePermisoDetailsModal();
      await updatePermisoAction('cancel', item.id);
    });
    modal.querySelector('[data-detail-delete]')?.addEventListener('click', async () => {
      closePermisoDetailsModal();
      await updatePermisoAction('delete', item.id);
    });

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    syncDashboardOverlayState();
  }

  function ensureCatalogoDetailsModal() {
    let modal = document.getElementById('catalogoDetailsModal');
    if (modal) return modal;

    modal = document.createElement('div');
    modal.id = 'catalogoDetailsModal';
    modal.className = 'fixed inset-0 z-[9998] hidden items-center justify-center bg-black/50 p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="w-full max-w-xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-4">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Detalle del material</div>
            <h3 id="catalogoDetailsTitle" class="mt-1 text-xl font-semibold text-slate-900">Material</h3>
          </div>
          <button type="button" id="catalogoDetailsClose" class="flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xl text-slate-700 hover:bg-slate-200">×</button>
        </div>
        <div id="catalogoDetailsBody" class="max-h-[90%] overflow-y-auto px-5 py-5"></div>
      </div>
    `;
    document.body.appendChild(modal);

    const close = () => {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.body.style.overflow = '';
      syncDashboardOverlayState();
    };
    modal.querySelector('#catalogoDetailsClose')?.addEventListener('click', close);
    modal.addEventListener('click', (ev) => {
      if (ev.target === modal) close();
    });
    document.addEventListener('keydown', (ev) => {
      if (ev.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
    return modal;
  }

  function closeCatalogoDetailsModal() {
    const modal = document.getElementById('catalogoDetailsModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
  }

  function openCatalogoDetails(item) {
    if (!item) return;
    const modal = ensureCatalogoDetailsModal();
    modal.querySelector('#catalogoDetailsTitle').textContent = item.nombre || 'Material';
    modal.querySelector('#catalogoDetailsBody').innerHTML = `
      <div class="space-y-4 text-sm text-slate-700">
        <div class="grid gap-4 sm:grid-cols-2">
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Categoría</div><div class="mt-1 font-semibold text-slate-900">${escapeHtml(item.categoria || 'Sin categoría')}</div></div>
          <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs uppercase tracking-wide text-slate-400">Estado</div><div class="mt-1 font-semibold text-slate-900">${Number(item.activo) ? 'Activo' : 'Inactivo'}</div></div>
        </div>
        <div class="rounded-2xl bg-slate-50 p-4">
          <div class="text-xs uppercase tracking-wide text-slate-400">Descripción</div>
          <div class="mt-2 whitespace-pre-wrap text-slate-800">${escapeHtml(item.descripcion || 'Sin descripción')}</div>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
          <button type="button" data-catalog-edit="${item.id}" class="rounded-xl border border-slate-200 px-4 py-3 font-medium text-slate-700 hover:bg-slate-50">Editar</button>
          <button type="button" data-catalog-delete="${item.id}" class="rounded-xl bg-rose-600 px-4 py-3 font-semibold text-white hover:bg-rose-700">Eliminar</button>
        </div>
      </div>
    `;

    modal.querySelector('[data-catalog-edit]')?.addEventListener('click', () => {
      closeCatalogoDetailsModal();
      openCatalogoModal(item);
    });
    modal.querySelector('[data-catalog-delete]')?.addEventListener('click', async () => {
      closeCatalogoDetailsModal();
      await deleteCatalogo(item.id);
    });

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    syncDashboardOverlayState();
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

  function setPermisoError(msg = '') {
    if (!els.permisoError) return;
    if (!msg) {
      els.permisoError.textContent = '';
      els.permisoError.classList.add('hidden');
      return;
    }
    els.permisoError.textContent = msg;
    els.permisoError.classList.remove('hidden');
  }

  function fillOperationalSelects(item) {
    const areaSel = els.permisoForm?.querySelector('[name="area_id"]');
    const respSel = els.permisoForm?.querySelector('[name="responsable_user_id"]');
    if (areaSel) {
      areaSel.innerHTML = '<option value="">Sin área</option>' + state.areas
        .map((area) => `<option value="${area.id}" ${String(item?.area_id || '') === String(area.id) ? 'selected' : ''}>${escapeHtml(area.nombre)}</option>`)
        .join('');
    }
    if (respSel) {
      respSel.innerHTML = '<option value="">Sin responsable</option>' + state.responsables
        .map((user) => `<option value="${user.id}" ${String(item?.responsable_user_id || '') === String(user.id) ? 'selected' : ''}>${escapeHtml(user.name)}</option>`)
        .join('');
    }
  }

  function renderPermisoMaterialOptions() {
    if (!els.permisoItemMaterial) return;
    els.permisoItemMaterial.innerHTML =
      '<option value="">Selecciona un material</option>' +
      state.catalogo
        .map((material) => `<option value="${material.id}">${escapeHtml(material.nombre)}</option>`)
        .join('') +
      `<option value="${MANUAL_VALUE}">Otro / escribir manualmente</option>`;
  }

  function syncPermisoItemManualFields() {
    const catalogId = (els.permisoItemMaterial?.value || '').trim();
    const isManual = catalogId === MANUAL_VALUE;
    els.permisoItemManualWrap?.classList.toggle('hidden', !isManual);
    if (!isManual) {
      if (els.permisoItemNombreManual) els.permisoItemNombreManual.value = '';
      if (els.permisoItemAgregarCatalogo) els.permisoItemAgregarCatalogo.checked = false;
    }
  }

  function resetPermisoItemComposer() {
    if (els.permisoItemMaterial) els.permisoItemMaterial.value = '';
    if (els.permisoItemCantidad) els.permisoItemCantidad.value = '';
    if (els.permisoItemNombreManual) els.permisoItemNombreManual.value = '';
    if (els.permisoItemAgregarCatalogo) els.permisoItemAgregarCatalogo.checked = false;
    syncPermisoItemManualFields();
  }

  function currentPermisoItemFromComposer() {
    const materialIdRaw = (els.permisoItemMaterial?.value || '').trim();
    const cantidad = (els.permisoItemCantidad?.value || '').trim();
    const manualName = (els.permisoItemNombreManual?.value || '').trim();
    const addToCatalog = Boolean(els.permisoItemAgregarCatalogo?.checked);

    if (!materialIdRaw) {
      throw new Error('Selecciona un material o "Otro / escribir manualmente".');
    }

    if (!cantidad) {
      throw new Error('Agrega la cantidad o detalle del material.');
    }

    if (materialIdRaw === MANUAL_VALUE) {
      if (!manualName) {
        throw new Error('Escribe el nombre manual cuando selecciones "Otro".');
      }
      return {
        material_id: null,
        material_nombre: manualName,
        cantidad_texto: cantidad,
        agregar_a_catalogo: addToCatalog ? 1 : 0,
      };
    }

    const selected = els.permisoItemMaterial?.selectedOptions?.[0];
    const catalogName = (selected?.textContent || '').trim();

    return {
      material_id: Number(materialIdRaw),
      material_nombre: catalogName,
      cantidad_texto: cantidad,
      agregar_a_catalogo: 0,
    };
  }

  function renderPermisoItemsList() {
    if (!els.permisoItemsList) return;
    if (!state.permisoDraftItems.length) {
      els.permisoItemsList.innerHTML = `
        <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
          Aún no agregas materiales a este permiso.
        </div>
      `;
      return;
    }

    els.permisoItemsList.innerHTML = state.permisoDraftItems
      .map((item, index) => `
        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
          <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div class="min-w-0">
              <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.material_nombre || 'Material')}</div>
              <div class="text-xs text-slate-500">Cantidad / detalle: ${escapeHtml(item.cantidad_texto || '')}</div>
            </div>
            <div class="flex items-center gap-2">
              ${item.material_id ? '<span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] text-slate-600">Catálogo</span>' : '<span class="rounded-full bg-sky-50 px-2.5 py-1 text-[11px] text-sky-700">Manual</span>'}
              <button type="button" class="permiso-item-remove rounded-xl border px-3 py-1.5 text-xs hover:bg-slate-50" data-index="${index}">Quitar</button>
            </div>
          </div>
        </div>
      `)
      .join('');
  }

  function addPermisoDraftItem() {
    try {
      const item = currentPermisoItemFromComposer();
      state.permisoDraftItems.push(item);
      renderPermisoItemsList();
      resetPermisoItemComposer();
      setPermisoError('');
    } catch (error) {
      setPermisoError(error.message || 'No se pudo agregar el item.');
    }
  }

  function renderCatalogo() {
    if (!state.catalogo.length) {
      els.catalogoList.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Todavía no hay materiales en el catálogo.</div>';
      return;
    }

    els.catalogoList.innerHTML = state.catalogo
      .map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-semibold text-slate-800">${escapeHtml(item.nombre)}</div>
            <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.categoria || 'Sin categoría')}</div>
            <div class="mt-1 text-sm text-slate-500 line-clamp-2">${escapeHtml(item.descripcion || 'Sin descripción')}</div>
          </div>
          <div class="w-[150px] shrink-0">
            <button type="button" class="js-cat-more rounded-xl border px-3 py-2 text-xs hover:bg-white" data-id="${item.id}">Ver más</button>
          </div>
        </div>
      </div>
    `)
      .join('');

    els.catalogoList.querySelectorAll('.js-cat-more').forEach((btn) => {
      btn.addEventListener('click', () => openCatalogoDetails(state.catalogo.find((item) => item.id === Number(btn.dataset.id)) || null));
    });
  }

  function renderPermisos() {
    if (!state.permisos.length) {
      els.permisosList.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Todavía no hay permisos registrados.</div>';
      return;
    }

    els.permisosList.innerHTML = state.permisos
      .map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.tipo_movimiento === 'salida' ? 'Salida autorizada' : 'Entrada autorizada')}</h3>
              <span class="rounded-full px-2.5 py-1 text-xs ${item.estado === 'pendiente' ? 'bg-amber-100 text-amber-700' : item.estado === 'aprobado' ? 'bg-emerald-100 text-emerald-700' : item.estado === 'en_proceso' ? 'bg-sky-100 text-sky-700' : item.estado === 'usado' ? 'bg-slate-200 text-slate-700' : 'bg-rose-100 text-rose-700'}">${escapeHtml(item.estado)}</span>
            </div>
            <div class="mt-1 text-sm text-slate-600">Responsable: ${escapeHtml(item.responsable_nombre || 'Sin responsable')} · Área: ${escapeHtml(item.area_nombre || 'Sin área')}</div>
            <div class="mt-2 text-sm text-slate-500">
              ${(item.items || []).slice(0, 2).map((material) => `${escapeHtml(material.material_nombre)} · ${escapeHtml(material.cantidad_texto)}`).join(' · ') || 'Sin materiales'}
              ${(item.items || []).length > 2 ? ` · +${(item.items || []).length - 2} más` : ''}
            </div>
          </div>
          <div class="lg:w-[180px] shrink-0">
            <button type="button" class="js-perm-more rounded-xl border px-3 py-2 text-sm hover:bg-white" data-id="${item.id}">Ver más</button>
          </div>
        </div>
      </div>
    `)
      .join('');

    els.permisosList.querySelectorAll('.js-perm-more').forEach((btn) =>
      btn.addEventListener('click', () => openPermisoDetails(state.permisos.find((item) => item.id === Number(btn.dataset.id)) || null)),
    );
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
    renderPermisoMaterialOptions();
    syncPermisoItemManualFields();
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
    syncDashboardOverlayState();
  }

  function closeCatalogoModal() {
    els.catalogoModal?.classList.add('hidden');
    els.catalogoModal?.classList.remove('flex');
    syncDashboardOverlayState();
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

    state.permisoDraftItems = (item?.items || []).map((row) => ({
      material_id: row.material_id ? Number(row.material_id) : null,
      material_nombre: row.material_nombre || '',
      cantidad_texto: row.cantidad_texto || '',
      agregar_a_catalogo: Number(row.agregar_a_catalogo || 0) ? 1 : 0,
    }));
    renderPermisoItemsList();
    resetPermisoItemComposer();
    setPermisoError('');
    els.permisoModal?.classList.remove('hidden');
    els.permisoModal?.classList.add('flex');
    syncDashboardOverlayState();
  }

  function closePermisoModal() {
    els.permisoModal?.classList.add('hidden');
    els.permisoModal?.classList.remove('flex');
    syncDashboardOverlayState();
  }

  async function saveCatalogo(ev) {
    ev.preventDefault();
    const submitBtn = ev.submitter || els.catalogoForm?.querySelector('button[type="submit"]');
    await withPendingAction({
      button: submitBtn,
      scope: els.catalogoForm,
      label: 'Guardando...',
      lock: [els.btnCatalogoCancel, els.btnCatalogoClose].filter(Boolean),
    }, async () => {
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
    });
  }

  async function savePermiso(ev) {
    ev.preventDefault();
    const submitBtn = ev.submitter || els.permisoForm?.querySelector('button[type="submit"]');
    await withPendingAction({
      button: submitBtn,
      scope: els.permisoForm,
      label: 'Guardando...',
      lock: [els.btnPermisoCancel, els.btnPermisoClose, els.btnAddPermisoItem].filter(Boolean),
    }, async () => {
      try {
        if (!state.permisoDraftItems.length) {
          throw new Error('Debes agregar al menos un item al permiso.');
        }
        const fd = new FormData(els.permisoForm);
        fd.set('items_json', JSON.stringify(state.permisoDraftItems));
        await fetchJSON(API_PERMISOS, { method: 'POST', body: fd });
        closePermisoModal();
        showAlert('Permiso guardado.', 'success');
        await loadAll();
      } catch (e) {
        setPermisoError(e.message || 'No se pudo guardar el permiso.');
      }
    });
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
  els.btnAddPermisoItem?.addEventListener('click', addPermisoDraftItem);
  els.permisoItemMaterial?.addEventListener('change', syncPermisoItemManualFields);
  els.permisoItemsList?.addEventListener('click', (event) => {
    const btn = event.target.closest('.permiso-item-remove');
    if (!btn) return;
    const index = Number(btn.dataset.index);
    if (!Number.isInteger(index) || index < 0 || index >= state.permisoDraftItems.length) return;
    state.permisoDraftItems.splice(index, 1);
    renderPermisoItemsList();
  });

  els.catalogoModal?.addEventListener('click', (ev) => {
    if (ev.target === els.catalogoModal) closeCatalogoModal();
  });
  els.permisoModal?.addEventListener('click', (ev) => {
    if (ev.target === els.permisoModal) closePermisoModal();
  });

  loadAll().catch((e) => showAlert(e.message || 'No se pudo cargar materiales y permisos.', 'error'));
})();
