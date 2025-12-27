(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.accesos = function ({ API, fetchJSON, escapeHtml }) {

    const els = {
      codigo: document.getElementById('codigo'),
      alert: document.getElementById('accesosAlert'),
      resultado: document.getElementById('accesoResultado'),

      btnBuscar: document.getElementById('btnBuscar'),
      btnEntrada: document.getElementById('btnConfirmarEntrada'),
      btnSalida: document.getElementById('btnConfirmarSalida'),

      btnOpenCamera: document.getElementById('btnOpenCamera'),
      cameraModal: document.getElementById('cameraModal'),
      video: document.getElementById('video'),
      btnCloseCamera: document.getElementById('btnCloseCamera'),

      hist: document.getElementById('accesosHist'),
      btnRefrescar: document.getElementById('btnRefrescarHist'),

      soundOk: document.getElementById('soundOk'),
      soundNo: document.getElementById('soundNo'),
    };

    let current = null;
    let stream = null;
    let detector = null;
    let scanning = false; // 🔒 evita doble lectura

    /* ---------- helpers ---------- */
    function alertMsg(msg) {
      if (!msg) {
        els.alert.classList.add('hidden');
        return;
      }
      els.alert.classList.remove('hidden');
      els.alert.textContent = msg;
    }

    function enableActions(on) {
      els.btnEntrada.disabled = !on;
      els.btnSalida.disabled = !on;
    }

    function feedback(ok) {
      // sonido
      if (ok) els.soundOk?.play();
      else els.soundNo?.play();

      // vibración móvil
      if (navigator.vibrate) {
        navigator.vibrate(ok ? 120 : [100, 60, 100]);
      }
    }

    /* ---------- buscar ---------- */
    async function buscar(code) {
      enableActions(false);
      alertMsg('');
      if (!code) return;

      try {
        const json = await fetchJSON(
          `${API}accesos.php?action=buscar&code=${encodeURIComponent(code)}`
        );

        current = json.data.visita;
        const ev = json.data.eval;

        feedback(ev.permitido);

        els.resultado.innerHTML = `
          <div class="font-semibold ${ev.permitido ? 'text-emerald-700' : 'text-rose-700'}">
            ${ev.permitido ? '✔ Acceso permitido' : '✖ Acceso denegado'}
          </div>
          <div class="mt-2 text-sm">
            <div><b>Visitante:</b> ${escapeHtml(current.nombre_visitante)}</div>
            <div><b>Unidad:</b> ${escapeHtml(current.unidad_clave)}</div>
            <div><b>Placa:</b> ${escapeHtml(current.placa_vehiculo || '—')}</div>
            ${!ev.permitido
              ? `<div class="text-xs text-rose-600 mt-1">${escapeHtml(ev.motivo)}</div>`
              : ''
            }
          </div>
        `;

        enableActions(true);

      } catch (e) {
        current = null;
        feedback(false);
        els.resultado.textContent = 'Código no válido.';
        alertMsg(e.message);
      }
    }

    /* ---------- registrar ---------- */
    async function registrar(tipo) {
      if (!current) return;

      const fd = new FormData();
      fd.set('action', 'scan');
      fd.set('code', current.codigo_acceso);
      fd.set('tipo_evento', tipo);

      await fetchJSON(`${API}accesos.php`, { method: 'POST', body: fd });

      els.resultado.innerHTML = `
        <div class="text-emerald-700 font-semibold">
          ✔ ${tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada'}
        </div>
      `;

      enableActions(false);
      loadHist();

      // 🕒 vuelve a escanear
      setTimeout(() => {
        current = null;
        els.resultado.textContent = 'Escanea el siguiente código.';
      }, 1500);
    }

    /* ---------- historial ---------- */
    async function loadHist() {
      els.hist.textContent = 'Cargando…';
      const json = await fetchJSON(`${API}accesos.php?action=hist&limit=10`);
      els.hist.innerHTML = json.data.items.map(r => `
        <div class="border rounded-lg px-3 py-2 bg-slate-50">
          <div class="flex justify-between">
            <b>${escapeHtml(r.codigo_acceso)}</b>
            <span class="${r.resultado === 'permitido' ? 'text-emerald-600' : 'text-rose-600'}">
              ${r.resultado}
            </span>
          </div>
          <div class="text-[11px]">${r.tipo_evento} · ${r.fecha_hora}</div>
        </div>
      `).join('');
    }

    /* ---------- CAMARA ---------- */
    async function openCamera() {
      els.cameraModal.classList.remove('hidden');

      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' }
      });

      els.video.srcObject = stream;
      detector = new BarcodeDetector({ formats: ['qr_code'] });
      scanLoop();
    }

    async function scanLoop() {
    if (!detector || !els.video || !stream) return;

    if (!scanning) {
        const codes = await detector.detect(els.video);
        if (codes.length) {
        scanning = true;
        buscar(codes[0].rawValue);
        setTimeout(() => scanning = false, 2500);
        }
    }

    requestAnimationFrame(scanLoop);
    }


    function closeCamera() {
      els.cameraModal.classList.add('hidden');
      if (stream) stream.getTracks().forEach(t => t.stop());
      stream = null;
    }

    /* ---------- events ---------- */
    els.btnBuscar.addEventListener('click', () =>
      buscar(els.codigo.value.trim())
    );

    els.btnEntrada.addEventListener('click', () => registrar('entrada'));
    els.btnSalida.addEventListener('click', () => registrar('salida'));

    els.btnOpenCamera.addEventListener('click', openCamera);
    els.btnCloseCamera.addEventListener('click', closeCamera);
    els.btnRefrescar.addEventListener('click', loadHist);

    enableActions(false);
    loadHist();
  };
})();
