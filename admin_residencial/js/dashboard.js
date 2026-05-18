(function () {
  const els = {
    header: document.getElementById('shellHeader'),
    name: document.getElementById('residentName'),
    addr: document.getElementById('residentAddress'),
    btnEdit: document.getElementById('btnEditAddress'),
    modeBadge: document.getElementById('operationalModeBadge'),
    modeHint: document.getElementById('operationalModeHint'),

    btnReg: document.getElementById('btnReglamento'),
    footer: document.getElementById('shellFooter'),
    body: document.getElementById('dashboardBody'),
    mount: document.getElementById('dashboardViewMount'),
    mobileDockLayer: document.getElementById('mobileDockLayer'),
    mobileFloatingDock: document.getElementById('mobileFloatingDock'),
    mobileMoreBtn: document.getElementById('btnOpenMobileMoreSheet'),
    mobileMoreSheet: document.getElementById('mobileMoreSheet'),
    mobileMoreBackdrop: document.getElementById('mobileMoreBackdrop'),
    mobileMoreClose: document.getElementById('btnCloseMobileMoreSheet'),
    desktopMorePopover: document.getElementById('desktopMorePopover'),

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
    contextLoad: 'idle', // 'idle' | 'ok' | 'error'
    homeMinimalNotice: '',
    moreSheetOpen: false,
  };

  const PRIMARY_DOCK_VIEWS = new Set(['home', 'residentes', 'incidencias', 'comunicados']);
  const SECONDARY_DOCK_VIEWS = new Set([
    'unidades',
    'guardias',
    'autos',
    'personal_recurrente',
    'visitantes_rapidos',
    'materiales',
    'solicitudes_pendientes',
    'bitacora_operativa',
    'perfil',
    'reglamento',
  ]);
  let lastBodyScrollTop = 0;

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
    if (!state.enabledViews.size) return firstEnabledView();
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
    if (!state.enabledViews.size) return '';
    if (state.enabledViews.has('home')) return 'home';
    const [first] = state.enabledViews;
    return first || '';
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

  function renderAccessBlocked(message = '') {
    const wrap = els.mount || els.body;
    if (!wrap) return;

    wrap.innerHTML = `
      <div class="rounded-[1.75rem] border border-amber-200 bg-white p-5 shadow-sm">
        <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-500">Admin residencial</div>
        <h2 class="mt-2 text-2xl font-semibold text-slate-900">Acceso operativo no disponible</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">${escapeHtml(message || 'Tu cuenta está asignada, pero este servicio ya no tiene módulos compatibles para tu rol.')}</p>
        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
          Revisa el perfil del servicio en Superadmin para habilitar módulos compatibles con Admin operativo.
        </div>
      </div>
    `;
  }

  function renderHomeMinimalNotice(message) {
    const wrap = els.mount || els.body;
    if (!wrap) return;
    if (!message) return;
    const notice = `
      <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        ${escapeHtml(message)}
      </div>
    `;
    if (wrap.firstElementChild) {
      wrap.insertAdjacentHTML('afterbegin', notice);
    } else {
      wrap.innerHTML = notice + wrap.innerHTML;
    }
  }

  function minimalViewsSet() {
    return new Set(['home', 'perfil', 'reglamento']);
  }

  function debugEnabled() {
    try {
      return new URLSearchParams(window.location.search || '').get('debug') === '1';
    } catch (_) {
      return false;
    }
  }

  function debugLogContext(allowedViews, enabledViews) {
    const msg = {
      base: BASE,
      contexto_url: `${API}contexto.php`,
      contextLoad: state.contextLoad,
      allowed_views: allowedViews,
      enabledViews: Array.from(enabledViews || []),
      operationalMode: state.operationalMode,
      preset: state.serviceProfile?.preset_servicio || null,
    };
    console.warn('[admin_residencial] contexto/allowed_views', msg);
  }

  function setActiveButtons(view) {
    document.querySelectorAll('.dashBtn').forEach(btn => {
      const isActive = btn.dataset.view === view;
      btn.classList.toggle('ring-2', isActive);
      btn.classList.toggle('ring-black/20', isActive);
    });

    document.querySelectorAll('[data-dock-primary-view]').forEach((btn) => {
      const isActive = btn.getAttribute('data-dock-primary-view') === view;
      btn.classList.toggle('mobile-dock-item-active', isActive);
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

  function isMobileViewport() {
    return window.matchMedia('(max-width: 767px)').matches;
  }

  function isLargeMoreViewport() {
    return window.matchMedia('(min-width: 750px)').matches;
  }

  function showMobileDock() {
    els.mobileDockLayer?.classList.remove('mobile-dock-hidden');
  }

  function hideMobileDock() {
    if (state.moreSheetOpen) return;
    els.mobileDockLayer?.classList.add('mobile-dock-hidden');
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

    const sheetHeight = Math.min(els.desktopMorePopover.scrollHeight || 0, window.innerHeight - (margin * 2));
    const left = Math.min(
      Math.max(margin, btnRect.right - desiredWidth),
      window.innerWidth - desiredWidth - margin
    );
    const top = Math.max(margin, btnRect.top - sheetHeight - 12);

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

  function handleBodyScroll() {
    showMobileDock();
    if (els.body) {
      lastBodyScrollTop = els.body.scrollTop || 0;
    }
  }

  async function navigateTo(view, opts = {}) {
    const { force = false } = opts;
    showShellHeader();
    closeMoreSheet();
    if (!state.enabledViews.size) {
      renderAccessBlocked();
      return;
    }
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
    if (!state.enabledViews.size) return '';
    return normalizeView(h || 'home');
  }

  async function loadContext() {
    els.name = els.name || document.getElementById('residentName');
    els.addr = els.addr || document.getElementById('residentAddress');
    // Header chips ("carsContainer") were removed for a cleaner UI.
    if (!els.name || !els.addr) return;

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
      state.contextLoad = state.serviceProfile ? 'ok' : 'error';
      state.enabledViews = state.serviceProfile ? buildEnabledViews(state.serviceProfile) : new Set();
      state.homeMinimalNotice = '';

      const allowed = Array.isArray(state.serviceProfile?.allowed_views) ? state.serviceProfile.allowed_views : [];
      debugLogContext(allowed, state.enabledViews);

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

      toggleOperationalButtons();
    } catch (e) {
      console.warn('loadContext() falló:', e);
      els.name.textContent = 'Admin residencial';
      els.addr.textContent = '—';
      state.context = null;
      state.operationalMode = 'residencial';
      state.serviceProfile = null;
      state.enabledViews = new Set();
      state.contextLoad = 'error';
      state.homeMinimalNotice = '';
      debugLogContext([], state.enabledViews);
      toggleOperationalButtons();
    }
  }

  function toggleOperationalButtons() {
    const isOperational = isOperationalMode(state.operationalMode);
    let secondaryVisibleCount = 0;
    let primaryVisibleCount = 0;

    document.querySelectorAll('[data-view]').forEach((el) => {
      const view = el.getAttribute('data-view') || '';
      if (!view) return;
      const allow = state.enabledViews.has(view);
      if (el.classList.contains('dashBtn')) {
        el.classList.toggle('hidden', !allow);
      } else if (el.hasAttribute('data-dock-primary-view')) {
        el.classList.toggle('hidden', !allow);
        if (allow) primaryVisibleCount += 1;
      } else if (el.hasAttribute('data-dock-secondary')) {
        el.classList.toggle('hidden', !allow);
        if (allow) secondaryVisibleCount += 1;
      } else if (el.hasAttribute('data-dock-desktop-secondary')) {
        el.classList.toggle('hidden', !allow);
      } else if (el.id === 'btnReglamento' || el.id === 'btnEditAddress') {
        el.classList.toggle('hidden', !allow);
      }
    });

    if (els.mobileMoreBtn) {
      els.mobileMoreBtn.classList.toggle('hidden', secondaryVisibleCount === 0);
    }
    if (els.mobileDockLayer) {
      els.mobileDockLayer.classList.toggle('hidden', primaryVisibleCount === 0 && secondaryVisibleCount === 0);
    }

    if (els.modeBadge) {
      const preset = String(state.serviceProfile?.preset_servicio || state.operationalMode || 'residencial');
      els.modeBadge.textContent = `Servicio ${modeLabel(preset).toLowerCase()}`;
    }
    if (els.modeHint) {
      els.modeHint.classList.toggle('hidden', isOperational || !!state.serviceProfile);
    }
    // modeOperationSelect chip removed from UI.
  }

  function renderCars(autos) {
    // Deprecated: we removed header autos chips for a cleaner UI.
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
      if (e.target.closest('#btnOpenMobileMoreSheet')) {
        e.preventDefault();
        if (state.moreSheetOpen) {
          closeMoreSheet();
        } else {
          openMoreSheet();
        }
        return;
      }

      if (e.target === els.mobileMoreBackdrop || e.target.closest('#btnCloseMobileMoreSheet')) {
        e.preventDefault();
        closeMoreSheet();
        return;
      }

      const target = e.target.closest('[data-view]');
      if (!target) return;
      const view = target.getAttribute('data-view');
      if (!view) return;
      e.preventDefault();
      closeMoreSheet();
      navigateTo(view);
    });

    els.modalClose?.addEventListener('click', closeCarModal);
    els.modal?.addEventListener('click', (e) => {
      if (e.target === els.modal) closeCarModal();
    });
    els.body?.addEventListener('scroll', handleBodyScroll, { passive: true });
    window.addEventListener('resize', () => {
      closeMoreSheet();
      showMobileDock();
      syncDockModalState();
    });

    initShellHeader();
    initModalWatcher();
    loadContext().then(() => {
      // If context failed, we show the big blocked card.
      if (!state.enabledViews.size && state.contextLoad === 'error') {
        renderAccessBlocked('No pudimos cargar tu contexto operativo. Intenta recargar la página; si el problema continúa, revisa el perfil del servicio en Superadmin.');
        return;
      }

      // If context loaded but there are no operable modules, fall back to a minimal Home/Perfil/Reglamento experience.
      if (!state.enabledViews.size && state.contextLoad === 'ok') {
        state.enabledViews = minimalViewsSet();
        state.homeMinimalNotice = 'Este servicio aún no tiene módulos habilitados para Admin operativo. Puedes ver Home/Perfil/Reglamento mientras se habilitan módulos desde Superadmin.';
        toggleOperationalButtons();
      }

      navigateTo(initialView() || 'home', { force: true }).then(() => {
        if (state.homeMinimalNotice) renderHomeMinimalNotice(state.homeMinimalNotice);
      });
    });
  });

  window.addEventListener('hashchange', () => {
    closeMoreSheet();
    if (!state.enabledViews.size) {
      // If context failed, keep the blocked state; if minimal mode, ignore hash changes to disallowed views.
      if (state.contextLoad === 'error') return;
      state.enabledViews = minimalViewsSet();
      toggleOperationalButtons();
    }
    const next = normalizeView((window.location.hash || '').replace('#', '').trim());
    const current = state.pendingView || state.currentView;
    if (next && next !== current) {
      navigateTo(next);
    }
  });
})();
