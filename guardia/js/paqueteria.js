// guardia/js/paqueteria.js
(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.paqueteria = function ({ API, fetchJSON, openModal, closeModal, escapeHtml }) {
    const root = document.getElementById('paqueteriaView');
    if (!root) return;

    const els = {
      list: document.getElementById('pkgList'),
      pagination: document.getElementById('pkgPagination'),
      btnNew: document.getElementById('btnNuevoPkg'),
      btnRefresh: document.getElementById('btnRefreshPkg'),
      frm: document.getElementById('frmPkgFilter'),
      search: document.getElementById('pkgSearch'),
      estado: document.getElementById('pkgEstado'),
      btnClear: document.getElementById('btnClearPkgFilters'),
      countTotal: document.getElementById('pkgCountTotal'),
      countPendientes: document.getElementById('pkgCountPendientes'),
      countEntregados: document.getElementById('pkgCountEntregados'),
      filterStatus: document.getElementById('pkgFilterStatus'),
    };

    const state = {
      destroyed: false,
      items: [],
      filteredItems: [],
      page: 1,
      perPage: 5,
      loading: false,
      actionToken: 0,
    };

    function isAlive() {
      return !state.destroyed;
    }

    function safeText(value, fallback = '—') {
      const text = String(value ?? '').trim();
      return escapeHtml(text || fallback);
    }

    function normalize(value) {
      return String(value || '').trim().toLowerCase();
    }

    function badgeState(estado) {
      if (estado === 'entregado') return 'bg-emerald-100 text-emerald-700 border-emerald-200';
      if (estado === 'devuelto') return 'bg-rose-100 text-rose-700 border-rose-200';
      return 'bg-amber-100 text-amber-700 border-amber-200';
    }

    function stateLabel(estado) {
      if (estado === 'entregado') return 'Entregado';
      if (estado === 'devuelto') return 'Devuelto';
      return 'Pendiente';
    }

    function setLoading(on) {
      state.loading = on;
      if (!els.list || !isAlive()) return;

      if (on) {
        els.list.innerHTML = `
          <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
            Cargando paquetes…
          </div>
        `;
      }
    }

    function updateSummary() {
      if (els.countTotal) {
        els.countTotal.textContent = String(state.filteredItems.length);
      }

      if (els.countPendientes) {
        const pending = state.filteredItems.filter((item) => normalize(item.estado) === 'registrado').length;
        els.countPendientes.textContent = String(pending);
      }

      if (els.countEntregados) {
        const delivered = state.filteredItems.filter((item) => normalize(item.estado) === 'entregado').length;
        els.countEntregados.textContent = String(delivered);
      }

      if (els.filterStatus) {
        const filters = [];
        if (els.search?.value?.trim()) filters.push(`Texto: ${els.search.value.trim()}`);
        if (els.estado?.value) filters.push(`Estado: ${stateLabel(els.estado.value)}`);
        els.filterStatus.textContent = filters.length ? filters.join(' · ') : 'Sin filtros';
      }
    }

    function applyFilters() {
      const q = normalize(els.search?.value);
      const estado = normalize(els.estado?.value);

      state.filteredItems = state.items.filter((item) => {
        const haystack = [
          item.descripcion,
          item.unidad_clave,
          item.empresa,
          item.codigo_rastreo,
          item.residente_nombre,
        ]
          .map(normalize)
          .join(' ');

        const okText = !q || haystack.includes(q);
        const okEstado = !estado || normalize(item.estado) === estado;
        return okText && okEstado;
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

      for (let i = 1; i <= totalPages; i += 1) {
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
            No hay paquetes que coincidan con los filtros actuales.
          </div>
        `;
        renderPagination();
        return;
      }

      els.list.innerHTML = `
        <div class="space-y-3">
          ${items.map((item) => `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
              <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="font-semibold text-slate-800 text-base">
                      ${safeText(item.descripcion)}
                    </div>

                    <span class="text-[11px] px-2 py-0.5 rounded-full border ${badgeState(item.estado)}">
                      ${safeText(stateLabel(item.estado))}
                    </span>

                    <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">
                      ${safeText(item.unidad_clave)}
                    </span>
                  </div>

                  <div class="text-sm text-slate-600 mt-2">
                    ${safeText(item.empresa, 'Sin empresa')} · Rastreo: ${safeText(item.codigo_rastreo, 'Sin rastreo')}
                  </div>

                  <div class="text-[11px] text-slate-500 mt-3">
                    Residente: ${safeText(item.residente_nombre, 'No asignado')} · Guardia: ${safeText(item.guardia_nombre, '—')} · ${safeText(item.created_at, '')}
                  </div>

                  ${
                    item.notas
                      ? `<div class="text-xs text-slate-400 mt-2">${safeText(item.notas, '')}</div>`
                      : ''
                  }
                </div>

                <div class="shrink-0 flex flex-col gap-2">
                  <button
                    type="button"
                    class="js-view-pkg rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs hover:bg-slate-50"
                    data-pkg='${escapeHtml(JSON.stringify(item))}'>
                    Ver detalle
                  </button>

                  ${
                    normalize(item.estado) === 'registrado'
                      ? `
                        <button
                          type="button"
                          class="js-mark-pkg rounded-lg bg-emerald-600 text-white px-3 py-2 text-xs hover:bg-emerald-700"
                          data-id="${escapeHtml(String(item.id))}"
                          data-state="entregado">
                          Marcar entregado
                        </button>

                        <button
                          type="button"
                          class="js-mark-pkg rounded-lg bg-rose-600 text-white px-3 py-2 text-xs hover:bg-rose-700"
                          data-id="${escapeHtml(String(item.id))}"
                          data-state="devuelto">
                          Marcar devuelto
                        </button>
                      `
                      : `
                        <button
                          type="button"
                          class="js-mark-pkg rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs hover:bg-slate-50"
                          data-id="${escapeHtml(String(item.id))}"
                          data-state="registrado">
                          Reabrir pendiente
                        </button>
                      `
                  }
                </div>
              </div>
            </div>
          `).join('')}
        </div>
      `;

      els.list.querySelectorAll('.js-view-pkg').forEach((btn) => {
        btn.addEventListener('click', () => {
          try {
            openDetailModal(JSON.parse(btn.dataset.pkg));
          } catch (_) {}
        });
      });

      els.list.querySelectorAll('.js-mark-pkg').forEach((btn) => {
        btn.addEventListener('click', () => {
          updateStatus(btn.dataset.id, btn.dataset.state);
        });
      });

      renderPagination();
    }

    async function load() {
      if (!isAlive()) return;

      setLoading(true);

      try {
        const json = await fetchJSON(`${API}paqueteria.php?action=list`);
        if (!isAlive()) return;

        state.items = Array.isArray(json.data?.items) ? json.data.items : [];
        applyFilters();
        renderList();
      } catch (e) {
        if (!isAlive()) return;

        els.list.innerHTML = `
          <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            Error: ${safeText(e.message, 'No se pudo cargar la paquetería.')}
          </div>
        `;

        if (els.pagination) els.pagination.innerHTML = '';
      } finally {
        state.loading = false;
      }
    }

    function openDetailModal(item) {
      openModal('Detalle del paquete', `
        <div class="space-y-3 text-sm">
          <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
            <div class="text-xs text-slate-500">Descripción</div>
            <div class="mt-1 font-semibold text-slate-800">${safeText(item.descripcion)}</div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
              <div class="text-xs text-slate-500">Unidad</div>
              <div class="mt-1 text-slate-800">${safeText(item.unidad_clave)}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
              <div class="text-xs text-slate-500">Estado</div>
              <div class="mt-1 text-slate-800">${safeText(stateLabel(item.estado))}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
              <div class="text-xs text-slate-500">Empresa</div>
              <div class="mt-1 text-slate-800">${safeText(item.empresa, 'Sin empresa')}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
              <div class="text-xs text-slate-500">Rastreo</div>
              <div class="mt-1 text-slate-800 break-all">${safeText(item.codigo_rastreo, 'Sin rastreo')}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
              <div class="text-xs text-slate-500">Residente</div>
              <div class="mt-1 text-slate-800">${safeText(item.residente_nombre, 'No asignado')}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
              <div class="text-xs text-slate-500">Guardia</div>
              <div class="mt-1 text-slate-800">${safeText(item.guardia_nombre)}</div>
            </div>
          </div>

          ${
            item.notas
              ? `
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3">
                  <div class="text-xs text-slate-500">Notas</div>
                  <div class="mt-1 text-slate-800 whitespace-pre-line">${safeText(item.notas, '')}</div>
                </div>
              `
              : ''
          }

          <div class="flex justify-end">
            <button type="button" id="pkgDetailClose" class="rounded-xl bg-[#4E7287] text-white px-4 py-2 text-sm">
              Cerrar
            </button>
          </div>
        </div>
      `);

      document.getElementById('pkgDetailClose')?.addEventListener('click', closeModal);
    }

    async function updateStatus(id, nextState) {
      const pkgId = Number(id || 0);
      if (!pkgId) return;

      const token = ++state.actionToken;
      const fd = new FormData();
      fd.set('action', 'update_status');
      fd.set('id', String(pkgId));
      fd.set('estado', String(nextState || 'registrado'));

      try {
        await fetchJSON(`${API}paqueteria.php`, {
          method: 'POST',
          body: fd,
        });

        if (!isAlive() || token !== state.actionToken) return;
        await load();
      } catch (e) {
        if (!isAlive() || token !== state.actionToken) return;

        openModal('Error', `
          <div class="space-y-4">
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
              ${safeText(e.message, 'No se pudo actualizar el estado del paquete.')}
            </div>
            <div class="flex justify-end">
              <button type="button" id="pkgErrClose" class="rounded-xl bg-[#4E7287] text-white px-4 py-2 text-sm">
                Entendido
              </button>
            </div>
          </div>
        `);

        document.getElementById('pkgErrClose')?.addEventListener('click', closeModal);
      }
    }

    async function getUnidades() {
      const json = await fetchJSON(`${API}paqueteria.php?action=unidades`);
      return Array.isArray(json.data?.items) ? json.data.items : [];
    }

    function openNewModal() {
      openModal('Nuevo paquete', `
        <form id="frmNewPkg" class="space-y-3">
          <div>
            <label class="text-xs text-slate-500">Unidad *</label>
            <select id="pkgUnidad" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="">Cargando unidades…</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-slate-500">Empresa</label>
            <input id="pkgEmpresa" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" placeholder="Amazon, DHL..." />
          </div>

          <div>
            <label class="text-xs text-slate-500">Descripción *</label>
            <input id="pkgDesc" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>

          <div>
            <label class="text-xs text-slate-500">Rastreo</label>
            <input id="pkgTrack" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>

          <div>
            <label class="text-xs text-slate-500">Notas</label>
            <textarea id="pkgNotas" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" rows="3"></textarea>
          </div>

          <div id="pkgErr" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>

          <div class="flex items-center justify-end gap-2">
            <button type="button" id="pkgCancel" class="rounded-xl border border-slate-200 px-4 py-2 text-sm">Cancelar</button>
            <button type="submit" id="pkgSave" class="rounded-xl bg-[#4E7287] text-white px-4 py-2 text-sm shadow">Guardar</button>
          </div>
        </form>
      `);

      const $ = (id) => document.getElementById(id);
      const err = $('pkgErr');
      const unidadSel = $('pkgUnidad');
      const saveBtn = $('pkgSave');

      function setErr(message = '') {
        if (!err) return;

        if (!message) {
          err.classList.add('hidden');
          err.textContent = '';
          return;
        }

        err.classList.remove('hidden');
        err.textContent = message;
      }

      function setSaving(on) {
        if (!saveBtn) return;
        saveBtn.disabled = on;
        saveBtn.textContent = on ? 'Guardando…' : 'Guardar';
      }

      $('pkgCancel')?.addEventListener('click', closeModal);

      getUnidades()
        .then((items) => {
          if (!unidadSel) return;
          unidadSel.innerHTML =
            `<option value="">Selecciona unidad</option>` +
            items.map((item) => `
              <option value="${escapeHtml(String(item.id))}">
                ${safeText(item.clave)}${item.residente_nombre ? ' · ' + safeText(item.residente_nombre, '') : ''}
              </option>
            `).join('');
        })
        .catch((e) => {
          setErr(e.message || 'No se pudieron cargar las unidades.');
          if (unidadSel) {
            unidadSel.innerHTML = `<option value="">Sin unidades disponibles</option>`;
          }
        });

      $('frmNewPkg')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        setErr('');
        setSaving(true);

        const unidadId = unidadSel?.value || '';
        const descripcion = $('pkgDesc')?.value?.trim() || '';

        if (!unidadId) {
          setSaving(false);
          return setErr('Selecciona una unidad.');
        }

        if (!descripcion) {
          setSaving(false);
          return setErr('La descripción es obligatoria.');
        }

        try {
          const fd = new FormData();
          fd.set('action', 'create');
          fd.set('unidad_id', unidadId);
          fd.set('empresa', $('pkgEmpresa')?.value?.trim() || '');
          fd.set('descripcion', descripcion);
          fd.set('codigo_rastreo', $('pkgTrack')?.value?.trim() || '');
          fd.set('notas', $('pkgNotas')?.value?.trim() || '');

          await fetchJSON(`${API}paqueteria.php`, {
            method: 'POST',
            body: fd,
          });

          closeModal();
          await load();
        } catch (e) {
          setErr(e.message || 'No se pudo guardar el paquete.');
        } finally {
          setSaving(false);
        }
      });
    }

    function onSubmitFilters(ev) {
      ev.preventDefault();
      applyFilters();
      renderList();
    }

    function onClearFilters() {
      if (els.search) els.search.value = '';
      if (els.estado) els.estado.value = '';
      applyFilters();
      renderList();
    }

    function onRefresh() {
      load();
    }

    function onNewClick() {
      openNewModal();
    }

    function bindEvents() {
      els.frm?.addEventListener('submit', onSubmitFilters);
      els.btnClear?.addEventListener('click', onClearFilters);
      els.btnRefresh?.addEventListener('click', onRefresh);
      els.btnNew?.addEventListener('click', onNewClick);

      els.estado?.addEventListener('change', () => {
        applyFilters();
        renderList();
      });

      els.search?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          applyFilters();
          renderList();
        }
      });
    }

    function unbindEvents() {
      els.frm?.removeEventListener('submit', onSubmitFilters);
      els.btnClear?.removeEventListener('click', onClearFilters);
      els.btnRefresh?.removeEventListener('click', onRefresh);
      els.btnNew?.removeEventListener('click', onNewClick);
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
