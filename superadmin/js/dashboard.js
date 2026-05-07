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
        logo: document.getElementById('systemLogoImg'),
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

    let currentViewScript = null;
    let dashboardContext = null;
    const state = {
        currentView: null,
        targetView: null,
        navToken: 0,
        isNavigating: false,
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
        if (!els.header) return;
        showShellHeader();
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
        document.querySelectorAll('.dashBtn').forEach((btn) => {
            const active = btn.dataset.view === view;
            btn.classList.toggle('ring-2', active);
            btn.classList.toggle('ring-black/20', active);
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
        const wrap = els.body?.querySelector('.max-w-6xl') || els.body;
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

        state.currentView = view;
        state.isNavigating = false;
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

            if (els.logo && data.logo_url) {
                els.logo.src = data.logo_url;
            }
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
        document.querySelectorAll('.dashBtn').forEach((btn) => {
            btn.addEventListener('click', () => navigateTo(btn.dataset.view));
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
