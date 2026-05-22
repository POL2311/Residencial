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
          <article class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm mb-4">
            <div class="flex items-start justify-between gap-3">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-500">
                  <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <div>
                  <h3 class="text-base font-semibold text-slate-800">${escapeHtml(item.titulo)}</h3>
                  <p class="text-xs text-slate-500">Tipo: ${escapeHtml(item.tipo || '—')}</p>
                </div>
              </div>
              <div class="flex flex-col items-end gap-1">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${priorityBadge(item.prioridad)}">
                  Prioridad: ${escapeHtml(item.prioridad)}
                </span>
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium ${badge(item.estado)}">
                  Estado: ${escapeHtml(item.estado)}
                </span>
              </div>
            </div>

            <div class="mt-4 rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
              <p class="whitespace-pre-wrap">${escapeHtml(item.descripcion || 'Sin descripción')}</p>
            </div>

            <div class="mt-4 hidden space-y-2 text-sm text-slate-600">
              <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span>Fecha: <span class="font-medium text-slate-700">${escapeHtml(item.created_at || '—')}</span></span>
              </div>
              ${item.guardia_nombre ? `
              <div class="flex items-center gap-2">
                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                </svg>
                <span>Atendida por: <span class="font-medium text-slate-700">${escapeHtml(item.guardia_nombre)}</span></span>
              </div>` : ''}
            </div>

            <div class="mt-4 border-t border-slate-100 pt-3 text-center">
              <button type="button" class="text-sm font-medium text-[#EF5A5A] hover:text-[#d94848] transition-colors" onclick="
                const details = this.parentElement.previousElementSibling;
                if (details.classList.contains('hidden')) {
                  details.classList.remove('hidden');
                  this.textContent = 'Ver menos';
                } else {
                  details.classList.add('hidden');
                  this.textContent = 'Ver más';
                }
              ">
                Ver más
              </button>
            </div>
          </article>
        `).join('') : `<div class="rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 text-center text-sm text-slate-500">Aun no has reportado incidencias.</div>`;
    }

    els.btnNew?.addEventListener('click', () => els.modal.classList.remove('hidden'));
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
