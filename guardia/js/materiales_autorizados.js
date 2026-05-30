(function () {
  window.GuardiaViews = window.GuardiaViews || {};
  window.GuardiaViews.materiales_autorizados = function ({
    API,
    fetchJSON,
    escapeHtml,
    openModal,
    closeModal,
    navigate,
  }) {
    const root = document.getElementById('materialesAutorizadosView');
    if (!root) return;

    const API_MATERIALES = `${API}materiales.php`;
    const AUTO_SCAN_KEY = 'guardia:accesos:auto_scan';
    const MANUAL_VALUE = '__manual__';

    const els = {
      list: document.getElementById('materialesAutorizadosList'),
      btnScan: document.getElementById('btnEscanearMaterialQr'),
      btnRequest: document.getElementById('btnNuevaSolicitudMaterial'),
    };

    const state = {
      items: [],
      catalogo: [],
      areas: [],
      responsables: [],
      draftItems: [],
      metaLoaded: false,
    };

    function toast(type, title, message) {
      try {
        if (window.AppToast && typeof window.AppToast.show === 'function') {
          window.AppToast.show({ type, title, message });
        }
      } catch (_) {}
    }

    function openMaterialDetail(item) {
      if (typeof openModal !== 'function' || !item) return;
      openModal('Detalle del permiso', `
        <div class="flex min-h-0 flex-1 flex-col">
          <div class="flex-1 space-y-4 overflow-y-auto overscroll-contain px-4 py-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
          ${innerContent}
          </div>
          <div class="flex shrink-0 items-center gap-2 border-t border-slate-100 bg-white px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <button type="button" onclick="closeModal()" class="min-h-11 flex-1 rounded-xl bg-[#4E7287] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">Cerrar</button>
          </div>
        </div>
      `);
    }

    async function loadMeta() {
      if (state.metaLoaded) return;
      const json = await fetchJSON(`${API_MATERIALES}?action=meta`);
      state.catalogo = json.data?.catalogo || [];
      state.areas = json.data?.areas || [];
      state.responsables = json.data?.responsables || [];
      state.metaLoaded = true;
    }

    function renderActiveList() {
      if (!state.items.length) {
        els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay permisos activos en este momento.</div>`;
        return;
      }

      els.list.innerHTML = state.items.map((item) => `
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <div class="text-lg font-semibold text-slate-800">${escapeHtml(item.tipo_movimiento === 'salida' ? 'Salida autorizada' : 'Entrada autorizada')}</div>
                <span class="rounded-full px-2.5 py-1 text-xs ${item.estado === 'aprobado' ? 'bg-emerald-100 text-emerald-700' : 'bg-sky-100 text-sky-700'}">${escapeHtml(item.estado || '')}</span>
              </div>
              <div class="mt-1 text-sm text-slate-600">Responsable: ${escapeHtml(item.responsable_nombre || 'Sin responsable')} · Área: ${escapeHtml(item.area_nombre || 'Sin área')}</div>
              <div class="app-mobile-secondary mt-3 space-y-2">
                ${(item.items || []).map((row) => `
                  <div class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    ${escapeHtml(row.material_nombre || '')} · ${escapeHtml(row.cantidad_texto || '')}
                  </div>
                `).join('')}
              </div>
            </div>
            <div class="flex flex-col items-end gap-2">
              <div class="app-mobile-secondary text-xs text-slate-400">Aprobado: ${escapeHtml(item.aprobado_at || '—')}</div>
              <button
                type="button"
                class="app-mobile-more hidden rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                data-material-more="${escapeHtml(String(item.id || ''))}">
                Ver más
              </button>
            </div>
          </div>
        </div>
      `).join('');

      els.list.querySelectorAll('[data-material-more]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const item = state.items.find((row) => String(row.id) === String(btn.dataset.materialMore || ''));
          if (item) openMaterialDetail(item);
        });
      });
    }

    async function load() {
      try {
        const json = await fetchJSON(`${API}accesos.php?action=materiales_autorizados`);
        state.items = json.data?.items || [];
        renderActiveList();
      } catch (e) {
        els.list.innerHTML = `<div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${escapeHtml(e.message || 'No se pudo cargar la información.')}</div>`;
      }
    }

    function renderMaterialOptions(selectEl) {
      if (!selectEl) return;
      selectEl.innerHTML =
        '<option value="">Selecciona un material</option>' +
        state.catalogo.map((material) => `<option value="${material.id}">${escapeHtml(material.nombre)}</option>`).join('') +
        `<option value="${MANUAL_VALUE}">Otro / escribir manualmente</option>`;
    }

    function renderAreaOptions(selectEl) {
      if (!selectEl) return;
      selectEl.innerHTML =
        '<option value="">Sin área</option>' +
        state.areas.map((area) => `<option value="${area.id}">${escapeHtml(area.nombre)}</option>`).join('');
    }

    function renderResponsableOptions(selectEl) {
      if (!selectEl) return;
      selectEl.innerHTML =
        '<option value="">Selecciona responsable</option>' +
        state.responsables.map((user) => `<option value="${user.id}">${escapeHtml(user.name)}</option>`).join('');
    }

    function syncManualFields(modalEls) {
      const materialId = String(modalEls.itemMaterial?.value || '').trim();
      const isManual = materialId === MANUAL_VALUE;
      modalEls.itemManualWrap?.classList.toggle('hidden', !isManual);
      if (!isManual) {
        if (modalEls.itemManualName) modalEls.itemManualName.value = '';
        if (modalEls.itemAddCatalog) modalEls.itemAddCatalog.checked = false;
      }
    }

    function resetItemComposer(modalEls) {
      if (modalEls.itemMaterial) modalEls.itemMaterial.value = '';
      if (modalEls.itemCantidad) modalEls.itemCantidad.value = '';
      if (modalEls.itemManualName) modalEls.itemManualName.value = '';
      if (modalEls.itemAddCatalog) modalEls.itemAddCatalog.checked = false;
      syncManualFields(modalEls);
    }

    function setModalError(modalEls, message = '') {
      if (!modalEls.error) return;
      if (!message) {
        modalEls.error.textContent = '';
        modalEls.error.classList.add('hidden');
        return;
      }
      modalEls.error.textContent = message;
      modalEls.error.classList.remove('hidden');
    }

    function currentItemFromComposer(modalEls) {
      const materialIdRaw = String(modalEls.itemMaterial?.value || '').trim();
      const cantidad = String(modalEls.itemCantidad?.value || '').trim();
      const manualName = String(modalEls.itemManualName?.value || '').trim();
      const addToCatalog = Boolean(modalEls.itemAddCatalog?.checked);

      if (!materialIdRaw) {
        throw new Error('Selecciona un material o "Otro / escribir manualmente".');
      }
      if (!cantidad) {
        throw new Error('Agrega la cantidad o detalle del material.');
      }

      if (materialIdRaw === MANUAL_VALUE) {
        if (!manualName) {
          throw new Error('Escribe el nombre manual del material.');
        }
        return {
          material_id: null,
          material_nombre: manualName,
          cantidad_texto: cantidad,
          agregar_a_catalogo: addToCatalog ? 1 : 0,
        };
      }

      const selected = modalEls.itemMaterial?.selectedOptions?.[0];
      return {
        material_id: Number(materialIdRaw),
        material_nombre: String(selected?.textContent || '').trim(),
        cantidad_texto: cantidad,
        agregar_a_catalogo: 0,
      };
    }

    function renderDraftItems(modalEls) {
      if (!modalEls.itemsList) return;
      if (!state.draftItems.length) {
        modalEls.itemsList.innerHTML = `
          <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
            Todavía no agregas materiales a esta solicitud.
          </div>
        `;
        return;
      }

      modalEls.itemsList.innerHTML = state.draftItems.map((item, index) => `
        <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="min-w-0">
            <div class="truncate text-sm font-semibold text-slate-800">${escapeHtml(item.material_nombre || 'Material')}</div>
            <div class="text-xs text-slate-500">${escapeHtml(item.cantidad_texto || '')}</div>
          </div>
          <div class="flex items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-[11px] ${item.material_id ? 'bg-slate-100 text-slate-600' : 'bg-sky-50 text-sky-700'}">
              ${item.material_id ? 'Catálogo' : 'Manual'}
            </span>
            <button type="button" class="js-remove-material-item rounded-xl border border-slate-200 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50" data-index="${index}">
              Quitar
            </button>
          </div>
        </div>
      `).join('');
    }

    async function openSolicitudModal() {
      try {
        await loadMeta();
      } catch (error) {
        toast('error', 'Materiales', error.message || 'No se pudo cargar el formulario de solicitud.');
        return;
      }

      state.draftItems = [];

      if (typeof openModal !== 'function') return;
      openModal('Nueva solicitud de materiales', `
        ${newInner}
          </div>
          <div class="flex shrink-0 items-center gap-2 border-t border-slate-100 bg-white px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">
            <button type="button" id="btnGuardMaterialCancel" class="min-h-11 flex-1 rounded-xl border border-slate-200 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50">Cancelar</button>
            <button type="submit" id="btnGuardMaterialSubmit" class="min-h-11 flex-1 rounded-xl bg-[#4E7287] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95">Enviar solicitud</button>
          </div>
        </form>
      `);

      const modalEls = {
        form: document.getElementById('guardMaterialRequestForm'),
        error: document.getElementById('guardMaterialRequestError'),
        itemMaterial: document.getElementById('guardMaterialItemMaterial'),
        itemCantidad: document.getElementById('guardMaterialItemCantidad'),
        itemManualWrap: document.getElementById('guardMaterialManualWrap'),
        itemManualName: document.getElementById('guardMaterialManualName'),
        itemAddCatalog: document.getElementById('guardMaterialAddCatalog'),
        itemsList: document.getElementById('guardMaterialItemsList'),
        btnAddItem: document.getElementById('btnGuardAddMaterialItem'),
        btnCancel: document.getElementById('btnGuardMaterialCancel'),
        btnSubmit: document.getElementById('btnGuardMaterialSubmit'),
        area: document.getElementById('guardMaterialArea'),
        responsable: document.getElementById('guardMaterialResponsable'),
      };

      renderMaterialOptions(modalEls.itemMaterial);
      renderAreaOptions(modalEls.area);
      renderResponsableOptions(modalEls.responsable);
      renderDraftItems(modalEls);
      resetItemComposer(modalEls);

      if (!state.responsables.length) {
        setModalError(modalEls, 'No hay responsables internos activos para recibir esta solicitud.');
        modalEls.btnSubmit?.setAttribute('disabled', 'disabled');
        modalEls.btnSubmit?.classList.add('opacity-60', 'cursor-not-allowed');
      }

      modalEls.itemMaterial?.addEventListener('change', () => syncManualFields(modalEls));
      modalEls.btnCancel?.addEventListener('click', () => typeof closeModal === 'function' && closeModal());
      modalEls.btnAddItem?.addEventListener('click', () => {
        try {
          state.draftItems.push(currentItemFromComposer(modalEls));
          renderDraftItems(modalEls);
          resetItemComposer(modalEls);
          setModalError(modalEls, '');
        } catch (error) {
          setModalError(modalEls, error.message || 'No se pudo agregar el item.');
        }
      });
      modalEls.itemsList?.addEventListener('click', (event) => {
        const btn = event.target.closest('.js-remove-material-item');
        if (!btn) return;
        const index = Number(btn.dataset.index || -1);
        if (!Number.isInteger(index) || index < 0 || index >= state.draftItems.length) return;
        state.draftItems.splice(index, 1);
        renderDraftItems(modalEls);
      });

      modalEls.form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        setModalError(modalEls, '');
        if (!state.draftItems.length) {
          setModalError(modalEls, 'Agrega al menos un item antes de enviar la solicitud.');
          return;
        }

        const original = modalEls.btnSubmit?.innerHTML || '';
        if (modalEls.btnSubmit) {
          modalEls.btnSubmit.disabled = true;
          modalEls.btnSubmit.classList.add('opacity-70', 'cursor-not-allowed');
          modalEls.btnSubmit.innerHTML = `
            <span class="inline-flex items-center gap-2">
              <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
              Enviando…
            </span>
          `;
        }

        try {
          const fd = new FormData(modalEls.form);
          fd.set('action', 'create_solicitud');
          fd.set('items_json', JSON.stringify(state.draftItems));
          const res = await fetchJSON(API_MATERIALES, { method: 'POST', body: fd });
          if (typeof closeModal === 'function') closeModal();
          toast('success', 'Materiales', res.message || 'Solicitud enviada correctamente.');
          await load();
        } catch (error) {
          setModalError(modalEls, error.message || 'No se pudo enviar la solicitud.');
        } finally {
          if (modalEls.btnSubmit) {
            modalEls.btnSubmit.disabled = false;
            modalEls.btnSubmit.classList.remove('opacity-70', 'cursor-not-allowed');
            modalEls.btnSubmit.innerHTML = original || 'Enviar solicitud';
          }
        }
      });
    }

    function openScanFlow() {
      try {
        window.sessionStorage.setItem(AUTO_SCAN_KEY, 'permiso_material');
      } catch (_) {}
      if (typeof navigate === 'function') {
        navigate('accesos');
      }
    }

    els.btnScan?.addEventListener('click', openScanFlow);
    els.btnRequest?.addEventListener('click', openSolicitudModal);

    load();
    return {};
  };
})();
