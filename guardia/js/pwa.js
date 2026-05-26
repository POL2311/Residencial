(function () {
  function guardiaBasePath() {
    const path = window.location.pathname || '';
    const idx = path.indexOf('/guardia/');
    if (idx === -1) return '/guardia/';
    return path.slice(0, idx + '/guardia/'.length);
  }

  function canRegisterServiceWorker() {
    if (!('serviceWorker' in navigator)) return false;
    if (window.isSecureContext) return true;
    const host = window.location.hostname || '';
    return host === 'localhost' || host === '127.0.0.1';
  }

  if (!canRegisterServiceWorker()) return;

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register(`${guardiaBasePath()}sw.js`, { scope: guardiaBasePath() })
      .catch((error) => {
        console.warn('[OS Gate PWA] No se pudo registrar service worker', error);
      });
  });
})();
