(function () {
  'use strict';

  const MODAL_SELECTOR = [
    '.os-modal-overlay',
    '.app-admin-modal-overlay',
    '[role="dialog"]',
    '[id*="modal" i][class*="fixed"][class*="inset-0"]',
    '[id$="Modal"].fixed.inset-0',
    '[id$="Modal"][class*="fixed"][class*="inset-0"]'
  ].join(',');

  let scheduledFrame = 0;

  function isElementVisible(element) {
    if (!element || !(element instanceof Element)) return false;
    if (element.hidden || element.classList.contains('hidden')) return false;
    if (element.getAttribute('aria-hidden') === 'true') return false;

    const style = window.getComputedStyle(element);
    if (style.display === 'none' || style.visibility === 'hidden') return false;

    return true;
  }

  function getCandidates() {
    return Array.from(document.querySelectorAll(MODAL_SELECTOR));
  }

  function getVisibleModals() {
    return getCandidates().filter(isElementVisible);
  }

  function prepareModal(element) {
    if (!element || !(element instanceof Element)) return;

    element.classList.add('os-modal-active');
    if (!element.hasAttribute('role')) element.setAttribute('role', 'dialog');
    if (!element.hasAttribute('aria-modal')) element.setAttribute('aria-modal', 'true');
    if (!element.hasAttribute('tabindex')) element.setAttribute('tabindex', '-1');
  }

  function sync() {
    scheduledFrame = 0;

    const candidates = getCandidates();
    const visible = candidates.filter(isElementVisible);

    candidates.forEach((element) => {
      if (visible.includes(element)) {
        prepareModal(element);
      } else {
        element.classList.remove('os-modal-active', 'is-open');
      }
    });

    document.body.classList.toggle('os-modal-open', visible.length > 0);
  }

  function scheduleSync() {
    if (scheduledFrame) return;
    scheduledFrame = window.requestAnimationFrame(sync);
  }

  function open(modalElement) {
    if (!modalElement || !(modalElement instanceof Element)) return;

    modalElement.classList.remove('hidden');
    modalElement.classList.add('is-open');
    modalElement.removeAttribute('hidden');
    modalElement.setAttribute('aria-hidden', 'false');
    prepareModal(modalElement);
    scheduleSync();
  }

  function close(modalElement) {
    if (!modalElement || !(modalElement instanceof Element)) return;

    modalElement.classList.add('hidden');
    modalElement.classList.remove('is-open', 'os-modal-active');
    modalElement.setAttribute('aria-hidden', 'true');
    scheduleSync();
  }

  function closeTopEscModal(event) {
    if (event.key !== 'Escape') return;

    const escModals = getVisibleModals().filter((modal) => (
      modal.matches('[data-os-modal-close-on-esc], [data-close-on-esc="true"]')
    ));
    const topModal = escModals[escModals.length - 1];

    if (!topModal) return;
    event.preventDefault();
    topModal.dispatchEvent(new CustomEvent('osgate:modal-close-request', { bubbles: true }));
    close(topModal);
  }

  function boot() {
    const observer = new MutationObserver(scheduleSync);
    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ['class', 'hidden', 'style', 'aria-hidden'],
      childList: true,
      subtree: true
    });

    document.addEventListener('keydown', closeTopEscModal);
    sync();
  }

  window.OSGateModal = {
    open,
    close,
    sync,
    visibleModals: getVisibleModals
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
