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
        unitLabel: $('homeUnitLabel'),
        residentQuickName: $('residentQuickName'),
        statAutos: $('homeStatAutos'),
        statPendientes: $('homeStatPendientes'),
        statEntregados: $('homeStatEntregados'),
        statContactos: $('homeStatContactos'),
        announcementTrack: $('homeAnnouncementsTrack'),
        announcementDots: $('homeAnnouncementDots'),
        announcementPrev: $('homeAnnPrev'),
        announcementNext: $('homeAnnNext'),
        services: $('homeServices'),
        servicesPrev: $('homeSrvPrev'),
        servicesNext: $('homeSrvNext'),
        packages: $('homeRecentPackages'),
        tips: $('homeTips'),
        actions: root.querySelectorAll('[data-home-nav]'),
    };

    const state = {
        announcements: [],
        services: [],
        currentAnnouncement: 0,
        autoAnnouncement: null,
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
        if (els.unitLabel) els.unitLabel.textContent = 'Configuración pendiente';
        if (els.residentQuickName && !els.residentQuickName.textContent.trim()) {
            els.residentQuickName.textContent = 'Residente';
        }
        if (els.statAutos) els.statAutos.textContent = '0';
        if (els.statPendientes) els.statPendientes.textContent = '0';
        if (els.statEntregados) els.statEntregados.textContent = '0';
        if (els.statContactos) els.statContactos.textContent = '0';

        renderAnnouncements([]);
        renderServices([]);
        renderRecentPackages([]);
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

    function badgeClass(priority) {
        if (priority === 'alta') return 'bg-rose-50 text-rose-700 border border-rose-200';
        if (priority === 'media') return 'bg-amber-50 text-amber-700 border border-amber-200';
        return 'bg-sky-50 text-sky-700 border border-sky-200';
    }

    function statusClass(status) {
        if (status === 'entregado') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        if (status === 'devuelto') return 'bg-rose-50 text-rose-700 border border-rose-200';
        return 'bg-amber-50 text-amber-700 border border-amber-200';
    }

    function statusLabel(status) {
        if (status === 'entregado') return 'Entregado';
        if (status === 'devuelto') return 'Devuelto';
        return 'Pendiente';
    }

    function renderAnnouncements(items) {
        if (!els.announcementTrack || !els.announcementDots) return;
        state.announcements = items || [];
        state.currentAnnouncement = 0;
        els.announcementTrack.innerHTML = '';
        els.announcementDots.innerHTML = '';

        if (!state.announcements.length) {
            els.announcementTrack.innerHTML = `
                <div class="min-w-full">
                  <div class="flex min-h-[18rem] items-center justify-center bg-slate-100 px-6 text-center text-sm text-slate-500">
                    No hay comunicados publicados por el momento.
                  </div>
                </div>
            `;
            stopAutoAnnouncements();
            return;
        }

        state.announcements.forEach((item, index) => {
            const slide = document.createElement('div');
            slide.className = 'min-w-full';
            slide.innerHTML = `
                <article class="flex min-h-[18rem] flex-col justify-end bg-gradient-to-br from-[#36596C] via-[#55798D] to-[#C7D7DD] p-6 text-white md:p-8">
                  <div class="max-w-3xl">
                    <div class="flex flex-wrap items-center gap-2">
                      <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs uppercase tracking-wide text-white/85">
                        ${escapeHtml(item.tipo || 'general')}
                      </span>
                      <span class="inline-flex rounded-full px-3 py-1 text-xs ${badgeClass(item.prioridad || 'general')}">
                        ${escapeHtml(item.prioridad || 'general')}
                      </span>
                    </div>
                    <h3 class="mt-4 text-2xl font-semibold md:text-3xl">${escapeHtml(item.titulo || 'Comunicado')}</h3>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-white/85 md:text-base">
                      ${escapeHtml(item.mensaje || '')}
                    </p>
                    <div class="mt-4 text-xs text-white/70">
                      ${escapeHtml(item.fecha_publicacion || '')}
                    </div>
                  </div>
                </article>
            `;
            els.announcementTrack.appendChild(slide);

            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = index === 0 ? 'h-2.5 w-2.5 rounded-full bg-slate-700' : 'h-2.5 w-2.5 rounded-full bg-slate-300';
            dot.dataset.announcement = String(index);
            dot.addEventListener('click', () => {
                state.currentAnnouncement = index;
                updateAnnouncementSlider();
                restartAutoAnnouncements();
            });
            els.announcementDots.appendChild(dot);
        });

        updateAnnouncementSlider();
        startAutoAnnouncements();
    }

    function updateAnnouncementSlider() {
        if (!els.announcementTrack || !els.announcementDots) return;
        els.announcementTrack.style.transform = `translateX(-${state.currentAnnouncement * 100}%)`;

        els.announcementDots.querySelectorAll('[data-announcement]').forEach((dot, index) => {
            dot.className = index === state.currentAnnouncement
                ? 'h-2.5 w-2.5 rounded-full bg-slate-700'
                : 'h-2.5 w-2.5 rounded-full bg-slate-300';
        });
    }

    function nextAnnouncement() {
        if (!state.announcements.length) return;
        state.currentAnnouncement = state.currentAnnouncement === state.announcements.length - 1 ? 0 : state.currentAnnouncement + 1;
        updateAnnouncementSlider();
    }

    function prevAnnouncement() {
        if (!state.announcements.length) return;
        state.currentAnnouncement = state.currentAnnouncement === 0 ? state.announcements.length - 1 : state.currentAnnouncement - 1;
        updateAnnouncementSlider();
    }

    function startAutoAnnouncements() {
        stopAutoAnnouncements();
        if (state.announcements.length <= 1) return;
        state.autoAnnouncement = setInterval(nextAnnouncement, 5000);
    }

    function stopAutoAnnouncements() {
        if (state.autoAnnouncement) {
            clearInterval(state.autoAnnouncement);
            state.autoAnnouncement = null;
        }
    }

    function restartAutoAnnouncements() {
        stopAutoAnnouncements();
        startAutoAnnouncements();
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
              <div class="h-32 bg-gradient-to-br from-[#DCE9EE] via-[#EEF4F6] to-[#B9CCD5]"></div>
              <div class="p-5">
                <div class="text-xs uppercase tracking-wide text-slate-400">${escapeHtml(item.categoria || 'servicio')}</div>
                <div class="mt-2 text-lg font-semibold text-slate-800">${escapeHtml(item.nombre || 'Servicio')}</div>
                <p class="mt-2 text-sm leading-6 text-slate-600">${escapeHtml(item.descripcion || '')}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                  ${item.telefono ? `<a href="tel:${escapeHtml(item.telefono)}" class="rounded-full border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 hover:bg-slate-50">Llamar</a>` : ''}
                  ${item.whatsapp ? `<a href="https://wa.me/52${escapeHtml(String(item.whatsapp).replace(/\D+/g, ''))}" target="_blank" rel="noreferrer" class="rounded-full bg-[#2E5D73] px-3 py-2 text-xs text-white hover:opacity-95">WhatsApp</a>` : ''}
                  ${item.link_url ? `<a href="${escapeHtml(item.link_url)}" target="_blank" rel="noreferrer" class="rounded-full bg-sky-50 px-3 py-2 text-xs text-sky-700 hover:bg-sky-100">Ver más</a>` : ''}
                </div>
              </div>
            </article>
        `).join('');
    }

    function renderRecentPackages(items) {
        if (!els.packages) return;
        if (!items?.length) {
            els.packages.innerHTML = `
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                  Todavía no hay movimientos de paquetería para tu unidad.
                </div>
            `;
            return;
        }

        els.packages.innerHTML = items.map((item) => `
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-slate-800 truncate">${escapeHtml(item.empresa || 'Paquete')}</div>
                  <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.created_at || '')}</div>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] ${statusClass(item.estado)}">
                  ${escapeHtml(statusLabel(item.estado))}
                </span>
              </div>
              <p class="mt-3 text-sm text-slate-600">${escapeHtml(item.descripcion || 'Sin descripción')}</p>
            </div>
        `).join('');
    }

    function renderTips(items) {
        if (!els.tips) return;
        els.tips.innerHTML = (items || []).map((item) => `
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4">
              <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.title || '')}</div>
              <p class="mt-2 text-sm text-slate-600">${escapeHtml(item.text || '')}</p>
            </div>
        `).join('');
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

        els.announcementPrev?.addEventListener('click', () => {
            prevAnnouncement();
            restartAutoAnnouncements();
        });

        els.announcementNext?.addEventListener('click', () => {
            nextAnnouncement();
            restartAutoAnnouncements();
        });

        els.servicesPrev?.addEventListener('click', () => {
            els.services?.scrollBy({ left: -320, behavior: 'smooth' });
        });

        els.servicesNext?.addEventListener('click', () => {
            els.services?.scrollBy({ left: 320, behavior: 'smooth' });
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
        const stats = home.stats || {};

        if (home.setup_incomplete || ctxData.setup_incomplete) {
            if (els.residentQuickName) {
                els.residentQuickName.textContent = user.name || 'Residente';
            }
            setupState(home.setup_message || ctxData.setup_message || 'Tu cuenta residente todavía no está ligada a una unidad activa.');
            return;
        }

        if (els.unitLabel) {
            const residencial = ctx.residencial_nombre || 'Tu residencial';
            const unidad = ctx.unidad_clave ? ` · Unidad ${ctx.unidad_clave}` : '';
            els.unitLabel.textContent = `${residencial}${unidad}`;
        }

        if (els.residentQuickName) {
            els.residentQuickName.textContent = user.name || 'Residente';
        }

        if (els.statAutos) els.statAutos.textContent = stats.autos ?? 0;
        if (els.statPendientes) els.statPendientes.textContent = stats.paquetes_pendientes ?? 0;
        if (els.statEntregados) els.statEntregados.textContent = stats.paquetes_entregados ?? 0;
        if (els.statContactos) els.statContactos.textContent = stats.contactos_emergencia ?? 0;

        renderAnnouncements(home.announcements || []);
        renderServices(home.services || []);
        renderRecentPackages(home.recent_packages || []);
        renderTips(home.tips || []);
    }

    bindActions();
    loadHome().catch((err) => {
        showAlert(err.message || 'No se pudo cargar el inicio.');
    });
})();
