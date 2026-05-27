(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.accesos = function ({ API, fetchJSON, escapeHtml }) {
    const els = {
      codigo: document.getElementById('codigo'),
      alert: document.getElementById('accesosAlert'),
      modal: document.getElementById('accessActionModal'),
      modalTitle: document.getElementById('accessActionTitle'),
      modalStatus: document.getElementById('accessActionStatus'),
      modalBody: document.getElementById('accessActionBody'),
      modalClose: document.getElementById('btnCloseAccessActionModal'),
      dynamicForm: document.getElementById('accessActionDynamicForm'),
      pinWrap: document.getElementById('accessPinWrap'),
      pin: document.getElementById('accessPin'),
      evidenceWrap: document.getElementById('accessEvidenceWrap'),
      evidence: document.getElementById('accessEvidence'),
      notes: document.getElementById('accessNotes'),

      btnBuscar: document.getElementById('btnBuscar'),
      btnEntrada: document.getElementById('btnConfirmarEntrada'),
      btnSalida: document.getElementById('btnConfirmarSalida'),
      actionButtonsWrap: document.getElementById('accessActionButtons'),

      btnOpenCamera: document.getElementById('btnOpenCamera'),
      cameraModal: document.getElementById('cameraModal'),
      cameraHint: document.getElementById('cameraHint'),
      cameraSupportNote: document.getElementById('cameraSupportNote'),
      video: document.getElementById('video'),
      btnRetryCamera: document.getElementById('btnRetryCamera'),
      btnCloseCamera: document.getElementById('btnCloseCamera'),
      btnCloseCameraFooter: document.getElementById('btnCloseCameraFooter'),

      residentSection: document.getElementById('residentDirectSection'),
      residentSearch: document.getElementById('residentDirectSearch'),
      residentAlert: document.getElementById('residentDirectAlert'),
      residentResults: document.getElementById('residentDirectResults'),
      btnResidentSearch: document.getElementById('btnBuscarResidenteDirecto'),

      hist: document.getElementById('accesosHist'),
      histPagination: document.getElementById('accesosHistPagination'),
      btnRefrescar: document.getElementById('btnRefrescarHist'),

      soundOk: document.getElementById('soundOk'),
      soundNo: document.getElementById('soundNo'),
    };

    const state = {
      destroyed: false,
      current: null,
      currentKind: null,
      currentResident: null,
      residentSearchItems: [],
      stream: null,
      detector: null,
      scannerMode: null,
      jsQrLoadPromise: null,
      canvas: null,
      canvasCtx: null,
      scanning: false,
      lastScanAt: 0,
      loadingSearch: false,
      loadingRegister: false,
      histItems: [],
      histPage: 1,
      histPerPage: 3,
      rafId: null,
      searchToken: 0,
      registerToken: 0,
      actionSource: null,
      currentEval: null,
      operationalMode: window.GuardiaDashboard?.getOperationalMode?.() || 'residencial',
      autoScanIntent: '',
    };

    const SCAN_INTERVAL_MS = 240;
    const AUTO_SCAN_KEY = 'guardia:accesos:auto_scan';

    function isAlive() {
      return !state.destroyed;
    }

    function safeText(value, fallback = '—') {
      const v = String(value ?? '').trim();
      return escapeHtml(v || fallback);
    }

    function isOperational() {
      return state.operationalMode && state.operationalMode !== 'residencial';
    }

    function alertMsg(msg = '', type = 'error') {
      if (!els.alert || !isAlive()) return;
      if (!msg) {
        els.alert.className = 'hidden mt-3 rounded-xl border px-3 py-2 text-xs';
        els.alert.textContent = '';
        return;
      }

      els.alert.className =
        type === 'success'
          ? 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700'
          : 'mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700';
      els.alert.textContent = msg;
      els.alert.classList.remove('hidden');
    }

    function residentAlertMsg(msg = '', type = 'error') {
      if (!els.residentAlert || !isAlive()) return;
      if (!msg) {
        els.residentAlert.classList.add('hidden');
        els.residentAlert.textContent = '';
        return;
      }
      els.residentAlert.className =
        type === 'success'
          ? 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700'
          : 'mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700';
      els.residentAlert.textContent = msg;
      els.residentAlert.classList.remove('hidden');
    }

    function feedback(ok) {
      try {
        if (ok) els.soundOk?.play?.();
        else els.soundNo?.play?.();
      } catch (_) {}
      if (navigator.vibrate) navigator.vibrate(ok ? 120 : [100, 60, 100]);
    }

    function getAppRoot() {
      const path = window.location.pathname || '';
      const idx = path.indexOf('/guardia/');
      return idx === -1 ? '' : path.slice(0, idx);
    }

    function showCameraRetry(show) {
      els.btnRetryCamera?.classList.toggle('hidden', !show);
      if (els.btnCloseCamera) {
        els.btnCloseCamera.classList.toggle('sm:col-span-2', !!show);
        els.btnCloseCamera.classList.toggle('sm:col-span-1', !show);
      }
    }

    function setCameraState(kind, message, note = '') {
      if (els.cameraHint) {
        els.cameraHint.className = `mt-2 text-xs ${
          kind === 'error'
            ? 'text-rose-700'
            : kind === 'success'
            ? 'text-emerald-700'
            : 'text-slate-500'
        }`;
        els.cameraHint.textContent = message;
      }

      if (els.cameraSupportNote) {
        els.cameraSupportNote.className = `mt-2 text-[11px] ${
          kind === 'error' ? 'text-rose-500' : 'text-slate-400'
        }`;
        els.cameraSupportNote.textContent = note || 'Si no abre o no detecta el QR, puedes ingresar el código manualmente.';
      }

      showCameraRetry(kind === 'error');
    }

    function isSecureCameraContext() {
      if (window.isSecureContext) return true;
      const host = window.location.hostname || '';
      return host === 'localhost' || host === '127.0.0.1';
    }

    async function getCameraPermissionState() {
      if (!navigator.permissions?.query) return 'unknown';
      try {
        const status = await navigator.permissions.query({ name: 'camera' });
        return status?.state || 'unknown';
      } catch (_) {
        return 'unknown';
      }
    }

    function mapCameraError(error) {
      const name = String(error?.name || '').toLowerCase();
      if (name === 'notallowederror' || name === 'securityerror') {
        return {
          message: 'Activa el permiso de cámara en tu navegador.',
          note: 'Revisa permisos del sitio y vuelve a intentar desde HTTPS.',
        };
      }
      if (name === 'notfounderror' || name === 'overconstrainederror') {
        return {
          message: 'No encontramos una cámara disponible en este dispositivo.',
          note: 'Prueba desde otro dispositivo o usa el código manual.',
        };
      }
      if (name === 'notreadableerror' || name === 'aborterror') {
        return {
          message: 'La cámara está ocupada o no se pudo iniciar.',
          note: 'Cierra otras apps que usen cámara y vuelve a intentar.',
        };
      }
      return {
        message: 'No se pudo abrir la cámara.',
        note: 'Si el problema continúa, usa el código manual.',
      };
    }

    async function loadJsQrFallback() {
      if (window.jsQR) return window.jsQR;
      if (state.jsQrLoadPromise) return state.jsQrLoadPromise;

      state.jsQrLoadPromise = new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-jsqr-fallback="1"]');
        if (existing) {
          existing.addEventListener('load', () => resolve(window.jsQR), { once: true });
          existing.addEventListener('error', () => reject(new Error('No se pudo cargar el lector QR.')), { once: true });
          return;
        }

        const script = document.createElement('script');
        script.src = `${getAppRoot()}/assets/vendor/jsQR.js`;
        script.defer = true;
        script.dataset.jsqrFallback = '1';
        script.onload = () => {
          if (window.jsQR) resolve(window.jsQR);
          else reject(new Error('El lector QR no quedó disponible.'));
        };
        script.onerror = () => reject(new Error('No se pudo cargar el lector QR local.'));
        document.body.appendChild(script);
      }).catch((error) => {
        state.jsQrLoadPromise = null;
        throw error;
      });

      return state.jsQrLoadPromise;
    }

    async function prepareScannerEngine() {
      if ('BarcodeDetector' in window) {
        try {
          state.detector = new BarcodeDetector({ formats: ['qr_code'] });
          state.scannerMode = 'native';
          return;
        } catch (_) {
          state.detector = null;
          state.scannerMode = null;
        }
      }

      await loadJsQrFallback();
      state.scannerMode = 'jsqr';
      if (!state.canvas) state.canvas = document.createElement('canvas');
      if (!state.canvasCtx) {
        state.canvasCtx = state.canvas.getContext('2d', { willReadFrequently: true }) || state.canvas.getContext('2d');
      }
    }

    async function detectCode() {
      if (!els.video) return '';

      if (state.scannerMode === 'native' && state.detector) {
        const codes = await state.detector.detect(els.video);
        return codes?.[0]?.rawValue || '';
      }

      if (state.scannerMode === 'jsqr' && window.jsQR && state.canvas && state.canvasCtx) {
        const width = els.video.videoWidth || 0;
        const height = els.video.videoHeight || 0;
        if (!width || !height) return '';

        state.canvas.width = width;
        state.canvas.height = height;
        state.canvasCtx.drawImage(els.video, 0, 0, width, height);
        const imageData = state.canvasCtx.getImageData(0, 0, width, height);
        const qr = window.jsQR(imageData.data, width, height, { inversionAttempts: 'dontInvert' });
        return qr?.data || '';
      }

      return '';
    }

    function setButtonsDisabled(disabled) {
      if (els.btnEntrada) els.btnEntrada.disabled = disabled;
      if (els.btnSalida) els.btnSalida.disabled = disabled;
    }

    function setActionsVisible(show) {
      els.actionButtonsWrap?.classList.toggle('hidden', !show);
    }

    function canRegisterCurrentAction() {
      if (state.actionSource === 'resident') {
        return !!state.currentResident?.access?.allow_direct_access;
      }

      if (state.actionSource !== 'code' || !state.current) return false;

      if (state.currentKind === 'persona_recurrente') return state.current?.puede_ingresar !== false;
      if (state.currentKind === 'orden_servicio') return state.current?.puede_ingresar !== false;

      return !!state.currentEval?.permitido;
    }

    function enableActions(on) {
      const visible = canRegisterCurrentAction();
      const disabled = !on || state.loadingSearch || state.loadingRegister || !visible;
      setActionsVisible(visible);
      setButtonsDisabled(disabled);
    }

    function setLoadingSearch(on, text = 'Buscando…') {
      state.loadingSearch = on;
      if (els.btnBuscar) {
        els.btnBuscar.disabled = on;
        els.btnBuscar.textContent = on ? text : 'Buscar';
      }
      if (els.codigo) els.codigo.disabled = on;
      enableActions(!!state.current || !!state.currentResident);
    }

    function setLoadingRegister(on, tipo = 'entrada') {
      state.loadingRegister = on;
      if (els.btnEntrada) {
        els.btnEntrada.textContent = on && tipo === 'entrada' ? 'Registrando…' : 'Registrar entrada';
      }
      if (els.btnSalida) {
        els.btnSalida.textContent = on && tipo === 'salida' ? 'Registrando…' : 'Registrar salida';
      }
      enableActions(!!state.current || !!state.currentResident);
    }

    function updateStatusUI(type, text) {
      const classes =
        type === 'ok'
          ? 'bg-emerald-100 text-emerald-700'
          : type === 'error'
          ? 'bg-rose-100 text-rose-700'
          : 'bg-slate-100 text-slate-700';

      if (els.modalStatus) {
        els.modalStatus.className = `mt-1 inline-flex rounded-full px-3 py-1 text-xs ${classes}`;
        els.modalStatus.textContent = text;
      }
    }

    function openActionModal(title = 'Validación de acceso') {
      if (els.modalTitle) els.modalTitle.textContent = title;
      if (window.OSGateModal?.open && els.modal) {
        window.OSGateModal.open(els.modal);
      } else {
        els.modal?.classList.remove('hidden');
      }
      document.body.style.overflow = 'hidden';
    }

    function closeActionModal() {
      if (window.OSGateModal?.close && els.modal) {
        window.OSGateModal.close(els.modal);
      } else {
        els.modal?.classList.add('hidden');
      }
      document.body.style.overflow = '';
      state.actionSource = null;
      state.current = null;
      state.currentKind = null;
      state.currentResident = null;
      state.currentEval = null;
      resetDynamicForm();
      if (els.modalBody) {
        els.modalBody.innerHTML = 'Ingresa un código para validar el acceso.';
      }
      updateStatusUI('idle', 'Esperando validación');
      enableActions(false);
    }

    function resetDynamicForm() {
      els.dynamicForm?.classList.add('hidden');
      els.pinWrap?.classList.add('hidden');
      els.evidenceWrap?.classList.add('hidden');
      if (els.pin) els.pin.value = '';
      if (els.evidence) els.evidence.value = '';
      if (els.notes) els.notes.value = '';
    }

    function showDynamicForm(config = {}) {
      const { pin = false, evidence = false } = config;
      els.dynamicForm?.classList.remove('hidden');
      els.pinWrap?.classList.toggle('hidden', !pin);
      els.evidenceWrap?.classList.toggle('hidden', !evidence);
      if (!pin && els.pin) els.pin.value = '';
      if (!evidence && els.evidence) els.evidence.value = '';
    }

    function resetResultArea() {
      state.current = null;
      state.currentKind = null;
      state.currentResident = null;
      state.actionSource = null;
      state.currentEval = null;
      resetDynamicForm();
      enableActions(false);

      if (els.modalBody) {
        els.modalBody.textContent = 'Ingresa un código para validar el acceso.';
      }
      updateStatusUI('idle', 'Esperando validación');
    }

    function renderResidentSearchResults(items) {
      if (!els.residentResults || !isAlive()) return;
      state.residentSearchItems = items.slice();

      if (!items.length) {
        els.residentResults.innerHTML = `<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No encontramos residentes con esa búsqueda.</div>`;
        return;
      }

      els.residentResults.innerHTML = items.map((item, index) => {
        const allow = !!item.access?.allow_direct_access;
        return `
          <button type="button" data-resident-select="${index}" class="w-full rounded-2xl border border-slate-200 bg-slate-50 p-4 text-left hover:bg-white">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="font-semibold text-slate-800">${safeText(item.name)}</div>
                <div class="mt-1 text-xs text-slate-500">${safeText(item.unidad_clave)} · ${safeText(item.telefono || item.email || 'Sin contacto')}</div>
              </div>
              <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-medium ${allow ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}">
                ${safeText(item.access?.label || 'Sin estado')}
              </span>
            </div>
            <div class="mt-2 text-xs ${allow ? 'text-emerald-700' : 'text-rose-700'}">${safeText(item.access?.reason || 'Sin detalles')}</div>
          </button>
        `;
      }).join('');
    }

    function renderResidentResult(resident) {
      if (!els.modalBody || !isAlive()) return;
      const access = resident?.access || {};
      const allow = !!access.allow_direct_access;
      els.modalBody.innerHTML = `
        <div class="rounded-2xl border ${allow ? 'border-emerald-200 bg-emerald-50/40' : 'border-rose-200 bg-rose-50/40'} p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="text-lg font-semibold text-slate-800">${safeText(resident?.name)}</div>
              <div class="mt-1 text-sm text-slate-600">${safeText(resident?.unidad_clave)} · ${safeText(resident?.telefono || resident?.email || 'Sin contacto')}</div>
            </div>
            <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium ${allow ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">${safeText(access.label || 'Sin estado')}</span>
          </div>
          <div class="mt-3 rounded-xl ${allow ? 'border border-emerald-200 bg-white/80 text-emerald-700' : 'border border-rose-200 bg-white/80 text-rose-700'} px-3 py-2 text-sm">
            ${safeText(access.reason || 'Sin detalles')}
          </div>
        </div>
      `;
      resetDynamicForm();
    }

    function renderVisitResult(visita, ev) {
      const permitido = !!ev?.permitido;
      if (!els.modalBody) return;
      els.modalBody.innerHTML = `
        <div class="space-y-4">
          <div class="font-semibold text-base ${permitido ? 'text-emerald-700' : 'text-rose-700'}">
            ${permitido ? '✔ Acceso permitido' : '✖ Acceso denegado'}
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Visitante</div><div class="mt-1 font-medium text-slate-800">${safeText(visita?.nombre_visitante)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Unidad</div><div class="mt-1 font-medium text-slate-800">${safeText(visita?.unidad_clave)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Residente</div><div class="mt-1 font-medium text-slate-800">${safeText(visita?.residente_nombre)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Placa</div><div class="mt-1 font-medium text-slate-800">${safeText(visita?.placa_vehiculo)}</div></div>
          </div>
          ${permitido ? '' : `<div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">${safeText(ev?.motivo || 'Acceso denegado.', '')}</div>`}
        </div>
      `;
      resetDynamicForm();
    }

    function renderPersonaResult(persona) {
      if (!els.modalBody) return;
      const compliance = persona?.cumplimiento || {};
      const status = compliance.cumplimiento_estado || persona?.cumplimiento_estado || 'autorizado';
      const label = compliance.cumplimiento_label || persona?.cumplimiento_label || 'Autorizado';
      const motivo = compliance.cumplimiento_motivo || persona?.cumplimiento_motivo || '';
      const provider = compliance.proveedor_nombre || persona?.proveedor_nombre || '';
      const canEnter = persona?.puede_ingresar !== false;
      const needsConfirmation = persona?.requiere_confirmacion === true;
      const badgeClass = status === 'bloqueado'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : (status === 'autorizado'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
          : 'border-amber-200 bg-amber-50 text-amber-700');
      els.modalBody.innerHTML = `
        <div class="space-y-4">
          <div class="font-semibold text-base text-slate-800">Personal recurrente identificado</div>
          <div class="flex gap-4">
            <div class="h-20 w-20 overflow-hidden rounded-2xl bg-slate-100">
              ${persona?.foto_url ? `<img src="${escapeHtml(persona.foto_url)}" alt="${safeText(persona.nombre)}" class="h-full w-full object-cover" loading="lazy" />` : `<div class="flex h-full items-center justify-center text-xs text-slate-400">Sin foto</div>`}
            </div>
            <div class="min-w-0">
              <div class="text-lg font-semibold text-slate-800">${safeText(persona?.nombre)}</div>
              <div class="mt-1 text-sm text-slate-600">${safeText(persona?.empresa || 'Sin empresa')} · ${safeText(persona?.puesto || 'Sin puesto')}</div>
              <div class="mt-1 text-sm text-slate-500">Área: <b>${safeText(persona?.area_nombre || 'Sin área')}</b> · ${safeText(persona?.telefono || 'Sin teléfono')}</div>
              <div class="mt-3 flex flex-wrap gap-2">
                <div class="inline-flex rounded-full px-3 py-1 text-xs ${persona?.esta_dentro ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-700'}">${persona?.esta_dentro ? 'Actualmente dentro' : 'Actualmente fuera'}</div>
                <div class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${badgeClass}">${safeText(label)}</div>
              </div>
            </div>
          </div>
          ${provider ? `<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">Proveedor: <b>${safeText(provider)}</b></div>` : ''}
          ${motivo ? `<div class="rounded-xl border ${canEnter ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-rose-200 bg-rose-50 text-rose-700'} px-3 py-2 text-sm">${safeText(canEnter && needsConfirmation ? `Acceso con advertencia: ${motivo}` : motivo)}</div>` : ''}
          ${canEnter ? '' : `<div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">Acceso bloqueado. No se puede registrar entrada ni salida.</div>`}
        </div>
      `;
      if (canEnter) {
        showDynamicForm({ pin: true, evidence: false });
      } else {
        resetDynamicForm();
      }
    }

    function renderVisitanteOperativoResult(item) {
      const evalInfo = item?.eval || {};
      if (!els.modalBody) return;
      els.modalBody.innerHTML = `
        <div class="space-y-4">
          <div class="font-semibold text-base ${evalInfo.permitido ? 'text-emerald-700' : 'text-rose-700'}">
            ${evalInfo.permitido ? 'Visitante listo para validación' : 'Visitante con restricciones'}
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Visitante</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.nombre_visitante)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Responsable</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.responsable_nombre)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Área</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.area_nombre)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Placa</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.placa_vehiculo)}</div></div>
          </div>
          <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">${safeText(item?.motivo, '')}</div>
          ${evalInfo.permitido ? '' : `<div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">${safeText(evalInfo.motivo || 'Acceso denegado.', '')}</div>`}
        </div>
      `;
      if (evalInfo.permitido) {
        showDynamicForm({ pin: false, evidence: true });
      } else {
        resetDynamicForm();
      }
    }

    function renderPermisoResult(item) {
      const evalInfo = item?.eval || {};
      if (!els.modalBody) return;
      els.modalBody.innerHTML = `
        <div class="space-y-4">
          <div class="font-semibold text-base ${evalInfo.permitido ? 'text-emerald-700' : 'text-rose-700'}">
            ${evalInfo.permitido ? 'Permiso listo para validación' : 'Permiso con restricciones'}
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Movimiento</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.tipo_movimiento)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Responsable</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.responsable_nombre)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Área</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.area_nombre)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Aprobó</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.aprobado_por_nombre || 'Pendiente')}</div></div>
          </div>
          <div class="space-y-2">
            ${(item?.items || []).map((row) => `<div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">${safeText(row.material_nombre)} · ${safeText(row.cantidad_texto)}</div>`).join('')}
          </div>
          ${evalInfo.permitido ? '' : `<div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">${safeText(evalInfo.motivo || 'Acceso denegado.', '')}</div>`}
        </div>
      `;
      if (evalInfo.permitido) {
        showDynamicForm({ pin: false, evidence: true });
      } else {
        resetDynamicForm();
      }
    }

    function renderOrdenServicioResult(item) {
      if (!els.modalBody) return;
      const schedule = item?.schedule || {};
      const compliance = item?.cumplimiento || {};
      const canEnter = item?.puede_ingresar !== false;
      const needsConfirmation = item?.requiere_confirmacion === true;
      const complianceStatus = compliance.cumplimiento_estado || 'autorizado';
      const badgeClass = complianceStatus === 'bloqueado'
        ? 'border-rose-200 bg-rose-50 text-rose-700'
        : (complianceStatus === 'autorizado'
          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
          : 'border-amber-200 bg-amber-50 text-amber-700');
      const scheduleMessage = schedule.motivo || '';
      els.modalBody.innerHTML = `
        <div class="space-y-4">
          <div class="font-semibold text-base ${canEnter ? 'text-emerald-700' : 'text-rose-700'}">
            ${canEnter ? 'Orden lista para validación' : 'Orden con restricciones'}
          </div>
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 text-sm">
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Folio</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.folio)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Servicio</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.tipo_servicio)}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Proveedor</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.proveedor_nombre || 'Sin proveedor')}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Persona</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.persona_nombre || 'Sin persona')}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Área</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.area_nombre || 'Sin área')}</div></div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><div class="text-xs uppercase tracking-wide text-slate-400">Horario</div><div class="mt-1 font-medium text-slate-800">${safeText(item?.fecha_programada)} · ${safeText([item?.hora_inicio, item?.hora_fin].filter(Boolean).join(' - ') || 'Sin horario')}</div></div>
          </div>
          <div class="flex flex-wrap gap-2">
            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${badgeClass}">${safeText(compliance.cumplimiento_label || 'Autorizado')}</span>
            <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold ${item?.estatus === 'en_proceso' ? 'border-sky-200 bg-sky-50 text-sky-700' : 'border-slate-200 bg-slate-50 text-slate-700'}">${safeText(item?.estatus || 'programada')}</span>
          </div>
          ${scheduleMessage ? `<div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-700">${safeText(scheduleMessage)}</div>` : ''}
          ${compliance.cumplimiento_motivo ? `<div class="rounded-xl border ${canEnter ? 'border-amber-200 bg-amber-50 text-amber-700' : 'border-rose-200 bg-rose-50 text-rose-700'} px-3 py-2 text-sm">${safeText(compliance.cumplimiento_motivo)}</div>` : ''}
          ${needsConfirmation && canEnter ? '<div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">Requiere confirmación manual del operador.</div>' : ''}
          ${canEnter ? '' : '<div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">No se puede registrar entrada ni salida.</div>'}
        </div>
      `;
      if (canEnter) {
        showDynamicForm({ pin: false, evidence: false });
      } else {
        resetDynamicForm();
      }
    }

    async function buscar(code) {
      const cleanCode = String(code || '').trim();
      enableActions(false);
      alertMsg('');

      if (!cleanCode) {
        alertMsg('Ingresa un código para buscar.');
        return;
      }

      const token = ++state.searchToken;
      setLoadingSearch(true);
      updateStatusUI('idle', 'Validando…');

      try {
        const json = await fetchJSON(`${API}accesos.php?action=buscar&code=${encodeURIComponent(cleanCode)}`);
        if (!isAlive() || token !== state.searchToken) return;

        const data = json.data || {};
        state.currentKind = data.kind || 'visita_residencial';
        state.actionSource = 'code';
        state.currentEval = null;
        openActionModal(state.currentKind === 'persona_recurrente' ? 'Acceso de personal recurrente' : (state.currentKind === 'orden_servicio' ? 'Orden de servicio' : 'Validación de acceso'));

        if (state.currentKind === 'persona_recurrente') {
          state.current = data.persona || null;
          feedback(state.current?.puede_ingresar !== false);
          renderPersonaResult(state.current);
          updateStatusUI(
            state.current?.puede_ingresar === false ? 'error' : (state.current?.requiere_confirmacion ? 'idle' : 'ok'),
            state.current?.puede_ingresar === false ? 'Acceso bloqueado' : (state.current?.requiere_confirmacion ? 'Advertencia de cumplimiento' : 'PIN requerido')
          );
        } else if (state.currentKind === 'visitante_rapido') {
          state.current = data.visitante_rapido || null;
          state.currentEval = state.current?.eval || null;
          feedback(!!state.current?.eval?.permitido);
          renderVisitanteOperativoResult(state.current);
          updateStatusUI(state.current?.eval?.permitido ? 'ok' : 'error', state.current?.eval?.permitido ? 'Listo para validar' : 'Con restricciones');
        } else if (state.currentKind === 'permiso_material') {
          state.current = data.permiso_material || null;
          state.currentEval = state.current?.eval || null;
          feedback(!!state.current?.eval?.permitido);
          renderPermisoResult(state.current);
          updateStatusUI(state.current?.eval?.permitido ? 'ok' : 'error', state.current?.eval?.permitido ? 'Listo para validar' : 'Con restricciones');
        } else if (state.currentKind === 'orden_servicio') {
          state.current = data.orden_servicio || null;
          feedback(state.current?.puede_ingresar !== false);
          renderOrdenServicioResult(state.current);
          updateStatusUI(
            state.current?.puede_ingresar === false ? 'error' : (state.current?.requiere_confirmacion ? 'idle' : 'ok'),
            state.current?.puede_ingresar === false ? 'Orden bloqueada' : (state.current?.requiere_confirmacion ? 'Advertencia de orden' : 'Listo para validar')
          );
        } else {
          state.current = data.visita || null;
          const ev = data.eval || {};
          state.currentEval = ev;
          feedback(ev.permitido);
          renderVisitResult(state.current, ev);
          updateStatusUI(ev.permitido ? 'ok' : 'error', ev.permitido ? 'Permitido' : 'Denegado');
        }

        enableActions(true);
      } catch (e) {
        if (!isAlive() || token !== state.searchToken) return;
        state.current = null;
        state.currentKind = null;
        state.actionSource = null;
        state.currentEval = null;
        feedback(false);
        resetDynamicForm();
        updateStatusUI('error', 'Denegado');
        alertMsg(e.message || 'No se pudo validar el código.');
      } finally {
        if (isAlive() && token === state.searchToken) setLoadingSearch(false);
      }
    }

    async function buscarResidente(query) {
      const cleanQuery = String(query || '').trim();
      state.currentResident = null;
      enableActions(!!state.current);
      residentAlertMsg('');

      if (!cleanQuery) {
        residentAlertMsg('Escribe un nombre, unidad, teléfono o correo para buscar.');
        return;
      }

      try {
        const json = await fetchJSON(`${API}accesos.php?action=buscar_residente&q=${encodeURIComponent(cleanQuery)}`);
        const items = Array.isArray(json.data?.items) ? json.data.items : [];
        renderResidentSearchResults(items);
      } catch (e) {
        residentAlertMsg(e.message || 'No se pudo buscar al residente.');
      }
    }

    async function registrarResidente(tipo) {
      if (!state.currentResident || !isAlive()) return;
      const fd = new FormData();
      fd.set('action', 'scan_residente');
      fd.set('resident_id', state.currentResident.user_id);
      fd.set('tipo_evento', tipo);
      try {
        setLoadingRegister(true, tipo);
        residentAlertMsg('');
        const json = await fetchJSON(`${API}accesos.php`, { method: 'POST', body: fd });
        state.currentResident = { ...state.currentResident, access: json.access || state.currentResident.access };
        renderResidentResult(state.currentResident);
        residentAlertMsg(json.message || 'Acceso de residente procesado correctamente.', 'success');
        await loadHist();
        window.setTimeout(() => {
          if (isAlive()) closeActionModal();
        }, 1200);
      } catch (e) {
        residentAlertMsg(e.message || 'No se pudo registrar el acceso del residente.');
      } finally {
        if (isAlive()) {
          setLoadingRegister(false, tipo);
          enableActions(!!state.current || !!state.currentResident);
        }
      }
    }

    async function registrar(tipo) {
      if (!state.current || !isAlive()) return;
      const token = ++state.registerToken;
      const fd = new FormData();
      let action = 'scan';

      if (state.currentKind === 'persona_recurrente') {
        action = 'confirm_persona_recurrente';
        fd.set('persona_id', String(state.current.id));
        fd.set('pin', els.pin?.value?.trim() || '');
        if (state.current?.requiere_confirmacion) fd.set('cumplimiento_confirmado', '1');
      } else if (state.currentKind === 'visitante_rapido') {
        action = 'confirm_visitante_rapido';
        fd.set('visitante_id', String(state.current.id));
        if (els.evidence?.files?.[0]) fd.append('evidencia', els.evidence.files[0]);
      } else if (state.currentKind === 'permiso_material') {
        action = 'confirm_permiso_material';
        fd.set('permiso_material_id', String(state.current.id));
        if (els.evidence?.files?.[0]) fd.append('evidencia', els.evidence.files[0]);
      } else if (state.currentKind === 'orden_servicio') {
        action = 'confirm_orden_servicio';
        fd.set('orden_id', String(state.current.id));
        if (state.current?.requiere_confirmacion) fd.set('cumplimiento_confirmado', '1');
      } else {
        fd.set('code', state.current.codigo_acceso);
      }

      fd.set('action', action);
      fd.set('tipo_evento', tipo);
      fd.set('observaciones', els.notes?.value?.trim() || '');

      try {
        setLoadingRegister(true, tipo);
        alertMsg('');
        const json = await fetchJSON(`${API}accesos.php`, { method: 'POST', body: fd });
        if (!isAlive() || token !== state.registerToken) return;

        feedback(!!json.permitido);
        updateStatusUI(json.permitido ? 'ok' : 'error', json.permitido ? (tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada') : 'Denegado');
        alertMsg(json.message || 'Movimiento procesado.', json.permitido ? 'success' : 'error');

        if (state.currentKind === 'persona_recurrente') {
          state.current = json.data?.persona || state.current;
          renderPersonaResult(state.current);
        } else if (state.currentKind === 'visitante_rapido') {
          state.current = json.data?.visitante_rapido || state.current;
          state.currentEval = state.current?.eval || { permitido: !!json.permitido, motivo: json.message || '' };
          renderVisitanteOperativoResult(state.current);
        } else if (state.currentKind === 'permiso_material') {
          state.current = json.data?.permiso_material || state.current;
          state.currentEval = state.current?.eval || { permitido: !!json.permitido, motivo: json.message || '' };
          renderPermisoResult(state.current);
        } else if (state.currentKind === 'orden_servicio') {
          state.current = json.data?.orden_servicio || state.current;
          renderOrdenServicioResult(state.current);
        } else {
          state.currentEval = { permitido: !!json.permitido, motivo: json.message || '' };
          if (els.modalBody) {
            els.modalBody.innerHTML = `
              <div class="rounded-xl px-4 py-3 ${json.permitido ? 'border border-emerald-200 bg-emerald-50' : 'border border-rose-200 bg-rose-50'}">
                <div class="${json.permitido ? 'text-emerald-700' : 'text-rose-700'} font-semibold">
                  ${json.permitido ? `✔ ${tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada'}` : '✖ Acceso denegado'}
                </div>
                <div class="text-sm text-slate-600 mt-1">${safeText(json.visita?.nombre_visitante || state.current?.nombre_visitante)}</div>
                ${json.permitido ? '' : `<div class="mt-2 text-sm text-rose-700">${safeText(json.message || state.currentEval?.motivo || 'Acceso denegado.', '')}</div>`}
              </div>
            `;
          }
        }

        await loadHist();

        setTimeout(() => {
          if (isAlive() && json.permitido) {
            closeActionModal();
          }
        }, 1500);
      } catch (e) {
        if (!isAlive() || token !== state.registerToken) return;
        alertMsg(e.message || 'No se pudo registrar el acceso.');
      } finally {
        if (isAlive() && token === state.registerToken) {
          setLoadingRegister(false, tipo);
          enableActions(!!state.current || !!state.currentResident);
        }
      }
    }

    function renderHistItem(row) {
      const isOperationalRow = !!row.tipo_origen;
      const ok = row.resultado === 'permitido';
      const title = isOperationalRow
        ? (row.persona_nombre || row.nombre_visitante || row.permiso_tipo_movimiento || row.tipo_origen)
        : (row.origen_acceso === 'residente_directo' ? row.residente_nombre : row.codigo_acceso);
      const subtitle = isOperationalRow
        ? `${safeText(row.tipo_origen)} · ${safeText(row.tipo_evento)}`
        : (row.origen_acceso === 'residente_directo' ? 'Acceso directo de residente' : safeText(row.nombre_visitante, 'Visitante no identificado'));
      const meta = isOperationalRow
        ? `${safeText(row.area_nombre || 'Sin área')} · ${safeText(row.fecha_hora, '')}`
        : `${safeText(row.unidad_clave)} · ${safeText(row.tipo_evento)}${row.origen_acceso === 'residente_directo' ? ' · residente' : ''}`;

      return `
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="font-semibold text-slate-800 break-all">${safeText(title, 'Sin referencia')}</div>
              <div class="text-sm text-slate-600 mt-1">${safeText(subtitle, 'Movimiento')}</div>
              <div class="text-xs text-slate-500 mt-1">${meta}</div>
              ${row.observaciones ? `<div class="text-xs text-slate-400 mt-1">${escapeHtml(row.observaciones)}</div>` : ''}
            </div>
            <div class="shrink-0 text-right">
              <div class="inline-flex rounded-full px-2 py-1 text-[11px] font-medium ${ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">${safeText(row.resultado)}</div>
              <div class="text-[11px] text-slate-400 mt-2">${safeText(row.fecha_hora, '')}</div>
            </div>
          </div>
        </div>
      `;
    }

    function paginate(items, page, perPage) {
      const start = (page - 1) * perPage;
      return items.slice(start, start + perPage);
    }

    function renderHistPagination() {
      if (!els.histPagination || !isAlive()) return;
      els.histPagination.innerHTML = '';
      const totalPages = Math.ceil(state.histItems.length / state.histPerPage);
      if (totalPages <= 1) return;

      const prev = document.createElement('button');
      prev.type = 'button';
      prev.textContent = '◀';
      prev.className = 'px-3 py-1 rounded border text-sm disabled:opacity-40';
      prev.disabled = state.histPage === 1;
      prev.addEventListener('click', () => {
        state.histPage -= 1;
        renderHist();
      });
      els.histPagination.appendChild(prev);

      for (let i = 1; i <= totalPages; i++) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = String(i);
        btn.className = 'px-3 py-1 rounded text-sm ' + (state.histPage === i ? 'bg-slate-800 text-white' : 'border');
        btn.addEventListener('click', () => {
          state.histPage = i;
          renderHist();
        });
        els.histPagination.appendChild(btn);
      }

      const next = document.createElement('button');
      next.type = 'button';
      next.textContent = '▶';
      next.className = 'px-3 py-1 rounded border text-sm disabled:opacity-40';
      next.disabled = state.histPage === totalPages;
      next.addEventListener('click', () => {
        state.histPage += 1;
        renderHist();
      });
      els.histPagination.appendChild(next);
    }

    function renderHist() {
      if (!els.hist || !isAlive()) return;
      const items = paginate(state.histItems, state.histPage, state.histPerPage);
      if (!items.length) {
        els.hist.innerHTML = `<div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">Aún no hay movimientos recientes.</div>`;
        renderHistPagination();
        return;
      }
      els.hist.innerHTML = items.map(renderHistItem).join('');
      renderHistPagination();
    }

    async function loadHist() {
      if (!els.hist || !isAlive()) return;
      els.hist.innerHTML = `<div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">Cargando historial…</div>`;
      try {
        const json = await fetchJSON(`${API}accesos.php?action=hist&limit=50`);
        if (!isAlive()) return;
        state.histItems = Array.isArray(json.data?.items) ? json.data.items : [];
        state.histPage = 1;
        renderHist();
      } catch (e) {
        if (!isAlive()) return;
        els.hist.innerHTML = `<div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">No se pudo cargar el historial.</div>`;
        if (els.histPagination) els.histPagination.innerHTML = '';
      }
    }

    async function openCamera() {
      if (!els.cameraModal || !els.video || !isAlive()) return;
      closeCamera();
      if (window.OSGateModal?.open) {
        window.OSGateModal.open(els.cameraModal);
      } else {
        els.cameraModal.classList.remove('hidden');
        els.cameraModal.classList.add('flex');
      }
      setCameraState('idle', 'Estamos solicitando permiso de cámara…');
      alertMsg('');

      if (!isSecureCameraContext()) {
        setCameraState('error', 'La cámara requiere HTTPS.', 'Abre este panel desde un dominio seguro para que el navegador permita la cámara.');
        return;
      }
      if (!navigator.mediaDevices?.getUserMedia) {
        setCameraState('error', 'Este navegador no puede abrir cámara.', 'Usa el código manual o prueba desde otro navegador móvil.');
        return;
      }

      try {
        const permissionState = await getCameraPermissionState();
        if (permissionState === 'denied') {
          setCameraState('error', 'Activa el permiso de cámara en tu navegador.', 'Revisa permisos del sitio y luego toca "Reintentar cámara".');
          return;
        }

        state.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        if (!isAlive()) {
          closeCamera();
          return;
        }
        els.video.srcObject = state.stream;
        await prepareScannerEngine();
        if (!state.scannerMode) {
          throw new Error('No se pudo preparar el lector QR.');
        }
        setCameraState('success', 'Apunta al código QR.', state.scannerMode === 'native'
          ? 'La lectura está lista. Si no detecta, también puedes ingresar el código manualmente.'
          : 'Usando lector QR compatible con este navegador. Si no detecta, usa el código manual.');
        scanLoop();
      } catch (e) {
        const mapped = mapCameraError(e);
        closeCamera();
        if (window.OSGateModal?.open && els.cameraModal) {
          window.OSGateModal.open(els.cameraModal);
        } else {
          els.cameraModal?.classList.remove('hidden');
          els.cameraModal?.classList.add('flex');
        }
        setCameraState('error', mapped.message, mapped.note);
        alertMsg(mapped.message);
      }
    }

    async function scanLoop(now = 0) {
      if (!els.video || !state.stream || !isAlive()) return;
      try {
        if (!state.scanning && (!state.lastScanAt || now - state.lastScanAt >= SCAN_INTERVAL_MS)) {
          state.lastScanAt = now;
          const raw = await detectCode();
          if (!isAlive()) return;
          if (raw) {
            state.scanning = true;
            if (els.codigo) els.codigo.value = raw;
            setCameraState('success', 'Código detectado. Validando…');
            await buscar(raw);
            if (!isAlive()) return;
            closeCamera();
            return;
          }
        }
      } catch (_) {}
      if (isAlive()) state.rafId = requestAnimationFrame(scanLoop);
    }

    function closeCamera() {
      if (window.OSGateModal?.close && els.cameraModal) {
        window.OSGateModal.close(els.cameraModal);
      } else {
        els.cameraModal?.classList.add('hidden');
        els.cameraModal?.classList.remove('flex');
      }
      if (state.rafId) cancelAnimationFrame(state.rafId);
      state.rafId = null;
      if (state.stream) state.stream.getTracks().forEach((track) => track.stop());
      if (els.video) els.video.srcObject = null;
      state.stream = null;
      state.detector = null;
      state.scannerMode = null;
      state.scanning = false;
      state.lastScanAt = 0;
      setCameraState('idle', 'Esperando código QR…');
    }

    function bindEvents() {
      els.btnBuscar?.addEventListener('click', () => buscar(els.codigo?.value?.trim()));
      els.btnResidentSearch?.addEventListener('click', () => buscarResidente(els.residentSearch?.value?.trim()));
      els.btnEntrada?.addEventListener('click', () => {
        if (state.actionSource === 'resident') registrarResidente('entrada');
        else registrar('entrada');
      });
      els.btnSalida?.addEventListener('click', () => {
        if (state.actionSource === 'resident') registrarResidente('salida');
        else registrar('salida');
      });
      els.btnOpenCamera?.addEventListener('click', openCamera);
      els.btnRetryCamera?.addEventListener('click', openCamera);
      els.btnCloseCamera?.addEventListener('click', closeCamera);
      els.btnCloseCameraFooter?.addEventListener('click', closeCamera);
      els.cameraModal?.addEventListener('osgate:modal-close-request', closeCamera);
      els.modalClose?.addEventListener('click', closeActionModal);
      els.modal?.addEventListener('osgate:modal-close-request', closeActionModal);
      els.btnRefrescar?.addEventListener('click', loadHist);
      els.codigo?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          buscar(els.codigo?.value?.trim());
        }
      });
      els.residentSearch?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          buscarResidente(els.residentSearch?.value?.trim());
        }
      });
      els.cameraModal?.addEventListener('click', (e) => {
        if (e.target === els.cameraModal) closeCamera();
      });
      els.modal?.addEventListener('click', (e) => {
        if (e.target === els.modal) closeActionModal();
      });
      els.residentResults?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-resident-select]');
        if (!btn) return;
        const resident = state.residentSearchItems[Number(btn.dataset.residentSelect || -1)];
        if (!resident) return;
        state.currentResident = resident;
        state.actionSource = 'resident';
        state.currentEval = null;
        openActionModal('Acceso directo de residente');
        updateStatusUI(resident?.access?.allow_direct_access ? 'ok' : 'error', resident?.access?.allow_direct_access ? 'Listo para validar' : 'Con restricciones');
        renderResidentResult(resident);
        enableActions(true);
      });
    }

    function unbindEvents() {
      // view se desmonta completa; no necesitamos granularidad adicional
    }

    function consumeAutoScanIntent() {
      let intent = '';
      try {
        intent = window.sessionStorage.getItem(AUTO_SCAN_KEY) || '';
        if (intent) window.sessionStorage.removeItem(AUTO_SCAN_KEY);
      } catch (_) {}
      state.autoScanIntent = intent;
      return intent;
    }

    function applyAutoScanIntent(intent) {
      if (intent !== 'persona_recurrente' && intent !== 'permiso_material') return;
      if (els.codigo) {
        els.codigo.placeholder = intent === 'permiso_material'
          ? 'Escanea o pega el QR del permiso de materiales'
          : 'Escanea o pega el QR del personal recurrente';
      }
      alertMsg(
        intent === 'permiso_material'
          ? 'Escanea el QR del permiso de materiales para validar entrada o salida.'
          : 'Escanea el QR del personal recurrente para registrar entrada o salida.',
        'success',
      );
      window.setTimeout(() => {
        if (isAlive()) openCamera();
      }, 250);
    }

    function init() {
      alertMsg('');
      resetResultArea();
      if (isOperational()) {
        els.residentSection?.classList.add('hidden');
      } else {
        els.residentSection?.classList.remove('hidden');
      }
      bindEvents();
      loadHist();
      applyAutoScanIntent(consumeAutoScanIntent());
    }

    init();

    return {
      unmount() {
        state.destroyed = true;
        closeCamera();
        unbindEvents();
      },
    };
  };
})();
