(function () {
  const view = document.getElementById('incidenciasView');
  if (!view) return;

  const API_BASE = '/admin_residencial/php/api/';
  const API_INCIDENCIAS = API_BASE + 'incidencias.php';
  const API_UNIDADES = API_BASE + 'unidades.php';
  const API_GUARDIAS = API_BASE + 'guardias.php';

  const els = {
    alert: document.getElementById('incidenciasAlert'),
    list: document.getElementById('incidenciasList'),
    filterEstado: document.getElementById('filterEstado'),
    btnAdd: document.getElementById('btnAddIncidencia'),
    search: document.getElementById('searchIncidencias'),
    chartCard: document.getElementById('incidenciasChartCard'),
    chartBars: document.getElementById('incidenciasChartBars'),
    chartTotals: document.getElementById('incidenciasChartTotals'),

    modalAdd: document.getElementById('incidenciaModal'),
    modalEdit: document.getElementById('incidenciaEditModal'),

    formAdd: document.getElementById('incidenciaForm'),
    formEdit: document.getElementById('incidenciaEditForm'),

    btnCloseAdd: document.getElementById('btnCloseIncidenciaModal'),
    btnCloseEdit: document.getElementById('btnCloseEditIncModal'),
    btnCancelAdd: document.getElementById('btnCancelIncidenciaAdd'),
    btnCancelEdit: document.getElementById('btnCancelIncidenciaEdit'),

    addError: document.getElementById('incidenciaAddError'),
    editError: document.getElementById('incidenciaEditError'),
    scopeWrap: document.getElementById('incidenciaScopeWrap'),
    unitField: document.getElementById('incResidentialUnitField'),
    scopeHint: document.getElementById('incidenciaScopeHint'),
  };

  if (!els.list) return;

  const state = {
    incidencias: [],
    unidades: [],
    guardias: [],
    meta: { modo_operacion: 'residencial', areas: [], personas: [], visitantes: [], permisos: [] },
    filter: 'todas',
    search: '',
    page: 1,
    perPage: 3,
  };
  const withPendingAction = window.AdminResidencialDashboard?.withPendingAction || (async (_options, task) => task());

  function escapeHtml(value = '') {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function humanizeValue(value, fallback = '—') {
    const raw = String(value ?? '').trim();
    if (!raw) return fallback;
    return raw
      .replaceAll('_', ' ')
      .replace(/\b\w/g, (letter) => letter.toUpperCase());
  }

  function currentMode() {
    const metaMode = String(state.meta?.modo_operacion || '').trim();
    const dashboardMode = String(window.AdminResidencialDashboard?.getOperationalMode?.() || '').trim();
    const dashboardPreset = String(window.AdminResidencialDashboard?.getServiceProfile?.()?.preset_servicio || '').trim();
    const mode = metaMode && metaMode !== 'residencial' ? metaMode : dashboardMode;
    return mode && mode !== 'residencial' ? mode : dashboardPreset || mode || 'residencial';
  }

  function isOperationalMode() {
    return String(currentMode() || 'residencial') !== 'residencial';
  }

  function label(term, fallback = '') {
    return window.OSGateLabels?.label?.(term, currentMode()) || fallback || term;
  }

  function incidentLower() {
    return String(label('incident', 'incidencia')).toLowerCase();
  }

  function applyStaticLabels() {
    window.OSGateLabels?.apply?.(view, currentMode());
    if (els.search) {
      els.search.placeholder = isOperationalMode()
        ? `Buscar por título, ${String(label('area', 'área')).toLowerCase()} o ${String(label('responsible', 'responsable')).toLowerCase()}...`
        : 'Buscar por título, unidad o residente...';
    }
    if (els.btnAdd) {
      els.btnAdd.textContent = isOperationalMode() ? '+ Reportar incidente' : '+ Reportar';
    }
    const chartTitle = els.chartCard?.querySelector('.mt-1.text-base');
    if (chartTitle) chartTitle.textContent = `Estado de ${String(label('incidents', 'incidencias')).toLowerCase()}`;
  }

  function showAlert(msg, type = 'info') {
    if (!els.alert) return;
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error'
        ? 'bg-rose-100 text-rose-700'
        : 'bg-sky-100 text-sky-700');
    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');

    setTimeout(() => {
      els.alert.classList.add('hidden');
    }, 3500);
  }

  function hideAlert() {
    els.alert?.classList.add('hidden');
  }

  function showFormError(el, msg) {
    if (!el) {
      showAlert(msg, 'error');
      return;
    }
    el.textContent = msg;
    el.classList.remove('hidden');
  }

  function clearFormError(el) {
    if (!el) return;
    el.textContent = '';
    el.classList.add('hidden');
  }

  function sanitizeUserMessage(message, fallback = 'No se pudo completar la solicitud.') {
    let msg = String(message || '').trim();
    if (!msg) return fallback;
    msg = msg
      .replace(/\s*\(HTTP\s+\d+(?:\s*\(redirect\))?\)\.?/gi, '')
      .replace(/\bHTTP\s+\d+(?:\s*\(redirect\))?:\s*/gi, '')
      .replace(/\s*Debug:\s*[^.]+\.?/gi, '')
      .trim();
    return msg || fallback;
  }

  async function fetchJSON(url, options = {}) {
    const { headers = {}, ...rest } = options;
    const res = await fetch(url, { credentials: 'same-origin', ...rest, headers: { Accept: 'application/json', ...headers } });
    const text = await res.text();

    let json;
    try {
      json = JSON.parse(text);
    } catch {
      console.error('[admin_residencial/incidencias] Respuesta inválida', { url, status: res.status, text });
      throw new Error(sanitizeUserMessage(text || 'Respuesta inválida del servidor'));
    }

    if (!json.ok) {
      console.error('[admin_residencial/incidencias] API error', { url, status: res.status, json });
      throw new Error(sanitizeUserMessage(json.error || 'Error'));
    }
    return json;
  }

  const api = {
    incidencias: {
      list: () => fetchJSON(API_INCIDENCIAS),
      save: (fd) =>
        fetchJSON(API_INCIDENCIAS, {
          method: 'POST',
          body: fd,
        }),
      remove: (id) => {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);
        return fetchJSON(API_INCIDENCIAS, {
          method: 'POST',
          body: fd,
        });
      },
    },
    unidades: {
      list: () => fetchJSON(API_UNIDADES),
    },
    guardias: {
      list: () => fetchJSON(API_GUARDIAS),
    },
    meta: {
      get: () => fetchJSON(API_INCIDENCIAS + '?action=meta'),
    },
  };

  const badgeEstado = (e) =>
    e === 'cerrada'
      ? 'bg-emerald-100 text-emerald-700'
      : e === 'en_proceso'
      ? 'bg-amber-100 text-amber-700'
      : 'bg-rose-100 text-rose-700';

  const badgePrioridad = (p) =>
    p === 'alta'
      ? 'bg-rose-100 text-rose-700'
      : p === 'media'
      ? 'bg-amber-100 text-amber-700'
      : 'bg-slate-100 text-slate-700';

  function fmtDate(s) {
    if (!s) return '—';
    const d = new Date(s);
    return Number.isNaN(d.getTime()) ? s : d.toLocaleString();
  }

  function paginate(arr, page, per) {
    return arr.slice((page - 1) * per, page * per);
  }

  function computeStatusCounts(items) {
    const counts = { abierta: 0, en_proceso: 0, cerrada: 0 };
    (items || []).forEach((i) => {
      const k = String(i.estado || '').trim();
      if (k === 'abierta' || k === 'en_proceso' || k === 'cerrada') counts[k] += 1;
    });
    return counts;
  }

  function renderStatusChart(counts) {
    if (!els.chartCard || !els.chartBars || !els.chartTotals) return;

    const abierta = Number(counts?.abierta || 0);
    const enProceso = Number(counts?.en_proceso || 0);
    const cerrada = Number(counts?.cerrada || 0);
    const total = abierta + enProceso + cerrada;

    els.chartTotals.textContent = total ? `${total} total` : 'Sin datos';
    els.chartBars.innerHTML = '';

    if (!total) {
      els.chartBars.innerHTML = `
        <div class="sm:col-span-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
          Sin datos para graficar con los filtros actuales.
        </div>
      `;
      return;
    }

    const max = Math.max(abierta, enProceso, cerrada, 1);
    const items = [
      { key: 'abierta', label: 'Abiertas', value: abierta, cls: 'bg-rose-100 text-rose-700', bar: 'bg-rose-500' },
      { key: 'en_proceso', label: 'En proceso', value: enProceso, cls: 'bg-amber-100 text-amber-700', bar: 'bg-amber-500' },
      { key: 'cerrada', label: 'Cerradas', value: cerrada, cls: 'bg-emerald-100 text-emerald-700', bar: 'bg-emerald-500' },
    ];

    items.forEach((it) => {
      const pct = Math.round((it.value / max) * 100);
      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-200 bg-white p-4';
      card.innerHTML = `
        <div class="flex items-center justify-between">
          <div class="text-sm font-semibold text-slate-800">${escapeHtml(it.label)}</div>
          <span class="inline-flex items-center rounded-full px-3 py-1 text-xs ${it.cls}">${it.value}</span>
        </div>
        <div class="mt-3 h-2 w-full rounded-full bg-slate-100 overflow-hidden">
          <div class="h-full ${it.bar}" style="width:${pct}%"></div>
        </div>
      `;
      els.chartBars.appendChild(card);
    });
  }

  function openIncidenciaDetail(inc) {
    const operational = isOperationalMode();
    const modal = document.createElement('div');
    modal.className = 'app-admin-modal-overlay fixed inset-0 z-[9998] bg-black/60 p-3 md:p-4 backdrop-blur-sm';
    modal.innerHTML = `
      <div class="flex min-h-full items-center justify-center">
        <div class="app-admin-modal-card w-full max-w-lg rounded-3xl bg-white shadow-2xl">
          <div class="app-admin-modal-header flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div class="text-lg font-semibold text-slate-900">Detalle de ${escapeHtml(incidentLower())}</div>
            <button type="button" class="js-inc-detail-close h-11 w-11 rounded-full border border-slate-200 bg-slate-50 text-xl text-slate-500 hover:bg-slate-100">×</button>
          </div>
          <div class="app-admin-modal-body space-y-4 p-5">
            <div>
              <div class="text-xs text-slate-500">Título</div>
              <div class="text-xl font-semibold text-slate-900">${escapeHtml(inc.titulo || '—')}</div>
            </div>
            <div class="flex flex-wrap gap-2">
              <span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(inc.prioridad)}">${escapeHtml(`Prioridad: ${humanizeValue(inc.prioridad, '')}`.trim())}</span>
              <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(inc.estado)}">${escapeHtml(`Estado: ${humanizeValue(inc.estado, '')}`.trim())}</span>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
              <div><div class="text-xs text-slate-500">Tipo</div><div class="text-sm text-slate-700">${escapeHtml(humanizeValue(inc.tipo))}</div></div>
              <div><div class="text-xs text-slate-500">Fecha</div><div class="text-sm text-slate-700">${escapeHtml(fmtDate(inc.created_at))}</div></div>
              <div><div class="text-xs text-slate-500">${escapeHtml(operational ? label('area', 'Área') : label('unit', 'Unidad'))}</div><div class="text-sm text-slate-700">${escapeHtml(inc.area_nombre || inc.unidad_clave || 'General')}</div></div>
              <div><div class="text-xs text-slate-500">Contexto</div><div class="text-sm text-slate-700">${escapeHtml(inc.unidad_clave || inc.area_nombre || 'General')}</div></div>
              <div><div class="text-xs text-slate-500">Relación</div><div class="text-sm text-slate-700">${escapeHtml(inc.residente_nombre || inc.persona_recurrente_nombre || inc.visitante_rapido_nombre || '—')}</div></div>
              <div><div class="text-xs text-slate-500">${escapeHtml(label('guard', 'Guardia'))}</div><div class="text-sm text-slate-700">${escapeHtml(inc.guardia_nombre || '—')}</div></div>
            </div>
            <div>
              <div class="text-xs text-slate-500">Descripción</div>
              <div class="mt-1 rounded-2xl bg-slate-50 px-3 py-3 text-sm text-slate-700 whitespace-pre-wrap">${escapeHtml(inc.descripcion || 'Sin descripción')}</div>
            </div>
            <div class="flex flex-wrap justify-end gap-2">
              <button type="button" class="js-inc-detail-edit rounded-full bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-200">Editar</button>
              <button type="button" class="js-inc-detail-delete rounded-full bg-rose-100 px-4 py-2 text-sm text-rose-700 hover:bg-rose-200">Eliminar</button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';
    const close = () => {
      document.body.style.overflow = '';
      modal.remove();
    };
    modal.addEventListener('click', (event) => {
      if (event.target === modal || event.target.closest('.js-inc-detail-close')) close();
    });
    modal.querySelector('.js-inc-detail-edit')?.addEventListener('click', () => {
      close();
      openEditModal(inc);
    });
    modal.querySelector('.js-inc-detail-delete')?.addEventListener('click', () => {
      close();
      deleteIncidencia(inc.id);
    });
  }

  function ensureUiHelpers() {
    if (document.getElementById('incidenciasUiLayer')) return;

    const layer = document.createElement('div');
    layer.id = 'incidenciasUiLayer';
    layer.innerHTML = `
      <div id="friendlyConfirmIncidencia"
           class="app-admin-modal-overlay hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="app-admin-modal-card w-full max-w-md rounded-3xl bg-white shadow-2xl">
          <div class="app-admin-modal-body p-6">
            <div class="flex items-start gap-4">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold">
                !
              </div>
              <div class="flex-1">
                <h3 id="friendlyConfirmIncidenciaTitle" class="text-xl font-semibold text-slate-900">
                  Confirmar acción
                </h3>
                <p id="friendlyConfirmIncidenciaMessage" class="mt-2 text-sm leading-6 text-slate-600">
                  ¿Deseas continuar?
                </p>
              </div>
            </div>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
              <button id="friendlyConfirmIncidenciaCancel"
                      type="button"
                      class="rounded-full bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                Cancelar
              </button>
              <button id="friendlyConfirmIncidenciaAccept"
                      type="button"
                      class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                Eliminar
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(layer);
  }

  function showConfirmIncidencia({
    title = 'Confirmar acción',
    message = '¿Deseas continuar?',
    acceptText = 'Aceptar',
    cancelText = 'Cancelar',
  } = {}) {
    ensureUiHelpers();

    return new Promise((resolve) => {
      const modal = document.getElementById('friendlyConfirmIncidencia');
      const titleEl = document.getElementById('friendlyConfirmIncidenciaTitle');
      const messageEl = document.getElementById('friendlyConfirmIncidenciaMessage');
      const acceptBtn = document.getElementById('friendlyConfirmIncidenciaAccept');
      const cancelBtn = document.getElementById('friendlyConfirmIncidenciaCancel');

      titleEl.textContent = title;
      messageEl.textContent = message;
      acceptBtn.textContent = acceptText;
      cancelBtn.textContent = cancelText;

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      const cleanup = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        acceptBtn.onclick = null;
        cancelBtn.onclick = null;
        modal.onclick = null;
        document.removeEventListener('keydown', onKeydown);
      };

      const onKeydown = (e) => {
        if (e.key === 'Escape') {
          cleanup();
          resolve(false);
        }
      };

      acceptBtn.onclick = () => {
        cleanup();
        resolve(true);
      };

      cancelBtn.onclick = () => {
        cleanup();
        resolve(false);
      };

      modal.onclick = (e) => {
        if (e.target === modal) {
          cleanup();
          resolve(false);
        }
      };

      document.addEventListener('keydown', onKeydown);
    });
  }

  async function loadAll() {
    hideAlert();
    try {
      const [i, u, g, m] = await Promise.all([
        api.incidencias.list(),
        api.unidades.list(),
        api.guardias.list(),
        api.meta.get(),
      ]);

      state.incidencias = i.incidencias || [];
      state.unidades = u.unidades || [];
      state.guardias = g.guardias || [];
      state.meta = m.data || state.meta;

      applyStaticLabels();
      render();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  function setTypeOptions() {
    const select = els.formAdd?.querySelector('[name="tipo"]');
    if (!select) return;
    const options = isOperationalMode()
      ? [
          ['seguridad', 'Seguridad'],
          ['robo', 'Robo'],
          ['conflicto', 'Conflicto'],
          ['salida_sin_permiso', 'Salida sin permiso'],
          ['visitante_sin_ine', 'Visitante sin INE'],
          ['material_no_coincide', 'Material no coincide'],
          ['evento_general', 'Evento general'],
          ['otro', 'Otro'],
        ]
      : [
          ['seguridad', 'Seguridad'],
          ['servicio', 'Servicio'],
          ['vecino', 'Vecino'],
          ['infraestructura', 'Infraestructura'],
          ['otro', 'Otro'],
        ];
    select.innerHTML = options.map(([value, text]) => `<option value="${escapeHtml(value)}">${escapeHtml(text)}</option>`).join('');
    select.value = isOperationalMode() ? 'evento_general' : 'seguridad';
  }

  function openAddModal() {
    if (!els.modalAdd || !els.formAdd) return;

    clearFormError(els.addError);
    els.formAdd.reset();
    setTypeOptions();

    const selU = els.formAdd.querySelector('[name="unidad_id"]');
    const operationalWrap = document.getElementById('incOperationalFields');
    const isOperational = isOperationalMode();
    const addTitle = els.modalAdd.querySelector('.app-admin-modal-header h2');
    if (addTitle) addTitle.textContent = label('report_incident', 'Reportar incidencia');
    const scopeInputs = els.formAdd.querySelectorAll('[name="incidencia_scope"]');
    scopeInputs.forEach((input) => {
      input.checked = input.value === 'unidad';
    });
    els.scopeWrap?.classList.toggle('hidden', isOperational);
    if (selU) {
      els.unitField?.classList.toggle('hidden', isOperational);
      selU.innerHTML = '';
      selU.innerHTML = '<option value="">Selecciona unidad</option>';
      state.unidades.forEach((u) => {
        selU.innerHTML += `<option value="${u.id}">${escapeHtml(u.clave)}</option>`;
      });
    }
    syncResidentialScopeUi();

    if (operationalWrap) {
      operationalWrap.classList.toggle('hidden', !isOperational);
      const fillSelect = (name, rows, labelKey = 'nombre', placeholder = 'Sin selección') => {
        const select = els.formAdd.querySelector(`[name="${name}"]`);
        if (!select) return;
        select.innerHTML = `<option value="">${placeholder}</option>` + rows.map((row) => {
          const label = row[labelKey] || row.nombre_visitante || row.tipo_movimiento || row.name || '—';
          return `<option value="${row.id}">${escapeHtml(label)}</option>`;
        }).join('');
      };
      fillSelect('area_id', state.meta.areas || [], 'nombre', 'Sin área');
      fillSelect('persona_recurrente_id', state.meta.personas || [], 'nombre', 'Sin persona');
      fillSelect('visitante_rapido_id', state.meta.visitantes || [], 'nombre_visitante', 'Sin visitante');
      fillSelect('permiso_material_id', state.meta.permisos || [], 'tipo_movimiento', 'Sin permiso');
    }

    els.modalAdd.classList.remove('hidden');
  }

  function currentIncidenciaScope() {
    const checked = els.formAdd?.querySelector('[name="incidencia_scope"]:checked');
    return checked?.value === 'general' ? 'general' : 'unidad';
  }

  function syncResidentialScopeUi() {
    if (!els.formAdd) return;
    const isOperational = isOperationalMode();
    if (isOperational) return;
    const isGeneral = currentIncidenciaScope() === 'general';
    els.unitField?.classList.toggle('hidden', isGeneral);
    const selU = els.formAdd.querySelector('[name="unidad_id"]');
    if (selU) {
      selU.disabled = isGeneral;
      if (isGeneral) selU.value = '';
    }
    if (els.scopeHint) {
      els.scopeHint.textContent = isGeneral
        ? 'Se registrará como incidencia general del residencial, sin ligarla a una casa.'
        : 'Selecciona la casa relacionada con la incidencia.';
    }
  }

  function closeAddModal() {
    els.modalAdd?.classList.add('hidden');
    clearFormError(els.addError);
  }

  function openEditModal(inc) {
    if (!els.modalEdit || !els.formEdit) return;

    clearFormError(els.editError);
    els.formEdit.reset();
    const editTitle = els.modalEdit.querySelector('.app-admin-modal-header h2');
    if (editTitle) editTitle.textContent = label('update_incident', 'Actualizar incidencia');
    const guardLabel = els.formEdit.querySelector('label');
    if (guardLabel) guardLabel.textContent = `Asignar ${String(label('guard', 'guardia')).toLowerCase()}`;

    const idInput = els.formEdit.querySelector('[name="id"]');
    if (idInput) idInput.value = inc.id;

    const selG = els.formEdit.querySelector('[name="guardia_id"]');
    if (selG) {
      selG.innerHTML = `<option value="">-- Sin asignar --</option>`;
      state.guardias.forEach((g) => {
        selG.innerHTML += `<option value="${g.id}">${escapeHtml(g.name)}</option>`;
      });
      selG.value = inc.guardia_id || '';
    }

    const selEstado = els.formEdit.querySelector('[name="estado"]');
    if (selEstado) selEstado.value = inc.estado || 'abierta';

    const selPri = els.formEdit.querySelector('[name="prioridad"]');
    if (selPri) selPri.value = inc.prioridad || 'media';

    els.modalEdit.classList.remove('hidden');
  }

  function closeEditModal() {
    els.modalEdit?.classList.add('hidden');
    clearFormError(els.editError);
  }

  async function deleteIncidencia(id) {
    const ok = await showConfirmIncidencia({
      title: label('delete_incident', 'Eliminar incidencia'),
      message: `Esta acción eliminará el ${incidentLower()} de forma permanente. ¿Deseas continuar?`,
      acceptText: 'Sí, eliminar',
      cancelText: 'Cancelar',
    });

    if (!ok) return;

    try {
      const resp = await api.incidencias.remove(id);
      showAlert(resp.message || (isOperationalMode() ? 'Incidente eliminado.' : 'Incidencia eliminada.'));
      await loadAll();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  function validateAddForm(fd) {
    const isOperational = isOperationalMode();
    const scope = String(fd.get('incidencia_scope') || 'unidad');
    const unidadId = Number(fd.get('unidad_id') || 0);
    const tipo = String(fd.get('tipo') || '').trim();
    const titulo = String(fd.get('titulo') || '').trim();
    const descripcion = String(fd.get('descripcion') || '').trim();
    const prioridad = String(fd.get('prioridad') || '').trim();

    if (!isOperational && scope !== 'general' && unidadId <= 0) throw new Error('Debes seleccionar una unidad.');
    if (!['seguridad', 'servicio', 'vecino', 'infraestructura', 'otro', 'robo', 'conflicto', 'salida_sin_permiso', 'visitante_sin_ine', 'material_no_coincide', 'evento_general'].includes(tipo)) {
      throw new Error('Tipo inválido.');
    }
    if (titulo.length < 3) throw new Error('El título debe tener al menos 3 caracteres.');
    if (titulo.length > 120) throw new Error('El título no puede exceder 120 caracteres.');
    if (descripcion.length < 5) throw new Error('La descripción debe tener al menos 5 caracteres.');
    if (descripcion.length > 1000) throw new Error('La descripción no puede exceder 1000 caracteres.');
    if (!['baja', 'media', 'alta'].includes(prioridad)) throw new Error('Prioridad inválida.');
  }

  function validateEditForm(fd) {
    const estado = String(fd.get('estado') || '').trim();
    const prioridad = String(fd.get('prioridad') || '').trim();

    if (!['abierta', 'en_proceso', 'cerrada'].includes(estado)) {
      throw new Error('Estado inválido.');
    }
    if (!['baja', 'media', 'alta'].includes(prioridad)) {
      throw new Error('Prioridad inválida.');
    }

    const guardiaId = String(fd.get('guardia_id') || '').trim();
    if (guardiaId !== '' && Number(guardiaId) <= 0) {
      throw new Error('Guardia inválido.');
    }
  }

  function render() {
    els.list.innerHTML = '';
    const operational = isOperationalMode();

    const filtradas = state.incidencias.filter((i) => {
      const byEstado =
        state.filter === 'todas' ? true : i.estado === state.filter;

      const q = state.search;
      const bySearch = !q
        ? true
        : (
            (i.titulo || '').toLowerCase().includes(q) ||
            (i.descripcion || '').toLowerCase().includes(q) ||
            (i.unidad_clave || '').toLowerCase().includes(q) ||
            (i.area_nombre || '').toLowerCase().includes(q) ||
            (i.residente_nombre || '').toLowerCase().includes(q) ||
            (i.persona_recurrente_nombre || '').toLowerCase().includes(q) ||
            (i.visitante_rapido_nombre || '').toLowerCase().includes(q)
          );

      return byEstado && bySearch;
    });

    renderStatusChart(computeStatusCounts(filtradas));

    const totalPages = Math.max(1, Math.ceil(filtradas.length / state.perPage));
    if (state.page > totalPages) state.page = 1;

    if (filtradas.length) {
      const header = document.createElement('div');
      header.className =
        'hidden md:grid grid-cols-9 gap-4 rounded-2xl border border-slate-200 bg-white/70 px-5 py-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 shadow-sm';

      header.innerHTML = `
        <div class="col-span-2">Título</div>
        <div>${escapeHtml(operational ? label('area', 'Área') : label('unit', 'Unidad'))}</div>
        <div class="col-span-2">${escapeHtml(operational ? label('responsible', 'Responsable') : label('person', 'Residente'))}</div>
        <div>${escapeHtml(label('guard', 'Guardia'))}</div>
        <div>Prioridad</div>
        <div>Estado</div>
        <div class="text-right">Acciones</div>
      `;
      els.list.appendChild(header);
    }

    const visibles = paginate(filtradas, state.page, state.perPage);

    if (!visibles.length) {
      els.list.innerHTML += `
        <div class="rounded-2xl border bg-slate-50 p-4 text-sm text-slate-600">
          ${escapeHtml(label('no_incidents', 'No hay incidencias para mostrar.'))}
        </div>`;
      return;
    }

    visibles.forEach((i) => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm';

      card.innerHTML = `
        <div class="space-y-3 md:hidden">
          <div>
            <div class="text-xs text-slate-500">Título</div>
            <div class="font-semibold">${escapeHtml(i.titulo || '—')}</div>
          </div>

          <div class="app-mobile-secondary"><span class="text-xs text-slate-500">Descripción</span><div class="whitespace-pre-wrap">${escapeHtml(i.descripcion || 'Sin descripción')}</div></div>
          <div><span class="text-xs text-slate-500">Tipo</span><div>${escapeHtml(humanizeValue(i.tipo))}</div></div>
          <div><span class="text-xs text-slate-500">Fecha</span><div>${escapeHtml(fmtDate(i.created_at))}</div></div>
          <div class="app-mobile-secondary"><span class="text-xs text-slate-500">${escapeHtml(operational ? label('area', 'Área') : label('unit', 'Unidad'))}</span><div>${escapeHtml(i.area_nombre || i.unidad_clave || 'General')}</div></div>
          <div class="app-mobile-secondary"><span class="text-xs text-slate-500">Contexto</span><div>${escapeHtml(i.unidad_clave || i.area_nombre || 'General')}</div></div>
          <div class="app-mobile-secondary"><span class="text-xs text-slate-500">${escapeHtml(operational ? label('responsible', 'Responsable') : 'Relación')}</span><div>${escapeHtml(i.residente_nombre || i.persona_recurrente_nombre || i.visitante_rapido_nombre || '—')}</div></div>
          <div class="app-mobile-secondary"><span class="text-xs text-slate-500">${escapeHtml(label('guard', 'Guardia'))}</span><div>${escapeHtml(i.guardia_nombre || '—')}</div></div>

          <div class="flex gap-2">
            <span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)}">${escapeHtml(`Prioridad: ${humanizeValue(i.prioridad, '')}`.trim())}</span>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)}">${escapeHtml(`Estado: ${humanizeValue(i.estado, '')}`.trim())}</span>
          </div>

          <div class="flex gap-2 pt-2">
            <button data-edit="${i.id}" class="app-mobile-actions flex-1 rounded-full bg-slate-100 px-3 py-2 text-sm text-slate-700 hover:bg-slate-200">Editar</button>
            <button data-del="${i.id}" class="app-mobile-actions flex-1 rounded-full bg-rose-100 px-3 py-2 text-sm text-rose-700 hover:bg-rose-200">Eliminar</button>
            <button data-more="${i.id}" class="app-mobile-more hidden flex-1 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Ver más</button>
          </div>
        </div>

        <div class="hidden md:grid grid-cols-9 gap-4 items-center">
          <div class="col-span-2">
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Título</div>
            <div class="font-semibold text-slate-800">${escapeHtml(i.titulo || '—')}</div>
            <div class="mt-2 text-[11px] uppercase tracking-[0.12em] text-slate-400">Descripción</div>
            <div class="text-xs text-slate-600 whitespace-pre-wrap">${escapeHtml(i.descripcion || 'Sin descripción')}</div>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">Contexto</div>
            <div>${escapeHtml(i.area_nombre || i.unidad_clave || 'General')}</div>
            <div class="mt-2 text-[11px] uppercase tracking-[0.12em] text-slate-400">Tipo</div>
            <div class="text-xs text-slate-600">${escapeHtml(humanizeValue(i.tipo))}</div>
          </div>
          <div class="col-span-2">
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">${escapeHtml(operational ? label('responsible', 'Responsable') : 'Relación')}</div>
            <div>${escapeHtml(i.residente_nombre || i.persona_recurrente_nombre || i.visitante_rapido_nombre || '—')}</div>
          </div>
          <div>
            <div class="text-[11px] uppercase tracking-[0.12em] text-slate-400">${escapeHtml(label('guard', 'Guardia'))}</div>
            <div>${escapeHtml(i.guardia_nombre || '—')}</div>
          </div>
          <div><span class="px-3 py-1 text-xs rounded-full ${badgePrioridad(i.prioridad)}">${escapeHtml(`Prioridad: ${humanizeValue(i.prioridad, '')}`.trim())}</span></div>
          <div>
            <span class="px-3 py-1 text-xs rounded-full ${badgeEstado(i.estado)}">${escapeHtml(`Estado: ${humanizeValue(i.estado, '')}`.trim())}</span>
            <div class="mt-2 text-[11px] uppercase tracking-[0.12em] text-slate-400">Fecha</div>
            <div class="text-[11px] text-slate-500">${escapeHtml(fmtDate(i.created_at))}</div>
          </div>
          <div class="flex justify-end gap-2">
            <button data-edit="${i.id}" class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-200">Editar</button>
            <button data-del="${i.id}" class="inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-200">Eliminar</button>
          </div>
        </div>
      `;

      els.list.appendChild(card);
    });

    if (totalPages > 1) {
      const nav = document.createElement('div');
      nav.className = 'flex justify-end gap-2 mt-4';

      nav.innerHTML = `
        <button ${state.page === 1 ? 'disabled' : ''}
          class="px-3 py-1 rounded border text-sm disabled:opacity-40"
          data-page="${state.page - 1}">
          ◀
        </button>

        ${Array.from({ length: totalPages }).map((_, i) => `
          <button data-page="${i + 1}"
            class="px-3 py-1 rounded text-sm ${
              state.page === i + 1 ? 'bg-slate-800 text-white' : 'border'
            }">
            ${i + 1}
          </button>
        `).join('')}

        <button ${state.page === totalPages ? 'disabled' : ''}
          class="px-3 py-1 rounded border text-sm disabled:opacity-40"
          data-page="${state.page + 1}">
          ▶
        </button>
      `;

      els.list.appendChild(nav);
    }
  }

  els.filterEstado?.addEventListener('change', (e) => {
    state.filter = e.target.value;
    state.page = 1;
    render();
  });

  els.search?.addEventListener('input', (e) => {
    state.search = (e.target.value || '').toLowerCase().trim();
    state.page = 1;
    render();
  });

  els.btnAdd?.addEventListener('click', openAddModal);
  els.formAdd?.addEventListener('change', (e) => {
    if (e.target?.name === 'incidencia_scope') {
      syncResidentialScopeUi();
    }
  });

  els.btnCloseAdd?.addEventListener('click', closeAddModal);
  els.btnCancelAdd?.addEventListener('click', closeAddModal);
  els.btnCloseEdit?.addEventListener('click', closeEditModal);
  els.btnCancelEdit?.addEventListener('click', closeEditModal);

  els.modalAdd?.addEventListener('click', (e) => {
    if (e.target === els.modalAdd) closeAddModal();
  });

  els.modalEdit?.addEventListener('click', (e) => {
    if (e.target === els.modalEdit) closeEditModal();
  });

  els.list.addEventListener('click', (e) => {
    const pageBtn = e.target.closest('[data-page]');
    const edit = e.target.closest('[data-edit]');
    const del = e.target.closest('[data-del]');

    if (pageBtn) {
      state.page = Number(pageBtn.dataset.page);
      render();
      return;
    }

    if (edit) {
      const inc = state.incidencias.find((x) => String(x.id) === String(edit.dataset.edit));
      if (inc) openEditModal(inc);
      return;
    }

    if (del) {
      deleteIncidencia(del.dataset.del);
    }
  });

  els.formAdd?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFormError(els.addError);
    const submitBtn = e.submitter || els.formAdd?.querySelector('button[type="submit"]');
    await withPendingAction({
      button: submitBtn,
      scope: els.formAdd,
      label: 'Guardando...',
      lock: [els.btnCancelAdd, els.btnCloseAdd].filter(Boolean),
    }, async () => {
      try {
        const fd = new FormData(els.formAdd);
        if (!isOperationalMode()) {
          if (String(fd.get('incidencia_scope') || 'unidad') === 'general') {
            fd.delete('unidad_id');
          }
          fd.delete('area_id');
          fd.delete('persona_recurrente_id');
          fd.delete('visitante_rapido_id');
          fd.delete('permiso_material_id');
          fd.delete('origen_tipo');
        } else {
          fd.delete('unidad_id');
          fd.delete('incidencia_scope');
        }
        validateAddForm(fd);

        const resp = await api.incidencias.save(fd);
        closeAddModal();
        showAlert(resp.message || (isOperationalMode() ? 'Incidente registrado.' : 'Incidencia registrada.'));
        await loadAll();
      } catch (e2) {
        showFormError(els.addError, e2.message || `Error al registrar ${incidentLower()}.`);
      }
    });
  });

  els.formEdit?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFormError(els.editError);
    const submitBtn = e.submitter || els.formEdit?.querySelector('button[type="submit"]');
    await withPendingAction({
      button: submitBtn,
      scope: els.formEdit,
      label: 'Guardando...',
      lock: [els.btnCancelEdit, els.btnCloseEdit].filter(Boolean),
    }, async () => {
      try {
        const fd = new FormData(els.formEdit);
        validateEditForm(fd);

        const resp = await api.incidencias.save(fd);
        closeEditModal();
        showAlert(resp.message || (isOperationalMode() ? 'Incidente actualizado.' : 'Incidencia actualizada.'));
        await loadAll();
      } catch (e2) {
        showFormError(els.editError, e2.message || `Error al actualizar ${incidentLower()}.`);
      }
    });
  });

  ensureUiHelpers();
  applyStaticLabels();
  loadAll();
})();
