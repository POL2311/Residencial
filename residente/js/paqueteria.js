(function () {
    if (window.__paqueteria_init_v1) return;
    window.__paqueteria_init_v1 = true;

    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }
    const BASE = baseResidentPath();
    const API = BASE + 'php/api/';

    function $(id) { return document.getElementById(id); }

    const root = $('paqueteriaView');
    if (!root) return;

    const els = {
        alert: $('paqAlert'),
        list: $('paqList'),
        empty: $('paqEmpty'),

        btnCreate: $('btnPaqCreate'),

        casetaWrap: $('paqCasetaWrap'),
        casetaTel: $('paqCasetaTel'),

        modal: $('paqModal'),
        modalTitle: $('paqModalTitle'),
        modalForm: $('paqModalForm'),
        modalAction: $('paqModalAction'),
        modalBody: $('paqModalBody'),
        btnCloseModal: $('btnClosePaqModal'),
        btnCancelModal: $('btnCancelPaqModal'),
        btnSaveModal: $('btnSavePaqModal'),
    };

    let state = {
        items: [],
        filter: 'todos',
        caseta_phone: null,
    };

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.textContent = msg;
        els.alert.className = 'rounded-2xl px-4 py-3 text-sm';

        if (type === 'ok') {
            els.alert.classList.add('bg-emerald-50', 'text-emerald-800', 'border', 'border-emerald-200');
        } else {
            els.alert.classList.add('bg-rose-50', 'text-rose-800', 'border', 'border-rose-200');
        }
    }

    function hideAlert() {
        if (!els.alert) return;
        els.alert.classList.add('hidden');
    }

    function openModal(title, action, bodyHtml, saveText = 'Guardar', danger = false) {
        els.modalTitle.textContent = title;
        els.modalAction.value = action;
        els.modalBody.innerHTML = bodyHtml;
        els.btnSaveModal.textContent = saveText;

        els.btnSaveModal.className = danger
            ? 'text-sm px-4 py-2 rounded-xl bg-rose-600 text-white hover:opacity-95'
            : 'text-sm px-4 py-2 rounded-xl bg-[#2E5D73] text-white hover:opacity-95';

        els.modal.classList.remove('hidden');
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.modalBody.innerHTML = '';
        els.modalAction.value = '';
        els.modalForm.reset();
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    async function apiPost(url, formDataOrObj) {
        let body;
        if (formDataOrObj instanceof FormData) {
            body = formDataOrObj;
        } else {
            const fd = new FormData();
            Object.keys(formDataOrObj || {}).forEach(k => fd.append(k, formDataOrObj[k]));
            body = fd;
        }

        const res = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        });

        const json = await res.json().catch(() => null);
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    function isFinal(estado) {
        return ['entregado', 'entregado_incidencia', 'rechazado', 'no_llego', 'devuelto', 'cancelado'].includes(String(estado));
    }

    function badgeFor(estado) {
        const e = String(estado || '');
        if (e === 'esperado') return 'bg-sky-50 border-sky-200 text-sky-700';
        if (e === 'registrado') return 'bg-amber-50 border-amber-200 text-amber-800';
        if (e === 'acceso_otorgado') return 'bg-indigo-50 border-indigo-200 text-indigo-700';
        if (e === 'entregado') return 'bg-emerald-50 border-emerald-200 text-emerald-800';
        if (e === 'entregado_incidencia') return 'bg-rose-50 border-rose-200 text-rose-800';
        if (e === 'rechazado' || e === 'no_llego' || e === 'devuelto' || e === 'cancelado') return 'bg-slate-100 border-slate-200 text-slate-600';
        return 'bg-slate-100 border-slate-200 text-slate-600';
    }

    function titleFor(estado) {
        const e = String(estado || '');
        if (e === 'esperado') return 'Esperado';
        if (e === 'registrado') return 'En caseta';
        if (e === 'acceso_otorgado') return 'Acceso otorgado';
        if (e === 'entregado') return 'Entregado';
        if (e === 'entregado_incidencia') return 'Entregado con incidencia';
        if (e === 'rechazado') return 'Rechazado';
        if (e === 'no_llego') return 'No llegó';
        if (e === 'devuelto') return 'Devuelto';
        if (e === 'cancelado') return 'Cancelado';
        return e || '—';
    }

    function applyFilter(items) {
        const f = state.filter;
        if (f === 'todos') return items;
        if (f === 'finalizados') return items.filter(x => isFinal(x.estado));
        return items.filter(x => String(x.estado) === f);
    }

    function render() {
        const items = applyFilter(state.items || []);
        els.list.innerHTML = '';

        if (!items.length) {
            els.empty.classList.remove('hidden');
            return;
        }
        els.empty.classList.add('hidden');

        items.forEach(p => {
            const e = String(p.estado || '');
            const faded = isFinal(e);

            const empresa = (p.empresa || '').trim();
            const desc = (p.descripcion || '').trim();
            const track = (p.codigo_rastreo || '').trim();

            const instruccion = String(p.instruccion_entrega || 'recepcion_caseta') === 'acceso_domicilio'
                ? 'Dejar pasar'
                : 'Recibir en caseta';

            const fechaEst = p.fecha_estimada ? ` · Estimada: ${escapeHtml(p.fecha_estimada)}` : '';
            const badge = badgeFor(e);

            const motivo = (p.motivo_detalle || '').trim();
            const incidencia = (p.incidencia_detalle || '').trim();

            const actionsHtml = buildActionsHtml(p);

            const row = document.createElement('div');
            row.className = `px-4 py-4 flex items-start justify-between gap-3 ${faded ? 'opacity-70' : ''}`;
            row.innerHTML = `
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full border text-[11px] ${badge}">
              ${escapeHtml(titleFor(e))}
            </span>
            <div class="text-sm font-semibold text-slate-800 truncate">
              ${escapeHtml(empresa || 'Paquete')}
            </div>
          </div>

          <div class="text-xs text-slate-500 mt-0.5">
            ${escapeHtml(instruccion)}${fechaEst}
          </div>

          <div class="text-xs text-slate-700 mt-2">
            ${escapeHtml(desc || '—')}
          </div>

          ${track ? `<div class="text-[11px] text-slate-500 mt-1">Tracking: <span class="font-medium text-slate-700">${escapeHtml(track)}</span></div>` : ''}

          ${motivo ? `<div class="text-[11px] text-slate-500 mt-2">Motivo: <span class="text-slate-700">${escapeHtml(motivo)}</span></div>` : ''}

          ${incidencia ? `<div class="text-[11px] text-rose-700 mt-2">Incidencia: <span class="text-rose-800 font-medium">${escapeHtml(incidencia)}</span></div>` : ''}
        </div>

        <div class="shrink-0 flex flex-col items-end gap-2">
          ${actionsHtml}
        </div>
      `;

            els.list.appendChild(row);

            // bind acciones
            bindRowActions(row, p);
        });

        // filtros (pintado)
        document.querySelectorAll('.paqFilter').forEach(btn => {
            const active = btn.dataset.filter === state.filter;
            btn.classList.toggle('bg-slate-50', active);
            btn.classList.toggle('border-slate-200', true);
            btn.classList.toggle('text-slate-700', true);
            btn.classList.toggle('bg-white', !active);
        });
    }

    function buildActionsHtml(p) {
        const e = String(p.estado || '');
        const canCall = !!state.caseta_phone;

        const callBtn = canCall
            ? `<a class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
              href="tel:${escapeHtml(state.caseta_phone)}">Llamar</a>`
            : '';

        // esperado: editar + cancelar + eliminar
        if (e === 'esperado') {
            return `
        <div class="flex flex-wrap justify-end gap-2">
          ${callBtn}
          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
            data-act="edit">Actualizar</button>

          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-amber-50 border border-amber-200 text-amber-800 hover:bg-amber-100"
            data-act="cancel">Cancelar</button>

          <button type="button"
            class="h-9 w-9 rounded-full bg-white border border-slate-200 text-slate-500 hover:bg-slate-100 flex items-center justify-center"
            title="Eliminar"
            data-act="delete">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none">
              <path d="M9 3h6m-8 4h10m-9 0l1 14h6l1-14M10 11v6m4-6v6"
                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </button>
        </div>
      `;
        }

        // en caseta: recibido / recibido con incidencia / rechazado
        if (e === 'registrado') {
            return `
        <div class="flex flex-wrap justify-end gap-2">
          ${callBtn}
          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-[#2E5D73] text-white hover:opacity-95"
            data-act="recibido">Recibido</button>

          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-rose-600 text-white hover:opacity-95"
            data-act="incidencia">Recibido con incidencia</button>

          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
            data-act="rechazar">Rechazar / No es mío</button>
        </div>
      `;
        }

        // acceso otorgado: residente confirma recibido / no llegó / rechazar
        if (e === 'acceso_otorgado') {
            return `
        <div class="flex flex-wrap justify-end gap-2">
          ${callBtn}
          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-[#2E5D73] text-white hover:opacity-95"
            data-act="recibido">Recibido</button>

          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
            data-act="no_llego">No llegó</button>

          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
            data-act="rechazar">Rechazar</button>
        </div>
      `;
        }

        // finalizados: si entregado_incidencia -> ver detalle (solo modal de lectura)
        if (e === 'entregado_incidencia') {
            return `
        <div class="flex flex-wrap justify-end gap-2">
          ${callBtn}
          <button type="button"
            class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
            data-act="ver">Ver detalle</button>
        </div>
      `;
        }

        return `
      <div class="flex flex-wrap justify-end gap-2">
        ${callBtn}
        <button type="button"
          class="text-xs px-3 py-1.5 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
          data-act="ver">Ver</button>
      </div>
    `;
    }

    function bindRowActions(row, p) {
        row.querySelectorAll('[data-act]').forEach(btn => {
            btn.addEventListener('click', () => {
                const act = btn.getAttribute('data-act');

                if (act === 'ver') {
                    const foto = p.incidencia_foto_url || p.foto_url || '';
                    openModal(
                        'Detalle del paquete',
                        'noop',
                        `
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 space-y-2">
                <div><span class="text-slate-500 text-xs">Estado:</span> <b>${escapeHtml(titleFor(p.estado))}</b></div>
                <div><span class="text-slate-500 text-xs">Empresa:</span> <b>${escapeHtml(p.empresa || '—')}</b></div>
                <div><span class="text-slate-500 text-xs">Descripción:</span> <b>${escapeHtml(p.descripcion || '—')}</b></div>
                ${p.codigo_rastreo ? `<div><span class="text-slate-500 text-xs">Tracking:</span> <b>${escapeHtml(p.codigo_rastreo)}</b></div>` : ''}
                ${p.fecha_estimada ? `<div><span class="text-slate-500 text-xs">Estimada:</span> <b>${escapeHtml(p.fecha_estimada)}</b></div>` : ''}
                ${p.motivo_detalle ? `<div><span class="text-slate-500 text-xs">Motivo:</span> <b>${escapeHtml(p.motivo_detalle)}</b></div>` : ''}
                ${p.incidencia_detalle ? `<div><span class="text-slate-500 text-xs">Incidencia:</span> <b class="text-rose-700">${escapeHtml(p.incidencia_detalle)}</b></div>` : ''}
              </div>

              ${foto ? `
                <div class="mt-3">
                  <div class="text-xs text-slate-500 mb-1">Evidencia</div>
                  <img class="w-full rounded-2xl border border-slate-200" alt="Evidencia" src="${escapeHtml(foto)}">
                </div>
              ` : ''}
            `,
                        'Cerrar'
                    );
                    // “Cerrar” como submit no aplica -> ocultamos submit con truco simple
                    els.btnSaveModal.classList.add('hidden');
                    return;
                }

                if (act === 'edit') {
                    openModal(
                        'Actualizar paquete esperado',
                        'update_expected',
                        `
              <input type="hidden" name="paq_id" value="${escapeHtml(String(p.id))}">

              <div>
                <label class="block text-xs text-slate-600 mb-1">Empresa (opcional)</label>
                <input name="empresa" value="${escapeHtml(p.empresa || '')}"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                  placeholder="Amazon, MercadoLibre, DHL...">
              </div>

              <div>
                <label class="block text-xs text-slate-600 mb-1">Descripción *</label>
                <input name="descripcion" value="${escapeHtml(p.descripcion || '')}"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                  placeholder="Caja, sobre, etc." required>
              </div>

              <div>
                <label class="block text-xs text-slate-600 mb-1">Código de rastreo (opcional)</label>
                <input name="codigo_rastreo" value="${escapeHtml(p.codigo_rastreo || '')}"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                  placeholder="Tracking number">
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs text-slate-600 mb-1">Instrucción</label>
                  <select name="instruccion_entrega"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                    <option value="recepcion_caseta" ${String(p.instruccion_entrega) !== 'acceso_domicilio' ? 'selected' : ''}>Recibir en caseta</option>
                    <option value="acceso_domicilio" ${String(p.instruccion_entrega) === 'acceso_domicilio' ? 'selected' : ''}>Dejar pasar</option>
                  </select>
                </div>

                <div>
                  <label class="block text-xs text-slate-600 mb-1">Fecha estimada (opcional)</label>
                  <input type="date" name="fecha_estimada" value="${escapeHtml(p.fecha_estimada || '')}"
                    class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
                </div>
              </div>
            `
                    );
                    els.btnSaveModal.classList.remove('hidden');
                    return;
                }

                if (act === 'cancel') {
                    openModal(
                        'Cancelar esperado',
                        'cancel_expected',
                        `
              <input type="hidden" name="paq_id" value="${escapeHtml(String(p.id))}">
              <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                ¿Cancelar este paquete esperado? Quedará en historial como <b>Cancelado</b>.
              </div>
            `,
                        'Cancelar',
                        true
                    );
                    els.btnSaveModal.classList.remove('hidden');
                    return;
                }

                if (act === 'delete') {
                    openModal(
                        'Eliminar esperado',
                        'delete_expected',
                        `
              <input type="hidden" name="paq_id" value="${escapeHtml(String(p.id))}">
              <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                ¿Eliminar definitivamente este paquete esperado? <b>Solo se permite si aún está en Esperado.</b>
              </div>
            `,
                        'Eliminar',
                        true
                    );
                    els.btnSaveModal.classList.remove('hidden');
                    return;
                }

                if (act === 'recibido') {
                    openModal(
                        'Confirmar recibido',
                        'confirm_entregado',
                        `
              <input type="hidden" name="paq_id" value="${escapeHtml(String(p.id))}">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                Confirma que ya recibiste este paquete.
              </div>
            `,
                        'Confirmar'
                    );
                    els.btnSaveModal.classList.remove('hidden');
                    return;
                }

                if (act === 'incidencia') {
                    openModal(
                        'Recibido con incidencia',
                        'confirm_entregado_incidencia',
                        `
              <input type="hidden" name="paq_id" value="${escapeHtml(String(p.id))}">

              <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                Foto y descripción son obligatorias para dejar evidencia.
              </div>

              <div>
                <label class="block text-xs text-slate-600 mb-1">¿Qué pasó? *</label>
                <input name="incidencia_detalle"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                  placeholder="Ej. caja abierta, golpeado, faltante..." required>
              </div>

              <div>
                <label class="block text-xs text-slate-600 mb-1">Foto evidencia *</label>
                <input type="file" name="foto" accept="image/*"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
              </div>
            `,
                        'Confirmar',
                        true
                    );
                    els.btnSaveModal.classList.remove('hidden');
                    return;
                }

                if (act === 'rechazar') {
                    openModal(
                        'Rechazar paquete',
                        'confirm_rechazado',
                        `
              <input type="hidden" name="paq_id" value="${escapeHtml(String(p.id))}">

              <div>
                <label class="block text-xs text-slate-600 mb-1">Motivo *</label>
                <select name="motivo_detalle"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
                  <option value="">Selecciona…</option>
                  <option value="No era mío">No era mío</option>
                  <option value="Llegó dañado">Llegó dañado</option>
                  <option value="Guía incorrecta">Guía incorrecta</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>

              <div>
                <label class="block text-xs text-slate-600 mb-1">Detalle (opcional)</label>
                <input name="motivo_extra"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                  placeholder="Opcional">
              </div>
            `,
                        'Rechazar',
                        true
                    );
                    els.btnSaveModal.classList.remove('hidden');
                    return;
                }

                if (act === 'no_llego') {
                    openModal(
                        'Marcar como No llegó',
                        'confirm_no_llego',
                        `
              <input type="hidden" name="paq_id" value="${escapeHtml(String(p.id))}">

              <div>
                <label class="block text-xs text-slate-600 mb-1">Motivo *</label>
                <select name="motivo_detalle"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800" required>
                  <option value="">Selecciona…</option>
                  <option value="No lo recibí">No lo recibí</option>
                  <option value="Se lo llevaron">Se lo llevaron</option>
                  <option value="Otro">Otro</option>
                </select>
              </div>

              <div>
                <label class="block text-xs text-slate-600 mb-1">Detalle (opcional)</label>
                <input name="motivo_extra"
                  class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
                  placeholder="Opcional">
              </div>
            `,
                        'Confirmar',
                        true
                    );
                    els.btnSaveModal.classList.remove('hidden');
                    return;
                }
            });
        });
    }

    async function loadList() {
        const json = await apiPost(`${API}paqueteria.php`, { action: 'list' });
        state.items = json.data?.items || [];
        state.caseta_phone = json.data?.caseta_phone || null;

        if (state.caseta_phone) {
            els.casetaWrap.classList.remove('hidden');
            els.casetaTel.href = `tel:${state.caseta_phone}`;
        } else {
            els.casetaWrap.classList.add('hidden');
        }

        render();
    }

    // Crear paquete esperado
    els.btnCreate?.addEventListener('click', () => {
        openModal(
            'Registrar paquete esperado',
            'create_expected',
            `
        <div>
          <label class="block text-xs text-slate-600 mb-1">Empresa (opcional)</label>
          <input name="empresa"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
            placeholder="Amazon, MercadoLibre, DHL...">
        </div>

        <div>
          <label class="block text-xs text-slate-600 mb-1">Descripción *</label>
          <input name="descripcion"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
            placeholder="Caja, sobre, etc." required>
        </div>

        <div>
          <label class="block text-xs text-slate-600 mb-1">Código de rastreo (opcional)</label>
          <input name="codigo_rastreo"
            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800"
            placeholder="Tracking number">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs text-slate-600 mb-1">Instrucción</label>
            <select name="instruccion_entrega"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
              <option value="recepcion_caseta" selected>Recibir en caseta</option>
              <option value="acceso_domicilio">Dejar pasar</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-slate-600 mb-1">Fecha estimada (opcional)</label>
            <input type="date" name="fecha_estimada"
              class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800">
          </div>
        </div>
      `
        );
        els.btnSaveModal.classList.remove('hidden');
    });

    // filtros
    document.querySelectorAll('.paqFilter').forEach(btn => {
        btn.addEventListener('click', () => {
            state.filter = btn.dataset.filter || 'todos';
            render();
        });
    });

    // modal events
    els.btnCloseModal?.addEventListener('click', () => { els.btnSaveModal.classList.remove('hidden'); closeModal(); });
    els.btnCancelModal?.addEventListener('click', () => { els.btnSaveModal.classList.remove('hidden'); closeModal(); });
    els.modal?.addEventListener('click', (e) => {
        if (e.target === els.modal) { els.btnSaveModal.classList.remove('hidden'); closeModal(); }
    });

    // submit
    els.modalForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideAlert();

        const action = els.modalAction.value;
        const fd = new FormData(els.modalForm);
        fd.set('action', action);

        try {
            // noop (solo lectura)
            if (action === 'noop') {
                closeModal();
                return;
            }

            // Acciones paquetería
            const json = await apiPost(`${API}paqueteria.php`, fd);
            showAlert('ok', json.message || 'Listo ✅');

            els.btnSaveModal.classList.remove('hidden');
            closeModal();
            await loadList();

            // refrescar header (por si quieres actualizar nombre/dirección en general)
            try {
                if (window.ResidenteDashboard && typeof window.ResidenteDashboard.loadContext === 'function') {
                    await window.ResidenteDashboard.loadContext();
                }
            } catch { /* noop */ }

        } catch (err) {
            // aquí mantenemos modal abierto para no perder info,
            // pero el alert queda visible arriba (si lo quieres cerrar en error, lo cambiamos)
            showAlert('error', err.message || 'Error');
        }
    });

    (async function init() {
        try {
            await loadList();
        } catch (e) {
            showAlert('error', e.message || 'No se pudo cargar paquetería.');
        }
    })();

})();