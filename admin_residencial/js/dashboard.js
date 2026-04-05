(function () {
  const els = {
    name: document.getElementById('residentName'),
    addr: document.getElementById('residentAddress'),
    btnEdit: document.getElementById('btnEditAddress'),
    cars: document.getElementById('carsContainer'),

    btnReg: document.getElementById('btnReglamento'),
    body: document.getElementById('dashboardBody'),

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

  let currentViewScript = null;

  function loadViewScript(view) {
    // Solo mete aquí lo que realmente tengas
    const scriptsMap = {
      perfil: BASE + 'js/perfil.js',
      autos: BASE + 'js/autos.js',
      guardias: BASE + 'js/guardias.js',
      comunicados: BASE + 'js/comunicados.js',
      reglamento: BASE + 'js/reglamento.js',

      // Módulos ya disponibles:
      unidades: BASE + 'js/unidades.js',
      residentes: BASE + 'js/residentes.js',
      incidencias: BASE + 'js/incidencias.js',
      home: BASE + 'js/home.js',
    };

    if (currentViewScript) {
      currentViewScript.remove();
      currentViewScript = null;
    }

    if (!scriptsMap[view]) return;

    const s = document.createElement('script');
    s.src = scriptsMap[view];
    s.defer = true;
    s.dataset.viewScript = view;
    document.body.appendChild(s);
    currentViewScript = s;
  }

  async function loadView(view) {
    const url = `${VIEWS}${view}.html`;
    const wrap = els.body?.querySelector('.max-w-6xl') || els.body;
    if (!wrap) return;

    try {
      const res = await fetch(url, { cache: 'no-store' });
      if (!res.ok) throw new Error('No se pudo cargar plantilla');
      wrap.innerHTML = await res.text();
    } catch (e) {
      wrap.innerHTML = `
        <div class="rounded-2xl bg-white p-4 shadow">
          <div class="text-sm font-semibold text-rose-600">No se pudo cargar la sección</div>
          <div class="text-xs text-slate-600">Plantilla: ${escapeHtml(view)}.html</div>
        </div>
      `;
    }

    // marcar activo el botón
    document.querySelectorAll('.dashBtn').forEach(btn => {
      const isActive = btn.dataset.view === view;
      btn.classList.toggle('ring-2', isActive);
      btn.classList.toggle('ring-black/20', isActive);
    });

    loadViewScript(view);
    window.location.hash = view;
  }

  function initialView() {
    const h = (window.location.hash || '').replace('#', '').trim();
    return h || 'home';
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
    } catch (e) {
      console.warn('loadContext() falló:', e);
      els.name.textContent = 'Admin residencial';
      els.addr.textContent = '—';
      els.cars.innerHTML = `<span class="text-xs opacity-90">—</span>`;
    }
  }

  function renderCars(autos) {
    if (!els.cars) return;

    if (!autos || autos.length === 0) {
      els.cars.innerHTML = `<span class="text-xs opacity-90">—</span>`;
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

      btn.className = `h-8 w-8 flex items-center justify-center ${colorClass} opacity-95 hover:opacity-100`;
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
    loadView,
    BASE,
    API,
    VIEWS,
  };

  window.AdminResidencialDashboard = api;

  // ✅ compatibilidad por si tus scripts aún usan el nombre viejo
  window.ResidenteDashboard = api;

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.dashBtn').forEach(btn => {
      btn.addEventListener('click', () => loadView(btn.dataset.view));
    });

    els.btnReg?.addEventListener('click', () => loadView('reglamento'));
    els.btnEdit?.addEventListener('click', () => loadView('perfil'));

    els.modalClose?.addEventListener('click', closeCarModal);
    els.modal?.addEventListener('click', (e) => {
      if (e.target === els.modal) closeCarModal();
    });

    loadContext();
    loadView(initialView());
  });
})();
