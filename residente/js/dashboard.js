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
        mount: document.getElementById('dashboardViewMount'),
        mobileDockLayer: document.getElementById('mobileDockLayer'),
        mobileMoreBtn: document.getElementById('btnOpenMobileMoreSheet'),
        mobileMoreSheet: document.getElementById('mobileMoreSheet'),
        mobileMoreBackdrop: document.getElementById('mobileMoreBackdrop'),
        mobileMoreClose: document.getElementById('btnCloseMobileMoreSheet'),
        desktopMorePopover: document.getElementById('desktopMorePopover'),
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

    function appRootBase() {
        const p = window.location.pathname || '';
        const idx = p.indexOf('/residente/');
        if (idx !== -1) return p.slice(0, idx);
        const parts = p.split('/').filter(Boolean);
        return parts.length ? '/' + parts[0] : '';
    }

    const BASE = baseResidentPath();
    const API = BASE + 'php/api/';
    const VIEWS = BASE + 'templates/views/';
    const inlineViews = new Set(['home', 'visitas', 'incidencias', 'perfil', 'paqueteria', 'autos', 'pagos', 'comunicados', 'servicios', 'reglamento']);
    const PRIMARY_DOCK_VIEWS = new Set(['home', 'visitas', 'incidencias', 'comunicados']);
    const SECONDARY_DOCK_VIEWS = new Set(['paqueteria', 'autos', 'pagos', 'servicios', 'perfil', 'reglamento']);

    let currentViewScript = null;
    let currentNotificationMeta = null;
    let currentNotificationUserId = 0;
    let currentNotificationResidencialId = 0;

    const state = {
        currentView: null,
        targetView: null,
        navToken: 0,
        isNavigating: false,
        serviceProfile: null,
        enabledViews: new Set(),
        moreSheetOpen: false,
    };

    function showShellHeader() {
        if (!els.header) return;
        els.header.style.marginTop = '0px';
        els.header.style.opacity = '1';
        els.header.style.transform = 'translateY(0)';
    }

    function initShellHeader() {
        showShellHeader();
    }

    function isLargeMoreViewport() {
        return window.matchMedia('(min-width: 750px)').matches;
    }

    function showMobileDock() {
        els.mobileDockLayer?.classList.remove('mobile-dock-hidden');
    }

    function isManagedOverlay(el) {
        return !!el && (
            el === els.mobileDockLayer ||
            el === els.mobileMoreBackdrop ||
            el === els.mobileMoreSheet ||
            el === els.desktopMorePopover ||
            el === document.getElementById('appToastRoot') ||
            !!els.mobileDockLayer?.contains(el)
        );
    }

    function hasActiveModal() {
        return Array.from(document.body.querySelectorAll('*')).some((el) => {
            if (!(el instanceof HTMLElement) || isManagedOverlay(el)) return false;
            if (el.classList.contains('hidden') || el.getAttribute('aria-hidden') === 'true') return false;
            const style = window.getComputedStyle(el);
            if (style.display === 'none' || style.visibility === 'hidden' || style.position !== 'fixed') return false;
            const zIndex = Number.parseInt(style.zIndex || '0', 10);
            if (!Number.isFinite(zIndex) || zIndex < 50) return false;
            const rect = el.getBoundingClientRect();
            return rect.width >= 80 && rect.height >= 80;
        });
    }

    function syncDockModalState() {
        const hasModal = hasActiveModal();
        document.body.classList.toggle('dashboard-modal-open', hasModal);
        els.mobileDockLayer?.classList.toggle('dock-hidden-by-modal', hasModal);
        if (els.mobileDockLayer) {
            els.mobileDockLayer.style.display = hasModal ? 'none' : '';
            els.mobileDockLayer.setAttribute('aria-hidden', hasModal ? 'true' : 'false');
        }
        if (els.footer) {
            els.footer.style.pointerEvents = hasModal ? 'none' : '';
        }
        if (hasModal) {
            closeMoreSheet();
        } else if (!state.moreSheetOpen) {
            showMobileDock();
        }
    }

    function initModalWatcher() {
        if (!document.body) return;
        const observer = new MutationObserver(() => {
            window.requestAnimationFrame(syncDockModalState);
        });
        observer.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['class', 'style', 'hidden', 'aria-hidden'],
        });
        syncDockModalState();
    }

    function positionDesktopMorePopover() {
        if (!els.mobileMoreBtn || !els.desktopMorePopover || !isLargeMoreViewport() || !state.moreSheetOpen) return;
        const margin = 16;
        const btnRect = els.mobileMoreBtn.getBoundingClientRect();
        const desiredWidth = Math.min(window.innerWidth - (margin * 2), 352);
        els.desktopMorePopover.style.width = `${desiredWidth}px`;
        els.desktopMorePopover.style.maxWidth = `${window.innerWidth - (margin * 2)}px`;
        const popoverHeight = Math.min(els.desktopMorePopover.scrollHeight || 0, window.innerHeight - (margin * 2));
        const left = Math.min(Math.max(margin, btnRect.right - desiredWidth), window.innerWidth - desiredWidth - margin);
        const top = Math.max(margin, btnRect.top - popoverHeight - 12);
        els.desktopMorePopover.style.left = `${left}px`;
        els.desktopMorePopover.style.top = `${top}px`;
    }

    function openMoreSheet() {
        state.moreSheetOpen = true;
        showMobileDock();
        els.mobileMoreBackdrop?.setAttribute('aria-hidden', 'false');
        els.mobileMoreSheet?.setAttribute('aria-hidden', 'false');
        els.mobileMoreBackdrop?.classList.add('is-open');
        els.mobileMoreSheet?.classList.add('is-open');
        document.body.classList.add('overflow-hidden');
        setActiveButtons(state.currentView || 'home');
    }

    function closeMoreSheet() {
        state.moreSheetOpen = false;
        document.body.classList.remove('overflow-hidden');
        els.mobileMoreBackdrop?.classList.remove('is-open');
        els.mobileMoreSheet?.classList.remove('is-open');
        els.mobileMoreBackdrop?.setAttribute('aria-hidden', 'true');
        els.mobileMoreSheet?.setAttribute('aria-hidden', 'true');
        if (els.desktopMorePopover) {
            els.desktopMorePopover.classList.add('hidden');
            els.desktopMorePopover.setAttribute('aria-hidden', 'true');
            els.desktopMorePopover.style.left = '';
            els.desktopMorePopover.style.top = '';
            els.desktopMorePopover.style.width = '';
            els.desktopMorePopover.style.maxWidth = '';
        }
        setActiveButtons(state.currentView || 'home');
    }

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function resolvePublicUrl(url) {
        const u = String(url || '').trim();
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
            } catch (_) {}
            return u;
        }
        if (u.startsWith('/assets/')) return appRootBase() + u;
        return u;
    }

    function setActiveButtons(view) {
        document.querySelectorAll('[data-dock-primary-view]').forEach((btn) => {
            const active = btn.getAttribute('data-dock-primary-view') === view;
            btn.classList.toggle('mobile-dock-item-active', active);
        });
        if (els.mobileMoreBtn) {
            const moreActive = SECONDARY_DOCK_VIEWS.has(view) && !PRIMARY_DOCK_VIEWS.has(view);
            els.mobileMoreBtn.classList.toggle('mobile-dock-item-active', moreActive || state.moreSheetOpen);
        }
    }

    function syncHash(view) {
        if ((window.location.hash || '').replace('#', '') !== view) {
            window.location.hash = view;
        }
    }

    function renderAccessBlocked(message = '') {
        const wrap = els.mount || els.body;
        if (!wrap) return;
        wrap.innerHTML = `
          <div class="rounded-[1.75rem] border border-amber-200 bg-white p-5 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-500">Residente</div>
            <h2 class="mt-2 text-2xl font-semibold text-slate-900">Acceso no disponible</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">${escapeHtml(message || 'Tu cuenta está asignada, pero este servicio ya no tiene módulos compatibles para el portal de residente.')}</p>
            <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
              Pide a la administración o a Superadmin que habiliten módulos reales para el rol Residente.
            </div>
          </div>
        `;
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

    async function navigateTo(view, opts = {}) {
        const { force = false } = opts;
        showShellHeader();
        closeMoreSheet();

        if (!state.enabledViews.size) {
            renderAccessBlocked();
            return;
        }

        if (!inlineViews.has(view)) view = firstEnabledView();
        if (!state.enabledViews.has(view)) view = firstEnabledView();

        if (!force && (state.targetView === view || state.currentView === view) && state.isNavigating === false) {
            syncHash(view);
            return;
        }

        const token = ++state.navToken;
        state.isNavigating = true;
        state.targetView = view;

        const wrap = els.mount || els.body;
        if (!wrap) return;
        setActiveButtons(view);
        syncHash(view);
        wrap.innerHTML = `
          <div class="rounded-2xl bg-white p-4 shadow">
            <div class="text-sm font-semibold text-slate-700">Cargando sección…</div>
            <div class="mt-1 text-xs text-slate-500">${escapeHtml(view)}</div>
          </div>
        `;

        try {
            const res = await fetch(`${VIEWS}${view}.html`, { cache: 'no-store' });
            if (token !== state.navToken) return;
            if (!res.ok) throw new Error('No se pudo cargar plantilla');
            wrap.innerHTML = await res.text();
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

        if (view === 'comunicados') markNotificationsSeen();
        state.currentView = view;
        state.isNavigating = false;
        setActiveButtons(view);
    }

    function initialView() {
        const h = (window.location.hash || '').replace('#', '').trim();
        if (!state.enabledViews.size) return '';
        if (!h) return firstEnabledView();
        if (!inlineViews.has(h)) return firstEnabledView();
        return !state.enabledViews.has(h) ? firstEnabledView() : h;
    }

    function firstEnabledView() {
        if (!state.enabledViews.size) return '';
        if (state.enabledViews.has('home')) return 'home';
        const [first] = state.enabledViews;
        return first || '';
    }

    function applyViewVisibility() {
        let primaryVisibleCount = 0;
        let secondaryVisibleCount = 0;

        document.querySelectorAll('[data-view]').forEach((el) => {
            const view = (el.getAttribute('data-view') || '').trim();
            if (!view) return;
            const allow = state.enabledViews.has(view);
            if (el.hasAttribute('data-dock-primary-view')) {
                el.classList.toggle('hidden', !allow);
                if (allow) primaryVisibleCount += 1;
            } else if (el.hasAttribute('data-dock-secondary')) {
                el.classList.toggle('hidden', !allow);
                if (allow) secondaryVisibleCount += 1;
            } else if (el.hasAttribute('data-dock-desktop-secondary')) {
                el.classList.toggle('hidden', !allow);
            } else if (el.id === 'btnEditAddress' || el.id === 'residentNotificationsButton') {
                el.classList.toggle('hidden', !allow);
            }
        });

        if (els.mobileMoreBtn) {
            els.mobileMoreBtn.classList.toggle('hidden', secondaryVisibleCount === 0);
        }
        if (els.mobileDockLayer) {
            els.mobileDockLayer.classList.toggle('hidden', primaryVisibleCount === 0 && secondaryVisibleCount === 0);
        }
    }

    async function loadContext() {
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
            state.serviceProfile = data.service_profile || null;
            state.enabledViews = new Set(Array.isArray(data.service_profile?.allowed_views) ? data.service_profile.allowed_views : ['home', 'perfil', 'reglamento']);
            currentNotificationMeta = data.notifications || null;
            currentNotificationUserId = Number(data.user?.id || 0);
            currentNotificationResidencialId = Number(ctx.residencial_id || 0);

            if (els.name) els.name.textContent = data.user?.name || 'Residente';
            if (data.setup_incomplete) {
                els.addr.textContent = data.setup_message || 'Configuración pendiente';
                renderCars([]);
                updateNotificationsUI();
                applyViewVisibility();
                setActiveButtons(state.currentView || 'home');
                return;
            }

            const resName = (ctx.residencial_nombre || data.residencial_nombre || '').trim();
            const unidad = (ctx.unidad_clave || data.unidad_clave || '').trim();
            if (resName || unidad) {
                els.addr.textContent = [resName ? `Residencial: ${resName}` : '', unidad ? `Unidad: ${unidad}` : ''].filter(Boolean).join(' · ');
            } else {
                els.addr.textContent = String(data.direccion || '').trim() || '—';
            }

            renderCars(data.autos || []);
            updateNotificationsUI();
            applyViewVisibility();
            setActiveButtons(state.currentView || 'home');
        } catch (e) {
            console.warn('loadContext() fallo:', e);
            if (els.name) els.name.textContent = 'Residente';
            els.addr.textContent = '—';
            els.cars.innerHTML = `<span class="rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-xs text-slate-500">Sin autos registrados</span>`;
            currentNotificationMeta = null;
            state.serviceProfile = null;
            state.enabledViews = new Set();
            updateNotificationsUI();
            applyViewVisibility();
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
        } catch (_) {}
        updateNotificationsUI();
    }

    function updateNotificationsUI() {
        if (!els.notificationsBadge) return;
        const total = Number(currentNotificationMeta?.total || 0);
        const marker = currentNotificationMarker();
        let seenMarker = '';
        try {
            seenMarker = window.localStorage.getItem(notificationsStorageKey()) || '';
        } catch (_) {}
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
            els.cars.innerHTML = `<span class="rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-xs text-slate-500">Sin autos registrados</span>`;
            return;
        }
        els.cars.innerHTML = '';
        autos.slice(0, 6).forEach((a) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'inline-flex h-10 min-w-[2.5rem] items-center justify-center rounded-full border border-slate-200 bg-white/80 px-2 text-[#436C81] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E5D73]/20';
            btn.title = a.placas || 'Auto';
            btn.innerHTML = `
              <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none">
                <path d="M7 16l-1 3m11-3l1 3M5 16h14l-1.5-6.5A2 2 0 0 0 15.55 8H8.45a2 2 0 0 0-1.95 1.5L5 16Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M7.5 16.5h.01M16.5 16.5h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
              </svg>
            `;
            btn.addEventListener('click', () => openCarModal(a));
            els.cars.appendChild(btn);
        });
    }

    function openCarModal(auto) {
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
        els.modal?.classList.add('hidden');
    }

    window.ResidenteDashboard = {
        loadContext,
        renderCars,
        openCarModal,
        closeCarModal,
        loadView: navigateTo,
        navigate: navigateTo,
        getCurrentView: () => state.currentView,
        markComunicadosSeen: markNotificationsSeen,
        getServiceProfile: () => state.serviceProfile,
    };

    document.addEventListener('DOMContentLoaded', () => {
        initShellHeader();
        els.btnEdit?.setAttribute('data-view', 'perfil');
        els.notificationsButton?.setAttribute('data-view', 'comunicados');

        document.addEventListener('click', (e) => {
            if (e.target.closest('#btnOpenMobileMoreSheet')) {
                e.preventDefault();
                if (state.moreSheetOpen) closeMoreSheet();
                else openMoreSheet();
                return;
            }

            if (e.target === els.mobileMoreBackdrop || e.target.closest('#btnCloseMobileMoreSheet')) {
                e.preventDefault();
                closeMoreSheet();
                return;
            }

            const target = e.target.closest('[data-view]');
            if (!target) return;
            const view = (target.getAttribute('data-view') || '').trim();
            if (!view || !inlineViews.has(view)) return;
            e.preventDefault();
            closeMoreSheet();
            navigateTo(view);
        });

        els.modalClose?.addEventListener('click', closeCarModal);
        els.modal?.addEventListener('click', (e) => {
            if (e.target === els.modal) closeCarModal();
        });
        els.body?.addEventListener('scroll', showMobileDock, { passive: true });
        window.addEventListener('resize', () => {
            closeMoreSheet();
            showMobileDock();
            syncDockModalState();
        });

        initModalWatcher();
        loadContext().then(() => {
            if (!state.enabledViews.size) {
                renderAccessBlocked();
                return;
            }
            navigateTo(initialView(), { force: true });
        });
    });

    window.addEventListener('hashchange', () => {
        closeMoreSheet();
        if (!state.enabledViews.size) return;
        const next = (window.location.hash || '').replace('#', '').trim();
        const current = state.targetView || state.currentView;
        if (next && next !== current && inlineViews.has(next)) {
            navigateTo(next);
        }
    });
})();
