(function () {
  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/home.php';

  const els = {
    alert: document.getElementById('homeAlert'),
    comTrack: document.getElementById('homeComunicadosTrack'),
    comDots: document.getElementById('homeComDots'),
    comPrev: document.getElementById('homeComPrev'),
    comNext: document.getElementById('homeComNext'),
    srvScroller: document.getElementById('homeServiciosScroller'),
    srvPrev: document.getElementById('homeSrvPrev'),
    srvNext: document.getElementById('homeSrvNext'),
  };

  if (!els.comTrack || !els.srvScroller) return;

  const state = {
    banners: [],
    servicios: [],
    currentSlide: 0,
    autoSlide: null,
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
      (isError ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
    els.alert.classList.remove('hidden');
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
    } catch {
      throw new Error(text || 'Respuesta inválida del servidor');
    }

    if (!json || !json.ok) {
      throw new Error((json && json.error) || 'Error');
    }

    return json;
  }

  function renderBanners() {
    els.comTrack.innerHTML = '';
    els.comDots.innerHTML = '';

    if (!state.banners.length) {
      els.comTrack.innerHTML = `
        <div class="min-w-full">
          <div class="h-64 md:h-80 bg-slate-200 flex items-center justify-center text-slate-500">
            No hay banners activos.
          </div>
        </div>
      `;
      return;
    }

    state.banners.forEach((b, i) => {
      const slide = document.createElement('div');
      slide.className = 'min-w-full';

      const bgImage = b.imagen_url
        ? `background-image:url('${escapeHtml(b.imagen_url)}'); background-size:cover; background-position:center;`
        : 'background:#cbd5e1;';

      slide.innerHTML = `
        <div class="h-64 md:h-80 flex items-end" style="${bgImage}">
          <div class="w-full bg-gradient-to-t from-black/70 to-transparent p-6 text-white">
            <span class="inline-block rounded-full bg-white/20 px-3 py-1 text-xs mb-3">
              ${escapeHtml(b.categoria || 'General')}
            </span>
            <h3 class="text-2xl font-bold">${escapeHtml(b.titulo || '')}</h3>
            <p class="text-sm text-white/85 mt-2">
              ${escapeHtml(b.subtitulo || '')}
            </p>
          </div>
        </div>
      `;
      els.comTrack.appendChild(slide);

      const dot = document.createElement('button');
      dot.className = 'h-2.5 w-2.5 rounded-full ' + (i === 0 ? 'bg-slate-700' : 'bg-slate-300');
      dot.dataset.slide = i;
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
    els.comTrack.style.transform = `translateX(-${state.currentSlide * 100}%)`;

    const dots = Array.from(els.comDots.querySelectorAll('[data-slide]'));
    dots.forEach((d, i) => {
      d.className = 'h-2.5 w-2.5 rounded-full ' + (i === state.currentSlide ? 'bg-slate-700' : 'bg-slate-300');
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

  function renderServicios() {
    els.srvScroller.innerHTML = '';

    if (!state.servicios.length) {
      els.srvScroller.innerHTML = `
        <div class="min-w-[280px] rounded-3xl bg-white shadow border border-slate-200 p-6 text-slate-500">
          No hay servicios activos.
        </div>
      `;
      return;
    }

    state.servicios.forEach((s) => {
      const card = document.createElement('div');
      card.className = 'min-w-[280px] rounded-3xl bg-white shadow border border-slate-200 overflow-hidden';

      const bgImage = s.imagen_url
        ? `background-image:url('${escapeHtml(s.imagen_url)}'); background-size:cover; background-position:center;`
        : 'background:#cbd5e1;';

      const waDigits = String(s.whatsapp || '').replace(/\D+/g, '');
      const telDigits = String(s.telefono || '').replace(/\D+/g, '');

      card.innerHTML = `
        <div class="h-40" style="${bgImage}"></div>
        <div class="p-4">
          <h3 class="font-semibold text-slate-800">${escapeHtml(s.nombre || '')}</h3>
          <p class="text-sm text-slate-500 mt-1">${escapeHtml(s.descripcion || '')}</p>

          <div class="flex flex-wrap gap-2 mt-4">
            ${telDigits ? `
              <a href="tel:${escapeHtml(telDigits)}"
                class="inline-flex items-center px-3 py-2 rounded-xl border text-sm hover:bg-slate-50">
                Llamar
              </a>
            ` : ''}

            ${waDigits ? `
              <a href="https://wa.me/52${escapeHtml(waDigits)}" target="_blank" rel="noopener noreferrer"
                class="inline-flex items-center px-3 py-2 rounded-xl bg-emerald-100 text-emerald-700 text-sm hover:bg-emerald-200">
                WhatsApp
              </a>
            ` : ''}

            ${s.link_url ? `
              <a href="${escapeHtml(s.link_url)}" target="_blank" rel="noopener noreferrer"
                class="inline-flex items-center px-3 py-2 rounded-xl bg-sky-100 text-sky-700 text-sm hover:bg-sky-200">
                Ver más
              </a>
            ` : ''}
          </div>
        </div>
      `;
      els.srvScroller.appendChild(card);
    });
  }

  async function loadHome() {
    try {
      const json = await fetchJSON(API);
      state.banners = json.banners || [];
      state.servicios = json.servicios || [];
      state.currentSlide = 0;

      renderBanners();
      renderServicios();
      startAutoSlide();
    } catch (err) {
      showAlert(err.message || 'No se pudo cargar el home.', true);
    }
  }

  els.comPrev?.addEventListener('click', () => {
    prevSlide();
    restartAutoSlide();
  });

  els.comNext?.addEventListener('click', () => {
    nextSlide();
    restartAutoSlide();
  });

  els.srvPrev?.addEventListener('click', () => {
    els.srvScroller.scrollBy({ left: -320, behavior: 'smooth' });
  });

  els.srvNext?.addEventListener('click', () => {
    els.srvScroller.scrollBy({ left: 320, behavior: 'smooth' });
  });

  loadHome();
})();