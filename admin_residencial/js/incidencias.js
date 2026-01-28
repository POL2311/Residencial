// admin_residencial/js/incidencias.js
// ESPEJO ESTRUCTURAL DE residentes.js / unidades.js (cards + modales)

console.log('[INCIDENCIAS] JS ACTIVO');

(function () {

  const view = document.getElementById('incidenciasView');
  if (!view) return;

  const API_BASE = '/residencial/admin_residencial/php/api/';
  const API_INCIDENCIAS = API_BASE + 'incidencias.php';
  const API_UNIDADES   = API_BASE + 'unidades.php';
  const API_GUARDIAS   = API_BASE + 'guardias.php';

  // =========================
  // ELEMENTOS
  // =========================
  const els = {
    alert: document.getElementById('incidenciasAlert'),
    list: document.getElementById('incidenciasList'),
    filterEstado: document.getElementById('filterEstado'),
    btnAdd: document.getElementById('btnAddIncidencia'),
    search: document.getElementById('searchIncidencias'),

    modalAdd: document.getElementById('incidenciaModal'),
    modalEdit: document.getElementById('incidenciaEditModal'),

    formAdd: document.getElementById('incidenciaForm'),
    formEdit: document.getElementById('incidenciaEditForm'),

    btnCloseAdd: document.getElementById('btnCloseIncidenciaModal'),
    btnCloseEdit: document.getElementById('btnCloseEditIncModal'),
    btnCancelAdd: document.getElementById('btnCancelIncidenciaAdd'),
    btnCancelEdit: document.getElementById('btnCancelIncidenciaEdit'),
  };

  if (!els.list) {
    console.error('[INCIDENCIAS] Falta #incidenciasList en el HTML');
    return;
  }

  // =========================
  // ESTADO
  // =========================
  const state = {
    incidencias: [],
    unidades: [],
    guardias: [],
    filter: 'todas',
    search: '',
    page: 1,
    perPage: 3,
  };

  // =========================
  // HELPERS
  // =========================
  function showAlert(msg, type = 'info') {
    if (!els.alert) return;
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error'
        ? 'bg-rose-100 text-rose-700'
        : 'bg-sky-100 text-sky-700');
    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');
  }

  function hideAlert() {
    els.alert?.classList.add('hidden');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    const text = await res.text();
    try {
      const json = JSON.parse(text);
      if (!json.ok) throw new Error(json.error || 'Error');
      return json;
    } catch {
      throw new Error(text);
    }
  }

  const badgeEstado = e =>
    e === 'cerrada'
      ? 'bg-emerald-100 text-emerald-700'
      : e === 'en_proceso'
      ? 'bg-amber-100 text-amber-700'
      : 'bg-rose-100 text-rose-700';

  const badgePrioridad = p =>
    p === 'alta'
      ? 'bg-rose-100 text-rose-700'
      : p === 'media'
      ? 'bg-amber-100 text-amber-700'
      : 'bg-slate-100 text-slate-700';

  const fmtDate = s => {
    if (!s) return '—';
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? s : d.toLocaleString();
  };

  const paginate = (arr, page, per) =>
    arr.slice((page - 1) * per, page * per);

  // =========================
  // LOAD
  // =========================
  async function loadAll() {
    hideAlert();
    try {
      const [i, u, g] = await Promise.all([
        fetchJSON(API_INCIDENCIAS),
        fetchJSON(API_UNIDADES),
        fetchJSON(API_GUARDIAS),
      ]);

      state.incidencias = i.incidencias || [];
      state.unidades = u.unidades || [];
      state.guardias = g.guardias || [];

      render();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  // =========================
  // RENDER
  // =========================
  function render() {
    els.list.innerHTML = '';

    // ===== FILTRO (ESTADO + SEARCH)
    const filtradas = state.incidencias.filter(i => {
      const byEstado =
        state.filter === 'todas' ? true : i.estado === state.filter;

      const q = state.search;
      const bySearch = !q
        ? true
        : (
            (i.titulo || '').toLowerCase().includes(q) ||
            (i.unidad_clave || '').toLowerCase().includes(q) ||
            (i.residente_nombre || '').toLowerCase().includes(q)
          );

      return byEstado && bySearch;
    });

    const totalPages = Math.max(1, Math.ceil(filtradas.length / state.perPage));
    if (state.page > totalPages) state.page = 1;

    // ===== HEADER DESKTOP
    if (filtradas.length) {
      const header = document.createElement('div');
      header.className =
        'hidden md:grid grid-cols-9 gap-4 px-4 py-2 text-xs font-semibold text-slate-500';

      header.innerHTML = `
        <div class="col-span-2">Título</div>
        <div>Unidad</div>
        <div class="col-span-2">Residente</div>
        <div>Guardia</div>
        <div>Prioridad</div>
        <div>Estado</div>
        <div class="text-right">Acciones</div>
      `;
      els.list.appendChild(header);
    }

    const visibles = paginate(filtradas, state.page, state.perPage);

    if (!visibles.length) {
      els.list.innerHTML += `
        <div class="rounded-2xl border bg-slate-50 p-4 text-sm text-slate-600">
          No hay incidencias para mostrar.
        </div>`;
      return;
    }

    // ===== CARDS
    visibles.forEach(i => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border bg-white px-4 py-4';

      card.innerHTML = `
        <!-- MOBILE -->
        <div class="space-y-3 md:hidden">
          <div>
            <div class="text-xs text-slate-500">Título</div>
            <div class="font-semibold">${i.titulo}</div>
            <div class="text-xs text-slate-400">${i.tipo}</div>
          </div>

          <div><span class="text-xs text-slate-500">Unidad</span><div>${i.unidad_clave}</div></div>
          <div><span class="text-xs text-slate-500">Residente</span><div>${i.residente_nombre}</div></div>
          <div><span class="text-xs text-slate-500">Guardia</span><div>${i.guardia_nombre || '—'}</div></div>

          <div class="flex gap-2">
            <span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)}">${i.prioridad}</span>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)}">${i.estado}</span>
          </div>

          <div class="text-[11px] text-slate-400">${fmtDate(i.created_at)}</div>

          <div class="flex gap-2 pt-2">
            <button data-edit="${i.id}" class="flex-1 rounded-xl border px-3 py-2">Editar</button>
            <button data-del="${i.id}" class="flex-1 rounded-xl bg-rose-500 text-white px-3 py-2">Eliminar</button>
          </div>
        </div>

        <!-- DESKTOP -->
        <div class="hidden md:grid grid-cols-9 gap-4 items-center">
          <div class="col-span-2">
            <div class="font-semibold">${i.titulo}</div>
            <div class="text-xs text-slate-500">${i.tipo}</div>
          </div>
          <div>${i.unidad_clave}</div>
          <div class="col-span-2">${i.residente_nombre}</div>
          <div>${i.guardia_nombre || '—'}</div>
          <div><span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)}">${i.prioridad}</span></div>
          <div>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)}">${i.estado}</span>
            <div class="text-[11px] text-slate-400">${fmtDate(i.created_at)}</div>
          </div>
          <div class="flex justify-end gap-2">
            <button data-edit="${i.id}" class="text-xs px-3 py-1 rounded-full border">Editar</button>
            <button data-del="${i.id}" class="text-xs px-3 py-1 rounded-full bg-rose-500 text-white">Eliminar</button>
          </div>
        </div>
      `;

      els.list.appendChild(card);
    });

    // ===== PAGINACIÓN
    if (totalPages > 1) {
      const nav = document.createElement('div');
      nav.className = 'flex justify-end gap-2 mt-4';

      nav.innerHTML = Array.from({ length: totalPages }).map((_, i) => `
        <button data-page="${i + 1}"
          class="px-3 py-1 rounded text-sm ${
            state.page === i + 1 ? 'bg-slate-800 text-white' : 'border'
          }">
          ${i + 1}
        </button>
      `).join('');

      els.list.appendChild(nav);
    }
  }

  // =========================
  // EVENTOS
  // =========================
  els.filterEstado?.addEventListener('change', e => {
    state.filter = e.target.value;
    state.page = 1;
    render();
  });

  els.search?.addEventListener('input', e => {
    state.search = e.target.value.toLowerCase().trim();
    state.page = 1;
    render();
  });

  els.list.addEventListener('click', e => {
    const pageBtn = e.target.closest('[data-page]');
    const edit = e.target.closest('[data-edit]');
    const del = e.target.closest('[data-del]');

    if (pageBtn) {
      state.page = Number(pageBtn.dataset.page);
      render();
    }

    if (edit) {
      const inc = state.incidencias.find(x => x.id == edit.dataset.edit);
      if (inc) openEditModal(inc);
    }

    if (del) deleteIncidencia(del.dataset.del);
  });

  // =========================
  // INIT
  // =========================
  loadAll();

})();
