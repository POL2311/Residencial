(function () {
  if (window.__admin_reglamento_init) return;
  window.__admin_reglamento_init = true;

  function basePath() {
    const p = location.pathname;
    const i = p.indexOf('/admin_residencial/');
    return i === -1 ? '/admin_residencial/' : p.slice(0, i) + '/admin_residencial/';
  }
  const API = basePath() + 'php/api/';

  const alertBox = document.getElementById('reglamentoAlert');
  const form = document.getElementById('reglamentoForm');

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

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    try {
      const res = await fetch(API + 'reglamento.php', { method: 'POST', body: formData, credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok) {
        showAlert(json.message || 'Reglamento guardado.');
      } else {
        showAlert(json.error || 'Error al guardar reglamento.', true);
      }
    } catch (err) {
      showAlert('Error de red al guardar reglamento.', true);
    }
  });

  async function loadReglamento() {
    try {
      const res = await fetch(API + 'reglamento.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok && json.reglamento) {
        form.titulo.value = json.reglamento.titulo || '';
        form.contenido.value = json.reglamento.contenido || '';
        form.version_label.value = json.reglamento.version_label || '';
      }
    } catch (err) {
      console.error('No se pudo cargar el reglamento.');
    }
  }

  loadReglamento();
})();
