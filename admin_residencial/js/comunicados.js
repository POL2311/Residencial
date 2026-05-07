(function () {
  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/comunicados.php';

  const els = {
    view: document.getElementById('comunicadosView'),
    alert: document.getElementById('comunicadosAlert'),
    list: document.getElementById('comunicadosList'),
    pagination: document.getElementById('comunicadosPagination'),

    btnAdd: document.getElementById('btnAddComunicado'),
    modal: document.getElementById('comunicadoModal'),
    modalTitle: document.getElementById('comunicadoModalTitle'),
    form: document.getElementById('comunicadoForm'),
    btnClose: document.getElementById('btnCloseComModal'),
    btnCancel: document.getElementById('btnCancelComModal'),
    formError: document.getElementById('comunicadoFormError'),
    imagePreview: document.getElementById('comunicadoImagePreview'),

    search: document.getElementById('comunicadoSearch'),
    filterEstado: document.getElementById('comunicadoFilterEstado'),
    filterPrioridad: document.getElementById('comunicadoFilterPrioridad'),
    btnResetFilters: document.getElementById('btnResetComunicadosFilters'),

    countTotal: document.getElementById('comCountTotal'),
    countPublicados: document.getElementById('comCountPublicados'),
    countBorradores: document.getElementById('comCountBorradores'),
  };

  if (!els.view || !els.list) return;

  const state = {
    comunicados: [],
    filtered: [],
    editingId: null,
    search: '',
    estado: 'todos',
    prioridad: 'todas',
    page: 1,
    perPage: 3,
  };

  function escapeHtml(value = '') {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function appRootBase() {
    const p = location.pathname || '';
    const i = p.indexOf('/admin_residencial/');
    if (i !== -1) return p.slice(0, i);
    const parts = p.split('/').filter(Boolean);
    return parts.length ? '/' + parts[0] : '';
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
    // Legacy stored URLs may start with /assets/... even when app is hosted under /residencial.
    if (u.startsWith('/assets/')) return appRootBase() + u;
    return u;
  }

  function sanitizeUserMessage(message, fallback = 'No se pudo completar la solicitud.') {
    let msg = String(message || '').trim();
    if (!msg) return fallback;
    msg = msg
      .replace(/\s*\(HTTP\s+\d+(?:\s*\(redirect\))?\)\.?/gi, '')
      .replace(/\bHTTP\s+\d+(?:\s*\(redirect\))?:\s*/gi, '')
      .replace(/\s*Debug:\s*[^.]+\.?/gi, '')
      .replace(/\s*Respuesta no JSON del servidor\.?/gi, '')
      .replace(/\s*\([^)]*\.{3}\)\s*$/gi, '')
      .trim();
    return msg || fallback;
  }

  function showAlert(msg, error = false) {
    if (!els.alert) return;
    els.alert.textContent = msg;
    els.alert.className =
      'rounded-xl px-4 py-3 text-sm ' +
      (error ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
    els.alert.classList.remove('hidden');

    setTimeout(() => {
      els.alert.classList.add('hidden');
    }, 4000);
  }

  function showFormError(msg) {
    if (!els.formError) {
      showAlert(msg, true);
      return;
    }
    els.formError.textContent = msg;
    els.formError.classList.remove('hidden');
  }

  function clearFormError() {
    if (!els.formError) return;
    els.formError.textContent = '';
    els.formError.classList.add('hidden');
  }

  function badge(p) {
    if (p === 'alta') return 'bg-rose-100 text-rose-700';
    if (p === 'media') return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-700';
  }

  function badgeEstado(estado) {
    return estado === 'publicado'
      ? 'bg-emerald-100 text-emerald-700'
      : 'bg-slate-100 text-slate-700';
  }

  function homeBadge(c) {
    const today = new Date().toISOString().slice(0, 10);
    const estado = String(c?.estado || '');
    const pub = String(c?.fecha_publicacion || '');
    const exp = String(c?.fecha_expiracion || '');

    if (estado !== 'publicado') {
      return { label: 'Borrador', cls: 'bg-slate-100 text-slate-700' };
    }
    if (pub && pub > today) {
      return { label: 'Programado', cls: 'bg-sky-100 text-sky-700' };
    }
    if (exp && exp < today) {
      return { label: 'Expirado', cls: 'bg-rose-100 text-rose-700' };
    }
    return { label: 'Vigente', cls: 'bg-emerald-100 text-emerald-700' };
  }

  function showHomeVisibilityHintFromForm(fd) {
    const today = new Date().toISOString().slice(0, 10);
    const estado = String(fd.get('estado') || '');
    const pub = String(fd.get('fecha_publicacion') || '').trim();
    const exp = String(fd.get('fecha_expiracion') || '').trim();

    if (estado !== 'publicado') {
      showAlert('Borrador: este comunicado no se mostrará en Home hasta publicarlo.');
      return;
    }
    if (pub && pub > today) {
      showAlert(`Programado: aparecerá en Home a partir de ${pub}.`);
      return;
    }
    if (exp && exp < today) {
      showAlert(`Expirado: no se mostrará en Home (expiró el ${exp}).`);
      return;
    }
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
    } catch (e) {
      const preview = (text || '').slice(0, 220).replace(/\s+/g, ' ').trim();
      const status = res.status || 0;
      console.error('[admin_residencial/comunicados] Respuesta no JSON', { url, status, preview, text });
      throw new Error(sanitizeUserMessage(`HTTP ${status}: Respuesta no JSON del servidor.` + (preview ? ` (${preview}...)` : '')));
    }

    if (!json || !json.ok) {
      const status = res.status || 0;
      const msg = (json && json.error) || 'Error';
      const dbg = json && json.debug_id ? ` Debug: ${json.debug_id}` : '';
      console.error('[admin_residencial/comunicados] API error', { url, status, json });
      throw new Error(sanitizeUserMessage(`HTTP ${status}: ${msg}.${dbg}`.trim()));
    }

    return json;
  }

  const api = {
    comunicados: {
      list: () => fetchJSON(API),
      save: (fd) =>
        fetchJSON(API, {
          method: 'POST',
          body: fd,
        }),
      archive: (id) => {
        const fd = new FormData();
        fd.append('action', 'archive');
        fd.append('id', id);

        return fetchJSON(API, {
          method: 'POST',
          body: fd,
        });
      },
    },
  };

  function ensureUiHelpers() {
    if (document.getElementById('comunicadosUiLayer')) return;

    const layer = document.createElement('div');
    layer.id = 'comunicadosUiLayer';
    layer.innerHTML = `
      <div id="friendlyConfirmComunicado"
           class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="w-full max-w-md rounded-3xl bg-white shadow-2xl overflow-hidden">
          <div class="p-6">
            <div class="flex items-start gap-4">
              <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600 text-xl font-bold">
                !
              </div>
              <div class="flex-1">
                <h3 id="friendlyConfirmComunicadoTitle" class="text-xl font-semibold text-slate-900">
                  Confirmar acción
                </h3>
                <p id="friendlyConfirmComunicadoMessage" class="mt-2 text-sm leading-6 text-slate-600">
                  ¿Deseas continuar?
                </p>
              </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
              <button id="friendlyConfirmComunicadoCancel"
                      type="button"
                      class="rounded-full bg-slate-100 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                Cancelar
              </button>
              <button id="friendlyConfirmComunicadoAccept"
                      type="button"
                      class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-700">
                Archivar
              </button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(layer);
  }

  function showConfirm({
    title = 'Confirmar acción',
    message = '¿Deseas continuar?',
    acceptText = 'Aceptar',
    cancelText = 'Cancelar',
  } = {}) {
    ensureUiHelpers();

    return new Promise((resolve) => {
      const modal = document.getElementById('friendlyConfirmComunicado');
      const titleEl = document.getElementById('friendlyConfirmComunicadoTitle');
      const messageEl = document.getElementById('friendlyConfirmComunicadoMessage');
      const acceptBtn = document.getElementById('friendlyConfirmComunicadoAccept');
      const cancelBtn = document.getElementById('friendlyConfirmComunicadoCancel');

      titleEl.textContent = title;
      messageEl.textContent = message;
      acceptBtn.textContent = acceptText;
      cancelBtn.textContent = cancelText;

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

  function openCreateModal() {
    state.editingId = null;
    clearFormError();
    els.form?.reset();
    if (els.modalTitle) els.modalTitle.textContent = 'Nuevo comunicado';
    if (els.form?.imagen_url) els.form.imagen_url.value = '';
    if (els.imagePreview) {
      els.imagePreview.src = '';
      els.imagePreview.classList.add('hidden');
    }

    const fechaPub = els.form?.querySelector('[name="fecha_publicacion"]');
    if (fechaPub && !fechaPub.value) {
      fechaPub.value = new Date().toISOString().slice(0, 10);
    }

    els.modal?.classList.remove('hidden');
  }

  function openEditModal(c) {
    state.editingId = c.id;
    clearFormError();
    els.form?.reset();
    if (els.modalTitle) els.modalTitle.textContent = 'Editar comunicado';

    Object.keys(c).forEach((k) => {
      // Los inputs file no se pueden setear programáticamente; solo guardamos el URL actual.
      if (k === 'imagen') return;
      if (els.form && els.form[k]) {
        els.form[k].value = c[k] ?? '';
      }
    });

    if (els.imagePreview) {
      const url = resolvePublicUrl(c.imagen_url);
      if (url) {
        els.imagePreview.src = url;
        els.imagePreview.classList.remove('hidden');
      } else {
        els.imagePreview.src = '';
        els.imagePreview.classList.add('hidden');
      }
    }

    els.modal?.classList.remove('hidden');
  }

  function closeModal() {
    els.modal?.classList.add('hidden');
    clearFormError();
    state.editingId = null;
    els.form?.reset();
    if (els.imagePreview) {
      els.imagePreview.src = '';
      els.imagePreview.classList.add('hidden');
    }
  }

  function validateForm(fd) {
    const titulo = String(fd.get('titulo') || '').trim();
    const mensaje = String(fd.get('mensaje') || '').trim();
    const tipo = String(fd.get('tipo') || '').trim();
    const prioridad = String(fd.get('prioridad') || '').trim();
    const estado = String(fd.get('estado') || '').trim();
    const fechaPub = String(fd.get('fecha_publicacion') || '').trim();
    const fechaExp = String(fd.get('fecha_expiracion') || '').trim();

    if (titulo.length < 3) throw new Error('El título debe tener al menos 3 caracteres.');
    if (titulo.length > 150) throw new Error('El título no puede exceder 150 caracteres.');
    if (mensaje.length < 5) throw new Error('El mensaje debe tener al menos 5 caracteres.');
    if (mensaje.length > 3000) throw new Error('El mensaje no puede exceder 3000 caracteres.');

    if (!['general', 'mantenimiento', 'seguridad', 'pagos'].includes(tipo)) {
      throw new Error('Tipo inválido.');
    }

    if (!['baja', 'media', 'alta'].includes(prioridad)) {
      throw new Error('Prioridad inválida.');
    }

    if (!['publicado', 'borrador'].includes(estado)) {
      throw new Error('Estado inválido.');
    }

    if (!fechaPub) throw new Error('La fecha de publicación es requerida.');

    if (fechaExp && fechaExp < fechaPub) {
      throw new Error('La fecha de expiración no puede ser menor a la fecha de publicación.');
    }
  }

  function applyFilters() {
    const q = state.search.trim().toLowerCase();

    state.filtered = state.comunicados.filter((c) => {
      const matchSearch = !q || (
        String(c.titulo || '').toLowerCase().includes(q) ||
        String(c.tipo || '').toLowerCase().includes(q) ||
        String(c.prioridad || '').toLowerCase().includes(q) ||
        String(c.estado || '').toLowerCase().includes(q) ||
        String(c.mensaje || '').toLowerCase().includes(q)
      );

      const matchEstado =
        state.estado === 'todos' ? true : String(c.estado || '') === state.estado;

      const matchPrioridad =
        state.prioridad === 'todas' ? true : String(c.prioridad || '') === state.prioridad;

      return matchSearch && matchEstado && matchPrioridad;
    });

    state.page = 1;
    updateSummary();
  }

  function updateSummary() {
    const total = state.comunicados.length;
    const publicados = state.comunicados.filter(c => c.estado === 'publicado').length;
    const borradores = state.comunicados.filter(c => c.estado === 'borrador').length;

    if (els.countTotal) els.countTotal.textContent = total;
    if (els.countPublicados) els.countPublicados.textContent = publicados;
    if (els.countBorradores) els.countBorradores.textContent = borradores;
  }

  function paginate(items, page, perPage) {
    const start = (page - 1) * perPage;
    return items.slice(start, start + perPage);
  }

  function renderPagination(totalPages) {
    if (!els.pagination) return;
    els.pagination.innerHTML = '';

    if (totalPages <= 1) return;

    const prev = document.createElement('button');
    prev.textContent = '◀';
    prev.className = 'px-3 py-1 rounded border text-sm disabled:opacity-40';
    prev.disabled = state.page === 1;
    prev.addEventListener('click', () => {
      state.page -= 1;
      render();
    });
    els.pagination.appendChild(prev);

    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement('button');
      btn.textContent = String(i);
      btn.className =
        'px-3 py-1 rounded text-sm ' +
        (state.page === i ? 'bg-slate-800 text-white' : 'border');
      btn.addEventListener('click', () => {
        state.page = i;
        render();
      });
      els.pagination.appendChild(btn);
    }

    const next = document.createElement('button');
    next.textContent = '▶';
    next.className = 'px-3 py-1 rounded border text-sm disabled:opacity-40';
    next.disabled = state.page === totalPages;
    next.addEventListener('click', () => {
      state.page += 1;
      render();
    });
    els.pagination.appendChild(next);
  }

  function render() {
    els.list.innerHTML = '';

    if (!state.filtered.length) {
      els.list.innerHTML = `
        <div class="rounded-2xl border bg-slate-50 p-5 text-sm text-slate-600">
          No hay comunicados para mostrar con los filtros actuales.
        </div>
      `;
      renderPagination(0);
      return;
    }

    const totalPages = Math.ceil(state.filtered.length / state.perPage);
    const visibles = paginate(state.filtered, state.page, state.perPage);

    visibles.forEach((c) => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border border-slate-200 bg-white p-5 shadow-sm';

      const imageUrl = resolvePublicUrl(c.imagen_url);
      const imageHtml = imageUrl
        ? `
            <div class="mb-4 hidden overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 md:block">
              <img src="${escapeHtml(imageUrl)}" alt=""
                   class="h-44 w-full object-cover" loading="lazy" />
            </div>
          `
        : '';

      const hb = homeBadge(c);

      card.innerHTML = `
        <div class="flex flex-col md:flex-row md:justify-between gap-4">
          <div class="flex-1 min-w-0">
            ${imageHtml}
            <h3 class="font-semibold text-2xl text-slate-900">${escapeHtml(c.titulo || '—')}</h3>

            <div class="text-sm text-slate-500 mt-2">
              ${escapeHtml(c.tipo || '—')} · ${escapeHtml(c.fecha_publicacion || '—')}
            </div>

            <p class="text-base text-slate-700 mt-4 whitespace-pre-line break-words">
              ${escapeHtml(c.mensaje || '')}
            </p>

            <div class="flex gap-2 mt-4 flex-wrap">
              <span class="inline-block px-3 py-1 text-xs rounded-full ${badge(c.prioridad)}">
                ${escapeHtml(c.prioridad || '')}
              </span>
              <span class="inline-block px-3 py-1 text-xs rounded-full ${badgeEstado(c.estado)}">
                ${escapeHtml(c.estado || '')}
              </span>
              <span class="inline-block px-3 py-1 text-xs rounded-full ${hb.cls}">
                ${escapeHtml(hb.label)}
              </span>
            </div>
          </div>

          <div class="flex md:flex-col gap-2 md:items-end">
            <button data-edit="${c.id}"
              class="inline-flex items-center justify-center rounded-full bg-slate-100 px-4 py-2 text-sm text-slate-700 hover:bg-slate-200">
              Editar
            </button>
            <button data-archive="${c.id}"
              class="inline-flex items-center justify-center rounded-full bg-rose-100 px-4 py-2 text-sm text-rose-700 hover:bg-rose-200">
              Archivar
            </button>
          </div>
        </div>
      `;

      els.list.appendChild(card);
    });

    renderPagination(totalPages);
  }

  async function load() {
    try {
      const j = await api.comunicados.list();
      state.comunicados = j.comunicados || [];
      updateSummary();
      applyFilters();
      render();
    } catch (e) {
      showAlert(e.message || 'Error al cargar comunicados.', true);
    }
  }

  els.search?.addEventListener('input', (e) => {
    state.search = e.target.value || '';
    applyFilters();
    render();
  });

  els.filterEstado?.addEventListener('change', (e) => {
    state.estado = e.target.value || 'todos';
    applyFilters();
    render();
  });

  els.filterPrioridad?.addEventListener('change', (e) => {
    state.prioridad = e.target.value || 'todas';
    applyFilters();
    render();
  });

  els.btnResetFilters?.addEventListener('click', () => {
    state.search = '';
    state.estado = 'todos';
    state.prioridad = 'todas';
    state.page = 1;

    if (els.search) els.search.value = '';
    if (els.filterEstado) els.filterEstado.value = 'todos';
    if (els.filterPrioridad) els.filterPrioridad.value = 'todas';

    applyFilters();
    render();
  });

  els.btnAdd?.addEventListener('click', openCreateModal);
  els.btnClose?.addEventListener('click', closeModal);
  els.btnCancel?.addEventListener('click', closeModal);

  els.modal?.addEventListener('click', (e) => {
    if (e.target === els.modal) closeModal();
  });

  els.list.addEventListener('click', async (e) => {
    const edit = e.target.closest('[data-edit]');
    const arch = e.target.closest('[data-archive]');

    if (edit) {
      const c = state.comunicados.find((x) => x.id == edit.dataset.edit);
      if (!c) return;
      openEditModal(c);
      return;
    }

    if (arch) {
      const ok = await showConfirm({
        title: 'Archivar comunicado',
        message: 'El comunicado dejará de mostrarse en el listado principal. ¿Deseas continuar?',
        acceptText: 'Sí, archivar',
        cancelText: 'Cancelar',
      });

      if (!ok) return;

      try {
        const resp = await api.comunicados.archive(arch.dataset.archive);
        showAlert(resp.message || 'Comunicado archivado');
        await load();
      } catch (err) {
        showAlert(err.message || 'Error al archivar comunicado', true);
      }
    }
  });

  els.form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFormError();

    const submitBtn = els.form?.querySelector('button[type="submit"]');
    const originalSubmitHtml = submitBtn ? submitBtn.innerHTML : '';
    const originalCancelDisabled = els.btnCancel ? els.btnCancel.disabled : false;
    const originalCloseDisabled = els.btnClose ? els.btnClose.disabled : false;

    function setLoading(isLoading) {
      if (submitBtn) {
        submitBtn.disabled = !!isLoading;
        submitBtn.classList.toggle('opacity-70', !!isLoading);
        submitBtn.classList.toggle('cursor-not-allowed', !!isLoading);
        if (isLoading) {
          submitBtn.innerHTML = `
            <span class="inline-flex items-center gap-2">
              <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
              Guardando…
            </span>
          `;
        } else {
          submitBtn.innerHTML = originalSubmitHtml || 'Guardar';
        }
      }
      if (els.btnCancel) els.btnCancel.disabled = !!isLoading || originalCancelDisabled;
      if (els.btnClose) els.btnClose.disabled = !!isLoading || originalCloseDisabled;
    }

    try {
      const fd = new FormData(els.form);
      validateForm(fd);

      setLoading(true);
      const j = await api.comunicados.save(fd);
      showAlert(j.message || 'Guardado');
      showHomeVisibilityHintFromForm(fd);
      closeModal();
      await load();
    } catch (err) {
      showFormError(err.message || 'Error al guardar comunicado.');
    } finally {
      setLoading(false);
    }
  });

  ensureUiHelpers();
  load();
})();
