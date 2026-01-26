(function () {
  if (window.__admin_pagos_init) return;
  window.__admin_pagos_init = true;

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }
  const API = basePath() + 'php/api/';

  const alertBox = document.getElementById('pagosAlert');
  const tableBody = document.getElementById('pagosTableBody');
  const btnAdd = document.getElementById('btnAddPago');
  const modal = document.getElementById('pagoModal');
  const form = document.getElementById('pagoForm');
  const modalTitle = document.getElementById('pagoModalTitle');
  const btnClose = document.getElementById('btnClosePagoModal');

  let pagosData = [];
  let residentsData = [];

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
    modalTitle.textContent = 'Registrar pago';
    form.querySelector('[name="id"]').value = '';
    // Llenar lista de residentes
    const userSelect = form.querySelector('select[name="user_id"]');
    userSelect.innerHTML = '';
    // Filtrar solo titulares (principal de cada unidad) de la lista de residentes
    const titulares = residentsData.filter(r => r.es_titular && r.activo_servicio);
    titulares.forEach(res => {
      const opt = document.createElement('option');
      opt.value = res.user_id;
      opt.textContent = `${res.name} - ${res.clave || res.unidad_clave || ''}`;
      userSelect.appendChild(opt);
    });
    modal.classList.remove('hidden');
  });

  btnClose.addEventListener('click', () => {
    modal.classList.add('hidden');
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    try {
      const res = await fetch(API + 'pagos_residentes.php', { method: 'POST', body: formData, credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        showAlert(json.message || 'Pago guardado.');
        modal.classList.add('hidden');
        await loadPagos();
      } else {
        showAlert(json.error || 'Error al guardar pago.', true);
      }
    } catch (err) {
      showAlert('Error de red al guardar pago.', true);
    }
  });

  tableBody.addEventListener('click', async (e) => {
    const target = e.target;
    if (target.matches('.btn-edit-pago')) {
      // Editar pago
      const pid = target.getAttribute('data-id');
      const pago = pagosData.find(p => p.id == pid);
      if (!pago) return;
      form.reset();
      modalTitle.textContent = 'Editar pago';
      form.querySelector('[name="id"]').value = pago.id;
      // Rellenar campos
      form.querySelector('[name="user_id"]').innerHTML = `<option value="${pago.user_id}">${pago.residente_nombre}</option>`;
      form.querySelector('[name="monto"]').value = pago.monto;
      form.querySelector('[name="fecha_pago"]').value = pago.fecha_pago;
      form.querySelector('[name="metodo"]').value = pago.metodo;
      form.querySelector('[name="concepto"]').value = pago.concepto || '';
      modal.classList.remove('hidden');
    }
    if (target.matches('.btn-delete-pago')) {
      const pid = target.getAttribute('data-id');
      if (!confirm('¿Eliminar este pago?')) return;
      try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', pid);
        const res = await fetch(API + 'pagos_residentes.php', { method: 'POST', body: formData, credentials: 'same-origin' });
        const json = await res.json();
        if (json.ok) {
          showAlert(json.message || 'Pago eliminado.');
          await loadPagos();
        } else {
          showAlert(json.error || 'Error al eliminar pago.', true);
        }
      } catch (err) {
        showAlert('Error de red al eliminar pago.', true);
      }
    }
  });

  function renderPagos() {
    tableBody.innerHTML = '';
    pagosData.forEach(p => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td class="px-3 py-2">${p.residente_nombre}</td>
        <td class="px-3 py-2">${p.unidad_clave || ''}</td>
        <td class="px-3 py-2">$${p.monto.toFixed(2)}</td>
        <td class="px-3 py-2">${p.fecha_pago}</td>
        <td class="px-3 py-2">${p.metodo}</td>
        <td class="px-3 py-2">${p.concepto || ''}</td>
        <td class="px-3 py-2 text-right">
          <button class="btn-edit-pago text-sm text-blue-600 hover:underline mr-3" data-id="${p.id}">Editar</button>
          <button class="btn-delete-pago text-sm text-red-600 hover:underline" data-id="${p.id}">Eliminar</button>
        </td>`;
      tableBody.appendChild(tr);
    });
  }

  async function loadPagos() {
    try {
      const res = await fetch(API + 'pagos_residentes.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        pagosData = json.pagos || [];
        renderPagos();
      } else {
        showAlert(json.error || 'Error al cargar pagos.', true);
      }
    } catch (err) {
      showAlert('Error de red al cargar pagos.', true);
    }
  }

  // Cargar lista de residentes (para elegir en formulario)
  async function loadResidentes() {
    try {
      const res = await fetch(API + 'residentes.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        residentsData = json.residentes || [];
      }
    } catch (err) {
      console.error('Error al cargar lista de residentes.');
    }
  }

  (async function init() {
    await loadResidentes();
    loadPagos();
  })();
})();
