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

    function extractText(text, max = 96) {
        const clean = String(text || '').replace(/\s+/g, ' ').trim();
        if (!clean) return 'Sin mensaje';
        return clean.length > max ? `${clean.slice(0, max).trim()}…` : clean;
    }

    function ensureImageModal() {
        let modal = document.getElementById('residentComunicadoImageModal');
        if (modal) return modal;

        modal = document.createElement('div');
        modal.id = 'residentComunicadoImageModal';
        modal.className = 'hidden fixed inset-0 z-[999] bg-black/60 backdrop-blur-sm';
        modal.innerHTML = `
          <div class="flex min-h-dvh w-full items-center justify-center p-4 pointer-events-none">
            <div class="flex max-h-[min(86dvh,640px)] w-full max-w-md flex-col overflow-hidden rounded-[1.35rem] bg-white shadow-2xl sm:max-w-2xl pointer-events-auto">
              <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-3">
                <div class="min-w-0">
                  <div id="residentComunicadoImageTitle" class="text-base font-semibold leading-tight text-slate-900">Imagen adjunta</div>
                  <p class="mt-1 text-xs leading-5 text-slate-500">Vista previa del comunicado.</p>
                </div>
                <button type="button" id="residentComunicadoImageClose" class="h-11 w-11 shrink-0 rounded-full bg-slate-100 text-slate-700 transition hover:bg-slate-200" aria-label="Cerrar imagen">×</button>
              </div>
              <div class="flex-1 overflow-y-auto overscroll-contain bg-slate-950/95 p-3">
                <img id="residentComunicadoImagePreview" src="" alt="Imagen adjunta" class="mx-auto max-h-[min(62dvh,34rem)] w-full rounded-2xl object-contain" />
              </div>
            </div>
          </div>
        `;
        document.body.appendChild(modal);

        const close = () => {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        };

        modal.addEventListener('click', (event) => {
            if (event.target === modal || event.target.closest('#residentComunicadoImageClose')) {
                close();
            }
        });

        return modal;
    }

    function openImageModal(item) {
        if (!item?.imagen_url) return;
        const modal = ensureImageModal();
        const title = modal.querySelector('#residentComunicadoImageTitle');
        const image = modal.querySelector('#residentComunicadoImagePreview');
        if (title) title.textContent = item.titulo || 'Imagen adjunta';
        if (image) {
            image.src = resolvePublicUrl(item.imagen_url);
            image.alt = item.titulo || 'Imagen adjunta';
        }
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
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
        els.list.innerHTML = items.length ? items.map((item, index) => `
          <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex flex-col gap-2 xl:grid xl:grid-cols-[1.3fr_0.75fr_0.8fr_1.6fr_auto] xl:items-center xl:gap-4">
              <div class="min-w-0">
                <div class="break-words text-base font-semibold text-slate-800">${escapeHtml(item.titulo || 'Comunicado')}</div>
              </div>
              <div class="min-w-0 break-words text-sm text-slate-500">${escapeHtml(item.tipo || 'general')}</div>
              <div class="min-w-0 break-words text-sm text-slate-500">${escapeHtml(item.fecha_publicacion || '—')}</div>
              <div class="min-w-0 break-words text-sm text-slate-600">${escapeHtml(extractText(item.mensaje, 110))}</div>
              <div class="flex items-center gap-2 xl:justify-end">
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${badge(item.prioridad)}">${escapeHtml(item.prioridad || 'baja')}</span>
                ${item.imagen_url ? `
                  <button type="button" class="js-open-comunicado-image min-h-11 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50" data-index="${index}">
                    Ver imagen adjunta
                  </button>
                ` : ''}
              </div>
            </div>
          </article>
        `).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay comunicados publicados por el momento.</div>`;

        els.list.onclick = (event) => {
            const btn = event.target.closest('.js-open-comunicado-image');
            if (!btn) return;
            const index = Number(btn.dataset.index || -1);
            if (!Number.isInteger(index) || index < 0 || index >= items.length) return;
            openImageModal(items[index]);
        };

        window.ResidenteDashboard?.markComunicadosSeen?.();
    }

    load().catch((err) => showError(err.message || 'No se pudo cargar comunicados.'));
})();
