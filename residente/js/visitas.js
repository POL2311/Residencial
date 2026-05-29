(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentVisitasView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/visitas.php';
    const QR_BASE = 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&format=png&data=';
    const els = {
        alert: document.getElementById('visitasAlert'),
        list: document.getElementById('residentVisitasList'),
        btnNew: document.getElementById('btnNuevaVisitaResident'),
        modal: document.getElementById('residentVisitasModal'),
        modalTitle: document.getElementById('residentVisitasModalTitle'),
        form: document.getElementById('residentVisitasForm'),
        btnClose: document.getElementById('btnCloseResidentVisitasModal'),
        btnCancel: document.getElementById('btnCancelResidentVisitasModal'),
        optionalToggle: document.getElementById('btnToggleResidentVisitasOptional'),
        optionalPanel: document.getElementById('residentVisitasOptional'),
        optionalIcon: document.getElementById('residentVisitasOptionalIcon'),
        scheduleHint: document.getElementById('residentVisitasScheduleHint'),
        durationButtons: root.querySelectorAll('[data-duration-preset]'),
        codeModal: document.getElementById('residentVisitasCodeModal'),
        codeValue: document.getElementById('residentVisitasCodeValue'),
        btnCloseCode: document.getElementById('btnCloseResidentVisitasCodeModal'),
        btnDismissCode: document.getElementById('btnDismissResidentVisitasCodeModal'),
        btnCopyCode: document.getElementById('btnCopyResidentVisitasCode'),
        btnDownloadQr: document.getElementById('btnDownloadResidentVisitasQr'),
        qrImage: document.getElementById('residentVisitasQrImage'),
        qrHint: document.getElementById('residentVisitasQrHint'),
    };

    let items = [];
    let currentCode = '';
    let isApplyingPreset = false;

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function formatDateInput(date) {
        return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
    }

    function formatTimeInput(date) {
        return `${pad2(date.getHours())}:${pad2(date.getMinutes())}`;
    }

    function parseDateInput(value) {
        const parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || parts.some(Number.isNaN)) return null;
        return new Date(parts[0], parts[1] - 1, parts[2], 0, 0, 0, 0);
    }

    function parseTimeParts(value) {
        const parts = String(value || '').split(':').map(Number);
        if (parts.length < 2 || parts.some(Number.isNaN)) return null;
        return { hours: parts[0], minutes: parts[1] };
    }

    function combineDateTime(dateValue, timeValue) {
        const date = parseDateInput(dateValue) || new Date();
        const time = parseTimeParts(timeValue);
        if (time) date.setHours(time.hours, time.minutes, 0, 0);
        return date;
    }

    function addMinutes(date, minutes) {
        return new Date(date.getTime() + minutes * 60 * 1000);
    }

    function getScheduleFields() {
        if (!els.form) return {};
        return {
            fechaDesde: els.form.querySelector('[name="fecha_desde"]'),
            fechaHasta: els.form.querySelector('[name="fecha_hasta"]'),
            horaDesde: els.form.querySelector('[name="hora_desde"]'),
            horaHasta: els.form.querySelector('[name="hora_hasta"]'),
        };
    }

    function setScheduleFields(start, end) {
        const fields = getScheduleFields();
        if (fields.fechaDesde) fields.fechaDesde.value = formatDateInput(start);
        if (fields.fechaHasta) fields.fechaHasta.value = formatDateInput(end);
        if (fields.horaDesde) fields.horaDesde.value = formatTimeInput(start);
        if (fields.horaHasta) fields.horaHasta.value = formatTimeInput(end);
    }

    function setDurationActive(preset) {
        els.durationButtons?.forEach((btn) => {
            const isActive = btn.dataset.durationPreset === preset;
            btn.setAttribute('aria-pressed', String(isActive));
            btn.classList.toggle('border-[#4E7287]', isActive);
            btn.classList.toggle('bg-[#4E7287]', isActive);
            btn.classList.toggle('text-white', isActive);
            btn.classList.toggle('border-slate-200', !isActive);
            btn.classList.toggle('bg-white', !isActive);
            btn.classList.toggle('text-slate-700', !isActive);
        });
    }

    function setScheduleHint(text) {
        if (els.scheduleHint) els.scheduleHint.textContent = text;
    }

    function applyDurationPreset(preset) {
        const fields = getScheduleFields();
        if (!fields.fechaDesde || !fields.fechaHasta || !fields.horaDesde || !fields.horaHasta) return;

        isApplyingPreset = true;
        const now = new Date();
        const currentStart = combineDateTime(fields.fechaDesde.value || formatDateInput(now), fields.horaDesde.value || formatTimeInput(now));
        let start = currentStart;
        let end = addMinutes(start, 60);
        let hint = 'Duración sugerida: 1 hora';

        if (preset === '2h') {
            end = addMinutes(start, 120);
            hint = 'Duración sugerida: 2 horas';
        } else if (preset === 'day') {
            start = parseDateInput(fields.fechaDesde.value) || now;
            start.setHours(0, 0, 0, 0);
            end = new Date(start);
            end.setHours(23, 59, 0, 0);
            hint = 'Acceso disponible todo el día';
        } else if (preset === 'tomorrow') {
            start = new Date(now);
            start.setDate(start.getDate() + 1);
            start.setHours(9, 0, 0, 0);
            end = addMinutes(start, 60);
            hint = 'Programado para mañana';
        }

        setScheduleFields(start, end);
        setDurationActive(preset);
        setScheduleHint(hint);
        isApplyingPreset = false;
    }

    function setOptionalDetails(open) {
        if (!els.optionalToggle || !els.optionalPanel) return;
        els.optionalPanel.classList.toggle('hidden', !open);
        els.optionalToggle.setAttribute('aria-expanded', String(open));
        if (els.optionalIcon) els.optionalIcon.textContent = open ? '⌃' : '⌄';
    }

    function markScheduleAsManual() {
        if (isApplyingPreset) return;
        const fields = getScheduleFields();
        if (fields.fechaDesde && fields.fechaHasta && fields.fechaDesde.value && (!fields.fechaHasta.value || fields.fechaHasta.value < fields.fechaDesde.value)) {
            fields.fechaHasta.value = fields.fechaDesde.value;
        }
        setDurationActive('');
        setScheduleHint('Horario ajustado manualmente');
    }

    function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm border ${type === 'ok' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'}`;
        els.alert.textContent = msg;
    }

    function qrUrl(code) {
        return `${QR_BASE}${encodeURIComponent(String(code || '').trim())}`;
    }

    async function renderQr(code) {
        if (!els.qrImage) return;
        if (!code) {
            if (els.qrHint) els.qrHint.textContent = 'No hay código disponible para generar el QR.';
            els.qrImage.removeAttribute('src');
            return;
        }

        if (els.qrHint) els.qrHint.textContent = 'Generando QR…';
        els.qrImage.onload = () => {
            if (els.qrHint) els.qrHint.textContent = 'Listo para escanear en caseta.';
        };
        els.qrImage.onerror = () => {
            if (els.qrHint) els.qrHint.textContent = 'No pudimos generar el QR. Usa el código manual.';
        };
        els.qrImage.src = qrUrl(code);
    }

    function renderUnavailable(message) {
        if (els.list) {
            els.list.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">${escapeHtml(message)}</div>`;
        }
    }

    function statusBadge(status) {
        if (status === 'usado') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        if (status === 'cancelado') return 'bg-rose-50 text-rose-700 border border-rose-200';
        if (status === 'vencido') return 'bg-slate-100 text-slate-600 border border-slate-200';
        return 'bg-amber-50 text-amber-700 border border-amber-200';
    }

    async function api(data) {
        const fd = new FormData();
        Object.keys(data || {}).forEach((key) => fd.append(key, data[key]));
        const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json().catch(() => null);
        if (!res.ok) {
            if (res.status === 404) throw new Error('No se encontró el endpoint de visitas. Verifica que XAMPP esté sirviendo esta copia del proyecto.');
            if (res.status === 403 || res.status === 422) throw new Error(json?.error || 'Tu cuenta residente no tiene una unidad activa configurada.');
            throw new Error(json?.error || 'Error de servidor');
        }
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.form.reset();
        setOptionalDetails(false);
        document.body.style.overflow = '';
    }

    function openFormModal() {
        els.modalTitle.textContent = 'Nueva visita';
        if (els.form) {
            els.form.reset();
            const now = new Date();
            const plusOneHour = new Date(now.getTime() + 60 * 60 * 1000);
            setScheduleFields(now, plusOneHour);
            const defaultType = els.form.querySelector('[name="tipo"][value="visita"]');
            if (defaultType) defaultType.checked = true;
            setOptionalDetails(false);
            applyDurationPreset('1h');
        }
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCodeModal() {
        if (!els.codeModal) return;
        els.codeModal.classList.add('hidden');
        currentCode = '';
        if (els.qrImage) {
            els.qrImage.removeAttribute('src');
            els.qrImage.onload = null;
            els.qrImage.onerror = null;
        }
        if (els.qrHint) els.qrHint.textContent = 'El guardia puede escanear este QR o capturar el código manualmente.';
        document.body.style.overflow = '';
    }

    async function openCodeModal(code) {
        currentCode = String(code || '').trim();
        if (els.codeValue) els.codeValue.textContent = currentCode || '----';
        els.codeModal?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        await renderQr(currentCode);
    }

    function render() {
        els.list.innerHTML = items.length ? items.map((item) => `
          <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0">
                <div class="break-words text-sm font-semibold text-slate-800">${escapeHtml(item.nombre_visitante || 'Visitante')}</div>
                <div class="mt-1 break-words text-xs text-slate-500">${escapeHtml(item.tipo || 'visita')} · ${escapeHtml(item.fecha_desde || '')} ${item.hora_desde ? '· ' + escapeHtml(item.hora_desde) : ''}</div>
              </div>
              <span class="inline-flex max-w-full self-start rounded-full px-2.5 py-1 text-center text-[11px] break-words sm:shrink-0 ${statusBadge(item.estado)}">${escapeHtml(item.estado || 'pendiente')}</span>
            </div>
            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-slate-600">
              <div class="break-words"><span class="text-slate-500">Codigo:</span> <span class="font-semibold text-slate-800">${escapeHtml(item.codigo_acceso || '')}</span></div>
              <div class="break-words"><span class="text-slate-500">Uso unico:</span> <span class="font-semibold text-slate-800">${Number(item.uso_unico || 0) === 1 ? 'Si' : 'No'}</span></div>
              <div class="break-words"><span class="text-slate-500">Motivo:</span> ${escapeHtml(item.motivo || '—')}</div>
              <div class="break-words"><span class="text-slate-500">Placa:</span> ${escapeHtml(item.placa_vehiculo || '—')}</div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
              <button type="button" class="min-h-11 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs text-slate-700" data-code="${escapeHtml(item.codigo_acceso || '')}">Codigo + QR</button>
              ${item.estado === 'pendiente' ? `<button type="button" class="min-h-11 rounded-full border border-rose-200 bg-white px-4 py-2 text-xs text-rose-700" data-cancel="${item.id}">Cancelar</button>` : ''}
            </div>
          </article>
        `).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Aun no has registrado visitas.</div>`;

        els.list.querySelectorAll('[data-code]').forEach((btn) => btn.addEventListener('click', async () => {
            openCodeModal(btn.dataset.code || '');
        }));
        els.list.querySelectorAll('[data-cancel]').forEach((btn) => btn.addEventListener('click', async () => {
            try {
                await api({ action: 'cancel', visit_id: btn.dataset.cancel });
                await load();
                showAlert('ok', 'Visita cancelada correctamente.');
            } catch (err) {
                showAlert('error', err.message || 'No se pudo cancelar la visita.');
            }
        }));
    }

    async function load() {
        const json = await api({ action: 'list' });
        items = json.data?.items || [];
        render();
    }

    els.btnNew?.addEventListener('click', openFormModal);
    els.btnClose?.addEventListener('click', closeModal);
    els.btnCancel?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    els.optionalToggle?.addEventListener('click', () => {
        setOptionalDetails(els.optionalToggle.getAttribute('aria-expanded') !== 'true');
    });
    els.durationButtons?.forEach((btn) => {
        btn.addEventListener('click', () => applyDurationPreset(btn.dataset.durationPreset || '1h'));
    });
    els.form?.querySelectorAll('[name="fecha_desde"], [name="fecha_hasta"], [name="hora_desde"], [name="hora_hasta"]').forEach((input) => {
        input.addEventListener('input', markScheduleAsManual);
    });
    els.btnCloseCode?.addEventListener('click', closeCodeModal);
    els.btnDismissCode?.addEventListener('click', closeCodeModal);
    els.codeModal?.addEventListener('click', (e) => { if (e.target === els.codeModal) closeCodeModal(); });
    els.btnCopyCode?.addEventListener('click', async () => {
        try {
            await navigator.clipboard?.writeText(currentCode || '');
            showAlert('ok', `Código copiado: ${currentCode || ''}`);
        } catch (_) {
            showAlert('error', 'No se pudo copiar el código automáticamente.');
        }
    });
    els.btnDownloadQr?.addEventListener('click', async () => {
        if (!currentCode) {
            showAlert('error', 'No hay un QR disponible para descargar.');
            return;
        }
        try {
            const response = await fetch(qrUrl(currentCode), {
                credentials: 'omit',
                mode: 'cors',
                cache: 'no-store',
            });
            if (!response.ok) throw new Error('No se pudo generar el archivo QR.');
            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = `visita-${currentCode}.png`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        } catch (_) {
            showAlert('error', 'No se pudo descargar el QR.');
        }
    });
    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(els.form);
        const payload = { action: 'create' };
        fd.forEach((value, key) => { payload[key] = value; });
        if (!payload.uso_unico) payload.uso_unico = '0';
        try {
            await api(payload);
            closeModal();
            await load();
            showAlert('ok', 'Visita registrada correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar la visita.');
        }
    });

    load().catch((err) => {
        renderUnavailable('No se pudieron cargar visitas para tu cuenta.');
        showAlert('error', err.message || 'No se pudieron cargar visitas.');
    });
})();
