(function () {
    const els = {
        header: document.getElementById('shellHeader'),
        name: document.getElementById('superadminName'),
        context: document.getElementById('superadminContext'),
        meta: document.getElementById('superadminMeta'),
        statResidenciales: document.getElementById('superadminStatResidenciales'),
        statUsuarios: document.getElementById('superadminStatUsuarios'),
        statActivos: document.getElementById('superadminStatActivos'),
        body: document.getElementById('dashboardBody'),
        mount: document.getElementById('dashboardViewMount'),
        logo: document.getElementById('systemLogoImg'),
        mobileDockLayer: document.getElementById('mobileDockLayer'),
        mobileMoreBtn: document.getElementById('btnOpenMobileMoreSheet'),
        mobileMoreSheet: document.getElementById('mobileMoreSheet'),
        mobileMoreBackdrop: document.getElementById('mobileMoreBackdrop'),
        mobileMoreClose: document.getElementById('btnCloseMobileMoreSheet'),
        desktopMorePopover: document.getElementById('desktopMorePopover'),
    };

    function baseSuperadminPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/superadmin/');
        if (idx === -1) return '/superadmin/';
        return p.slice(0, idx) + '/superadmin/';
    }

    const BASE = baseSuperadminPath();
    const API = BASE + 'php/api/';
    const VIEWS = BASE + 'templates/views/';
    const inlineViews = new Set(['home', 'residenciales', 'usuarios', 'turnos_guardias', 'incidencias', 'comunicados', 'seguridad', 'reportes', 'configuracion']);
    const PRIMARY_DOCK_VIEWS = new Set(['home', 'residenciales', 'usuarios', 'reportes']);
    const SECONDARY_DOCK_VIEWS = new Set(['turnos_guardias', 'incidencias', 'comunicados', 'seguridad', 'configuracion']);

    let currentViewScript = null;
    let dashboardContext = null;
    const state = {
        currentView: null,
        targetView: null,
        navToken: 0,
        isNavigating: false,
        enabledViews: new Set(inlineViews),
        moreSheetOpen: false,
    };

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

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
        els.mobileDockLayer?.classList.toggle('dock-hidden-by-modal', hasModal);
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

    function initialView() {
        const h = (window.location.hash || '').replace('#', '').trim();
        if (!h) return 'home';
        return inlineViews.has(h) ? h : 'home';
    }

    function scriptMap() {
        return {
            home: BASE + 'js/home.js',
            residenciales: BASE + 'js/residenciales.js',
            usuarios: BASE + 'js/usuarios.js',
            turnos_guardias: BASE + 'js/turnos_guardias.js',
            incidencias: BASE + 'js/incidencias.js',
            comunicados: BASE + 'js/comunicados.js',
            seguridad: BASE + 'js/seguridad.js',
            reportes: BASE + 'js/reportes.js',
            configuracion: BASE + 'js/configuracion.js',
        };
    }

    function loadViewScript(view, token) {
        return new Promise((resolve) => {
            const map = scriptMap();
            if (currentViewScript) {
                currentViewScript.remove();
                currentViewScript = null;
            }
            if (!map[view]) {
                resolve();
                return;
            }
            const s = document.createElement('script');
            s.src = `${map[view]}?v=${Date.now()}`;
            s.dataset.viewScript = view;
            s.onload = () => resolve(token === state.navToken);
            s.onerror = () => resolve(false);
            document.body.appendChild(s);
            currentViewScript = s;
        });
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

    async function navigateTo(view, opts = {}) {
        const { force = false } = opts;
        showShellHeader();
        closeMoreSheet();
        if (!inlineViews.has(view)) view = 'home';
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
        state.currentView = view;
        state.isNavigating = false;
        setActiveButtons(view);
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
            }
        });
        els.mobileMoreBtn?.classList.toggle('hidden', secondaryVisibleCount === 0);
        els.mobileDockLayer?.classList.toggle('hidden', primaryVisibleCount === 0 && secondaryVisibleCount === 0);
    }

    async function fetchContext() {
        const res = await fetch(`${API}contexto.php`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.ok) {
            throw new Error(json?.error || 'No se pudo cargar el contexto.');
        }
        return json.data || {};
    }

    async function loadContext() {
        try {
            const data = await fetchContext();
            dashboardContext = data;
            els.name.textContent = data.user?.name || 'Super Admin';
            els.context.textContent = data.system_name || 'Sistema Residencial';
            els.meta.textContent = data.role_label || 'Control global del sistema';
            els.statResidenciales.textContent = String(data.quick_stats?.residenciales ?? 0);
            els.statUsuarios.textContent = String(data.quick_stats?.usuarios ?? 0);
            els.statActivos.textContent = String(data.quick_stats?.residenciales_activos ?? 0);
            if (els.logo && data.logo_url) els.logo.src = data.logo_url;
            applyViewVisibility();
            setActiveButtons(state.currentView || 'home');
        } catch (err) {
            console.warn('loadContext fallo:', err);
            els.name.textContent = 'Super Admin';
            els.context.textContent = 'Sistema Residencial';
            els.meta.textContent = 'No se pudo cargar el contexto.';
            els.statResidenciales.textContent = '0';
            els.statUsuarios.textContent = '0';
            els.statActivos.textContent = '0';
        }
    }

    window.SuperadminDashboard = {
        BASE,
        API,
        VIEWS,
        loadContext,
        loadView: navigateTo,
        navigate: navigateTo,
        getCurrentView: () => state.currentView,
        getContext: () => dashboardContext,
    };

    document.addEventListener('DOMContentLoaded', () => {
        initShellHeader();
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

        els.body?.addEventListener('scroll', showMobileDock, { passive: true });
        window.addEventListener('resize', () => {
            closeMoreSheet();
            showMobileDock();
            syncDockModalState();
        });

        initModalWatcher();
        loadContext();
        navigateTo(initialView(), { force: true });
    });

    window.addEventListener('hashchange', () => {
        closeMoreSheet();
        const next = (window.location.hash || '').replace('#', '').trim();
        const current = state.targetView || state.currentView;
        if (next && next !== current && inlineViews.has(next)) {
            navigateTo(next);
        }
    });
})();
