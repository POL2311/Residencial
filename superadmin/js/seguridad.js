(function () {
    const root = document.getElementById('superadminSeguridadView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'seguridad.php';
    const els = {
        alert: document.getElementById('seguridadAlert'),
        form: document.getElementById('seguridadForm'),
        btnRefresh: document.getElementById('btnRefreshSeguridad'),
    };

    let csrf = '';

    function showAlert(type, msg) {
        const normalizedType = type === 'ok' ? 'success' : type;
        if (window.AppToast?.show) {
            window.AppToast.show({ type: normalizedType, message: msg });
            return;
        }
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm ${normalizedType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`;
        els.alert.textContent = msg;
    }

    async function api(data) {
        const fd = new FormData();
        Object.entries(data || {}).forEach(([key, value]) => fd.append(key, value));
        const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.ok) throw new Error(json?.error || 'No se pudo procesar la solicitud.');
        return json;
    }

    async function load() {
        const json = await api({ action: 'get' });
        const config = json.data?.config || {};
        csrf = json.data?.csrf_token || '';
        els.form.max_intentos_login.value = config.max_intentos_login ?? 5;
        els.form.minutos_bloqueo_login.value = config.minutos_bloqueo_login ?? 15;
        els.form.tiempo_sesion_minutos.value = config.tiempo_sesion_minutos ?? 60;
        els.form.registrar_logs_acceso.checked = Number(config.registrar_logs_acceso || 0) === 1;
    }

    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await api({
                action: 'save',
                csrf_token: csrf,
                max_intentos_login: els.form.max_intentos_login.value,
                minutos_bloqueo_login: els.form.minutos_bloqueo_login.value,
                tiempo_sesion_minutos: els.form.tiempo_sesion_minutos.value,
                registrar_logs_acceso: els.form.registrar_logs_acceso.checked ? '1' : '0',
            });
            showAlert('ok', 'Configuración de seguridad actualizada correctamente.');
            await load();
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar la configuración.');
        }
    });

    els.btnRefresh?.addEventListener('click', () => {
        load().then(() => showAlert('ok', 'Configuración recargada.')).catch((err) => showAlert('error', err.message || 'No se pudo recargar.'));
    });

    load().catch((err) => showAlert('error', err.message || 'No se pudo cargar la configuración.'));
})();
