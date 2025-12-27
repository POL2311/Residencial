// guardia/js/paqueteria.js
(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.paqueteria = function ({ API, fetchJSON, openModal, closeModal, escapeHtml }) {
    const root = document.getElementById('paqueteriaView');
    if (!root) return;

    const els = {
      list: document.getElementById('pkgList'),
      btnNew: document.getElementById('btnNuevoPkg'),
    };

    function render(items) {
      if (!els.list) return;
      if (!items || items.length === 0) {
        els.list.innerHTML = `<div class="text-xs text-slate-500">Aún no hay paquetes.</div>`;
        return;
      }

      els.list.innerHTML = `
        <div class="space-y-2">
          ${items.map(p => `
            <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
              <div class="flex items-center justify-between gap-2">
                <div class="font-semibold text-slate-800">${escapeHtml(p.descripcion || '—')}</div>
                <div class="text-[11px] text-slate-500">${escapeHtml(p.created_at || '')}</div>
              </div>
              <div class="text-[11px] text-slate-600 mt-1">
                Unidad: <b>${escapeHtml(p.unidad_clave || '—')}</b>
                · Estado: ${escapeHtml(p.estado || 'registrado')}
              </div>
              ${p.empresa ? `<div class="text-[11px] text-slate-600">Empresa: ${escapeHtml(p.empresa)}</div>` : ''}
              ${p.codigo_rastreo ? `<div class="text-[11px] text-slate-600">Rastreo: <code>${escapeHtml(p.codigo_rastreo)}</code></div>` : ''}
            </div>
          `).join('')}
        </div>
      `;
    }

    async function load() {
      if (!els.list) return;
      els.list.textContent = 'Cargando…';
      try {
        const json = await fetchJSON(`${API}paqueteria.php`);
        render(json.data?.items || []);
      } catch (e) {
        els.list.innerHTML = `<div class="text-xs text-rose-700">Error: ${escapeHtml(e.message)}</div>`;
      }
    }

    function openNew() {
      openModal('Nuevo paquete', `
        <form id="frmNewPkg" class="space-y-3">
          <div>
            <label class="text-xs text-slate-500">Unidad *</label>
            <select id="pkgUnidad" class="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></select>
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
            <button class="rounded-xl bg-[#4E7287] text-white px-4 py-2 text-sm shadow">Guardar</button>
          </div>
        </form>
      `);

      const $ = (id) => document.getElementById(id);
      const unidadSel = $('pkgUnidad');
      const err = $('pkgErr');

      const setErr = (m) => {
        if (!err) return;
        if (!m) { err.classList.add('hidden'); err.textContent=''; return; }
        err.classList.remove('hidden');
        err.textContent = m;
      };

      $('pkgCancel')?.addEventListener('click', closeModal);

      fetchJSON(`${API}paqueteria.php?action=unidades`)
        .then(j => {
          const items = j.data?.items || [];
          unidadSel.innerHTML = `<option value="">Selecciona unidad</option>` +
            items.map(u => `<option value="${escapeHtml(u.id)}">${escapeHtml(u.clave)}</option>`).join('');
        })
        .catch(e => setErr(e.message));

      $('frmNewPkg')?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        setErr('');

        const unidad_id = unidadSel?.value || '';
        const empresa = $('pkgEmpresa')?.value || '';
        const descripcion = $('pkgDesc')?.value || '';
        const codigo_rastreo = $('pkgTrack')?.value || '';
        const notas = $('pkgNotas')?.value || '';

        if (!unidad_id) return setErr('Selecciona una unidad.');
        if (!descripcion.trim()) return setErr('La descripción es obligatoria.');

        try {
          const fd = new FormData();
          fd.set('action', 'create');
          fd.set('unidad_id', unidad_id);
          fd.set('empresa', empresa.trim());
          fd.set('descripcion', descripcion.trim());
          fd.set('codigo_rastreo', codigo_rastreo.trim());
          fd.set('notas', notas.trim());

          const r = await fetch(`${API}paqueteria.php`, { method: 'POST', credentials: 'same-origin', body: fd });
          const j = await r.json().catch(() => ({}));
          if (!r.ok || j.ok === false) throw new Error(j.error || 'No se pudo guardar');

          closeModal();
          await load();
        } catch (e) {
          setErr(e.message || 'Error al guardar');
        }
      });
    }

    els.btnNew?.addEventListener('click', openNew);
    load();
  };
})();
