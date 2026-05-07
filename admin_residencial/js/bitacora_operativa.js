(function () {
  const root = document.getElementById('bitacoraOperativaView');
  if (!root) return;

  const API = '/admin_residencial/php/api/bitacora_operativa.php';
  const els = {
    form: document.getElementById('bitacoraFilters'),
    desde: document.getElementById('bitacoraDesde'),
    hasta: document.getElementById('bitacoraHasta'),
    tipo: document.getElementById('bitacoraTipo'),
    alert: document.getElementById('bitacoraAlert'),
    summary: document.getElementById('bitacoraSummary'),
    list: document.getElementById('bitacoraList'),
  };

  function escapeHtml(v = '') {
    return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function appRootBase() {
    try {
      const p = window.location.pathname || '';
      const idx = p.indexOf('/admin_residencial/');
      if (idx === -1) return '';
      return p.slice(0, idx);
    } catch (_) {
      return '';
    }
  }

  function resolvePublicUrl(url) {
    const u = String(url || '').trim();
    if (!u) return '';
    if (u.startsWith('/assets/')) return appRootBase() + u;
    return u;
  }

  function openEvidenceGallery(urls = [], startIndex = 0) {
    if (!Array.isArray(urls) || !urls.length) return;

    let current = Math.max(0, Math.min(startIndex, urls.length - 1));
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 bg-black/70 p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="min-h-full flex items-center justify-center">
        <div class="w-full max-w-4xl rounded-3xl bg-white shadow-2xl">
          <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div class="text-sm font-semibold text-slate-800">Evidencias del movimiento</div>
            <button type="button" class="js-evidence-close h-11 w-11 rounded-full border border-slate-200 bg-slate-50 text-xl text-slate-500 hover:bg-slate-100">×</button>
          </div>
          <div class="space-y-4 p-5">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-slate-950/95">
              <img id="adminBitacoraGalleryImage" src="" alt="Evidencia" class="h-[60vh] w-full object-contain" />
            </div>
            <div class="flex items-center justify-between gap-3">
              <button type="button" id="adminBitacoraPrev" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Anterior</button>
              <div id="adminBitacoraCounter" class="text-sm text-slate-500"></div>
              <button type="button" id="adminBitacoraNext" class="rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Siguiente</button>
            </div>
            <div id="adminBitacoraThumbs" class="flex flex-wrap gap-2"></div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    const img = modal.querySelector('#adminBitacoraGalleryImage');
    const prev = modal.querySelector('#adminBitacoraPrev');
    const next = modal.querySelector('#adminBitacoraNext');
    const counter = modal.querySelector('#adminBitacoraCounter');
    const thumbs = modal.querySelector('#adminBitacoraThumbs');

    function close() {
      document.body.style.overflow = '';
      modal.remove();
    }

    function paint() {
      if (!img || !counter || !thumbs) return;
      img.src = resolvePublicUrl(urls[current]);
      counter.textContent = `${current + 1} de ${urls.length}`;
      thumbs.innerHTML = urls.map((url, index) => `
        <button type="button" class="overflow-hidden rounded-2xl border ${index === current ? 'border-[#2E5D73] ring-2 ring-[#2E5D73]/15' : 'border-slate-200'} bg-white" data-gallery-index="${index}">
          <img src="${escapeHtml(resolvePublicUrl(url))}" alt="Miniatura ${index + 1}" class="h-16 w-16 object-cover" loading="lazy" />
        </button>
      `).join('');
    }

    modal.addEventListener('click', (event) => {
      if (event.target === modal || event.target.closest('.js-evidence-close')) {
        close();
        return;
      }
      const thumb = event.target.closest('[data-gallery-index]');
      if (thumb) {
        current = Number(thumb.dataset.galleryIndex || 0);
        paint();
      }
    });
    prev?.addEventListener('click', () => {
      current = current === 0 ? urls.length - 1 : current - 1;
      paint();
    });
    next?.addEventListener('click', () => {
      current = current === urls.length - 1 ? 0 : current + 1;
      paint();
    });

    paint();
  }

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...rest, headers: { Accept: 'application/json', ...headers } });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'Error');
    return json;
  }

  function showAlert(msg = '', type = 'info') {
    if (!els.alert) return;
    if (!msg) {
      els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
      els.alert.textContent = '';
      return;
    }
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error' ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');
  }

  function renderSummary(summary) {
    els.summary.innerHTML = `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Movimientos</div><div class="mt-2 text-3xl font-bold text-slate-800">${summary.total || 0}</div></div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Permitidos</div><div class="mt-2 text-3xl font-bold text-emerald-600">${summary.permitidos || 0}</div></div>
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><div class="text-xs uppercase tracking-wide text-slate-400">Denegados</div><div class="mt-2 text-3xl font-bold text-rose-600">${summary.denegados || 0}</div></div>
    `;
  }

  function renderList(items) {
    if (!items.length) {
      els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay movimientos en el rango seleccionado.</div>`;
      return;
    }

    els.list.innerHTML = items.map((item) => `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-base font-semibold text-slate-800">${escapeHtml(item.tipo_evento)} · ${escapeHtml(item.tipo_origen)}</h3>
              <span class="rounded-full px-2.5 py-1 text-xs ${item.resultado === 'permitido' ? 'bg-emerald-100 text-emerald-700' : item.resultado === 'denegado' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600'}">${escapeHtml(item.resultado)}</span>
            </div>
            <div class="mt-1 text-sm text-slate-600">
              Guardia: ${escapeHtml(item.guardia_nombre || '—')} · Área: ${escapeHtml(item.area_nombre || '—')}
            </div>
            <div class="mt-1 text-sm text-slate-500">
              ${escapeHtml(item.persona_nombre || item.nombre_visitante || item.permiso_tipo_movimiento || 'Evento general')}
            </div>
            <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.observaciones || 'Sin observaciones')}</div>
            ${Array.isArray(item.evidencias) && item.evidencias.length ? `
              <div class="mt-3 flex flex-wrap gap-2">
                ${item.evidencias.slice(0, 3).map((url, index) => `
                  <button type="button" class="js-bitacora-evidence block h-16 w-16 overflow-hidden rounded-xl border border-slate-200 bg-slate-100" data-item-id="${item.id}" data-evidence-index="${index}">
                    <img src="${escapeHtml(resolvePublicUrl(url))}" alt="Evidencia" class="h-full w-full object-cover" loading="lazy" />
                  </button>
                `).join('')}
              </div>
            ` : ''}
          </div>
          <div class="text-xs text-slate-400">${escapeHtml(item.fecha_hora)}</div>
        </div>
      </div>
    `).join('');
  }

  async function load() {
    try {
      const query = new URLSearchParams({
        desde: els.desde.value,
        hasta: els.hasta.value,
      });
      if (els.tipo.value) query.set('tipo_origen', els.tipo.value);

      const json = await fetchJSON(`${API}?${query.toString()}`);
      els.list.__items = json.data?.items || [];
      renderSummary(json.data?.summary || {});
      renderList(els.list.__items);
    } catch (e) {
      showAlert(e.message || 'No se pudo cargar la bitácora.', 'error');
    }
  }

  const today = new Date().toISOString().slice(0, 10);
  els.desde.value = today;
  els.hasta.value = today;
  els.list?.addEventListener('click', (event) => {
    const btn = event.target.closest('.js-bitacora-evidence');
    if (!btn) return;
    const itemId = Number(btn.dataset.itemId || 0);
    const evidenceIndex = Number(btn.dataset.evidenceIndex || 0);
    const item = (els.list.__items || []).find((row) => Number(row.id) === itemId);
    if (!item?.evidencias?.length) return;
    openEvidenceGallery(item.evidencias, evidenceIndex);
  });
  els.form?.addEventListener('submit', (ev) => {
    ev.preventDefault();
    load();
  });

  load();
})();
