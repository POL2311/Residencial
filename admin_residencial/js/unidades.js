console.log('[UNIDADES] JS CARGADO');

document.addEventListener('DOMContentLoaded', () => {

  const API_URL = '/admin_residencial/php/api/unidades.php'; // 👈 RUTA CORRECTA

  const els = {
    list: document.getElementById('unidadesList'),
    empty: document.getElementById('unidadesEmpty'),
    alert: document.getElementById('unidadesAlert'),
    btnAdd: document.getElementById('btnAddUnidad'),

    modal: document.getElementById('modal'),
    modalTitle: document.getElementById('modalTitle'),
    modalForm: document.getElementById('modalForm'),
    btnCloseModal: document.getElementById('btnCloseModal'),
    btnCancelModal: document.getElementById('btnCancelModal'),
  };

  if (!els.list || !els.modalForm) {
    console.error('[UNIDADES] Elementos base no encontrados');
    return;
  }

  const state = {
    unidades: [],
    page: 1,
    editingId: null,
  };

  const PER_PAGE = 5;

  /* =====================
     HELPERS
  ===================== */
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

  function paginate(arr, page, perPage) {
    const start = (page - 1) * perPage;
    return arr.slice(start, start + perPage);
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      ...options
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error');
    return json;
  }

  /* =====================
     LOAD
  ===================== */
  async function load() {
    hideAlert();
    els.list.innerHTML = '';

    try {
      const json = await fetchJSON(API_URL);
      state.unidades = json.unidades || [];
      state.page = 1;
      render();
    } catch (err) {
      showAlert(err.message, 'error');
    }
  }

  /* =====================
     RENDER
  ===================== */
  function render() {
    els.list.innerHTML = '';

    if (!state.unidades.length) {
      els.empty.classList.remove('hidden');
      return;
    }
    els.empty.classList.add('hidden');

    // Headers
    const header = document.createElement('div');
    header.className = 'grid grid-cols-6 gap-4 px-4 py-2 text-xs font-semibold text-slate-500';
    header.innerHTML = `
      <div>Clave</div>
      <div>Tipo</div>
      <div>Ubicación</div>
      <div>Titular</div>
      <div>Estado</div>
      <div class="text-right">Acciones</div>
    `;
    els.list.appendChild(header);

    const visibles = paginate(state.unidades, state.page, PER_PAGE);

    visibles.forEach(u => {
      const card = document.createElement('div');
      card.className =
        'grid grid-cols-6 gap-4 items-center bg-white border border-slate-200 rounded-2xl px-4 py-3';

      card.innerHTML = `
        <div class="font-semibold">${u.clave}</div>
        <div class="capitalize">${u.tipo}</div>
        <div>${u.torre || '—'}</div>
        <div>${u.titular || '—'}</div>
        <div>
          <span class="px-3 py-1 text-xs rounded-full ${
            u.activo == 1
              ? 'bg-emerald-100 text-emerald-700'
              : 'bg-rose-100 text-rose-700'
          }">
            ${u.activo == 1 ? 'Activa' : 'Inactiva'}
          </span>
        </div>
        <div class="flex justify-end gap-2">
          <button data-edit="${u.id}" class="text-xs px-3 py-1 rounded-full border">Editar</button>
          <button data-del="${u.id}" class="text-xs px-3 py-1 rounded-full bg-rose-500 text-white">Eliminar</button>
        </div>
      `;

      els.list.appendChild(card);
    });

    renderPagination();
  }

  function renderPagination() {
    const pages = Math.ceil(state.unidades.length / PER_PAGE);
    if (pages <= 1) return;

    const nav = document.createElement('div');
    nav.className = 'flex justify-end gap-2 mt-4';

    nav.innerHTML = Array.from({ length: pages }, (_, i) => `
      <button data-page="${i + 1}"
        class="px-3 py-1 rounded border ${state.page === i + 1 ? 'bg-slate-800 text-white' : ''}">
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

  /* =====================
     MODAL
  ===================== */
  function openModal(edit = null) {
    els.modal.classList.remove('hidden');
    els.modalForm.reset();

    if (edit) {
      state.editingId = edit.id;
      els.modalTitle.textContent = 'Editar unidad';
      els.modalForm.id.value = edit.id;
      els.modalForm.clave.value = edit.clave;
      els.modalForm.torre.value = edit.torre || '';
      els.modalForm.tipo.value = edit.tipo;
    } else {
      state.editingId = null;
      els.modalTitle.textContent = 'Agregar unidad';
      els.modalForm.id.value = '';
    }
  }

  function closeModal() {
    els.modal.classList.add('hidden');
    state.editingId = null;
  }

  async function deleteUnidad(id) {
    if (!confirm('¿Eliminar unidad?')) return;

    try {
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('id', id);

      await fetchJSON(API_URL, { method: 'POST', body: fd });
      load();
    } catch (err) {
      showAlert(err.message, 'error');
    }
  }

  /* =====================
     EVENTS
  ===================== */
  els.list.addEventListener('click', e => {
    const edit = e.target.closest('[data-edit]');
    const del  = e.target.closest('[data-del]');

    if (edit) {
      const u = state.unidades.find(x => x.id == edit.dataset.edit);
      if (u) openModal(u);
    }

    if (del) deleteUnidad(del.dataset.del);
  });

  els.btnAdd?.addEventListener('click', () => openModal());
  els.btnCloseModal?.addEventListener('click', closeModal);
  els.btnCancelModal?.addEventListener('click', closeModal);

  els.modalForm.addEventListener('submit', async e => {
    e.preventDefault();

    try {
      const fd = new FormData(els.modalForm);
      fd.set('action', state.editingId ? 'update' : 'create');
      if (state.editingId) fd.set('id', state.editingId);

      await fetchJSON(API_URL, { method: 'POST', body: fd });
      closeModal();
      load();
    } catch (err) {
      showAlert(err.message, 'error');
    }
  });

  els.btnAdd.classList.remove('hidden');
  load();
});
