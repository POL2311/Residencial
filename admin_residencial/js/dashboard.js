(function () {
  const els = {
    header: document.getElementById('shellHeader'),
    name: document.getElementById('residentName'),
    addr: document.getElementById('residentAddress'),
    btnEdit: document.getElementById('btnEditAddress'),
    cars: document.getElementById('carsContainer'),
    modeSelect: document.getElementById('modeOperationSelect'),
    modeBadge: document.getElementById('operationalModeBadge'),
    modeHint: document.getElementById('operationalModeHint'),

    btnReg: document.getElementById('btnReglamento'),
    body: document.getElementById('dashboardBody'),
    mount: document.getElementById('dashboardViewMount'),

    modal: document.getElementById('carModal'),
    modalBody: document.getElementById('carModalBody'),
    modalClose: document.getElementById('btnCloseCarModal'),
  };

  function basePath() {
    const p = window.location.pathname;

    // ✅ Admin residencial
    const idxAdmin = p.indexOf('/admin_residencial/');
    if (idxAdmin !== -1) return p.slice(0, idxAdmin) + '/admin_residencial/';

    // fallback (por si alguien lo corre desde residente por error)
    const idxRes = p.indexOf('/residente/');
    if (idxRes !== -1) return p.slice(0, idxRes) + '/residente/';

    // default seguro
    return '/admin_residencial/';
  }

  const BASE = basePath();
  const API = BASE + 'php/api/';
  const VIEWS = BASE + 'templates/views/';
  const ALLOWED_VIEWS = new Set([
    'home',
    'unidades',
    'residentes',
    'guardias',
    'incidencias',
    'comunicados',
    'perfil',
    'reglamento',
    'autos',
    'personal_recurrente',
    'visitantes_rapidos',
    'materiales',
    'solicitudes_pendientes',
    'bitacora_operativa',
  ]);

  let currentViewScript = null;
  const state = {
    currentView: null,
    pendingView: null,
    navToken: 0,
    isNavigating: false,
    context: null,
    operationalMode: 'residencial',
    serviceProfile: null,
    enabledViews: new Set(),
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

  function normalizeView(view) {
    const normalized = ALLOWED_VIEWS.has(view) ? view : 'home';
    if (!state.enabledViews.size) return normalized;
    return state.enabledViews.has(normalized) ? normalized : firstEnabledView();
  }

  function isOperationalMode(mode) {
    return String(mode || 'residencial').trim() !== 'residencial';
  }

  function modeLabel(mode) {
    const key = String(mode || 'residencial').trim().toLowerCase();
    const map = {
      residencial: 'Residencial',
      empresa: 'Empresa',
      obra: 'Obra',
      comercio: 'Comercio',
      servicio: 'Servicio',
    };
    return map[key] || 'Residencial';
  }

  function firstEnabledView() {
    if (state.enabledViews.has('home')) return 'home';
    const [first] = state.enabledViews;
    return first || 'home';
  }

  function buildEnabledViews(profile) {
    const allowed = Array.isArray(profile?.allowed_views) ? profile.allowed_views : [];
    return new Set(allowed.filter((view) => ALLOWED_VIEWS.has(view)));
  }

  function loadViewScript(view, token) {
    // Solo mete aquí lo que realmente tengas
    const scriptsMap = {
      perfil: BASE + 'js/perfil.js',
      autos: BASE + 'js/autos.js',
      guardias: BASE + 'js/guardias.js',
      comunicados: BASE + 'js/comunicados.js',
      reglamento: BASE + 'js/reglamento.js',

      // si luego los creas:
      unidades: BASE + 'js/unidades.js',
      residentes: BASE + 'js/residentes.js',
      incidencias: BASE + 'js/incidencias.js',
      home: BASE + 'js/home.js',
      personal_recurrente: BASE + 'js/personal_recurrente.js',
      visitantes_rapidos: BASE + 'js/visitantes_rapidos.js',
      materiales: BASE + 'js/materiales.js',
      solicitudes_pendientes: BASE + 'js/solicitudes_pendientes.js',
      bitacora_operativa: BASE + 'js/bitacora_operativa.js',
    };

    if (currentViewScript) {
      currentViewScript.remove();
      currentViewScript = null;
    }

    if (!scriptsMap[view]) return Promise.resolve(true);

    const s = document.createElement('script');
    s.src = `${scriptsMap[view]}?v=${Date.now()}`;
    s.defer = true;
    s.dataset.viewScript = view;
    return new Promise((resolve) => {
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

  function scrollToViewTop() {
    const wrap = els.mount || els.body;
    if (els.body && typeof els.body.scrollTo === 'function') {
      els.body.scrollTo({ top: 0, behavior: 'smooth' });
    }
    if (wrap && typeof wrap.scrollIntoView === 'function') {
      wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }

  async function navigateTo(view, opts = {}) {
    const { force = false } = opts;
    showShellHeader();
    view = normalizeView(view || 'home');

    if (!force && (state.pendingView === view || state.currentView === view) && state.isNavigating === false) {
      syncHash(view);
      return;
    }

    const token = ++state.navToken;
    state.isNavigating = true;
    state.pendingView = view;

    const wrap = els.mount || els.body;
    if (!wrap) return;

    setActiveButtons(view);
    syncHash(view);
    scrollToViewTop();

    wrap.innerHTML = `
      <div class="rounded-2xl bg-white p-4 shadow">
        <div class="text-sm font-semibold text-slate-700">Cargando sección…</div>
        <div class="text-xs text-slate-500 mt-1">${escapeHtml(view)}</div>
      </div>
    `;

    try {
      const res = await fetch(`${VIEWS}${view}.html`, { cache: 'no-store' });
      if (token !== state.navToken) return;
      if (!res.ok) throw new Error('No se pudo cargar plantilla');
      const html = await res.text();
      if (token !== state.navToken) return;
      wrap.innerHTML = html;
    } catch (e) {
      if (token !== state.navToken) return;
      wrap.innerHTML = `
        <div class="rounded-2xl bg-white p-4 shadow">
          <div class="text-sm font-semibold text-rose-600">No se pudo cargar la sección</div>
          <div class="text-xs text-slate-600">Plantilla: ${escapeHtml(view)}.html</div>
        </div>
      `;
      state.pendingView = null;
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
      state.pendingView = null;
      state.isNavigating = false;
      return;
    }

    state.currentView = view;
    state.pendingView = null;
    state.isNavigating = false;
  }

  function initialView() {
    const h = (window.location.hash || '').replace('#', '').trim();
    return normalizeView(h || 'home');
  }

  async function loadContext() {
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
      state.context = data;
      state.operationalMode = String(data.modo_operacion || ctx.modo_operacion || 'residencial').trim() || 'residencial';
      state.serviceProfile = data.service_profile || null;
      state.enabledViews = buildEnabledViews(state.serviceProfile);

      // Nombre
      els.name.textContent = data.user?.name || 'Admin residencial';

      // Dirección / contexto
      if (data.direccion && String(data.direccion).trim() !== '') {
        els.addr.textContent = data.direccion;
      } else {
        const resName = (ctx.residencial_nombre || data.residencial_nombre || '').trim();
        const unidad = (ctx.unidad_clave || data.unidad_clave || '').trim(); // por si lo mandan
        els.addr.textContent =
          (resName && unidad)
            ? `Residencial: ${resName} · Unidad: ${unidad}`
            : (resName ? `Residencial: ${resName}` : '—');
      }

      // Autos preview (si tu API lo manda)
      const autos = Array.isArray(data.autos) ? data.autos : [];
      renderCars(autos);
      toggleOperationalButtons();
    } catch (e) {
      console.warn('loadContext() falló:', e);
      els.name.textContent = 'Admin residencial';
      els.addr.textContent = '—';
      els.cars.innerHTML = `<span class="rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs text-white/80">Sin autos registrados</span>`;
      state.context = null;
      state.operationalMode = 'residencial';
      state.serviceProfile = null;
      state.enabledViews = new Set(['home', 'perfil', 'reglamento']);
      toggleOperationalButtons();
    }
  }

  function toggleOperationalButtons() {
    const isOperational = isOperationalMode(state.operationalMode);
    document.querySelectorAll('[data-view]').forEach((el) => {
      const view = el.getAttribute('data-view') || '';
      if (!view) return;
      const allow = !state.enabledViews.size || state.enabledViews.has(view);
      if (el.classList.contains('dashBtn')) {
        el.classList.toggle('hidden', !allow);
      } else if (el.id === 'btnReglamento' || el.id === 'btnEditAddress') {
        el.classList.toggle('hidden', !allow);
      }
    });
    if (els.modeBadge) {
      const preset = String(state.serviceProfile?.preset_servicio || state.operationalMode || 'residencial');
      els.modeBadge.textContent = `Servicio ${modeLabel(preset).toLowerCase()}`;
    }
    if (els.modeHint) {
      els.modeHint.classList.toggle('hidden', isOperational || !!state.serviceProfile);
    }
    if (els.modeSelect) {
      const preset = String(state.serviceProfile?.preset_servicio || state.operationalMode || 'residencial');
      els.modeSelect.textContent = modeLabel(preset);
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

  // ✅ API GLOBAL (para vistas)
  const api = {
    loadContext,
    renderCars,
    openCarModal,
    closeCarModal,
    loadView: navigateTo,
    navigate: navigateTo,
    BASE,
    API,
    VIEWS,
    getContext: () => state.context,
    getOperationalMode: () => state.operationalMode,
    getServiceProfile: () => state.serviceProfile,
  };

  window.AdminResidencialDashboard = api;

  // ✅ compatibilidad por si tus scripts aún usan el nombre viejo
  window.ResidenteDashboard = api;

  document.addEventListener('DOMContentLoaded', () => {
    els.mount = els.mount || document.getElementById('dashboardViewMount');

    document.addEventListener('click', (e) => {
      const target = e.target.closest('[data-view]');
      if (!target) return;
      const view = target.getAttribute('data-view');
      if (!view) return;
      e.preventDefault();
      navigateTo(view);
    });

    els.modalClose?.addEventListener('click', closeCarModal);
    els.modal?.addEventListener('click', (e) => {
      if (e.target === els.modal) closeCarModal();
    });

    initShellHeader();
    loadContext().then(() => {
      navigateTo(initialView(), { force: true });
    });
  });

  window.addEventListener('hashchange', () => {
    const next = normalizeView((window.location.hash || '').replace('#', '').trim());
    const current = state.pendingView || state.currentView;
    if (next && next !== current) {
      navigateTo(next);
    }
  });
})();
