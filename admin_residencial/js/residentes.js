// admin_residencial/js/residentes.js
// Script actualizado para que el modal de detalles cargue autos y pagos del residente seleccionado

console.log('[RESIDENTES] JS ACTIVO');

(function () {

  // =========================
  // ELEMENTOS
  // =========================
  const els = {
    view: document.getElementById('residentesView'),
    list: document.getElementById('residentesList'),
    empty: document.getElementById('residentesEmpty'),
    alert: document.getElementById('residentesAlert'),
    btnAdd: document.getElementById('btnAddResidente'),

    modal: document.getElementById('modal'),
    modalTitle: document.getElementById('modalTitle'),
    modalForm: document.getElementById('modalForm'),
    btnCloseModal: document.getElementById('btnCloseModal'),
    btnCancelModal: document.getElementById('btnCancelModal'),

    // Elementos del modal de detalle
    detailModal: document.getElementById('detailModal'),
    detailModalContent: document.getElementById('detailModalContent'),
  };

  if (!els.view) return;

  // =========================
  // ESTADO
  // =========================
  const state = {
    residentes: [],
    unidades: [],
    editingId: null,
    selected: null,

    pagos: [],
    autos: [],
    pagosPage: 1,
    autosPage: 1,
    currentUserId: null,
    residentesPage: 1,
     filteredResidentes: []
  };

  const PAGOS_PER_PAGE = 3;
  const AUTOS_PER_PAGE = 4;
  const RESIDENTES_PER_PAGE = 5;

  function paginate(array, page = 1, perPage = 3) {
    const start = (page - 1) * perPage;
    return array.slice(start, start + perPage);
  }

  function renderPagination(total, page, perPage, type) {
    const pages = Math.ceil(total / perPage);
    if (pages <= 1) return '';

    let html = `<div class="flex gap-1 justify-end mt-3 text-sm" data-type="${type}">`;
    html += `
      <button ${page === 1 ? 'disabled' : ''}
        data-page="${page - 1}"
        class="px-3 py-1 rounded border ${page === 1 ? 'opacity-50' : ''}">
        ◀
      </button>
    `;
    for (let i = 1; i <= pages; i++) {
      html += `
        <button data-page="${i}"
          class="px-3 py-1 rounded border ${i === page ? 'bg-slate-900 text-white' : ''}">
          ${i}
        </button>
      `;
    }
    html += `
      <button ${page === pages ? 'disabled' : ''}
        data-page="${page + 1}"
        class="px-3 py-1 rounded border ${page === pages ? 'opacity-50' : ''}">
        ▶
      </button>
    </div>`;
    return html;
  }

  function renderPagos() {
    const visibles = paginate(state.pagos, state.pagosPage, PAGOS_PER_PAGE);
    if (!visibles.length) {
      return `<p class="text-xs text-slate-500">Sin pagos registrados.</p>`;
    }
    return `
      <div data-type="pagos">
        <table class="min-w-full text-xs border rounded-xl overflow-hidden">
          <thead class="bg-slate-100">
            <tr>
              <th class="px-3 py-2">Fecha</th>
              <th class="px-3 py-2">Monto</th>
              <th class="px-3 py-2">Método</th>
              <th class="px-3 py-2">Concepto</th>
            </tr>
          </thead>
          <tbody>
            ${visibles.map(p => `
              <tr class="border-t">
                <td class="px-3 py-2">${p.fecha_pago || '—'}</td>
                <td class="px-3 py-2">$${Number(p.monto).toFixed(2)}</td>
                <td class="px-3 py-2">${p.metodo}</td>
                <td class="px-3 py-2">${p.concepto || ''}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
        ${renderPagination(
          state.pagos.length,
          state.pagosPage,
          PAGOS_PER_PAGE,
          'pagos'
        )}
      </div>
    `;
  }

  function renderAutos() {
    const visibles = paginate(state.autos, state.autosPage, AUTOS_PER_PAGE);
    if (!visibles.length) {
      return `<p class="text-xs text-slate-500">Sin autos asignados.</p>`;
    }
    return `
      <div data-type="autos">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          ${visibles.map(a => `
            <div class="rounded-2xl border p-4 shadow-sm">
              <div class="font-semibold">${a.placas}</div>
              <div class="text-xs text-slate-500">
                ${a.modelo || '—'} • ${a.color || '—'}
              </div>
            </div>
          `).join('')}
        </div>
        ${renderPagination(
          state.autos.length,
          state.autosPage,
          AUTOS_PER_PAGE,
          'autos'
        )}
      </div>
    `;
  }

  function renderDetailModal(r, totalPagado) {
    return `
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden">
      <div class="flex justify-between items-center px-6 py-4 border-b bg-slate-50">
        <div>
          <h2 class="text-lg font-semibold text-slate-800">Detalle del residente</h2>
          <p class="text-xs text-slate-500">Información general y actividad</p>
        </div>
        <button id="btnCloseDetail"
          class="h-9 w-9 rounded-full bg-white border hover:bg-slate-100 flex items-center justify-center">
          ✕
        </button>
      </div>
      <div class="p-6 space-y-6">
        <section class="rounded-2xl border bg-white p-5">
          <h3 class="text-sm font-semibold text-slate-700 mb-4">
            👤 Información del residente
          </h3>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-xs text-slate-500">Nombre</p>
              <p class="font-medium text-slate-800">${r.nombre}</p>
            </div>
            <div>
              <p class="text-xs text-slate-500">Unidad</p>
              <p class="font-medium text-slate-800">${r.unidad_clave || '—'}</p>
            </div>
            <div>
              <p class="text-xs text-slate-500">Email</p>
              <p class="font-medium text-slate-800">${r.email || '—'}</p>
            </div>
            <div>
              <p class="text-xs text-slate-500">Teléfono</p>
              <p class="font-medium text-slate-800">${r.telefono || '—'}</p>
            </div>
          </div>
        </section>
        <section class="rounded-2xl border bg-white p-5 space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-sm font-semibold text-slate-700">💳 Pagos</h3>
            <button id="btnAddPago"
              class="px-4 py-2 rounded-xl text-xs bg-blue-500 text-white hover:bg-blue-600">
              + Agregar pago
            </button>
          </div>
          <div class="text-sm">
            <strong>Total pagado:</strong>
            $${Number(totalPagado).toFixed(2)}
          </div>
          ${renderPagos()}
        </section>
        <section class="rounded-2xl border bg-white p-5 space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-sm font-semibold text-slate-700">🚗 Autos</h3>
            <button id="btnAddAuto"
              class="px-4 py-2 rounded-xl text-xs bg-slate-800 text-white hover:bg-slate-900">
              + Agregar auto
            </button>
          </div>
          ${renderAutos()}
        </section>
      </div>
    </div>
    `;
  }

  // =========================
  // HELPERS
  // =========================
  function showAlert(msg, type = 'info') {
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error'
        ? 'bg-rose-100 text-rose-700'
        : 'bg-sky-100 text-sky-700');
    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');
  }

  function hideAlert() {
    els.alert.classList.add('hidden');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      ...options
    });
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      if (!json.ok) throw new Error(json.error || 'Error');
      return json;
    } catch (err) {
      throw new Error(text);
    }
  }

  // =========================
  // LOAD
  // =========================
  async function loadData() {
    hideAlert();
    els.list.innerHTML = '';
    try {
      const json = await fetchJSON(
        '/residencial/admin_residencial/php/api/residentes.php?action=list'
      );
    state.residentes = json.residentes || [];
    state.filteredResidentes = [...state.residentes];
    state.unidades = json.unidades || [];
    state.residentesPage = 1;
    renderResidentes();
    els.list.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  // =========================
  // RENDER (lista)
  // =========================
function renderResidentes() {
  els.list.innerHTML = '';

  if (!state.filteredResidentes.length) {
    els.empty.classList.remove('hidden');
    return;
  }

  els.empty.classList.add('hidden');

  const visibles = paginate(
    state.filteredResidentes,
    state.residentesPage,
    RESIDENTES_PER_PAGE
  );

  visibles.forEach(r => {
    const wa = r.telefono
      ? `https://wa.me/52${r.telefono.replace(/\D/g, '')}`
      : '#';

    const card = document.createElement('div');
    card.className =
      'rounded-2xl border bg-white p-4 flex justify-between items-center';

card.innerHTML = `
  <div class="w-full grid grid-cols-4 gap-4 items-center">

    <!-- Col 1: Residente -->
    <div>
      <div class="font-semibold text-slate-800">${r.nombre}</div>
      <div class="text-xs text-slate-500">Unidad: ${r.unidad_clave || '—'}</div>
      <div class="text-xs text-slate-600">${r.telefono || 'Sin teléfono'}</div>
    </div>

    <!-- Col 2: Calle / Casa -->
    <div class="text-sm text-slate-700">
      ${r.unidad_detalle || '—'}
    </div>

    <!-- Col 3: Estado servicio -->
    <div class="flex justify-center">
      <label class="relative inline-flex items-center cursor-pointer">
       <input type="checkbox"
        class="sr-only peer"
        data-toggle="${r.resid_unid_id}"
        ${r.activo_servicio == 1 ? 'checked' : ''}>
        <div class="
          w-11 h-6 bg-slate-300 rounded-full peer
          peer-checked:bg-emerald-500
          after:content-['']
          after:absolute after:top-[2px] after:left-[2px]
          after:bg-white after:rounded-full after:h-5 after:w-5
          after:transition-all
          peer-checked:after:translate-x-full
        "></div>
      </label>
    </div>

    <!-- Col 4: Más acciones -->
    <div class="flex justify-end gap-2 flex-wrap">

      ${r.telefono ? `
        <a href="tel:${r.telefono}"
          class="text-xs px-3 py-1 rounded-full border">
          Llamar
        </a>

        <a href="https://wa.me/52${r.telefono.replace(/\D/g,'')}"
          target="_blank"
          class="text-xs px-3 py-1 rounded-full bg-green-500 text-white">
          WhatsApp
        </a>
      ` : ''}

      <button data-more="${r.resid_unid_id}"
        class="text-xs px-3 py-1 rounded-full bg-slate-100">
        Ver más
      </button>

      <button data-edit="${r.resid_unid_id}"
        class="text-xs px-3 py-1 rounded-full border">
        Editar
      </button>

      <button data-del="${r.resid_unid_id}"
        class="text-xs px-3 py-1 rounded-full bg-rose-500 text-white">
        Eliminar
      </button>
    </div>

  </div>
`;



    els.list.appendChild(card);
  });

  // 👇 paginación
  els.list.insertAdjacentHTML(
    'beforeend',
    renderPagination(
      state.filteredResidentes.length,
      state.residentesPage,
      RESIDENTES_PER_PAGE,
      'residentes'
    )
  );
}


  // =========================
  // MODAL CRUD
  // =========================
  function openModal(mode, r = null) {
    els.modal.classList.remove('hidden');
    els.modalForm.reset();

    const sel = els.modalForm.unidad_id;
    sel.innerHTML = `<option value="">—</option>`;
    state.unidades.forEach(u => {
      sel.innerHTML += `<option value="${u.id}">${u.clave}</option>`;
    });

    if (mode === 'edit' && r) {
      els.modalTitle.textContent = 'Editar residente';
      state.editingId = r.resid_unid_id;
      els.modalForm.nombre.value = r.nombre;
      els.modalForm.telefono.value = r.telefono || '';
      els.modalForm.email.value = r.email || '';
      setTimeout(() => {
        sel.value = String(r.unidad_id);
      }, 0);
    } else {
      els.modalTitle.textContent = 'Agregar residente';
      state.editingId = null;
    }
  }

  function closeModal() {
    els.modal.classList.add('hidden');
    state.editingId = null;
  }

  function openAddPagoModal(residente) {
    const modal = document.createElement('div');
    modal.className =
      'fixed inset-0 bg-black/40 flex items-center justify-center z-50';
    modal.innerHTML = `
      <div class="bg-white rounded-3xl w-full max-w-xl p-8 shadow-2xl relative">
        <button id="closeAddPago"
          class="absolute top-4 right-4 h-9 w-9 rounded-full border hover:bg-slate-100">
          ✕
        </button>
        <h2 class="text-lg font-semibold">Agregar pago</h2>
        <p class="text-sm text-slate-500 mb-6">
          ${residente.nombre} · Unidad ${residente.unidad_clave}
        </p>
        <form id="addPagoForm" class="space-y-5">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-slate-500">Monto *</label>
              <input name="monto" type="number" step="0.01" required
                class="w-full mt-1 rounded-xl border px-4 py-2"
                placeholder="Ej. 850.00">
            </div>
            <div>
              <label class="text-xs text-slate-500">Fecha *</label>
              <input name="fecha" type="date" required
                class="w-full mt-1 rounded-xl border px-4 py-2">
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-slate-500">Método</label>
              <select name="metodo"
                class="w-full mt-1 rounded-xl border px-4 py-2">
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="tarjeta">Tarjeta</option>
              </select>
            </div>
            <div>
              <label class="text-xs text-slate-500">Concepto</label>
              <input name="concepto"
                class="w-full mt-1 rounded-xl border px-4 py-2"
                placeholder="Ej. Mantenimiento enero">
            </div>
          </div>
          <input type="hidden" name="user_id" value="${residente.user_id}">
          <input type="hidden" name="action" value="create">
          <div class="flex justify-end gap-3 pt-6">
            <button type="button" id="cancelAddPago"
              class="px-5 py-2 rounded-xl border hover:bg-slate-100">
              Cancelar
            </button>
            <button
              class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white">
              Registrar pago
            </button>
          </div>
        </form>
      </div>
    `;
    document.body.appendChild(modal);
    modal.querySelector('#closeAddPago').onclick =
      modal.querySelector('#cancelAddPago').onclick = () => modal.remove();
    modal.querySelector('#addPagoForm').onsubmit = async e => {
      e.preventDefault();
      const fd = new FormData(e.target);
      try {
        await fetchJSON(
          '/residencial/admin_residencial/php/api/pagos_residentes.php',
          { method: 'POST', body: fd }
        );
        modal.remove();
        // vuelve a cargar datos y re-renderiza paginaciones
        state.pagosPage = 1;
        await openDetail(state.selected.resid_unid_id);
      } catch (err) {
        alert(err.message);
      }
    };
  }

