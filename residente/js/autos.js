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
    };

    let autos = [];

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
        els.body.innerHTML = `
          ${auto.id ? `<input type="hidden" name="auto_id" value="${escapeHtml(auto.id)}">` : ''}
          <div>
            <label class="block text-xs text-slate-600 mb-1">Placas</label>
            <input name="placas" value="${escapeHtml(auto.placas || '')}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" required>
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Modelo</label>
            <input name="modelo" value="${escapeHtml(auto.modelo || '')}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Color</label>
            <input name="color" value="${escapeHtml(auto.color || '')}" class="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs text-slate-600 mb-1">Tag ID</label>
            <input value="${escapeHtml(auto.tag_id || '')}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500" disabled>
          </div>
        `;
        els.modal.classList.remove('hidden');
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        els.form.reset();
    }

    function render() {
        if (!autos.length) {
            els.list.innerHTML = `<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Aun no tienes autos registrados.</div>`;
            return;
        }
        els.list.innerHTML = autos.map((auto) => `
          <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 flex items-center justify-between gap-3">
            <div>
              <div class="text-sm font-semibold text-slate-800">${escapeHtml(auto.placas)}</div>
              <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-600">
                <div><span class="text-slate-500">Placas:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.placas || 'Sin placas')}</span></div>
                <div><span class="text-slate-500">Modelo:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.modelo || 'Sin modelo')}</span></div>
                <div><span class="text-slate-500">Color:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.color || 'Sin color')}</span></div>
                <div><span class="text-slate-500">Tag:</span> <span class="font-medium text-slate-800">${escapeHtml(auto.tag_id || 'Sin tag')}</span></div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700" data-edit="${auto.id}">Editar</button>
              <button type="button" class="rounded-full border border-rose-200 bg-white px-3 py-1.5 text-xs text-rose-700" data-delete="${auto.id}">Eliminar</button>
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
