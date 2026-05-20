(function () {
  function baseResidentPath() {
    const p = window.location.pathname;
    const idx = p.indexOf('/residente/');
    if (idx === -1) return '/residente/';
    return p.slice(0, idx) + '/residente/';
  }

  const root = document.getElementById('residentAmenidadesView');
  if (!root || root.dataset.bound === '1') return;
  root.dataset.bound = '1';

  const API = baseResidentPath() + 'php/api/amenidades.php';
  const els = {
    alert: document.getElementById('residentAmenidadesAlert'),
    catalogo: document.getElementById('residentAmenidadesCatalogo'),
    reservas: document.getElementById('residentAmenidadesReservas'),
    btnNew: document.getElementById('btnNuevaAmenidadResident'),
    modal: document.getElementById('residentAmenidadModal'),
    form: document.getElementById('residentAmenidadForm'),
    btnClose: document.getElementById('btnCloseResidentAmenidadModal'),
    btnCancel: document.getElementById('btnCancelResidentAmenidadModal'),
  };

  const state = {
    amenidades: [],
    reservas: [],
  };

  function escapeHtml(value = '') {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function pad2(value) {
    return String(value).padStart(2, '0');
  }

  function todayInput() {
    const d = new Date();
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
  }

  function plusHourTime() {
    const d = new Date(Date.now() + 60 * 60 * 1000);
    return `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
  }

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...rest,
      headers: { Accept: 'application/json', ...headers },
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.error || 'No se pudo completar la solicitud.');
    return json;
  }

  function showAlert(message = '', type = 'ok') {
    if (!els.alert) return;
    if (!message) {
      els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
      els.alert.textContent = '';
      return;
    }
    els.alert.className = `rounded-2xl border px-4 py-3 text-sm ${
      type === 'error'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : 'border-emerald-200 bg-emerald-50 text-emerald-700'
    }`;
    els.alert.textContent = message;
  }

  function statusBadge(status) {
    const map = {
      pendiente: 'bg-amber-50 text-amber-700 border-amber-200',
      aprobada: 'bg-emerald-50 text-emerald-700 border-emerald-200',
      rechazada: 'bg-rose-50 text-rose-700 border-rose-200',
      cancelada: 'bg-slate-100 text-slate-600 border-slate-200',
      finalizada: 'bg-slate-100 text-slate-600 border-slate-200',
    };
    return map[status] || map.pendiente;
  }

  function renderCatalogo() {
    if (!els.catalogo) return;
    if (!state.amenidades.length) {
      els.catalogo.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay amenidades disponibles por el momento.</div>';
      return;
    }
    els.catalogo.innerHTML = state.amenidades.map((item) => `
      <article class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="text-base font-semibold text-slate-800">${escapeHtml(item.nombre)}</h3>
            <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.ubicacion || 'Sin ubicación')} · ${item.capacidad ? `${escapeHtml(item.capacidad)} personas` : 'Capacidad por confirmar'}</div>
            <p class="mt-2 text-sm leading-6 text-slate-600">${escapeHtml(item.descripcion || 'Sin descripción')}</p>
          </div>
          <button type="button" data-request-amenidad="${item.id}" class="rounded-full bg-[#2E5D73] px-3 py-1.5 text-xs font-semibold text-white">Apartar</button>
        </div>
      </article>
    `).join('');
  }

  function renderReservas() {
    if (!els.reservas) return;
    if (!state.reservas.length) {
      els.reservas.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Aún no has solicitado amenidades.</div>';
      return;
    }
    els.reservas.innerHTML = state.reservas.map((item) => `
      <article class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-base font-semibold text-slate-800">${escapeHtml(item.amenidad_nombre || 'Amenidad')}</h3>
              <span class="rounded-full border px-2.5 py-1 text-[11px] ${statusBadge(item.estado)}">${escapeHtml(item.estado)}</span>
            </div>
            <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.fecha)} · ${escapeHtml(item.hora_inicio)}-${escapeHtml(item.hora_fin)}</div>
            ${item.notas_admin ? `<div class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">Admin: ${escapeHtml(item.notas_admin)}</div>` : ''}
          </div>
          ${item.estado === 'pendiente' ? `<button type="button" data-cancel-reserva="${item.id}" class="rounded-full border border-rose-200 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700">Cancelar</button>` : ''}
        </div>
      </article>
    `).join('');
  }

  function fillAmenitySelect(selectedId = '') {
    const select = els.form?.querySelector('[name="amenidad_id"]');
    if (!select) return;
    select.innerHTML = state.amenidades.map((item) => `<option value="${item.id}">${escapeHtml(item.nombre)}</option>`).join('');
    if (selectedId) select.value = String(selectedId);
  }

  function openModal(selectedId = '') {
    if (!els.modal || !els.form) return;
    els.form.reset();
    fillAmenitySelect(selectedId);
    const fecha = els.form.querySelector('[name="fecha"]');
    const inicio = els.form.querySelector('[name="hora_inicio"]');
    const fin = els.form.querySelector('[name="hora_fin"]');
    if (fecha) fecha.value = todayInput();
    if (inicio) inicio.value = plusHourTime();
    if (fin) {
      const d = new Date(Date.now() + 2 * 60 * 60 * 1000);
      fin.value = `${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
    }
    els.modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    els.modal?.classList.add('hidden');
    document.body.style.overflow = '';
  }

  async function load() {
    const json = await fetchJSON(API);
    state.amenidades = Array.isArray(json.data?.amenidades) ? json.data.amenidades : [];
    state.reservas = Array.isArray(json.data?.reservas) ? json.data.reservas : [];
    renderCatalogo();
    renderReservas();
    fillAmenitySelect();
  }

  els.btnNew?.addEventListener('click', () => openModal());
  els.btnClose?.addEventListener('click', closeModal);
  els.btnCancel?.addEventListener('click', closeModal);
  els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
  els.catalogo?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-request-amenidad]');
    if (!btn) return;
    openModal(btn.dataset.requestAmenidad || '');
  });
  els.reservas?.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-cancel-reserva]');
    if (!btn) return;
    const fd = new FormData();
    fd.set('action', 'cancel');
    fd.set('id', btn.dataset.cancelReserva || '');
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      showAlert('Solicitud cancelada correctamente.');
      await load();
    } catch (err) {
      showAlert(err.message || 'No se pudo cancelar la solicitud.', 'error');
    }
  });
  els.form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(els.form);
    fd.set('action', 'create');
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      closeModal();
      showAlert('Solicitud enviada correctamente.');
      await load();
    } catch (err) {
      showAlert(err.message || 'No se pudo enviar la solicitud.', 'error');
    }
  });

  load().catch((err) => {
    showAlert(err.message || 'No se pudieron cargar las amenidades.', 'error');
    if (els.catalogo) els.catalogo.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No se pudieron cargar amenidades.</div>';
  });
})();
