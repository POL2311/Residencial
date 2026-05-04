(function () {
    const root = document.getElementById('superadminConfiguracionView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'configuracion.php';
    const els = {
        alert: document.getElementById('configAlert'),
        form: document.getElementById('configForm'),
        btnRefresh: document.getElementById('btnRefreshConfig'),
        servicesWrap: document.getElementById('globalServicesWrap'),
        btnNuevoServicio: document.getElementById('btnNuevoServicioGlobal'),
        modal: document.getElementById('globalServiceModal'),
        modalTitle: document.getElementById('globalServiceModalTitle'),
        serviceForm: document.getElementById('globalServiceForm'),
        btnCloseModal: document.getElementById('btnCloseGlobalServiceModal'),
        btnCancelModal: document.getElementById('btnCancelGlobalServiceModal'),
        btnTestSmtp: document.getElementById('btnTestSmtp'),
        smtpTestEmail: document.getElementById('smtpTestEmail'),
    };

    let csrf = '';
    let globalServices = [];

    function field(name) {
        return els.serviceForm?.elements?.namedItem(name);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

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

    function renderServices() {
        if (!globalServices.length) {
            els.servicesWrap.innerHTML = `
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">
                  Aún no hay servicios globales. Cuando agregues uno, aparecerá en el home de los residenciales como publicidad destacada.
                </div>
            `;
            return;
        }

        els.servicesWrap.innerHTML = `
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
              ${globalServices.map((item) => `
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="flex items-center gap-2">
                        <h3 class="truncate text-base font-semibold text-slate-800">${escapeHtml(item.nombre)}</h3>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${Number(item.activo || 0) === 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200'}">${Number(item.activo || 0) === 1 ? 'Activo' : 'Oculto'}</span>
                      </div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.categoria || 'servicio')}</div>
                    </div>
                    <div class="text-xs text-slate-400">Orden ${Number(item.orden || 0)}</div>
                  </div>

                  ${item.imagen_url ? `
                    <div class="mt-4 h-36 rounded-2xl bg-slate-200 bg-cover bg-center" style="background-image:url('${escapeHtml(item.imagen_url)}')"></div>
                  ` : ''}

                  <p class="mt-4 text-sm text-slate-600">${escapeHtml(item.descripcion || 'Sin descripción')}</p>

                  <div class="mt-4 flex flex-wrap gap-2 text-xs text-slate-500">
                    ${item.telefono ? `<span class="rounded-full bg-white px-3 py-1 border border-slate-200">Tel: ${escapeHtml(item.telefono)}</span>` : ''}
                    ${item.whatsapp ? `<span class="rounded-full bg-white px-3 py-1 border border-slate-200">WA: ${escapeHtml(item.whatsapp)}</span>` : ''}
                    ${item.link_url ? `<span class="rounded-full bg-white px-3 py-1 border border-slate-200">Con enlace</span>` : ''}
                  </div>

                  <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <button type="button" data-service-edit="${escapeHtml(item.id)}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 hover:bg-slate-100">Editar</button>
                    <button type="button" data-service-delete="${escapeHtml(item.id)}" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700 hover:bg-rose-100">Eliminar</button>
                  </div>
                </article>
              `).join('')}
            </div>
        `;
    }

    function fillConfig(config) {
        els.form.nombre_sistema.value = config.nombre_sistema || '';
        els.form.empresa.value = config.empresa || '';
        els.form.email_soporte.value = config.email_soporte || '';
        els.form.logo_url.value = config.logo_url || '';
        els.form.color_primario.value = config.color_primario || '';
        els.form.color_secundario.value = config.color_secundario || '';
        els.form.smtp_host.value = config.smtp_host || '';
        els.form.smtp_port.value = config.smtp_port || '';
        els.form.smtp_username.value = config.smtp_username || '';
        els.form.smtp_password.value = config.smtp_password || '';
        els.form.smtp_encryption.value = config.smtp_encryption || 'tls';
        els.form.smtp_from_email.value = config.smtp_from_email || '';
        els.form.smtp_from_name.value = config.smtp_from_name || '';
    }

    function openModal(item = null) {
        els.serviceForm.reset();
        field('id').value = item?.id || '';
        field('nombre').value = item?.nombre || '';
        field('descripcion').value = item?.descripcion || '';
        field('imagen_url').value = item?.imagen_url || '';
        field('categoria').value = item?.categoria || 'servicio';
        field('orden').value = item?.orden || 0;
        field('telefono').value = item?.telefono || '';
        field('whatsapp').value = item?.whatsapp || '';
        field('link_url').value = item?.link_url || '';
        field('activo').checked = Number(item?.activo ?? 1) === 1;
        els.modalTitle.textContent = item ? 'Editar servicio global' : 'Nuevo servicio global';
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        els.modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    async function load() {
        const json = await api({ action: 'get' });
        const config = json.data?.config || {};
        csrf = json.data?.csrf_token || '';
        globalServices = json.data?.global_services || [];

        fillConfig(config);
        renderServices();
    }

    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await api({
                action: 'save',
                csrf_token: csrf,
                nombre_sistema: els.form.nombre_sistema.value,
                empresa: els.form.empresa.value,
                email_soporte: els.form.email_soporte.value,
                logo_url: els.form.logo_url.value,
                color_primario: els.form.color_primario.value,
                color_secundario: els.form.color_secundario.value,
                smtp_host: els.form.smtp_host.value,
                smtp_port: els.form.smtp_port.value,
                smtp_username: els.form.smtp_username.value,
                smtp_password: els.form.smtp_password.value,
                smtp_encryption: els.form.smtp_encryption.value,
                smtp_from_email: els.form.smtp_from_email.value,
                smtp_from_name: els.form.smtp_from_name.value,
            });
            showAlert('ok', 'Configuración general actualizada correctamente.');
            await load();
            window.SuperadminDashboard?.loadContext?.();
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar la configuración.');
        }
    });

    els.btnRefresh?.addEventListener('click', () => {
        load().then(() => showAlert('ok', 'Configuración recargada.')).catch((err) => showAlert('error', err.message || 'No se pudo recargar.'));
    });

    els.btnTestSmtp?.addEventListener('click', async () => {
        try {
            await api({
                action: 'test_smtp',
                csrf_token: csrf,
                test_email: els.smtpTestEmail?.value || '',
                nombre_sistema: els.form.nombre_sistema.value,
                smtp_host: els.form.smtp_host.value,
                smtp_port: els.form.smtp_port.value,
                smtp_username: els.form.smtp_username.value,
                smtp_password: els.form.smtp_password.value,
                smtp_encryption: els.form.smtp_encryption.value,
                smtp_from_email: els.form.smtp_from_email.value,
                smtp_from_name: els.form.smtp_from_name.value,
            });
            showAlert('ok', 'Correo de prueba enviado correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo enviar el correo de prueba.');
        }
    });

    els.btnNuevoServicio?.addEventListener('click', () => openModal());
    els.btnCloseModal?.addEventListener('click', closeModal);
    els.btnCancelModal?.addEventListener('click', closeModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeModal(); });

    els.servicesWrap?.addEventListener('click', async (e) => {
        const editBtn = e.target.closest('[data-service-edit]');
        if (editBtn) {
            const id = String(editBtn.getAttribute('data-service-edit') || '');
            const item = globalServices.find((service) => String(service.id) === id);
            if (item) openModal(item);
            return;
        }

        const deleteBtn = e.target.closest('[data-service-delete]');
        if (!deleteBtn) return;
        const id = String(deleteBtn.getAttribute('data-service-delete') || '');
        const item = globalServices.find((service) => String(service.id) === id);
        if (!item) return;

        const ok = window.confirm(`¿Eliminar "${item.nombre}" de los servicios globales?`);
        if (!ok) return;

        try {
            await api({ action: 'delete_service', csrf_token: csrf, id });
            await load();
            showAlert('ok', 'Servicio global eliminado.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo eliminar el servicio global.');
        }
    });

    els.serviceForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await api({
                action: 'save_service',
                csrf_token: csrf,
                id: field('id').value,
                nombre: field('nombre').value,
                descripcion: field('descripcion').value,
                imagen_url: field('imagen_url').value,
                categoria: field('categoria').value,
                orden: field('orden').value,
                telefono: field('telefono').value,
                whatsapp: field('whatsapp').value,
                link_url: field('link_url').value,
                activo: field('activo').checked ? '1' : '0',
            });
            closeModal();
            await load();
            showAlert('ok', 'Servicio global guardado correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo guardar el servicio global.');
        }
    });

    load().catch((err) => showAlert('error', err.message || 'No se pudo cargar la configuración.'));
})();
