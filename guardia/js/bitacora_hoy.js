(function () {
  window.GuardiaViews = window.GuardiaViews || {};
  window.GuardiaViews.bitacora_hoy = function ({ API, fetchJSON, escapeHtml, openModal, closeModal }) {
    const root = document.getElementById('bitacoraHoyView');
    if (!root) return;
    const list = document.getElementById('bitacoraHoyList');
    const btnNuevo = document.getElementById('btnNuevoReporteBitacora');

    function toast(type, title, message) {
      try {
        if (window.AppToast && typeof window.AppToast.show === 'function') {
          window.AppToast.show({ type, title, message });
        }
      } catch (_) {}
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

    function reportTypeFields(tipo) {
      if (tipo === 'basura_ingreso') {
        return `
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs text-slate-600">Proveedor (opcional)</label>
              <input name="proveedor" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Recolector, empresa…" />
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-600">Placas (opcional)</label>
              <input name="placas" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="ABC-123" />
            </div>
          </div>
        `;
      }
      if (tipo === 'luces') {
        return `
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs text-slate-600">Acción</label>
              <select name="accion" class="w-full rounded-xl border px-3 py-2 text-sm">
                <option value="encendido">Encendido</option>
                <option value="apagado">Apagado</option>
                <option value="reporte">Reporte</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs text-slate-600">Ubicación (opcional)</label>
              <input name="ubicacion" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Entrada, jardín, andador…" />
            </div>
          </div>
        `;
      }
      if (tipo === 'rondin') {
        return `
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <label class="mb-1 block text-xs text-slate-600">Zona (opcional)</label>
              <input name="zona" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Zona A, perímetro…" />
            </div>
            <label class="mt-6 inline-flex items-center gap-2 text-sm text-slate-700">
              <input type="checkbox" name="incidencias_detectadas" value="1" class="h-4 w-4 rounded border-slate-300" />
              Incidencias detectadas
            </label>
          </div>
        `;
      }
      return '';
    }

    function openNewReportModal() {
      if (typeof openModal !== 'function') return;
      openModal('Nuevo reporte operativo', `
        <form id="bitacoraNewReportForm" class="space-y-3">
          <div id="bitacoraNewReportError" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Tipo</label>
            <select id="bitacoraReportTipo" name="tipo_evento" class="w-full rounded-xl border px-3 py-2 text-sm" required>
              <option value="basura_ingreso">Basura (ingreso)</option>
              <option value="luces">Luces</option>
              <option value="rondin">Rondín</option>
              <option value="nota">Nota</option>
            </select>
          </div>
          <div id="bitacoraReportExtraFields"></div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Observaciones</label>
            <textarea name="observaciones" rows="4" class="w-full rounded-xl border px-3 py-2 text-sm" placeholder="Describe lo ocurrido…" required></textarea>
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
            <button type="button" id="bitacoraNewReportCancel" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
              Cancelar
            </button>
            <button type="submit" class="rounded-xl bg-[#2E5D73] px-4 py-2 text-sm font-semibold text-white hover:opacity-95">
              Guardar
            </button>
          </div>
        </form>
      `);

      const form = document.getElementById('bitacoraNewReportForm');
      const tipo = document.getElementById('bitacoraReportTipo');
      const extra = document.getElementById('bitacoraReportExtraFields');
      const err = document.getElementById('bitacoraNewReportError');
      const cancel = document.getElementById('bitacoraNewReportCancel');

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

      function renderExtra() {
        if (!extra || !tipo) return;
        extra.innerHTML = reportTypeFields(tipo.value);
      }

      cancel?.addEventListener('click', () => {
        if (typeof closeModal === 'function') closeModal();
      });
      tipo?.addEventListener('change', renderExtra);
      renderExtra();

      form?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        setError('');
        try {
          const fd = new FormData(form);
          fd.set('action', 'create');
          const res = await fetchJSON(`${API}bitacora_reportes.php`, {
            method: 'POST',
            body: fd,
          });
          if (typeof closeModal === 'function') closeModal();
          toast('success', 'Reporte registrado', res.message || 'Reporte guardado.');
          load();
        } catch (e) {
          setError(e.message || 'No se pudo guardar el reporte.');
        }
      });
    }

    async function load() {
      try {
        const json = await fetchJSON(`${API}accesos.php?action=bitacora_hoy`);
        const items = json.data?.items || [];
        if (!items.length) {
          list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">Todavía no hay movimientos registrados hoy.</div>`;
          return;
        }
        list.innerHTML = items.map((item) => `
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <div class="text-base font-semibold text-slate-800">${escapeHtml(item.tipo_evento || '')} · ${escapeHtml(item.tipo_origen || '')}</div>
                  <span class="rounded-full px-2.5 py-1 text-xs ${item.resultado === 'permitido' ? 'bg-emerald-100 text-emerald-700' : item.resultado === 'denegado' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'}">${escapeHtml(item.resultado || '')}</span>
                </div>
                <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.persona_nombre || item.nombre_visitante || item.permiso_tipo_movimiento || 'Evento general')}</div>
                <div class="mt-1 text-sm text-slate-500">Área: ${escapeHtml(item.area_nombre || 'Sin área')} · Guardia: ${escapeHtml(item.guardia_nombre || '—')}</div>
                ${item.observaciones ? `<div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.observaciones)}</div>` : ''}
                ${renderEvidencias(item.evidencias || [])}
              </div>
              <div class="text-xs text-slate-400">${escapeHtml(item.fecha_hora || '')}</div>
            </div>
          </div>
        `).join('');
      } catch (e) {
        list.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(e.message || 'No se pudo cargar la bitácora.')}</div>`;
      }
    }

    btnNuevo?.addEventListener('click', openNewReportModal);
    load();
    return {};
  };
})();
