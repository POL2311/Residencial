(function () {
  const els = {
    header: document.getElementById('shellHeader'),
    name: document.getElementById('guardName'),
    ctx: document.getElementById('guardContext'),
    hint: document.getElementById('guardHint'),
    notificationsButton: document.getElementById('guardNotificationsButton'),
    notificationsBadge: document.getElementById('guardNotificationsBadge'),
    turnoBadge: document.getElementById('guardTurnoBadge'),
    statusBadge: document.getElementById('guardStatusBadge'),
    modeBadge: document.getElementById('guardModeBadge'),
    btnReg: document.getElementById('btnReglamento'),
    body: document.getElementById('dashboardBody'),
    mount: document.getElementById('dashboardViewMount'),
    mobileDockLayer: document.getElementById('mobileDockLayer'),
    mobileMoreBtn: document.getElementById('btnOpenMobileMoreSheet'),
    mobileMoreSheet: document.getElementById('mobileMoreSheet'),
    mobileMoreBackdrop: document.getElementById('mobileMoreBackdrop'),
    mobileMoreClose: document.getElementById('btnCloseMobileMoreSheet'),
    desktopMorePopover: document.getElementById('desktopMorePopover'),

    modal: document.getElementById('gModal'),
    modalTitle: document.getElementById('gModalTitle'),
    modalBody: document.getElementById('gModalBody'),
    modalClose: document.getElementById('gModalClose'),
  };

  function basePath() {
    const p = window.location.pathname;
    const idx = p.indexOf('/guardia/');
    if (idx === -1) return '/guardia/';
    return p.slice(0, idx) + '/guardia/';
  }

  const BASE = basePath();
  const API = BASE + 'php/api/';
  const VIEWS = BASE + 'templates/views/';
  const SCRIPTS = {
    home: BASE + 'js/home.js',
    perfil: BASE + 'js/perfil.js',
    accesos: BASE + 'js/accesos.js',
    autos: BASE + 'js/autos.js',
    incidencias: BASE + 'js/incidencias.js',
    paqueteria: BASE + 'js/paqueteria.js',
    personas_dentro: BASE + 'js/personas_dentro.js',
    materiales_autorizados: BASE + 'js/materiales_autorizados.js',
    bitacora_hoy: BASE + 'js/bitacora_hoy.js',
  };

  const state = {
    currentView: null,
    targetView: null,
    currentViewScript: null,
    currentViewController: null,
    context: null,
    operationalMode: 'residencial',
    serviceProfile: null,
    enabledViews: new Set(),
    notifications: null,
    canOperate: true,
    activationPending: false,
    contextDiagnostic: null,
    navToken: 0,
    isNavigating: false,
    moreSheetOpen: false,
  };
  const PRIMARY_DOCK_VIEWS = new Set(['home', 'accesos', 'incidencias', 'autos']);
  const SECONDARY_DOCK_VIEWS = new Set(['paqueteria', 'personas_dentro', 'materiales_autorizados', 'bitacora_hoy', 'perfil']);

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function sanitizeUserMessage(message, fallback = 'No se pudo completar la solicitud.') {
    let msg = String(message || '').trim();
    if (!msg) return fallback;
    msg = msg
      .replace(/\s*\(HTTP\s+\d+(?:\s*\(redirect\))?\)\.?/gi, '')
      .replace(/\bHTTP\s+\d+(?:\s*\(redirect\))?:\s*/gi, '')
      .replace(/\s*Debug:\s*[^.]+\.?/gi, '')
      .replace(/\s*Respuesta:\s*.+$/gi, '')
      .trim();
    return msg || fallback;
  }

  function showShellHeader() {
    if (!els.header) return;
    els.header.style.marginTop = '0px';
    els.header.style.opacity = '1';
    els.header.style.transform = 'translateY(0)';
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

  function toggleOperationalButtons() {
    let primaryVisibleCount = 0;
    let secondaryVisibleCount = 0;
    document.querySelectorAll('[data-view]').forEach((el) => {
      const view = el.getAttribute('data-view') || '';
      if (!view) return;
      const allow = state.canOperate && state.enabledViews.has(view);
      if (el.hasAttribute('data-dock-primary-view')) {
        el.classList.toggle('hidden', !allow);
        if (allow) primaryVisibleCount += 1;
      } else if (el.hasAttribute('data-dock-secondary')) {
        el.classList.toggle('hidden', !allow);
        if (allow) secondaryVisibleCount += 1;
      } else if (el.hasAttribute('data-dock-desktop-secondary')) {
        el.classList.toggle('hidden', !allow);
      } else {
        el.classList.toggle('hidden', !allow);
      }
    });
    els.mobileMoreBtn?.classList.toggle('hidden', secondaryVisibleCount === 0);
    els.mobileDockLayer?.classList.toggle('hidden', primaryVisibleCount === 0 && secondaryVisibleCount === 0);

    if (els.notificationsButton) {
      els.notificationsButton.classList.toggle('hidden', !state.canOperate);
    }

    if (els.modeBadge) {
      const label = String(state.serviceProfile?.preset_servicio || state.operationalMode || 'residencial');
      els.modeBadge.textContent = `Servicio: ${label}`;
    }
  }

  function buildEnabledViews() {
    const allowed = Array.isArray(state.serviceProfile?.allowed_views) ? state.serviceProfile.allowed_views : [];
    return new Set(allowed);
  }

  function firstEnabledView() {
    if (!state.enabledViews.size) return '';
    if (state.enabledViews.has('home')) return 'home';
    const [first] = state.enabledViews;
    return first || '';
  }

  function guardNotificationsStorageKey() {
    const userId = Number(state.context?.user?.id || 0);
    const residencialId = Number(state.context?.residencial_id || 0);
    return `guard-notifications-seen:${userId}:${residencialId}`;
  }

  function currentNotificationMarker() {
    const latestId = Number(state.notifications?.latest_id || 0);
    const latestUpdatedAt = String(state.notifications?.latest_updated_at || '');
    if (!latestId && !latestUpdatedAt) return '';
    return `${latestId}:${latestUpdatedAt}`;
  }

  function markNotificationsSeen() {
    const marker = currentNotificationMarker();
    if (!marker) return;
    try {
      window.localStorage.setItem(guardNotificationsStorageKey(), marker);
    } catch (_) {}
  }

  function updateNotificationsBadge() {
    if (!els.notificationsBadge) return;

    let seen = '';
    try {
      seen = window.localStorage.getItem(guardNotificationsStorageKey()) || '';
    } catch (_) {}

    const unread = !!currentNotificationMarker() && currentNotificationMarker() !== seen;
    els.notificationsBadge.classList.toggle('hidden', !unread);

    if (els.notificationsButton) {
      els.notificationsButton.classList.toggle('ring-2', unread);
      els.notificationsButton.classList.toggle('ring-white/40', unread);
      els.notificationsButton.setAttribute('aria-label', unread ? 'Hay alertas operativas nuevas' : 'Notificaciones operativas');
      els.notificationsButton.title = unread ? 'Hay alertas operativas nuevas' : 'Notificaciones operativas';
    }
  }

  function formatNotificationDate(value) {
    if (!value) return 'Sin fecha';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return escapeHtml(String(value));
    return date.toLocaleString('es-MX', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  function renderNotificationsContent(items = []) {
    if (!items.length) {
      return `
        <div class="space-y-3">
          <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-600">
            No hay alertas operativas por el momento.
          </div>
          <div class="flex justify-end">
            <button type="button" id="guardRefreshLocalData" class="rounded-xl bg-[#2E5D73] px-4 py-2 text-sm text-white hover:opacity-95">
              Actualizar base local
            </button>
          </div>
        </div>
      `;
    }

    return `
      <div class="space-y-3">
        <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3 text-xs text-sky-800">
          Revisa estos cambios para mantener actualizada la base local de accesos del guardia.
        </div>
        <div class="space-y-3 max-h-[90%] overflow-y-auto pr-1">
          ${items.map((item) => `
            <article class="rounded-2xl border ${item.status === 'permitido' ? 'border-emerald-200 bg-emerald-50/50' : 'border-rose-200 bg-rose-50/50'} px-4 py-4">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.resident_name || 'Residente')}</div>
                  <div class="mt-1 text-xs text-slate-500">Unidad: ${escapeHtml(item.unidad_clave || '—')}</div>
                </div>
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-medium ${item.status === 'permitido' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">
                  ${escapeHtml(item.status_label || 'Actualizado')}
                </span>
              </div>
              <div class="mt-3 text-sm text-slate-700">${escapeHtml(item.reason || 'Sin detalle adicional.')}</div>
              <div class="mt-2 text-[11px] text-slate-500">${escapeHtml(formatNotificationDate(item.updated_at))}</div>
            </article>
          `).join('')}
        </div>
        <div class="flex justify-end">
          <button type="button" id="guardRefreshLocalData" class="rounded-xl bg-[#2E5D73] px-4 py-2 text-sm text-white hover:opacity-95">
            Actualizar base local
          </button>
        </div>
      </div>
    `;
  }

  function initShellHeader() {
    if (!els.header) return;
    showShellHeader();
  }

  async function fetchJSON(url, opts = {}) {
    const { headers = {}, ...rest } = opts || {};
    const r = await fetch(url, {
      headers: { Accept: 'application/json', ...headers },
      credentials: 'same-origin',
      cache: 'no-store',
      ...rest,
    });

    const text = await r.text().catch(() => '');
    let json = {};
    let parsedJson = false;
    if (text) {
      try {
        json = JSON.parse(text);
        parsedJson = true;
      } catch (_) {
        json = {};
      }
    }

    const isOk = r.ok && (json?.ok !== false);
    if (!isOk) {
      const statusPart = `HTTP ${r.status}${r.redirected ? ' (redirect)' : ''}`;
      const debugPart = json?.debug_id ? ` Debug: ${json.debug_id}` : '';
      const snippet = parsedJson ? '' : String(text || '').replace(/\s+/g, ' ').slice(0, 200);
      const rawMessage = json?.error
        ? `${json.error} (${statusPart}).${debugPart}`
        : `${statusPart}: Respuesta no JSON o sin mensaje.${debugPart}${snippet ? ` Respuesta: ${snippet}` : ''}`;
      console.error('[guardia] fetchJSON error', {
        url,
        status: r.status,
        redirected: r.redirected,
        responseUrl: r.url,
        json,
        text: snippet || text,
      });
      throw new Error(sanitizeUserMessage(rawMessage));
    }
    return json;
  }

  function openModal(title, html) {
    if (!els.modal || !els.modalBody || !els.modalTitle) return;
    showShellHeader();
    els.modalTitle.textContent = title || 'Detalle';
    els.modalBody.innerHTML = html || '';
    els.modal.classList.remove('hidden');
  }

  function closeModal() {
    if (!els.modal) return;
    els.modal.classList.add('hidden');
  }

  function getWrap() {
    return els.body?.querySelector('.max-w-6xl') || els.body;
  }

  function renderViewLoading(view) {
    const wrap = getWrap();
    if (!wrap) return;

    wrap.innerHTML = `
      <div class="rounded-2xl bg-white p-4 shadow border border-slate-200">
        <div class="text-sm font-semibold text-slate-700">Cargando sección…</div>
        <div class="text-xs text-slate-500 mt-1">${escapeHtml(view)}</div>
      </div>
    `;
  }

  function renderViewError(view, message) {
    const wrap = getWrap();
    if (!wrap) return;

    wrap.innerHTML = `
      <div class="rounded-2xl bg-white p-4 shadow border border-rose-200">
        <div class="text-sm font-semibold text-rose-600">No se pudo cargar la sección</div>
        <div class="text-xs text-slate-600 mt-1">Plantilla/Vista: ${escapeHtml(view)}</div>
        <div class="text-xs text-slate-500 mt-2">${escapeHtml(message || 'Error desconocido')}</div>
      </div>
    `;
  }

  function renderActivationPending() {
    const wrap = getWrap();
    if (!wrap) return;

    const diagnostic = state.contextDiagnostic || {};
    const title = diagnostic.reason_code === 'guard_role_disabled'
      ? 'Guardia deshabilitado para este servicio'
      : diagnostic.reason_code === 'guard_absence_exception'
        ? 'Ausencia programada'
      : diagnostic.reason_code === 'missing_assignment'
        ? 'Activacion pendiente'
        : 'No pudimos cargar tu servicio';
    const message = diagnostic.reason_message || 'Tu cuenta necesita configuracion adicional antes de operar.';
    const steps = Array.isArray(diagnostic.help_steps) ? diagnostic.help_steps : [];
    const note = diagnostic.reason_code === 'guard_absence_exception'
      ? 'Tu cuenta sigue activa, pero hoy quedó marcada con una ausencia programada y por eso no puede operar.'
      : 'Tu cuenta puede iniciar sesion, pero todavia no tiene un contexto operativo listo para trabajar.';

    wrap.innerHTML = `
      <div class="space-y-4">
        <section class="rounded-[1.75rem] border border-amber-200 bg-white p-5 shadow-sm">
          <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-500">Guardia</div>
          <h2 class="mt-2 text-2xl font-semibold text-slate-900">${escapeHtml(title)}</h2>
          <p class="mt-2 text-sm leading-6 text-slate-600">${escapeHtml(message)}</p>
          <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            ${escapeHtml(note)}
          </div>
        </section>

        <section class="rounded-[1.75rem] border border-slate-200 bg-white p-5 shadow-sm">
          <div class="text-lg font-semibold text-slate-900">${escapeHtml(diagnostic.help_title || 'Como activarlo')}</div>
          <ol class="mt-4 space-y-3 text-sm text-slate-700">
            ${(steps.length ? steps : ['Confirma que la cuenta del guardia este activa.', 'Verifica que tenga un servicio asignado.', 'Revisa que el rol Guardia este habilitado para ese servicio.']).map((step, index) => `
              <li class="flex items-start gap-3">
                <span class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#2E5D73] text-xs font-semibold text-white">${index + 1}</span>
                <span class="leading-6">${escapeHtml(step)}</span>
              </li>
            `).join('')}
          </ol>
        </section>
      </div>
    `;
  }

  function setActiveButtons(view) {
    document.querySelectorAll('.dashBtn').forEach((btn) => {
      const isActive = btn.dataset.view === view;
      btn.classList.toggle('ring-2', isActive);
      btn.classList.toggle('ring-white/50', isActive);
      btn.classList.toggle('scale-[0.99]', isActive);
    });
    document.querySelectorAll('[data-dock-primary-view]').forEach((btn) => {
      const active = btn.getAttribute('data-dock-primary-view') === view;
      btn.classList.toggle('mobile-dock-item-active', active);
    });
    if (els.mobileMoreBtn) {
      const moreActive = SECONDARY_DOCK_VIEWS.has(view) && !PRIMARY_DOCK_VIEWS.has(view);
      els.mobileMoreBtn.classList.toggle('mobile-dock-item-active', moreActive || state.moreSheetOpen);
    }
  }

  async function loadTemplate(view, token) {
    const wrap = getWrap();
    if (!wrap) return false;

    try {
      const res = await fetch(`${VIEWS}${view}.html`, { cache: 'no-store' });
      if (token !== state.navToken) return false;
      if (!res.ok) throw new Error('No se pudo cargar plantilla');

      const html = await res.text();
      if (token !== state.navToken) return false;

      wrap.innerHTML = html;
      return true;
    } catch (e) {
      if (token === state.navToken) {
        renderViewError(view, e.message || 'No se pudo cargar plantilla');
      }
      return false;
    }
  }

  function unloadCurrentView() {
    try {
      if (
        state.currentViewController &&
        typeof state.currentViewController.unmount === 'function'
      ) {
        state.currentViewController.unmount();
      }
    } catch (e) {
      console.warn('unmount fallo', e);
    }

    state.currentViewController = null;

    if (state.currentViewScript) {
      state.currentViewScript.remove();
      state.currentViewScript = null;
    }
  }

  function clearViewRegistration(view) {
    if (window.GuardiaViews && Object.prototype.hasOwnProperty.call(window.GuardiaViews, view)) {
      try {
        delete window.GuardiaViews[view];
      } catch (_) {
        window.GuardiaViews[view] = undefined;
      }
    }
  }

  function loadViewScript(view, token) {
    return new Promise((resolve) => {
      const src = SCRIPTS[view];
      if (!src) {
        resolve(true);
        return;
      }

      clearViewRegistration(view);

      const s = document.createElement('script');
      s.src = `${src}?v=${Date.now()}`;
      s.defer = true;
      s.dataset.viewScript = view;

      s.onload = () => {
        if (token !== state.navToken) {
          resolve(false);
          return;
        }
        resolve(true);
      };

      s.onerror = () => {
        if (token !== state.navToken) {
          resolve(false);
          return;
        }
        resolve(false);
      };

      document.body.appendChild(s);
      state.currentViewScript = s;
    });
  }

  async function mountView(view, token) {
    if (token !== state.navToken) return false;

    if (!window.GuardiaViews || typeof window.GuardiaViews[view] !== 'function') {
      renderViewError(view, 'El script de la vista no se registró correctamente.');
      return false;
    }

    try {
      const controller = window.GuardiaViews[view]({
        BASE,
        API,
        openModal,
        closeModal,
        escapeHtml,
        fetchJSON,
        loadContext,
        getContext: () => state.context,
        navigate: navigateTo,
      });

      if (token !== state.navToken) return false;

      state.currentViewController = controller || null;
      return true;
    } catch (e) {
      if (token === state.navToken) {
        renderViewError(view, e.message || 'La vista falló al inicializar.');
      }
      return false;
    }
  }

  function initialView() {
    const h = (window.location.hash || '').replace('#', '').trim();
    if (!state.enabledViews.size) return '';
    if (!h) return firstEnabledView();
    return !state.enabledViews.has(h) ? firstEnabledView() : h;
  }

  async function navigateTo(view, opts = {}) {
    const { force = false } = opts;
    closeMoreSheet();
    if (!view) view = firstEnabledView();
    if (state.canOperate && !state.enabledViews.size) {
      renderViewError('guardia', 'Este servicio no tiene vistas operables para Guardia.');
      state.isNavigating = false;
      return;
    }
    const requestedView = view;
    if (state.canOperate && !state.enabledViews.has(view)) {
      view = firstEnabledView();
      // Keep URL hash consistent with the actual view we're going to render.
      try {
        if (window.location.hash.replace('#', '') !== view) {
          window.location.hash = view;
        }
      } catch (_) {}
      try {
        if (window.AppToast?.show && requestedView) {
          window.AppToast.show({
            type: 'info',
            title: 'Sección no disponible',
            message: 'Esta sección no está habilitada para este servicio.',
            duration: 4500,
          });
        }
      } catch (_) {}
    }

    // Si ya estás en la misma vista pero quieres recargarla, se permite con force
    if (!force && state.currentView === view && state.isNavigating === false) {
      return;
    }

    const token = ++state.navToken;
    state.isNavigating = true;
    state.targetView = view;
    state.currentView = view;
    showShellHeader();

    if (!state.canOperate) {
      unloadCurrentView();
      setActiveButtons('');
      renderActivationPending();
      state.currentView = 'activation';
      state.targetView = 'activation';
      state.isNavigating = false;
      return;
    }

    setActiveButtons(view);
    unloadCurrentView();
    renderViewLoading(view);

    const templateOk = await loadTemplate(view, token);
    if (!templateOk || token !== state.navToken) {
      state.isNavigating = false;
      return;
    }

    const scriptOk = await loadViewScript(view, token);
    if (!scriptOk || token !== state.navToken) {
      renderViewError(view, 'No se pudo cargar el script de la vista.');
      state.isNavigating = false;
      return;
    }

    await mountView(view, token);

    if (token === state.navToken) {
      state.isNavigating = false;
      state.targetView = view;
    }
  }

  function updateHeaderContext(data) {
    const nameEl = els.name || document.getElementById('guardName');
    const ctxEl = els.ctx || document.getElementById('guardContext');
    const hintEl = els.hint || document.getElementById('guardHint');
    const turnoEl = els.turnoBadge || document.getElementById('guardTurnoBadge');
    const statusEl = els.statusBadge || document.getElementById('guardStatusBadge');

    const rawName = String(data.user?.name || 'Guardia').trim();
    const cleanedName = /^guardia\d+$/i.test(rawName) ? 'Guardia' : rawName.replace(/\d+\s*$/, '').trim() || 'Guardia';
    const serviceLabel = String(state.serviceProfile?.preset_servicio || state.operationalMode || 'residencial').trim() || 'residencial';

    if (nameEl) nameEl.textContent = cleanedName;
    if (ctxEl) ctxEl.textContent = `Servicio: ${serviceLabel}`;

    if (hintEl) {
      hintEl.textContent = '';
      hintEl.classList.add('hidden');
    }

    if (turnoEl) {
      const turnoTxt = data.turno_actual || 'Sin turno';
      turnoEl.innerHTML = `<span class="h-2 w-2 rounded-full bg-emerald-400"></span> Turno: ${escapeHtml(turnoTxt)}`;
    }

    if (statusEl) {
      const estado = data.guardia_en_servicio ? 'En servicio' : 'Fuera de turno';
      statusEl.textContent = `Estado: ${estado}`;
    }
  }

  async function loadContext() {
    try {
      const res = await fetch(`${API}contexto.php`, {
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'application/json' },
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok || json.ok === false) {
        throw Object.assign(new Error(json.error || 'No se pudo cargar el contexto del guardia.'), {
          reason_code: json.reason_code || 'context_load_error',
          reason_message: json.reason_message || json.error || 'Hubo un error al cargar tu servicio de guardia.',
          help_title: json.help_title || 'Como activarlo',
          help_steps: Array.isArray(json.help_steps) ? json.help_steps : [],
        });
      }

      state.context = json.data || {};
      state.operationalMode = String(state.context?.modo_operacion || 'residencial').trim() || 'residencial';
      state.serviceProfile = state.context?.service_profile || null;
      state.notifications = state.context?.notifications || null;
      state.canOperate = state.context?.can_operate !== false;
      state.activationPending = state.context?.activation_pending === true;
      state.contextDiagnostic = state.activationPending ? {
        reason_code: state.context?.reason_code || 'context_load_error',
        reason_message: state.context?.reason_message || 'Tu cuenta necesita configuracion adicional antes de operar.',
        help_title: state.context?.help_title || 'Como activarlo',
        help_steps: Array.isArray(state.context?.help_steps) ? state.context.help_steps : [],
      } : null;
      state.enabledViews = state.canOperate ? buildEnabledViews() : new Set();
      updateHeaderContext(state.context);
      if (!state.canOperate) {
        if (els.ctx) els.ctx.textContent = 'Activacion pendiente';
        if (els.hint) {
          els.hint.textContent = '';
          els.hint.classList.add('hidden');
        }
      }
      toggleOperationalButtons();
      updateNotificationsBadge();
      return state.context;
    } catch (e) {
      console.warn('loadContext fallo:', e);
      state.context = {
        user: state.context?.user || null,
        can_operate: false,
      };
      state.operationalMode = 'residencial';
      state.serviceProfile = null;
      state.notifications = null;
      state.canOperate = false;
      state.activationPending = true;
      state.contextDiagnostic = {
        reason_code: e.reason_code || 'context_load_error',
        reason_message: e.reason_message || e.message || 'Hubo un error al cargar tu servicio de guardia.',
        help_title: e.help_title || 'Como activarlo',
        help_steps: Array.isArray(e.help_steps) ? e.help_steps : [],
      };
      state.enabledViews = new Set();

      if (els.name) els.name.textContent = state.context?.user?.name || 'Guardia';
      if (els.ctx) els.ctx.textContent = 'Activacion pendiente';
      if (els.hint) {
        els.hint.textContent = state.contextDiagnostic.reason_message || '';
        els.hint.classList.remove('hidden');
      }
      toggleOperationalButtons();
      updateNotificationsBadge();

      return null;
    }
  }

  async function openNotifications() {
    openModal('Notificaciones operativas', `<div class="text-sm text-slate-500">Cargando alertas…</div>`);

    try {
      const json = await fetchJSON(`${API}notificaciones.php`);
      const notifications = json.data?.notifications || { items: [] };
      state.notifications = notifications;
      updateNotificationsBadge();
      markNotificationsSeen();
      updateNotificationsBadge();

      if (els.modalBody) {
        els.modalBody.innerHTML = renderNotificationsContent(notifications.items || []);
        els.modalBody.querySelector('#guardRefreshLocalData')?.addEventListener('click', async () => {
          closeModal();
          await loadContext();
          await navigateTo(state.currentView || firstEnabledView(), { force: true });
        });
      }
    } catch (e) {
      if (els.modalBody) {
        els.modalBody.innerHTML = `<div class="text-sm text-rose-600">No se pudieron cargar las alertas operativas.</div>`;
      }
    }
  }

  async function openReglamento() {
    openModal('Reglamento', `<div class="text-sm text-slate-500">Cargando reglamento…</div>`);

    try {
      const json = await fetchJSON(`${API}reglamento.php`);
      const r = json.data || json.reglamento || null;

      if (!r) {
        if (els.modalBody) {
          els.modalBody.innerHTML = `
            <div class="text-sm text-slate-600">
              No hay reglamento público registrado para este residencial.
            </div>
          `;
        }
        return;
      }

      if (els.modalBody) {
        els.modalBody.innerHTML = `
          <div class="space-y-3 max-h-[90%] overflow-y-auto pr-2">
            <div class="text-lg font-semibold text-slate-800">
              ${escapeHtml(r.titulo)}
            </div>

            <div class="text-xs text-slate-500">
              Versión ${escapeHtml(r.version_label || '—')}
            </div>

            <div class="prose prose-sm max-w-none text-slate-700 whitespace-pre-line">
              ${escapeHtml(r.contenido)}
            </div>
          </div>
        `;
      }
    } catch (e) {
      if (els.modalBody) {
        els.modalBody.innerHTML = `
          <div class="text-sm text-rose-600">
            No se pudo cargar el reglamento.
          </div>
        `;
      }
    }
  }

  function bindStaticEvents() {
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

      if (e.target.closest('#mobileReglamentoShortcut') || e.target.closest('#desktopReglamentoShortcut')) {
        e.preventDefault();
        closeMoreSheet();
        openReglamento();
        return;
      }

      const t = e.target.closest('[data-view]');
      if (!t) return;
      const v = t.getAttribute('data-view');
      if (!v) return;

      if (window.location.hash.replace('#', '') !== v) {
        window.location.hash = v;
        return;
      }

      navigateTo(v);
    });

    els.btnReg?.addEventListener('click', openReglamento);
    els.notificationsButton?.addEventListener('click', openNotifications);

    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (ev) => {
      if (ev.target === els.modal) closeModal();
    });

    els.body?.addEventListener('scroll', showMobileDock, { passive: true });
    window.addEventListener('resize', () => {
      closeMoreSheet();
      showMobileDock();
      syncDockModalState();
    });

    window.addEventListener('hashchange', () => {
      closeMoreSheet();
      const next = initialView();
      const current = state.targetView || state.currentView;
      if (next && next !== current) {
        navigateTo(next);
      }
    });
  }

  window.GuardiaDashboard = {
    BASE,
    API,
    loadContext,
    navigateTo,
    fetchJSON,
    openModal,
    closeModal,
    escapeHtml,
    getContext: () => state.context,
    getOperationalMode: () => state.operationalMode,
    getServiceProfile: () => state.serviceProfile,
  };

  document.addEventListener('DOMContentLoaded', async () => {
    initShellHeader();
    bindStaticEvents();
    initModalWatcher();
    await loadContext();
    if (!state.canOperate) {
      await navigateTo('', { force: true });
      return;
    }
    if (!state.enabledViews.size) {
      renderViewError('guardia', 'Este servicio no tiene vistas operables para Guardia.');
      return;
    }
    await navigateTo(initialView(), { force: true });
  });
})();
