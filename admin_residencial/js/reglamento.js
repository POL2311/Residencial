(function () {
  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/reglamento.php';

  const els = {
    view: document.getElementById('reglamentoView'),
    alert: document.getElementById('reglamentoAlert'),
    form: document.getElementById('reglamentoForm'),
    titleInput: document.getElementById('reglamentoTitulo'),
    versionInput: document.getElementById('reglamentoVersion'),
    contentInput: document.getElementById('reglamentoContenido'),
    previewTitle: document.getElementById('reglamentoPreviewTitle'),
    previewVersion: document.getElementById('reglamentoPreviewVersion'),
    previewContent: document.getElementById('reglamentoPreviewContent'),
    formError: document.getElementById('reglamentoFormError'),
    btnPreview: document.getElementById('btnTogglePreview'),
    previewWrap: document.getElementById('reglamentoPreviewWrap'),
  };

  if (!els.view || !els.form) return;

  const state = {
    previewVisible: true,
  };

  function escapeHtml(value = '') {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function nl2brSafe(value = '') {
    return escapeHtml(value).replace(/\n/g, '<br>');
  }

  function showAlert(msg, isError = false) {
    if (!els.alert) return;
    els.alert.textContent = msg;
    els.alert.className =
      'rounded-2xl px-4 py-3 text-sm ' +
      (isError
        ? 'bg-rose-100 text-rose-700'
        : 'bg-emerald-100 text-emerald-700');
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
      throw new Error(text || 'Respuesta inválida del servidor');
    }

    if (!json || !json.ok) {
      throw new Error((json && json.error) || 'Error');
    }

    return json;
  }

  const api = {
    reglamento: {
      get: () => fetchJSON(API),
      save: (fd) =>
        fetchJSON(API, {
          method: 'POST',
          body: fd,
        }),
    },
  };

  function renderPreview() {
    const titulo = els.titleInput?.value?.trim() || 'Sin título';
    const version = els.versionInput?.value?.trim() || 'Sin versión';
    const contenido = els.contentInput?.value?.trim() || 'Aún no hay contenido del reglamento.';

    if (els.previewTitle) {
      els.previewTitle.textContent = titulo;
    }

    if (els.previewVersion) {
      els.previewVersion.textContent = `Versión: ${version}`;
    }

    if (els.previewContent) {
      els.previewContent.innerHTML = nl2brSafe(contenido);
    }
  }

  function validateForm(fd) {
    const titulo = String(fd.get('titulo') || '').trim();
    const version = String(fd.get('version_label') || '').trim();
    const contenido = String(fd.get('contenido') || '').trim();

    if (titulo.length < 3) {
      throw new Error('El título debe tener al menos 3 caracteres.');
    }

    if (titulo.length > 180) {
      throw new Error('El título no puede exceder 180 caracteres.');
    }

    if (version.length > 50) {
      throw new Error('La versión no puede exceder 50 caracteres.');
    }

    if (contenido.length < 20) {
      throw new Error('El contenido debe tener al menos 20 caracteres.');
    }

    if (contenido.length > 30000) {
      throw new Error('El contenido del reglamento es demasiado largo.');
    }
  }

  function togglePreview() {
    state.previewVisible = !state.previewVisible;

    if (!els.previewWrap || !els.btnPreview) return;

    if (state.previewVisible) {
      els.previewWrap.classList.remove('hidden');
      els.btnPreview.textContent = 'Ocultar vista previa';
    } else {
      els.previewWrap.classList.add('hidden');
      els.btnPreview.textContent = 'Mostrar vista previa';
    }
  }

  async function loadReglamento() {
    try {
      const json = await api.reglamento.get();
      const r = json.reglamento || {};

      if (els.titleInput) els.titleInput.value = r.titulo || '';
      if (els.contentInput) els.contentInput.value = r.contenido || '';
      if (els.versionInput) els.versionInput.value = r.version_label || '';

      renderPreview();
    } catch (err) {
      showAlert(err.message || 'No se pudo cargar el reglamento.', true);
    }
  }

  els.titleInput?.addEventListener('input', renderPreview);
  els.versionInput?.addEventListener('input', renderPreview);
  els.contentInput?.addEventListener('input', renderPreview);

  els.btnPreview?.addEventListener('click', togglePreview);

  els.form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearFormError();

    try {
      const fd = new FormData(els.form);
      validateForm(fd);

      const json = await api.reglamento.save(fd);
      renderPreview();
      showAlert(json.message || 'Reglamento guardado.');
    } catch (err) {
      showFormError(err.message || 'Error al guardar reglamento.');
    }
  });

  loadReglamento();
})();