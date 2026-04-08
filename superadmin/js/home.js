(function () {
    const root = document.getElementById('superadminHomeView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/');
    const els = {
        alert: document.getElementById('superadminHomeAlert'),
        systemName: document.getElementById('superadminHomeSystemName'),
        metricActivos: document.getElementById('homeMetricActivos'),
        metricTotal: document.getElementById('homeMetricTotal'),
        metricUsuarios: document.getElementById('homeMetricUsuarios'),
        metricAccesos: document.getElementById('homeMetricAccesos'),
        recent: document.getElementById('homeRecentResidenciales'),
        activity: document.getElementById('homeActivity'),
        tips: document.getElementById('homeTips'),
        actions: root.querySelectorAll('[data-home-nav]'),
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function showAlert(type, msg) {
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm ${type === 'ok' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`;
        els.alert.textContent = msg;
    }

    async function apiGet() {
        const res = await fetch(`${API}home.php`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.ok) {
            throw new Error(json?.error || 'No se pudo cargar el home.');
        }
        return json.data || {};
    }

    function renderRecent(items) {
        els.recent.innerHTML = items.length ? items.map((item) => `
            <article class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                  <div class="text-sm font-semibold text-slate-800 truncate">${escapeHtml(item.nombre)}</div>
                  <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.codigo || '—')} · ${escapeHtml(item.ciudad || '')}${item.estado ? ', ' + escapeHtml(item.estado) : ''}</div>
                </div>
                <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${item.estatus_plan === 'activo' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : item.estatus_plan === 'prueba' ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-slate-100 text-slate-600 border border-slate-200'}">${escapeHtml(item.estatus_plan || '—')}</span>
              </div>
              <div class="mt-3 text-xs text-slate-500">Plan: <span class="font-medium text-slate-700">${escapeHtml(item.plan_nombre || 'Sin plan')}</span></div>
            </article>
        `).join('') : `
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 md:col-span-2">
              Aún no hay residenciales para mostrar.
            </div>
        `;
    }

    function renderActivity(items) {
        els.activity.innerHTML = items.length ? items.map((item) => `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.title || 'Actividad')}</div>
              <p class="mt-2 text-sm text-slate-600">${escapeHtml(item.text || '')}</p>
              ${item.date ? `<div class="mt-2 text-xs text-slate-400">${escapeHtml(item.date)}</div>` : ''}
            </div>
        `).join('') : `
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
              No hay actividad reciente disponible.
            </div>
        `;
    }

    function renderTips(items) {
        els.tips.innerHTML = items.map((item) => `
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
              <div class="text-sm font-semibold text-slate-800">${escapeHtml(item.title)}</div>
              <p class="mt-2 text-sm text-slate-600">${escapeHtml(item.text)}</p>
            </div>
        `).join('');
    }

    async function load() {
        const data = await apiGet();
        const summary = data.summary || {};

        if (els.systemName) {
            els.systemName.textContent = `${data.system_name || 'Sistema Residencial'} · Control operativo centralizado`;
        }

        els.metricActivos.textContent = String(summary.residenciales_activos ?? 0);
        els.metricTotal.textContent = String(summary.residenciales_total ?? 0);
        els.metricUsuarios.textContent = String(summary.usuarios_total ?? 0);
        els.metricAccesos.textContent = String(summary.accesos_hoy ?? 0);

        renderRecent(data.recent_residenciales || []);
        renderActivity(data.activity || []);
        renderTips(data.tips || []);
    }

    els.actions.forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.homeNav || '';
            if (target && window.SuperadminDashboard?.loadView) {
                window.SuperadminDashboard.loadView(target);
            }
        });
    });

    load().catch((err) => showAlert('error', err.message || 'No se pudo cargar el home.'));
})();
