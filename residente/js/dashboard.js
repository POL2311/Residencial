(function () {
    const els = {
        header: document.getElementById('shellHeader'),
        name: document.getElementById('residentName'),
        addr: document.getElementById('residentAddress'),
        btnEdit: document.getElementById('btnEditAddress'),
        cars: document.getElementById('carsContainer'),
        notificationsButton: document.getElementById('residentNotificationsButton'),
        notificationsBadge: document.getElementById('residentNotificationsBadge'),
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
    const inlineViews = new Set(['home', 'visitas', 'incidencias', 'paqueteria', 'autos', 'pagos', 'comunicados', 'servicios', 'reglamento', 'perfil']);

    let currentViewScript = null;
    let currentNotificationMeta = null;
    let currentNotificationUserId = 0;
    let currentNotificationResidencialId = 0;
    const state = {
        currentView: null,
        targetView: null,
        navToken: 0,
        isNavigating: false,
    };
    function showShellHeader() {
        if (!els.header) return;
        els.header.style.marginTop = '0px';
        els.header.style.opacity = '1';
        els.header.style.transform = 'translateY(0)';
    }

    function initShellHeader() {
        if (!els.header) return;
        showShellHeader();
    }

    function loadViewScript(view, token) {
        return new Promise((resolve) => {
        const scriptsMap = {
            home: BASE + 'js/home.js',
            visitas: BASE + 'js/visitas.js',
            incidencias: BASE + 'js/incidencias.js',
            perfil: BASE + 'js/perfil.js',
            paqueteria: BASE + 'js/paqueteria.js',
            autos: BASE + 'js/autos.js',
            pagos: BASE + 'js/pagos.js',
            comunicados: BASE + 'js/comunicados.js',
            servicios: BASE + 'js/servicios.js',
            reglamento: BASE + 'js/reglamento.js',
        };

        if (currentViewScript) {
            currentViewScript.remove();
            currentViewScript = null;
        }

        if (!scriptsMap[view]) {
            resolve();
            return;
        }

        const s = document.createElement('script');
        s.src = `${scriptsMap[view]}?v=${Date.now()}`;
        s.dataset.viewScript = view;
        s.onload = () => resolve(token === state.navToken);
        s.onerror = () => resolve(false);
        document.body.appendChild(s);
        currentViewScript = s;
        });
    }

    function setActiveButtons(view) {
        document.querySelectorAll('.dashBtn').forEach(btn => {
            const isActive = btn.dataset.view === view;
            btn.classList.toggle('ring-2', isActive);
            btn.classList.toggle('ring-black/20', isActive);
        });
    }

    function syncHash(view) {
        if ((window.location.hash || '').replace('#', '') !== view) {
            window.location.hash = view;
        }
    }

    async function navigateTo(view, opts = {}) {
        const { force = false } = opts;
        showShellHeader();
        if (!inlineViews.has(view)) {
            view = 'home';
        }
        if (!force && (state.targetView === view || state.currentView === view) && state.isNavigating === false) {
            syncHash(view);
            return;
        }

        const token = ++state.navToken;
        state.isNavigating = true;
        state.targetView = view;

        const url = `${VIEWS}${view}.html`;
        const wrap = els.body?.querySelector('.max-w-6xl') || els.body;
        if (!wrap) return;
        setActiveButtons(view);
        syncHash(view);

        try {
            const res = await fetch(url, { cache: 'no-store' });
            if (token !== state.navToken) return;
            if (!res.ok) throw new Error('No se pudo cargar plantilla');
            const html = await res.text();
            if (token !== state.navToken) return;
            wrap.innerHTML = html;
        } catch {
            if (token !== state.navToken) return;
            wrap.innerHTML = `
        <div class="rounded-2xl bg-white p-4 shadow">
          <div class="text-sm font-semibold text-rose-600">No se pudo cargar la sección</div>
          <div class="text-xs text-slate-600">Plantilla: ${escapeHtml(view)}.html</div>
        </div>
      `;
            state.isNavigating = false;
            return;
        }

        const scriptOk = await loadViewScript(view, token);
        if (token !== state.navToken) return;

        if (!scriptOk && currentViewScript) {
            wrap.innerHTML = `
        <div class="rounded-2xl bg-white p-4 shadow">
          <div class="text-sm font-semibold text-rose-600">No se pudo cargar la sección</div>
          <div class="text-xs text-slate-600">Script: ${escapeHtml(view)}.js</div>
        </div>
      `;
        }

        if (view === 'comunicados') {
            markNotificationsSeen();
        }
        state.currentView = view;
        state.isNavigating = false;
    }

    function initialView() {
        const h = (window.location.hash || '').replace('#', '').trim();
        if (!h) return 'home';
        return inlineViews.has(h) ? h : 'home';
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
            currentNotificationMeta = data.notifications || null;
            currentNotificationUserId = Number(data.user?.id || 0);
            currentNotificationResidencialId = Number(ctx.residencial_id || 0);

            els.name.textContent = data.user?.name || 'Residente';

            if (data.setup_incomplete) {
                els.addr.textContent = data.setup_message || 'Configuración pendiente';
                renderCars([]);
                return;
            }

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
            updateNotificationsUI();
        } catch (e) {
            console.warn('loadContext() fallo:', e);
            els.name.textContent = 'Residente';
            els.addr.textContent = '—';
            els.cars.innerHTML = `<span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs text-white/80">Sin autos registrados</span>`;
            currentNotificationMeta = null;
            updateNotificationsUI();
        }
    }

    function notificationsStorageKey() {
        return `resident-notifications-seen:${currentNotificationUserId || 0}:${currentNotificationResidencialId || 0}`;
    }

    function currentNotificationMarker() {
        return String(currentNotificationMeta?.latest_updated_at || currentNotificationMeta?.latest_id || '');
    }

    function markNotificationsSeen() {
        const marker = currentNotificationMarker();
        if (!marker) return;
        try {
            window.localStorage.setItem(notificationsStorageKey(), marker);
        } catch (_) {
            // ignore storage issues
        }
        updateNotificationsUI();
    }

    function updateNotificationsUI() {
        if (!els.notificationsBadge) return;
        const total = Number(currentNotificationMeta?.total || 0);
        const marker = currentNotificationMarker();
        let seenMarker = '';
        try {
            seenMarker = window.localStorage.getItem(notificationsStorageKey()) || '';
        } catch (_) {
            seenMarker = '';
        }
        const unread = total > 0 && marker !== '' && marker !== seenMarker;
        els.notificationsBadge.classList.toggle('hidden', !unread);
        if (els.notificationsButton) {
            els.notificationsButton.setAttribute('aria-label', unread ? 'Hay comunicados nuevos' : 'Comunicados');
            els.notificationsButton.title = unread ? 'Hay comunicados nuevos' : 'Comunicados';
            els.notificationsButton.classList.toggle('ring-2', unread);
            els.notificationsButton.classList.toggle('ring-white/40', unread);
        }
    }

    function renderCars(autos) {
        if (!els.cars) return;

        if (!autos || autos.length === 0) {
            els.cars.innerHTML = `<span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs text-white/80">Sin autos registrados</span>`;
            return;
        }

        els.cars.innerHTML = '';
        autos.slice(0, 6).forEach((a, idx) => {
            const btn = document.createElement('button');
            btn.type = 'button';

            const colorClass =
                (idx % 3 === 1) ? 'text-red-400'
                    : (idx % 3 === 2) ? 'text-yellow-300'
                        : 'text-white';

            btn.className = `inline-flex h-9 min-w-[2.25rem] items-center justify-center rounded-full border border-white/10 bg-white/10 px-2 ${colorClass} opacity-95 transition-all duration-200 hover:-translate-y-0.5 hover:bg-white/20 hover:opacity-100 hover:shadow-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/35`;
            btn.title = a.placas || 'Auto';

            btn.innerHTML = `
        <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none">
          <path d="M7 16l-1 3m11-3l1 3M5 16h14l-1.5-6.5A2 2 0 0 0 15.55 8H8.45a2 2 0 0 0-1.95 1.5L5 16Z"
                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M7.5 16.5h.01M16.5 16.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
      `;

            btn.addEventListener('click', () => openCarModal(a));
            els.cars.appendChild(btn);
        });
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
        showShellHeader();
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

    // ✅ EXPONER API GLOBAL PARA OTRAS VISTAS (perfil.js / paqueteria.js)
    window.ResidenteDashboard = {
        loadContext,
        renderCars,
        openCarModal,
        closeCarModal,
        loadView: navigateTo,
        navigate: navigateTo,
        getCurrentView: () => state.currentView,
        markComunicadosSeen: markNotificationsSeen,
    };

    document.addEventListener('DOMContentLoaded', () => {
        initShellHeader();
        document.addEventListener('click', (e) => {
            const target = e.target.closest('[data-view]');
            if (!target) return;

            const view = (target.getAttribute('data-view') || '').trim();
            if (!view || !inlineViews.has(view)) return;

            e.preventDefault();
            navigateTo(view);
        });

        els.btnEdit?.setAttribute('data-view', 'perfil');
        els.notificationsButton?.setAttribute('data-view', 'comunicados');

        els.modalClose?.addEventListener('click', closeCarModal);
        els.modal?.addEventListener('click', (e) => {
            if (e.target === els.modal) closeCarModal();
        });

        loadContext();
        navigateTo(initialView(), { force: true });
    });

    window.addEventListener('hashchange', () => {
        const next = (window.location.hash || '').replace('#', '').trim();
        const current = state.targetView || state.currentView;
        if (next && next !== current && inlineViews.has(next)) {
            navigateTo(next);
        }
    });
})();
