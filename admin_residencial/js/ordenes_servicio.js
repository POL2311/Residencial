(function () {
  const root = document.getElementById('ordenesServicioView');
  if (!root) return;

  function basePath() {
    try {
      const p = window.location.pathname || '';
      const idx = p.indexOf('/admin_residencial/');
      if (idx !== -1) return p.slice(0, idx) + '/admin_residencial/';
    } catch (_) {}
    return '/admin_residencial/';
  }

  const API = basePath() + 'php/api/ordenes_servicio.php';
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_opts, task) => task());
  const syncOverlay = () => window.AdminResidencialDashboard?.syncOverlayState?.();

  const state = {
    mode: window.AdminResidencialDashboard?.getOperationalMode?.() || 'retailops',
    meta: { proveedores: [], personas: [], areas: [] },
    items: [],
    detail: null,
  };

  const els = {
    alert: document.getElementById('ordenesServicioAlert'),
    stats: document.getElementById('ordenesServicioStats'),
    status: document.getElementById('ordenesServicioStatus'),
    provider: document.getElementById('ordenesServicioProvider'),
    area: document.getElementById('ordenesServicioArea'),
    date: document.getElementById('ordenesServicioDate'),
    search: document.getElementById('ordenesServicioSearch'),
    clear: document.getElementById('ordenesServicioClear'),
    apply: document.getElementById('ordenesServicioApply'),
    refresh: document.getElementById('ordenesServicioRefresh'),
    newBtn: document.getElementById('ordenesServicioNew'),
    count: document.getElementById('ordenesServicioCount'),
    table: document.getElementById('ordenesServicioTable'),
    cards: document.getElementById('ordenesServicioCards'),
    empty: document.getElementById('ordenesServicioEmpty'),
    modal: document.getElementById('ordenesServicioModal'),
    modalTitle: document.getElementById('ordenesServicioModalTitle'),
    form: document.getElementById('ordenesServicioForm'),
    detailModal: document.getElementById('ordenesServicioDetailModal'),
    detailTitle: document.getElementById('ordenesServicioDetailTitle'),
    detailBody: document.getElementById('ordenesServicioDetailBody'),
    qrModal: document.getElementById('ordenesServicioQrModal'),
    qrTitle: document.getElementById('ordenesServicioQrTitle'),
    qrBody: document.getElementById('ordenesServicioQrBody'),
  };

  function escapeHtml(value = '') {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function label(term, fallback = term) {
    return window.OSGateLabels?.label?.(term, state.mode) || fallback;
  }

  function qrPreview(payload) {
    return `https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=${encodeURIComponent(payload || '')}`;
  }

  function showAlert(message = '', type = 'success') {
    if (!els.alert) return;
    if (!message) {
      els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
      els.alert.textContent = '';
      return;
    }
    els.alert.className = 'rounded-2xl px-4 py-3 text-sm ' + (
      type === 'error'
        ? 'border border-rose-200 bg-rose-50 text-rose-700'
        : 'border border-emerald-200 bg-emerald-50 text-emerald-700'
    );
    els.alert.textContent = message;
    els.alert.classList.remove('hidden');
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
    if (!res.ok || json.ok === false) {
      throw new Error(json.error || 'No se pudo procesar la solicitud.');
    }
    return json;
  }

  function openModal(modal) {
    modal?.classList.remove('hidden');
    modal?.classList.add('flex');
    document.body.style.overflow = 'hidden';
    syncOverlay();
  }

  function closeModal(modal) {
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    document.body.style.overflow = '';
    syncOverlay();
  }

  function statusText(value = '') {
    const map = {
      programada: label('scheduled', 'Programada'),
      en_proceso: label('in_progress', 'En proceso'),
      pendiente_validacion: label('pending_validation', 'Pendiente validación'),
      cerrada: label('closed', 'Cerrada'),
      cancelada: label('cancelled', 'Cancelada'),
      baja: 'Baja',
      media: 'Media',
      alta: 'Alta',
      critica: 'Crítica',
      autorizado: label('authorized', 'Autorizado'),
      pendiente: label('pending', 'Pendiente'),
      bloqueado: label('blocked', 'Bloqueado'),
      documento_vencido: label('expired_document', 'Documento vencido'),
      fuera_de_horario: label('out_of_hours', 'Fuera de horario'),
    };
    return map[String(value || '').toLowerCase()] || value || '—';
  }

  function badgeClass(value = '') {
    const status = String(value || '').toLowerCase();
    if (status === 'bloqueado' || status === 'cancelada' || status === 'critica') return 'border-rose-100 bg-rose-50 text-rose-700';
    if (status === 'pendiente' || status === 'pendiente_validacion' || status === 'documento_vencido' || status === 'fuera_de_horario' || status === 'alta') return 'border-amber-100 bg-amber-50 text-amber-700';
    if (status === 'cerrada' || status === 'autorizado' || status === 'programada') return 'border-emerald-100 bg-emerald-50 text-emerald-700';
    if (status === 'en_proceso') return 'border-sky-100 bg-sky-50 text-sky-700';
    return 'border-slate-200 bg-slate-100 text-slate-600';
  }

  function optionHtml(items, firstLabel, textFn, selected = '') {
    return `<option value="">${escapeHtml(firstLabel)}</option>` + items.map((item) => {
      const text = textFn(item);
      return `<option value="${escapeHtml(item.id)}" ${String(item.id) === String(selected) ? 'selected' : ''}>${escapeHtml(text)}</option>`;
    }).join('');
  }

  function fillFilters() {
    if (els.provider) {
      els.provider.innerHTML = optionHtml(state.meta.proveedores || [], 'Todos', (p) => p.tipo_servicio ? `${p.nombre} · ${p.tipo_servicio}` : p.nombre, els.provider.value);
    }
    if (els.area) {
      els.area.innerHTML = optionHtml(state.meta.areas || [], `Todas las ${label('areas', 'áreas').toLowerCase()}`, (a) => a.codigo ? `${a.nombre} · ${a.codigo}` : a.nombre, els.area.value);
    }
  }

  function fillFormSelects(order = {}) {
    const provider = els.form?.querySelector('[name="proveedor_id"]');
    const person = els.form?.querySelector('[name="persona_recurrente_id"]');
    const area = els.form?.querySelector('[name="area_id"]');
    if (provider) {
      provider.innerHTML = optionHtml(state.meta.proveedores || [], 'Sin proveedor', (p) => {
        const status = p.cumplimiento_label ? ` · ${p.cumplimiento_label}` : '';
        return `${p.nombre}${status}`;
      }, order.proveedor_id || '');
    }
    if (person) {
      person.innerHTML = optionHtml(state.meta.personas || [], 'Sin persona asignada', (p) => {
        const detail = [p.empresa, p.area_nombre, p.cumplimiento?.cumplimiento_label].filter(Boolean).join(' · ');
        return `${p.nombre}${detail ? ` · ${detail}` : ''}`;
      }, order.persona_recurrente_id || '');
    }
    if (area) {
      area.innerHTML = optionHtml(state.meta.areas || [], 'Sin área', (a) => a.codigo ? `${a.nombre} · ${a.codigo}` : a.nombre, order.area_id || '');
    }
  }

  async function loadMeta() {
    const json = await fetchJSON(`${API}?action=meta`);
    const data = json.data || {};
    state.meta = data;
    state.mode = data.context?.modo_operacion || state.mode;
    fillFilters();
    window.OSGateLabels?.apply?.(root, state.mode);
  }

  function currentParams() {
    const params = new URLSearchParams({ action: 'list' });
    if (els.status?.value) params.set('estatus', els.status.value);
    if (els.provider?.value) params.set('proveedor_id', els.provider.value);
    if (els.area?.value) params.set('area_id', els.area.value);
    if (els.date?.value) params.set('fecha', els.date.value);
    if (els.search?.value?.trim()) params.set('q', els.search.value.trim());
    return params;
  }

  function renderStats(summary = {}) {
    if (!els.stats) return;
    const cards = [
      ['Programadas', summary.programada || 0, 'text-emerald-600'],
      ['En proceso', summary.en_proceso || 0, 'text-sky-600'],
      ['Pendientes validación', summary.pendiente_validacion || 0, 'text-amber-600'],
      ['Cerradas', summary.cerrada || 0, 'text-slate-700'],
      ['Canceladas', summary.cancelada || 0, 'text-rose-600'],
    ];
    els.stats.innerHTML = cards.map(([title, value, cls]) => `
      <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">${escapeHtml(title)}</div>
        <div class="mt-2 text-3xl font-bold ${cls}">${escapeHtml(value)}</div>
      </div>
    `).join('');
  }

  function rowActions(item) {
    const status = String(item.estatus || '');
    const canClose = ['en_proceso', 'pendiente_validacion'].includes(status);
    const canCancel = !['cerrada', 'cancelada'].includes(status);
    return `
      <div class="flex flex-wrap gap-2">
        <button type="button" data-os-detail="${item.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Detalle</button>
        <button type="button" data-os-edit="${item.id}" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">Editar</button>
        <button type="button" data-os-qr="${item.id}" class="rounded-xl bg-[#2E5D73] px-3 py-2 text-xs font-semibold text-white">QR</button>
        ${canClose ? `<button type="button" data-os-close="${item.id}" class="rounded-xl border border-emerald-200 px-3 py-2 text-xs font-semibold text-emerald-700">Cerrar</button>` : ''}
        ${canCancel ? `<button type="button" data-os-cancel="${item.id}" class="rounded-xl border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-700">Cancelar</button>` : ''}
      </div>
    `;
  }

  function renderRows(items) {
    state.items = items;
    if (els.count) els.count.textContent = `${items.length} ${items.length === 1 ? 'registro' : 'registros'}`;
    els.empty?.classList.toggle('hidden', items.length > 0);

    if (els.table) {
      els.table.innerHTML = items.map((item) => `
        <tr class="align-top hover:bg-slate-50/70">
          <td class="px-4 py-3">
            <div class="font-semibold text-slate-900">${escapeHtml(item.folio)}</div>
            <div class="mt-1 text-xs text-slate-400">${escapeHtml(item.tipo_servicio || '')}</div>
          </td>
          <td class="px-4 py-3 text-slate-600">${escapeHtml(item.proveedor_nombre || 'Sin proveedor')}</td>
          <td class="px-4 py-3 text-slate-600">${escapeHtml(item.persona_nombre || 'Sin persona')}</td>
          <td class="px-4 py-3 text-slate-600">${escapeHtml(item.area_nombre || 'Sin área')}</td>
          <td class="px-4 py-3 text-slate-600">${escapeHtml(item.fecha_programada)}<div class="text-xs text-slate-400">${escapeHtml([item.hora_inicio, item.hora_fin].filter(Boolean).join(' - ') || 'Sin horario')}</div></td>
          <td class="px-4 py-3"><span class="rounded-full border px-2.5 py-1 text-xs font-semibold ${badgeClass(item.estatus)}">${escapeHtml(statusText(item.estatus))}</span><div class="mt-2"><span class="rounded-full border px-2.5 py-1 text-xs font-semibold ${badgeClass(item.prioridad)}">${escapeHtml(statusText(item.prioridad))}</span></div></td>
          <td class="px-4 py-3"><span class="rounded-full border px-2.5 py-1 text-xs font-semibold ${badgeClass(item.cumplimiento?.cumplimiento_estado)}">${escapeHtml(item.cumplimiento?.cumplimiento_label || 'Autorizado')}</span></td>
          <td class="px-4 py-3">${rowActions(item)}</td>
        </tr>
      `).join('');
    }

    if (els.cards) {
      els.cards.innerHTML = items.map((item) => `
        <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="font-semibold text-slate-900">${escapeHtml(item.folio)}</div>
              <div class="mt-1 text-sm text-slate-600">${escapeHtml(item.tipo_servicio || 'Sin tipo')}</div>
            </div>
            <span class="rounded-full border px-2.5 py-1 text-xs font-semibold ${badgeClass(item.estatus)}">${escapeHtml(statusText(item.estatus))}</span>
          </div>
          <div class="mt-3 grid gap-2 text-sm text-slate-600">
            <div>${escapeHtml(item.proveedor_nombre || 'Sin proveedor')}</div>
            <div>${escapeHtml(item.persona_nombre || 'Sin persona')} · ${escapeHtml(item.area_nombre || 'Sin área')}</div>
            <div>${escapeHtml(item.fecha_programada)} · ${escapeHtml([item.hora_inicio, item.hora_fin].filter(Boolean).join(' - ') || 'Sin horario')}</div>
          </div>
          <div class="mt-3">${rowActions(item)}</div>
        </article>
      `).join('');
    }
  }

  async function loadList() {
    showAlert('');
    const json = await fetchJSON(`${API}?${currentParams().toString()}`);
    const data = json.data || {};
    state.mode = data.context?.modo_operacion || state.mode;
    renderStats(data.summary || {});
    renderRows(data.items || []);
    window.OSGateLabels?.apply?.(root, state.mode);
  }

  function orderById(id) {
    return state.items.find((item) => Number(item.id) === Number(id)) || null;
  }

  function openForm(order = null) {
    els.form?.reset();
    const statusField = els.form?.elements.estatus || null;
    const statusReadonly = els.form?.querySelector('[data-os-status-readonly]') || null;
    if (statusField) {
      statusField.disabled = false;
      statusField.classList.remove('hidden');
    }
    if (statusReadonly) {
      statusReadonly.textContent = '';
      statusReadonly.classList.add('hidden');
    }
    fillFormSelects(order || {});
    if (els.modalTitle) els.modalTitle.textContent = order ? `Editar ${order.folio}` : 'Nueva orden';
    if (order && els.form) {
      Object.entries(order).forEach(([key, value]) => {
        const field = els.form.elements[key];
        if (field) field.value = value ?? '';
      });
      if (els.form.elements.hora_inicio && order.hora_inicio) els.form.elements.hora_inicio.value = String(order.hora_inicio).slice(0, 5);
      if (els.form.elements.hora_fin && order.hora_fin) els.form.elements.hora_fin.value = String(order.hora_fin).slice(0, 5);
      if (['cerrada', 'cancelada'].includes(String(order.estatus || ''))) {
        if (statusField) {
          statusField.disabled = true;
          statusField.classList.add('hidden');
        }
        if (statusReadonly) {
          statusReadonly.textContent = statusText(order.estatus);
          statusReadonly.classList.remove('hidden');
        }
      }
    } else if (els.form?.elements.fecha_programada) {
      els.form.elements.fecha_programada.value = new Date().toISOString().slice(0, 10);
    }
    openModal(els.modal);
  }

  function showQr(order) {
    if (!order || !els.qrBody) return;
    if (els.qrTitle) els.qrTitle.textContent = `QR ${order.folio}`;
    els.qrBody.innerHTML = `
      <div class="flex flex-col items-center gap-4">
        <img src="${qrPreview(order.qr_payload)}" alt="QR ${escapeHtml(order.folio)}" class="h-72 w-72 max-w-full rounded-2xl object-contain" />
        <div class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-3">
          <div class="text-xs font-semibold uppercase tracking-wide text-slate-400">Payload</div>
          <div class="mt-2 break-all text-sm font-semibold text-slate-700">${escapeHtml(order.qr_payload)}</div>
        </div>
        <button type="button" data-os-regenerate="${order.id}" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">Regenerar QR</button>
      </div>
    `;
    openModal(els.qrModal);
  }

  async function loadDetail(id) {
    const json = await fetchJSON(`${API}?action=detail&id=${encodeURIComponent(id)}`);
    state.detail = json.data || {};
    const order = state.detail.orden || {};
    if (els.detailTitle) els.detailTitle.textContent = `Detalle ${order.folio || ''}`;
    if (els.detailBody) {
      const evidencias = state.detail.evidencias || [];
      const eventos = state.detail.eventos || [];
      els.detailBody.innerHTML = `
        <div class="grid gap-4 lg:grid-cols-[1fr_0.9fr]">
          <section class="space-y-4">
            <div class="rounded-2xl border border-slate-200 p-4">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="text-xs uppercase tracking-wide text-slate-400">${escapeHtml(order.tipo_servicio || '')}</div>
                  <h3 class="mt-1 text-xl font-semibold text-slate-900">${escapeHtml(order.folio || '')}</h3>
                </div>
                <div class="flex flex-wrap gap-2">
                  <span class="rounded-full border px-3 py-1 text-xs font-semibold ${badgeClass(order.estatus)}">${escapeHtml(statusText(order.estatus))}</span>
                  <span class="rounded-full border px-3 py-1 text-xs font-semibold ${badgeClass(order.cumplimiento?.cumplimiento_estado)}">${escapeHtml(order.cumplimiento?.cumplimiento_label || 'Autorizado')}</span>
                </div>
              </div>
              <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><span class="text-slate-400">Proveedor</span><div class="font-semibold text-slate-800">${escapeHtml(order.proveedor_nombre || 'Sin proveedor')}</div></div>
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><span class="text-slate-400">Persona</span><div class="font-semibold text-slate-800">${escapeHtml(order.persona_nombre || 'Sin persona')}</div></div>
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><span class="text-slate-400">Área</span><div class="font-semibold text-slate-800">${escapeHtml(order.area_nombre || 'Sin área')}</div></div>
                <div class="rounded-xl bg-slate-50 p-3 text-sm"><span class="text-slate-400">Horario</span><div class="font-semibold text-slate-800">${escapeHtml(order.fecha_programada || '')} · ${escapeHtml([order.hora_inicio, order.hora_fin].filter(Boolean).join(' - ') || 'Sin horario')}</div></div>
              </div>
              <p class="mt-4 whitespace-pre-wrap text-sm text-slate-600">${escapeHtml(order.descripcion || 'Sin descripción')}</p>
              ${order.observaciones_cierre ? `<div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">${escapeHtml(order.observaciones_cierre)}</div>` : ''}
            </div>
            <form data-os-evidence-form="${order.id}" class="rounded-2xl border border-slate-200 p-4">
              <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500" data-os-label="evidence">Evidencias</h4>
              <div class="mt-3 grid gap-3 md:grid-cols-3">
                <select name="tipo" class="rounded-xl border border-slate-200 px-3 py-2 text-sm"><option value="general">General</option><option value="antes">Antes</option><option value="despues">Después</option><option value="cierre">Cierre</option></select>
                <input name="archivo_url" placeholder="URL / ruta" class="rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                <button class="app-admin-primary rounded-xl px-3 py-2 text-sm font-semibold" type="submit">Agregar evidencia</button>
              </div>
              <textarea name="descripcion" rows="2" placeholder="Descripción" class="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"></textarea>
              <div class="mt-3 space-y-2">
                ${evidencias.map((ev) => `<div class="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600"><b>${escapeHtml(ev.tipo || 'general')}</b> · ${escapeHtml(ev.descripcion || ev.archivo_url || 'Sin descripción')}</div>`).join('') || '<div class="text-sm text-slate-400">Sin evidencias capturadas.</div>'}
              </div>
            </form>
          </section>
          <section class="space-y-4">
            <div class="rounded-2xl border border-slate-200 p-4">
              <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Bitácora</h4>
              <div class="mt-3 space-y-2">
                ${eventos.map((event) => `<div class="rounded-xl bg-slate-50 px-3 py-2 text-sm"><div class="font-semibold text-slate-800">${escapeHtml(event.tipo_evento || '')}</div><div class="text-xs text-slate-400">${escapeHtml(event.fecha_hora || '')} · ${escapeHtml(event.resultado || '')}</div><div class="mt-1 text-slate-600">${escapeHtml(event.observaciones || '')}</div></div>`).join('') || '<div class="text-sm text-slate-400">Sin eventos registrados.</div>'}
              </div>
            </div>
            ${['en_proceso', 'pendiente_validacion'].includes(String(order.estatus || '')) ? `<form data-os-close-form="${order.id}" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4"><h4 class="text-sm font-semibold text-emerald-800">Cerrar orden</h4><textarea name="observaciones_cierre" rows="3" class="mt-3 w-full rounded-xl border border-emerald-200 px-3 py-2 text-sm" placeholder="Observaciones de cierre"></textarea><button class="mt-3 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">Cerrar orden</button></form>` : ''}
          </section>
        </div>
      `;
    }
    openModal(els.detailModal);
  }

  async function postAction(fd) {
    return withPendingAction({}, () => fetchJSON(API, { method: 'POST', body: fd }));
  }

  async function init() {
    try {
      await loadMeta();
      await loadList();
    } catch (error) {
      showAlert(error.message || 'No se pudo cargar órdenes de servicio.', 'error');
    }
  }

  els.form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const fd = new FormData(els.form);
    fd.set('action', 'save');
    try {
      const json = await postAction(fd);
      closeModal(els.modal);
      showAlert(json.message || 'Orden guardada.');
      await loadList();
    } catch (error) {
      showAlert(error.message || 'No se pudo guardar la orden.', 'error');
    }
  });

  root.addEventListener('click', async (event) => {
    const target = event.target.closest('button');
    if (!target) return;
    if (target.matches('[data-os-close-modal]')) closeModal(els.modal);
    if (target.matches('[data-os-close-detail]')) closeModal(els.detailModal);
    if (target.matches('[data-os-close-qr]')) closeModal(els.qrModal);
    if (target === els.newBtn) openForm();
    if (target === els.refresh || target === els.apply) await loadList();
    if (target === els.clear) {
      if (els.status) els.status.value = '';
      if (els.provider) els.provider.value = '';
      if (els.area) els.area.value = '';
      if (els.date) els.date.value = '';
      if (els.search) els.search.value = '';
      await loadList();
    }
    const editId = target.dataset.osEdit;
    if (editId) openForm(orderById(editId));
    const detailId = target.dataset.osDetail;
    if (detailId) await loadDetail(detailId);
    const qrId = target.dataset.osQr;
    if (qrId) showQr(orderById(qrId));
    const cancelId = target.dataset.osCancel;
    if (cancelId && window.confirm('¿Cancelar esta orden de servicio?')) {
      const fd = new FormData();
      fd.set('action', 'cancel');
      fd.set('id', cancelId);
      try {
        const json = await postAction(fd);
        showAlert(json.message || 'Orden cancelada.');
        await loadList();
      } catch (error) {
        showAlert(error.message || 'No se pudo cancelar.', 'error');
      }
    }
    const closeId = target.dataset.osClose;
    if (closeId) await loadDetail(closeId);
    const regenerateId = target.dataset.osRegenerate;
    if (regenerateId) {
      const fd = new FormData();
      fd.set('action', 'regenerate_qr');
      fd.set('id', regenerateId);
      try {
        const json = await postAction(fd);
        showAlert(json.message || 'QR regenerado.');
        closeModal(els.qrModal);
        await loadList();
      } catch (error) {
        showAlert(error.message || 'No se pudo regenerar QR.', 'error');
      }
    }
  });

  els.detailBody?.addEventListener('submit', async (event) => {
    const evidenceForm = event.target.closest('[data-os-evidence-form]');
    const closeForm = event.target.closest('[data-os-close-form]');
    if (!evidenceForm && !closeForm) return;
    event.preventDefault();
    const fd = new FormData(event.target);
    if (evidenceForm) {
      fd.set('action', 'save_evidence');
      fd.set('orden_id', evidenceForm.dataset.osEvidenceForm);
    } else {
      fd.set('action', 'close');
      fd.set('id', closeForm.dataset.osCloseForm);
    }
    try {
      const json = await postAction(fd);
      showAlert(json.message || 'Actualizado.');
      await loadList();
      await loadDetail(fd.get('id') || fd.get('orden_id'));
    } catch (error) {
      showAlert(error.message || 'No se pudo actualizar.', 'error');
    }
  });

  init();
})();
