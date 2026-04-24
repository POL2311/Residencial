(function () {
  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/guardias.php';
  const TURNOS_API = basePath() + 'php/api/guardias_turnos.php';

  const els = {
    view: document.getElementById('guardiasView'),
    alert: document.getElementById('guardiasAlert'),
    list: document.getElementById('guardiasList'),
    btnAdd: document.getElementById('btnAddGuardia'),

    modal: document.getElementById('guardiaModal'),
    form: document.getElementById('guardiaForm'),
    btnClose: document.getElementById('btnCloseGuardiaModal'),
    modalError: document.getElementById('guardiaModalError'),
  };

  if (!els.view || !els.list) return;

  const state = {
    guardias: [],
    editingId: null,
    supportsGuardiaServicio: true,
    selectedTurnosGuardiaId: null,
  };

  function escapeHtml(value = '') {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function showAlert(msg, isError = false) {
    if (!els.alert) return;
    els.alert.textContent = msg;
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (isError
        ? 'bg-rose-100 text-rose-700'
        : 'bg-emerald-100 text-emerald-700');
    els.alert.classList.remove('hidden');

    setTimeout(() => {
      els.alert.classList.add('hidden');
    }, 3500);
  }

  function showModalError(msg) {
    if (!els.modalError) {
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
    const res = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });

    const text = await res.text();
    let json = null;

    try {
      json = JSON.parse(text);
    } catch (e) {
      throw new Error(text || 'Respuesta inválida del servidor');
    }

    if (!json || !json.ok) {
      throw new Error((json && json.error) || 'Error');
    }

    return json;
  }

  function phoneDigits(raw) {
    return String(raw || '').replace(/\D+/g, '');
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
    if (!state.supportsGuardiaServicio) {
      return `
        <div class="text-[11px] text-slate-400">
          Sin soporte de turnos
        </div>
      `;
    }

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

  function servicioSummaryHTML(g) {
    const btnLabel = g.nombre_turno ? 'Ver más' : 'Asignar';
    return `
      <div class="flex items-center gap-3">
        ${toggleHTML({ id: g.id, enServicio: Number(g.guardia_en_servicio || 0) === 1 })}
        <button
          type="button"
          class="js-turno inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
          data-id="${g.id}"
        >
          ${btnLabel}
        </button>
      </div>
    `;
  }

  function getFormFields() {
    return {
      name: els.form?.querySelector('[name="name"]'),
      email: els.form?.querySelector('[name="email"]'),
      telefono: els.form?.querySelector('[name="telefono"]'),
      password: els.form?.querySelector('[name="password"]'),
      passwordConfirm: els.form?.querySelector('[name="password_confirm"]'),
    };
  }

  function setModalTitle(text) {
    const title = els.modal?.querySelector('h2');
    if (title) title.textContent = text;
  }

  function openCreateModal() {
    state.editingId = null;
    clearModalError();
    els.form?.reset();
    setModalTitle('Nuevo guardia');

    const { password, passwordConfirm } = getFormFields();
    if (password) {
      password.required = true;
      password.closest('div')?.classList.remove('hidden');
    }
    if (passwordConfirm) {
      passwordConfirm.required = true;
      passwordConfirm.closest('div')?.classList.remove('hidden');
    }

    els.modal?.classList.remove('hidden');
    els.modal?.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function openEditModal(guardia) {
    state.editingId = guardia.id;
    clearModalError();
    els.form?.reset();
    setModalTitle('Editar guardia');

    const fields = getFormFields();
    if (fields.name) fields.name.value = guardia.name || '';
    if (fields.email) fields.email.value = guardia.email || '';
    if (fields.telefono) fields.telefono.value = guardia.telefono || '';

    if (fields.password) {
      fields.password.value = '';
      fields.password.required = false;
      fields.password.closest('div')?.classList.add('hidden');
    }
    if (fields.passwordConfirm) {
      fields.passwordConfirm.value = '';
      fields.passwordConfirm.required = false;
      fields.passwordConfirm.closest('div')?.classList.add('hidden');
    }

    els.modal?.classList.remove('hidden');
    els.modal?.classList.add('flex');
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    els.modal?.classList.add('hidden');
    els.modal?.classList.remove('flex');
    clearModalError();
    state.editingId = null;
    els.form?.reset();
    document.body.style.overflow = '';

    const { password, passwordConfirm } = getFormFields();
    if (password) {
      password.required = true;
      password.closest('div')?.classList.remove('hidden');
    }
    if (passwordConfirm) {
      passwordConfirm.required = true;
      passwordConfirm.closest('div')?.classList.remove('hidden');
    }
  }

  async function load() {
    try {
      const json = await fetchJSON(API);
      state.guardias = json.guardias || [];
      state.supportsGuardiaServicio = !!json.supports_guardia_servicio;
      render(state.guardias);
    } catch (e) {
      showAlert(e.message || 'Error al cargar guardias', true);
    }
  }

  function render(guardias) {
    els.list.innerHTML = '';

    if (!guardias.length) {
      els.list.innerHTML = `
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
          No hay guardias registrados.
        </div>
      `;
      return;
    }

    guardias.forEach((g) => {
      const telDigits = phoneDigits(g.telefono);
      const telWa = phoneWaMx(telDigits);
      const enServicio = Number(g.guardia_en_servicio || 0) === 1;

      const row = document.createElement('div');
      row.className =
        'bg-white border border-slate-200 rounded-2xl px-5 py-4 shadow-sm';

      row.innerHTML = `
        <div class="space-y-4">
          <div class="md:hidden">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-semibold text-slate-900 truncate">${escapeHtml(g.name || '—')}</div>
                <div class="text-xs text-slate-500 truncate">${escapeHtml(g.email || '')}</div>
              </div>
              <div>${badgeCuenta(Number(g.is_active) === 1)}</div>
            </div>
            <div class="mt-3 space-y-3">
              <div>
                ${
                  telDigits
                    ? `
                      <div class="flex flex-wrap gap-2">
                        <a href="tel:${escapeHtml(telDigits)}" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                          Llamar
                        </a>
                        <a href="https://wa.me/${escapeHtml(telWa)}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-200">
                          WhatsApp
                        </a>
                      </div>
                      <div class="text-xs text-slate-500 mt-1">${escapeHtml(g.telefono || '')}</div>
                    `
                    : `<span class="text-[11px] text-slate-400">Sin teléfono</span>`
                }
              </div>
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                ${servicioSummaryHTML(g)}
              </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
              <button
                type="button"
                class="js-edit inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200"
                data-id="${g.id}">
                Editar
              </button>
              <button
                type="button"
                class="js-delete inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-200"
                data-id="${g.id}">
                Eliminar
              </button>
            </div>
          </div>

          <div class="hidden md:grid md:grid-cols-12 md:gap-4 md:items-center">
            <div class="col-span-3 min-w-0">
              <div class="font-semibold text-slate-900 truncate">${escapeHtml(g.name || '—')}</div>
              <div class="text-xs text-slate-500 truncate">${escapeHtml(g.email || '')}</div>
            </div>

            <div class="col-span-3">
              ${
                telDigits
                  ? `
                    <div class="flex flex-wrap gap-2">
                      <a href="tel:${escapeHtml(telDigits)}" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                        Llamar
                      </a>
                      <a href="https://wa.me/${escapeHtml(telWa)}" target="_blank" rel="noopener noreferrer"
                         class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-200">
                        WhatsApp
                      </a>
                    </div>
                    <div class="text-xs text-slate-500 mt-1">${escapeHtml(g.telefono || '')}</div>
                  `
                  : `<span class="text-[11px] text-slate-400">Sin teléfono</span>`
              }
            </div>

            <div class="col-span-3">
              ${servicioSummaryHTML(g)}
            </div>

            <div class="col-span-1">
              ${badgeCuenta(Number(g.is_active) === 1)}
            </div>

            <div class="col-span-2 flex justify-end gap-2 flex-wrap">
              <button
                type="button"
                class="js-edit inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200"
                data-id="${g.id}">
                Editar
              </button>

              <button
                type="button"
                class="js-delete inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-200"
                data-id="${g.id}">
                Eliminar
              </button>
            </div>
          </div>
        </div>
      `;

      els.list.appendChild(row);
    });
  }

  async function handleCreate(fd) {
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

    fd.append('action', 'create_guardia');
    fd.append('nombre', fd.get('name') || '');
    fd.delete('name');

    const json = await fetchJSON(API, {
      method: 'POST',
      body: fd,
    });

    showAlert(json.message || 'Guardia creado correctamente');
    closeModal();
    await load();
  }

  async function handleEdit(fd) {
    const id = state.editingId;

    fd.append('action', 'edit_guardia');
    fd.append('guardia_id', String(id));
    fd.append('nombre', fd.get('name') || '');
    fd.delete('name');
    fd.delete('password');
    fd.delete('password_confirm');

    const json = await fetchJSON(API, {
      method: 'POST',
      body: fd,
    });

    showAlert(json.message || 'Guardia actualizado correctamente');
    closeModal();
    await load();
  }

  function ensureUiHelpers() {
    if (document.getElementById('guardiasUiLayer')) return;

    const layer = document.createElement('div');
    layer.id = 'guardiasUiLayer';
    layer.innerHTML = `
      <div id="friendlyConfirmGuardia"
           class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden">
          <div class="p-6">
            <div class="flex items-start gap-4">
              <div id="friendlyConfirmGuardiaIcon"
                   class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold">
                !
              </div>
              <div class="flex-1">
                <h3 id="friendlyConfirmGuardiaTitle" class="text-xl font-semibold text-slate-900">
                  Confirmar acción
                </h3>
                <p id="friendlyConfirmGuardiaMessage" class="mt-2 text-sm leading-6 text-slate-600">
                  ¿Deseas continuar?
                </p>
              </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
              <button id="friendlyConfirmGuardiaCancel"
                      type="button"
                      class="rounded-full bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                Cancelar
              </button>
              <button id="friendlyConfirmGuardiaAccept"
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

  function showConfirmGuardia({
    title = 'Confirmar acción',
    message = '¿Deseas continuar?',
    acceptText = 'Aceptar',
    cancelText = 'Cancelar',
  } = {}) {
    ensureUiHelpers();

    return new Promise((resolve) => {
      const modal = document.getElementById('friendlyConfirmGuardia');
      const titleEl = document.getElementById('friendlyConfirmGuardiaTitle');
      const messageEl = document.getElementById('friendlyConfirmGuardiaMessage');
      const acceptBtn = document.getElementById('friendlyConfirmGuardiaAccept');
      const cancelBtn = document.getElementById('friendlyConfirmGuardiaCancel');

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

  async function handleDelete(id) {
    const ok = await showConfirmGuardia({
      title: 'Eliminar guardia',
      message: 'Esta acción eliminará al guardia del residencial. ¿Deseas continuar?',
      acceptText: 'Sí, eliminar',
      cancelText: 'Cancelar',
    });

    if (!ok) return;

    const fd = new FormData();
    fd.append('action', 'delete_guardia');
    fd.append('guardia_id', String(id));

    try {
      const json = await fetchJSON(API, {
        method: 'POST',
        body: fd,
      });

      showAlert(json.message || 'Guardia eliminado');
      await load();
    } catch (err) {
      showAlert(err.message || 'Error al eliminar guardia', true);
    }
  }

  async function handleToggle(id, nuevo) {
    try {
      const json = await fetchJSON(API, {
        method: 'POST',
        body: new URLSearchParams({
          action: 'toggle_servicio',
          guardia_id: String(id),
          nuevo_estado: String(nuevo),
        }),
      });

      showAlert(json.message || (nuevo ? 'Guardia en servicio.' : 'Guardia en descanso.'));
      await load();
    } catch (err) {
      showAlert(err.message || 'Error al cambiar servicio', true);
    }
  }

  els.btnAdd?.addEventListener('click', openCreateModal);
  els.btnClose?.addEventListener('click', closeModal);

  els.modal?.addEventListener('click', (e) => {
    if (e.target === els.modal) closeModal();
  });

  els.form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearModalError();

    try {
      const fd = new FormData(els.form);

      if (state.editingId) {
        await handleEdit(fd);
      } else {
        await handleCreate(fd);
      }
    } catch (err) {
      showModalError(err.message || 'Error al guardar guardia');
    }
  });

  async function fetchTurnos(guardiaId) {
    return fetchJSON(`${TURNOS_API}?action=list&guardia_id=${encodeURIComponent(guardiaId)}`);
  }

  function renderTurnosList(turnos, guardiaId) {
    if (!turnos.length) {
      return `<div class="text-sm text-slate-500">No hay turnos registrados.</div>`;
    }

    return `
      <div class="space-y-3">
        ${turnos.map(t => `
          <div class="rounded-2xl border border-slate-200 p-4">
            <div class="flex items-center justify-between gap-3">
              <div>
                <div class="font-semibold text-slate-800">${escapeHtml(t.nombre_turno)}</div>
                <div class="text-sm text-slate-500">${escapeHtml(t.hora_inicio)} - ${escapeHtml(t.hora_fin)}</div>
                <div class="text-xs text-slate-400">${escapeHtml(t.dias_semana || '')}</div>
              </div>
              <div class="flex gap-2">
                <button class="js-edit-turno px-3 py-2 rounded-xl bg-slate-100 text-slate-700 text-xs"
                  data-turno-id="${t.id}"
                  data-guardia-id="${guardiaId}"
                  data-nombre="${escapeHtml(t.nombre_turno)}"
                  data-inicio="${escapeHtml(t.hora_inicio)}"
                  data-fin="${escapeHtml(t.hora_fin)}"
                  data-dias="${escapeHtml(t.dias_semana || '')}"
                  data-activo="${t.activo}">
                  Editar
                </button>
                <button class="js-delete-turno px-3 py-2 rounded-xl bg-rose-100 text-rose-700 text-xs"
                  data-turno-id="${t.id}"
                  data-guardia-id="${guardiaId}">
                  Eliminar
                </button>
              </div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  function validateTurnoForm(fd) {
    const nombre = String(fd.get('nombre_turno') || '').trim();
    const horaInicio = String(fd.get('hora_inicio') || '').trim();
    const horaFin = String(fd.get('hora_fin') || '').trim();
    const dias = String(fd.get('dias_semana') || '').trim().toUpperCase();

    if (!nombre) {
      throw new Error('El nombre del turno es obligatorio.');
    }

    if (nombre.length > 50) {
      throw new Error('El nombre del turno no puede exceder 50 caracteres.');
    }

    if (!horaInicio || !horaFin) {
      throw new Error('Debes capturar hora de inicio y hora fin.');
    }

    if (horaInicio === horaFin) {
      throw new Error('La hora de inicio y fin no pueden ser iguales.');
    }

    const diasRegex = /^(LUN|MAR|MIE|JUE|VIE|SAB|DOM)(,(LUN|MAR|MIE|JUE|VIE|SAB|DOM))*$/;
    if (!diasRegex.test(dias)) {
      throw new Error('Los días deben tener formato como: LUN,MAR,MIE,JUE,VIE');
    }
  }

  async function handleDeleteTurno({ turnoId, guardiaId, guardia, listWrap }) {
    const ok = await showConfirmGuardia({
      title: 'Eliminar turno',
      message: 'Esta acción eliminará el turno seleccionado. ¿Deseas continuar?',
      acceptText: 'Sí, eliminar',
      cancelText: 'Cancelar',
    });

    if (!ok) return;

    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('turno_id', turnoId);
    fd.append('guardia_id', guardiaId);

    try {
      const json = await fetchJSON(TURNOS_API, {
        method: 'POST',
        body: fd,
      });

      showAlert(json.message || 'Turno eliminado correctamente.');
      listWrap.innerHTML = renderTurnosList(json.turnos || [], guardia.id);
      await load();
    } catch (err) {
      showAlert(err.message || 'Error al eliminar turno', true);
    }
  }

  function openTurnoModal(guardia, turnos = []) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[10020] p-4';
    modal.innerHTML = `
      <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between border-b px-5 py-4">
          <div>
            <h3 class="text-lg font-semibold text-slate-900">Turnos de guardia</h3>
            <p class="text-sm text-slate-500">${escapeHtml(guardia.name)} · ${escapeHtml(guardia.email || '')}</p>
          </div>
          <button class="js-close-turno-modal h-9 w-9 rounded-full bg-slate-100 hover:bg-slate-200">✕</button>
        </div>

        <div class="grid md:grid-cols-2 gap-6 p-5">
          <div>
            <form id="guardiaTurnoForm" class="space-y-3">
              <input type="hidden" name="turno_id">
              <input type="hidden" name="guardia_id" value="${guardia.id}">

              <div>
                <label class="block text-sm font-medium mb-1">Nombre del turno</label>
                <input name="nombre_turno" class="w-full rounded-xl border px-3 py-2" placeholder="Ej. Matutino" required>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-sm font-medium mb-1">Hora inicio</label>
                  <input type="time" name="hora_inicio" class="w-full rounded-xl border px-3 py-2" required>
                </div>
                <div>
                  <label class="block text-sm font-medium mb-1">Hora fin</label>
                  <input type="time" name="hora_fin" class="w-full rounded-xl border px-3 py-2" required>
                </div>
              </div>

              <div>
                <label class="block text-sm font-medium mb-1">Días</label>
                <input name="dias_semana" class="w-full rounded-xl border px-3 py-2"
                  placeholder="LUN,MAR,MIE,JUE,VIE" required>
                <div class="text-[11px] text-slate-500 mt-1">Formato: LUN,MAR,MIE,JUE,VIE</div>
              </div>

              <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="activo" checked>
                Dejar como turno activo
              </label>

              <div class="flex justify-end gap-2 pt-2">
                <button type="button" class="js-reset-turno-form px-4 py-2 rounded-xl border">Limpiar</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-[#2E5D73] text-white">Guardar turno</button>
              </div>
            </form>
          </div>

          <div>
            <div class="mb-3 text-sm font-semibold text-slate-700">Turnos registrados</div>
            <div id="guardiaTurnosList">
              ${renderTurnosList(turnos, guardia.id)}
            </div>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(modal);

    const close = () => modal.remove();

    modal.querySelector('.js-close-turno-modal')?.addEventListener('click', close);
    modal.addEventListener('click', (e) => {
      if (e.target === modal) close();
    });

    const form = modal.querySelector('#guardiaTurnoForm');
    const listWrap = modal.querySelector('#guardiaTurnosList');

    modal.querySelector('.js-reset-turno-form')?.addEventListener('click', () => {
      form.reset();
      form.querySelector('[name="turno_id"]').value = '';
      form.querySelector('[name="activo"]').checked = true;
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const fd = new FormData(form);
      const isUpdate = !!fd.get('turno_id');
      fd.append('action', isUpdate ? 'update' : 'create');

      try {
        validateTurnoForm(fd);

        const json = await fetchJSON(TURNOS_API, {
          method: 'POST',
          body: fd,
        });

        showAlert(json.message || 'Turno guardado correctamente.');
        form.reset();
        form.querySelector('[name="turno_id"]').value = '';
        form.querySelector('[name="activo"]').checked = true;
        listWrap.innerHTML = renderTurnosList(json.turnos || [], guardia.id);
        await load();
      } catch (err) {
        showAlert(err.message || 'Error al guardar turno', true);
      }
    });

    listWrap.addEventListener('click', async (e) => {
      const editBtn = e.target.closest('.js-edit-turno');
      const deleteBtn = e.target.closest('.js-delete-turno');

      if (editBtn) {
        form.querySelector('[name="turno_id"]').value = editBtn.dataset.turnoId || '';
        form.querySelector('[name="nombre_turno"]').value = editBtn.dataset.nombre || '';
        form.querySelector('[name="hora_inicio"]').value = editBtn.dataset.inicio || '';
        form.querySelector('[name="hora_fin"]').value = editBtn.dataset.fin || '';
        form.querySelector('[name="dias_semana"]').value = editBtn.dataset.dias || '';
        form.querySelector('[name="activo"]').checked = Number(editBtn.dataset.activo || 0) === 1;
        return;
      }

      if (deleteBtn) {
        await handleDeleteTurno({
          turnoId: deleteBtn.dataset.turnoId,
          guardiaId: deleteBtn.dataset.guardiaId,
          guardia,
          listWrap,
        });
      }
    });
  }

  async function handleTurno(guardiaId) {
    const guardia = state.guardias.find(g => Number(g.id) === Number(guardiaId));
    if (!guardia) return;

    try {
      const json = await fetchTurnos(guardiaId);
      openTurnoModal(guardia, json.turnos || []);
    } catch (err) {
      showAlert(err.message || 'Error al cargar turnos', true);
    }
  }

  els.btnAdd?.addEventListener('click', openCreateModal);
  els.btnClose?.addEventListener('click', closeModal);

  els.modal?.addEventListener('click', (e) => {
    if (e.target === els.modal) closeModal();
  });

  els.form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearModalError();

    try {
      const fd = new FormData(els.form);

      if (state.editingId) {
        await handleEdit(fd);
      } else {
        await handleCreate(fd);
      }
    } catch (err) {
      showModalError(err.message || 'Error al guardar guardia');
    }
  });

  els.list.addEventListener('click', async (e) => {
    const toggleBtn = e.target.closest('.js-toggle');
    const turnoBtn = e.target.closest('.js-turno');
    const editBtn = e.target.closest('.js-edit');
    const deleteBtn = e.target.closest('.js-delete');

    if (toggleBtn) {
      const id = Number(toggleBtn.dataset.id || 0);
      const actual = Number(toggleBtn.dataset.servicio || 0);
      const nuevo = actual === 1 ? 0 : 1;
      await handleToggle(id, nuevo);
      return;
    }

    if (turnoBtn) {
      const id = Number(turnoBtn.dataset.id || 0);
      await handleTurno(id);
      return;
    }

    if (editBtn) {
      const id = Number(editBtn.dataset.id || 0);
      const guardia = state.guardias.find((g) => Number(g.id) === id);
      if (guardia) openEditModal(guardia);
      return;
    }

    if (deleteBtn) {
      const id = Number(deleteBtn.dataset.id || 0);
      await handleDelete(id);
    }
  });

  ensureUiHelpers();
  load();
})();
