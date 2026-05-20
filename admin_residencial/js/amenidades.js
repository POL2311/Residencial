(function () {
  const root = document.getElementById('adminAmenidadesView');
  if (!root || root.dataset.bound === '1') return;
  root.dataset.bound = '1';

  const API = '/admin_residencial/php/api/amenidades.php';
  const els = {
    alert: document.getElementById('amenidadesAdminAlert'),
    tabs: root.querySelectorAll('[data-amenidades-tab]'),
    solicitudesSection: document.getElementById('amenidadesSolicitudesSection'),
    catalogoSection: document.getElementById('amenidadesCatalogoSection'),
    reservasList: document.getElementById('amenidadesAdminReservasList'),
    catalogoList: document.getElementById('amenidadesAdminCatalogoList'),
    estado: document.getElementById('amenidadesAdminEstado'),
    fechaDesde: document.getElementById('amenidadesAdminFechaDesde'),
    btnFiltrar: document.getElementById('btnFiltrarAmenidadesAdmin'),
    btnNueva: document.getElementById('btnNuevaAmenidadAdmin'),
    amenidadModal: document.getElementById('amenidadAdminModal'),
    amenidadTitle: document.getElementById('amenidadAdminModalTitle'),
    amenidadForm: document.getElementById('amenidadAdminForm'),
    btnCloseAmenidad: document.getElementById('btnCloseAmenidadAdminModal'),
    btnCancelAmenidad: document.getElementById('btnCancelAmenidadAdminModal'),
    reviewModal: document.getElementById('amenidadReviewModal'),
    reviewTitle: document.getElementById('amenidadReviewTitle'),
    reviewSummary: document.getElementById('amenidadReviewSummary'),
    reviewForm: document.getElementById('amenidadReviewForm'),
    btnCloseReview: document.getElementById('btnCloseAmenidadReviewModal'),
    btnCancelReview: document.getElementById('btnCancelAmenidadReviewModal'),
    btnSubmitReview: document.getElementById('btnSubmitAmenidadReview'),
  };

  const state = {
    amenidades: [],
    reservas: [],
    tab: 'solicitudes',
  };

  function escapeHtml(value = '') {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
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

  function showAlert(message = '', type = 'success') {
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

  function setTab(tab) {
    state.tab = tab;
    els.solicitudesSection?.classList.toggle('hidden', tab !== 'solicitudes');
    els.catalogoSection?.classList.toggle('hidden', tab !== 'catalogo');
    els.tabs.forEach((btn) => {
      const active = btn.dataset.amenidadesTab === tab;
      btn.className = active
        ? 'rounded-full bg-[#2E5D73] px-4 py-2 text-sm font-semibold text-white shadow'
        : 'rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700';
    });
  }

  function renderCatalogo() {
    if (!els.catalogoList) return;
    if (!state.amenidades.length) {
      els.catalogoList.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">Aún no hay amenidades registradas.</div>';
      return;
    }
    els.catalogoList.innerHTML = state.amenidades.map((item) => `
      <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.nombre)}</h3>
              <span class="rounded-full border px-2.5 py-1 text-xs ${Number(item.activo) === 1 ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-100 text-slate-600'}">${Number(item.activo) === 1 ? 'Activa' : 'Inactiva'}</span>
            </div>
            <div class="mt-1 text-sm text-slate-500">${escapeHtml(item.ubicacion || 'Sin ubicación')} · ${item.capacidad ? `${escapeHtml(item.capacidad)} personas` : 'Sin capacidad'}</div>
            <p class="mt-3 text-sm leading-6 text-slate-600">${escapeHtml(item.descripcion || 'Sin descripción')}</p>
          </div>
          <button type="button" data-edit-amenidad="${item.id}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Editar</button>
        </div>
      </article>
    `).join('');
  }

  function renderReservas() {
    if (!els.reservasList) return;
    if (!state.reservas.length) {
      els.reservasList.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">No hay solicitudes con los filtros actuales.</div>';
      return;
    }
    els.reservasList.innerHTML = state.reservas.map((item) => {
      const pending = item.estado === 'pendiente';
      return `
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-lg font-semibold text-slate-800">${escapeHtml(item.amenidad_nombre || 'Amenidad')}</h3>
                <span class="rounded-full border px-2.5 py-1 text-xs ${statusBadge(item.estado)}">${escapeHtml(item.estado)}</span>
              </div>
              <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.fecha)} · ${escapeHtml(item.hora_inicio)}-${escapeHtml(item.hora_fin)} · Unidad ${escapeHtml(item.unidad_clave || '—')}</div>
              <div class="mt-1 text-sm text-slate-500">Residente: ${escapeHtml(item.residente_nombre || '—')} · ${escapeHtml(item.amenidad_ubicacion || 'Sin ubicación')}</div>
              ${item.notas_residente ? `<p class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">${escapeHtml(item.notas_residente)}</p>` : ''}
              ${item.notas_admin ? `<p class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-500">Admin: ${escapeHtml(item.notas_admin)}</p>` : ''}
            </div>
            ${pending ? `
              <div class="grid gap-2 sm:grid-cols-2 lg:w-56">
                <button type="button" data-review="approve" data-id="${item.id}" class="rounded-xl bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Aprobar</button>
                <button type="button" data-review="reject" data-id="${item.id}" class="rounded-xl border border-rose-200 bg-white px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Rechazar</button>
              </div>
            ` : ''}
          </div>
        </article>
      `;
    }).join('');
  }

  function openAmenidadModal(item = null) {
    if (!els.amenidadModal || !els.amenidadForm) return;
    els.amenidadTitle.textContent = item ? 'Editar amenidad' : 'Nueva amenidad';
    els.amenidadForm.reset();
    els.amenidadForm.elements.id.value = item?.id || '';
    els.amenidadForm.elements.nombre.value = item?.nombre || '';
    els.amenidadForm.elements.ubicacion.value = item?.ubicacion || '';
    els.amenidadForm.elements.capacidad.value = item?.capacidad || '';
    els.amenidadForm.elements.descripcion.value = item?.descripcion || '';
    els.amenidadForm.elements.activo.checked = item ? Number(item.activo) === 1 : true;
    els.amenidadModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeAmenidadModal() {
    els.amenidadModal?.classList.add('hidden');
    document.body.style.overflow = '';
  }

  function openReviewModal(item, decision) {
    if (!els.reviewModal || !els.reviewForm) return;
    els.reviewForm.reset();
    els.reviewForm.elements.id.value = item.id;
    els.reviewForm.elements.decision.value = decision;
    els.reviewTitle.textContent = decision === 'approve' ? 'Aprobar reserva' : 'Rechazar reserva';
    els.reviewSummary.innerHTML = `
      <div class="font-semibold text-slate-800">${escapeHtml(item.amenidad_nombre || 'Amenidad')}</div>
      <div class="mt-1 text-slate-600">${escapeHtml(item.fecha)} · ${escapeHtml(item.hora_inicio)}-${escapeHtml(item.hora_fin)}</div>
      <div class="mt-1 text-slate-500">${escapeHtml(item.residente_nombre || '—')} · Unidad ${escapeHtml(item.unidad_clave || '—')}</div>
    `;
    els.btnSubmitReview.className = decision === 'approve'
      ? 'rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white'
      : 'rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white';
    els.btnSubmitReview.textContent = decision === 'approve' ? 'Aprobar' : 'Rechazar';
    els.reviewModal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
  }

  function closeReviewModal() {
    els.reviewModal?.classList.add('hidden');
    document.body.style.overflow = '';
  }

  async function load() {
    const qs = new URLSearchParams();
    if (els.estado?.value) qs.set('estado', els.estado.value);
    if (els.fechaDesde?.value) qs.set('fecha_desde', els.fechaDesde.value);
    const json = await fetchJSON(`${API}?${qs.toString()}`);
    state.amenidades = Array.isArray(json.data?.amenidades) ? json.data.amenidades : [];
    state.reservas = Array.isArray(json.data?.reservas) ? json.data.reservas : [];
    renderCatalogo();
    renderReservas();
  }

  els.tabs.forEach((btn) => btn.addEventListener('click', () => setTab(btn.dataset.amenidadesTab || 'solicitudes')));
  els.btnNueva?.addEventListener('click', () => openAmenidadModal());
  els.btnFiltrar?.addEventListener('click', () => load().catch((e) => showAlert(e.message, 'error')));
  els.btnCloseAmenidad?.addEventListener('click', closeAmenidadModal);
  els.btnCancelAmenidad?.addEventListener('click', closeAmenidadModal);
  els.btnCloseReview?.addEventListener('click', closeReviewModal);
  els.btnCancelReview?.addEventListener('click', closeReviewModal);
  els.amenidadModal?.addEventListener('click', (e) => { if (e.target === els.amenidadModal) closeAmenidadModal(); });
  els.reviewModal?.addEventListener('click', (e) => { if (e.target === els.reviewModal) closeReviewModal(); });

  els.catalogoList?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-edit-amenidad]');
    if (!btn) return;
    const item = state.amenidades.find((row) => String(row.id) === String(btn.dataset.editAmenidad));
    if (item) openAmenidadModal(item);
  });

  els.reservasList?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-review]');
    if (!btn) return;
    const item = state.reservas.find((row) => String(row.id) === String(btn.dataset.id));
    if (item) openReviewModal(item, btn.dataset.review);
  });

  els.amenidadForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(els.amenidadForm);
    fd.set('action', 'save_amenidad');
    fd.set('activo', els.amenidadForm.elements.activo.checked ? '1' : '0');
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      closeAmenidadModal();
      showAlert('Amenidad guardada correctamente.');
      await load();
      setTab('catalogo');
    } catch (err) {
      showAlert(err.message || 'No se pudo guardar la amenidad.', 'error');
    }
  });

  els.reviewForm?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(els.reviewForm);
    fd.set('action', 'review');
    try {
      await fetchJSON(API, { method: 'POST', body: fd });
      closeReviewModal();
      showAlert(fd.get('decision') === 'approve' ? 'Reserva aprobada.' : 'Reserva rechazada.');
      await load();
    } catch (err) {
      closeReviewModal();
      showAlert(err.message || 'No se pudo revisar la solicitud.', 'error');
      await load().catch(() => {});
    }
  });

  setTab('solicitudes');
  load().catch((e) => showAlert(e.message || 'No se pudieron cargar amenidades.', 'error'));
})();
