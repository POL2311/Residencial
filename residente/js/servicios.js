(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    function appRootBase() {
        const p = window.location.pathname || '';
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '';
        return p.slice(0, idx);
    }

    function resolvePublicUrl(url) {
        const u = String(url ?? '').trim();
        if (!u) return '';
        if (u.startsWith('data:')) return u;
        if (/^https?:\/\//i.test(u)) {
            try {
                const parsed = new URL(u);
                const base = appRootBase();
                if (base && parsed.pathname.startsWith('/assets/') && !parsed.pathname.startsWith(base + '/assets/')) {
                    parsed.pathname = base + parsed.pathname;
                    return parsed.toString();
                }
            } catch (_) {
                // ignore parse errors
            }
            return u;
        }
        if (u.startsWith('/assets/')) return appRootBase() + u;
        return u;
    }

    const root = document.getElementById('residentServiciosView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/servicios.php';
    const els = {
        alert: document.getElementById('serviciosAlert'),
        list: document.getElementById('residentServiciosList'),
    };

    let services = [];

    function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function showError(msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = 'rounded-2xl px-4 py-3 text-sm bg-rose-50 text-rose-800 border border-rose-200';
        els.alert.textContent = msg;
    }

    function shortText(text, max = 100) {
        const clean = String(text || '').replace(/\s+/g, ' ').trim();
        if (!clean) return 'Sin descripción disponible.';
        return clean.length > max ? `${clean.slice(0, max).trim()}…` : clean;
    }

    function ensureDetailModal() {
        let modal = document.getElementById('residentServicioDetailModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'residentServicioDetailModal';
        modal.className = 'hidden fixed inset-0 w-screen h-[100dvh] z-50 bg-black/70 p-4 backdrop-blur-sm';
        modal.innerHTML = `
          <div class="min-h-full flex items-center justify-center">
            <div class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl bg-white shadow-2xl">
              <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div id="residentServicioModalTitle" class="text-sm font-semibold text-slate-800">Detalle del servicio</div>
                <button type="button" id="residentServicioModalClose" class="h-11 w-11 rounded-full border border-slate-200 bg-slate-50 text-xl text-slate-500 hover:bg-slate-100">×</button>
              </div>
              <div id="residentServicioModalBody" class="flex-1 overflow-y-auto"></div>
            </div>
          </div>
        `;
        document.body.appendChild(modal);

        const close = () => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('#residentServicioModalClose')) {
                close();
            }
        });

        return modal;
    }

    function openDetailModal(item) {
        const modal = ensureDetailModal();
        const title = modal.querySelector('#residentServicioModalTitle');
        const body = modal.querySelector('#residentServicioModalBody');
        if (title) title.textContent = item?.nombre || 'Detalle del servicio';
        if (body) {
            const imageUrl = resolvePublicUrl(item?.imagen_url || '');
            const waDigits = String(item?.whatsapp || '').replace(/\D+/g, '');
            body.innerHTML = `
              <article class="overflow-hidden">
                <div class="h-56 ${imageUrl ? 'bg-slate-100' : 'bg-gradient-to-br from-[#DCE9EE] via-[#EEF4F6] to-[#B9CCD5]'}">
                  ${imageUrl ? `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(item?.nombre || 'Servicio')}" class="h-full w-full object-cover" loading="lazy" />` : ''}
                </div>
                <div class="space-y-4 p-5">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${item?.origen === 'global' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                      ${item?.origen === 'global' ? 'Global' : 'Residencial'}
                    </span>
                    ${item?.categoria ? `<span class="inline-flex rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] text-slate-600">${escapeHtml(item.categoria)}</span>` : ''}
                  </div>
                  <div>
                    <div class="text-2xl font-semibold text-slate-800">${escapeHtml(item?.nombre || 'Servicio')}</div>
                    <p class="mt-3 text-sm leading-6 text-slate-600">${escapeHtml(item?.descripcion || 'Sin descripción disponible.')}</p>
                  </div>
                  <div class="flex flex-wrap gap-2">
                    ${item?.telefono ? `<a href="tel:${escapeHtml(item.telefono)}" class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Llamar</a>` : ''}
                    ${waDigits ? `<a href="https://wa.me/52${escapeHtml(waDigits)}" target="_blank" rel="noreferrer" class="rounded-full bg-[#2E5D73] px-4 py-2 text-sm text-white hover:opacity-95">WhatsApp</a>` : ''}
                    ${item?.perfil_url ? `<a href="${escapeHtml(item.perfil_url)}" target="_blank" rel="noreferrer" class="rounded-full bg-sky-50 px-4 py-2 text-sm text-sky-700 hover:bg-sky-100">Perfil</a>` : ''}
                  </div>
                </div>
              </article>
            `;
        }

        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    async function load() {
        const res = await fetch(API, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        const json = await res.json().catch(() => null);
        if (!json || !json.ok) throw new Error(json?.error || 'No se pudo cargar servicios.');
        services = json.data?.items || [];
        els.list.innerHTML = services.length ? services.map((item, index) => {
            const waDigits = String(item.whatsapp || '').replace(/\D+/g, '');
            return `
              <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex flex-col gap-3 xl:grid xl:grid-cols-[1.1fr_0.8fr_1.5fr_auto] xl:items-center xl:gap-4">
                  <div class="min-w-0">
                    <div class="truncate text-base font-semibold text-slate-800">${escapeHtml(item.nombre || 'Servicio')}</div>
                    <div class="mt-1 text-xs uppercase tracking-wide text-slate-400">${escapeHtml(item.categoria || 'servicio')}</div>
                  </div>
                  <div class="flex flex-wrap gap-2 text-xs">
                    <span class="inline-flex rounded-full px-2.5 py-1 ${item.origen === 'global' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                      ${item.origen === 'global' ? 'Global' : 'Residencial'}
                    </span>
                  </div>
                  <div class="text-sm text-slate-600">${escapeHtml(shortText(item.descripcion, 110))}</div>
                  <div class="flex flex-wrap items-center gap-2 xl:justify-end">
                    ${item.telefono ? `<a href="tel:${escapeHtml(item.telefono)}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Llamar</a>` : ''}
                    ${waDigits ? `<a href="https://wa.me/52${escapeHtml(waDigits)}" target="_blank" rel="noreferrer" class="rounded-full bg-[#2E5D73] px-3 py-2 text-xs text-white hover:opacity-95">WhatsApp</a>` : ''}
                    ${item.perfil_url ? `<a href="${escapeHtml(item.perfil_url)}" target="_blank" rel="noreferrer" class="rounded-full bg-sky-50 px-3 py-2 text-xs text-sky-700 hover:bg-sky-100">Perfil</a>` : ''}
                    <button type="button" class="js-open-service-detail rounded-full border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50" data-index="${index}">
                      Ver más
                    </button>
                  </div>
                </div>
              </article>
            `;
        }).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">No hay servicios disponibles por el momento.</div>`;

        els.list.onclick = (event) => {
            const btn = event.target.closest('.js-open-service-detail');
            if (!btn) return;
            const index = Number(btn.dataset.index || -1);
            if (!Number.isInteger(index) || index < 0 || index >= services.length) return;
            openDetailModal(services[index]);
        };
    }

    load().catch((err) => showError(err.message || 'No se pudo cargar servicios.'));
})();
