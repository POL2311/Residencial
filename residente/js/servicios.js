(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentServiciosView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/servicios.php';
    const els = {
        alert: document.getElementById('serviciosAlert'),
        list: document.getElementById('residentServiciosList'),
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
        if (!json || !json.ok) throw new Error(json?.error || 'No se pudo cargar servicios.');
        const items = json.data?.items || [];
        els.list.innerHTML = items.length ? items.map((item) => `
          <article class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-sm">
            <div class="h-28 ${item.imagen_url ? 'bg-slate-100' : 'bg-gradient-to-br from-[#DCE9EE] via-[#EEF4F6] to-[#B9CCD5]'}">
              ${item.imagen_url ? `<img src="${escapeHtml(item.imagen_url)}" alt="${escapeHtml(item.nombre || 'Servicio')}" class="h-full w-full object-cover" loading="lazy" />` : ''}
            </div>
            <div class="p-5">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-[11px] uppercase tracking-wide text-slate-400">${escapeHtml(item.categoria || 'servicio')}</div>
                  <div class="mt-2 text-lg font-semibold text-slate-800">${escapeHtml(item.nombre || 'Servicio')}</div>
                </div>
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${item.origen === 'global' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                  ${item.origen === 'global' ? 'Global' : 'Residencial'}
                </span>
              </div>
              <p class="mt-3 text-sm leading-6 text-slate-600">${escapeHtml(item.descripcion || 'Sin descripción disponible.')}</p>
              <div class="mt-4 flex flex-wrap gap-2">
                ${item.telefono ? `<a href="tel:${escapeHtml(item.telefono)}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Llamar</a>` : ''}
                ${item.whatsapp ? `<a href="https://wa.me/52${escapeHtml(String(item.whatsapp).replace(/\D+/g, ''))}" target="_blank" rel="noreferrer" class="rounded-full bg-[#2E5D73] px-3 py-2 text-xs text-white hover:opacity-95">WhatsApp</a>` : ''}
                ${item.link_url ? `<a href="${escapeHtml(item.link_url)}" target="_blank" rel="noreferrer" class="rounded-full bg-sky-50 px-3 py-2 text-xs text-sky-700 hover:bg-sky-100">Ver más</a>` : ''}
              </div>
            </div>
          </article>
        `).join('') : `<div class="xl:col-span-2 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">No hay servicios disponibles por el momento.</div>`;
    }

    load().catch((err) => showError(err.message || 'No se pudo cargar servicios.'));
})();
