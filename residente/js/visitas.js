(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentVisitasView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/visitas.php';
    const els = {
        alert: document.getElementById('visitasAlert'),
        list: document.getElementById('residentVisitasList'),
        statTotal: document.getElementById('visitasStatTotal'),
        statPendientes: document.getElementById('visitasStatPendientes'),
        statUsadas: document.getElementById('visitasStatUsadas'),
        statCanceladas: document.getElementById('visitasStatCanceladas'),
        btnNew: document.getElementById('btnNuevaVisitaResident'),
        modal: document.getElementById('residentVisitasModal'),
        modalTitle: document.getElementById('residentVisitasModalTitle'),
        form: document.getElementById('residentVisitasForm'),
        btnClose: document.getElementById('btnCloseResidentVisitasModal'),
        btnCancel: document.getElementById('btnCancelResidentVisitasModal'),
        codeModal: document.getElementById('residentVisitasCodeModal'),
        codeValue: document.getElementById('residentVisitasCodeValue'),
        btnCloseCode: document.getElementById('btnCloseResidentVisitasCodeModal'),
        btnDismissCode: document.getElementById('btnDismissResidentVisitasCodeModal'),
        btnCopyCode: document.getElementById('btnCopyResidentVisitasCode'),
    };

    let items = [];
    let currentCode = '';

    function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm border ${type === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'}`;
        els.alert.textContent = msg;
    }

    function renderUnavailable(message) {
        if (els.statTotal) els.statTotal.textContent = '0';
        if (els.statPendientes) els.statPendientes.textContent = '0';
        if (els.statUsadas) els.statUsadas.textContent = '0';
        if (els.statCanceladas) els.statCanceladas.textContent = '0';
        if (els.list) {
            els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">${escapeHtml(message)}</div>`;
        }
    }

    function statusBadge(status) {
        if (status === 'usado') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        if (status === 'cancelado') return 'bg-rose-50 text-rose-700 border border-rose-200';
        if (status === 'vencido') return 'bg-slate-100 text-slate-600 border border-slate-200';
        return 'bg-amber-50 text-amber-700 border border-amber-200';
    }

    async function api(data) {
        const fd = new FormData();
        Object.keys(data || {}).forEach((key) => fd.append(key, data[key]));
        const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json().catch(() => null);
        if (!res.ok) {
            if (res.status === 404) throw new Error('No se encontró el endpoint de visitas. Verifica que XAMPP esté sirviendo esta copia del proyecto.');
            if (res.status === 403 || res.status === 422) throw new Error(json?.error || 'Tu cuenta residente no tiene una unidad activa configurada.');
            throw new Error(json?.error || 'Error de servidor');
        }
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.form.reset();
        document.body.style.overflow = '';
    }

    function openFormModal() {
        els.modalTitle.textContent = 'Nueva visita';
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCodeModal() {
        if (!els.codeModal) return;
        els.codeModal.classList.add('hidden');
        currentCode = '';
        document.body.style.overflow = '';
    }

    function openCodeModal(code) {
        currentCode = String(code || '').trim();
        if (els.codeValue) els.codeValue.textContent = currentCode || '----';
        els.codeModal?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function renderStats() {
        if (els.statTotal) els.statTotal.textContent = String(items.length);
        if (els.statPendientes) els.statPendientes.textContent = String(items.filter((item) => item.estado === 'pendiente').length);
        if (els.statUsadas) els.statUsadas.textContent = String(items.filter((item) => item.estado === 'usado').length);
        if (els.statCanceladas) els.statCanceladas.textContent = String(items.filter((item) => item.estado === 'cancelado').length);
    }

    function render() {
        renderStats();
        els.list.innerHTML = items.length ? items.map((item) => `
          <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.nombre_visitante || 'Visitante')}</div>
                <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.tipo || 'visita')} · ${escapeHtml(item.fecha_desde || '')} ${item.hora_desde ? '· ' + escapeHtml(item.hora_desde) : ''}</div>
              </div>
              <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${statusBadge(item.estado)}">${escapeHtml(item.estado || 'pendiente')}</span>
            </div>
            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-600">
              <div><span class="text-slate-500">Codigo:</span> <span class="font-semibold text-slate-800">${escapeHtml(item.codigo_acceso || '')}</span></div>
              <div><span class="text-slate-500">Uso unico:</span> <span class="font-semibold text-slate-800">${Number(item.uso_unico || 0) === 1 ? 'Si' : 'No'}</span></div>
              <div><span class="text-slate-500">Motivo:</span> ${escapeHtml(item.motivo || '—')}</div>
              <div><span class="text-slate-500">Placa:</span> ${escapeHtml(item.placa_vehiculo || '—')}</div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
              <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700" data-code="${escapeHtml(item.codigo_acceso || '')}">Ver codigo</button>
              ${item.estado === 'pendiente' ? `<button type="button" class="rounded-full border border-rose-200 bg-white px-3 py-1.5 text-xs text-rose-700" data-cancel="${item.id}">Cancelar</button>` : ''}
            </div>
          </article>
        `).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Aun no has registrado visitas.</div>`;

        els.list.querySelectorAll('[data-code]').forEach((btn) => btn.addEventListener('click', async () => {
            openCodeModal(btn.dataset.code || '');
        }));
        els.list.querySelectorAll('[data-cancel]').forEach((btn) => btn.addEventListener('click', async () => {
            try {
                await api({ action: 'cancel', visit_id: btn.dataset.cancel });
                await load();
                showAlert('ok', 'Visita cancelada correctamente.');
            } catch (err) {
                showAlert('error', err.message || 'No se pudo cancelar la visita.');
            }
        }));
    }

    async function load() {
        const json = await api({ action: 'list' });
        items = json.data?.items || [];
        render();
    }

    els.btnNew?.addEventListener('click', openFormModal);
    els.btnClose?.addEventListener('click', closeModal);
    els.btnCancel?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    els.btnCloseCode?.addEventListener('click', closeCodeModal);
    els.btnDismissCode?.addEventListener('click', closeCodeModal);
    els.codeModal?.addEventListener('click', (e) => { if (e.target === els.codeModal) closeCodeModal(); });
    els.btnCopyCode?.addEventListener('click', async () => {
        try {
            await navigator.clipboard?.writeText(currentCode || '');
            showAlert('ok', `Código copiado: ${currentCode || ''}`);
        } catch (_) {
            showAlert('error', 'No se pudo copiar el código automáticamente.');
        }
    });
    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(els.form);
        const payload = { action: 'create' };
        fd.forEach((value, key) => { payload[key] = value; });
        if (!payload.uso_unico) payload.uso_unico = '0';
        try {
            await api(payload);
            closeModal();
            await load();
            showAlert('ok', 'Visita registrada correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar la visita.');
        }
    });

    load().catch((err) => {
        renderUnavailable('No se pudieron cargar visitas para tu cuenta.');
        showAlert('error', err.message || 'No se pudieron cargar visitas.');
    });
})();
