(function () {
    const els = {
        name: document.getElementById('residentName'),
        addr: document.getElementById('residentAddress'),
        btnEdit: document.getElementById('btnEditAddress'),
        cars: document.getElementById('carsContainer'),

        btnReg: document.getElementById('btnReglamento'),
        body: document.getElementById('dashboardBody'),

        modal: document.getElementById('carModal'),
        modalBody: document.getElementById('carModalBody'),
        modalClose: document.getElementById('btnCloseCarModal'),
    };

    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const BASE = baseResidentPath();
    const API = BASE + 'php/api/';
    const VIEWS = BASE + 'templates/views/';

    let currentViewScript = null;

    function loadViewScript(view) {
        const scriptsMap = {
            perfil: BASE + 'js/perfil.js',
            // luego: reglamento, comunicados, etc.
        };

        if (currentViewScript) {
            currentViewScript.remove();
            currentViewScript = null;
        }

        if (!scriptsMap[view]) return;

        const s = document.createElement('script');
        s.src = scriptsMap[view];
        s.defer = true;
        s.dataset.viewScript = view;
        document.body.appendChild(s);
        currentViewScript = s;
    }

    async function loadView(view) {
        const url = `${VIEWS}${view}.html`;
        const wrap = els.body?.querySelector('.max-w-6xl') || els.body;
        if (!wrap) return;

        try {
            const res = await fetch(url, { cache: 'no-store' });
            if (!res.ok) throw new Error('No se pudo cargar plantilla');
            wrap.innerHTML = await res.text();
        } catch {
            wrap.innerHTML = `
        <div class="rounded-2xl bg-white p-4 shadow">
          <div class="text-sm font-semibold text-rose-600">No se pudo cargar la sección</div>
          <div class="text-xs text-slate-600">Plantilla: ${escapeHtml(view)}.html</div>
        </div>
      `;
        }

        document.querySelectorAll('.dashBtn').forEach(btn => {
            const isActive = btn.dataset.view === view;
            btn.classList.toggle('ring-2', isActive);
            btn.classList.toggle('ring-black/20', isActive);
        });

        loadViewScript(view);
        window.location.hash = view;
    }

    function initialView() {
        const h = (window.location.hash || '').replace('#', '').trim();
        return h || 'home';
    }

    async function loadContext() {
        // Re-checar elementos por si el DOM cambió
        els.name = els.name || document.getElementById('residentName');
        els.addr = els.addr || document.getElementById('residentAddress');
        els.cars = els.cars || document.getElementById('carsContainer');

        if (!els.name || !els.addr || !els.cars) return;

        try {
            const res = await fetch(`${API}contexto.php`, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
            });

            const json = await res.json();
            if (!json.ok) throw new Error(json.error || 'Contexto no disponible');

            const data = json.data || {};
            const ctx = data.ctx || {};

            els.name.textContent = data.user?.name || 'Residente';

            if (data.direccion && String(data.direccion).trim() !== '') {
                els.addr.textContent = data.direccion;
            } else {
                const resName = (ctx.residencial_nombre || data.residencial_nombre || '').trim();
                const unidad = (ctx.unidad_clave || data.unidad_clave || '').trim();
                els.addr.textContent =
                    (resName && unidad)
                        ? `Residencial: ${resName} · Unidad: ${unidad}`
                        : (resName || unidad || '—');
            }

            renderCars(data.autos || []);
        } catch (e) {
            console.warn('loadContext() fallo:', e);
            els.name.textContent = 'Residente';
            els.addr.textContent = '—';
            els.cars.innerHTML = `<span class="text-xs opacity-90">Sin autos</span>`;
        }
    }



    function openCarModal(auto) {
        els.modal = els.modal || document.getElementById('carModal');
        els.modalBody = els.modalBody || document.getElementById('carModalBody');
        if (!els.modal || !els.modalBody) return;

        els.modalBody.innerHTML = `
      <div class="rounded-xl bg-slate-50 px-3 py-2">
        <div class="text-xs text-slate-500">Placas</div>
        <div class="font-semibold text-slate-900">${escapeHtml(auto.placas || '—')}</div>
      </div>
      <div class="rounded-xl bg-slate-50 px-3 py-2">
        <div class="text-xs text-slate-500">Modelo</div>
        <div class="text-slate-900">${escapeHtml(auto.modelo || '—')}</div>
      </div>
      <div class="rounded-xl bg-slate-50 px-3 py-2">
        <div class="text-xs text-slate-500">Color</div>
        <div class="text-slate-900">${escapeHtml(auto.color || '—')}</div>
      </div>
    `;
        els.modal.classList.remove('hidden');
    }

    function closeCarModal() {
        els.modal = els.modal || document.getElementById('carModal');
        if (!els.modal) return;
        els.modal.classList.add('hidden');
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    // ✅ EXPONER API GLOBAL PARA OTRAS VISTAS (perfil.js)
    window.ResidenteDashboard = {
        loadContext,
        renderCars,
        openCarModal,
        closeCarModal,
        loadView,
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dashBtn').forEach(btn => {
            btn.addEventListener('click', () => loadView(btn.dataset.view));
        });

        els.btnReg?.addEventListener('click', () => loadView('reglamento'));
        els.btnEdit?.addEventListener('click', () => loadView('perfil'));

        els.modalClose?.addEventListener('click', closeCarModal);
        els.modal?.addEventListener('click', (e) => {
            if (e.target === els.modal) closeCarModal();
        });

        loadContext();
        loadView(initialView());
    });
})();