function openAddAutoModal(residente) {
  const modal = document.createElement('div');
  modal.className =
    'fixed inset-0 bg-black/40 flex items-center justify-center z-50';

  modal.innerHTML = `
    <div class="bg-white rounded-3xl w-full max-w-xl p-8 shadow-2xl relative">

      <!-- Cerrar -->
      <button id="closeAddAuto"
        class="absolute top-4 right-4 h-9 w-9 rounded-full border hover:bg-slate-100 flex items-center justify-center">
        ✕
      </button>

      <!-- Header -->
      <h2 class="text-lg font-semibold text-slate-800">Agregar auto</h2>
      <p class="text-sm text-slate-500 mb-6">
        ${residente.nombre} · Unidad ${residente.unidad_clave}
      </p>

      <!-- Form -->
      <form id="addAutoForm" class="space-y-5">

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-slate-500">Placas *</label>
            <input name="placas" required
              class="w-full mt-1 rounded-xl border px-4 py-2 focus:ring-2 focus:ring-blue-500"
              placeholder="Ej. ABC-123">
          </div>

          <div>
            <label class="text-xs text-slate-500">Modelo</label>
            <input name="modelo"
              class="w-full mt-1 rounded-xl border px-4 py-2 focus:ring-2 focus:ring-blue-500"
              placeholder="Ej. Versa 2020">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-xs text-slate-500">Color</label>
            <input name="color"
              class="w-full mt-1 rounded-xl border px-4 py-2 focus:ring-2 focus:ring-blue-500"
              placeholder="Ej. Blanco">
          </div>

          <div>
            <label class="text-xs text-slate-500">Casa asignada</label>
            <input disabled
              class="w-full mt-1 rounded-xl border bg-slate-100 px-4 py-2"
              value="${residente.unidad_clave}">
          </div>
        </div>

        <!-- Hidden -->
        <input type="hidden" name="user_id" value="${residente.user_id}">
        <input type="hidden" name="unidad_id" value="${residente.unidad_id}">
        <input type="hidden" name="action" value="create">

        <!-- Footer -->
        <div class="flex justify-end gap-3 pt-6">
          <button type="button" id="cancelAddAuto"
            class="px-5 py-2 rounded-xl border hover:bg-slate-100">
            Cancelar
          </button>
          <button
            class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white">
            Crear auto
          </button>
        </div>
      </form>
    </div>
  `;

  document.body.appendChild(modal);

  modal.querySelector('#closeAddAuto').onclick =
    modal.querySelector('#cancelAddAuto').onclick = () => modal.remove();

  modal.querySelector('#addAutoForm').onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(e.target);

    try {
      await fetchJSON(
        '/residencial/admin_residencial/php/api/autos_admin.php',
        { method: 'POST', body: fd }
      );

      modal.remove();
      state.autosPage = 1;
      await openDetail(state.selected.resid_unid_id);
    } catch (err) {
      alert(err.message);
    }
  };
}

  function filterResidentes(query) {
  query = query.toLowerCase().trim();

  if (!query) {
    state.filteredResidentes = [...state.residentes];
  } else {
    state.filteredResidentes = state.residentes.filter(r =>
      (r.nombre || '').toLowerCase().includes(query) ||
      (r.unidad_clave || '').toLowerCase().includes(query) ||
      (r.telefono || '').includes(query)
    );
  }

  state.residentesPage = 1;
  renderResidentes();
}


  // =========================
  // DETALLE / “Ver más”
  // =========================
  // =========================
