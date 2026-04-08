(function () {
    const els = {
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
    const inlineViews = new Set(['home', 'residenciales', 'usuarios', 'seguridad', 'reportes', 'configuracion']);

    let currentViewScript = null;
    let currentViewName = null;
    let dashboardContext = null;

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
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
            seguridad: BASE + 'js/seguridad.js',
            reportes: BASE + 'js/reportes.js',
            configuracion: BASE + 'js/configuracion.js',
        };
    }

    function loadViewScript(view) {
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
            s.onload = () => resolve();
            s.onerror = () => resolve();
            document.body.appendChild(s);
            currentViewScript = s;
        });
    }

    async function loadView(view) {
        if (!inlineViews.has(view)) {
            view = 'home';
        }

        currentViewName = view;
        const wrap = els.body?.querySelector('.max-w-6xl') || els.body;
        if (!wrap) return;

        try {
            const res = await fetch(`${VIEWS}${view}.html`, { cache: 'no-store' });
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

        document.querySelectorAll('.dashBtn').forEach((btn) => {
            const active = btn.dataset.view === view;
            btn.classList.toggle('ring-2', active);
            btn.classList.toggle('ring-black/20', active);
        });

        await loadViewScript(view);
        window.location.hash = view;
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
        loadView,
        navigate: loadView,
        getCurrentView: () => currentViewName,
        getContext: () => dashboardContext,
    };

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dashBtn').forEach((btn) => {
            btn.addEventListener('click', () => loadView(btn.dataset.view));
        });

        loadContext();
        loadView(initialView());
    });

    window.addEventListener('hashchange', () => {
        const next = (window.location.hash || '').replace('#', '').trim();
        if (next && next !== currentViewName && inlineViews.has(next)) {
            loadView(next);
        }
    });
})();
