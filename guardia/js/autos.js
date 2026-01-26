(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.autos = function ({ API, fetchJSON, openModal, closeModal, escapeHtml }) {
    const root = document.getElementById('autosView');
    if (!root) return;

    const els = {
      list: document.getElementById('autosList'),
      frm: document.getElementById('frmAutosFilter'),
      fPlacas: document.getElementById('fPlacas'),
      fModelo: document.getElementById('fModelo'),
      btnNew: document.getElementById('btnNuevoAuto'),
    };

    /* ===================== helpers UI ===================== */
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

    /* ===================== API helpers ===================== */
    async function getUnidades() {
      const json = await fetchJSON(`${API}unidades.php`);
      return json.data?.items || [];
    }

    async function getPropietarios() {
      const json = await fetchJSON(`${API}propietarios.php`);
      return json.data?.items || [];
    }

    /* ===================== render list ===================== */
    function render(items) {
      if (!items || items.length === 0) {
        els.list.innerHTML = `<div class="text-xs text-slate-500">No hay autos.</div>`;
        return;
      }

      els.list.innerHTML = `
        <div class="space-y-2">
          ${items.map(a => `
            <button type="button"
              class="w-full text-left rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 hover:bg-slate-100"
              data-auto='${escapeHtml(JSON.stringify(a))}'>
              <div class="flex justify-between">
                <div class="font-semibold">${escapeHtml(a.placas)}</div>
                <div class="text-xs text-slate-500">${escapeHtml(a.unidad_clave || '—')}</div>
              </div>
              <div class="text-xs text-slate-600 mt-1">
                ${escapeHtml(a.modelo || '—')} · ${escapeHtml(a.color || '—')} · ${escapeHtml(a.propietario_nombre || '—')}
              </div>
            </button>
          `).join('')}
        </div>
      `;

      els.list.querySelectorAll('button').forEach(btn => {
        btn.onclick = () => openEditModal(JSON.parse(btn.dataset.auto));
      });
    }

    async function load() {
      els.list.textContent = 'Cargando…';
      const q = new URLSearchParams({ action: 'list' });
      if (els.fPlacas.value) q.set('placas', els.fPlacas.value);
      if (els.fModelo.value) q.set('modelo', els.fModelo.value);

      const json = await fetchJSON(`${API}autos.php?${q.toString()}`);
      render(json.data?.items || []);
    }

    /* ===================== crear UNIDAD ===================== */
    function openCreateUnidad(onDone) {
      openModal('Nueva unidad', `
        <form id="frmUnidad" class="space-y-2">
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
            <button class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Crear</button>
          </div>
          <div id="msgU" class="text-xs"></div>
        </form>
      `);

      document.getElementById('cancelU').onclick = closeModal;

      document.getElementById('frmUnidad').onsubmit = async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.set('action', 'create');
        const json = await fetchJSON(`${API}unidades.php`, { method: 'POST', body: fd });
        closeModal();
        onDone(json.data.item);
      };
    }

    /* ===================== crear PROPIETARIO ===================== */
    function openCreateProp(defaultUnidad, onDone) {
      openModal('Nuevo propietario', `
        <form id="frmProp" class="space-y-2">
          ${formRow('Nombre *', `<input name="name" class="w-full rounded-xl border px-3 py-2" required />`)}
          ${formRow('Email *', `<input name="email" type="email" class="w-full rounded-xl border px-3 py-2" required />`)}
          ${formRow('Teléfono', `<input name="telefono" class="w-full rounded-xl border px-3 py-2" />`)}
          <input type="hidden" name="unidad_id" value="${escapeHtml(defaultUnidad || '')}" />
          <div class="flex justify-end gap-2">
            <button type="button" id="cancelP" class="border px-4 py-2 rounded-xl">Cancelar</button>
            <button class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Crear</button>
          </div>
          <div id="msgP" class="text-xs"></div>
        </form>
      `);

      document.getElementById('cancelP').onclick = closeModal;

      document.getElementById('frmProp').onsubmit = async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.set('action', 'create');
        const json = await fetchJSON(`${API}propietarios.php`, { method: 'POST', body: fd });
        alert(`Contraseña temporal: ${json.data.temp_password}`);
        closeModal();
        onDone(json.data.item);
      };
    }

    /* ===================== crear AUTO ===================== */
    async function openCreateModal() {
      const unidades = await getUnidades();
      const props = await getPropietarios();

      openModal('Nuevo auto', `
        <form id="frmAuto" class="space-y-2">
          ${formRow('Placas *', `<input name="placas" class="w-full rounded-xl border px-3 py-2" required />`)}
          ${formRow('Modelo', `<input name="modelo" class="w-full rounded-xl border px-3 py-2" />`)}
          ${formRow('Color', `<input name="color" class="w-full rounded-xl border px-3 py-2" />`)}

          <div class="rounded-xl bg-slate-50 border px-3 py-2">
            <div class="flex justify-between text-xs text-slate-500">
              Unidad <button type="button" id="addUnidad" class="text-[#4E7287]">+ Nueva</button>
            </div>
            <select name="unidad_id" id="selUnidad" class="w-full border rounded-xl px-3 py-2 mt-1"></select>
          </div>

          <div class="rounded-xl bg-slate-50 border px-3 py-2">
            <div class="flex justify-between text-xs text-slate-500">
              Propietario <button type="button" id="addProp" class="text-[#4E7287]">+ Nuevo</button>
            </div>
            <select name="propietario_user_id" id="selProp" class="w-full border rounded-xl px-3 py-2 mt-1"></select>
          </div>

          ${formRow('Notas', `<textarea name="notas" class="w-full rounded-xl border px-3 py-2"></textarea>`)}

          <div class="flex justify-end gap-2">
            <button type="button" id="cancelA" class="border px-4 py-2 rounded-xl">Cancelar</button>
            <button class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Guardar</button>
          </div>
        </form>
      `);

      const selU = document.getElementById('selUnidad');
      const selP = document.getElementById('selProp');
      fillSelect(selU, unidades, unidadLabel);
      fillSelect(selP, props, propLabel);

      document.getElementById('addUnidad').onclick = () =>
        openCreateUnidad(u => fillSelect(selU, [...unidades, u], unidadLabel, u.id));

      document.getElementById('addProp').onclick = () =>
        openCreateProp(selU.value, p => fillSelect(selP, [...props, p], propLabel, p.id));

      document.getElementById('cancelA').onclick = closeModal;

      document.getElementById('frmAuto').onsubmit = async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.set('action', 'create');
        await fetchJSON(`${API}autos.php`, { method: 'POST', body: fd });
        closeModal();
        load();
      };
    }

    /* ===================== editar AUTO ===================== */
    async function openEditModal(a) {
      const unidades = await getUnidades();
      const props = await getPropietarios();

      openModal('Auto', `
        <form id="frmEdit" class="space-y-2">
          <input type="hidden" name="auto_id" value="${a.id}" />
          ${formRow('Placas *', `<input name="placas" value="${escapeHtml(a.placas)}" class="w-full border px-3 py-2 rounded-xl" required />`)}
          ${formRow('Modelo', `<input name="modelo" value="${escapeHtml(a.modelo || '')}" class="w-full border px-3 py-2 rounded-xl" />`)}
          ${formRow('Color', `<input name="color" value="${escapeHtml(a.color || '')}" class="w-full border px-3 py-2 rounded-xl" />`)}
          ${formRow('Unidad', `<select name="unidad_id" id="selUEdit" class="w-full border px-3 py-2 rounded-xl"></select>`)}
          ${formRow('Propietario', `<select name="propietario_user_id" id="selPEdit" class="w-full border px-3 py-2 rounded-xl"></select>`)}
          <div class="flex justify-between">
            <button type="button" id="delA" class="text-rose-700">Desactivar</button>
            <button class="bg-[#4E7287] text-white px-4 py-2 rounded-xl">Guardar</button>
          </div>
        </form>
      `);

      fillSelect(document.getElementById('selUEdit'), unidades, unidadLabel, a.unidad_id);
      fillSelect(document.getElementById('selPEdit'), props, propLabel, a.propietario_user_id);

      document.getElementById('delA').onclick = async () => {
        if (!confirm('¿Desactivar auto?')) return;
        const fd = new FormData();
        fd.set('action', 'delete');
        fd.set('auto_id', a.id);
        await fetchJSON(`${API}autos.php`, { method: 'POST', body: fd });
        closeModal();
        load();
      };

      document.getElementById('frmEdit').onsubmit = async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.set('action', 'update');
        await fetchJSON(`${API}autos.php`, { method: 'POST', body: fd });
        closeModal();
        load();
      };
    }

    /* ===================== events ===================== */
    els.frm.onsubmit = e => { e.preventDefault(); load(); };
    els.btnNew.onclick = openCreateModal;
    load();
  };
})();