// TOGGLE ESTADO SERVICIO
// =========================
els.list.addEventListener('change', async (e) => {
  const input = e.target;

  if (!input.matches('input[type="checkbox"][data-toggle]')) return;

  const residUnidId = input.dataset.toggle;
  const newStatus = input.checked ? 1 : 0;

  const fd = new FormData();
  fd.append('action', 'toggle_active');
  fd.append('id', residUnidId);
  fd.append('active', newStatus);

  try {
    await fetchJSON(
      '/residencial/admin_residencial/php/api/residentes.php',
      { method: 'POST', body: fd }
    );

    // sincroniza estado local
    const r = state.residentes.find(x => x.resid_unid_id == residUnidId);
    if (r) r.activo_servicio = newStatus;

  } catch (err) {
    // rollback visual
    input.checked = !newStatus;
    alert(err.message);
  }
});

  async function openDetail(residUnidId) {
    try {
      state.pagosPage = 1;
      state.autosPage = 1;
      // 1. Residente
      const detailResp = await fetchJSON(
        `/residencial/admin_residencial/php/api/residentes.php?action=get&id=${residUnidId}`
      );
      const r = detailResp.residente;
      state.selected = r;

      // 2. user_id
      const resident = state.residentes.find(x => x.resid_unid_id == residUnidId);
      const userId = resident ? resident.user_id : r.user_id;
      state.currentUserId = userId;

      // 3. Pagos
      const pagosResp = await fetchJSON(
        `/residencial/admin_residencial/php/api/pagos_residentes.php?action=list&user_id=${userId}`
      );
      state.pagos = pagosResp.data ? pagosResp.data.pagos : [];

      const totalPagado = state.pagos.reduce(
        (sum, p) => sum + (p.monto || 0),
        0
      );

      // 4. Autos
      const autosResp = await fetchJSON(
        `/residencial/admin_residencial/php/api/autos_admin.php?action=list_by_resident&user_id=${userId}`
      );
      state.autos = autosResp.data ? autosResp.data.autos : [];

      // 5. Render
      els.detailModalContent.innerHTML =
        renderDetailModal(r, totalPagado);
      els.detailModal.classList.remove('hidden');

      attachDetailEventListeners();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  // Vuelve a enlazar los manejadores después de re-render
  function attachDetailEventListeners() {
    // botón cerrar
    document.getElementById('btnCloseDetail')?.addEventListener('click', () => {
      els.detailModal.classList.add('hidden');
    });
    // botón agregar pago
    document.getElementById('btnAddPago')?.addEventListener('click', () => {
      openAddPagoModal({
        user_id: state.currentUserId,
        nombre: state.selected.nombre,
        unidad_clave: state.selected.unidad_clave
      });
    });
    // botón agregar auto
    document.getElementById('btnAddAuto')?.addEventListener('click', () => {
      openAddAutoModal({
        user_id: state.currentUserId,
        nombre: state.selected.nombre,
        unidad_id: state.selected.unidad_id,
        unidad_clave: state.selected.unidad_clave
      });
    });





  }

  // Paginación: al hacer clic, actualiza la página y vuelve a enlazar
  els.detailModalContent.addEventListener('click', e => {
    const btn = e.target.closest('[data-page]');
    if (!btn) return;
    const page = Number(btn.dataset.page);
    const wrapper = btn.closest('[data-type]');
    if (!wrapper) return;
    if (wrapper.dataset.type === 'pagos') {
      state.pagosPage = page;
    }
    if (wrapper.dataset.type === 'autos') {
      state.autosPage = page;
    }
        if (wrapper.dataset.type === 'residentes') {
    state.residentesPage = page;
    renderResidentes();
    }

    els.detailModalContent.innerHTML =
      renderDetailModal(
        state.selected,
        state.pagos.reduce((s, p) => s + (p.monto || 0), 0)
      );
    // volver a enlazar
    attachDetailEventListeners();
  });

  // =========================
  // EVENTOS
  // =========================
  els.list.addEventListener('click', e => {
  const btn = e.target.closest('[data-page]');
  if (!btn) return;

  const wrapper = btn.closest('[data-type]');
  if (!wrapper || wrapper.dataset.type !== 'residentes') return;

  const page = Number(btn.dataset.page);
  state.residentesPage = page;
  renderResidentes();
});
  document.getElementById('residentSearch')?.addEventListener('input', e => {
  filterResidentes(e.target.value);
});
  els.btnAdd?.addEventListener('click', () => openModal('create'));
  els.btnCloseModal?.addEventListener('click', closeModal);
  els.btnCancelModal?.addEventListener('click', closeModal);

  els.list.addEventListener('click', e => {
    const edit = e.target.closest('[data-edit]');
    const del = e.target.closest('[data-del]');
    const more = e.target.closest('[data-more]');
    if (edit) {
      const r = state.residentes.find(x => x.resid_unid_id == edit.dataset.edit);
      if (r) openModal('edit', r);
    }
    if (more) {
      openDetail(more.dataset.more);
    }
    if (del && confirm('¿Eliminar residente?')) {
      deleteResidente(del.dataset.del);
    }
  });

  els.modalForm.addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(els.modalForm);
    fd.append('action', state.editingId ? 'update' : 'create');
    if (state.editingId) fd.append('id', state.editingId);
    try {
      await fetchJSON(
        '/residencial/admin_residencial/php/api/residentes.php',
        { method: 'POST', body: fd }
      );
      closeModal();
      loadData();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  });
  

  async function deleteResidente(id) {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);
    try {
      await fetchJSON(
        '/residencial/admin_residencial/php/api/residentes.php',
        { method: 'POST', body: fd }
      );
      loadData();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  // =========================
  // INIT
  // =========================
  (function init() {
    els.btnAdd?.classList.remove('hidden');
    loadData();
  })();

})();
