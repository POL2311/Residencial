(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentIncidenciasView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/incidencias.php';
    const els = {
        alert: document.getElementById('incidenciasAlert'),
        list: document.getElementById('residentIncidenciasList'),
        btnNew: document.getElementById('btnNuevaIncidenciaResident'),
        modal: document.getElementById('residentIncidenciasModal'),
        form: document.getElementById('residentIncidenciasForm'),
        btnClose: document.getElementById('btnCloseResidentIncModal'),
        btnCancel: document.getElementById('btnCancelResidentIncModal'),
    };

    function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm border ${type === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'}`;
        els.alert.textContent = msg;
    }

    function renderEmpty(message) {
        if (!els.list) return;
        els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">${escapeHtml(message)}</div>`;
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.form.reset();
        document.body.style.overflow = '';
    }

    function badge(estado) {
        if (estado === 'cerrada') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        if (estado === 'en_proceso') return 'bg-amber-50 text-amber-700 border border-amber-200';
        return 'bg-rose-50 text-rose-700 border border-rose-200';
    }

    function priorityBadge(priority) {
        if (priority === 'alta') return 'bg-rose-50 text-rose-700 border border-rose-200';
        if (priority === 'media') return 'bg-amber-50 text-amber-700 border border-amber-200';
        return 'bg-sky-50 text-sky-700 border border-sky-200';
    }

    async function api(data) {
        const fd = new FormData();
        Object.keys(data || {}).forEach((key) => fd.append(key, data[key]));
        const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json().catch(() => null);
        if (!res.ok) {
            if (res.status === 404) throw new Error('No se encontró el endpoint de incidencias. Verifica que XAMPP esté sirviendo esta copia del proyecto.');
            if (res.status === 403 || res.status === 422) throw new Error(json?.error || 'Tu cuenta residente no tiene una unidad activa configurada.');
            throw new Error(json?.error || 'Error de servidor');
        }
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    async function load() {
        const json = await api({ action: 'list' });
        const items = json.data?.items || [];
        els.list.innerHTML = items.length ? items.map((item) => `
          <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0">
                <div class="text-xs uppercase tracking-wide text-slate-400">Título</div>
                <div class="mt-1 break-words text-sm font-semibold text-slate-800">${escapeHtml(item.titulo)}</div>
              </div>
              <div class="flex max-w-full flex-wrap gap-2 sm:shrink-0 sm:justify-end">
                <span class="inline-flex max-w-full rounded-full px-2.5 py-1 text-[11px] break-words ${priorityBadge(item.prioridad)}">Prioridad: ${escapeHtml(item.prioridad)}</span>
                <span class="inline-flex max-w-full rounded-full px-2.5 py-1 text-[11px] break-words ${badge(item.estado)}">Estado: ${escapeHtml(item.estado)}</span>
              </div>
            </div>
            <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600">
              <div class="break-words">
                <span class="text-slate-500">Tipo:</span>
                <span class="font-medium text-slate-700">${escapeHtml(item.tipo || '—')}</span>
              </div>
              <div class="break-words">
                <span class="text-slate-500">Fecha:</span>
                <span class="font-medium text-slate-700">${escapeHtml(item.created_at || '—')}</span>
              </div>
              <div class="break-words">
                <span class="text-slate-500">Descripción:</span>
                <span class="whitespace-pre-wrap font-medium text-slate-700">${escapeHtml(item.descripcion || 'Sin descripción')}</span>
              </div>
              ${item.guardia_nombre ? `<div class="break-words"><span class="text-slate-500">Atendida por:</span> <span class="font-medium text-slate-700">${escapeHtml(item.guardia_nombre)}</span></div>` : ''}
            </div>
          </article>
        `).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Aun no has reportado incidencias.</div>`;
    }

    els.btnNew?.addEventListener('click', () => {
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    });
    els.btnClose?.addEventListener('click', closeModal);
    els.btnCancel?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(els.form);
        const payload = { action: 'create' };
        fd.forEach((value, key) => { payload[key] = value; });
        try {
            await api(payload);
            closeModal();
            await load();
            showAlert('ok', 'Incidencia registrada correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar la incidencia.');
        }
    });

    load().catch((err) => {
        renderEmpty('No se pudieron cargar incidencias para tu cuenta.');
        showAlert('error', err.message || 'No se pudieron cargar incidencias.');
    });
})();
