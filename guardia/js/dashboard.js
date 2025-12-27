// guardia/js/dashboard.js
(function () {
  const els = {
    name: document.getElementById('guardName'),
    ctx: document.getElementById('guardContext'),
    hint: document.getElementById('guardHint'),
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

  let currentViewScript = null;

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  async function fetchJSON(url, opts = {}) {
    const r = await fetch(url, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store',
      ...opts
    });
    const json = await r.json().catch(() => ({}));
    if (!r.ok || json.ok === false) {
      throw new Error(json.error || 'Error API');
    }
    return json;
  }

  function openModal(title, html) {
    if (!els.modal || !els.modalBody || !els.modalTitle) return;
    els.modalTitle.textContent = title || 'Detalle';
    els.modalBody.innerHTML = html || '';
    els.modal.classList.remove('hidden');
  }

  function closeModal() {
    if (!els.modal) return;
    els.modal.classList.add('hidden');
  }

  async function loadView(view) {
    const wrap = els.body?.querySelector('.max-w-6xl') || els.body;
    if (!wrap) return;

    // active style
    document.querySelectorAll('.dashBtn').forEach(btn => {
      const isActive = btn.dataset.view === view;
      btn.classList.toggle('ring-2', isActive);
      btn.classList.toggle('ring-white/50', isActive);
    });

    // load html
    try {
      const res = await fetch(`${VIEWS}${view}.html`, { cache: 'no-store' });
      if (!res.ok) throw new Error('No se pudo cargar plantilla');
      wrap.innerHTML = await res.text();
    } catch (e) {
      wrap.innerHTML = `
        <div class="rounded-2xl bg-white p-4 shadow">
          <div class="text-sm font-semibold text-rose-600">No se pudo cargar la sección</div>
          <div class="text-xs text-slate-600">Plantilla: ${escapeHtml(view)}.html</div>
        </div>
      `;
      return;
    }

    // load view script
    loadViewScript(view);

    // init hook (cuando el script expone init)
    setTimeout(() => {
      if (window.GuardiaViews && typeof window.GuardiaViews[view] === 'function') {
        window.GuardiaViews[view]({
          BASE,
          API,
          openModal,
          closeModal,
          escapeHtml,
          fetchJSON,
          loadContext,
        });
      }
    }, 0);
  }

  function loadViewScript(view) {
    const scriptsMap = {
      home: null,
      accesos: BASE + 'js/accesos.js',
      autos: BASE + 'js/autos.js',
      incidencias: BASE + 'js/incidencias.js',
      paqueteria: BASE + 'js/paqueteria.js',
    };

    if (currentViewScript) {
      currentViewScript.remove();
      currentViewScript = null;
    }

    if (!scriptsMap[view]) return;

    const s = document.createElement('script');
    s.src = scriptsMap[view] + '?v=' + Date.now();
    s.defer = true;
    s.dataset.viewScript = view;
    document.body.appendChild(s);
    currentViewScript = s;
  }

  function initialView() {
    const h = (window.location.hash || '').replace('#', '').trim();
    if (h) return h;
    return 'home';
  }

  async function loadContext() {
    els.name = els.name || document.getElementById('guardName');
    els.ctx  = els.ctx  || document.getElementById('guardContext');

    if (!els.name || !els.ctx) return;

    try {
      const json = await fetchJSON(`${API}contexto.php`);
      const data = json.data || {};

      els.name.textContent = data.user?.name || 'Guardia';
      els.ctx.textContent  = data.header_line || data.direccion || '—';

      if (els.hint) {
        if (data.residencial_direccion) {
          els.hint.textContent = data.residencial_direccion;
          els.hint.classList.remove('hidden');
        } else {
          els.hint.classList.add('hidden');
        }
      }
    } catch (e) {
      console.warn('loadContext() fallo:', e);
      els.name.textContent = 'Guardia';
      els.ctx.textContent = '—';
    }
  }

  // ✅ API GLOBAL como residente (para que tus vistas puedan usarlo)
  window.GuardiaDashboard = {
    BASE,
    API,
    loadContext,
    loadView,
    fetchJSON,
    openModal,
    closeModal,
    escapeHtml,
  };

  document.addEventListener('DOMContentLoaded', () => {
    // botones footer
    document.querySelectorAll('.dashBtn').forEach(btn => {
      btn.addEventListener('click', () => {
        const v = btn.dataset.view || 'home';
        window.location.hash = v;
        loadView(v);
      });
    });

    // iconos header (data-view)
    document.addEventListener('click', (e) => {
      const t = e.target.closest('[data-view]');
      if (!t) return;
      const v = t.getAttribute('data-view');
      if (!v) return;
      window.location.hash = v;
      loadView(v);
    });

    // reglamento (puedes apuntar a uno real)
    els.btnReg?.addEventListener('click', () => {
      // si tienes un reglamento guardia, cámbialo aquí:
      // loadView('reglamento');
      openModal('Reglamento', `<div class="text-sm">Aquí conectas tu reglamento.</div>`);
    });

    // modal close
    els.modalClose?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (ev) => {
      if (ev.target === els.modal) closeModal();
    });

    // hash routing
    window.addEventListener('hashchange', () => loadView(initialView()));

    // boot
    loadContext();
    loadView(initialView());
  });
})();
