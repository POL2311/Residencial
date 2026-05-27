(function () {
  'use strict';

  if (window.__osgateModalInitialized && window.OSGateModal) return;
  window.__osgateModalInitialized = true;
  if (typeof window.OSGateModalDebug === 'undefined') window.OSGateModalDebug = false;

  const MODAL_SELECTOR = [
    '.os-modal-v2',
    '.os-modal-overlay',
    '.app-admin-modal-overlay',
    '[role="dialog"]',
    '[id*="modal" i][class*="fixed"][class*="inset-0"]',
    '[id$="Modal"].fixed.inset-0',
    '[id$="Modal"][class*="fixed"][class*="inset-0"]'
  ].join(',');

  const MODAL_ROOT_SELECTOR = [
    '.os-modal-v2',
    '.os-modal-overlay',
    '.app-admin-modal-overlay',
    '[id*="modal" i][class*="fixed"][class*="inset-0"]',
    '[id$="Modal"].fixed.inset-0',
    '[id$="Modal"][class*="fixed"][class*="inset-0"]'
  ].join(',');

  const knownModals = new Set();
  const stats = { syncs: 0, candidates: 0, open: false };
  let scheduledFrame = 0;
  let viewportFrame = 0;
  let modalOpenState = null;

  function syncViewportHeight() {
    viewportFrame = 0;
    const height = Math.round(window.visualViewport?.height || window.innerHeight || 0);
    if (height > 0) {
      document.documentElement.style.setProperty('--os-modal-vh', `${height}px`);
    }
  }

  function scheduleViewportHeight() {
    if (viewportFrame) return;
    viewportFrame = window.requestAnimationFrame(syncViewportHeight);
  }

  function isElement(node) {
    return node && node.nodeType === 1;
  }

  function matchesModal(element) {
    return isElement(element) && element.matches(MODAL_SELECTOR);
  }

  function registerModal(element) {
    if (!matchesModal(element)) return false;
    if (knownModals.has(element)) return false;
    knownModals.add(element);
    return true;
  }

  function registerTree(root) {
    if (!root) return false;
    let found = false;

    if (isElement(root) && registerModal(root)) found = true;

    const queryRoot = root.querySelectorAll ? root : null;
    if (queryRoot) {
      queryRoot.querySelectorAll(MODAL_SELECTOR).forEach((element) => {
        if (registerModal(element)) found = true;
      });
    }

    return found;
  }

  function purgeDisconnected() {
    knownModals.forEach((modal) => {
      if (!document.documentElement.contains(modal)) {
        knownModals.delete(modal);
      }
    });
  }

  function removeTree(root) {
    if (!isElement(root)) return false;
    let removed = false;

    knownModals.forEach((modal) => {
      if (modal === root || root.contains(modal)) {
        knownModals.delete(modal);
        removed = true;
      }
    });

    return removed;
  }

  function isElementVisible(element) {
    if (!element || !(element instanceof Element)) return false;
    let current = element;
    while (current && current !== document.documentElement) {
      if (current.hidden || current.classList.contains('hidden')) return false;
      if (current.getAttribute('aria-hidden') === 'true') return false;
      current = current.parentElement;
    }

    const style = window.getComputedStyle(element);
    return style.display !== 'none' && style.visibility !== 'hidden';
  }

  function getCandidates() {
    purgeDisconnected();
    return Array.from(knownModals);
  }

  function getVisibleModals() {
    return getCandidates().filter(isElementVisible);
  }

  function setAttributeOnce(element, name, value) {
    if (element.getAttribute(name) !== value) element.setAttribute(name, value);
  }

  function prepareModal(element) {
    if (!element || !(element instanceof Element)) return;

    if (!element.classList.contains('os-modal-active')) {
      element.classList.add('os-modal-active');
    }
    if (!element.hasAttribute('role')) element.setAttribute('role', 'dialog');
    if (!element.hasAttribute('aria-modal')) element.setAttribute('aria-modal', 'true');
    if (!element.hasAttribute('tabindex')) element.setAttribute('tabindex', '-1');
  }

  function sync() {
    scheduledFrame = 0;

    const candidates = getCandidates();
    const visible = [];

    candidates.forEach((element) => {
      if (isElementVisible(element)) {
        visible.push(element);
        prepareModal(element);
      } else if (element.classList.contains('os-modal-active') || element.classList.contains('is-open')) {
        element.classList.remove('os-modal-active', 'is-open');
      }
    });

    const shouldOpen = visible.length > 0;
    if (modalOpenState !== shouldOpen || document.body.classList.contains('os-modal-open') !== shouldOpen) {
      document.body.classList.toggle('os-modal-open', shouldOpen);
      modalOpenState = shouldOpen;
    }

    stats.syncs += 1;
    stats.candidates = candidates.length;
    stats.open = shouldOpen;
    if (window.OSGateModalDebug) {
      console.debug('[OSGateModal]', {
        syncs: stats.syncs,
        candidates: stats.candidates,
        visible: visible.length,
        open: shouldOpen
      });
    }
  }

  function scheduleSync() {
    if (scheduledFrame) return;
    scheduledFrame = window.requestAnimationFrame(sync);
  }

  function open(modalElement) {
    if (!modalElement || !(modalElement instanceof Element)) return;

    registerTree(modalElement);
    modalElement.classList.remove('hidden');
    if (!modalElement.classList.contains('is-open')) modalElement.classList.add('is-open');
    if (modalElement.hidden) modalElement.hidden = false;
    setAttributeOnce(modalElement, 'aria-hidden', 'false');
    prepareModal(modalElement);
    scheduleSync();
  }

  function close(modalElement) {
    if (!modalElement || !(modalElement instanceof Element)) return;

    registerTree(modalElement);
    if (!modalElement.classList.contains('hidden')) modalElement.classList.add('hidden');
    modalElement.classList.remove('is-open', 'os-modal-active');
    setAttributeOnce(modalElement, 'aria-hidden', 'true');
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

  function closeFromTrigger(event) {
    const trigger = event.target?.closest?.('[data-close-modal]');
    if (!trigger) return;

    const modal = trigger.closest(MODAL_ROOT_SELECTOR);
    if (!modal) return;

    event.preventDefault();
    modal.dispatchEvent(new CustomEvent('osgate:modal-close-request', {
      bubbles: true,
      detail: { trigger }
    }));
    close(modal);
  }

  function shouldSyncForAttribute(target) {
    if (!isElement(target)) return false;
    if (knownModals.has(target)) return true;
    return registerModal(target);
  }

  function handleMutations(records) {
    let shouldSync = false;

    records.forEach((record) => {
      if (record.type === 'attributes') {
        if (shouldSyncForAttribute(record.target)) shouldSync = true;
        return;
      }

      if (record.type !== 'childList') return;

      record.addedNodes.forEach((node) => {
        if (registerTree(node)) shouldSync = true;
      });
      record.removedNodes.forEach((node) => {
        if (removeTree(node)) shouldSync = true;
      });
    });

    if (shouldSync) scheduleSync();
  }

  function boot() {
    registerTree(document);
    syncViewportHeight();

    const observer = new MutationObserver(handleMutations);
    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ['class', 'hidden', 'style', 'aria-hidden'],
      childList: true,
      subtree: true
    });

    document.addEventListener('keydown', closeTopEscModal);
    document.addEventListener('click', closeFromTrigger);
    window.addEventListener('resize', scheduleViewportHeight, { passive: true });
    if (window.visualViewport) {
      window.visualViewport.addEventListener('resize', scheduleViewportHeight, { passive: true });
    }
    sync();
  }

  window.OSGateModal = {
    open,
    close,
    sync,
    register: registerTree,
    visibleModals: getVisibleModals,
    stats
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
  } else {
    boot();
  }
})();
