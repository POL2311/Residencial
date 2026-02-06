(function () {
  if (window.__admin_comunicados_init) return;
  window.__admin_comunicados_init = true;

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }

  const API = basePath() + 'php/api/comunicados.php';

  const els = {
    alert: document.getElementById('comunicadosAlert'),
    list: document.getElementById('comunicadosList'),
    btnAdd: document.getElementById('btnAddComunicado'),
    modal: document.getElementById('comunicadoModal'),
    form: document.getElementById('comunicadoForm'),
    btnClose: document.getElementById('btnCloseComModal'),
  };

  let data = [];

  function showAlert(msg, error = false) {
    els.alert.textContent = msg;
    els.alert.className =
      'rounded-xl px-4 py-3 text-sm ' +
      (error ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700');
    els.alert.classList.remove('hidden');
    setTimeout(() => els.alert.classList.add('hidden'), 4000);
  }

  function badge(p) {
    if (p === 'alta') return 'bg-rose-100 text-rose-700';
    if (p === 'media') return 'bg-amber-100 text-amber-700';
    return 'bg-slate-100 text-slate-700';
  }

  function render() {
    els.list.innerHTML = '';

    data.forEach(c => {
      const card = document.createElement('div');
      card.className = 'rounded-2xl border bg-white p-4';

      card.innerHTML = `
        <div class="flex justify-between gap-4">
          <div class="flex-1">
            <h3 class="font-semibold text-lg">${c.titulo}</h3>

            <div class="text-xs text-slate-500 mt-1">
              ${c.tipo} · ${c.fecha_publicacion}
            </div>

            <p class="text-sm mt-2 line-clamp-3">${c.mensaje}</p>

            <span class="inline-block mt-2 px-3 py-1 text-xs rounded-full ${badge(c.prioridad)}">
              ${c.prioridad}
            </span>
          </div>

          <div class="flex flex-col gap-2 items-end">
            <button data-edit="${c.id}"
              class="text-sm px-3 py-1 border rounded-lg hover:bg-slate-50">
              Editar
            </button>
            <button data-archive="${c.id}"
              class="text-sm px-3 py-1 bg-rose-500 text-white rounded-lg hover:bg-rose-600">
              Archivar
            </button>
          </div>
        </div>
      `;

      els.list.appendChild(card);
    });
  }

  async function load() {
    const r = await fetch(API, { credentials: 'same-origin' });
    const j = await r.json();
    if (j.ok) {
      data = j.comunicados || [];
      render();
    }
  }

  els.btnAdd.onclick = () => {
    els.form.reset();
    els.form.id.value = '';
    els.modal.classList.remove('hidden');
  };

  els.btnClose.onclick = () => els.modal.classList.add('hidden');

  els.list.onclick = async e => {
    const edit = e.target.closest('[data-edit]');
    const arch = e.target.closest('[data-archive]');

    if (edit) {
      const c = data.find(x => x.id == edit.dataset.edit);
      if (!c) return;
      Object.keys(c).forEach(k => {
        if (els.form[k]) els.form[k].value = c[k] ?? '';
      });
      els.modal.classList.remove('hidden');
    }

    if (arch) {
      if (!confirm('¿Archivar comunicado?')) return;
      const fd = new FormData();
      fd.append('action', 'archive');
      fd.append('id', arch.dataset.archive);
      await fetch(API, { method: 'POST', body: fd });
      showAlert('Comunicado archivado');
      load();
    }
  };

  els.form.onsubmit = async e => {
    e.preventDefault();
    const fd = new FormData(els.form);
    const r = await fetch(API, { method: 'POST', body: fd });
    const j = await r.json();
    if (j.ok) {
      showAlert(j.message || 'Guardado');
      els.modal.classList.add('hidden');
      load();
    } else {
      showAlert(j.error, true);
    }
  };

  load();
})();
