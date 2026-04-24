// admin_residencial/js/unidades.js
// ESPEJO ESTRUCTURAL DE residentes.js, PERO PARA UNIDADES

console.log('[UNIDADES] JS ACTIVO');

(function () {

  /* =========================
     ELEMENTOS
  ========================= */
  const els = {
    view: document.getElementById('unidadesView'),
    list: document.getElementById('unidadesList'),
    empty: document.getElementById('unidadesEmpty'),
    alert: document.getElementById('unidadesAlert'),
    btnAdd: document.getElementById('btnAddUnidad'),
    search: document.getElementById('unidadSearch'),

    modal: document.getElementById('modal'),
    modalTitle: document.getElementById('modalTitle'),
    modalForm: document.getElementById('modalForm'),
    btnCloseModal: document.getElementById('btnCloseModal'),
    btnCancelModal: document.getElementById('btnCancelModal'),

    detailModal: document.getElementById('detailModal'),
    detailContent: document.getElementById('detailModalContent'),
  };

  if (!els.view) return;

  /* =========================
     ESTADO
  ========================= */
  const state = {
    unidades: [],
    filteredUnidades: [],
    page: 1,
    editingId: null,
    selected: null
  };

  const PER_PAGE = 5;

  /* =========================
     HELPERS
  ========================= */
  function paginate(arr, page, perPage) {
    const start = (page - 1) * perPage;
    return arr.slice(start, start + perPage);
  }

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
    } catch {
      throw new Error(text);
    }
  }

  /* =========================
     LOAD
  ========================= */
  async function loadUnidades() {
    hideAlert();
    els.list.innerHTML = '';

    try {
      const json = await fetchJSON(
        '/Residencial/admin_residencial/php/api/unidades.php'
      );

      state.unidades = json.unidades || [];
      state.filteredUnidades = [...state.unidades];
      state.page = 1;

      render();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  /* =========================
     RENDER
  ========================= */
  function render() {
    els.list.innerHTML = '';

    if (!state.filteredUnidades.length) {
      els.empty.classList.remove('hidden');
      return;
    }
    els.empty.classList.add('hidden');

    const visibles = paginate(
      state.filteredUnidades,
      state.page,
      PER_PAGE
    );

    visibles.forEach(u => {
      const card = document.createElement('div');
      card.className =
        'rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm';

      card.innerHTML = `
        <div class="space-y-4">
          <div class="md:hidden">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-semibold text-slate-800">${u.clave}</div>
                <div class="text-xs text-slate-500 capitalize">${u.tipo}</div>
              </div>
              <div class="text-right text-xs text-slate-400">${u.torre || 'Sin torre'}</div>
            </div>
            <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
              <div>
                <dt class="text-xs uppercase tracking-wide text-slate-400">Titular</dt>
                <dd class="mt-1 text-slate-700">${u.titular || 'Sin titular'}</dd>
              </div>
            </dl>
          </div>

          <div class="hidden md:grid md:grid-cols-4 md:gap-4 md:items-center">
            <div>
              <div class="font-semibold text-slate-800">${u.clave}</div>
              <div class="text-xs text-slate-500 capitalize">${u.tipo}</div>
            </div>
            <div class="text-sm text-slate-700">
              ${u.torre || '—'}
            </div>
            <div class="text-sm text-slate-600 text-center">
              ${u.titular || 'Sin titular'}
            </div>
            <div class="flex justify-end gap-2 flex-wrap">
              <button data-more="${u.id}"
                class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                Ver más
              </button>
              <button data-edit="${u.id}"
                class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-200">
                Editar
              </button>
              <button data-del="${u.id}"
                class="inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-200">
                Eliminar
              </button>
            </div>
          </div>

          <div class="flex flex-wrap justify-end gap-2">
            <button data-more="${u.id}"
              class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
              Ver más
            </button>
            <button data-edit="${u.id}"
              class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-200">
              Editar
            </button>
            <button data-del="${u.id}"
              class="inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-200">
              Eliminar
            </button>
          </div>
        </div>
      `;

      els.list.appendChild(card);
    });

    renderPagination();
  }

  function renderPagination() {
    const total = state.filteredUnidades.length;
    const pages = Math.ceil(total / PER_PAGE);
    if (pages <= 1) return;

    const nav = document.createElement('div');
    nav.className = 'flex justify-end gap-2 mt-4';

    nav.innerHTML = Array.from({ length: pages }, (_, i) => `
      <button data-page="${i + 1}"
        class="px-3 py-1 rounded border ${state.page === i + 1 ? 'bg-slate-900 text-white' : ''}">
        ${i + 1}
      </button>
    `).join('');

    nav.addEventListener('click', e => {
      const btn = e.target.closest('[data-page]');
      if (!btn) return;
      state.page = Number(btn.dataset.page);
      render();
    });

    els.list.appendChild(nav);
  }

  /* =========================
     FILTRO
  ========================= */
  function filterUnidades(query) {
    query = query.toLowerCase().trim();

    if (!query) {
      state.filteredUnidades = [...state.unidades];
    } else {
      state.filteredUnidades = state.unidades.filter(u =>
        (u.clave || '').toLowerCase().includes(query) ||
        (u.torre || '').toLowerCase().includes(query) ||
        (u.tipo || '').toLowerCase().includes(query) ||
        (u.titular || '').toLowerCase().includes(query)
      );
    }

    state.page = 1;
    render();
  }

  /* =========================
     MODAL CRUD
  ========================= */
  function openModal(unidad = null) {
    document.body.style.overflow = 'hidden';
    els.modal.classList.remove('hidden');
    els.modalForm.reset();

    if (unidad) {
      state.editingId = unidad.id;
      els.modalTitle.textContent = 'Editar unidad';
      els.modalForm.id.value = unidad.id;
      els.modalForm.clave.value = unidad.clave;
      els.modalForm.torre.value = unidad.torre || '';
      els.modalForm.tipo.value = unidad.tipo;
    } else {
      state.editingId = null;
      els.modalTitle.textContent = 'Agregar unidad';
    }
  }

  function closeModal() {
    els.modal.classList.add('hidden');
    document.body.style.overflow = '';
    state.editingId = null;
  }

  async function deleteUnidad(id) {
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', id);

    try {
      await fetchJSON(
        '/admin_residencial/php/api/unidades.php',
        { method: 'POST', body: fd }
      );
      loadUnidades();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  /* =========================
     VER MÁS
  ========================= */
  function openDetail(unidad) {
    els.detailContent.innerHTML = `
      <div class="bg-white rounded-2xl p-4 sm:p-6 space-y-4">
        <div class="flex justify-between items-center gap-3">
          <h2 class="text-lg font-semibold">Detalle de unidad</h2>
          <button id="closeDetail"
            class="h-9 w-9 rounded-full border hover:bg-slate-100">✕</button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div><strong>Clave:</strong> ${unidad.clave}</div>
          <div><strong>Tipo:</strong> ${unidad.tipo}</div>
          <div><strong>Torre:</strong> ${unidad.torre || '—'}</div>
          <div><strong>Titular:</strong> ${unidad.titular || 'Sin titular'}</div>
        </div>
      </div>
    `;

    els.detailModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    document.getElementById('closeDetail').onclick = () =>
      (els.detailModal.classList.add('hidden'), document.body.style.overflow = '');
  }

  /* =========================
     EVENTOS
  ========================= */
  els.list.addEventListener('click', e => {
    const edit = e.target.closest('[data-edit]');
    const del  = e.target.closest('[data-del]');
    const more = e.target.closest('[data-more]');

    if (edit) {
      const u = state.unidades.find(x => x.id == edit.dataset.edit);
      if (u) openModal(u);
    }

    if (more) {
      const u = state.unidades.find(x => x.id == more.dataset.more);
      if (u) openDetail(u);
    }

    if (del && confirm('¿Eliminar unidad?')) {
      deleteUnidad(del.dataset.del);
    }
  });

  els.btnAdd?.addEventListener('click', () => openModal());
  els.btnCloseModal?.addEventListener('click', closeModal);
  els.btnCancelModal?.addEventListener('click', closeModal);

  els.modalForm.addEventListener('submit', async e => {
    e.preventDefault();

    const fd = new FormData(els.modalForm);
    fd.set('action', state.editingId ? 'update' : 'create');
    if (state.editingId) fd.set('id', state.editingId);

    try {
      await fetchJSON(
        '/admin_residencial/php/api/unidades.php',
        { method: 'POST', body: fd }
      );
      closeModal();
      loadUnidades();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  });

  els.search?.addEventListener('input', e =>
    filterUnidades(e.target.value)
  );

  /* =========================
     INIT
  ========================= */
  (function init() {
    els.btnAdd?.classList.remove('hidden');
    loadUnidades();
  })();

})();
