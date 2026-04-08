// guardia/js/incidencias.js
(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.incidencias = function ({ API, fetchJSON, openModal, closeModal, escapeHtml }) {
    const root = document.getElementById('incidenciasView');
    if (!root) return;

    const els = {
      list: document.getElementById('incList'),
      pagination: document.getElementById('incPagination'),
      btnNew: document.getElementById('btnNuevaInc'),

      frm: document.getElementById('frmIncFilter'),
      search: document.getElementById('incSearch'),
      estado: document.getElementById('incEstado'),
      prioridad: document.getElementById('incPrioridad'),
      tipo: document.getElementById('incTipoFilter'),
      btnClear: document.getElementById('btnClearIncFilters'),

      countTotal: document.getElementById('incCountTotal'),
      countAbiertas: document.getElementById('incCountAbiertas'),
      filterStatus: document.getElementById('incFilterStatus'),
    };

    const state = {
      destroyed: false,
      items: [],
      filteredItems: [],
      page: 1,
      perPage: 5,
      loading: false,
    };

    function isAlive() {
      return !state.destroyed;
    }

    function safeText(v, fallback = '—') {
      const s = String(v ?? '').trim();
      return escapeHtml(s || fallback);
    }

    function normalize(v) {
      return String(v || '').trim().toLowerCase();
    }

    function badgePrioridad(prioridad) {
      if (prioridad === 'alta') return 'bg-rose-100 text-rose-700 border-rose-200';
      if (prioridad === 'media') return 'bg-amber-100 text-amber-700 border-amber-200';
      return 'bg-slate-100 text-slate-700 border-slate-200';
    }

    function badgeEstado(estado) {
      if (estado === 'abierta') return 'bg-rose-100 text-rose-700 border-rose-200';
      if (estado === 'en_proceso') return 'bg-sky-100 text-sky-700 border-sky-200';
      if (estado === 'cerrada') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
      return 'bg-slate-100 text-slate-700 border-slate-200';
    }

    function setLoading(on) {
      state.loading = on;
      if (!els.list || !isAlive()) return;

      if (on) {
        els.list.innerHTML = `
          <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
            Cargando incidencias…
          </div>
        `;
      }
    }

    function updateSummary() {
      if (els.countTotal) {
        els.countTotal.textContent = String(state.filteredItems.length);
      }

      if (els.countAbiertas) {
        const abiertas = state.filteredItems.filter(i => String(i.estado || '') === 'abierta').length;
        els.countAbiertas.textContent = String(abiertas);
      }

      if (els.filterStatus) {
        const filters = [];
        if (els.search?.value?.trim()) filters.push(`Texto: ${els.search.value.trim()}`);
        if (els.estado?.value) filters.push(`Estado: ${els.estado.value}`);
        if (els.prioridad?.value) filters.push(`Prioridad: ${els.prioridad.value}`);
        if (els.tipo?.value) filters.push(`Tipo: ${els.tipo.value}`);
        els.filterStatus.textContent = filters.length ? filters.join(' · ') : 'Sin filtros';
      }
    }

    function applyFilters() {
      const q = normalize(els.search?.value);
      const estado = normalize(els.estado?.value);
      const prioridad = normalize(els.prioridad?.value);
      const tipo = normalize(els.tipo?.value);

      state.filteredItems = state.items.filter((i) => {
        const titulo = normalize(i.titulo);
        const descripcion = normalize(i.descripcion);
        const unidad = normalize(i.unidad_clave);
        const itemEstado = normalize(i.estado);
        const itemPrioridad = normalize(i.prioridad);
        const itemTipo = normalize(i.tipo);

        const okText = !q || titulo.includes(q) || descripcion.includes(q) || unidad.includes(q);
        const okEstado = !estado || itemEstado === estado;
        const okPrioridad = !prioridad || itemPrioridad === prioridad;
        const okTipo = !tipo || itemTipo === tipo;

        return okText && okEstado && okPrioridad && okTipo;
      });

      state.page = 1;
      updateSummary();
    }

    function paginate(items, page, perPage) {
      const start = (page - 1) * perPage;
      return items.slice(start, start + perPage);
    }

    function renderPagination() {
      if (!els.pagination || !isAlive()) return;
      els.pagination.innerHTML = '';

      const totalPages = Math.ceil(state.filteredItems.length / state.perPage);
      if (totalPages <= 1) return;

      const prev = document.createElement('button');
      prev.type = 'button';
      prev.textContent = '◀';
      prev.className = 'px-3 py-1 rounded border text-sm disabled:opacity-40';
      prev.disabled = state.page === 1;
      prev.addEventListener('click', () => {
        state.page -= 1;
        renderList();
      });
      els.pagination.appendChild(prev);

      for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = String(i);
        btn.className =
          'px-3 py-1 rounded text-sm ' +
          (state.page === i ? 'bg-slate-800 text-white' : 'border');
        btn.addEventListener('click', () => {
          state.page = i;
          renderList();
        });
        els.pagination.appendChild(btn);
      }

      const next = document.createElement('button');
      next.type = 'button';
      next.textContent = '▶';
      next.className = 'px-3 py-1 rounded border text-sm disabled:opacity-40';
      next.disabled = state.page === totalPages;
      next.addEventListener('click', () => {
        state.page += 1;
        renderList();
      });
      els.pagination.appendChild(next);
    }

    function renderList() {
      if (!els.list || !isAlive()) return;

      const items = paginate(state.filteredItems, state.page, state.perPage);

      if (!state.filteredItems.length) {
        els.list.innerHTML = `
          <div class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">
            No hay incidencias que coincidan con los filtros actuales.
          </div>
        `;
        renderPagination();
        return;
      }

      els.list.innerHTML = `
        <div class="space-y-3">
          ${items.map(i => `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
              <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="font-semibold text-slate-800 text-base">
                      ${safeText(i.titulo)}
                    </div>

                    <span class="text-[11px] px-2 py-0.5 rounded-full border ${badgePrioridad(i.prioridad)}">
                      ${safeText(i.prioridad)}
                    </span>

                    <span class="text-[11px] px-2 py-0.5 rounded-full border ${badgeEstado(i.estado)}">
                      ${safeText(i.estado)}
                    </span>
                  </div>

                  <div class="text-sm text-slate-600 mt-2">
                    ${safeText(i.descripcion, '')}
                  </div>

                  <div class="text-[11px] text-slate-500 mt-3">
                    Unidad: <b>${safeText(i.unidad_clave)}</b> · Tipo: ${safeText(i.tipo)} · ${safeText(i.created_at, '')}
                  </div>
                </div>

                <div class="shrink-0 flex flex-col items-end gap-2">
                  <div class="text-xs text-slate-400">
                    ID #${safeText(i.id)}
                  </div>

                  <div class="flex gap-2">
                    <button
                      type="button"
                      class="js-edit-inc rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs hover:bg-slate-50"
                      data-inc='${escapeHtml(JSON.stringify(i))}'>
                      Editar
                    </button>

                    <button
                      type="button"
                      class="js-del-inc rounded-lg bg-rose-600 text-white px-3 py-2 text-xs hover:bg-rose-700"
                      data-id="${escapeHtml(String(i.id))}"
                      data-title="${safeText(i.titulo, '')}">
                      Eliminar
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `).join('')}
        </div>
      `;

      els.list.querySelectorAll('.js-del-inc').forEach((btn) => {
        btn.addEventListener('click', () => {
          openDeleteConfirm(btn.dataset.id, btn.dataset.title);
        });
      });

      els.list.querySelectorAll('.js-edit-inc').forEach((btn) => {
        btn.addEventListener('click', () => {
          try {
            openEditModal(JSON.parse(btn.dataset.inc));
          } catch (_) {}
        });
      });

      renderPagination();
    }

    async function load() {
      if (!isAlive()) return;

      setLoading(true);

      try {
        const q = new URLSearchParams();
        if (els.search?.value?.trim()) q.set('q', els.search.value.trim());
        if (els.estado?.value) q.set('estado', els.estado.value);
        if (els.prioridad?.value) q.set('prioridad', els.prioridad.value);
        if (els.tipo?.value) q.set('tipo', els.tipo.value);

        const url = `${API}incidencias.php${q.toString() ? `?${q.toString()}` : ''}`;
        const json = await fetchJSON(url);
        if (!isAlive()) return;

        state.items = json.data?.items || [];
        applyFilters();
        renderList();
      } catch (e) {
        if (!isAlive()) return;

        els.list.innerHTML = `
          <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            Error: ${safeText(e.message, 'No se pudo cargar la información.')}
          </div>
        `;

        if (els.pagination) els.pagination.innerHTML = '';
      } finally {
        state.loading = false;
      }
    }

    function setModalError(msg = '') {
      const err = document.getElementById('incErr');
      if (!err) return;

      if (!msg) {
        err.className = 'hidden rounded-xl border px-3 py-2 text-xs';
        err.textContent = '';
        return;
      }

      err.className = 'rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700';
      err.textContent = msg;
    }

    function setModalSubmitting(on, text = 'Guardando…') {
      const btn = document.getElementById('incSubmit');
      if (!btn) return;
      btn.disabled = on;
      btn.textContent = on ? text : 'Guardar';
    }

    async function loadResidentesByUnidad(unidadId, selectId, selected = '') {
      const sel = document.getElementById(selectId);
      if (!sel) return;

      sel.innerHTML = `<option value="">Cargando residentes…</option>`;

      if (!unidadId) {
        sel.innerHTML = `<option value="">Selecciona primero una unidad</option>`;
        return;
      }

      try {
        const j = await fetchJSON(`${API}incidencias.php?action=residentes_unidad&unidad_id=${encodeURIComponent(unidadId)}`);
        const items = j.data?.items || [];

        if (!items.length) {
          sel.innerHTML = `<option value="">Sin residentes activos</option>`;
          return;
        }

        sel.innerHTML =
          `<option value="">Selecciona residente</option>` +
          items.map(r => `
            <option value="${escapeHtml(String(r.id))}" ${String(r.id) === String(selected) ? 'selected' : ''}>
              ${escapeHtml(r.name || '—')}${r.es_titular == 1 ? ' (titular)' : ''}
            </option>
          `).join('');
      } catch (e) {
        sel.innerHTML = `<option value="">Error al cargar residentes</option>`;
      }
    }

    function openDeleteConfirm(id, title) {
      openModal('Eliminar incidencia', `
        <div class="space-y-4">
          <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
            ¿Seguro que deseas eliminar la incidencia
            <b>${safeText(title, 'sin título')}</b>?
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" id="incDeleteCancel" class="rounded-xl border border-slate-200 px-4 py-2 text-sm">
              Cancelar
            </button>
            <button type="button" id="incDeleteAccept" class="rounded-xl bg-rose-600 text-white px-4 py-2 text-sm">
              Sí, eliminar
            </button>
          </div>
        </div>
      `);

      document.getElementById('incDeleteCancel')?.addEventListener('click', closeModal);
      document.getElementById('incDeleteAccept')?.addEventListener('click', async () => {
        try {
          const fd = new FormData();
          fd.set('action', 'delete');
          fd.set('id', id);

          const r = await fetch(`${API}incidencias.php`, {
            method: 'POST',
            credentials: 'same-origin',
            body: fd
          });

          const j = await r.json().catch(() => ({}));
          if (!r.ok || j.ok === false) throw new Error(j.error || 'No se pudo eliminar');

          closeModal();
          await load();
        } catch (e) {
          openModal('Error', `
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
              ${safeText(e.message, 'No se pudo eliminar la incidencia.')}
            </div>
            <div class="flex justify-end mt-4">
              <button type="button" id="incDeleteErrOk" class="rounded-xl bg-[#4E7287] text-white px-4 py-2 text-sm">
                Entendido
              </button>
            </div>
          `);

          document.getElementById('incDeleteErrOk')?.addEventListener('click', closeModal);
        }
      });
    }

    function buildFormModal({
      title = 'Nueva incidencia',
      item = null,
      submitAction = 'create',
      submitText = 'Guardar',
    }) {
      const isEdit = !!item;

      openModal(title, `
        <form id="frmNewInc" class="space-y-3">
          ${isEdit ? `<input type="hidden" id="incId" value="${escapeHtml(String(item.id || ''))}" />` : ''}

          <div>
            <label class="text-xs text-slate-500">Unidad *</label>
            <select id="incUnidad" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></select>
          </div>

          <div>
            <label class="text-xs text-slate-500">Residente *</label>
            <select id="incResidente" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="">Selecciona primero una unidad</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-slate-500">Tipo</label>
            <select id="incTipo" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="seguridad" ${item?.tipo === 'seguridad' ? 'selected' : ''}>Seguridad</option>
              <option value="ruido" ${item?.tipo === 'ruido' ? 'selected' : ''}>Ruido</option>
              <option value="mantenimiento" ${item?.tipo === 'mantenimiento' ? 'selected' : ''}>Mantenimiento</option>
              <option value="otros" ${item?.tipo === 'otros' ? 'selected' : ''}>Otros</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-slate-500">Prioridad</label>
            <select id="incPrioridad" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="baja" ${item?.prioridad === 'baja' ? 'selected' : ''}>Baja</option>
              <option value="media" ${!item || item?.prioridad === 'media' ? 'selected' : ''}>Media</option>
              <option value="alta" ${item?.prioridad === 'alta' ? 'selected' : ''}>Alta</option>
            </select>
          </div>

          ${isEdit ? `
            <div>
              <label class="text-xs text-slate-500">Estado</label>
              <select id="incEstadoEdit" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="abierta" ${item?.estado === 'abierta' ? 'selected' : ''}>Abierta</option>
                <option value="en_proceso" ${item?.estado === 'en_proceso' ? 'selected' : ''}>En proceso</option>
                <option value="cerrada" ${item?.estado === 'cerrada' ? 'selected' : ''}>Cerrada</option>
              </select>
            </div>
          ` : ''}

          <div>
            <label class="text-xs text-slate-500">Título *</label>
            <input id="incTitulo" value="${safeText(item?.titulo || '', '')}" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>

          <div>
            <label class="text-xs text-slate-500">Descripción *</label>
            <textarea id="incDesc" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" rows="4">${safeText(item?.descripcion || '', '')}</textarea>
          </div>

          <div id="incErr" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>

          <div class="flex items-center justify-end gap-2">
            <button type="button" id="incCancel" class="rounded-xl border border-slate-200 px-4 py-2 text-sm">Cancelar</button>
            <button type="submit" id="incSubmit" class="rounded-xl bg-[#4E7287] text-white px-4 py-2 text-sm shadow">${escapeHtml(submitText)}</button>
          </div>
        </form>
      `);

      const $ = (id) => document.getElementById(id);
      const unidadSel = $('incUnidad');
      const residenteSel = $('incResidente');

      $('incCancel')?.addEventListener('click', closeModal);

      fetchJSON(`${API}incidencias.php?action=unidades`)
        .then(async (j) => {
          const items = j.data?.items || [];
          if (!unidadSel) return;

          unidadSel.innerHTML =
            `<option value="">Selecciona unidad</option>` +
            items.map(u => `
              <option value="${escapeHtml(String(u.id))}" ${String(u.id) === String(item?.unidad_id || '') ? 'selected' : ''}>
                ${escapeHtml(u.clave)}
              </option>
            `).join('');

          await loadResidentesByUnidad(
            item?.unidad_id || '',
            'incResidente',
            item?.residente_id || ''
          );
        })
        .catch(e => setModalError(e.message || 'No se pudieron cargar las unidades.'));

      unidadSel?.addEventListener('change', async () => {
        setModalError('');
        await loadResidentesByUnidad(unidadSel.value, 'incResidente', '');
      });

      $('frmNewInc')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        setModalError('');
        setModalSubmitting(true);

        const payload = new FormData();
        payload.set('action', submitAction);
        if (isEdit) payload.set('id', $('incId')?.value || '');

        payload.set('unidad_id', unidadSel?.value || '');
        payload.set('residente_id', residenteSel?.value || '');
        payload.set('tipo', $('incTipo')?.value || 'seguridad');
        payload.set('prioridad', $('incPrioridad')?.value || 'media');
        payload.set('titulo', ($('incTitulo')?.value || '').trim());
        payload.set('descripcion', ($('incDesc')?.value || '').trim());

        if (isEdit) {
          payload.set('estado', $('incEstadoEdit')?.value || 'abierta');
        }

        if (!payload.get('unidad_id')) {
          setModalSubmitting(false);
          return setModalError('Selecciona una unidad.');
        }

        if (!payload.get('residente_id')) {
          setModalSubmitting(false);
          return setModalError('Selecciona un residente.');
        }

        if (!String(payload.get('titulo') || '').trim()) {
          setModalSubmitting(false);
          return setModalError('El título es obligatorio.');
        }

        if (!String(payload.get('descripcion') || '').trim()) {
          setModalSubmitting(false);
          return setModalError('La descripción es obligatoria.');
        }

        try {
          const r = await fetch(`${API}incidencias.php`, {
            method: 'POST',
            credentials: 'same-origin',
            body: payload
          });

          const j = await r.json().catch(() => ({}));
          if (!r.ok || j.ok === false) throw new Error(j.error || 'No se pudo guardar');

          closeModal();
          await load();
        } catch (e) {
          setModalError(e.message || 'Error al guardar');
        } finally {
          setModalSubmitting(false);
        }
      });
    }

    function openNewModal() {
      buildFormModal({
        title: 'Nueva incidencia',
        item: null,
        submitAction: 'create',
        submitText: 'Guardar',
      });
    }

    function openEditModal(item) {
      buildFormModal({
        title: 'Editar incidencia',
        item,
        submitAction: 'update',
        submitText: 'Guardar cambios',
      });
    }

    function onSubmitFilters(e) {
      e.preventDefault();
      applyFilters();
      renderList();
    }

    function onClearFilters() {
      if (els.search) els.search.value = '';
      if (els.estado) els.estado.value = '';
      if (els.prioridad) els.prioridad.value = '';
      if (els.tipo) els.tipo.value = '';
      applyFilters();
      renderList();
    }

    function onNewClick() {
      openNewModal();
    }

    function bindEvents() {
      els.btnNew?.addEventListener('click', onNewClick);
      els.frm?.addEventListener('submit', onSubmitFilters);
      els.btnClear?.addEventListener('click', onClearFilters);

      [els.search, els.estado, els.prioridad, els.tipo].forEach((input) => {
        input?.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            applyFilters();
            renderList();
          }
        });
      });

      [els.estado, els.prioridad, els.tipo].forEach((input) => {
        input?.addEventListener('change', () => {
          applyFilters();
          renderList();
        });
      });
    }

    function unbindEvents() {
      els.btnNew?.removeEventListener('click', onNewClick);
      els.frm?.removeEventListener('submit', onSubmitFilters);
      els.btnClear?.removeEventListener('click', onClearFilters);
    }

    bindEvents();
    load();

    return {
      unmount() {
        state.destroyed = true;
        unbindEvents();
      }
    };
  };
})();