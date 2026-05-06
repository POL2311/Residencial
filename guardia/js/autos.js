(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.autos = function ({ API, fetchJSON, openModal, closeModal, escapeHtml }) {
    const root = document.getElementById('autosView');
    if (!root) return;

    const els = {
      list: document.getElementById('autosList'),
      pagination: document.getElementById('autosPagination'),
      frm: document.getElementById('frmAutosFilter'),
      fPlacas: document.getElementById('fPlacas'),
      fModelo: document.getElementById('fModelo'),
      fColor: document.getElementById('fColor'),
      btnClear: document.getElementById('btnClearAutosFilters'),
      btnNew: document.getElementById('btnNuevoAuto'),
    };

    const state = {
      destroyed: false,
      items: [],
      filteredItems: [],
      page: 1,
      perPage: 3,
      loading: false,
    };

    function isAlive() {
      return !state.destroyed;
    }

    function formRow(label, inputHtml) {
      return `
        <div class="rounded-xl bg-slate-50 px-3 py-2 border border-slate-200">
          <div class="text-xs text-slate-500">${label}</div>
          <div class="mt-1">${inputHtml}</div>
        </div>
      `;
    }

    function unidadLabel(u) {
      return `${u.clave || '—'}${u.tipo ? ' · ' + u.tipo : ''}${u.torre ? ' · ' + u.torre : ''}${u.nivel ? ' · nivel ' + u.nivel : ''}`;
    }

    function propLabel(p) {
      return `${p.name || '—'}${p.email ? ' · ' + p.email : ''}`;
    }

    function fillSelect(sel, items, labelFn, selected = '') {
      if (!sel) return;
      sel.innerHTML =
        `<option value="">Selecciona…</option>` +
        items.map(i => `
          <option value="${escapeHtml(String(i.id))}" ${String(i.id) === String(selected) ? 'selected' : ''}>
            ${escapeHtml(labelFn(i))}
          </option>
        `).join('');
    }

    function normalize(val) {
      return String(val || '').trim().toLowerCase();
    }

    function safeText(v, fallback = '—') {
      const s = String(v ?? '').trim();
      return escapeHtml(s || fallback);
    }

    function setLoading(on) {
      state.loading = on;
      if (!els.list) return;

      if (on) {
        els.list.innerHTML = `
          <div class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
            Cargando autos…
          </div>
        `;
      }
    }

    function setModalMessage(id, msg = '', isError = true) {
      const el = document.getElementById(id);
      if (!el) return;

      if (!msg) {
        el.className = 'hidden rounded-xl px-3 py-2 text-sm';
        el.textContent = '';
        return;
      }

      el.className =
        'rounded-xl px-3 py-2 text-sm ' +
        (isError
          ? 'bg-rose-50 border border-rose-200 text-rose-700'
          : 'bg-emerald-50 border border-emerald-200 text-emerald-700');

      el.textContent = msg;
    }

    function setModalSubmitting(buttonId, on, loadingText = 'Guardando…', defaultText = 'Guardar') {
      const btn = document.getElementById(buttonId);
      if (!btn) return;
      btn.disabled = on;
      btn.textContent = on ? loadingText : defaultText;
    }

    async function getUnidades() {
      const json = await fetchJSON(`${API}unidades.php`);
      return json.data?.items || [];
    }

    async function getPropietarios() {
      const json = await fetchJSON(`${API}propietarios.php`);
      return json.data?.items || [];
    }

    function applyFilters() {
      const fPlacas = normalize(els.fPlacas?.value);
      const fModelo = normalize(els.fModelo?.value);
      const fColor = normalize(els.fColor?.value);

      state.filteredItems = state.items.filter((a) => {
        const placas = normalize(a.placas);
        const modelo = normalize(a.modelo);
        const color = normalize(a.color);

        const okPlacas = !fPlacas || placas.includes(fPlacas);
        const okModelo = !fModelo || modelo.includes(fModelo);
        const okColor = !fColor || color.includes(fColor);

        return okPlacas && okModelo && okColor;
      });

      state.page = 1;
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
        render();
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
          render();
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
        render();
      });
      els.pagination.appendChild(next);
    }

    function render(itemsOverride = null) {
      if (!els.list || !isAlive()) return;

      const source = itemsOverride || state.filteredItems;
      const pageItems = paginate(source, state.page, state.perPage);

      if (!source.length) {
        els.list.innerHTML = `
          <div class="rounded-xl bg-slate-50 p-5 text-sm text-slate-500">
            No hay autos que coincidan con los filtros actuales.
          </div>
        `;
        renderPagination();
        return;
      }

      els.list.innerHTML = `
        <div class="space-y-3">
          ${pageItems.map(a => `
            <button type="button"
              class="js-auto-card w-full text-left rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 hover:bg-slate-100 transition"
              data-auto='${escapeHtml(JSON.stringify(a))}'>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <div class="font-semibold text-slate-800 text-base">${safeText(a.placas)}</div>
                  <span class="inline-flex rounded-full bg-slate-200 px-2 py-1 text-[11px] text-slate-700">
                    ${safeText(a.unidad_clave)}
                  </span>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600">
                  <div><span class="text-slate-500">Placas:</span> <span class="font-medium text-slate-800">${safeText(a.placas)}</span></div>
                  <div><span class="text-slate-500">Modelo:</span> <span class="font-medium text-slate-800">${safeText(a.modelo)}</span></div>
                  <div><span class="text-slate-500">Color:</span> <span class="font-medium text-slate-800">${safeText(a.color)}</span></div>
                  <div><span class="text-slate-500">Propietario:</span> <span class="font-medium text-slate-800">${safeText(a.propietario_nombre)}</span></div>
                </div>

                ${
                  a.notas
                    ? `<div class="text-xs text-slate-400 mt-3">${safeText(a.notas, '')}</div>`
                    : ''
                }
              </div>
            </button>
          `).join('')}
        </div>
      `;

      els.list.querySelectorAll('.js-auto-card').forEach(btn => {
        btn.addEventListener('click', () => {
          try {
            openEditModal(JSON.parse(btn.dataset.auto));
          } catch (_) {}
        });
      });

      renderPagination();
    }

    async function load() {
      if (!isAlive()) return;

      setLoading(true);

      try {
        const q = new URLSearchParams({ action: 'list' });
        if (els.fPlacas?.value?.trim()) q.set('placas', els.fPlacas.value.trim());
        if (els.fModelo?.value?.trim()) q.set('modelo', els.fModelo.value.trim());
        if (els.fColor?.value?.trim()) q.set('color', els.fColor.value.trim());

        const json = await fetchJSON(`${API}autos.php?${q.toString()}`);
        if (!isAlive()) return;

        state.items = json.data?.items || [];
        applyFilters();
        render();
      } catch (e) {
        if (!isAlive()) return;

        els.list.innerHTML = `
          <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            No se pudo cargar el listado de autos.
          </div>
        `;
        if (els.pagination) els.pagination.innerHTML = '';
      } finally {
        state.loading = false;
      }
    }

    function showConfirm({
      title = 'Confirmar acción',
      message = '¿Deseas continuar?',
      acceptText = 'Aceptar',
      cancelText = 'Cancelar',
      onAccept,
    }) {
      openModal(title, `
        <div class="space-y-4">
          <div class="text-sm text-slate-700">${escapeHtml(message)}</div>
          <div class="flex justify-end gap-2">
            <button type="button" id="confirmCancel" class="border px-4 py-2 rounded-xl">${escapeHtml(cancelText)}</button>
            <button type="button" id="confirmAccept" class="bg-rose-600 text-white px-4 py-2 rounded-xl">${escapeHtml(acceptText)}</button>
          </div>
        </div>
      `);

      document.getElementById('confirmCancel')?.addEventListener('click', closeModal);
      document.getElementById('confirmAccept')?.addEventListener('click', async () => {
        try {
          await onAccept?.();
        } finally {
          closeModal();
        }
      });
    }

    function showTempPasswordModal(tempPassword, item, onDone) {
      openModal('Propietario creado', `
        <div class="space-y-4">
          <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
            <div class="font-semibold text-emerald-700">Propietario creado correctamente</div>
            <div class="text-sm text-slate-600 mt-1">Contraseña temporal:</div>
            <div class="mt-2 break-all rounded-lg bg-white border px-3 py-2 font-mono text-sm text-slate-800">
              ${escapeHtml(tempPassword || '—')}
            </div>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" id="btnTempPwdOk" class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">
              Continuar
            </button>
          </div>
        </div>
      `);

      document.getElementById('btnTempPwdOk')?.addEventListener('click', () => {
        closeModal();
        onDone?.(item);
      });
    }

    function openCreateUnidad(onDone) {
      openModal('Nueva unidad', `
        <form id="frmUnidad" class="space-y-3">
          <div id="msgU" class="hidden rounded-xl px-3 py-2 text-sm"></div>

          ${formRow('Clave *', `<input name="clave" class="w-full rounded-xl border px-3 py-2" required />`)}

          ${formRow('Tipo',
            `<select name="tipo" class="w-full rounded-xl border px-3 py-2">
              <option value="casa">Casa</option>
              <option value="departamento">Departamento</option>
              <option value="local">Local</option>
              <option value="otro">Otro</option>
            </select>`
          )}

          <div class="flex justify-end gap-2">
            <button type="button" id="cancelU" class="border px-4 py-2 rounded-xl">Cancelar</button>
            <button type="submit" id="submitU" class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Crear</button>
          </div>
        </form>
      `);

      document.getElementById('cancelU')?.addEventListener('click', closeModal);

      document.getElementById('frmUnidad')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setModalMessage('msgU', '');
        setModalSubmitting('submitU', true, 'Creando…', 'Crear');

        try {
          const fd = new FormData(e.target);
          fd.set('action', 'create');
          const json = await fetchJSON(`${API}unidades.php`, { method: 'POST', body: fd });
          closeModal();
          onDone?.(json.data.item);
        } catch (err) {
          setModalMessage('msgU', err.message || 'No se pudo crear la unidad.', true);
        } finally {
          setModalSubmitting('submitU', false, 'Creando…', 'Crear');
        }
      });
    }

    function openCreateProp(defaultUnidad, onDone) {
      openModal('Nuevo propietario', `
        <form id="frmProp" class="space-y-3">
          <div id="msgP" class="hidden rounded-xl px-3 py-2 text-sm"></div>

          ${formRow('Nombre *', `<input name="name" class="w-full rounded-xl border px-3 py-2" required />`)}
          ${formRow('Email *', `<input name="email" type="email" class="w-full rounded-xl border px-3 py-2" required />`)}
          ${formRow('Teléfono', `<input name="telefono" class="w-full rounded-xl border px-3 py-2" />`)}
          <input type="hidden" name="unidad_id" value="${escapeHtml(defaultUnidad || '')}" />

          <div class="flex justify-end gap-2">
            <button type="button" id="cancelP" class="border px-4 py-2 rounded-xl">Cancelar</button>
            <button type="submit" id="submitP" class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Crear</button>
          </div>
        </form>
      `);

      document.getElementById('cancelP')?.addEventListener('click', closeModal);

      document.getElementById('frmProp')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setModalMessage('msgP', '');
        setModalSubmitting('submitP', true, 'Creando…', 'Crear');

        try {
          const fd = new FormData(e.target);
          fd.set('action', 'create');
          const json = await fetchJSON(`${API}propietarios.php`, { method: 'POST', body: fd });

          showTempPasswordModal(
            json.data?.temp_password || '',
            json.data?.item,
            (item) => onDone?.(item)
          );
        } catch (err) {
          setModalMessage('msgP', err.message || 'No se pudo crear el propietario.', true);
        } finally {
          setModalSubmitting('submitP', false, 'Creando…', 'Crear');
        }
      });
    }

    async function openCreateModal() {
      const unidades = await getUnidades();
      const props = await getPropietarios();

      openModal('Nuevo auto', `
        <form id="frmAuto" class="space-y-3">
          <div id="msgA" class="hidden rounded-xl px-3 py-2 text-sm"></div>

          ${formRow('Placas *', `<input name="placas" class="w-full rounded-xl border px-3 py-2" required />`)}
          ${formRow('Modelo', `<input name="modelo" class="w-full rounded-xl border px-3 py-2" />`)}
          ${formRow('Color', `<input name="color" class="w-full rounded-xl border px-3 py-2" />`)}

          <div class="rounded-xl bg-slate-50 border px-3 py-2">
            <div class="flex justify-between text-xs text-slate-500">
              <span>Unidad</span>
              <button type="button" id="addUnidad" class="text-[#4E7287]">+ Nueva</button>
            </div>
            <select name="unidad_id" id="selUnidad" class="w-full border rounded-xl px-3 py-2 mt-1"></select>
          </div>

          <div class="rounded-xl bg-slate-50 border px-3 py-2">
            <div class="flex justify-between text-xs text-slate-500">
              <span>Propietario</span>
              <button type="button" id="addProp" class="text-[#4E7287]">+ Nuevo</button>
            </div>
            <select name="propietario_user_id" id="selProp" class="w-full border rounded-xl px-3 py-2 mt-1"></select>
          </div>

          ${formRow('Notas', `<textarea name="notas" class="w-full rounded-xl border px-3 py-2"></textarea>`)}

          <div class="flex justify-end gap-2">
            <button type="button" id="cancelA" class="border px-4 py-2 rounded-xl">Cancelar</button>
            <button type="submit" id="submitA" class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Guardar</button>
          </div>
        </form>
      `);

      const selU = document.getElementById('selUnidad');
      const selP = document.getElementById('selProp');

      let unidadesState = [...unidades];
      let propsState = [...props];

      fillSelect(selU, unidadesState, unidadLabel);
      fillSelect(selP, propsState, propLabel);

      document.getElementById('addUnidad')?.addEventListener('click', () =>
        openCreateUnidad((u) => {
          unidadesState = [...unidadesState, u];
          fillSelect(selU, unidadesState, unidadLabel, u.id);
        })
      );

      document.getElementById('addProp')?.addEventListener('click', () =>
        openCreateProp(selU?.value, (p) => {
          propsState = [...propsState, p];
          fillSelect(selP, propsState, propLabel, p.id);
        })
      );

      document.getElementById('cancelA')?.addEventListener('click', closeModal);

      document.getElementById('frmAuto')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setModalMessage('msgA', '');
        setModalSubmitting('submitA', true, 'Guardando…', 'Guardar');

        try {
          const fd = new FormData(e.target);
          fd.set('action', 'create');
          await fetchJSON(`${API}autos.php`, { method: 'POST', body: fd });
          closeModal();
          await load();
        } catch (err) {
          setModalMessage('msgA', err.message || 'No se pudo guardar el auto.', true);
        } finally {
          setModalSubmitting('submitA', false, 'Guardando…', 'Guardar');
        }
      });
    }

    async function openEditModal(a) {
      const unidades = await getUnidades();
      const props = await getPropietarios();

      openModal('Editar auto', `
        <form id="frmEdit" class="space-y-3">
          <div id="msgE" class="hidden rounded-xl px-3 py-2 text-sm"></div>

          <input type="hidden" name="auto_id" value="${escapeHtml(a.id)}" />

          ${formRow('Placas *', `<input name="placas" value="${safeText(a.placas, '')}" class="w-full border px-3 py-2 rounded-xl" required />`)}
          ${formRow('Modelo', `<input name="modelo" value="${safeText(a.modelo, '')}" class="w-full border px-3 py-2 rounded-xl" />`)}
          ${formRow('Color', `<input name="color" value="${safeText(a.color, '')}" class="w-full border px-3 py-2 rounded-xl" />`)}
          ${formRow('Unidad', `<select name="unidad_id" id="selUEdit" class="w-full border px-3 py-2 rounded-xl"></select>`)}
          ${formRow('Propietario', `<select name="propietario_user_id" id="selPEdit" class="w-full border px-3 py-2 rounded-xl"></select>`)}
          ${formRow('Notas', `<textarea name="notas" class="w-full border px-3 py-2 rounded-xl">${safeText(a.notas || '', '')}</textarea>`)}

          <div class="flex justify-between items-center">
            <button type="button" id="delA" class="text-rose-700 hover:underline">
              Desactivar
            </button>

            <div class="flex gap-2">
              <button type="button" id="cancelEdit" class="border px-4 py-2 rounded-xl">Cancelar</button>
              <button type="submit" id="submitE" class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Guardar</button>
            </div>
          </div>
        </form>
      `);

      fillSelect(document.getElementById('selUEdit'), unidades, unidadLabel, a.unidad_id);
      fillSelect(document.getElementById('selPEdit'), props, propLabel, a.propietario_user_id);

      document.getElementById('cancelEdit')?.addEventListener('click', closeModal);

      document.getElementById('delA')?.addEventListener('click', () => {
        showConfirm({
          title: 'Desactivar auto',
          message: '¿Deseas desactivar este auto? El registro dejará de aparecer en el listado activo.',
          acceptText: 'Sí, desactivar',
          cancelText: 'Cancelar',
          onAccept: async () => {
            const fd = new FormData();
            fd.set('action', 'delete');
            fd.set('auto_id', a.id);
            await fetchJSON(`${API}autos.php`, { method: 'POST', body: fd });
            await load();
          }
        });
      });

      document.getElementById('frmEdit')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        setModalMessage('msgE', '');
        setModalSubmitting('submitE', true, 'Guardando…', 'Guardar');

        try {
          const fd = new FormData(e.target);
          fd.set('action', 'update');
          await fetchJSON(`${API}autos.php`, { method: 'POST', body: fd });
          closeModal();
          await load();
        } catch (err) {
          setModalMessage('msgE', err.message || 'No se pudo actualizar el auto.', true);
        } finally {
          setModalSubmitting('submitE', false, 'Guardando…', 'Guardar');
        }
      });
    }

    function onSubmitFilters(e) {
      e.preventDefault();
      applyFilters();
      render();
    }

    function onClearFilters() {
      if (els.fPlacas) els.fPlacas.value = '';
      if (els.fModelo) els.fModelo.value = '';
      if (els.fColor) els.fColor.value = '';
      applyFilters();
      render();
    }

    function onNewClick() {
      openCreateModal();
    }

    function bindEvents() {
      els.frm?.addEventListener('submit', onSubmitFilters);
      els.btnClear?.addEventListener('click', onClearFilters);
      els.btnNew?.addEventListener('click', onNewClick);

      [els.fPlacas, els.fModelo, els.fColor].forEach((input) => {
        input?.addEventListener('keydown', (e) => {
          if (e.key === 'Enter') {
            e.preventDefault();
            applyFilters();
            render();
          }
        });
      });
    }

    function unbindEvents() {
      els.frm?.removeEventListener('submit', onSubmitFilters);
      els.btnClear?.removeEventListener('click', onClearFilters);
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
