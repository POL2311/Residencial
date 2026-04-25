(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const BASE = baseResidentPath();
    const API = BASE + 'php/api/';

    function $(id) { return document.getElementById(id); }

    const root = $('residentHomeView');
    if (!root) return;
    if (root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const els = {
        alert: $('homeAlert'),
        services: $('homeServices'),
        servicesPrev: $('homeSrvPrev'),
        servicesNext: $('homeSrvNext'),
        tipsModal: $('residentTipsModal'),
        tipsModalBody: $('residentTipsModalBody'),
        btnCloseTipsModal: $('btnCloseResidentTipsModal'),
        btnDismissTipsModal: $('btnDismissResidentTipsModal'),
        actions: root.querySelectorAll('[data-home-nav]'),
    };

    const state = {
        services: [],
        tips: [],
    };

    function escapeHtml(s) {
        return String(s ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showAlert(msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.textContent = msg;
    }

    function hideAlert() {
        if (!els.alert) return;
        els.alert.classList.add('hidden');
        els.alert.textContent = '';
    }

    function setupState(message) {
        renderTips([
            {
                title: 'Vincula tu unidad',
                text: message || 'Tu cuenta residente todavía no tiene residencial y unidad activos asignados.',
            },
            {
                title: 'Qué revisar',
                text: 'Verifica que existan relaciones en usuarios_residenciales y residentes_unidades para tu usuario.',
            },
        ]);
        showAlert(message || 'Tu cuenta residente todavía no está ligada a una unidad activa.');
    }

    async function apiPost(url, data) {
        const fd = new FormData();
        Object.keys(data || {}).forEach((key) => fd.append(key, data[key]));
        const res = await fetch(url, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok) {
            if (res.status === 404) {
                throw new Error('No se encontró el endpoint solicitado. Verifica que XAMPP esté sirviendo esta copia del proyecto.');
            }
            if (res.status === 403) {
                throw new Error(json?.error || 'Tu cuenta residente todavía no está ligada a una unidad activa.');
            }
            throw new Error(json?.error || 'Error de servidor');
        }
        if (!json || !json.ok) throw new Error(json?.error || 'Error de servidor');
        return json;
    }

    function renderTips(items) {
        state.tips = items || [];
        if (!els.tipsModalBody) return;
        els.tipsModalBody.innerHTML = state.tips.map((item) => `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.title || '')}</div>
              <p class="mt-2 text-sm text-slate-600">${escapeHtml(item.text || '')}</p>
            </div>
        `).join('');
    }

    function renderServices(items) {
        if (!els.services) return;
        state.services = items || [];

        if (!state.services.length) {
            els.services.innerHTML = `
                <div class="min-w-[280px] rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                  No hay servicios destacados disponibles.
                </div>
            `;
            return;
        }

        els.services.innerHTML = state.services.map((item) => `
            <article class="min-w-[290px] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
              <div class="h-28 bg-gradient-to-br from-[#DCE9EE] via-[#EEF4F6] to-[#B9CCD5]"></div>
              <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wide text-slate-400">${escapeHtml(item.categoria || 'servicio')}</div>
                    <div class="mt-2 text-lg font-semibold text-slate-800">${escapeHtml(item.nombre || 'Servicio')}</div>
                  </div>
                  <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${item.origen === 'global' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                    ${item.origen === 'global' ? 'Global' : 'Residencial'}
                  </span>
                </div>
                <p class="mt-3 text-sm leading-6 text-slate-600">${escapeHtml(item.descripcion || '')}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                  ${item.telefono ? `<a href="tel:${escapeHtml(item.telefono)}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Llamar</a>` : ''}
                  ${item.whatsapp ? `<a href="https://wa.me/52${escapeHtml(String(item.whatsapp).replace(/\D+/g, ''))}" target="_blank" rel="noreferrer" class="rounded-full bg-[#2E5D73] px-3 py-2 text-xs text-white hover:opacity-95">WhatsApp</a>` : ''}
                  ${item.link_url ? `<a href="${escapeHtml(item.link_url)}" target="_blank" rel="noreferrer" class="rounded-full bg-sky-50 px-3 py-2 text-xs text-sky-700 hover:bg-sky-100">Ver más</a>` : ''}
                </div>
              </div>
            </article>
        `).join('');
    }

    function todayKey() {
        const now = new Date();
        const y = now.getFullYear();
        const m = String(now.getMonth() + 1).padStart(2, '0');
        const d = String(now.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    function tipsDismissKey() {
        return `residentTipsDismissed:${todayKey()}`;
    }

    function closeTipsModal() {
        if (!els.tipsModal) return;
        els.tipsModal.classList.add('hidden');
        document.body.style.overflow = '';
        try {
            window.localStorage.setItem(tipsDismissKey(), '1');
        } catch (_) {
            // ignore storage issues and keep UX flowing
        }
    }

    function maybeOpenTipsModal() {
        if (!els.tipsModal || !state.tips.length) return;
        let dismissed = false;
        try {
            dismissed = window.localStorage.getItem(tipsDismissKey()) === '1';
        } catch (_) {
            dismissed = false;
        }
        if (dismissed) return;
        els.tipsModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function bindActions() {
        els.actions.forEach((btn) => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.homeNav || '';
                if (target && window.ResidenteDashboard?.loadView) {
                    window.ResidenteDashboard.loadView(target);
                }
            });
        });

        els.servicesPrev?.addEventListener('click', () => {
            els.services?.scrollBy({ left: -320, behavior: 'smooth' });
        });

        els.servicesNext?.addEventListener('click', () => {
            els.services?.scrollBy({ left: 320, behavior: 'smooth' });
        });

        els.btnCloseTipsModal?.addEventListener('click', closeTipsModal);
        els.btnDismissTipsModal?.addEventListener('click', closeTipsModal);
        els.tipsModal?.addEventListener('click', (event) => {
            if (event.target === els.tipsModal) closeTipsModal();
        });
    }

    async function loadHome() {
        hideAlert();
        const [homeJson, ctxJson] = await Promise.all([
            apiPost(`${API}home.php`, { action: 'get' }),
            apiPost(`${API}contexto.php`, { action: 'get' }),
        ]);

        const home = homeJson.data || {};
        const ctxData = ctxJson.data || {};
        const user = ctxData.user || {};
        const ctx = home.ctx || ctxData.ctx || {};

        if (home.setup_incomplete || ctxData.setup_incomplete) {
            setupState(home.setup_message || ctxData.setup_message || 'Tu cuenta residente todavía no está ligada a una unidad activa.');
            return;
        }

        try {
            const servicesJson = await apiPost(`${API}servicios.php`, { action: 'list' });
            renderServices(servicesJson.data?.items || []);
        } catch (_) {
            renderServices([]);
        }

        renderTips(home.tips || []);
        maybeOpenTipsModal();
    }

    bindActions();
    loadHome().catch((err) => {
        showAlert(err.message || 'No se pudo cargar el inicio.');
    });
})();
