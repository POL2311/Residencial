(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentComunicadosView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/comunicados.php';
    const els = {
        alert: document.getElementById('comunicadosAlert'),
        list: document.getElementById('residentComunicadosList'),
    };

    function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function badge(priority) {
        if (priority === 'alta') return 'bg-rose-50 text-rose-700 border border-rose-200';
        if (priority === 'media') return 'bg-amber-50 text-amber-700 border border-amber-200';
        return 'bg-sky-50 text-sky-700 border border-sky-200';
    }

    function showError(msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = 'rounded-2xl px-4 py-3 text-sm bg-rose-50 text-rose-800 border border-rose-200';
        els.alert.textContent = msg;
    }

    async function load() {
        const res = await fetch(API, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        const json = await res.json().catch(() => null);
        if (!json || !json.ok) throw new Error(json?.error || 'No se pudo cargar comunicados.');
        const items = json.data?.items || [];
        els.list.innerHTML = items.length ? items.map((item) => `
          <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-lg font-semibold text-slate-800">${escapeHtml(item.titulo)}</div>
                <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.tipo || 'general')} · ${escapeHtml(item.fecha_publicacion || '')}</div>
              </div>
              <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${badge(item.prioridad)}">${escapeHtml(item.prioridad || 'baja')}</span>
            </div>
            <p class="mt-4 whitespace-pre-wrap text-sm leading-6 text-slate-600">${escapeHtml(item.mensaje || '')}</p>
          </article>
        `).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay comunicados publicados por el momento.</div>`;
    }

    load().catch((err) => showError(err.message || 'No se pudo cargar comunicados.'));
})();
