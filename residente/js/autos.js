(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentAutosView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/autos.php';
    const els = {
        alert: document.getElementById('autosAlert'),
        list: document.getElementById('residentAutosList'),
        btnNew: document.getElementById('btnNuevoAutoResident'),
        modal: document.getElementById('residentAutosModal'),
        modalTitle: document.getElementById('residentAutosModalTitle'),
        form: document.getElementById('residentAutosForm'),
        action: document.getElementById('residentAutosAction'),
        body: document.getElementById('residentAutosBody'),
        btnClose: document.getElementById('btnCloseResidentAutosModal'),
        btnCancel: document.getElementById('btnCancelResidentAutosModal'),
        btnSave: document.getElementById('btnSaveResidentAutosModal'),
    };

    let autos = [];
    const FORM_LABEL = 'mb-1 block text-xs font-medium text-slate-600';
    const FORM_CONTROL = 'min-h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#2E5D73] focus:ring-2 focus:ring-[#2E5D73]/15';

    function escapeHtml(s) {
        return String(s ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
    }

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = 'rounded-2xl px-4 py-3 text-sm';
        els.alert.textContent = msg;
        els.alert.classList.add(type === 'ok' ? 'bg-emerald-50' : 'bg-rose-50', type === 'ok' ? 'text-emerald-800' : 'text-rose-800', 'border', type === 'ok' ? 'border-emerald-200' : 'border-rose-200');
    }

    async function api(data) {
        const fd = new FormData();
        Object.keys(data || {}).forEach((key) => fd.append(key, data[key]));
        const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json().catch(() => null);
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    function openModal(title, action, auto = {}) {
        els.modalTitle.textContent = title;
        els.action.value = action;
        if (els.btnSave) els.btnSave.textContent = action === 'create' ? 'Agregar auto' : 'Guardar cambios';
        els.body.innerHTML = `
          ${auto.id ? `<input type="hidden" name="auto_id" value="${escapeHtml(auto.id)}">` : ''}
          <div>
            <label class="${FORM_LABEL}">Placas</label>
            <input name="placas" value="${escapeHtml(auto.placas || '')}" class="${FORM_CONTROL} uppercase" autocapitalize="characters" placeholder="Ej. ABC-123" required>
          </div>
          <details class="rounded-2xl border border-slate-200 bg-slate-50/80 p-3">
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-slate-700">
              <span>Detalles opcionales</span>
              <span class="text-slate-400">⌄</span>
            </summary>
            <div class="mt-3 space-y-3">
              <div>
                <label class="${FORM_LABEL}">Modelo</label>
                <input name="modelo" value="${escapeHtml(auto.modelo || '')}" class="${FORM_CONTROL}" placeholder="Ej. Versa 2022">
              </div>
              <div>
                <label class="${FORM_LABEL}">Color</label>
                <input name="color" value="${escapeHtml(auto.color || '')}" class="${FORM_CONTROL}" placeholder="Ej. Blanco">
              </div>
              <div>
                <label class="${FORM_LABEL}">Tag ID</label>
                <input value="${escapeHtml(auto.tag_id || 'Sin tag asignado')}" class="${FORM_CONTROL} bg-slate-50 text-slate-500" disabled>
              </div>
            </div>
          </details>
        `;
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.form.reset();
        document.body.style.overflow = '';
    }

    function render() {
        if (!autos.length) {
            els.list.innerHTML = `<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Aun no tienes autos registrados.</div>`;
            return;
        }
        els.list.innerHTML = autos.map((auto) => `
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
              <div class="break-words text-sm font-semibold text-slate-800">${escapeHtml(auto.placas || 'Sin placas')}</div>
              <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600">
                <div class="break-words"><span class="text-slate-500">Modelo:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.modelo || 'Sin modelo')}</span></div>
                <div class="break-words"><span class="text-slate-500">Color:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.color || 'Sin color')}</span></div>
                <div class="break-words"><span class="text-slate-500">Tag:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.tag_id || 'Sin tag')}</span></div>
              </div>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end">
              <button type="button" class="min-h-11 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs text-slate-700" data-edit="${auto.id}">Editar</button>
              <button type="button" class="min-h-11 rounded-full border border-rose-200 bg-white px-4 py-2 text-xs text-rose-700" data-delete="${auto.id}">Eliminar</button>
            </div>
          </div>
        `).join('');

        els.list.querySelectorAll('[data-edit]').forEach((btn) => btn.addEventListener('click', () => {
            const auto = autos.find((item) => String(item.id) === btn.dataset.edit);
            if (auto) openModal('Editar auto', 'update', auto);
        }));
        els.list.querySelectorAll('[data-delete]').forEach((btn) => btn.addEventListener('click', async () => {
            try {
                await api({ action: 'delete', auto_id: btn.dataset.delete });
                await load();
                showAlert('ok', 'Auto eliminado.');
                window.ResidenteDashboard?.loadContext?.();
            } catch (err) {
                showAlert('error', err.message || 'No se pudo eliminar.');
            }
        }));
    }

    async function load() {
        const json = await api({ action: 'list' });
        autos = json.data?.autos || [];
        render();
    }

    els.btnNew?.addEventListener('click', () => openModal('Agregar auto', 'create'));
    els.btnClose?.addEventListener('click', closeModal);
    els.btnCancel?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });
    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(els.form);
        const payload = {};
        fd.forEach((value, key) => { payload[key] = value; });
        payload.action = els.action.value || 'create';
        try {
            await api(payload);
            closeModal();
            await load();
            window.ResidenteDashboard?.loadContext?.();
            showAlert('ok', payload.action === 'create' ? 'Auto agregado.' : 'Auto actualizado.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar el auto.');
        }
    });

    load().catch((err) => showAlert('error', err.message || 'No se pudo cargar autos.'));
})();
