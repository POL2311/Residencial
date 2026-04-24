(function () {
  const view = document.getElementById('incidenciasView');
  if (!view) return;

  const API_BASE = '/admin_residencial/php/api/';
  const API_INCIDENCIAS = API_BASE + 'incidencias.php';
  const API_UNIDADES = API_BASE + 'unidades.php';
  const API_GUARDIAS = API_BASE + 'guardias.php';

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

    addError: document.getElementById('incidenciaAddError'),
    editError: document.getElementById('incidenciaEditError'),
  };

  if (!els.list) return;

  const state = {
    incidencias: [],
    unidades: [],
    guardias: [],
    filter: 'todas',
    search: '',
    page: 1,
    perPage: 3,
  };

  function escapeHtml(value = '') {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function showAlert(msg, type = 'info') {
    if (!els.alert) return;
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error'
        ? 'bg-rose-100 text-rose-700'
        : 'bg-sky-100 text-sky-700');
    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');

    setTimeout(() => {
      els.alert.classList.add('hidden');
    }, 3500);
  }

  function hideAlert() {
    els.alert?.classList.add('hidden');
  }

  function showFormError(el, msg) {
    if (!el) {
      showAlert(msg, 'error');
      return;
    }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function clearFormError(el) {
    if (!el) return;
    el.textContent = '';
    el.classList.add('hidden');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    const text = await res.text();

    let json;
    try {
      json = JSON.parse(text);
    } catch {
      throw new Error(text || 'Respuesta inválida del servidor');
    }

    if (!json.ok) throw new Error(json.error || 'Error');
    return json;
  }

  const api = {
    incidencias: {
      list: () => fetchJSON(API_INCIDENCIAS),
      save: (fd) =>
        fetchJSON(API_INCIDENCIAS, {
          method: 'POST',
          body: fd,
        }),
      remove: (id) => {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        return fetchJSON(API_INCIDENCIAS, {
          method: 'POST',
          body: fd,
        });
      },
    },
    unidades: {
      list: () => fetchJSON(API_UNIDADES),
    },
    guardias: {
      list: () => fetchJSON(API_GUARDIAS),
    },
  };

  const badgeEstado = (e) =>
    e === 'cerrada'
      ? 'bg-emerald-100 text-emerald-700'
      : e === 'en_proceso'
      ? 'bg-amber-100 text-amber-700'
      : 'bg-rose-100 text-rose-700';

  const badgePrioridad = (p) =>
    p === 'alta'
      ? 'bg-rose-100 text-rose-700'
      : p === 'media'
      ? 'bg-amber-100 text-amber-700'
      : 'bg-slate-100 text-slate-700';

  function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? s : d.toLocaleString();
  }

  function paginate(arr, page, per) {
    return arr.slice((page - 1) * per, page * per);
  }

  function ensureUiHelpers() {
    if (document.getElementById('incidenciasUiLayer')) return;

    const layer = document.createElement('div');
    layer.id = 'incidenciasUiLayer';
    layer.innerHTML = `
      <div id="friendlyConfirmIncidencia"
           class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden">
          <div class="p-6">
            <div class="flex items-start gap-4">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold">
                !
              </div>
              <div class="flex-1">
                <h3 id="friendlyConfirmIncidenciaTitle" class="text-xl font-semibold text-slate-900">
                  Confirmar acción
                </h3>
                <p id="friendlyConfirmIncidenciaMessage" class="mt-2 text-sm leading-6 text-slate-600">
                  ¿Deseas continuar?
                </p>
              </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
              <button id="friendlyConfirmIncidenciaCancel"
                      type="button"
                      class="rounded-full bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                Cancelar
              </button>
              <button id="friendlyConfirmIncidenciaAccept"
                      type="button"
                      class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(layer);
  }

  function showConfirmIncidencia({
    title = 'Confirmar acción',
    message = '¿Deseas continuar?',
    acceptText = 'Aceptar',
    cancelText = 'Cancelar',
  } = {}) {
    ensureUiHelpers();

    return new Promise((resolve) => {
      const modal = document.getElementById('friendlyConfirmIncidencia');
      const titleEl = document.getElementById('friendlyConfirmIncidenciaTitle');
      const messageEl = document.getElementById('friendlyConfirmIncidenciaMessage');
      const acceptBtn = document.getElementById('friendlyConfirmIncidenciaAccept');
      const cancelBtn = document.getElementById('friendlyConfirmIncidenciaCancel');

      titleEl.textContent = title;
      messageEl.textContent = message;
      acceptBtn.textContent = acceptText;
      cancelBtn.textContent = cancelText;

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      const cleanup = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        acceptBtn.onclick = null;
        cancelBtn.onclick = null;
        modal.onclick = null;
        document.removeEventListener('keydown', onKeydown);
      };

      const onKeydown = (e) => {
        if (e.key === 'Escape') {
          cleanup();
          resolve(false);
        }
      };

      acceptBtn.onclick = () => {
        cleanup();
        resolve(true);
      };

      cancelBtn.onclick = () => {
        cleanup();
        resolve(false);
      };

      modal.onclick = (e) => {
        if (e.target === modal) {
          cleanup();
          resolve(false);
        }
      };

      document.addEventListener('keydown', onKeydown);
    });
  }

  async function loadAll() {
    hideAlert();
    try {
      const [i, u, g] = await Promise.all([
        api.incidencias.list(),
        api.unidades.list(),
        api.guardias.list(),
      ]);

      state.incidencias = i.incidencias || [];
      state.unidades = u.unidades || [];
      state.guardias = g.guardias || [];

      render();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  function openAddModal() {
    if (!els.modalAdd || !els.formAdd) return;

    clearFormError(els.addError);
    els.formAdd.reset();

    const selU = els.formAdd.querySelector('[name="unidad_id"]');
    if (selU) {
      selU.innerHTML = '';
      state.unidades.forEach((u) => {
        selU.innerHTML += `<option value="${u.id}">${escapeHtml(u.clave)}</option>`;
      });
    }

    els.modalAdd.classList.remove('hidden');
  }

  function closeAddModal() {
    els.modalAdd?.classList.add('hidden');
    clearFormError(els.addError);
  }

  function openEditModal(inc) {
    if (!els.modalEdit || !els.formEdit) return;

    clearFormError(els.editError);
    els.formEdit.reset();

    const idInput = els.formEdit.querySelector('[name="id"]');
    if (idInput) idInput.value = inc.id;

    const selG = els.formEdit.querySelector('[name="guardia_id"]');
    if (selG) {
      selG.innerHTML = `<option value="">-- Sin asignar --</option>`;
      state.guardias.forEach((g) => {
        selG.innerHTML += `<option value="${g.id}">${escapeHtml(g.name)}</option>`;
      });
      selG.value = inc.guardia_id || '';
    }

    const selEstado = els.formEdit.querySelector('[name="estado"]');
    if (selEstado) selEstado.value = inc.estado || 'abierta';

    const selPri = els.formEdit.querySelector('[name="prioridad"]');
    if (selPri) selPri.value = inc.prioridad || 'media';

    els.modalEdit.classList.remove('hidden');
  }

  function closeEditModal() {
    els.modalEdit?.classList.add('hidden');
    clearFormError(els.editError);
  }

  async function deleteIncidencia(id) {
    const ok = await showConfirmIncidencia({
      title: 'Eliminar incidencia',
      message: 'Esta acción eliminará la incidencia de forma permanente. ¿Deseas continuar?',
      acceptText: 'Sí, eliminar',
      cancelText: 'Cancelar',
    });

    if (!ok) return;

    try {
      const resp = await api.incidencias.remove(id);
      showAlert(resp.message || 'Incidencia eliminada.');
      await loadAll();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  function validateAddForm(fd) {
    const unidadId = Number(fd.get('unidad_id') || 0);
    const tipo = String(fd.get('tipo') || '').trim();
    const titulo = String(fd.get('titulo') || '').trim();
    const descripcion = String(fd.get('descripcion') || '').trim();
    const prioridad = String(fd.get('prioridad') || '').trim();

    if (unidadId <= 0) throw new Error('Debes seleccionar una unidad.');
    if (!['seguridad', 'servicio', 'vecino', 'infraestructura', 'otro'].includes(tipo)) {
      throw new Error('Tipo inválido.');
    }
    if (titulo.length < 3) throw new Error('El título debe tener al menos 3 caracteres.');
    if (titulo.length > 120) throw new Error('El título no puede exceder 120 caracteres.');
    if (descripcion.length < 5) throw new Error('La descripción debe tener al menos 5 caracteres.');
    if (descripcion.length > 1000) throw new Error('La descripción no puede exceder 1000 caracteres.');
    if (!['baja', 'media', 'alta'].includes(prioridad)) throw new Error('Prioridad inválida.');
  }

  function validateEditForm(fd) {
    const estado = String(fd.get('estado') || '').trim();
    const prioridad = String(fd.get('prioridad') || '').trim();

    if (!['abierta', 'en_proceso', 'cerrada'].includes(estado)) {
      throw new Error('Estado inválido.');
    }
    if (!['baja', 'media', 'alta'].includes(prioridad)) {
      throw new Error('Prioridad inválida.');
    }

    const guardiaId = String(fd.get('guardia_id') || '').trim();
    if (guardiaId !== '' && Number(guardiaId) <= 0) {
      throw new Error('Guardia inválido.');
    }
  }

  function render() {
    els.list.innerHTML = '';

    const filtradas = state.incidencias.filter((i) => {
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

    if (filtradas.length) {
      const header = document.createElement('div');
      header.className =
        'hidden md:grid grid-cols-9 gap-4 rounded-2xl border border-slate-200 bg-white/70 px-5 py-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 shadow-sm';

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

    visibles.forEach((i) => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm';

      card.innerHTML = `
        <div class="space-y-3 md:hidden">
          <div>
            <div class="text-xs text-slate-500">Título</div>
            <div class="font-semibold">${escapeHtml(i.titulo || '—')}</div>
            <div class="text-xs text-slate-400 capitalize">${escapeHtml(i.tipo || '—')}</div>
          </div>

          <div><span class="text-xs text-slate-500">Unidad</span><div>${escapeHtml(i.unidad_clave || '—')}</div></div>
          <div><span class="text-xs text-slate-500">Residente</span><div>${escapeHtml(i.residente_nombre || '—')}</div></div>
          <div><span class="text-xs text-slate-500">Guardia</span><div>${escapeHtml(i.guardia_nombre || '—')}</div></div>

          <div class="flex gap-2">
            <span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)} capitalize">${escapeHtml(i.prioridad || '')}</span>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)} capitalize">${escapeHtml(i.estado || '')}</span>
          </div>

          <div class="text-[11px] text-slate-400">${escapeHtml(fmtDate(i.created_at))}</div>

          <div class="flex gap-2 pt-2">
            <button data-edit="${i.id}" class="flex-1 rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-700 hover:bg-slate-200">Editar</button>
            <button data-del="${i.id}" class="flex-1 rounded-full bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">Eliminar</button>
          </div>
        </div>

        <div class="hidden md:grid grid-cols-9 gap-4 items-center">
          <div class="col-span-2">
            <div class="font-semibold text-slate-800">${escapeHtml(i.titulo || '—')}</div>
            <div class="text-xs text-slate-500 capitalize">${escapeHtml(i.tipo || '—')}</div>
          </div>
          <div>${escapeHtml(i.unidad_clave || '—')}</div>
          <div class="col-span-2">${escapeHtml(i.residente_nombre || '—')}</div>
          <div>${escapeHtml(i.guardia_nombre || '—')}</div>
          <div><span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)} capitalize">${escapeHtml(i.prioridad || '')}</span></div>
          <div>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)} capitalize">${escapeHtml(i.estado || '')}</span>
            <div class="text-[11px] text-slate-400">${escapeHtml(fmtDate(i.created_at))}</div>
          </div>
          <div class="flex justify-end gap-2">
            <button data-edit="${i.id}" class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-200">Editar</button>
            <button data-del="${i.id}" class="inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-200">Eliminar</button>
          </div>
        </div>
      `;

      els.list.appendChild(card);
    });

    if (totalPages > 1) {
      const nav = document.createElement('div');
      nav.className = 'flex justify-end gap-2 mt-4';

      nav.innerHTML = `
        <button ${state.page === 1 ? 'disabled' : ''}
          class="px-3 py-1 rounded border text-sm disabled:opacity-40"
          data-page="${state.page - 1}">
          ◀
        </button>

        ${Array.from({ length: totalPages }).map((_, i) => `
          <button data-page="${i + 1}"
            class="px-3 py-1 rounded text-sm ${
              state.page === i + 1 ? 'bg-slate-800 text-white' : 'border'
            }">
            ${i + 1}
          </button>
        `).join('')}

        <button ${state.page === totalPages ? 'disabled' : ''}
          class="px-3 py-1 rounded border text-sm disabled:opacity-40"
          data-page="${state.page + 1}">
          ▶
        </button>
      `;

      els.list.appendChild(nav);
    }
  }

  els.filterEstado?.addEventListener('change', (e) => {
    state.filter = e.target.value;
    state.page = 1;
    render();
  });

  els.search?.addEventListener('input', (e) => {
    state.search = (e.target.value || '').toLowerCase().trim();
    state.page = 1;
    render();
  });

  els.btnAdd?.addEventListener('click', openAddModal);

  els.btnCloseAdd?.addEventListener('click', closeAddModal);
  els.btnCancelAdd?.addEventListener('click', closeAddModal);
  els.btnCloseEdit?.addEventListener('click', closeEditModal);
  els.btnCancelEdit?.addEventListener('click', closeEditModal);

  els.modalAdd?.addEventListener('click', (e) => {
    if (e.target === els.modalAdd) closeAddModal();
  });

  els.modalEdit?.addEventListener('click', (e) => {
    if (e.target === els.modalEdit) closeEditModal();
  });

  els.list.addEventListener('click', (e) => {
    const pageBtn = e.target.closest('[data-page]');
    const edit = e.target.closest('[data-edit]');
    const del = e.target.closest('[data-del]');

    if (pageBtn) {
      state.page = Number(pageBtn.dataset.page);
      render();
      return;
    }

    if (edit) {
      const inc = state.incidencias.find((x) => String(x.id) === String(edit.dataset.edit));
      if (inc) openEditModal(inc);
      return;
    }

    if (del) {
      deleteIncidencia(del.dataset.del);
    }
  });

  els.formAdd?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFormError(els.addError);

    try {
      const fd = new FormData(els.formAdd);
      validateAddForm(fd);

      const resp = await api.incidencias.save(fd);
      closeAddModal();
      showAlert(resp.message || 'Incidencia registrada.');
      await loadAll();
    } catch (e2) {
      showFormError(els.addError, e2.message || 'Error al registrar incidencia.');
    }
  });

  els.formEdit?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFormError(els.editError);

    try {
      const fd = new FormData(els.formEdit);
      validateEditForm(fd);

      const resp = await api.incidencias.save(fd);
      closeEditModal();
      showAlert(resp.message || 'Incidencia actualizada.');
      await loadAll();
    } catch (e2) {
      showFormError(els.editError, e2.message || 'Error al actualizar incidencia.');
    }
  });

  ensureUiHelpers();
  loadAll();
})();
