(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    function appRootBase() {
        const p = window.location.pathname || '';
        const idx = p.indexOf('/residente/');
        if (idx !== -1) return p.slice(0, idx);
        const parts = p.split('/').filter(Boolean);
        return parts.length ? '/' + parts[0] : '';
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
        comSlider: $('residentHomeComSlider'),
        comTrack: $('residentHomeComTrack'),
        comDots: $('residentHomeComDots'),
        comPrev: $('residentHomeComPrev'),
        comNext: $('residentHomeComNext'),
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
        banners: [],
        currentSlide: 0,
        autoSlide: null,
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

    function resolvePublicUrl(url) {
        const u = String(url || '').trim();
        if (!u) return '';
        if (u.startsWith('data:')) return u;
        if (/^https?:\/\//i.test(u)) {
            try {
                const parsed = new URL(u);
                const base = appRootBase();
                if (base && parsed.pathname.startsWith('/assets/') && !parsed.pathname.startsWith(base + '/assets/')) {
                    parsed.pathname = base + parsed.pathname;
                    return parsed.toString();
                }
            } catch (_) {
                // ignore parse errors
            }
            return u;
        }
        if (u.startsWith('/assets/')) return appRootBase() + u;
        return u;
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

    function renderBanners(items) {
        if (!els.comTrack || !els.comDots) return;
        state.banners = items || [];
        state.currentSlide = 0;

        els.comTrack.innerHTML = '';
        els.comDots.innerHTML = '';

        if (!state.banners.length) {
            els.comTrack.innerHTML = `
                <div class="min-w-full">
                  <div class="h-52 md:h-64 bg-slate-50 flex flex-col items-center justify-center text-slate-600 px-6 text-center">
                    <div class="text-sm font-semibold text-slate-700">No hay comunicados activos.</div>
                    <div class="mt-2 text-xs leading-5 text-slate-500 max-w-md">
                      Revisa que estén en <b>Publicado</b>, con <b>fecha de publicación</b> menor o igual a hoy y que la <b>expiración</b> no esté vencida.
                    </div>
                    <button id="residentHomeGoComunicados" type="button"
                      class="mt-4 inline-flex items-center justify-center rounded-full bg-[#2E5D73] px-4 py-2 text-xs font-semibold text-white hover:opacity-95">
                      Ir a Comunicados
                    </button>
                  </div>
                </div>
            `;
            const btn = document.getElementById('residentHomeGoComunicados');
            btn?.addEventListener('click', () => {
                window.ResidenteDashboard?.loadView?.('comunicados');
            });
            return;
        }

        state.banners.forEach((b, i) => {
            const slide = document.createElement('div');
            slide.className = 'min-w-full';

            const bgImage = b.imagen_url
                ? `background-image:url('${escapeHtml(resolvePublicUrl(b.imagen_url))}'); background-size:cover; background-position:center;`
                : 'background:#cbd5e1;';

            slide.innerHTML = `
                <div class="h-52 md:h-64 flex items-end" style="${bgImage}">
                  <div class="w-full bg-gradient-to-t from-black/70 to-transparent p-6 text-white">
                    <span class="inline-block rounded-full bg-white/20 px-3 py-1 text-xs mb-3">
                      ${escapeHtml(b.categoria || 'General')}
                    </span>
                    <h3 class="text-xl md:text-2xl font-bold">${escapeHtml(b.titulo || '')}</h3>
                    <p class="text-sm text-white/85 mt-2">${escapeHtml(b.subtitulo || '')}</p>
                  </div>
                </div>
            `;

            els.comTrack.appendChild(slide);

            const dot = document.createElement('button');
            dot.type = 'button';
            dot.className = 'flex h-11 w-11 items-center justify-center rounded-full';
            dot.innerHTML = `<span class="h-2.5 w-2.5 rounded-full ${i === 0 ? 'bg-slate-700' : 'bg-slate-300'}"></span>`;
            dot.dataset.slide = String(i);
            dot.addEventListener('click', () => {
                state.currentSlide = i;
                updateSlider();
                restartAutoSlide();
            });
            els.comDots.appendChild(dot);
        });

        updateSlider();
    }

    function updateSlider() {
        if (!els.comTrack || !els.comDots) return;
        els.comTrack.style.transform = `translateX(-${state.currentSlide * 100}%)`;

        const dots = Array.from(els.comDots.querySelectorAll('[data-slide]'));
        dots.forEach((d, i) => {
            d.className = 'flex h-11 w-11 items-center justify-center rounded-full';
            d.innerHTML = `<span class="h-2.5 w-2.5 rounded-full ${i === state.currentSlide ? 'bg-slate-700' : 'bg-slate-300'}"></span>`;
        });
    }

    function nextSlide() {
        if (!state.banners.length) return;
        state.currentSlide = state.currentSlide === state.banners.length - 1 ? 0 : state.currentSlide + 1;
        updateSlider();
    }

    function prevSlide() {
        if (!state.banners.length) return;
        state.currentSlide = state.currentSlide === 0 ? state.banners.length - 1 : state.currentSlide - 1;
        updateSlider();
    }

    function startAutoSlide() {
        stopAutoSlide();
        if (state.banners.length <= 1) return;
        state.autoSlide = setInterval(nextSlide, 5000);
    }

    function stopAutoSlide() {
        if (state.autoSlide) {
            clearInterval(state.autoSlide);
            state.autoSlide = null;
        }
    }

    function restartAutoSlide() {
        stopAutoSlide();
        startAutoSlide();
    }

    function bindSwipe(sliderEl, { onNext, onPrev, onInteract }) {
        if (!sliderEl || sliderEl.dataset.swipeBound === '1') return;
        sliderEl.dataset.swipeBound = '1';

        let startX = null;
        let startY = null;
        let isPointerDown = false;

        const threshold = 40;
        const maxVertical = 28;

        function handleEnd(endX, endY) {
            if (startX === null || startY === null) return;
            const dx = endX - startX;
            const dy = endY - startY;
            startX = null;
            startY = null;
            isPointerDown = false;
            if (Math.abs(dy) > maxVertical) return;
            if (Math.abs(dx) < threshold) return;
            if (dx < 0) onNext?.();
            else onPrev?.();
            onInteract?.();
        }

        sliderEl.addEventListener('touchstart', (ev) => {
            const touch = ev.changedTouches?.[0];
            if (!touch) return;
            startX = touch.clientX;
            startY = touch.clientY;
        }, { passive: true });

        sliderEl.addEventListener('touchend', (ev) => {
            const touch = ev.changedTouches?.[0];
            if (!touch) return;
            handleEnd(touch.clientX, touch.clientY);
        }, { passive: true });

        sliderEl.addEventListener('pointerdown', (ev) => {
            if (ev.pointerType === 'mouse' && ev.button !== 0) return;
            isPointerDown = true;
            startX = ev.clientX;
            startY = ev.clientY;
        });

        sliderEl.addEventListener('pointerup', (ev) => {
            if (!isPointerDown) return;
            handleEnd(ev.clientX, ev.clientY);
        });

        sliderEl.addEventListener('pointercancel', () => {
            startX = null;
            startY = null;
            isPointerDown = false;
        });
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
              <div class="break-words text-sm font-semibold text-slate-800">${escapeHtml(item.title || '')}</div>
              <p class="mt-2 break-words text-sm text-slate-600">${escapeHtml(item.text || '')}</p>
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
            <article class="min-w-[82vw] max-w-[20rem] flex-none overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm sm:min-w-[290px]">
              <div class="h-28 ${item.imagen_url ? 'bg-slate-100' : 'bg-gradient-to-br from-[#DCE9EE] via-[#EEF4F6] to-[#B9CCD5]'}">
                ${item.imagen_url ? `<img src="${escapeHtml(resolvePublicUrl(item.imagen_url))}" alt="${escapeHtml(item.nombre || 'Servicio')}" class="h-full w-full object-cover" loading="lazy" />` : ''}
              </div>
              <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <div class="text-xs uppercase tracking-wide text-slate-400">${escapeHtml(item.categoria || 'servicio')}</div>
                    <div class="mt-2 break-words text-lg font-semibold text-slate-800">${escapeHtml(item.nombre || 'Servicio')}</div>
                  </div>
                  <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${item.origen === 'global' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                    ${item.origen === 'global' ? 'Global' : 'Residencial'}
                  </span>
                </div>
                <p class="mt-3 break-words text-sm leading-6 text-slate-600">${escapeHtml(item.descripcion || '')}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                  ${item.telefono ? `<a href="tel:${escapeHtml(item.telefono)}" class="inline-flex min-h-11 items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-xs text-slate-700 hover:bg-slate-50">Llamar</a>` : ''}
                  ${item.whatsapp ? `<a href="https://wa.me/52${escapeHtml(String(item.whatsapp).replace(/\D+/g, ''))}" target="_blank" rel="noreferrer" class="inline-flex min-h-11 items-center rounded-full bg-[#2E5D73] px-4 py-2 text-xs text-white hover:opacity-95">WhatsApp</a>` : ''}
                  ${item.link_url ? `<a href="${escapeHtml(item.link_url)}" target="_blank" rel="noreferrer" class="inline-flex min-h-11 items-center rounded-full bg-sky-50 px-4 py-2 text-xs text-sky-700 hover:bg-sky-100">Ver más</a>` : ''}
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

        els.comPrev?.addEventListener('click', () => {
            prevSlide();
            restartAutoSlide();
        });

        els.comNext?.addEventListener('click', () => {
            nextSlide();
            restartAutoSlide();
        });
        bindSwipe(els.comSlider, {
            onPrev: prevSlide,
            onNext: nextSlide,
            onInteract: restartAutoSlide,
        });

        els.servicesPrev?.addEventListener('click', () => {
            els.services?.scrollBy({ left: -320, behavior: 'smooth' });
        });

        els.servicesNext?.addEventListener('click', () => {
            els.services?.scrollBy({ left: 320, behavior: 'smooth' });
        });

        els.btnCloseTipsModal?.addEventListener('click', (e) => { e.stopPropagation(); closeTipsModal(); });
        els.btnDismissTipsModal?.addEventListener('click', (e) => { e.stopPropagation(); closeTipsModal(); });
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

        renderBanners(home.banners || []);
        startAutoSlide();

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
