// admin_residencial/js/residentes.js

(function () {
  // =========================
  // ELEMENTOS
  // =========================
  const els = {
    view: document.getElementById('residentesView'),
    list: document.getElementById('residentesList'),
    empty: document.getElementById('residentesEmpty'),
    alert: document.getElementById('residentesAlert'),
    btnAdd: document.getElementById('btnAddResidente'),

    modal: document.getElementById('modal'),
    modalTitle: document.getElementById('modalTitle'),
    modalForm: document.getElementById('modalForm'),
    btnCloseModal: document.getElementById('btnCloseModal'),
    btnCancelModal: document.getElementById('btnCancelModal'),
    btnInlineAddUnidad: document.getElementById('btnInlineAddUnidad'),
    inlineUnidadPanel: document.getElementById('inlineUnidadPanel'),
    inlineUnidadForm: document.getElementById('inlineUnidadForm'),
    inlineUnidadAlert: document.getElementById('inlineUnidadAlert'),
    inlineUnidadEmptyHint: document.getElementById('inlineUnidadEmptyHint'),
    btnCancelInlineUnidad: document.getElementById('btnCancelInlineUnidad'),
    btnSaveInlineUnidad: document.getElementById('btnSaveInlineUnidad'),

    detailModal: document.getElementById('detailModal'),
    detailModalContent: document.getElementById('detailModalContent'),

    residentSearch: document.getElementById('residentSearch'),
  };

  if (!els.view) return;

  // =========================
  // ESTADO
  // =========================
  const state = {
    residentes: [],
    filteredResidentes: [],
    unidades: [],
    editingId: null,
    selected: null,

    pagos: [],
    autos: [],
    pagosPage: 1,
    autosPage: 1,
    residentesPage: 1,
    currentUserId: null,
    selectedUnidadId: '',
  };

  // =========================
  // CONSTANTES
  // =========================
  const PAGOS_PER_PAGE = 3;
  const AUTOS_PER_PAGE = 3;
  const RESIDENTES_PER_PAGE = 3;
  const API_BASE = '/admin_residencial/php/api';

  const MESSAGES = {
    residenteCreado: 'Residente agregado correctamente.',
    residenteActualizado: 'Residente actualizado correctamente.',
    residenteEliminado: 'Residente eliminado correctamente.',
    residenteSuspendido: 'Residente suspendido correctamente.',
    residenteReactivado: 'Residente reactivado correctamente.',
    residenteBaneado: 'Residente bloqueado manualmente.',
    residenteDesbaneado: 'Bloqueo manual retirado correctamente.',
    autoCreado: 'Auto registrado correctamente.',
    pagoCreado: 'Pago registrado correctamente.',
  };

  const ALLOWED_PAYMENT_METHODS = ['efectivo', 'transferencia', 'tarjeta'];

  // =========================
  // HELPERS UI
  // =========================
  function showAlert(msg, type = 'info') {
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (type === 'error'
        ? 'bg-rose-100 text-rose-700'
        : 'bg-sky-100 text-sky-700');

    els.alert.textContent = msg;
    els.alert.classList.remove('hidden');
  }

  function hideAlert() {
    els.alert.classList.add('hidden');
  }

  function ensureUiHelpers() {
    if (document.getElementById('residentesUiLayer')) return;

    const layer = document.createElement('div');
    layer.id = 'residentesUiLayer';
    layer.innerHTML = `
      <div id="friendlyConfirm"
           class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden">
          <div class="p-6">
            <div class="flex items-start gap-4">
              <div id="friendlyConfirmIcon"
                   class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold">
                !
              </div>
              <div class="flex-1">
                <h3 id="friendlyConfirmTitle" class="text-xl font-semibold text-slate-900">
                  Confirmar acción
                </h3>
                <p id="friendlyConfirmMessage" class="mt-2 text-sm leading-6 text-slate-600">
                  ¿Deseas continuar?
                </p>
              </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
              <button id="friendlyConfirmCancel"
                      type="button"
                      class="rounded-full bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                Cancelar
              </button>
              <button id="friendlyConfirmAccept"
                      type="button"
                      class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                Aceptar
              </button>
            </div>
          </div>
        </div>
      </div>

      <div id="friendlyToastWrap"
           class="fixed top-4 right-4 z-[10010] flex w-full max-w-sm flex-col gap-3 pointer-events-none">
      </div>
    `;
    document.body.appendChild(layer);
  }

  function showToast(message, type = 'success') {
    ensureUiHelpers();

    const wrap = document.getElementById('friendlyToastWrap');
    const toast = document.createElement('div');

    const styles = {
      success: 'border-emerald-200 bg-emerald-50 text-emerald-800',
      error: 'border-rose-200 bg-rose-50 text-rose-800',
      info: 'border-sky-200 bg-sky-50 text-sky-800',
    };

    toast.className =
      `pointer-events-auto rounded-2xl border px-4 py-3 shadow-lg ${styles[type] || styles.info}`;

    toast.innerHTML = `
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium">${escapeHtml(message)}</p>
        <button type="button"
                class="text-lg leading-none opacity-60 hover:opacity-100">
          &times;
        </button>
      </div>
    `;

    const closeBtn = toast.querySelector('button');
    closeBtn?.addEventListener('click', () => toast.remove());

    wrap.appendChild(toast);

    setTimeout(() => {
      toast.remove();
    }, 3200);
  }

  function showConfirm({
    title = 'Confirmar acción',
    message = '¿Deseas continuar?',
    acceptText = 'Aceptar',
    cancelText = 'Cancelar',
    tone = 'danger',
  } = {}) {
    ensureUiHelpers();

    return new Promise((resolve) => {
      const modal = document.getElementById('friendlyConfirm');
      const titleEl = document.getElementById('friendlyConfirmTitle');
      const messageEl = document.getElementById('friendlyConfirmMessage');
      const acceptBtn = document.getElementById('friendlyConfirmAccept');
      const cancelBtn = document.getElementById('friendlyConfirmCancel');
      const iconEl = document.getElementById('friendlyConfirmIcon');

      titleEl.textContent = title;
      messageEl.textContent = message;
      acceptBtn.textContent = acceptText;
      cancelBtn.textContent = cancelText;

      if (tone === 'danger') {
        iconEl.className =
          'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold';
        acceptBtn.className =
          'rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700';
        iconEl.textContent = '!';
      } else {
        iconEl.className =
          'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 text-xl font-bold';
        acceptBtn.className =
          'rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700';
        iconEl.textContent = '✓';
      }

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      const cleanup = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        acceptBtn.onclick = null;
        cancelBtn.onclick = null;
        modal.onclick = null;
        document.removeEventListener('keydown', onKeydown);
      };

      const onKeydown = (e) => {
        if (e.key === 'Escape') {
          cleanup();
          resolve(false);
        }
      };

      acceptBtn.onclick = () => {
        cleanup();
        resolve(true);
      };

      cancelBtn.onclick = () => {
        cleanup();
        resolve(false);
      };

      modal.onclick = (e) => {
        if (e.target === modal) {
          cleanup();
          resolve(false);
        }
      };

      document.addEventListener('keydown', onKeydown);
    });
  }

  function showPrompt({
    title = 'Motivo',
    message = 'Escribe un motivo (opcional):',
    placeholder = 'Opcional',
    defaultValue = '',
    acceptText = 'Guardar',
    cancelText = 'Cancelar',
    tone = 'danger',
  } = {}) {
    ensureUiHelpers();

    if (!document.getElementById('friendlyPrompt')) {
      const layer = document.getElementById('residentesUiLayer');
      if (layer) {
        const wrap = document.createElement('div');
        wrap.innerHTML = `
          <div id="friendlyPrompt"
               class="hidden fixed inset-0 z-[10005] items-center justify-center bg-black/50 p-4">
            <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden">
              <div class="p-6">
                <div class="flex items-start gap-4">
                  <div id="friendlyPromptIcon"
                       class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold">
                    !
                  </div>
                  <div class="flex-1">
                    <h3 id="friendlyPromptTitle" class="text-xl font-semibold text-slate-900">
                      ${escapeHtml(title)}
                    </h3>
                    <p id="friendlyPromptMessage" class="mt-2 text-sm leading-6 text-slate-600">
                      ${escapeHtml(message)}
                    </p>
                    <textarea id="friendlyPromptInput"
                              rows="3"
                              class="mt-4 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#2E5D73]/20"
                              placeholder="${escapeHtml(placeholder)}"></textarea>
                  </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                  <button id="friendlyPromptCancel"
                          type="button"
                          class="rounded-full bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                    ${escapeHtml(cancelText)}
                  </button>
                  <button id="friendlyPromptAccept"
                          type="button"
                          class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                    ${escapeHtml(acceptText)}
                  </button>
                </div>
              </div>
            </div>
          </div>
        `;
        layer.appendChild(wrap);
      }
    }

    return new Promise((resolve) => {
      const modal = document.getElementById('friendlyPrompt');
      const titleEl = document.getElementById('friendlyPromptTitle');
      const messageEl = document.getElementById('friendlyPromptMessage');
      const acceptBtn = document.getElementById('friendlyPromptAccept');
      const cancelBtn = document.getElementById('friendlyPromptCancel');
      const iconEl = document.getElementById('friendlyPromptIcon');
      const inputEl = document.getElementById('friendlyPromptInput');

      if (!modal || !titleEl || !messageEl || !acceptBtn || !cancelBtn || !iconEl || !inputEl) {
        resolve(null);
        return;
      }

      titleEl.textContent = title;
      messageEl.textContent = message;
      acceptBtn.textContent = acceptText;
      cancelBtn.textContent = cancelText;
      inputEl.value = String(defaultValue || '');
      inputEl.placeholder = placeholder;

      if (tone === 'danger') {
        iconEl.className =
          'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold';
        acceptBtn.className =
          'rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700';
        iconEl.textContent = '!';
      } else {
        iconEl.className =
          'flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 text-xl font-bold';
        acceptBtn.className =
          'rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700';
        iconEl.textContent = '✓';
      }

      modal.classList.remove('hidden');
      modal.classList.add('flex');
      setTimeout(() => inputEl.focus(), 0);

      const cleanup = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        acceptBtn.onclick = null;
        cancelBtn.onclick = null;
        modal.onclick = null;
        document.removeEventListener('keydown', onKeydown);
      };

      const onKeydown = (e) => {
        if (e.key === 'Escape') {
          cleanup();
          resolve(null);
        }
      };

      acceptBtn.onclick = () => {
        const value = String(inputEl.value || '');
        cleanup();
        resolve(value);
      };

      cancelBtn.onclick = () => {
        cleanup();
        resolve(null);
      };

      modal.onclick = (e) => {
        if (e.target === modal) {
          cleanup();
          resolve(null);
        }
      };

      document.addEventListener('keydown', onKeydown);
    });
  }

  function updateResidentInState(updated) {
    if (!updated) return;
    const userId = String(updated.user_id || '');
    const residUnidId = String(updated.resid_unid_id || '');
    if (!userId && !residUnidId) return;

    const merge = (r) => {
      if (!r) return r;
      if (userId && String(r.user_id || '') === userId) return { ...r, ...updated };
      if (residUnidId && String(r.resid_unid_id || '') === residUnidId) return { ...r, ...updated };
      return r;
    };

    state.residentes = state.residentes.map(merge);
    state.filteredResidentes = state.filteredResidentes.map(merge);

    if (state.selected && (String(state.selected.user_id || '') === userId || String(state.selected.resid_unid_id || '') === residUnidId)) {
      state.selected = { ...state.selected, ...updated };
    }
  }

  function bindModalClose(modal, selectors = []) {
    selectors.forEach((selector) => {
      const el = modal.querySelector(selector);
      if (el) el.onclick = () => {
        modal.remove();
        document.body.style.overflow = '';
      };
    });
  }

  // =========================
  // HELPERS DE SEGURIDAD / SANITIZADO
  // =========================
  function escapeHtml(value = '') {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function normalizePhoneDigits(value = '') {
    return String(value).replace(/\D+/g, '');
  }

  function getWhatsAppUrl(phone = '') {
    const digits = normalizePhoneDigits(phone);
    if (digits.length < 10) return '';
    return `https://wa.me/52${digits}`;
  }

  function getTelUrl(phone = '') {
    const digits = normalizePhoneDigits(phone);
    if (digits.length < 7) return '';
    return `tel:${digits}`;
  }

  function normalizePlacas(value = '') {
    return String(value).trim().toUpperCase().replace(/\s+/g, '');
  }

  function isValidDateYMD(value = '') {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(String(value))) return false;
    const date = new Date(`${value}T00:00:00`);
    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
  }

  // =========================
  // HELPERS DE FORMATO
  // =========================
  function paginate(array, page = 1, perPage = 3) {
    const start = (page - 1) * perPage;
    return array.slice(start, start + perPage);
  }

  function renderPagination(total, page, perPage, type) {
    const pages = Math.ceil(total / perPage);
    if (pages <= 1) return '';

    let html = `<div class="flex gap-1 justify-end mt-3 text-sm" data-type="${type}">`;
    html += `
      <button ${page === 1 ? 'disabled' : ''}
        data-page="${page - 1}"
        class="px-3 py-1 rounded border ${page === 1 ? 'opacity-50 cursor-not-allowed' : ''}">
        ◀
      </button>
    `;

    for (let i = 1; i <= pages; i++) {
      html += `
        <button data-page="${i}"
          class="px-3 py-1 rounded border ${i === page ? 'bg-slate-900 text-white' : ''}">
          ${i}
        </button>
      `;
    }

    html += `
      <button ${page === pages ? 'disabled' : ''}
        data-page="${page + 1}"
        class="px-3 py-1 rounded border ${page === pages ? 'opacity-50 cursor-not-allowed' : ''}">
        ▶
      </button>
    </div>`;

    return html;
  }

  function getPagoFecha(p) {
    return p.fecha_pago || p.fecha || p.payment_date || p.date || p.created_at || '';
  }

  function formatFecha(fecha) {
    if (!fecha) return '—';

    const clean = String(fecha).trim().slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(clean)) return escapeHtml(fecha);

    const [y, m, d] = clean.split('-');
    return `${d}/${m}/${y}`;
  }

  function formatMoney(value) {
    const num = Number(value || 0);
    return num.toLocaleString('es-MX', {
      style: 'currency',
      currency: 'MXN',
      minimumFractionDigits: 2,
    });
  }

  function accessBadge(r) {
    const label = escapeHtml(r.access_label || 'Sin estado');
    const reason = escapeHtml(r.access_reason || 'Estado no disponible');
    const classes = r.allow_direct_access
      ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
      : 'border-rose-200 bg-rose-50 text-rose-700';

    return `
      <div class="inline-flex flex-col gap-1 rounded-2xl border px-3 py-2 ${classes}">
        <span class="text-[11px] font-semibold uppercase tracking-wide">${label}</span>
        <span class="text-[11px] normal-case tracking-normal opacity-90">${reason}</span>
      </div>
    `;
  }

  function sanitizeUserMessage(message, fallback = 'No se pudo completar la solicitud.') {
    let msg = String(message || '').trim();
    if (!msg) return fallback;
    msg = msg
      .replace(/\s*\(HTTP\s+\d+(?:\s*\(redirect\))?\)\.?/gi, '')
      .replace(/\bError HTTP\s+\d+\b/gi, '')
      .replace(/\bHTTP\s+\d+(?:\s*\(redirect\))?:\s*/gi, '')
      .replace(/\s*Debug:\s*[^.]+\.?/gi, '')
      .trim();
    return msg || fallback;
  }

  // =========================
  // API
  // =========================
  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });

    const text = await res.text();
    let json;

    try {
      json = JSON.parse(text);
    } catch (_) {
      console.error('[admin_residencial/residentes] Respuesta inválida', { url, status: res.status, text });
      throw new Error(sanitizeUserMessage(`Respuesta inválida del servidor (${res.status})`));
    }

    if (!res.ok || !json.ok) {
      console.error('[admin_residencial/residentes] API error', { url, status: res.status, json });
      throw new Error(sanitizeUserMessage(json.error || json.message || `Error HTTP ${res.status}`));
    }

    return json;
  }

  const api = {
    residentes: {
      list: () =>
        fetchJSON(`${API_BASE}/residentes.php?action=list`),

      get: (residUnidId) =>
        fetchJSON(`${API_BASE}/residentes.php?action=get&id=${encodeURIComponent(residUnidId)}`),

      save: (formData) =>
        fetchJSON(`${API_BASE}/residentes.php`, {
          method: 'POST',
          body: formData,
        }),

      remove: (id) => {
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('id', id);

        return fetchJSON(`${API_BASE}/residentes.php`, {
          method: 'POST',
          body: fd,
        });
      },

      toggleActive: (id, active) => {
        const fd = new FormData();
        fd.append('action', 'toggle_active');
        fd.append('id', id);
        fd.append('active', active ? '1' : '0');

        return fetchJSON(`${API_BASE}/residentes.php`, {
          method: 'POST',
          body: fd,
        });
      },

      toggleManualBan: (id, ban, motivo = '') => {
        const fd = new FormData();
        fd.append('action', 'toggle_manual_ban');
        fd.append('id', id);
        fd.append('ban', ban ? '1' : '0');
        if (motivo) fd.append('motivo', motivo);

        return fetchJSON(`${API_BASE}/residentes.php`, {
          method: 'POST',
          body: fd,
        });
      },
    },

    pagos: {
      list: (userId) =>
        fetchJSON(`${API_BASE}/pagos_residentes.php?action=list&user_id=${encodeURIComponent(userId)}`),

      create: (formData) =>
        fetchJSON(`${API_BASE}/pagos_residentes.php`, {
          method: 'POST',
          body: formData,
        }),
    },

    autos: {
      listByResident: (userId) =>
        fetchJSON(`${API_BASE}/autos_admin.php?action=list_by_resident&user_id=${encodeURIComponent(userId)}`),

      create: (formData) =>
        fetchJSON(`${API_BASE}/autos_admin.php`, {
          method: 'POST',
          body: formData,
        }),
    },

    unidades: {
      createInline: (formData) =>
        fetchJSON(`${API_BASE}/unidades.php`, {
          method: 'POST',
          body: formData,
        }),
    },
  };

  // =========================
  // RENDER
  // =========================
  function renderPagos() {
    const visibles = paginate(state.pagos, state.pagosPage, PAGOS_PER_PAGE);

    if (!visibles.length) {
      return `<p class="text-xs text-slate-500">Sin pagos registrados.</p>`;
    }

    return `
      <div data-type="pagos" class="space-y-3">
        <div class="overflow-hidden rounded-2xl border border-slate-200">
          <table class="min-w-full table-fixed text-sm">
            <thead class="bg-slate-100 text-slate-700">
              <tr>
                <th class="w-[22%] px-4 py-3 text-left font-semibold">Fecha</th>
                <th class="w-[20%] px-4 py-3 text-right font-semibold">Monto</th>
                <th class="w-[20%] px-4 py-3 text-left font-semibold">Método</th>
                <th class="w-[38%] px-4 py-3 text-left font-semibold">Concepto</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
              ${visibles.map((p) => {
                const fecha = formatFecha(getPagoFecha(p));
                const monto = formatMoney(p.monto);
                const metodo = escapeHtml(p.metodo || p.method || p.medio || '—');
                const concepto = escapeHtml(
                  p.concepto || p.concept || p.descripcion || p.description || p.nota || '—'
                );

                return `
                  <tr class="align-middle">
                    <td class="px-4 py-3 text-slate-700 whitespace-nowrap">${fecha}</td>
                    <td class="px-4 py-3 text-right font-semibold text-slate-900 whitespace-nowrap">${monto}</td>
                    <td class="px-4 py-3 text-slate-700 capitalize">${metodo}</td>
                    <td class="px-4 py-3 text-slate-700 break-words">${concepto}</td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>

        ${renderPagination(state.pagos.length, state.pagosPage, PAGOS_PER_PAGE, 'pagos')}
      </div>
    `;
  }

  function renderAutos() {
    const visibles = paginate(state.autos, state.autosPage, AUTOS_PER_PAGE);

    if (!visibles.length) {
      return `<p class="text-xs text-slate-500">Sin autos asignados.</p>`;
    }

    return `
      <div data-type="autos">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          ${visibles.map((a) => `
            <div class="rounded-2xl border p-4 shadow-sm">
              <div class="font-semibold">${escapeHtml(a.placas || '—')}</div>
              <div class="text-xs text-slate-500">
                ${escapeHtml(a.modelo || '—')} • ${escapeHtml(a.color || '—')}
              </div>
              <div class="mt-2 text-xs text-slate-500">
                Tag: <span class="font-medium text-slate-700">${escapeHtml(a.tag_id || 'Sin tag')}</span>
              </div>
            </div>
          `).join('')}
        </div>

        ${renderPagination(state.autos.length, state.autosPage, AUTOS_PER_PAGE, 'autos')}
      </div>
    `;
  }

  function renderDetailModal(r, totalPagado) {
    return `
      <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl overflow-hidden">
        <div class="flex justify-between items-center px-6 py-4 border-b bg-slate-50">
          <div>
            <h2 class="text-lg font-semibold text-slate-800">Detalle del residente</h2>
            <p class="text-xs text-slate-500">Información general y actividad</p>
          </div>
          <button id="btnCloseDetail"
            class="h-9 w-9 rounded-full bg-white border hover:bg-slate-100 flex items-center justify-center">
            ✕
          </button>
        </div>

        <div class="p-6 space-y-6">
          <section class="rounded-2xl border bg-white p-5">
            <h3 class="text-sm font-semibold text-slate-700 mb-4">👤 Información del residente</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-xs text-slate-500">Nombre</p>
                <p class="font-medium text-slate-800">${escapeHtml(r.nombre || '—')}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500">Unidad</p>
                <p class="font-medium text-slate-800">${escapeHtml(r.unidad_clave || '—')}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500">Email</p>
                <p class="font-medium text-slate-800">${escapeHtml(r.email || '—')}</p>
              </div>
              <div>
                <p class="text-xs text-slate-500">Teléfono</p>
                <p class="font-medium text-slate-800">${escapeHtml(r.telefono || '—')}</p>
              </div>
            </div>
            <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
              ${accessBadge(r)}
              <button type="button"
                data-ban-toggle="${escapeHtml(r.resid_unid_id)}"
                data-ban-active="${r.acceso_baneado_manual ? '1' : '0'}"
                class="inline-flex items-center justify-center rounded-full ${r.acceso_baneado_manual ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-rose-100 text-rose-700 hover:bg-rose-200'} px-4 py-2 text-xs font-medium">
                ${r.acceso_baneado_manual ? 'Quitar baneo' : 'Banear acceso'}
              </button>
            </div>
          </section>

          <section class="rounded-2xl border bg-white p-5 space-y-4">
            <div class="flex justify-between items-center">
              <h3 class="text-sm font-semibold text-slate-700">💳 Pagos</h3>
              <button id="btnAddPago"
                class="px-4 py-2 rounded-xl text-xs bg-blue-500 text-white hover:bg-blue-600">
                + Agregar pago
              </button>
            </div>

            <div class="text-sm">
              <strong>Total pagado:</strong> ${formatMoney(totalPagado)}
            </div>

            ${renderPagos()}
          </section>

          <section class="rounded-2xl border bg-white p-5 space-y-4">
            <div class="flex justify-between items-center">
              <h3 class="text-sm font-semibold text-slate-700">🚗 Autos</h3>
              <button id="btnAddAuto"
                class="px-4 py-2 rounded-xl text-xs bg-slate-800 text-white hover:bg-slate-900">
                + Agregar auto
              </button>
            </div>

            ${renderAutos()}
          </section>
        </div>
      </div>
    `;
  }

  function renderResidenteCard(r) {
    const phoneDigits = normalizePhoneDigits(r.telefono || '');
    const telUrl = getTelUrl(phoneDigits);
    const waUrl = getWhatsAppUrl(phoneDigits);

    return `
      <div class="space-y-4">
        <div class="md:hidden">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="font-semibold text-slate-800">${escapeHtml(r.nombre || '—')}</div>
              <div class="text-xs text-slate-500">Unidad: ${escapeHtml(r.unidad_clave || '—')}</div>
              <div class="text-xs text-slate-600">${escapeHtml(r.telefono || 'Sin teléfono')}</div>
            </div>
            <div class="flex justify-center">
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox"
                  class="sr-only peer"
                  data-toggle="${escapeHtml(r.resid_unid_id)}"
                  ${r.activo_servicio == 1 ? 'checked' : ''}>
                <div class="
                  w-11 h-6 bg-slate-300 rounded-full peer
                  peer-checked:bg-emerald-500
                  after:content-['']
                  after:absolute after:top-[2px] after:left-[2px]
                  after:bg-white after:rounded-full after:h-5 after:w-5
                  after:transition-all
                  peer-checked:after:translate-x-full
                "></div>
              </label>
            </div>
          </div>
          <div class="mt-3 text-sm text-slate-700">
            ${escapeHtml(r.unidad_detalle || '—')}
          </div>
          <div class="mt-3">${accessBadge(r)}</div>
          <div class="mt-4 flex flex-wrap gap-2">
            ${telUrl ? `
              <a href="${telUrl}" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                Llamar
              </a>
            ` : ''}

            ${waUrl ? `
              <a href="${waUrl}" target="_blank" rel="noopener noreferrer"
                class="inline-flex items-center justify-center rounded-full bg-emerald-100 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-200">
                WhatsApp
              </a>
            ` : ''}

            <button data-more="${escapeHtml(r.resid_unid_id)}"
              class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
              Ver más
            </button>

            <button data-edit="${escapeHtml(r.resid_unid_id)}"
              class="app-mobile-actions inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-200">
              Editar
            </button>

            <button data-ban-toggle="${escapeHtml(r.resid_unid_id)}"
              data-ban-active="${r.acceso_baneado_manual ? '1' : '0'}"
              class="app-mobile-actions inline-flex items-center justify-center rounded-full ${r.acceso_baneado_manual ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-rose-100 text-rose-700 hover:bg-rose-200'} px-3 py-1.5 text-xs">
              ${r.acceso_baneado_manual ? 'Quitar baneo' : 'Banear'}
            </button>

            <button data-del="${escapeHtml(r.resid_unid_id)}"
              class="app-mobile-actions inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-200">
              Eliminar
            </button>
          </div>
        </div>

        <div class="hidden md:grid md:grid-cols-4 md:gap-4 md:items-center">
          <div>
            <div class="font-semibold text-slate-800">${escapeHtml(r.nombre || '—')}</div>
            <div class="text-xs text-slate-500">Unidad: ${escapeHtml(r.unidad_clave || '—')}</div>
            <div class="text-xs text-slate-600">${escapeHtml(r.telefono || 'Sin teléfono')}</div>
            <div class="mt-3">${accessBadge(r)}</div>
          </div>

          <div class="text-sm text-slate-700">
            ${escapeHtml(r.unidad_detalle || '—')}
          </div>

          <div class="flex justify-center">
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox"
                class="sr-only peer"
                data-toggle="${escapeHtml(r.resid_unid_id)}"
                ${r.activo_servicio == 1 ? 'checked' : ''}>
              <div class="
                w-11 h-6 bg-slate-300 rounded-full peer
                peer-checked:bg-emerald-500
                after:content-['']
                after:absolute after:top-[2px] after:left-[2px]
                after:bg-white after:rounded-full after:h-5 after:w-5
                after:transition-all
                peer-checked:after:translate-x-full
              "></div>
            </label>
          </div>

          <div class="flex justify-end gap-2 flex-wrap">
            ${telUrl ? `
              <a href="${telUrl}" class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
                Llamar
              </a>
            ` : ''}

            ${waUrl ? `
              <a href="${waUrl}" target="_blank" rel="noopener noreferrer"
                class="inline-flex items-center justify-center rounded-full bg-emerald-100 px-3 py-1.5 text-xs text-emerald-700 hover:bg-emerald-200">
                WhatsApp
              </a>
            ` : ''}

            <button data-more="${escapeHtml(r.resid_unid_id)}"
              class="inline-flex items-center justify-center rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-50">
              Ver más
            </button>

            <button data-edit="${escapeHtml(r.resid_unid_id)}"
              class="inline-flex items-center justify-center rounded-full bg-slate-100 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-200">
              Editar
            </button>

            <button data-ban-toggle="${escapeHtml(r.resid_unid_id)}"
              data-ban-active="${r.acceso_baneado_manual ? '1' : '0'}"
              class="inline-flex items-center justify-center rounded-full ${r.acceso_baneado_manual ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200' : 'bg-rose-100 text-rose-700 hover:bg-rose-200'} px-3 py-1.5 text-xs">
              ${r.acceso_baneado_manual ? 'Quitar baneo' : 'Banear'}
            </button>

            <button data-del="${escapeHtml(r.resid_unid_id)}"
              class="inline-flex items-center justify-center rounded-full bg-rose-100 px-3 py-1.5 text-xs text-rose-700 hover:bg-rose-200">
              Eliminar
            </button>
          </div>
        </div>
      </div>
    `;
  }

  function renderResidentes() {
    els.list.innerHTML = '';

    if (!state.filteredResidentes.length) {
      els.empty.classList.remove('hidden');
      return;
    }

    els.empty.classList.add('hidden');

    const visibles = paginate(
      state.filteredResidentes,
      state.residentesPage,
      RESIDENTES_PER_PAGE
    );

    visibles.forEach((r) => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-200 bg-white px-5 py-4 shadow-sm';
      card.innerHTML = renderResidenteCard(r);
      els.list.appendChild(card);
    });

    els.list.insertAdjacentHTML(
      'beforeend',
      renderPagination(
        state.filteredResidentes.length,
        state.residentesPage,
        RESIDENTES_PER_PAGE,
        'residentes'
      )
    );
  }

  function sortUnidades() {
    state.unidades.sort((a, b) =>
      String(a.clave || '').localeCompare(String(b.clave || ''), 'es', { numeric: true, sensitivity: 'base' })
    );
  }

  function renderUnidadOptionLabel(unidad) {
    return escapeHtml(unidad?.clave || '—');
  }

  function fillUnidades(selectedId = '') {
    const sel = els.modalForm?.unidad_id;
    if (!sel) return;

    sortUnidades();

    if (!state.unidades.length) {
      sel.innerHTML = '<option value="">No hay casas/unidades registradas todavía</option>';
      sel.value = '';
      els.inlineUnidadEmptyHint?.classList.remove('hidden');
      return;
    }

    els.inlineUnidadEmptyHint?.classList.add('hidden');

    const placeholder = '<option value="">Selecciona una unidad</option>';
    const options = state.unidades
      .map((u) => `<option value="${escapeHtml(u.id)}">${renderUnidadOptionLabel(u)}</option>`)
      .join('');

    sel.innerHTML = placeholder + options;
    sel.value = selectedId ? String(selectedId) : '';
  }

  function showInlineUnidadAlert(message, type = 'error') {
    if (!els.inlineUnidadAlert) return;

    els.inlineUnidadAlert.className =
      'rounded-2xl px-4 py-3 text-xs ' +
      (type === 'error'
        ? 'bg-rose-100 text-rose-700'
        : 'bg-emerald-100 text-emerald-700');

    els.inlineUnidadAlert.textContent = message;
    els.inlineUnidadAlert.classList.remove('hidden');
  }

  function hideInlineUnidadAlert() {
    els.inlineUnidadAlert?.classList.add('hidden');
  }

  function setInlineUnidadFieldsEnabled(enabled) {
    els.inlineUnidadForm?.querySelectorAll('input, select').forEach((field) => {
      field.disabled = !enabled;
    });
  }

  function openInlineUnidadPanel() {
    hideInlineUnidadAlert();
    setInlineUnidadFieldsEnabled(true);
    els.inlineUnidadForm?.querySelectorAll('input, select').forEach((field) => {
      if (field instanceof HTMLSelectElement) {
        field.selectedIndex = 0;
      } else {
        field.value = '';
      }
    });
    els.inlineUnidadPanel?.classList.remove('hidden');
    els.inlineUnidadForm?.querySelector('[name="clave"]')?.focus();
  }

  function closeInlineUnidadPanel(reset = true) {
    hideInlineUnidadAlert();
    if (reset) {
      els.inlineUnidadForm?.querySelectorAll('input, select').forEach((field) => {
        if (field instanceof HTMLSelectElement) {
          field.selectedIndex = 0;
        } else {
          field.value = '';
        }
      });
    }
    setInlineUnidadFieldsEnabled(false);
    els.inlineUnidadPanel?.classList.add('hidden');
  }

  // =========================
  // MODALES
  // =========================
  function openModal(mode, r = null) {
    document.body.style.overflow = 'hidden';
    els.modal.classList.remove('hidden');
    els.modalForm.reset();
    closeInlineUnidadPanel();
    state.selectedUnidadId = '';

    const sel = els.modalForm.unidad_id;

    const passwordHelp = els.modalForm
      .querySelector('[name="password"]')
      ?.parentElement?.querySelector('p');

    if (mode === 'edit' && r) {
      els.modalTitle.textContent = 'Editar residente';
      state.editingId = r.resid_unid_id;
      els.modalForm.nombre.value = r.nombre || '';
      els.modalForm.telefono.value = r.telefono || '';
      els.modalForm.email.value = r.email || '';
      if (els.modalForm.password) els.modalForm.password.value = '';
      if (els.modalForm.password_confirm) els.modalForm.password_confirm.value = '';

      if (passwordHelp) {
        passwordHelp.textContent = 'Déjalo vacío si no deseas cambiar la contraseña.';
      }

      state.selectedUnidadId = String(r.unidad_id || '');
      fillUnidades(state.selectedUnidadId);
    } else {
      els.modalTitle.textContent = 'Agregar residente';
      state.editingId = null;
      fillUnidades();

      if (passwordHelp) {
        passwordHelp.textContent = 'Si lo dejas vacío, se generará una contraseña temporal.';
      }
    }
  }

  function closeModal() {
    els.modal.classList.add('hidden');
    document.body.style.overflow = '';
    state.editingId = null;
    state.selectedUnidadId = '';
    closeInlineUnidadPanel();
  }

  function openAddPagoModal(residente) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/40 flex items-center justify-center z-50';

    modal.innerHTML = `
      <div class="bg-white rounded-3xl w-full max-w-xl p-8 shadow-2xl relative">
        <button id="closeAddPago"
          class="absolute top-4 right-4 h-9 w-9 rounded-full border hover:bg-slate-100">
          ✕
        </button>

        <h2 class="text-lg font-semibold">Agregar pago</h2>
        <p class="text-sm text-slate-500 mb-6">
          ${escapeHtml(residente.nombre || '—')} · Unidad ${escapeHtml(residente.unidad_clave || '—')}
        </p>

        <form id="addPagoForm" class="space-y-5">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-slate-500">Monto *</label>
              <input name="monto" type="number" step="0.01" required
                class="w-full mt-1 rounded-xl border px-4 py-2"
                placeholder="Ej. 850.00">
            </div>
            <div>
              <label class="text-xs text-slate-500">Fecha *</label>
              <input name="fecha" type="date" required
                class="w-full mt-1 rounded-xl border px-4 py-2">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-slate-500">Método</label>
              <select name="metodo" class="w-full mt-1 rounded-xl border px-4 py-2">
                <option value="efectivo">Efectivo</option>
                <option value="transferencia">Transferencia</option>
                <option value="tarjeta">Tarjeta</option>
              </select>
            </div>
            <div>
              <label class="text-xs text-slate-500">Concepto</label>
              <input name="concepto"
                class="w-full mt-1 rounded-xl border px-4 py-2"
                placeholder="Ej. Mantenimiento enero"
                maxlength="120">
            </div>
          </div>

          <input type="hidden" name="user_id" value="${escapeHtml(residente.user_id)}">
          <input type="hidden" name="action" value="create">

          <div class="flex justify-end gap-3 pt-6">
            <button type="button" id="cancelAddPago"
              class="px-5 py-2 rounded-xl border hover:bg-slate-100">
              Cancelar
            </button>
            <button class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white">
              Registrar pago
            </button>
          </div>
        </form>
      </div>
    `;

    document.body.appendChild(modal);
    bindModalClose(modal, ['#closeAddPago', '#cancelAddPago']);

    modal.querySelector('#addPagoForm').onsubmit = async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);

      const monto = Number(fd.get('monto') || 0);
      const fecha = String(fd.get('fecha') || '').trim();
      const metodo = String(fd.get('metodo') || '').trim().toLowerCase();
      const concepto = String(fd.get('concepto') || '').trim();

      if (!(monto > 0)) {
        showToast('El monto debe ser mayor a 0.', 'error');
        return;
      }

      if (!isValidDateYMD(fecha)) {
        showToast('La fecha del pago no es válida.', 'error');
        return;
      }

      if (metodo && !ALLOWED_PAYMENT_METHODS.includes(metodo)) {
        showToast('El método de pago no es válido.', 'error');
        return;
      }

      if (concepto.length > 120) {
        showToast('El concepto no puede exceder 120 caracteres.', 'error');
        return;
      }

      try {
        const resp = await api.pagos.create(fd);

        modal.remove();
        await refreshCurrentDetail({ resetPagos: true });
        const updated = resp?.data?.residente_actualizado || null;
        if (updated) {
          updateResidentInState(updated);
          renderResidentes();
        }
        showToast(resp.message || MESSAGES.pagoCreado, 'success');
      } catch (err) {
        showToast(err.message || 'No se pudo registrar el pago.', 'error');
      }
    };
  }

  function openAddAutoModal(residente) {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 bg-black/40 flex items-center justify-center z-50';

    modal.innerHTML = `
      <div class="bg-white rounded-3xl w-full max-w-xl p-8 shadow-2xl relative">
        <button id="closeAddAuto"
          class="absolute top-4 right-4 h-9 w-9 rounded-full border hover:bg-slate-100 flex items-center justify-center">
          ✕
        </button>

        <h2 class="text-lg font-semibold text-slate-800">Agregar auto</h2>
        <p class="text-sm text-slate-500 mb-6">
          ${escapeHtml(residente.nombre || '—')} · Unidad ${escapeHtml(residente.unidad_clave || '—')}
        </p>

        <form id="addAutoForm" class="space-y-5">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-slate-500">Placas *</label>
              <input name="placas" required
                class="w-full mt-1 rounded-xl border px-4 py-2 focus:ring-2 focus:ring-blue-500"
                placeholder="Ej. ABC-123"
                maxlength="15">
            </div>

            <div>
              <label class="text-xs text-slate-500">Modelo</label>
              <input name="modelo"
                class="w-full mt-1 rounded-xl border px-4 py-2 focus:ring-2 focus:ring-blue-500"
                placeholder="Ej. Versa 2020"
                maxlength="80">
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs text-slate-500">Color</label>
              <input name="color"
                class="w-full mt-1 rounded-xl border px-4 py-2 focus:ring-2 focus:ring-blue-500"
                placeholder="Ej. Blanco"
                maxlength="40">
            </div>

            <div>
              <label class="text-xs text-slate-500">Tag ID</label>
              <input name="tag_id"
                class="w-full mt-1 rounded-xl border px-4 py-2 focus:ring-2 focus:ring-blue-500"
                placeholder="Ej. TAG-001"
                maxlength="120">
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="text-xs text-slate-500">Casa asignada</label>
              <input disabled
                class="w-full mt-1 rounded-xl border bg-slate-100 px-4 py-2"
                value="${escapeHtml(residente.unidad_clave || '—')}">
            </div>
          </div>

          <input type="hidden" name="user_id" value="${escapeHtml(residente.user_id)}">
          <input type="hidden" name="unidad_id" value="${escapeHtml(residente.unidad_id)}">
          <input type="hidden" name="action" value="create">

          <div class="flex justify-end gap-3 pt-6">
            <button type="button" id="cancelAddAuto"
              class="px-5 py-2 rounded-xl border hover:bg-slate-100">
              Cancelar
            </button>
            <button class="px-5 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white">
              Crear auto
            </button>
          </div>
        </form>
      </div>
    `;

    document.body.appendChild(modal);
    bindModalClose(modal, ['#closeAddAuto', '#cancelAddAuto']);

    modal.querySelector('#addAutoForm').onsubmit = async (e) => {
      e.preventDefault();
      const fd = new FormData(e.target);

      const placas = normalizePlacas(fd.get('placas') || '');
      const modelo = String(fd.get('modelo') || '').trim();
      const color = String(fd.get('color') || '').trim();
      const tagId = String(fd.get('tag_id') || '').trim();

      fd.set('placas', placas);

      if (placas.length < 5 || placas.length > 15) {
        showToast('Las placas deben tener entre 5 y 15 caracteres.', 'error');
        return;
      }

      if (modelo.length > 80) {
        showToast('El modelo no puede exceder 80 caracteres.', 'error');
        return;
      }

      if (color.length > 40) {
        showToast('El color no puede exceder 40 caracteres.', 'error');
        return;
      }

      if (tagId.length > 120) {
        showToast('El Tag ID no puede exceder 120 caracteres.', 'error');
        return;
      }

      try {
        const resp = await api.autos.create(fd);

        modal.remove();
        await refreshCurrentDetail({ resetAutos: true });
        showToast(resp.message || MESSAGES.autoCreado, 'success');
      } catch (err) {
        showToast(err.message || 'No se pudo registrar el auto.', 'error');
      }
    };
  }

  // =========================
  // FLOWS
  // =========================
  async function loadData() {
    hideAlert();
    els.list.innerHTML = '';

    try {
      const json = await api.residentes.list();

      state.residentes = Array.isArray(json.residentes) ? json.residentes : [];
      state.filteredResidentes = [...state.residentes];
      state.unidades = Array.isArray(json.unidades) ? json.unidades : [];
      state.residentesPage = 1;

      renderResidentes();
      els.list.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  function filterResidentes(query) {
    query = String(query || '').toLowerCase().trim();

    if (!query) {
      state.filteredResidentes = [...state.residentes];
    } else {
      state.filteredResidentes = state.residentes.filter((r) =>
        String(r.nombre || '').toLowerCase().includes(query) ||
        String(r.unidad_clave || '').toLowerCase().includes(query) ||
        String(r.telefono || '').includes(query)
      );
    }

    state.residentesPage = 1;
    renderResidentes();
  }

  async function openDetail(residUnidId) {
    try {
      state.pagosPage = 1;
      state.autosPage = 1;

      const detailResp = await api.residentes.get(residUnidId);
      const r = detailResp.residente;
      state.selected = r;

      const resident = state.residentes.find((x) => x.resid_unid_id == residUnidId);
      const userId = resident ? resident.user_id : r.user_id;
      state.currentUserId = userId;

      const [pagosResp, autosResp] = await Promise.all([
        api.pagos.list(userId),
        api.autos.listByResident(userId),
      ]);

      state.pagos = Array.isArray(pagosResp.data?.pagos) ? pagosResp.data.pagos : [];
      state.autos = Array.isArray(autosResp.data?.autos) ? autosResp.data.autos : [];

      const totalPagado = state.pagos.reduce((sum, p) => sum + (Number(p.monto) || 0), 0);

      els.detailModalContent.innerHTML = renderDetailModal(r, totalPagado);
      els.detailModal.classList.remove('hidden');

      attachDetailEventListeners();
    } catch (e) {
      showAlert(e.message, 'error');
    }
  }

  async function refreshCurrentDetail({ resetPagos = false, resetAutos = false } = {}) {
    if (!state.selected?.resid_unid_id) return;

    if (resetPagos) state.pagosPage = 1;
    if (resetAutos) state.autosPage = 1;

    await openDetail(state.selected.resid_unid_id);
  }

  function attachDetailEventListeners() {
    document.getElementById('btnCloseDetail')?.addEventListener('click', () => {
      els.detailModal.classList.add('hidden');
    });

    document.getElementById('btnAddPago')?.addEventListener('click', () => {
      openAddPagoModal({
        user_id: state.currentUserId,
        nombre: state.selected.nombre,
        unidad_clave: state.selected.unidad_clave,
      });
    });

    document.getElementById('btnAddAuto')?.addEventListener('click', () => {
      openAddAutoModal({
        user_id: state.currentUserId,
        nombre: state.selected.nombre,
        unidad_id: state.selected.unidad_id,
        unidad_clave: state.selected.unidad_clave,
      });
    });
  }

  async function confirmDeleteResidente(id) {
    const confirmed = await showConfirm({
      title: 'Eliminar residente',
      message: 'Esta acción eliminará la relación del residente con la unidad. ¿Deseas continuar?',
      acceptText: 'Sí, eliminar',
      cancelText: 'Cancelar',
      tone: 'danger',
    });

    if (!confirmed) return;

    await deleteResidente(id);
  }

  async function deleteResidente(id) {
    try {
      const resp = await api.residentes.remove(id);
      await loadData();
      showToast(resp.message || MESSAGES.residenteEliminado, 'success');
    } catch (e) {
      showToast(e.message || 'No se pudo eliminar el residente.', 'error');
    }
  }

  async function toggleManualBan(id, currentlyBanned) {
    try {
      let motivo = '';
      if (!currentlyBanned) {
        const value = await showPrompt({
          title: 'Banear acceso',
          message: 'Escribe el motivo del baneo (opcional).',
          placeholder: 'Ej. Adeudo pendiente o instrucción de administración',
          defaultValue: '',
          acceptText: 'Banear',
          cancelText: 'Cancelar',
          tone: 'danger',
        });
        if (value === null) return;
        motivo = value;
      }

      const resp = await api.residentes.toggleManualBan(id, !currentlyBanned, motivo);
      await loadData();

      if (state.selected?.resid_unid_id == id) {
        await refreshCurrentDetail();
      }

      showToast(
        resp.message || (currentlyBanned ? MESSAGES.residenteDesbaneado : MESSAGES.residenteBaneado),
        'success'
      );
    } catch (err) {
      showToast(err.message || 'No se pudo actualizar el bloqueo manual.', 'error');
    }
  }

  // =========================
  // EVENTOS
  // =========================
  els.list.addEventListener('change', async (e) => {
    const input = e.target;
    if (!input.matches('input[type="checkbox"][data-toggle]')) return;

    const residUnidId = input.dataset.toggle;
    const newStatus = input.checked ? 1 : 0;

    try {
      await api.residentes.toggleActive(residUnidId, newStatus);

      const r = state.residentes.find((x) => x.resid_unid_id == residUnidId);
      if (r) r.activo_servicio = newStatus;

      showToast(
        newStatus ? MESSAGES.residenteReactivado : MESSAGES.residenteSuspendido,
        'success'
      );
    } catch (err) {
      input.checked = !newStatus;
      showToast(err.message || 'No se pudo actualizar el estado del residente.', 'error');
    }
  });

  els.detailModalContent.addEventListener('click', (e) => {
    const banBtn = e.target.closest('[data-ban-toggle]');
    if (banBtn) {
      toggleManualBan(banBtn.dataset.banToggle, banBtn.dataset.banActive === '1');
      return;
    }

    const btn = e.target.closest('[data-page]');
    if (!btn) return;

    const page = Number(btn.dataset.page);
    const wrapper = btn.closest('[data-type]');
    if (!wrapper) return;

    if (wrapper.dataset.type === 'pagos') {
      state.pagosPage = page;
    }

    if (wrapper.dataset.type === 'autos') {
      state.autosPage = page;
    }

    els.detailModalContent.innerHTML = renderDetailModal(
      state.selected,
      state.pagos.reduce((s, p) => s + (Number(p.monto) || 0), 0)
    );

    attachDetailEventListeners();
  });

  els.list.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-page]');
    if (!btn) return;

    const wrapper = btn.closest('[data-type]');
    if (!wrapper || wrapper.dataset.type !== 'residentes') return;

    const page = Number(btn.dataset.page);
    state.residentesPage = page;
    renderResidentes();
  });

  els.list.addEventListener('click', (e) => {
    const edit = e.target.closest('[data-edit]');
    const del = e.target.closest('[data-del]');
    const more = e.target.closest('[data-more]');
    const banBtn = e.target.closest('[data-ban-toggle]');

    if (edit) {
      const r = state.residentes.find((x) => x.resid_unid_id == edit.dataset.edit);
      if (r) openModal('edit', r);
    }

    if (more) {
      openDetail(more.dataset.more);
    }

    if (del) {
      confirmDeleteResidente(del.dataset.del);
    }

    if (banBtn) {
      toggleManualBan(banBtn.dataset.banToggle, banBtn.dataset.banActive === '1');
    }
  });

  els.residentSearch?.addEventListener('input', (e) => {
    filterResidentes(e.target.value);
  });

  els.btnAdd?.addEventListener('click', () => openModal('create'));
  els.btnCloseModal?.addEventListener('click', closeModal);
  els.btnCancelModal?.addEventListener('click', closeModal);
  els.btnInlineAddUnidad?.addEventListener('click', openInlineUnidadPanel);
  els.btnCancelInlineUnidad?.addEventListener('click', () => closeInlineUnidadPanel());

  els.btnSaveInlineUnidad?.addEventListener('click', async () => {
    const fd = new FormData();
    const clave = String(els.inlineUnidadForm?.querySelector('[name="clave"]')?.value || '').trim();
    const tipo = String(els.inlineUnidadForm?.querySelector('[name="tipo"]')?.value || '').trim();
    const torre = String(els.inlineUnidadForm?.querySelector('[name="torre"]')?.value || '').trim();

    if (!clave) {
      showInlineUnidadAlert('La clave es obligatoria.');
      return;
    }

    if (!['casa', 'departamento', 'local', 'otro'].includes(tipo)) {
      showInlineUnidadAlert('Selecciona un tipo válido.');
      return;
    }

    fd.set('action', 'create_inline_for_residente');
    fd.set('clave', clave);
    fd.set('tipo', tipo);
    fd.set('torre', torre);

    try {
      const resp = await api.unidades.createInline(fd);
      const unidad = resp.unidad || null;

      if (unidad?.id) {
        state.unidades = state.unidades.filter((item) => String(item.id) !== String(unidad.id));
        state.unidades.push(unidad);
        state.selectedUnidadId = String(unidad.id);
        fillUnidades(state.selectedUnidadId);
      }

      showInlineUnidadAlert(resp.message || 'Casa agregada correctamente.', 'success');
      showToast(resp.message || 'Casa agregada correctamente.', 'success');

      setTimeout(() => {
        closeInlineUnidadPanel();
      }, 250);
    } catch (err) {
      showInlineUnidadAlert(err.message || 'No se pudo crear la casa.');
    }
  });

  if (!els.modalForm) {
    console.error('[RESIDENTES] No se encontró #modalForm. La vista pudo no haberse montado correctamente.');
  } else {
    els.modalForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      try {
        const isEditing = !!state.editingId;

        const fd = new FormData(els.modalForm);
        fd.append('action', isEditing ? 'update' : 'create');
        if (isEditing) fd.append('id', state.editingId);

        const nombre = String(fd.get('nombre') || '').trim();
        const telefono = normalizePhoneDigits(fd.get('telefono') || '');
        const email = String(fd.get('email') || '').trim();
        const unidadId = String(fd.get('unidad_id') || '').trim();
        const password = String(fd.get('password') || '').trim();
        const passwordConfirm = String(fd.get('password_confirm') || '').trim();

        fd.set('telefono', telefono);

        if (nombre.length < 3 || nombre.length > 120) {
          showToast('El nombre debe tener entre 3 y 120 caracteres.', 'error');
          return;
        }

        if (telefono && (telefono.length < 10 || telefono.length > 15)) {
          showToast('El teléfono debe tener entre 10 y 15 dígitos.', 'error');
          return;
        }

        if (!email) {
          showToast('El correo es obligatorio.', 'error');
          return;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
          showToast('El correo no es válido.', 'error');
          return;
        }

        if (!unidadId) {
          showToast('Debes seleccionar una unidad.', 'error');
          return;
        }

        if (password || passwordConfirm) {
          if (password.length < 8) {
            showToast('La contraseña debe tener al menos 8 caracteres.', 'error');
            return;
          }

          if (password !== passwordConfirm) {
            showToast('Las contraseñas no coinciden.', 'error');
            return;
          }
        }

        const resp = await api.residentes.save(fd);

        closeModal();
        await loadData();

        showToast(
          resp.message || (isEditing ? MESSAGES.residenteActualizado : MESSAGES.residenteCreado),
          'success'
        );
      } catch (err) {
        showToast(err?.message || 'No se pudo guardar el residente.', 'error');
      }
    });
  }

  // =========================
  // INIT
  // =========================
  (function init() {
    ensureUiHelpers();
    els.btnAdd?.classList.remove('hidden');
    loadData();
  })();
})();
