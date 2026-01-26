// guardia/js/incidencias.js
(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.incidencias = function ({ API, fetchJSON, openModal, closeModal, escapeHtml }) {
    const root = document.getElementById('incidenciasView');
    if (!root) return;

    const els = {
      list: document.getElementById('incList'),
      btnNew: document.getElementById('btnNuevaInc'),
    };

    function renderList(items) {
      if (!els.list) return;
      if (!items || items.length === 0) {
        els.list.innerHTML = `<div class="text-xs text-slate-500">Aún no hay incidencias.</div>`;
        return;
      }

      els.list.innerHTML = `
        <div class="space-y-2">
          ${items.map(i => {
            const badge =
              (i.prioridad === 'alta') ? 'bg-rose-100 text-rose-700 border-rose-200' :
              (i.prioridad === 'media') ? 'bg-amber-100 text-amber-700 border-amber-200' :
              'bg-slate-100 text-slate-700 border-slate-200';

            return `
              <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                <div class="flex items-center justify-between gap-2">
                  <div class="font-semibold text-slate-800">${escapeHtml(i.titulo || '—')}</div>
                  <span class="text-[11px] px-2 py-0.5 rounded-full border ${badge}">
                    ${escapeHtml(i.prioridad || '—')}
                  </span>
                </div>
                <div class="text-[11px] text-slate-600 mt-1">
                  Unidad: <b>${escapeHtml(i.unidad_clave || '—')}</b> · Estado: ${escapeHtml(i.estado || '—')}
                </div>
                <div class="text-[11px] text-slate-500 mt-1">${escapeHtml(i.created_at || '')}</div>
              </div>
            `;
          }).join('')}
        </div>
      `;
    }

    async function load() {
      if (!els.list) return;
      els.list.textContent = 'Cargando…';
      try {
        const json = await fetchJSON(`${API}incidencias.php`);
        renderList(json.data?.items || []);
      } catch (e) {
        els.list.innerHTML = `<div class="text-xs text-rose-700">Error: ${escapeHtml(e.message)}</div>`;
      }
    }

    function openNewModal() {
      openModal('Nueva incidencia', `
        <form id="frmNewInc" class="space-y-3">
          <div>
            <label class="text-xs text-slate-500">Unidad *</label>
            <select id="incUnidad" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></select>
          </div>

          <div>
            <label class="text-xs text-slate-500">Tipo</label>
            <select id="incTipo" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="seguridad">Seguridad</option>
              <option value="ruido">Ruido</option>
              <option value="mantenimiento">Mantenimiento</option>
              <option value="otros">Otros</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-slate-500">Prioridad</label>
            <select id="incPrioridad" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
              <option value="baja">Baja</option>
              <option value="media" selected>Media</option>
              <option value="alta">Alta</option>
            </select>
          </div>

          <div>
            <label class="text-xs text-slate-500">Título *</label>
            <input id="incTitulo" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
          </div>

          <div>
            <label class="text-xs text-slate-500">Descripción *</label>
            <textarea id="incDesc" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" rows="3"></textarea>
          </div>

          <div id="incErr" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"></div>

          <div class="flex items-center justify-end gap-2">
            <button type="button" id="incCancel" class="rounded-xl border border-slate-200 px-4 py-2 text-sm">Cancelar</button>
            <button class="rounded-xl bg-[#4E7287] text-white px-4 py-2 text-sm shadow">Guardar</button>
          </div>
        </form>
      `);

      const $ = (id) => document.getElementById(id);
      const unidadSel = $('incUnidad');
      const err = $('incErr');

      const setErr = (m) => {
        if (!err) return;
        if (!m) { err.classList.add('hidden'); err.textContent=''; return; }
        err.classList.remove('hidden');
        err.textContent = m;
      };

      $('incCancel')?.addEventListener('click', closeModal);

      // load unidades
      fetchJSON(`${API}incidencias.php?action=unidades`)
        .then(j => {
          const items = j.data?.items || [];
          unidadSel.innerHTML = `<option value="">Selecciona unidad</option>` +
            items.map(u => `<option value="${escapeHtml(u.id)}">${escapeHtml(u.clave)}</option>`).join('');
        })
        .catch(e => setErr(e.message));

      $('frmNewInc')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        setErr('');

        const unidad_id = unidadSel?.value || '';
        const titulo = $('incTitulo')?.value || '';
        const descripcion = $('incDesc')?.value || '';
        const tipo = $('incTipo')?.value || 'seguridad';
        const prioridad = $('incPrioridad')?.value || 'media';

        if (!unidad_id) return setErr('Selecciona una unidad.');
        if (!titulo.trim()) return setErr('El título es obligatorio.');
        if (!descripcion.trim()) return setErr('La descripción es obligatoria.');

        try {
          const fd = new FormData();
          fd.set('action', 'create');
          fd.set('unidad_id', unidad_id);
          fd.set('titulo', titulo.trim());
          fd.set('descripcion', descripcion.trim());
          fd.set('tipo', tipo);
          fd.set('prioridad', prioridad);

          const r = await fetch(`${API}incidencias.php`, { method: 'POST', credentials: 'same-origin', body: fd });
          const j = await r.json().catch(() => ({}));
          if (!r.ok || j.ok === false) throw new Error(j.error || 'No se pudo guardar');

          closeModal();
          await load();
        } catch (e) {
          setErr(e.message || 'Error al guardar');
        }
      });
    }

    els.btnNew?.addEventListener('click', openNewModal);

    load();
  };
})();
