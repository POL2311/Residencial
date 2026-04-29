(function () {
  window.GuardiaViews = window.GuardiaViews || {};

  window.GuardiaViews.accesos = function ({ API, fetchJSON, escapeHtml }) {
    const els = {
      codigo: document.getElementById('codigo'),
      alert: document.getElementById('accesosAlert'),
      resultado: document.getElementById('accesoResultado'),
      estadoBadge: document.getElementById('accesoEstadoBadge'),
      miniStatus: document.getElementById('accesoMiniStatus'),
      dynamicForm: document.getElementById('accesoDynamicForm'),
      pinWrap: document.getElementById('accessPinWrap'),
      pin: document.getElementById('accessPin'),
      evidenceWrap: document.getElementById('accessEvidenceWrap'),
      evidence: document.getElementById('accessEvidence'),
      notes: document.getElementById('accessNotes'),

      btnBuscar: document.getElementById('btnBuscar'),
      btnEntrada: document.getElementById('btnConfirmarEntrada'),
      btnSalida: document.getElementById('btnConfirmarSalida'),

      btnOpenCamera: document.getElementById('btnOpenCamera'),
      cameraModal: document.getElementById('cameraModal'),
      cameraHint: document.getElementById('cameraHint'),
      video: document.getElementById('video'),
      btnCloseCamera: document.getElementById('btnCloseCamera'),

      residentSection: document.getElementById('residentDirectSection'),
      residentSearch: document.getElementById('residentDirectSearch'),
      residentAlert: document.getElementById('residentDirectAlert'),
      residentResults: document.getElementById('residentDirectResults'),
      btnResidentSearch: document.getElementById('btnBuscarResidenteDirecto'),
      btnResidentEntrada: document.getElementById('btnConfirmarEntradaResidente'),
      btnResidentSalida: document.getElementById('btnConfirmarSalidaResidente'),

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
      scanning: false,
      loadingSearch: false,
      loadingRegister: false,
      histItems: [],
      histPage: 1,
      histPerPage: 5,
      rafId: null,
      searchToken: 0,
      registerToken: 0,
      operationalMode: window.GuardiaDashboard?.getOperationalMode?.() || 'residencial',
    };

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

    function setButtonsDisabled(disabled) {
      if (els.btnEntrada) els.btnEntrada.disabled = disabled;
      if (els.btnSalida) els.btnSalida.disabled = disabled;
    }

    function setResidentButtonsDisabled(disabled) {
      if (els.btnResidentEntrada) els.btnResidentEntrada.disabled = disabled;
      if (els.btnResidentSalida) els.btnResidentSalida.disabled = disabled;
    }

    function enableActions(on) {
      const disabled = !on || state.loadingSearch || state.loadingRegister;
      setButtonsDisabled(disabled);
      const residentDisabled = !state.currentResident?.access?.allow_direct_access || state.loadingRegister || state.loadingSearch;
      setResidentButtonsDisabled(residentDisabled);
    }

    function setLoadingSearch(on, text = 'Buscando…') {
      state.loadingSearch = on;
      if (els.btnBuscar) {
        els.btnBuscar.disabled = on;
        els.btnBuscar.textContent = on ? text : 'Buscar';
      }
      if (els.codigo) els.codigo.disabled = on;
      enableActions(!!state.current);
    }

    function setLoadingRegister(on, tipo = 'entrada') {
      state.loadingRegister = on;
      if (els.btnEntrada) {
        els.btnEntrada.textContent = on && tipo === 'entrada' ? 'Registrando…' : 'Registrar entrada';
      }
      if (els.btnSalida) {
        els.btnSalida.textContent = on && tipo === 'salida' ? 'Registrando…' : 'Registrar salida';
      }
      enableActions(!!state.current);
    }

    function updateStatusUI(type, text) {
      const classes =
        type === 'ok'
          ? 'bg-emerald-100 text-emerald-700'
          : type === 'error'
          ? 'bg-rose-100 text-rose-700'
          : 'bg-slate-100 text-slate-700';

      if (els.estadoBadge) {
        els.estadoBadge.className = `mt-3 inline-flex items-center rounded-full px-3 py-1 text-sm ${classes}`;
        els.estadoBadge.textContent = text;
      }
      if (els.miniStatus) {
        els.miniStatus.classList.remove('hidden');
        els.miniStatus.className = `rounded-full px-3 py-1 text-xs font-medium ${classes}`;
        els.miniStatus.textContent = text;
      }
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
      resetDynamicForm();
      enableActions(false);

      if (els.resultado) {
        els.resultado.textContent = 'Ingresa un código para validar el acceso.';
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
      if (!els.residentResults || !isAlive()) return;
      const access = resident?.access || {};
      const allow = !!access.allow_direct_access;
      els.residentResults.innerHTML = `
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
    }

    function renderVisitResult(visita, ev) {
      const permitido = !!ev?.permitido;
      els.resultado.innerHTML = `
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
      els.resultado.innerHTML = `
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
              <div class="mt-3 inline-flex rounded-full px-3 py-1 text-xs ${persona?.esta_dentro ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-700'}">${persona?.esta_dentro ? 'Actualmente dentro' : 'Actualmente fuera'}</div>
            </div>
          </div>
        </div>
      `;
      showDynamicForm({ pin: true, evidence: false });
    }

    function renderVisitanteOperativoResult(item) {
      const evalInfo = item?.eval || {};
      els.resultado.innerHTML = `
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
      showDynamicForm({ pin: false, evidence: true });
    }

    function renderPermisoResult(item) {
      const evalInfo = item?.eval || {};
      els.resultado.innerHTML = `
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
      showDynamicForm({ pin: false, evidence: true });
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

        if (state.currentKind === 'persona_recurrente') {
          state.current = data.persona || null;
          feedback(true);
          renderPersonaResult(state.current);
          updateStatusUI('ok', 'PIN requerido');
        } else if (state.currentKind === 'visitante_rapido') {
          state.current = data.visitante_rapido || null;
          feedback(!!state.current?.eval?.permitido);
          renderVisitanteOperativoResult(state.current);
          updateStatusUI(state.current?.eval?.permitido ? 'ok' : 'error', state.current?.eval?.permitido ? 'Listo para validar' : 'Con restricciones');
        } else if (state.currentKind === 'permiso_material') {
          state.current = data.permiso_material || null;
          feedback(!!state.current?.eval?.permitido);
          renderPermisoResult(state.current);
          updateStatusUI(state.current?.eval?.permitido ? 'ok' : 'error', state.current?.eval?.permitido ? 'Listo para validar' : 'Con restricciones');
        } else {
          state.current = data.visita || null;
          const ev = data.eval || {};
          feedback(ev.permitido);
          renderVisitResult(state.current, ev);
          updateStatusUI(ev.permitido ? 'ok' : 'error', ev.permitido ? 'Permitido' : 'Denegado');
        }

        enableActions(true);
      } catch (e) {
        if (!isAlive() || token !== state.searchToken) return;
        state.current = null;
        state.currentKind = null;
        feedback(false);
        resetDynamicForm();
        if (els.resultado) {
          els.resultado.innerHTML = `<div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">Código no válido o no encontrado.</div>`;
        }
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
        residentAlertMsg('');
        const json = await fetchJSON(`${API}accesos.php`, { method: 'POST', body: fd });
        state.currentResident = { ...state.currentResident, access: json.access || state.currentResident.access };
        renderResidentResult(state.currentResident);
        residentAlertMsg(json.message || 'Acceso de residente procesado correctamente.', 'success');
        await loadHist();
      } catch (e) {
        residentAlertMsg(e.message || 'No se pudo registrar el acceso del residente.');
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
      } else if (state.currentKind === 'visitante_rapido') {
        action = 'confirm_visitante_rapido';
        fd.set('visitante_id', String(state.current.id));
        if (els.evidence?.files?.[0]) fd.append('evidencia', els.evidence.files[0]);
      } else if (state.currentKind === 'permiso_material') {
        action = 'confirm_permiso_material';
        fd.set('permiso_material_id', String(state.current.id));
        if (els.evidence?.files?.[0]) fd.append('evidencia', els.evidence.files[0]);
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
          renderVisitanteOperativoResult(state.current);
        } else if (state.currentKind === 'permiso_material') {
          state.current = json.data?.permiso_material || state.current;
          renderPermisoResult(state.current);
        } else {
          if (els.resultado) {
            els.resultado.innerHTML = `
              <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                <div class="text-emerald-700 font-semibold">✔ ${tipo === 'entrada' ? 'Entrada registrada' : 'Salida registrada'}</div>
                <div class="text-sm text-slate-600 mt-1">${safeText(json.visita?.nombre_visitante || state.current?.nombre_visitante)}</div>
              </div>
            `;
          }
        }

        await loadHist();

        setTimeout(() => {
          if (isAlive() && json.permitido) {
            resetResultArea();
          }
        }, 1500);
      } catch (e) {
        if (!isAlive() || token !== state.registerToken) return;
        alertMsg(e.message || 'No se pudo registrar el acceso.');
      } finally {
        if (isAlive() && token === state.registerToken) {
          setLoadingRegister(false, tipo);
          enableActions(!!state.current);
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
        if (els.cameraHint) els.cameraHint.textContent = 'Esperando código QR…';
        state.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
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
              if (isAlive()) state.scanning = false;
            }, 2000);
          }
        }
      } catch (_) {}
      if (isAlive()) state.rafId = requestAnimationFrame(scanLoop);
    }

    function closeCamera() {
      els.cameraModal?.classList.add('hidden');
      els.cameraModal?.classList.remove('flex');
      if (state.rafId) cancelAnimationFrame(state.rafId);
      state.rafId = null;
      if (state.stream) state.stream.getTracks().forEach((track) => track.stop());
      if (els.video) els.video.srcObject = null;
      state.stream = null;
      state.detector = null;
      state.scanning = false;
      if (els.cameraHint) els.cameraHint.textContent = 'Esperando código QR…';
    }

    function bindEvents() {
      els.btnBuscar?.addEventListener('click', () => buscar(els.codigo?.value?.trim()));
      els.btnResidentSearch?.addEventListener('click', () => buscarResidente(els.residentSearch?.value?.trim()));
      els.btnEntrada?.addEventListener('click', () => registrar('entrada'));
      els.btnSalida?.addEventListener('click', () => registrar('salida'));
      els.btnResidentEntrada?.addEventListener('click', () => registrarResidente('entrada'));
      els.btnResidentSalida?.addEventListener('click', () => registrarResidente('salida'));
      els.btnOpenCamera?.addEventListener('click', openCamera);
      els.btnCloseCamera?.addEventListener('click', closeCamera);
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
      els.residentResults?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-resident-select]');
        if (!btn) return;
        const resident = state.residentSearchItems[Number(btn.dataset.residentSelect || -1)];
        if (!resident) return;
        state.currentResident = resident;
        renderResidentResult(resident);
      });
    }

    function unbindEvents() {
      // view se desmonta completa; no necesitamos granularidad adicional
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
