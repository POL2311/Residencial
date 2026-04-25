(function () {
    function baseResidentPath() {
        const p = window.location.pathname;
        const idx = p.indexOf('/residente/');
        if (idx === -1) return '/residente/';
        return p.slice(0, idx) + '/residente/';
    }

    const root = document.getElementById('residentReglamentoView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = baseResidentPath() + 'php/api/reglamento.php';
    const els = {
        alert: document.getElementById('reglamentoAlert'),
        title: document.getElementById('residentReglamentoTitle'),
        meta: document.getElementById('residentReglamentoMeta'),
        content: document.getElementById('residentReglamentoContent'),
    };

    function showError(msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = 'rounded-2xl px-4 py-3 text-sm bg-rose-50 text-rose-800 border border-rose-200';
        els.alert.textContent = msg;
    }

    function showEmpty(msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = 'rounded-2xl px-4 py-3 text-sm bg-slate-50 text-slate-700 border border-slate-200';
        els.alert.textContent = msg;
    }

    async function load() {
        const res = await fetch(API, { credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        const json = await res.json().catch(() => null);
        if (!json || !json.ok) throw new Error(json?.error || 'No se pudo cargar el reglamento.');
        const item = json.data?.item || null;

        if (!item) {
            if (els.title) els.title.textContent = 'Reglamento';
            if (els.meta) els.meta.textContent = 'Sin version disponible';
            if (els.content) {
                els.content.textContent = 'Aun no hay reglamento disponible para tu residencial.';
            }
            showEmpty('Aun no hay reglamento disponible para tu residencial.');
            return;
        }

        if (els.title) els.title.textContent = item.titulo || 'Reglamento';
        if (els.meta) els.meta.textContent = `Version ${item.version_label || '—'} · Actualizado ${item.updated_at || item.created_at || '—'}`;
        if (els.content) els.content.textContent = item.contenido || 'Sin contenido.';
    }

    load().catch((err) => showError(err.message || 'No se pudo cargar el reglamento.'));
})();
