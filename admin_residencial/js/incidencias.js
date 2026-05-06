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
    chartCard: document.getElementById('incidenciasChartCard'),
    chartBars: document.getElementById('incidenciasChartBars'),
    chartTotals: document.getElementById('incidenciasChartTotals'),

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
    meta: { modo_operacion: 'residencial', areas: [], personas: [], visitantes: [], permisos: [] },
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

  function humanizeValue(value, fallback = '—') {
    const raw = String(value ?? '').trim();
    if (!raw) return fallback;
    return raw
      .replaceAll('_', ' ')
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
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
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, { credentials: 'same-origin', ...rest, headers: { Accept: 'application/json', ...headers } });
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
    meta: {
      get: () => fetchJSON(API_INCIDENCIAS + '?action=meta'),
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

  function computeStatusCounts(items) {
    const counts = { abierta: 0, en_proceso: 0, cerrada: 0 };
    (items || []).forEach((i) => {
      const k = String(i.estado || '').trim();
      if (k === 'abierta' || k === 'en_proceso' || k === 'cerrada') counts[k] += 1;
    });
    return counts;
  }

  function renderStatusChart(counts) {
    if (!els.chartCard || !els.chartBars || !els.chartTotals) return;

    const abierta = Number(counts?.abierta || 0);
    const enProceso = Number(counts?.en_proceso || 0);
    const cerrada = Number(counts?.cerrada || 0);
    const total = abierta + enProceso + cerrada;

    els.chartTotals.textContent = total ? `${total} total` : 'Sin datos';
    els.chartBars.innerHTML = '';

    if (!total) {
      els.chartBars.innerHTML = `
        <div class="sm:col-span-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
          Sin datos para graficar con los filtros actuales.
        </div>
      `;
      return;
    }

    const max = Math.max(abierta, enProceso, cerrada, 1);
    const items = [
      { key: 'abierta', label: 'Abiertas', value: abierta, cls: 'bg-rose-100 text-rose-700', bar: 'bg-rose-500' },
      { key: 'en_proceso', label: 'En proceso', value: enProceso, cls: 'bg-amber-100 text-amber-700', bar: 'bg-amber-500' },
      { key: 'cerrada', label: 'Cerradas', value: cerrada, cls: 'bg-emerald-100 text-emerald-700', bar: 'bg-emerald-500' },
    ];

    items.forEach((it) => {
      const pct = Math.round((it.value / max) * 100);
      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-200 bg-white p-4';
      card.innerHTML = `
        <div class="flex items-center justify-between">
          <div class="text-sm font-semibold text-slate-800">${escapeHtml(it.label)}</div>
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs ${it.cls}">${it.value}</span>
        </div>
        <div class="mt-3 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full ${it.bar}" style="width:${pct}%"></div>
        </div>
      `;
      els.chartBars.appendChild(card);
    });
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
      const [i, u, g, m] = await Promise.all([
        api.incidencias.list(),
        api.unidades.list(),
        api.guardias.list(),
        api.meta.get(),
      ]);

      state.incidencias = i.incidencias || [];
      state.unidades = u.unidades || [];
      state.guardias = g.guardias || [];
      state.meta = m.data || state.meta;

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
    const operationalWrap = document.getElementById('incOperationalFields');
    const isOperational = String(state.meta?.modo_operacion || 'residencial') !== 'residencial';
    if (selU) {
      selU.closest('div')?.classList.toggle('hidden', isOperational);
      selU.innerHTML = '';
      state.unidades.forEach((u) => {
        selU.innerHTML += `<option value="${u.id}">${escapeHtml(u.clave)}</option>`;
      });
    }

    if (operationalWrap) {
      operationalWrap.classList.toggle('hidden', !isOperational);
      const fillSelect = (name, rows, labelKey = 'nombre', placeholder = 'Sin selección') => {
        const select = els.formAdd.querySelector(`[name="${name}"]`);
        if (!select) return;
        select.innerHTML = `<option value="">${placeholder}</option>` + rows.map((row) => {
          const label = row[labelKey] || row.nombre_visitante || row.tipo_movimiento || row.name || '—';
          return `<option value="${row.id}">${escapeHtml(label)}</option>`;
        }).join('');
      };
      fillSelect('area_id', state.meta.areas || [], 'nombre', 'Sin área');
      fillSelect('persona_recurrente_id', state.meta.personas || [], 'nombre', 'Sin persona');
      fillSelect('visitante_rapido_id', state.meta.visitantes || [], 'nombre_visitante', 'Sin visitante');
      fillSelect('permiso_material_id', state.meta.permisos || [], 'tipo_movimiento', 'Sin permiso');
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
    const isOperational = String(state.meta?.modo_operacion || 'residencial') !== 'residencial';
    const unidadId = Number(fd.get('unidad_id') || 0);
    const tipo = String(fd.get('tipo') || '').trim();
    const titulo = String(fd.get('titulo') || '').trim();
    const descripcion = String(fd.get('descripcion') || '').trim();
    const prioridad = String(fd.get('prioridad') || '').trim();

    if (!isOperational && unidadId <= 0) throw new Error('Debes seleccionar una unidad.');
    if (!['seguridad', 'servicio', 'vecino', 'infraestructura', 'otro', 'robo', 'conflicto', 'salida_sin_permiso', 'visitante_sin_ine', 'material_no_coincide', 'evento_general'].includes(tipo)) {
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

    renderStatusChart(computeStatusCounts(filtradas));

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
          </div>

          <div><span class="text-xs text-slate-500">Descripción</span><div class="whitespace-pre-wrap">${escapeHtml(i.descripcion || 'Sin descripción')}</div></div>
          <div><span class="text-xs text-slate-500">Tipo</span><div>${escapeHtml(humanizeValue(i.tipo))}</div></div>
          <div><span class="text-xs text-slate-500">Fecha</span><div>${escapeHtml(fmtDate(i.created_at))}</div></div>
          <div><span class="text-xs text-slate-500">Unidad</span><div>${escapeHtml(i.unidad_clave || '—')}</div></div>
          <div><span class="text-xs text-slate-500">Contexto</span><div>${escapeHtml(i.unidad_clave || i.area_nombre || '—')}</div></div>
          <div><span class="text-xs text-slate-500">Relación</span><div>${escapeHtml(i.residente_nombre || i.persona_recurrente_nombre || i.visitante_rapido_nombre || '—')}</div></div>
          <div><span class="text-xs text-slate-500">Guardia</span><div>${escapeHtml(i.guardia_nombre || '—')}</div></div>

          <div class="flex gap-2">
            <span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)}">${escapeHtml(`Prioridad: ${humanizeValue(i.prioridad, '')}`.trim())}</span>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)}">${escapeHtml(`Estado: ${humanizeValue(i.estado, '')}`.trim())}</span>
          </div>

          <div class="flex gap-2 pt-2">
            <button data-edit="${i.id}" class="flex-1 rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-700 hover:bg-slate-200">Editar</button>
            <button data-del="${i.id}" class="flex-1 rounded-full bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">Eliminar</button>
          </div>
        </div>

        <div class="hidden md:grid grid-cols-9 gap-4 items-center">
          <div class="col-span-2">
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Título</div>
            <div class="font-semibold text-slate-800">${escapeHtml(i.titulo || '—')}</div>
            <div class="mt-2 text-[11px] uppercase tracking-[0.12em] text-slate-400">Descripción</div>
            <div class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(i.descripcion || 'Sin descripción')}</div>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Contexto</div>
            <div>${escapeHtml(i.unidad_clave || i.area_nombre || '—')}</div>
            <div class="mt-2 text-[11px] uppercase tracking-[0.12em] text-slate-400">Tipo</div>
            <div class="text-xs text-slate-600">${escapeHtml(humanizeValue(i.tipo))}</div>
          </div>
          <div class="col-span-2">
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Relación</div>
            <div>${escapeHtml(i.residente_nombre || i.persona_recurrente_nombre || i.visitante_rapido_nombre || '—')}</div>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Guardia</div>
            <div>${escapeHtml(i.guardia_nombre || '—')}</div>
          </div>
          <div><span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)}">${escapeHtml(`Prioridad: ${humanizeValue(i.prioridad, '')}`.trim())}</span></div>
          <div>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)}">${escapeHtml(`Estado: ${humanizeValue(i.estado, '')}`.trim())}</span>
            <div class="mt-2 text-[11px] uppercase tracking-[0.12em] text-slate-400">Fecha</div>
            <div class="text-[11px] text-slate-500">${escapeHtml(fmtDate(i.created_at))}</div>
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
      if (String(state.meta?.modo_operacion || 'residencial') === 'residencial') {
        fd.delete('area_id');
        fd.delete('persona_recurrente_id');
        fd.delete('visitante_rapido_id');
        fd.delete('permiso_material_id');
        fd.delete('origen_tipo');
      } else {
        fd.delete('unidad_id');
      }
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
