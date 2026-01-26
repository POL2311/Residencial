(function () {
  if (window.__admin_comunicados_init) return;
  window.__admin_comunicados_init = true;

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }
  const API = basePath() + 'php/api/';

  const alertBox = document.getElementById('comunicadosAlert');
  const listContainer = document.getElementById('comunicadosList');
  const btnAdd = document.getElementById('btnAddComunicado');
  const modal = document.getElementById('comunicadoModal');
  const form = document.getElementById('comunicadoForm');
  const btnClose = document.getElementById('btnCloseComModal');

  let comunicadosData = [];

  function showAlert(msg, isError = false) {
    if (!alertBox) return;
    alertBox.textContent = msg;
    alertBox.classList.remove('hidden');
    alertBox.classList.toggle('bg-red-100', isError);
    alertBox.classList.toggle('text-red-800', isError);
    alertBox.classList.toggle('bg-green-100', !isError);
    alertBox.classList.toggle('text-green-800', !isError);
    setTimeout(() => alertBox.classList.add('hidden'), 5000);
  }

  btnAdd.addEventListener('click', () => {
    form.reset();
    form.id.value = '';
    modal.classList.remove('hidden');
  });

  btnClose.addEventListener('click', () => {
    modal.classList.add('hidden');
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    try {
      const res = await fetch(API + 'comunicados.php', { method: 'POST', body: formData, credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        showAlert(json.message || 'Comunicado guardado.');
        modal.classList.add('hidden');
        await loadComunicados();
      } else {
        showAlert(json.error || 'Error al guardar comunicado.', true);
      }
    } catch (err) {
      showAlert('Error de red al guardar comunicado.', true);
    }
  });

  listContainer.addEventListener('click', async (e) => {
    const target = e.target;
    if (target.matches('.btn-archivar')) {
      const id = target.getAttribute('data-id');
      if (!confirm('¿Archivar este comunicado?')) return;
      try {
        const formData = new FormData();
        formData.append('action', 'archive');
        formData.append('id', id);
        const res = await fetch(API + 'comunicados.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const json = await res.json();
        if (json.ok) {
          showAlert(json.message || 'Comunicado archivado.');
          await loadComunicados();
        } else {
          showAlert(json.error || 'Error al archivar comunicado.', true);
        }
      } catch (err) {
        showAlert('Error de red al archivar comunicado.', true);
      }
    }
    if (target.matches('.btn-editar')) {
      const id = target.getAttribute('data-id');
      const com = comunicadosData.find(c => c.id == id);
      if (!com) return;
      form.reset();
      form.id.value = com.id;
      form.titulo.value = com.titulo;
      form.mensaje.value = com.mensaje;
      form.tipo.value = com.tipo;
      form.prioridad.value = com.prioridad;
      form.fecha_publicacion.value = com.fecha_publicacion;
      form.fecha_expiracion.value = com.fecha_expiracion || '';
      form.estado.value = com.estado;
      modal.classList.remove('hidden');
    }
  });

  function renderComunicados() {
    listContainer.innerHTML = '';
    comunicadosData.forEach(c => {
      const item = document.createElement('div');
      item.className = 'rounded-2xl bg-white shadow px-4 py-3';
      item.innerHTML = `
        <div class="flex justify-between items-center">
          <div>
            <h3 class="text-base font-semibold">${c.titulo}</h3>
            <div class="text-xs text-slate-500">Categoría: ${c.tipo}, Prioridad: ${c.prioridad}, Estado: ${c.estado}, Publicado: ${c.fecha_publicacion}</div>
          </div>
          <div class="space-x-2">
            <button class="btn-editar text-sm text-blue-600 hover:underline" data-id="${c.id}">Editar</button>
            <button class="btn-archivar text-sm text-red-600 hover:underline" data-id="${c.id}">Archivar</button>
          </div>
        </div>
        <p class="mt-2 text-sm whitespace-pre-line">${c.mensaje}</p>
      `;
      listContainer.appendChild(item);
    });
  }

  async function loadComunicados() {
    try {
      const res = await fetch(API + 'comunicados.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        comunicadosData = json.comunicados || [];
        renderComunicados();
      } else {
        showAlert(json.error || 'Error al cargar comunicados.', true);
      }
    } catch (err) {
      showAlert('Error de red al cargar comunicados.', true);
    }
  }

  loadComunicados();
})();
