(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.rondines = function ({ API, fetchJSON, escapeHtml }) {
    const root = document.getElementById('guardRondinesView');
    if (!root) return null;

    const els = {
      refresh: document.getElementById('btnRefreshRounds'),
      alert: document.getElementById('roundAlert'),
      routesWrap: document.getElementById('roundRoutesWrap'),
      routesList: document.getElementById('roundRoutesList'),
      activeWrap: document.getElementById('roundActiveWrap'),
      activeContent: document.getElementById('roundActiveContent'),
      pointModal: document.getElementById('roundPointModal'),
      pointModalHint: document.getElementById('roundPointModalHint'),
      pointForm: document.getElementById('roundPointForm'),
      pointFormError: document.getElementById('roundPointFormError'),
      qrCode: document.getElementById('roundQrCode'),
      pointStatus: document.getElementById('roundPointStatus'),
      observation: document.getElementById('roundObservation'),
      evidence: document.getElementById('roundEvidence'),
      gpsStatus: document.getElementById('roundGpsStatus'),
      submitPoint: document.getElementById('btnSubmitRoundPoint'),
      closePointModal: document.getElementById('btnCloseRoundPointModal'),
      openCamera: document.getElementById('btnOpenRoundCamera'),
      cameraModal: document.getElementById('roundCameraModal'),
      video: document.getElementById('roundVideo'),
      cameraHint: document.getElementById('roundCameraHint'),
      retryCamera: document.getElementById('btnRetryRoundCamera'),
      closeCamera: document.getElementById('btnCloseRoundCamera'),
    };

    const state = {
      destroyed: false,
      routes: [],
      detail: null,
      loading: false,
      currentPoint: null,
      stream: null,
      detector: null,
      scannerMode: null,
      jsQrLoadPromise: null,
      canvas: null,
      canvasCtx: null,
      scanning: false,
      rafId: null,
      lastScanAt: 0,
    };

    const SCAN_INTERVAL_MS = 240;

    function isAlive() {
      return !state.destroyed;
    }

    function apiUrl(action, params = {}) {
      const query = new URLSearchParams({ action, ...params });
      return `${API}rondines.php?${query.toString()}`;
    }

    function safe(value, fallback = '—') {
      const text = String(value ?? '').trim();
      return escapeHtml(text || fallback);
    }

    function toast(type, title, message) {
      try {
        window.AppToast?.show?.({ type, title, message });
      } catch (_) {}
    }

    function showAlert(message = '', type = 'info') {
      if (!els.alert) return;
      if (!message) {
        els.alert.className = 'hidden rounded-2xl px-4 py-3 text-sm';
        els.alert.textContent = '';
        return;
      }
      const styles = type === 'error'
        ? 'border border-rose-200 bg-rose-50 text-rose-700'
        : type === 'success'
          ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
          : 'border border-amber-200 bg-amber-50 text-amber-800';
      els.alert.className = `rounded-2xl px-4 py-3 text-sm ${styles}`;
      els.alert.textContent = message;
      els.alert.classList.remove('hidden');
    }

    function showFormError(message = '') {
      if (!els.pointFormError) return;
      if (!message) {
        els.pointFormError.classList.add('hidden');
        els.pointFormError.textContent = '';
        return;
      }
      els.pointFormError.textContent = message;
      els.pointFormError.classList.remove('hidden');
    }

    function formatDate(value) {
      if (!value) return '—';
      const date = new Date(String(value).replace(' ', 'T'));
      if (Number.isNaN(date.getTime())) return String(value);
      return date.toLocaleString('es-MX', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      });
    }

    function progressSummary(detail = state.detail) {
      const summary = detail?.summary || {};
      const total = Number(summary.puntos_totales || 0);
      const done = Number(summary.puntos_escaneados || 0);
      const percent = total > 0 ? Math.round((done / total) * 100) : 0;
      return { total, done, percent, anomalias: Number(summary.anomalias || 0) };
    }

    function renderRoutes(items = state.routes) {
      if (!els.routesList) return;
      if (!items.length) {
        els.routesList.innerHTML = `
          <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500">
            No hay rutas activas de rondín disponibles.
          </div>
        `;
        return;
      }

      els.routesList.innerHTML = items.map((route) => `
        <article class="rounded-2xl border border-slate-200 bg-white px-4 py-4 shadow-sm">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
              <div class="text-base font-semibold text-slate-800">${safe(route.nombre, 'Ruta')}</div>
              <div class="mt-1 text-sm text-slate-500">${safe(route.descripcion, 'Sin descripción')}</div>
              <div class="mt-3 inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs text-slate-600">
                ${safe(route.puntos_count || 0)} puntos
              </div>
            </div>
            <button type="button" data-action="start" data-id="${safe(route.id)}" class="rounded-xl bg-[#2E5D73] px-4 py-2 text-sm font-semibold text-white">
              Iniciar rondín
            </button>
          </div>
        </article>
      `).join('');

      els.routesList.querySelectorAll('[data-action="start"]').forEach((btn) => {
        btn.addEventListener('click', () => startRoute(Number(btn.getAttribute('data-id') || 0), btn));
      });
    }

    function renderActive(detail = state.detail) {
      if (!els.activeWrap || !els.activeContent) return;
      if (!detail?.ejecucion) {
        els.activeWrap.classList.add('hidden');
        els.activeContent.innerHTML = '';
        els.routesWrap?.classList.remove('hidden');
        return;
      }

      const { total, done, percent, anomalias } = progressSummary(detail);
      const exec = detail.ejecucion;
      const points = Array.isArray(detail.puntos) ? detail.puntos : [];
      els.routesWrap?.classList.add('hidden');
      els.activeWrap.classList.remove('hidden');
      els.activeContent.innerHTML = `
        <div class="flex flex-col gap-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Rondín en proceso</div>
              <h2 class="mt-1 text-xl font-semibold text-slate-800">${safe(exec.ruta_nombre, 'Ruta')}</h2>
              <p class="mt-1 text-sm text-slate-500">Inicio: ${safe(formatDate(exec.inicio_at))}</p>
            </div>
            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
              ${done}/${total} puntos · ${percent}%
            </span>
          </div>

          <div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
              <div class="h-full rounded-full bg-[#2E5D73]" style="width:${Math.max(0, Math.min(100, percent))}%"></div>
            </div>
            <div class="mt-2 text-xs text-slate-500">${anomalias} anomalías registradas</div>
          </div>

          <div class="grid gap-3">
            ${points.map((point) => {
              const event = point.evento || null;
              const isDone = !!event;
              const badge = isDone
                ? event.estado === 'anomalia'
                  ? 'bg-rose-100 text-rose-700'
                  : 'bg-emerald-100 text-emerald-700'
                : 'bg-slate-100 text-slate-600';
              return `
                <article class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="text-sm font-semibold text-slate-800">${safe(point.orden)}. ${safe(point.nombre, 'Punto')}</div>
                      <div class="mt-1 text-xs text-slate-500">${safe(point.area_nombre, 'Sin área')} ${point.radio_metros ? `· radio ${safe(point.radio_metros)}m` : ''}</div>
                      ${point.requiere_foto || point.requiere_observacion ? `
                        <div class="mt-2 flex flex-wrap gap-1.5 text-[11px] text-slate-500">
                          ${point.requiere_foto ? '<span class="rounded-full bg-slate-100 px-2 py-1">foto requerida</span>' : ''}
                          ${point.requiere_observacion ? '<span class="rounded-full bg-slate-100 px-2 py-1">observación requerida</span>' : ''}
                        </div>
                      ` : ''}
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-medium ${badge}">
                      ${isDone ? safe(event.estado) : 'pendiente'}
                    </span>
                  </div>
                  ${isDone ? `
                    <div class="mt-3 rounded-xl bg-slate-50 px-3 py-2 text-xs text-slate-600">
                      ${safe(formatDate(event.escaneado_at))}${event.gps_valido ? ' · GPS válido' : ' · GPS con advertencia'}
                    </div>
                  ` : `
                    <button type="button" data-action="register-point" data-id="${safe(point.id)}" class="mt-3 w-full rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">
                      Registrar punto
                    </button>
                  `}
                </article>
              `;
            }).join('')}
          </div>

          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <button id="btnOpenGenericRoundPoint" type="button" class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700">
              Escanear punto
            </button>
            <button id="btnFinishRound" type="button" class="rounded-2xl bg-[#2E5D73] px-4 py-3 text-sm font-semibold text-white">
              Finalizar rondín
            </button>
            <button id="btnCancelRound" type="button" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 sm:col-span-2">
              Cancelar rondín
            </button>
          </div>
        </div>
      `;

      els.activeContent.querySelectorAll('[data-action="register-point"]').forEach((btn) => {
        btn.addEventListener('click', () => {
          const id = Number(btn.getAttribute('data-id') || 0);
          openPointModal(points.find((point) => Number(point.id) === id) || null);
        });
      });
      els.activeContent.querySelector('#btnOpenGenericRoundPoint')?.addEventListener('click', () => openPointModal(null));
      els.activeContent.querySelector('#btnFinishRound')?.addEventListener('click', finishRound);
      els.activeContent.querySelector('#btnCancelRound')?.addEventListener('click', cancelRound);
    }

    async function captureGps(statusEl = els.gpsStatus) {
      if (!navigator.geolocation) {
        if (statusEl) statusEl.textContent = 'GPS no disponible en este dispositivo.';
        return { warning: 'GPS no disponible.' };
      }
      if (statusEl) statusEl.textContent = 'Solicitando ubicación GPS…';
      return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
          (pos) => {
            const coords = pos.coords || {};
            const result = {
              latitud: coords.latitude,
              longitud: coords.longitude,
              precision_metros: coords.accuracy,
              warning: coords.accuracy > 100 ? 'Precisión GPS mayor a 100m.' : '',
            };
            if (statusEl) {
              statusEl.className = `rounded-xl border px-3 py-2 text-xs ${
                result.warning ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-emerald-200 bg-emerald-50 text-emerald-700'
              }`;
              statusEl.textContent = `GPS capturado (${Math.round(coords.accuracy || 0)}m de precisión).${result.warning ? ` ${result.warning}` : ''}`;
            }
            resolve(result);
          },
          (err) => {
            const warning = err?.code === 1 ? 'Permiso de GPS denegado.' : 'No se pudo capturar GPS.';
            if (statusEl) {
              statusEl.className = 'rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800';
              statusEl.textContent = `${warning} El registro continuará con advertencia.`;
            }
            resolve({ warning });
          },
          { enableHighAccuracy: true, timeout: 10000, maximumAge: 10000 }
        );
      });
    }

    function appendGps(fd, gps) {
      if (gps?.latitud != null) fd.set('latitud', String(gps.latitud));
      if (gps?.longitud != null) fd.set('longitud', String(gps.longitud));
      if (gps?.precision_metros != null) fd.set('precision_metros', String(gps.precision_metros));
    }

    async function startRoute(routeId, button) {
      if (!routeId || state.loading) return;
      state.loading = true;
      button?.setAttribute('disabled', 'disabled');
      showAlert('Preparando GPS para iniciar rondín…');
      try {
        const gps = await captureGps(null);
        const fd = new FormData();
        fd.set('action', 'iniciar');
        fd.set('ruta_id', String(routeId));
        appendGps(fd, gps);
        const json = await fetchJSON(`${API}rondines.php`, { method: 'POST', body: fd });
        state.detail = json.data || null;
        showAlert(json.message || 'Rondín iniciado.', 'success');
        renderActive(state.detail);
      } catch (e) {
        showAlert(e.message || 'No se pudo iniciar el rondín.', 'error');
      } finally {
        state.loading = false;
        button?.removeAttribute('disabled');
      }
    }

    function openPointModal(point = null) {
      state.currentPoint = point;
      showFormError('');
      if (els.pointForm) els.pointForm.reset();
      if (els.pointModalHint) {
        els.pointModalHint.textContent = point
          ? `Punto esperado: ${point.nombre || 'Punto'}${point.requiere_foto ? ' · requiere foto' : ''}${point.requiere_observacion ? ' · requiere observación' : ''}`
          : 'Escanea o captura manualmente el QR del punto.';
      }
      if (els.gpsStatus) {
        els.gpsStatus.className = 'rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600';
        els.gpsStatus.textContent = 'GPS pendiente. Se solicitará ubicación al guardar.';
      }
      if (els.qrCode) {
        els.qrCode.value = '';
        els.qrCode.placeholder = point?.qr_payload || 'op:round_point:...';
      }
      if (window.OSGateModal?.open && els.pointModal) {
        window.OSGateModal.open(els.pointModal);
      } else {
        els.pointModal?.classList.remove('hidden');
      }
    }

    function closePointModal() {
      closeCamera();
      if (window.OSGateModal?.close && els.pointModal) {
        window.OSGateModal.close(els.pointModal);
      } else {
        els.pointModal?.classList.add('hidden');
      }
      state.currentPoint = null;
    }

    async function submitPoint() {
      if (!state.detail?.ejecucion?.id || !els.pointForm || state.loading) return;
      showFormError('');
      state.loading = true;
      els.submitPoint?.setAttribute('disabled', 'disabled');
      try {
        const gps = await captureGps(els.gpsStatus);
        const fd = new FormData(els.pointForm);
        fd.set('action', 'registrar_punto');
        fd.set('ejecucion_id', String(state.detail.ejecucion.id));
        appendGps(fd, gps);
        const json = await fetchJSON(`${API}rondines.php`, { method: 'POST', body: fd });
        state.detail = json.data || null;
        closePointModal();
        renderActive(state.detail);
        showAlert(json.warning || json.message || 'Punto registrado.', json.warning ? 'info' : 'success');
        toast(json.warning ? 'info' : 'success', 'Rondín', json.warning || json.message || 'Punto registrado.');
      } catch (e) {
        showFormError(e.message || 'No se pudo registrar el punto.');
      } finally {
        state.loading = false;
        els.submitPoint?.removeAttribute('disabled');
      }
    }

    async function finishRound() {
      if (!state.detail?.ejecucion?.id || state.loading) return;
      state.loading = true;
      showAlert('Capturando GPS de cierre…');
      try {
        const gps = await captureGps(null);
        const fd = new FormData();
        fd.set('action', 'finalizar');
        fd.set('ejecucion_id', String(state.detail.ejecucion.id));
        appendGps(fd, gps);
        const json = await fetchJSON(`${API}rondines.php`, { method: 'POST', body: fd });
        state.detail = null;
        showAlert(json.message || 'Rondín finalizado.', 'success');
        toast('success', 'Rondín', json.message || 'Rondín finalizado.');
        await load();
      } catch (e) {
        showAlert(e.message || 'No se pudo finalizar el rondín.', 'error');
      } finally {
        state.loading = false;
      }
    }

    async function cancelRound() {
      if (!state.detail?.ejecucion?.id || state.loading) return;
      const motivo = window.prompt('Motivo de cancelación del rondín') || '';
      if (!motivo.trim()) return;
      state.loading = true;
      try {
        const fd = new FormData();
        fd.set('action', 'cancelar');
        fd.set('ejecucion_id', String(state.detail.ejecucion.id));
        fd.set('motivo', motivo.trim());
        const json = await fetchJSON(`${API}rondines.php`, { method: 'POST', body: fd });
        state.detail = null;
        showAlert(json.message || 'Rondín cancelado.', 'success');
        await load();
      } catch (e) {
        showAlert(e.message || 'No se pudo cancelar el rondín.', 'error');
      } finally {
        state.loading = false;
      }
    }

    function getAppRoot() {
      const path = window.location.pathname || '';
      const idx = path.indexOf('/guardia/');
      return idx === -1 ? '' : path.slice(0, idx);
    }

    function isSecureCameraContext() {
      if (window.isSecureContext) return true;
      const host = window.location.hostname || '';
      return host === 'localhost' || host === '127.0.0.1';
    }

    function setCameraState(kind, message) {
      if (!els.cameraHint) return;
      els.cameraHint.className = `mt-2 text-xs ${kind === 'error' ? 'text-rose-700' : kind === 'success' ? 'text-emerald-700' : 'text-slate-500'}`;
      els.cameraHint.textContent = message;
      els.retryCamera?.classList.toggle('hidden', kind !== 'error');
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
        script.async = true;
        script.dataset.jsqrFallback = '1';
        script.onload = () => resolve(window.jsQR);
        script.onerror = () => reject(new Error('No se pudo cargar el lector QR.'));
        document.head.appendChild(script);
      });
      return state.jsQrLoadPromise;
    }

    async function prepareScannerEngine() {
      if ('BarcodeDetector' in window) {
        try {
          state.detector = new window.BarcodeDetector({ formats: ['qr_code'] });
          state.scannerMode = 'barcode';
          return;
        } catch (_) {}
      }
      await loadJsQrFallback();
      state.scannerMode = 'jsqr';
      state.canvas = state.canvas || document.createElement('canvas');
      state.canvasCtx = state.canvas.getContext('2d', { willReadFrequently: true });
    }

    function stopCamera() {
      state.scanning = false;
      if (state.rafId) {
        window.cancelAnimationFrame(state.rafId);
        state.rafId = null;
      }
      if (state.stream) {
        state.stream.getTracks().forEach((track) => track.stop());
        state.stream = null;
      }
      if (els.video) {
        els.video.srcObject = null;
      }
    }

    function closeCamera() {
      stopCamera();
      if (window.OSGateModal?.close && els.cameraModal) {
        window.OSGateModal.close(els.cameraModal);
      } else {
        els.cameraModal?.classList.add('hidden');
      }
    }

    async function openCamera() {
      if (!isSecureCameraContext()) {
        setCameraState('error', 'La cámara requiere HTTPS o localhost.');
        if (window.OSGateModal?.open && els.cameraModal) {
          window.OSGateModal.open(els.cameraModal);
        } else {
          els.cameraModal?.classList.remove('hidden');
        }
        return;
      }
      if (window.OSGateModal?.open && els.cameraModal) {
        window.OSGateModal.open(els.cameraModal);
      } else {
        els.cameraModal?.classList.remove('hidden');
      }
      setCameraState('info', 'Abriendo cámara…');
      try {
        await prepareScannerEngine();
        state.stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
          audio: false,
        });
        els.video.srcObject = state.stream;
        await els.video.play();
        state.scanning = true;
        state.lastScanAt = 0;
        setCameraState('info', 'Apunta al QR del punto.');
        scanLoop();
      } catch (_) {
        setCameraState('error', 'No se pudo abrir la cámara. Usa captura manual si hace falta.');
      }
    }

    async function scanLoop() {
      if (!state.scanning || !els.video || state.destroyed) return;
      const now = Date.now();
      try {
        if (els.video.readyState >= 2 && now - state.lastScanAt >= SCAN_INTERVAL_MS) {
          state.lastScanAt = now;
          let value = '';
          if (state.scannerMode === 'barcode' && state.detector) {
            const codes = await state.detector.detect(els.video);
            value = codes?.[0]?.rawValue || '';
          } else if (window.jsQR && state.canvasCtx) {
            state.canvas.width = els.video.videoWidth || 640;
            state.canvas.height = els.video.videoHeight || 360;
            state.canvasCtx.drawImage(els.video, 0, 0, state.canvas.width, state.canvas.height);
            const image = state.canvasCtx.getImageData(0, 0, state.canvas.width, state.canvas.height);
            value = window.jsQR(image.data, image.width, image.height)?.data || '';
          }
          if (value) {
            if (els.qrCode) els.qrCode.value = value;
            setCameraState('success', 'QR detectado.');
            closeCamera();
            return;
          }
        }
      } catch (_) {}
      state.rafId = window.requestAnimationFrame(scanLoop);
    }

    async function load() {
      if (state.loading) return;
      state.loading = true;
      showAlert('');
      try {
        const activeJson = await fetchJSON(apiUrl('activo'));
        state.detail = activeJson.data?.detalle || null;
        if (state.detail?.ejecucion) {
          renderActive(state.detail);
        } else {
          renderActive(null);
          const routesJson = await fetchJSON(apiUrl('rutas'));
          state.routes = routesJson.data?.items || [];
          renderRoutes(state.routes);
        }
      } catch (e) {
        showAlert(e.message || 'No se pudieron cargar los rondines.', 'error');
      } finally {
        state.loading = false;
      }
    }

    els.refresh?.addEventListener('click', load);
    els.closePointModal?.addEventListener('click', closePointModal);
    els.pointModal?.addEventListener('osgate:modal-close-request', closePointModal);
    els.pointModal?.addEventListener('click', (ev) => {
      if (ev.target === els.pointModal) closePointModal();
    });
    els.openCamera?.addEventListener('click', openCamera);
    els.closeCamera?.addEventListener('click', closeCamera);
    els.cameraModal?.addEventListener('osgate:modal-close-request', closeCamera);
    els.retryCamera?.addEventListener('click', openCamera);
    els.submitPoint?.addEventListener('click', submitPoint);

    load();

    return {
      unmount() {
        state.destroyed = true;
        stopCamera();
      },
    };
  };
})();
