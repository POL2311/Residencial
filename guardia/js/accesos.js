(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.accesos = function ({ API, fetchJSON, escapeHtml }) {
    const els = {
      codigo: document.getElementById('codigo'),
      alert: document.getElementById('accesosAlert'),
      resultado: document.getElementById('accesoResultado'),
      estadoBadge: document.getElementById('accesoEstadoBadge'),
      miniStatus: document.getElementById('accesoMiniStatus'),

      btnBuscar: document.getElementById('btnBuscar'),
      btnEntrada: document.getElementById('btnConfirmarEntrada'),
      btnSalida: document.getElementById('btnConfirmarSalida'),

      btnOpenCamera: document.getElementById('btnOpenCamera'),
      cameraModal: document.getElementById('cameraModal'),
      cameraHint: document.getElementById('cameraHint'),
      video: document.getElementById('video'),
      btnCloseCamera: document.getElementById('btnCloseCamera'),

      hist: document.getElementById('accesosHist'),
      histPagination: document.getElementById('accesosHistPagination'),
      btnRefrescar: document.getElementById('btnRefrescarHist'),

      soundOk: document.getElementById('soundOk'),
      soundNo: document.getElementById('soundNo'),
    };

    const state = {
      current: null,
      stream: null,
      detector: null,
      scanning: false,
      destroyed: false,
      loadingSearch: false,
      loadingRegister: false,

      histItems: [],
      histPage: 1,
      histPerPage: 5,

      rafId: null,
      searchToken: 0,
      registerToken: 0,
    };

    function isAlive() {
      return !state.destroyed;
    }

    function safeText(value, fallback = '—') {
      const v = String(value ?? '').trim();
      return escapeHtml(v || fallback);
    }

    function alertMsg(msg = '', type = 'error') {
      if (!els.alert || !isAlive()) return;

      if (!msg) {
        els.alert.classList.add('hidden');
        els.alert.textContent = '';
        els.alert.className =
          'hidden mt-3 rounded-xl border px-3 py-2 text-xs';
        return;
      }

      const styles =
        type === 'success'
          ? 'mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700'
          : 'mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700';

      els.alert.className = styles;
      els.alert.textContent = msg;
      els.alert.classList.remove('hidden');
    }

    function feedback(ok) {
      try {
        if (ok) els.soundOk?.play?.();
        else els.soundNo?.play?.();
      } catch (_) {}

      if (navigator.vibrate) {
        navigator.vibrate(ok ? 120 : [100, 60, 100]);
      }
    }

    function setButtonsDisabled(disabled) {
      if (els.btnEntrada) els.btnEntrada.disabled = disabled;
      if (els.btnSalida) els.btnSalida.disabled = disabled;
    }

    function enableActions(on) {
      const disabled = !on || state.loadingRegister || state.loadingSearch;
      setButtonsDisabled(disabled);
    }

    function setLoadingSearch(on, text = 'Buscando…') {
      state.loadingSearch = on;

      if (els.btnBuscar) {
        els.btnBuscar.disabled = on;
        els.btnBuscar.textContent = on ? text : 'Buscar';
      }

      if (els.codigo) {
        els.codigo.disabled = on;
      }

      enableActions(!!state.current);
    }

    function setLoadingRegister(on, tipo = 'entrada') {
      state.loadingRegister = on;

      if (els.btnEntrada) {
        els.btnEntrada.textContent =
          on && tipo === 'entrada' ? 'Registrando…' : 'Registrar entrada';
      }

      if (els.btnSalida) {
        els.btnSalida.textContent =
          on && tipo === 'salida' ? 'Registrando…' : 'Registrar salida';
      }

      if (els.btnBuscar) {
        els.btnBuscar.disabled = on || state.loadingSearch;
      }

      enableActions(!!state.current);
    }

    function updateStatusUI(type, text) {
      if (!isAlive()) return;

      const classes =
        type === 'ok'
          ? 'bg-emerald-100 text-emerald-700'
          : type === 'error'
          ? 'bg-rose-100 text-rose-700'
          : 'bg-slate-100 text-slate-700';

      if (els.estadoBadge) {
        els.estadoBadge.className =
          `mt-3 inline-flex items-center rounded-full px-3 py-1 text-sm ${classes}`;
        els.estadoBadge.textContent = text;
      }

      if (els.miniStatus) {
        els.miniStatus.classList.remove('hidden');
        els.miniStatus.className = `rounded-full px-3 py-1 text-xs font-medium ${classes}`;
        els.miniStatus.textContent = text;
      }
    }

    function resetResultArea() {
      state.current = null;
      enableActions(false);

      if (els.resultado) {
        els.resultado.textContent = 'Ingresa un código para validar el acceso.';
      }

      updateStatusUI('idle', 'Esperando validación');
    }

    function renderVisitResult(visita, ev) {
      if (!els.resultado || !isAlive()) return;

      const permitido = !!ev?.permitido;
      const nombre = safeText(visita?.nombre_visitante);
      const unidad = safeText(visita?.unidad_clave);
      const placa = safeText(visita?.placa_vehiculo);
      const residente = safeText(visita?.residente_nombre);
      const estado = safeText(visita?.estado);
      const codigo = safeText(visita?.codigo_acceso);
      const motivo = safeText(ev?.motivo, '');

      els.resultado.innerHTML = `
        <div class="space-y-4">
          <div class="font-semibold text-base ${permitido ? 'text-emerald-700' : 'text-rose-700'}">
            ${permitido ? '✔ Acceso permitido' : '✖ Acceso denegado'}
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
              <div class="text-xs text-slate-400 uppercase tracking-wide">Visitante</div>
              <div class="mt-1 font-medium text-slate-800">${nombre}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
              <div class="text-xs text-slate-400 uppercase tracking-wide">Unidad</div>
              <div class="mt-1 font-medium text-slate-800">${unidad}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
              <div class="text-xs text-slate-400 uppercase tracking-wide">Residente</div>
              <div class="mt-1 font-medium text-slate-800">${residente}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
              <div class="text-xs text-slate-400 uppercase tracking-wide">Placa</div>
              <div class="mt-1 font-medium text-slate-800">${placa}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
              <div class="text-xs text-slate-400 uppercase tracking-wide">Estado visita</div>
              <div class="mt-1 font-medium text-slate-800">${estado}</div>
            </div>

            <div class="rounded-xl bg-slate-50 border border-slate-200 p-3">
              <div class="text-xs text-slate-400 uppercase tracking-wide">Código</div>
              <div class="mt-1 font-medium text-slate-800 break-all">${codigo}</div>
            </div>
          </div>

          ${
            !permitido
              ? `
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">
                  ${motivo || 'Acceso denegado.'}
                </div>
              `
              : ''
          }
        </div>
      `;
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
        const json = await fetchJSON(
          `${API}accesos.php?action=buscar&code=${encodeURIComponent(cleanCode)}`
        );

        if (!isAlive() || token !== state.searchToken) return;

        state.current = json.data?.visita || null;
        const ev = json.data?.eval || {};

        feedback(ev.permitido);
        renderVisitResult(state.current, ev);
        updateStatusUI(
          ev.permitido ? 'ok' : 'error',
          ev.permitido ? 'Permitido' : 'Denegado'
        );

        enableActions(true);
      } catch (e) {
        if (!isAlive() || token !== state.searchToken) return;

        state.current = null;
        feedback(false);

        if (els.resultado) {
          els.resultado.innerHTML = `
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
              Código no válido o no encontrado.
            </div>
          `;
        }

        updateStatusUI('error', 'Denegado');
        alertMsg(e.message || 'No se pudo validar el código.');
      } finally {
        if (isAlive() && token === state.searchToken) {
          setLoadingSearch(false);
        }
      }
    }

    async function registrar(tipo) {
      if (!state.current || !isAlive()) return;

      const token = ++state.registerToken;
      const fd = new FormData();
      fd.set('action', 'scan');
      fd.set('code', state.current.codigo_acceso);
      fd.set('tipo_evento', tipo);

      try {
        setLoadingRegister(true, tipo);
        alertMsg('');

        const json = await fetchJSON(`${API}accesos.php`, {
          method: 'POST',
          body: fd,
        });

        if (!isAlive() || token !== state.registerToken) return;

        if (els.resultado) {
          els.resultado.innerHTML = `
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
              <div class="text-emerald-700 font-semibold">
                ✔ ${tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada'}
              </div>
              <div class="text-sm text-slate-600 mt-1">
                ${safeText(json.visita?.nombre_visitante || state.current?.nombre_visitante)}
              </div>
            </div>
          `;
        }

        updateStatusUI(
          'ok',
          tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada'
        );

        await loadHist();

        setTimeout(() => {
          if (isAlive()) {
            resetResultArea();
          }
        }, 1500);
      } catch (e) {
        if (!isAlive() || token !== state.registerToken) return;
        alertMsg(e.message || 'No se pudo registrar el acceso.');
      } finally {
        if (isAlive() && token === state.registerToken) {
          setLoadingRegister(false);
          enableActions(!!state.current);
        }
      }
    }

    function renderHistItem(r) {
      const ok = r.resultado === 'permitido';

      return `
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="font-semibold text-slate-800 break-all">
                ${safeText(r.codigo_acceso, 'Sin código')}
              </div>

              <div class="text-sm text-slate-600 mt-1">
                ${safeText(r.nombre_visitante, 'Visitante no identificado')}
              </div>

              <div class="text-xs text-slate-500 mt-1">
                ${safeText(r.unidad_clave)} · ${safeText(r.tipo_evento)}
              </div>

              ${
                r.observaciones
                  ? `<div class="text-xs text-slate-400 mt-1">${escapeHtml(r.observaciones)}</div>`
                  : ''
              }
            </div>

            <div class="shrink-0 text-right">
              <div class="inline-flex rounded-full px-2 py-1 text-[11px] font-medium ${ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">
                ${safeText(r.resultado)}
              </div>

              <div class="text-[11px] text-slate-400 mt-2">
                ${safeText(r.fecha_hora, '')}
              </div>
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
        btn.className =
          'px-3 py-1 rounded text-sm ' +
          (state.histPage === i ? 'bg-slate-800 text-white' : 'border');
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
        els.hist.innerHTML = `
          <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">
            Aún no hay movimientos recientes.
          </div>
        `;
        renderHistPagination();
        return;
      }

      els.hist.innerHTML = items.map(renderHistItem).join('');
      renderHistPagination();
    }

    async function loadHist() {
      if (!els.hist || !isAlive()) return;

      els.hist.innerHTML = `
        <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500">
          Cargando historial…
        </div>
      `;

      try {
        const json = await fetchJSON(`${API}accesos.php?action=hist&limit=50`);
        if (!isAlive()) return;

        state.histItems = Array.isArray(json.data?.items) ? json.data.items : [];
        state.histPage = 1;
        renderHist();
      } catch (e) {
        if (!isAlive()) return;

        els.hist.innerHTML = `
          <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
            No se pudo cargar el historial.
          </div>
        `;

        if (els.histPagination) els.histPagination.innerHTML = '';
      }
    }

    async function openCamera() {
      if (!els.cameraModal || !els.video || !isAlive()) return;

      if (!navigator.mediaDevices?.getUserMedia) {
        alertMsg('Este dispositivo no soporta acceso a cámara.');
        return;
      }

      if (!('BarcodeDetector' in window)) {
        alertMsg('Tu navegador no soporta lectura QR automática. Usa captura manual.');
        return;
      }

      try {
        els.cameraModal.classList.remove('hidden');
        els.cameraModal.classList.add('flex');

        if (els.cameraHint) {
          els.cameraHint.textContent = 'Esperando código QR…';
        }

        state.stream = await navigator.mediaDevices.getUserMedia({
          video: { facingMode: 'environment' },
        });

        if (!isAlive()) {
          closeCamera();
          return;
        }

        els.video.srcObject = state.stream;
        state.detector = new BarcodeDetector({ formats: ['qr_code'] });
        scanLoop();
      } catch (e) {
        alertMsg('No se pudo abrir la cámara.');
        closeCamera();
      }
    }

    async function scanLoop() {
      if (!state.detector || !els.video || !state.stream || !isAlive()) return;

      try {
        if (!state.scanning) {
          const codes = await state.detector.detect(els.video);

          if (!isAlive()) return;

          if (codes.length) {
            state.scanning = true;
            const raw = codes[0]?.rawValue || '';

            if (raw) {
              if (els.codigo) els.codigo.value = raw;
              if (els.cameraHint) els.cameraHint.textContent = 'Código detectado. Validando…';

              await buscar(raw);

              if (!isAlive()) return;
              closeCamera();
            }

            setTimeout(() => {
              if (isAlive()) {
                state.scanning = false;
              }
            }, 2500);
          }
        }
      } catch (_) {
        // silencioso
      }

      if (isAlive()) {
        state.rafId = requestAnimationFrame(scanLoop);
      }
    }

    function closeCamera() {
      if (els.cameraModal) {
        els.cameraModal.classList.add('hidden');
        els.cameraModal.classList.remove('flex');
      }

      if (state.rafId) {
        cancelAnimationFrame(state.rafId);
        state.rafId = null;
      }

      if (state.stream) {
        state.stream.getTracks().forEach((t) => t.stop());
      }

      if (els.video) {
        els.video.srcObject = null;
      }

      state.stream = null;
      state.detector = null;
      state.scanning = false;

      if (els.cameraHint) {
        els.cameraHint.textContent = 'Esperando código QR…';
      }
    }

    function onBuscarClick() {
      buscar(els.codigo?.value?.trim());
    }

    function onEntradaClick() {
      registrar('entrada');
    }

    function onSalidaClick() {
      registrar('salida');
    }

    function onOpenCameraClick() {
      openCamera();
    }

    function onCloseCameraClick() {
      closeCamera();
    }

    function onRefreshHistClick() {
      loadHist();
    }

    function onCodigoKeydown(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        buscar(els.codigo?.value?.trim());
      }
    }

    function onCameraBackdropClick(e) {
      if (e.target === els.cameraModal) {
        closeCamera();
      }
    }

    function bindEvents() {
      els.btnBuscar?.addEventListener('click', onBuscarClick);
      els.btnEntrada?.addEventListener('click', onEntradaClick);
      els.btnSalida?.addEventListener('click', onSalidaClick);
      els.btnOpenCamera?.addEventListener('click', onOpenCameraClick);
      els.btnCloseCamera?.addEventListener('click', onCloseCameraClick);
      els.btnRefrescar?.addEventListener('click', onRefreshHistClick);
      els.codigo?.addEventListener('keydown', onCodigoKeydown);
      els.cameraModal?.addEventListener('click', onCameraBackdropClick);
    }

    function unbindEvents() {
      els.btnBuscar?.removeEventListener('click', onBuscarClick);
      els.btnEntrada?.removeEventListener('click', onEntradaClick);
      els.btnSalida?.removeEventListener('click', onSalidaClick);
      els.btnOpenCamera?.removeEventListener('click', onOpenCameraClick);
      els.btnCloseCamera?.removeEventListener('click', onCloseCameraClick);
      els.btnRefrescar?.removeEventListener('click', onRefreshHistClick);
      els.codigo?.removeEventListener('keydown', onCodigoKeydown);
      els.cameraModal?.removeEventListener('click', onCameraBackdropClick);
    }

    function init() {
      alertMsg('');
      resetResultArea();
      bindEvents();
      loadHist();
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