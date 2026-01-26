console.log('[GUARDIAS] JS ACTIVO');

(function () {
  if (window.__admin_guardias_init) return;
  window.__admin_guardias_init = true;

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/guardias.php';

  const els = {
    alert: document.getElementById('guardiasAlert'),
    list: document.getElementById('guardiasList'),
    btnAdd: document.getElementById('btnAddGuardia'),

    modal: document.getElementById('guardiaModal'),
    form: document.getElementById('guardiaForm'),
    btnClose: document.getElementById('btnCloseGuardiaModal'),
    modalError: document.getElementById('guardiaModalError'),
  };

  if (!els.list) {
    console.warn('[GUARDIAS] guardiasList no existe');
    return;
  }

  function showAlert(msg, isError = false) {
    if (!els.alert) return;
    els.alert.textContent = msg;
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (isError ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
    els.alert.classList.remove('hidden');
    setTimeout(() => els.alert.classList.add('hidden'), 3500);
  }

  function showModalError(msg) {
    if (!els.modalError) {
      // fallback al alert superior si no existe el div del modal
      showAlert(msg, true);
      return;
    }
    els.modalError.textContent = msg;
    els.modalError.classList.remove('hidden');
  }

  function clearModalError() {
    if (!els.modalError) return;
    els.modalError.textContent = '';
    els.modalError.classList.add('hidden');
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });

    // por si PHP muere y regresa HTML (500), agarramos el texto
    const text = await res.text();
    let json = null;
    try {
      json = JSON.parse(text);
    } catch (e) {
      throw new Error(text || 'Respuesta inválida del servidor');
    }

    if (!json || !json.ok) throw new Error((json && json.error) || 'Error');
    return json;
  }

  function phoneDigits(raw) {
    const d = String(raw || '').replace(/\D+/g, '');
    return d || '';
  }
  function phoneWaMx(digits) {
    if (digits && digits.length === 10) return '52' + digits;
    return digits;
  }

  function badgeCuenta(isActive) {
    return isActive
      ? `<span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] bg-emerald-100 text-emerald-700">Activo</span>`
      : `<span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] bg-slate-200 text-slate-700">Inactivo</span>`;
  }

  function toggleHTML({ id, enServicio }) {
    return `
      <button
        class="js-toggle inline-flex items-center h-7 w-12 rounded-full transition ${
          enServicio ? 'bg-emerald-500' : 'bg-slate-400'
        }"
        data-id="${id}"
        data-servicio="${enServicio ? 1 : 0}"
        type="button"
        title="Cambiar servicio"
      >
        <span class="sr-only">Cambiar servicio</span>
        <span class="inline-block h-6 w-6 rounded-full bg-white shadow transform transition ${
          enServicio ? 'translate-x-5' : 'translate-x-1'
        }"></span>
      </button>
      <div class="text-[11px] text-slate-500 mt-1">
        ${enServicio ? 'En servicio' : 'Descanso'}
      </div>
    `;
  }

  function render(guardias) {
    els.list.innerHTML = '';

    if (!guardias.length) {
      els.list.innerHTML = `
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
          No hay guardias registrados.
        </div>`;
      return;
    }

    guardias.forEach(g => {
      const telDigits = phoneDigits(g.telefono);
      const telWa = phoneWaMx(telDigits);
      const enServicio = Number(g.guardia_en_servicio || 0) === 1;

      const row = document.createElement('div');
      row.className =
        'grid grid-cols-12 gap-3 items-center bg-white border border-slate-200 rounded-2xl px-4 py-4';

      row.innerHTML = `
        <div class="col-span-4 min-w-0">
          <div class="font-semibold text-slate-900 truncate">${g.name || '—'}</div>
          <div class="text-xs text-slate-500 truncate">${g.email || ''}</div>
        </div>

        <div class="col-span-3">
          ${
            telDigits
              ? `
                <div class="flex flex-wrap gap-2">
                  <a href="tel:${telDigits}" class="inline-flex items-center gap-1 px-3 py-1 rounded-full border text-[11px] hover:bg-slate-50">
                    📞 Llamar
                  </a>
                  <a href="https://wa.me/${telWa}" target="_blank"
                     class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-emerald-100 text-emerald-700 text-[11px] hover:bg-emerald-200">
                    💬 WhatsApp
                  </a>
                </div>
                <div class="text-[11px] text-slate-500 mt-1">${g.telefono}</div>
              `
              : `<span class="text-[11px] text-slate-400">Sin teléfono</span>`
          }
        </div>

        <div class="col-span-2">
          ${toggleHTML({ id: g.id, enServicio })}
        </div>

        <div class="col-span-2">
          ${badgeCuenta(Number(g.is_active) === 1)}
        </div>

        <div class="col-span-1 text-right">
          <a
            href="admin_residencial_incidencias.php?modal=new&guardia_id=${g.id}"
            class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-rose-100 text-rose-700 text-[11px] hover:bg-rose-200"
            title="Reportar incidencia"
          >
            🚨
          </a>
        </div>
      `;

      els.list.appendChild(row);
    });
  }

  async function load() {
    try {
      const json = await fetchJSON(API);
      render(json.guardias || []);
    } catch (e) {
      showAlert(e.message || 'Error al cargar guardias', true);
    }
  }

  // Abrir modal
  els.btnAdd?.addEventListener('click', () => {
    clearModalError();
    els.form?.reset();
    els.modal?.classList.remove('hidden');
  });

  // Cerrar modal
  els.btnClose?.addEventListener('click', () => {
    els.modal?.classList.add('hidden');
    clearModalError();
  });

  // SUBMIT → CREAR GUARDIA
  els.form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearModalError();

    try {
      const fd = new FormData(els.form);

      // Validación rápida front
      const pass = String(fd.get('password') || '');
      const pass2 = String(fd.get('password_confirm') || '');
      if (pass.length < 6) {
        showModalError('La contraseña debe tener al menos 6 caracteres.');
        return;
      }
      if (pass !== pass2) {
        showModalError('Las contraseñas no coinciden.');
        return;
      }

      // Backend espera nombre en "nombre" (tu input se llama "name")
      fd.append('action', 'create_guardia');
      fd.append('nombre', fd.get('name') || '');
      fd.delete('name');

      const json = await fetchJSON(API, {
        method: 'POST',
        body: fd,
      });

      showAlert(json.message || 'Guardia creado correctamente');
      els.modal?.classList.add('hidden');
      els.form?.reset();
      clearModalError();
      load();
    } catch (err) {
      // ✅ error dentro del modal
      showModalError(err.message || 'Error al crear guardia');
    }
  });

  // Toggle servicio
  els.list.addEventListener('click', async (e) => {
    const btn = e.target.closest('.js-toggle');
    if (!btn) return;

    const id = btn.dataset.id;
    const actual = Number(btn.dataset.servicio || 0);
    const nuevo = actual === 1 ? 0 : 1;

    try {
      await fetchJSON(API, {
        method: 'POST',
        body: new URLSearchParams({
          action: 'toggle_servicio',
          guardia_id: String(id),
          nuevo_estado: String(nuevo),
        }),
      });

      showAlert(nuevo ? 'Guardia en servicio.' : 'Guardia en descanso.');
      load();
    } catch (err) {
      showAlert(err.message || 'Error al cambiar servicio', true);
    }
  });

  load();
})();
