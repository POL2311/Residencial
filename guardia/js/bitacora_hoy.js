(function () {
  window.GuardiaViews = window.GuardiaViews || {};
  window.GuardiaViews.bitacora_hoy = function ({ API, fetchJSON, escapeHtml, openModal, closeModal }) {
    const root = document.getElementById('bitacoraHoyView');
    if (!root) return;

    const REPORT_LABELS = {
      seguridad: 'Seguridad',
      rondin: 'Rondin',
      mantenimiento: 'Mantenimiento',
      eventos: 'Eventos',
      otros: 'Otros',
      basura_ingreso: 'Basura (ingreso)',
      luces: 'Luces',
      nota: 'Nota',
    };

    const list = document.getElementById('bitacoraHoyList');
    const btnNuevo = document.getElementById('btnNuevoReporteBitacora');

    const state = {
      items: [],
    };

    function toast(type, title, message) {
      try {
        if (window.AppToast && typeof window.AppToast.show === 'function') {
          window.AppToast.show({ type, title, message });
        }
      } catch (_) {}
    }

    function appRootBase() {
      try {
        const p = window.location.pathname || '';
        const idx = p.indexOf('/guardia/');
        if (idx === -1) return '';
        return p.slice(0, idx);
      } catch (_) {
        return '';
      }
    }

    function resolvePublicUrl(url) {
      const u = String(url || '').trim();
      if (!u) return '';
      if (u.startsWith('/assets/')) return appRootBase() + u;
      return u;
    }

    function tipoLabel(tipo) {
      return REPORT_LABELS[String(tipo || '').trim()] || String(tipo || 'Reporte');
    }

    function renderEvidencias(urls = [], itemIndex = 0) {
      if (!Array.isArray(urls) || !urls.length) return '';
      return `
        <div class="mt-3 flex flex-wrap gap-2">
          ${urls.slice(0, 3).map((url, evidenceIndex) => `
            <button
              type="button"
              class="js-bitacora-evidence block h-16 w-16 overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
              data-item-index="${itemIndex}"
              data-evidence-index="${evidenceIndex}">
              <img src="${escapeHtml(resolvePublicUrl(url))}" alt="Evidencia" class="h-full w-full object-cover" loading="lazy" />
            </button>
          `).join('')}
        </div>
      `;
    }

    function openEvidenceGallery(urls = [], startIndex = 0) {
      if (!Array.isArray(urls) || !urls.length || typeof openModal !== 'function') return;
      let current = Math.max(0, Math.min(startIndex, urls.length - 1));

      openModal('Evidencias del reporte', `
        <div class="space-y-4">
          <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-950/95">
            <img id="guardBitacoraGalleryImage" src="" alt="Evidencia" class="h-[55vh] w-full object-contain" />
          </div>
          <div class="flex items-center justify-between gap-3">
            <button type="button" id="guardBitacoraPrev" class="rounded-2xl border border-slate-200 bg-white min-h-11 px-4 text-sm text-slate-700 hover:bg-slate-50">
              Anterior
            </button>
            <div id="guardBitacoraCounter" class="text-sm text-slate-500"></div>
            <button type="button" id="guardBitacoraNext" class="rounded-2xl border border-slate-200 bg-white min-h-11 px-4 text-sm text-slate-700 hover:bg-slate-50">
              Siguiente
            </button>
          </div>
          <div id="guardBitacoraThumbs" class="flex flex-wrap gap-2"></div>
        </div>
      `);

      const img = document.getElementById('guardBitacoraGalleryImage');
      const prev = document.getElementById('guardBitacoraPrev');
      const next = document.getElementById('guardBitacoraNext');
      const counter = document.getElementById('guardBitacoraCounter');
      const thumbs = document.getElementById('guardBitacoraThumbs');

      function paint() {
        if (!img || !counter || !thumbs) return;
        img.src = resolvePublicUrl(urls[current]);
        counter.textContent = `${current + 1} de ${urls.length}`;
        if (prev) prev.disabled = urls.length <= 1;
        if (next) next.disabled = urls.length <= 1;
        thumbs.innerHTML = urls.map((url, index) => `
          <button
            type="button"
            class="overflow-hidden rounded-2xl border ${index === current ? 'border-[#2E5D73] ring-2 ring-[#2E5D73]/15' : 'border-slate-200'} bg-white"
            data-gallery-index="${index}">
            <img src="${escapeHtml(resolvePublicUrl(url))}" alt="Miniatura ${index + 1}" class="h-16 w-16 object-cover" loading="lazy" />
          </button>
        `).join('');
      }

      prev?.addEventListener('click', () => {
        current = current === 0 ? urls.length - 1 : current - 1;
        paint();
      });
      next?.addEventListener('click', () => {
        current = current === urls.length - 1 ? 0 : current + 1;
        paint();
      });
      thumbs?.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-gallery-index]');
        if (!btn) return;
        current = Number(btn.dataset.galleryIndex || 0);
        paint();
      });

      paint();
    }

    function openReportDetail(item) {
      if (typeof openModal !== 'function' || !item) return;
      openModal('Detalle del movimiento', `
        <div class="space-y-4">
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <div class="text-lg font-semibold text-slate-800">${escapeHtml(tipoLabel(item.tipo_evento))} · ${escapeHtml(item.tipo_origen || '')}</div>
              <span class="rounded-full px-2.5 py-1 text-xs ${item.resultado === 'permitido' ? 'bg-emerald-100 text-emerald-700' : item.resultado === 'denegado' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'}">${escapeHtml(item.resultado || '')}</span>
            </div>
            <div class="text-sm text-slate-600">${escapeHtml(item.persona_nombre || item.nombre_visitante || item.permiso_tipo_movimiento || 'Evento general')}</div>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div>
              <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Área</div>
              <div class="text-sm text-slate-700">${escapeHtml(item.area_nombre || 'Sin área')}</div>
            </div>
            <div>
              <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Guardia</div>
              <div class="text-sm text-slate-700">${escapeHtml(item.guardia_nombre || '—')}</div>
            </div>
            <div class="sm:col-span-2">
              <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Fecha</div>
              <div class="text-sm text-slate-700">${escapeHtml(item.fecha_hora || '—')}</div>
            </div>
          </div>
          ${item.observaciones ? `<div class="rounded-2xl bg-slate-50 px-3 py-3 text-sm text-slate-700 whitespace-pre-wrap">${escapeHtml(item.observaciones)}</div>` : ''}
          ${Array.isArray(item.evidencias) && item.evidencias.length ? `
            <div class="space-y-2">
              <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Evidencias</div>
              <div class="flex flex-wrap gap-2">
                ${item.evidencias.slice(0, 6).map((url, index) => `
                  <button
                    type="button"
                    class="js-detail-evidence block h-20 w-20 overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
                    data-index="${index}">
                    <img src="${escapeHtml(resolvePublicUrl(url))}" alt="Evidencia" class="h-full w-full object-cover" loading="lazy" />
                  </button>
                `).join('')}
              </div>
            </div>
          ` : ''}
        </div>
      `);

      document.querySelectorAll('.js-detail-evidence').forEach((btn) => {
        btn.addEventListener('click', () => {
          const evidenceIndex = Number(btn.dataset.index || 0);
          openEvidenceGallery(item.evidencias || [], evidenceIndex);
        });
      });
    }

    function wireEvidenceInputs(form) {
      form.querySelectorAll('[data-evidence-slot]').forEach((slot) => {
        const input = slot.querySelector('input[type="file"]');
        const label = slot.querySelector('[data-file-label]');
        if (!input || !label) return;
        input.addEventListener('change', () => {
          const fileName = input.files?.[0]?.name || '';
          label.textContent = fileName || 'Agregar foto';
          slot.classList.toggle('border-[#2E5D73]', !!fileName);
          slot.classList.toggle('bg-[#2E5D73]/5', !!fileName);
        });
      });
    }

    function openNewReportModal() {
      if (typeof openModal !== 'function') return;
      openModal('Nuevo reporte operativo', `
        <form id="bitacoraNewReportForm" class="space-y-4">
          <div id="bitacoraNewReportError" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Tipo</label>
            <select id="bitacoraReportTipo" name="tipo_evento" class="w-full rounded-2xl border border-slate-200 bg-white min-h-11 px-4.5 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#2E5D73]/20" required>
              <option value="seguridad">Nota</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs text-slate-600">Observaciones</label>
            <textarea name="observaciones" rows="4" class="w-full rounded-2xl border border-slate-200 bg-white min-h-11 px-4 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#2E5D73]/20" placeholder="Describe lo ocurrido…" required></textarea>
          </div>
          <div>
            <div class="text-xs text-slate-600">Evidencias (opcional, maximo 3)</div>
            <div class="mt-2 grid gap-2 sm:grid-cols-3">
              ${[1, 2, 3].map((index) => `
                <label data-evidence-slot class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-200 bg-white min-h-11 px-4 text-sm text-slate-700 transition hover:border-[#2E5D73]/40 hover:bg-slate-50">
                  <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">+</span>
                  <div class="min-w-0">
                    <div class="text-xs uppercase tracking-[0.16em] text-slate-400">Foto ${index}</div>
                    <div data-file-label class="truncate text-sm font-medium text-slate-700">Agregar foto</div>
                  </div>
                  <input type="file" name="evidencia_${index}" accept="image/*" class="hidden" />
                </label>
              `).join('')}
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-2">
            <button type="button" id="bitacoraNewReportCancel" class="rounded-xl border border-slate-200 bg-white min-h-11 px-4 text-sm text-slate-700 hover:bg-slate-50">
              Cancelar
            </button>
            <button type="submit" class="rounded-xl bg-[#2E5D73] min-h-11 px-4 text-sm font-semibold text-white hover:opacity-95">
              Guardar
            </button>
          </div>
        </form>
      `);

      const form = document.getElementById('bitacoraNewReportForm');
      const err = document.getElementById('bitacoraNewReportError');
      const cancel = document.getElementById('bitacoraNewReportCancel');
      const submitBtn = form?.querySelector('button[type="submit"]');
      const modalCloseBtn = document.getElementById('gModalClose');

      wireEvidenceInputs(form);

      const originalSubmitHtml = submitBtn ? submitBtn.innerHTML : '';
      const originalCancelDisabled = cancel ? cancel.disabled : false;
      const originalCloseDisabled = modalCloseBtn ? modalCloseBtn.disabled : false;

      function setLoading(isLoading) {
        if (submitBtn) {
          submitBtn.disabled = !!isLoading;
          submitBtn.classList.toggle('opacity-70', !!isLoading);
          submitBtn.classList.toggle('cursor-not-allowed', !!isLoading);
          if (isLoading) {
            submitBtn.innerHTML = `
              <span class="inline-flex items-center gap-2">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                Guardando…
              </span>
            `;
          } else {
            submitBtn.innerHTML = originalSubmitHtml || 'Guardar';
          }
        }
        if (cancel) cancel.disabled = !!isLoading || originalCancelDisabled;
        if (modalCloseBtn) modalCloseBtn.disabled = !!isLoading || originalCloseDisabled;
      }

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

      cancel?.addEventListener('click', () => {
        if (typeof closeModal === 'function') closeModal();
      });

      form?.addEventListener('submit', async (ev) => {
        ev.preventDefault();
        setError('');
        setLoading(true);
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
        } finally {
          setLoading(false);
        }
      });
    }

    async function load() {
      try {
        const json = await fetchJSON(`${API}accesos.php?action=bitacora_hoy`);
        state.items = json.data?.items || [];
        if (!state.items.length) {
          list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">Todavia no hay movimientos registrados hoy.</div>`;
          return;
        }
        list.innerHTML = state.items.map((item, itemIndex) => `
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <div class="text-base font-semibold text-slate-800">${escapeHtml(tipoLabel(item.tipo_evento))} · ${escapeHtml(item.tipo_origen || '')}</div>
                  <span class="rounded-full px-2.5 py-1 text-xs ${item.resultado === 'permitido' ? 'bg-emerald-100 text-emerald-700' : item.resultado === 'denegado' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'}">${escapeHtml(item.resultado || '')}</span>
                </div>
                <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.persona_nombre || item.nombre_visitante || item.permiso_tipo_movimiento || 'Evento general')}</div>
                <div class="mt-1 text-xs text-slate-500 md:text-sm">Area: ${escapeHtml(item.area_nombre || 'Sin area')} · ${escapeHtml(item.fecha_hora || '')}</div>
                <div class="app-mobile-secondary mt-1 text-sm text-slate-500">Guardia: ${escapeHtml(item.guardia_nombre || '—')}</div>
                ${item.observaciones ? `<div class="app-mobile-secondary mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.observaciones)}</div>` : ''}
                <div class="app-mobile-secondary">${renderEvidencias(item.evidencias || [], itemIndex)}</div>
              </div>
              <div class="flex flex-col items-end gap-2">
                <div class="app-mobile-secondary text-xs text-slate-400">${escapeHtml(item.fecha_hora || '')}</div>
                <button
                  type="button"
                  class="app-mobile-more hidden rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                  data-more-report="${itemIndex}">
                  Ver más
                </button>
              </div>
            </div>
          </div>
        `).join('');
      } catch (e) {
        list.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(e.message || 'No se pudo cargar la bitacora.')}</div>`;
      }
    }

    list?.addEventListener('click', (event) => {
      const moreBtn = event.target.closest('[data-more-report]');
      if (moreBtn) {
        const itemIndex = Number(moreBtn.dataset.moreReport || -1);
        const item = state.items[itemIndex];
        if (item) openReportDetail(item);
        return;
      }
      const btn = event.target.closest('.js-bitacora-evidence');
      if (!btn) return;
      const itemIndex = Number(btn.dataset.itemIndex || -1);
      const evidenceIndex = Number(btn.dataset.evidenceIndex || 0);
      const evidencias = state.items[itemIndex]?.evidencias || [];
      if (!Array.isArray(evidencias) || !evidencias.length) return;
      openEvidenceGallery(evidencias, evidenceIndex);
    });

    btnNuevo?.addEventListener('click', openNewReportModal);
    load();
    return {};
  };
})();
