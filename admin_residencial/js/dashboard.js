(function () {
  const els = {
    header: document.getElementById('shellHeader'),
    addr: document.getElementById('residentAddress'),
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
    accessBlocked: false,
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
    if (!state.enabledViews.size) return '';
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

  function resolveServiceDisplayName(data = {}, ctx = {}) {
    const profile = data.service_profile || state.serviceProfile || {};
    const candidates = [
      data.direccion,
      ctx.residencial_nombre,
      data.residencial_nombre,
      profile.nombre_servicio,
      profile.service_name,
      profile.nombre,
      ctx.servicio_nombre,
      data.servicio_nombre,
      ctx.nombre_servicio,
      data.nombre_servicio,
      profile.preset_servicio,
      data.modo_operacion,
      ctx.modo_operacion,
    ];

    for (const candidate of candidates) {
      const value = String(candidate || '').trim();
      if (value) return value;
    }

    return 'Servicio activo';
  }

  function firstEnabledView() {
    if (!state.enabledViews.size) return '';
    if (state.enabledViews.has('home')) return 'home';
    const [first] = state.enabledViews;
    return first || '';
  }

  function isViewEnabledByProfile(profile, view) {
    const alwaysEnabledViews = new Set(['home', 'perfil', 'reglamento']);
    if (alwaysEnabledViews.has(view)) return true;

    const moduleFlagByView = {
      unidades: 'habilita_unidades',
      residentes: 'habilita_residentes_catalogo',
      guardias: 'habilita_guardias_catalogo',
      incidencias: 'habilita_incidencias',
      comunicados: 'habilita_comunicados',
      autos: 'habilita_autos',
      personal_recurrente: 'habilita_personal_recurrente',
      visitantes_rapidos: 'habilita_visitantes_rapidos',
      materiales: 'habilita_materiales',
      solicitudes_pendientes: 'habilita_solicitudes_pendientes',
      bitacora_operativa: 'habilita_bitacora_operativa',
    };

    const flag = moduleFlagByView[view];
    if (!flag) return false;

    const moduleMeta = profile?.modules?.[flag];
    if (moduleMeta && typeof moduleMeta === 'object' && Object.prototype.hasOwnProperty.call(moduleMeta, 'enabled')) {
      return moduleMeta.enabled === true;
    }

    return Number(profile?.[flag] ?? 0) === 1;
  }

  function buildEnabledViews(profile) {
    const allowed = Array.isArray(profile?.allowed_views) ? profile.allowed_views : [];
    const candidates = allowed;
    return new Set(
      candidates.filter((view) => (
        ALLOWED_VIEWS.has(view) &&
        isViewEnabledByProfile(profile, view)
      ))
    );
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
    state.accessBlocked = true;
    hideFooterNavigation();
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

  function resolveActiveView(fallback = 'home') {
    return state.pendingView || state.currentView || fallback;
  }

  function syncHash(view) {
    if ((window.location.hash || '').replace('#', '') !== view) {
      window.location.hash = view;
    }
  }

  function scrollToViewTop() {
    if (els.body && typeof els.body.scrollTo === 'function') {
      els.body.scrollTo({ top: 0, behavior: 'auto' });
    }
  }

  function isMobileViewport() {
    return window.matchMedia('(max-width: 767px)').matches;
  }

  function showMobileDock() {
    if (state.accessBlocked) return;
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
    if (state.accessBlocked) {
      hideFooterNavigation();
      return;
    }
    const hasModal = hasActiveModal();
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

  function openMoreSheet() {
    if (state.accessBlocked) return;
    state.moreSheetOpen = true;
    showMobileDock();
    els.mobileMoreBackdrop?.setAttribute('aria-hidden', 'false');
    els.mobileMoreSheet?.setAttribute('aria-hidden', 'false');
    els.mobileMoreBackdrop?.classList.add('is-open');
    els.mobileMoreSheet?.classList.add('is-open');
    document.body.classList.add('overflow-hidden');
    setActiveButtons(resolveActiveView());
  }

  function closeMoreSheet() {
    state.moreSheetOpen = false;
    document.body.classList.remove('overflow-hidden');
    els.mobileMoreBackdrop?.classList.remove('is-open');
    els.mobileMoreSheet?.classList.remove('is-open');
    els.mobileMoreBackdrop?.setAttribute('aria-hidden', 'true');
    els.mobileMoreSheet?.setAttribute('aria-hidden', 'true');
    setActiveButtons(resolveActiveView());
  }

  function hideFooterNavigation() {
    closeMoreSheet();
    if (els.mobileDockLayer) {
      els.mobileDockLayer.classList.add('hidden', 'mobile-dock-hidden');
      els.mobileDockLayer.style.display = 'none';
      els.mobileDockLayer.setAttribute('aria-hidden', 'true');
      els.mobileDockLayer.style.pointerEvents = 'none';
    }
    if (els.footer) {
      els.footer.style.pointerEvents = 'none';
    }
  }

  function showFooterNavigationIfAllowed() {
    if (state.accessBlocked) return;
    if (!els.mobileDockLayer) return;
    if (!state.enabledViews.size) {
      hideFooterNavigation();
      return;
    }
    els.mobileDockLayer.classList.remove('mobile-dock-hidden');
    els.mobileDockLayer.style.display = '';
    els.mobileDockLayer.setAttribute('aria-hidden', 'false');
    els.mobileDockLayer.style.pointerEvents = '';
    if (els.footer) {
      els.footer.style.pointerEvents = '';
    }
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
    if (!state.enabledViews.size) {
      renderAccessBlocked();
      return;
    }
    const requestedView = String(view || 'home').trim();
    if (!force && requestedView && !state.enabledViews.has(requestedView)) {
      renderAccessBlocked('La sección solicitada no está habilitada para este perfil de servicio.');
      return;
    }
    view = normalizeView(requestedView || 'home');
    if (!view) {
      renderAccessBlocked();
      return;
    }
    state.accessBlocked = false;
    showFooterNavigationIfAllowed();

    if (!force && (state.pendingView === view || state.currentView === view) && state.isNavigating === false) {
      state.pendingView = view;
      closeMoreSheet();
      setActiveButtons(view);
      syncHash(view);
      return;
    }

    state.pendingView = view;
    closeMoreSheet();
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
      setActiveButtons(resolveActiveView());
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
      setActiveButtons(resolveActiveView());
      return;
    }

    state.currentView = view;
    state.pendingView = null;
    state.isNavigating = false;
    setActiveButtons(view);
    showFooterNavigationIfAllowed();
  }

  function initialView() {
    const h = (window.location.hash || '').replace('#', '').trim();
    if (!state.enabledViews.size) return '';
    return normalizeView(h || 'home');
  }

  async function loadContext() {
    els.addr = els.addr || document.getElementById('residentAddress');
    // Header chips ("carsContainer") were removed for a cleaner UI.
    if (!els.addr) return;

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
      state.accessBlocked = !state.enabledViews.size;

      const allowed = Array.isArray(state.serviceProfile?.allowed_views) ? state.serviceProfile.allowed_views : [];
      debugLogContext(allowed, state.enabledViews);

      els.addr.textContent = resolveServiceDisplayName(data, ctx);

      toggleOperationalButtons();
      showFooterNavigationIfAllowed();
    } catch (e) {
      console.warn('loadContext() falló:', e);
      els.addr.textContent = 'Servicio activo';
      state.context = null;
      state.operationalMode = 'residencial';
      state.serviceProfile = null;
      state.enabledViews = new Set();
      state.contextLoad = 'error';
      state.accessBlocked = true;
      debugLogContext([], state.enabledViews);
      toggleOperationalButtons();
      hideFooterNavigation();
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
        const allowInSecondaryDock = allow && SECONDARY_DOCK_VIEWS.has(view);
        el.classList.toggle('hidden', !allowInSecondaryDock);
        if (allowInSecondaryDock) secondaryVisibleCount += 1;
      } else if (el.id === 'btnReglamento') {
        el.classList.toggle('hidden', !allow);
      }
    });

    if (els.mobileMoreBtn) {
      const hideMoreButton = secondaryVisibleCount === 0;
      els.mobileMoreBtn.classList.toggle('hidden', hideMoreButton);
      if (hideMoreButton && state.moreSheetOpen) {
        closeMoreSheet();
      }
    }
    if (els.mobileDockLayer) {
      els.mobileDockLayer.classList.toggle('hidden', primaryVisibleCount === 0 && secondaryVisibleCount === 0);
    }
    if (state.accessBlocked) {
      hideFooterNavigation();
    } else {
      showFooterNavigationIfAllowed();
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
    syncDockModalState();
  }

  function closeCarModal() {
    els.modal = els.modal || document.getElementById('carModal');
    if (!els.modal) return;
    els.modal.classList.add('hidden');
    syncDockModalState();
  }

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  async function withPendingAction(options, task) {
    const settings = options || {};
    const button = settings.button instanceof HTMLElement ? settings.button : null;
    const scope = settings.scope instanceof HTMLElement ? settings.scope : (button?.form || null);
    const label = settings.label || 'Guardando...';
    const lock = Array.isArray(settings.lock) ? settings.lock.filter((el) => el instanceof HTMLElement) : [];

    if (scope?.dataset.submitting === '1') return;
    if (button?.dataset.pending === '1') return;

    const lockedState = lock.map((el) => ({ el, disabled: !!el.disabled }));
    const submitOriginal = button ? {
      html: button.innerHTML,
      disabled: !!button.disabled,
      minWidth: button.style.minWidth || '',
    } : null;

    const usesLightText = !!button && (
      button.classList.contains('text-white') ||
      button.className.includes('bg-[#2E5D73]') ||
      button.className.includes('bg-blue-') ||
      button.className.includes('bg-emerald-') ||
      button.className.includes('bg-rose-') ||
      button.className.includes('bg-amber-')
    );

    const spinnerClasses = usesLightText
      ? 'border-white/35 border-t-white'
      : 'border-slate-300 border-t-slate-700';

    if (scope) {
      scope.dataset.submitting = '1';
      scope.setAttribute('aria-busy', 'true');
    }

    if (button) {
      const width = Math.ceil(button.getBoundingClientRect().width || 0);
      if (width > 0) button.style.minWidth = `${width}px`;
      button.dataset.pending = '1';
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      button.classList.add('opacity-70', 'cursor-not-allowed');
      button.innerHTML = `
        <span class="inline-flex items-center justify-center gap-2">
          <span class="h-4 w-4 animate-spin rounded-full border-2 ${spinnerClasses}"></span>
          <span>${escapeHtml(label)}</span>
        </span>
      `;
    }

    lockedState.forEach(({ el }) => {
      el.disabled = true;
      el.classList.add('opacity-70', 'cursor-not-allowed');
    });

    try {
      return await task();
    } finally {
      if (scope) {
        delete scope.dataset.submitting;
        scope.removeAttribute('aria-busy');
      }

      if (button && submitOriginal) {
        button.disabled = submitOriginal.disabled;
        button.removeAttribute('aria-busy');
        delete button.dataset.pending;
        button.classList.remove('opacity-70', 'cursor-not-allowed');
        button.innerHTML = submitOriginal.html;
        button.style.minWidth = submitOriginal.minWidth;
      }

      lockedState.forEach(({ el, disabled }) => {
        el.disabled = disabled;
        el.classList.remove('opacity-70', 'cursor-not-allowed');
      });
    }
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
    syncOverlayState: syncDockModalState,
    withPendingAction,
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
      showFooterNavigationIfAllowed();
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

      // If context loaded but there are no operable modules, keep a blocked controlled state.
      if (!state.enabledViews.size && state.contextLoad === 'ok') {
        renderAccessBlocked('Este servicio aún no tiene módulos operables para Admin residencial.');
        toggleOperationalButtons();
        return;
      }

      navigateTo(initialView() || 'home', { force: true });
    });
  });

  window.addEventListener('hashchange', () => {
    closeMoreSheet();
    if (!state.enabledViews.size || state.accessBlocked) {
      hideFooterNavigation();
      return;
    }
    const next = normalizeView((window.location.hash || '').replace('#', '').trim());
    const current = state.pendingView || state.currentView;
    if (next && next !== current) {
      navigateTo(next);
    }
  });
})();
