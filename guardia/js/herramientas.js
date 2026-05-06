(function () {
  window.GuardiaViews = window.GuardiaViews || {};
  window.GuardiaViews.herramientas = function ({ API, fetchJSON, escapeHtml, openModal, closeModal }) {
    const root = document.getElementById('guardHerramientasView');
    if (!root) return;

    const els = {
      btnNuevo: document.getElementById('btnNuevoPrestamoHerramienta'),
      estado: document.getElementById('herrEstado'),
      prev: document.getElementById('herrPrev'),
      next: document.getElementById('herrNext'),
      pageInfo: document.getElementById('herrPageInfo'),
      alert: document.getElementById('herrAlert'),
      list: document.getElementById('herrList'),
    };

    const state = {
      page: 1,
      totalPages: 1,
      catalogo: null,
      unidades: null,
      isLoading: false,
    };

    function toast(type, title, message) {
      try {
        if (window.AppToast && typeof window.AppToast.show === 'function') {
          window.AppToast.show({ type, title, message });
        }
      } catch (_) {}
    }

    function showAlert(msg = '', type = 'info') {
      if (!els.alert) return;
      if (!msg) {
        els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
        els.alert.textContent = '';
        return;
      }
      els.alert.className =
        'rounded-2xl px-4 py-3 text-sm ' +
        (type === 'error' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
      els.alert.textContent = msg;
      els.alert.classList.remove('hidden');
    }

    function renderEvidencias(urls = []) {
      if (!Array.isArray(urls) || !urls.length) return '';
      return `
        <div class="mt-3 flex flex-wrap gap-2">
          ${urls.slice(0, 3).map((url) => `
            <a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="block h-16 w-16 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
              <img src="${escapeHtml(url)}" alt="Evidencia" class="h-full w-full object-cover" loading="lazy" />
            </a>
          `).join('')}
        </div>
      `;
    }

    async function ensureCatalogo() {
      if (Array.isArray(state.catalogo)) return state.catalogo;
      const json = await fetchJSON(`${API}herramientas.php?action=catalogo`);
      state.catalogo = json.data?.items || [];
      return state.catalogo;
    }

    async function ensureUnidades() {
      if (Array.isArray(state.unidades)) return state.unidades;
      const json = await fetchJSON(`${API}herramientas.php?action=unidades`);
      state.unidades = json.data?.items || [];
      return state.unidades;
    }

    async function fetchResidentesUnidad(unidadId) {
      const json = await fetchJSON(`${API}herramientas.php?action=residentes_unidad&unidad_id=${encodeURIComponent(unidadId)}`);
      return json.data?.items || [];
    }

    function renderList(items = []) {
      if (!els.list) return;
      if (!items.length) {
        els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay préstamos para el filtro seleccionado.</div>`;
        return;
      }

      els.list.innerHTML = items.map((item) => `
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <div class="text-base font-semibold text-slate-800">${escapeHtml(item.herramienta_nombre || 'Herramienta')}</div>
                <span class="rounded-full px-2.5 py-1 text-xs ${item.estado === 'prestado' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'}">
                  ${escapeHtml(item.estado || '')}
                </span>
              </div>
              <div class="mt-1 text-sm text-slate-600">Unidad: <span class="font-medium">${escapeHtml(item.unidad_clave || '—')}</span></div>
              <div class="mt-1 text-sm text-slate-500">Residente: ${escapeHtml(item.residente_nombre || item.residente_email || '—')}</div>
              ${item.notas ? `<div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.notas)}</div>` : ''}
              ${renderEvidencias(item.evidencias || [])}
              <div class="mt-3 text-xs text-slate-400">Prestado: ${escapeHtml(item.prestado_at || '—')}${item.devuelto_at ? ` · Devuelto: ${escapeHtml(item.devuelto_at)}` : ''}</div>
            </div>
            <div class="shrink-0">
              ${item.estado === 'prestado' ? `
                <button data-action="devuelto" data-id="${escapeHtml(item.id)}" class="rounded-xl bg-[#2E5D73] px-4 py-2 text-sm font-semibold text-white hover:opacity-95">
                  Marcar devuelto
                </button>
              ` : ''}
            </div>
          </div>
        </article>
      `).join('');

      els.list.querySelectorAll('button[data-action="devuelto"]').forEach((btn) => {
        btn.addEventListener('click', async () => {
          const id = Number(btn.getAttribute('data-id') || 0);
          if (!id) return;
          try {
            const fd = new FormData();
            fd.set('action', 'marcar_devuelto');
            fd.set('prestamo_id', String(id));
            const res = await fetchJSON(`${API}herramientas.php`, { method: 'POST', body: fd });
            toast('success', 'Actualizado', res.message || 'Préstamo marcado como devuelto.');
            load();
          } catch (e) {
            toast('error', 'Error', e.message || 'No se pudo actualizar.');
          }
        });
      });
    }

    function updatePagination(p) {
      state.totalPages = Number(p?.total_pages || 1) || 1;
      state.page = Number(p?.page || 1) || 1;
      if (els.pageInfo) {
        els.pageInfo.textContent = `Página ${state.page} de ${state.totalPages}`;
      }
      els.prev?.toggleAttribute('disabled', state.page <= 1);
      els.next?.toggleAttribute('disabled', state.page >= state.totalPages);
      els.prev?.classList.toggle('opacity-50', state.page <= 1);
      els.next?.classList.toggle('opacity-50', state.page >= state.totalPages);
    }

    async function load() {
      if (state.isLoading) return;
      state.isLoading = true;
      showAlert('');
      try {
        const params = new URLSearchParams({ action: 'prestamos', page: String(state.page) });
        if (els.estado?.value) params.set('estado', els.estado.value);
        const json = await fetchJSON(`${API}herramientas.php?${params.toString()}`);
        updatePagination(json.data?.pagination || {});
        renderList(json.data?.items || []);
      } catch (e) {
        showAlert(e.message || 'No se pudieron cargar los préstamos.', 'error');
      } finally {
        state.isLoading = false;
      }
    }

    function openNewLoanModal() {
      if (typeof openModal !== 'function') return;
      openModal('Nuevo préstamo', `
        <form id="herrNewLoanForm" class="space-y-3">
          <div id="herrNewLoanError" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Herramienta</label>
            <select id="herrToolSelect" name="herramienta_id" class="w-full rounded-xl border px-3 py-2 text-sm" required></select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Unidad</label>
            <select id="herrUnitSelect" name="unidad_id" class="w-full rounded-xl border px-3 py-2 text-sm" required></select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Residente (opcional)</label>
            <select id="herrResidentSelect" name="residente_id" class="w-full rounded-xl border px-3 py-2 text-sm">
              <option value="">—</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Notas (opcional)</label>
            <textarea name="notas" rows="3" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Detalle del préstamo…"></textarea>
          </div>
          <div>
            <div class="text-xs text-slate-600">Evidencias (opcional, máximo 3)</div>
            <div class="mt-2 grid gap-2 sm:grid-cols-3">
              <input type="file" name="evidencia_1" accept="image/*" class="w-full rounded-xl border bg-white px-3 py-2 text-xs" />
              <input type="file" name="evidencia_2" accept="image/*" class="w-full rounded-xl border bg-white px-3 py-2 text-xs" />
              <input type="file" name="evidencia_3" accept="image/*" class="w-full rounded-xl border bg-white px-3 py-2 text-xs" />
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" id="herrNewLoanCancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancelar</button>
            <button type="submit" class="rounded-xl bg-[#2E5D73] px-4 py-2 text-sm font-semibold text-white hover:opacity-95">Guardar</button>
          </div>
        </form>
      `);

      const form = document.getElementById('herrNewLoanForm');
      const err = document.getElementById('herrNewLoanError');
      const toolSel = document.getElementById('herrToolSelect');
      const unitSel = document.getElementById('herrUnitSelect');
      const resSel = document.getElementById('herrResidentSelect');

      function setError(msg) {
        if (!err) return;
        if (!msg) {
          err.classList.add('hidden');
          err.textContent = '';
          return;
        }
        err.textContent = msg;
        err.classList.remove('hidden');
      }

      document.getElementById('herrNewLoanCancel')?.addEventListener('click', () => {
        if (typeof closeModal === 'function') closeModal();
      });

      Promise.all([ensureCatalogo(), ensureUnidades()]).then(([catalogo, unidades]) => {
        toolSel.innerHTML = catalogo.length
          ? catalogo.map((t) => `<option value="${escapeHtml(t.id)}">${escapeHtml(t.nombre || 'Herramienta')}</option>`).join('')
          : `<option value="">No hay herramientas activas</option>`;
        unitSel.innerHTML = unidades.length
          ? unidades.map((u) => `<option value="${escapeHtml(u.id)}">${escapeHtml(u.clave || 'Unidad')}</option>`).join('')
          : `<option value="">No hay unidades</option>`;
        unitSel.dispatchEvent(new Event('change'));
      }).catch((e) => setError(e.message || 'No se pudo cargar catálogo.'));

      unitSel?.addEventListener('change', async () => {
        if (!resSel) return;
        const unidadId = unitSel.value;
        resSel.innerHTML = `<option value="">—</option>`;
        if (!unidadId) return;
        try {
          const items = await fetchResidentesUnidad(unidadId);
          resSel.innerHTML = `<option value="">—</option>` + items.map((r) => `<option value="${escapeHtml(r.user_id)}">${escapeHtml(r.name || r.email || 'Residente')}</option>`).join('');
        } catch (_) {}
      });

      form?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        setError('');
        try {
          const fd = new FormData(form);
          fd.set('action', 'create_prestamo');
          const res = await fetchJSON(`${API}herramientas.php`, { method: 'POST', body: fd });
          if (typeof closeModal === 'function') closeModal();
          toast('success', 'Préstamo registrado', res.message || 'Listo.');
          state.page = 1;
          load();
        } catch (e) {
          setError(e.message || 'No se pudo guardar el préstamo.');
        }
      });
    }

    els.estado?.addEventListener('change', () => {
      state.page = 1;
      load();
    });
    els.prev?.addEventListener('click', () => {
      if (state.page > 1) {
        state.page -= 1;
        load();
      }
    });
    els.next?.addEventListener('click', () => {
      if (state.page < state.totalPages) {
        state.page += 1;
        load();
      }
    });
    els.btnNuevo?.addEventListener('click', openNewLoanModal);

    load();
    return {};
  };
})();

