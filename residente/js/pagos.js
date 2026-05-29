(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentPagosView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/pagos.php';
    const els = {
        alert: document.getElementById('pagosAlert'),
        count: document.getElementById('pagosCount'),
        total: document.getElementById('pagosTotal'),
        ultimo: document.getElementById('pagosUltimo'),
        list: document.getElementById('residentPagosList'),
    };

    function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
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
        if (!json || !json.ok) throw new Error(json?.error || 'No se pudo cargar pagos.');
        const summary = json.data?.summary || {};
        const items = json.data?.items || [];
        if (els.count) els.count.textContent = String(summary.count ?? 0);
        if (els.total) els.total.textContent = `$${Number(summary.total ?? 0).toFixed(2)}`;
        if (els.ultimo) els.ultimo.textContent = summary.ultimo_pago || '—';

        els.list.innerHTML = items.length ? items.map((item) => `
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <div class="break-words text-sm font-semibold text-slate-800">$${Number(item.monto || 0).toFixed(2)}</div>
              <div class="mt-1 break-words text-xs text-slate-500">${escapeHtml(item.concepto || 'Sin concepto')} · ${escapeHtml(item.metodo || 'Sin metodo')}</div>
            </div>
            <div class="min-w-0 text-left sm:text-right sm:shrink-0">
              <div class="break-words text-sm font-medium text-slate-700">${escapeHtml(item.fecha || '')}</div>
              <div class="mt-1 text-xs ${Number(item.activo || 0) === 1 ? 'text-emerald-600' : 'text-slate-400'}">${Number(item.activo || 0) === 1 ? 'Activo' : 'Inactivo'}</div>
            </div>
          </div>
        `).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay pagos registrados para mostrar.</div>`;
    }

    load().catch((err) => showError(err.message || 'No se pudo cargar pagos.'));
})();
