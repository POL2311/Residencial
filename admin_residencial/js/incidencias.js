(function () {
  if (window.__admin_incidencias_init) return;
  window.__admin_incidencias_init = true;

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }
  const API = basePath() + 'php/api/';

  const alertBox = document.getElementById('incidenciasAlert');
  const tableBody = document.getElementById('incidenciasTableBody');
  const filterSelect = document.getElementById('filterEstado');
  const btnAdd = document.getElementById('btnAddIncidencia');
  const modalAdd = document.getElementById('incidenciaModal');
  const formAdd = document.getElementById('incidenciaForm');
  const modalEdit = document.getElementById('incidenciaEditModal');
  const formEdit = document.getElementById('incidenciaEditForm');
  const btnCloseAdd = document.getElementById('btnCloseIncidenciaModal');
  const btnCloseEdit = document.getElementById('btnCloseEditIncModal');

  let incidentsData = [];
  let unitsData = [];
  let guardsData = [];

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

  filterSelect.addEventListener('change', () => {
    renderIncidents();
  });

  btnAdd.addEventListener('click', () => {
    formAdd.reset();
    const unitSelect = formAdd.querySelector('select[name="unidad_id"]');
    unitSelect.innerHTML = '';
    unitsData.forEach(u => {
      const optText = u.calle ? `${u.calle} ${u.numero_exterior || ''}`.trim() + (u.numero_interior ? ` Int. ${u.numero_interior}` : '') :
                      u.torre ? `Torre ${u.torre}${u.nivel ? ' Nivel ' + u.nivel : ''} - ${u.clave}` :
                      u.clave;
      const option = document.createElement('option');
      option.value = u.id;
      option.textContent = optText;
      unitSelect.appendChild(option);
    });
    modalAdd.classList.remove('hidden');
  });

  btnCloseAdd.addEventListener('click', () => {
    modalAdd.classList.add('hidden');
  });
  btnCloseEdit.addEventListener('click', () => {
    modalEdit.classList.add('hidden');
  });

  formAdd.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(formAdd);
    try {
      const res = await fetch(API + 'incidencias.php', { method: 'POST', body: formData, credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        showAlert(json.message || 'Incidencia registrada.');
        modalAdd.classList.add('hidden');
        await loadIncidents();
      } else {
        showAlert(json.error || 'Error al registrar incidencia.', true);
      }
    } catch (err) {
      showAlert('Error de red al registrar incidencia.', true);
    }
  });

  formEdit.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(formEdit);
    try {
      const res = await fetch(API + 'incidencias.php', { method: 'POST', body: formData, credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        showAlert(json.message || 'Incidencia actualizada.');
        modalEdit.classList.add('hidden');
        await loadIncidents();
      } else {
        showAlert(json.error || 'Error al actualizar incidencia.', true);
      }
    } catch (err) {
      showAlert('Error de red al actualizar incidencia.', true);
    }
  });

  tableBody.addEventListener('click', (e) => {
    if (e.target.matches('.btn-edit-inc')) {
      const incId = e.target.getAttribute('data-id');
      const inc = incidentsData.find(i => i.id == incId);
      if (!inc) return;
      formEdit.reset();
      formEdit.querySelector('[name="id"]').value = inc.id;
      // Llenar lista de guardias en select
      const guardSelect = formEdit.querySelector('[name="guardia_id"]');
      guardSelect.innerHTML = '<option value="">-- Sin asignar --</option>' + 
        guardsData.map(g => `<option value="${g.id}">${g.name}</option>`).join('');
      guardSelect.value = inc.guardia_id || '';
      formEdit.querySelector('[name="estado"]').value = inc.estado;
      formEdit.querySelector('[name="prioridad"]').value = inc.prioridad;
      modalEdit.classList.remove('hidden');
    }
  });

  function renderIncidents() {
    const filter = filterSelect.value;
    tableBody.innerHTML = '';
    incidentsData.forEach(inc => {
      if (filter && filter !== 'todas' && inc.estado !== filter) return;
      const date = new Date(inc.created_at);
      const dateStr = date.toLocaleString();
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="px-3 py-2">${inc.titulo}</td>
        <td class="px-3 py-2 text-xs text-slate-600">${inc.tipo}</td>
        <td class="px-3 py-2">${inc.unidad_clave}</td>
        <td class="px-3 py-2">${inc.residente_nombre}</td>
        <td class="px-3 py-2">${inc.guardia_nombre || '-'}</td>
        <td class="px-3 py-2">${inc.prioridad}</td>
        <td class="px-3 py-2 ${inc.estado === 'cerrada' ? 'text-green-600' : inc.estado === 'en_proceso' ? 'text-orange-600' : 'text-red-600'}">${inc.estado}</td>
        <td class="px-3 py-2 text-xs text-slate-600">${dateStr}</td>
        <td class="px-3 py-2 text-right">
          <button class="btn-edit-inc text-sm text-blue-600 hover:underline" data-id="${inc.id}">Editar</button>
        </td>`;
      tableBody.appendChild(tr);
    });
  }

  async function loadIncidents() {
    try {
      const res = await fetch(API + 'incidencias.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        incidentsData = json.incidencias || [];
        renderIncidents();
      } else {
        showAlert(json.error || 'Error al cargar incidencias.', true);
      }
    } catch (err) {
      showAlert('Error de red al cargar incidencias.', true);
    }
  }

  // Cargar listas de unidades y guardias antes de incidencias
  (async function init() {
    try {
      const [resUnits, resGuards] = await Promise.all([
        fetch(API + 'unidades.php', { credentials: 'same-origin' }),
        fetch(API + 'guardias.php', { credentials: 'same-origin' })
      ]);
      const jsonUnits = await resUnits.json();
      const jsonGuards = await resGuards.json();
      unitsData = jsonUnits.ok ? jsonUnits.unidades || [] : [];
      guardsData = jsonGuards.ok ? jsonGuards.guardias || [] : [];
    } catch (e) {
      console.error('Error cargando listas de unidades o guardias.');
    }
    loadIncidents();
  })();
})();
