(function () {
  const els = {
    header: document.getElementById('shellHeader'),
    name: document.getElementById('guardName'),
    ctx: document.getElementById('guardContext'),
    hint: document.getElementById('guardHint'),
    turnoBadge: document.getElementById('guardTurnoBadge'),
    statusBadge: document.getElementById('guardStatusBadge'),
    btnReg: document.getElementById('btnReglamento'),
    body: document.getElementById('dashboardBody'),

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
  };

  const state = {
    currentView: null,
    targetView: null,
    currentViewScript: null,
    currentViewController: null,
    context: null,
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

  async function fetchJSON(url, opts = {}) {
    const r = await fetch(url, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
      ...opts,
    });

    const json = await r.json().catch(() => ({}));
    if (!r.ok || json.ok === false) {
      throw new Error(json.error || 'Error API');
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

  function setActiveButtons(view) {
    document.querySelectorAll('.dashBtn').forEach((btn) => {
      const isActive = btn.dataset.view === view;
      btn.classList.toggle('ring-2', isActive);
      btn.classList.toggle('ring-white/50', isActive);
      btn.classList.toggle('scale-[0.99]', isActive);
    });
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
    return h || 'home';
  }

  async function navigateTo(view, opts = {}) {
    const { force = false } = opts;
    if (!view) view = 'home';

    // Si ya estás en la misma vista pero quieres recargarla, se permite con force
    if (!force && state.currentView === view && state.isNavigating === false) {
      return;
    }

    const token = ++state.navToken;
    state.isNavigating = true;
    state.targetView = view;
    state.currentView = view;
    showShellHeader();

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

    if (nameEl) nameEl.textContent = data.user?.name || 'Guardia';
    if (ctxEl) ctxEl.textContent = data.header_line || data.direccion || '—';

    if (hintEl) {
      if (data.direccion) {
        hintEl.textContent = data.direccion;
        hintEl.classList.remove('hidden');
      } else {
        hintEl.classList.add('hidden');
      }
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
      const json = await fetchJSON(`${API}contexto.php`);
      state.context = json.data || {};
      updateHeaderContext(state.context);
      return state.context;
    } catch (e) {
      console.warn('loadContext fallo:', e);
      state.context = null;

      if (els.name) els.name.textContent = 'Guardia';
      if (els.ctx) els.ctx.textContent = '—';

      return null;
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
          <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-2">
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

    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (ev) => {
      if (ev.target === els.modal) closeModal();
    });

    window.addEventListener('hashchange', () => {
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
  };

  document.addEventListener('DOMContentLoaded', async () => {
    initShellHeader();
    bindStaticEvents();
    await loadContext();
    await navigateTo(initialView(), { force: true });
  });
})();
