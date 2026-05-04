(function () {
  const ROOT_ID = 'appToastRoot';
  const TOAST_LIFETIME = 4400;

  function ensureRoot() {
    let root = document.getElementById(ROOT_ID);
    if (root) return root;

    root = document.createElement('div');
    root.id = ROOT_ID;
    root.setAttribute('aria-live', 'polite');
    root.setAttribute('aria-atomic', 'true');
    root.className = 'pointer-events-none fixed inset-x-4 top-4 z-[90] flex flex-col items-stretch gap-3 sm:inset-x-auto sm:right-4 sm:top-4 sm:w-[min(24rem,calc(100vw-2rem))]';
    document.body.appendChild(root);
    return root;
  }

  function normalizeType(type) {
    const raw = String(type || '').trim().toLowerCase();
    if (raw === 'ok') return 'success';
    if (raw === 'danger') return 'error';
    if (raw === 'warn') return 'warning';
    return raw || 'info';
  }

  function getTone(type) {
    switch (normalizeType(type)) {
      case 'success':
        return {
          card: 'border-emerald-200 bg-emerald-50 text-emerald-900',
          badge: 'bg-emerald-500/10 text-emerald-700',
          title: 'Éxito',
        };
      case 'error':
        return {
          card: 'border-rose-200 bg-rose-50 text-rose-900',
          badge: 'bg-rose-500/10 text-rose-700',
          title: 'Error',
        };
      case 'warning':
        return {
          card: 'border-amber-200 bg-amber-50 text-amber-900',
          badge: 'bg-amber-500/10 text-amber-700',
          title: 'Atención',
        };
      default:
        return {
          card: 'border-sky-200 bg-sky-50 text-sky-900',
          badge: 'bg-sky-500/10 text-sky-700',
          title: 'Información',
        };
    }
  }

  function dismissToast(node) {
    if (!node || node.dataset.closing === '1') return;
    node.dataset.closing = '1';
    node.classList.add('translate-y-2', 'opacity-0');
    window.setTimeout(() => node.remove(), 220);
  }

  function buildToast({ type, title, message, duration }) {
    const tone = getTone(type);
    const toast = document.createElement('article');
    toast.className = [
      'pointer-events-auto w-full overflow-hidden rounded-2xl border shadow-[0_18px_45px_rgba(15,23,42,0.12)] backdrop-blur',
      tone.card,
      'translate-y-2 opacity-0 transition-all duration-200 ease-out',
    ].join(' ');

    const safeTitle = String(title || tone.title);
    const safeMessage = String(message || '').trim();
    const timeout = Number(duration || TOAST_LIFETIME);

    toast.innerHTML = `
      <div class="flex items-start gap-3 px-4 py-3.5">
        <div class="inline-flex shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] ${tone.badge}">
          ${safeTitle}
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-sm leading-6">${safeMessage || 'Operación realizada.'}</p>
        </div>
        <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-black/5 bg-white/50 text-lg leading-none text-current transition hover:bg-white/80" aria-label="Cerrar notificación">
          ×
        </button>
      </div>
    `;

    const closeButton = toast.querySelector('button');
    closeButton?.addEventListener('click', () => dismissToast(toast));

    if (timeout > 0) {
      window.setTimeout(() => dismissToast(toast), timeout);
    }

    return toast;
  }

  function showToast(options = {}) {
    const root = ensureRoot();
    const toast = buildToast(options);
    root.appendChild(toast);
    window.requestAnimationFrame(() => {
      toast.classList.remove('translate-y-2', 'opacity-0');
    });
    return toast;
  }

  window.AppToast = {
    show(options) {
      return showToast(options);
    },
    success(message, title = 'Éxito', duration) {
      return showToast({ type: 'success', title, message, duration });
    },
    error(message, title = 'Error', duration = 5600) {
      return showToast({ type: 'error', title, message, duration });
    },
    warning(message, title = 'Atención', duration) {
      return showToast({ type: 'warning', title, message, duration });
    },
    info(message, title = 'Información', duration) {
      return showToast({ type: 'info', title, message, duration });
    },
  };
})();
