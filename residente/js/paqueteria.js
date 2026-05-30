(function () {
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
        statTotal: $('paqStatTotal'),
        statPendientes: $('paqStatPendientes'),
        statEntregados: $('paqStatEntregados'),
        filterStatus: $('paqFilterStatus'),
        btnRefresh: $('btnRefreshPaq'),
        list: $('paqList'),
        empty: $('paqEmpty'),
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
    const FORM_LABEL = 'mb-1 block text-xs font-medium text-slate-600';
    const FORM_CONTROL = 'w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#2E5D73] focus:ring-2 focus:ring-[#2E5D73]/15';
    const SOFT_CARD = 'rounded-2xl border border-slate-200 bg-slate-50/80 p-3';
    const PRIMARY_ACTION = 'min-h-11 flex-1 rounded-xl bg-[#2E5D73] px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95';
    const DANGER_ACTION = 'min-h-11 flex-1 rounded-xl bg-rose-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:opacity-95';

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

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
        els.alert.textContent = '';
    }

    function openModal(title, action, bodyHtml, saveText = 'Guardar', danger = false) {
        els.modalTitle.textContent = title;
        els.modalAction.value = action;
        els.modalBody.innerHTML = bodyHtml;
        els.btnSaveModal.textContent = saveText;
        els.btnSaveModal.className = danger ? DANGER_ACTION : PRIMARY_ACTION;
        els.btnSaveModal.classList.remove('hidden');
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.modalBody.innerHTML = '';
        els.modalAction.value = '';
        els.modalForm.reset();
        els.btnSaveModal.classList.remove('hidden');
        document.body.style.overflow = '';
    }

    async function apiPost(url, data) {
        const body = new FormData();
        Object.keys(data || {}).forEach((key) => body.append(key, data[key]));

        const res = await fetch(url, {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        });

        const json = await res.json().catch(() => null);
        if (!res.ok) {
            if (res.status === 404) {
                throw new Error(json?.error || 'No se encontró el endpoint de paquetería. Verifica que XAMPP esté sirviendo esta copia del proyecto.');
            }
            if (res.status === 403 || res.status === 422) {
                throw new Error(json?.error || 'Tu cuenta residente no tiene una unidad activa configurada.');
            }
            throw new Error(json?.error || 'Error de servidor');
        }
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    function badgeFor(estado) {
        if (estado === 'entregado') return 'bg-emerald-50 border-emerald-200 text-emerald-800';
        if (estado === 'devuelto') return 'bg-rose-50 border-rose-200 text-rose-800';
        return 'bg-amber-50 border-amber-200 text-amber-800';
    }

    function titleFor(estado) {
        if (estado === 'entregado') return 'Entregado';
        if (estado === 'devuelto') return 'Devuelto';
        return 'Pendiente';
    }

    function applyFilter(items) {
        if (state.filter === 'todos') return items;
        return items.filter((item) => String(item.estado || '') === state.filter);
    }

    function filterLabel() {
        if (state.filter === 'registrado') return 'Pendientes';
        if (state.filter === 'entregado') return 'Entregados';
        if (state.filter === 'devuelto') return 'Devueltos';
        return 'Todos';
    }

    function renderStats() {
        const items = state.items || [];
        const pendientes = items.filter((item) => String(item.estado || '') === 'registrado').length;
        const entregados = items.filter((item) => String(item.estado || '') === 'entregado').length;

        if (els.statTotal) els.statTotal.textContent = String(items.length);
        if (els.statPendientes) els.statPendientes.textContent = String(pendientes);
        if (els.statEntregados) els.statEntregados.textContent = String(entregados);
        if (els.filterStatus) els.filterStatus.textContent = filterLabel();
    }

    function buildActionsHtml(p) {
        const estado = String(p.estado || '');
        const canCall = !!state.caseta_phone;

        const callBtn = canCall
            ? `<a class="inline-flex min-h-11 items-center justify-center text-xs px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
                href="tel:${escapeHtml(state.caseta_phone)}">Llamar</a>`
            : '';

        if (estado === 'registrado') {
            return `
                <div class="flex w-full flex-wrap justify-start gap-2 sm:justify-end">
                  ${callBtn}
                  <button type="button"
                    class="min-h-11 text-xs px-4 py-2 rounded-full bg-[#2E5D73] text-white hover:opacity-95"
                    data-act="confirm">Confirmar recibido</button>

                  <button type="button"
                    class="min-h-11 text-xs px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
                    data-act="return">Reportar devolución</button>
                </div>
            `;
        }

        return `
            <div class="flex w-full flex-wrap justify-start gap-2 sm:justify-end">
              ${callBtn}
              <button type="button"
                class="min-h-11 text-xs px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-700 hover:bg-slate-100"
                data-act="view">Ver detalle</button>
            </div>
        `;
    }

    function bindRowActions(row, item) {
        row.querySelectorAll('[data-act]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const act = btn.getAttribute('data-act');

                if (act === 'view') {
                    openModal(
                        'Detalle del paquete',
                        'noop',
                        `
                            <div class="${SOFT_CARD} space-y-2 text-sm text-slate-700">
                              <div class="break-words"><span class="text-slate-500 text-xs">Estado:</span> <b>${escapeHtml(titleFor(item.estado))}</b></div>
                              <div class="break-words"><span class="text-slate-500 text-xs">Empresa:</span> <b>${escapeHtml(item.empresa || '—')}</b></div>
                              <div class="break-words"><span class="text-slate-500 text-xs">Descripción:</span> <b>${escapeHtml(item.descripcion || '—')}</b></div>
                              ${item.codigo_rastreo ? `<div class="break-words"><span class="text-slate-500 text-xs">Tracking:</span> <b>${escapeHtml(item.codigo_rastreo)}</b></div>` : ''}
                              ${item.notas ? `<div class="break-words"><span class="text-slate-500 text-xs">Notas:</span> <b>${escapeHtml(item.notas)}</b></div>` : ''}
                              ${item.guardia_nombre ? `<div class="break-words"><span class="text-slate-500 text-xs">Registrado por:</span> <b>${escapeHtml(item.guardia_nombre)}</b></div>` : ''}
                            </div>
                        `,
                        'Cerrar'
                    );
                    els.btnSaveModal.classList.add('hidden');
                    return;
                }

                if (act === 'confirm') {
                    openModal(
                        'Confirmar recibido',
                        'confirm_entregado',
                        `
                            <input type="hidden" name="paq_id" value="${escapeHtml(String(item.id))}">
                            <div class="${SOFT_CARD} text-sm text-slate-700">
                              Confirma que ya recibiste este paquete.
                            </div>
                        `,
                        'Confirmar'
                    );
                    return;
                }

                if (act === 'return') {
                    openModal(
                        'Reportar devolución',
                        'confirm_devuelto',
                        `
                            <input type="hidden" name="paq_id" value="${escapeHtml(String(item.id))}">
                            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                              Este paquete se marcará como <b>Devuelto</b>.
                            </div>

                            <div>
                              <label class="${FORM_LABEL}">Motivo *</label>
                              <textarea name="motivo"
                                class="${FORM_CONTROL} resize-none"
                                rows="2"
                                placeholder="Ej. no me pertenece, dirección incorrecta, no puedo recibirlo..."
                                required></textarea>
                            </div>
                        `,
                        'Marcar devuelto',
                        true
                    );
                }
            });
        });
    }

    function render() {
        const items = applyFilter(state.items || []);
        els.list.innerHTML = '';
        renderStats();

        if (!items.length) {
            els.empty.classList.remove('hidden');
        } else {
            els.empty.classList.add('hidden');
        }

        items.forEach((p) => {
            const row = document.createElement('div');
            row.className = 'px-4 py-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between';
            row.innerHTML = `
                <div class="min-w-0 break-words">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="inline-flex max-w-full items-center px-2 py-0.5 rounded-full border text-[11px] break-words ${badgeFor(p.estado)}">
                      ${escapeHtml(titleFor(p.estado))}
                    </span>
                    <div class="text-sm font-semibold text-slate-800 break-words">
                      ${escapeHtml((p.empresa || 'Paquete').trim())}
                    </div>
                  </div>

                  <div class="text-xs text-slate-500 mt-0.5">
                    ${escapeHtml(p.created_at || '')}
                  </div>

                  <div class="text-xs text-slate-700 mt-2 break-words">
                    ${escapeHtml((p.descripcion || '—').trim())}
                  </div>

                  ${p.codigo_rastreo ? `<div class="text-[11px] text-slate-500 mt-1 break-words">Tracking: <span class="font-medium text-slate-700">${escapeHtml(p.codigo_rastreo)}</span></div>` : ''}
                  ${p.notas ? `<div class="text-[11px] text-slate-500 mt-2 break-words">Notas: <span class="text-slate-700">${escapeHtml(p.notas)}</span></div>` : ''}
                </div>

                <div class="flex w-full flex-col items-start gap-2 sm:w-auto sm:shrink-0 sm:items-end">
                  ${buildActionsHtml(p)}
                </div>
            `;

            els.list.appendChild(row);
            bindRowActions(row, p);
        });

        document.querySelectorAll('.paqFilter').forEach((btn) => {
            const active = btn.dataset.filter === state.filter;
            btn.classList.toggle('bg-slate-50', active);
            btn.classList.toggle('bg-white', !active);
            btn.classList.toggle('text-slate-900', active);
            btn.classList.toggle('text-slate-700', !active);
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

    document.querySelectorAll('.paqFilter').forEach((btn) => {
        btn.addEventListener('click', () => {
            state.filter = btn.dataset.filter || 'todos';
            render();
        });
    });

    els.btnRefresh?.addEventListener('click', () => {
        hideAlert();
        loadList().then(() => {
            showAlert('ok', 'Paquetería actualizada.');
        }).catch((err) => {
            showAlert('error', err.message || 'No se pudo actualizar la paquetería.');
        });
    });

    // Evitar cualquier submit nativo del formulario
    els.modalForm?.addEventListener('submit', (e) => {
        e.preventDefault();
        e.stopPropagation();
    });

    els.btnSaveModal?.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        hideAlert();

        if (els.modalForm && !els.modalForm.reportValidity()) {
            return;
        }

        const fd = new FormData(els.modalForm);
        const action = els.modalAction.value || '';

        if (!action || action === 'noop') {
            closeModal();
            return;
        }

        try {
            const payload = {};
            fd.forEach((value, key) => {
                payload[key] = value;
            });

            const json = await apiPost(`${API}paqueteria.php`, payload);
            closeModal();
            await loadList();
            showAlert('ok', json.message || 'Operación realizada.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo completar la acción.');
        }
    });

    els.btnCloseModal?.addEventListener('click', closeModal);
    els.btnCancelModal?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => {
        if (e.target === els.modal) closeModal();
    });

    loadList().catch((err) => {
        state.items = [];
        render();
        showAlert('error', err.message || 'No se pudo cargar la paquetería.');
    });
})();
