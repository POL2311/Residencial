(function () {
    const root = document.getElementById('superadminReportesView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'reportes.php';
    const PER_PAGE = 3;
    const els = {
        alert: document.getElementById('reportesAlert'),
        period: document.getElementById('reportPeriod'),
        btnExportCsv: document.getElementById('btnExportReportCsv'),
        btnPrint: document.getElementById('btnPrintReport'),
        btnRefresh: document.getElementById('btnRefreshReportes'),
        metricActivos: document.getElementById('reportMetricActivos'),
        metricActivosMeta: document.getElementById('reportMetricActivosMeta'),
        metricResidenciales: document.getElementById('reportMetricResidenciales'),
        metricResidencialesMeta: document.getElementById('reportMetricResidencialesMeta'),
        metricUsuariosActivos: document.getElementById('reportMetricUsuariosActivos'),
        metricUsuariosActivosMeta: document.getElementById('reportMetricUsuariosActivosMeta'),
        metricUsuarios: document.getElementById('reportMetricUsuarios'),
        metricUsuariosMeta: document.getElementById('reportMetricUsuariosMeta'),
        summary: document.getElementById('reportSummary'),
        summaryPeriodLabel: document.getElementById('reportSummaryPeriodLabel'),
        seriesChart: document.getElementById('reportSeriesChart'),
        rolesChart: document.getElementById('reportRolesChart'),
        roles: document.getElementById('reportRoles'),
        rangeMeta: document.getElementById('reportRangeMeta'),
        residenciales: document.getElementById('reportResidenciales'),
        residencialesPagination: document.getElementById('reportResidencialesPagination'),
        users: document.getElementById('reportUsers'),
        usersPagination: document.getElementById('reportUsersPagination'),
    };

    const state = {
        data: null,
        residencialesPage: 1,
        usersPage: 1,
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

    function getPageInfo(items, page) {
        const total = items.length;
        const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
        const safePage = Math.min(Math.max(page, 1), totalPages);
        const start = (safePage - 1) * PER_PAGE;
        const visible = items.slice(start, start + PER_PAGE);
        return {
            total,
            totalPages,
            page: safePage,
            start,
            end: Math.min(start + visible.length, total),
            visible,
        };
    }

    function renderPager(container, items, page, key) {
        if (!container) return;
        const { total, totalPages, start, end } = getPageInfo(items, page);
        if (total <= PER_PAGE) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
              <div>Mostrando ${start + 1}-${end} de ${total}</div>
              <div class="flex items-center gap-2">
                <button type="button" data-page-group="${key}" data-page-action="prev" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page <= 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Anterior</button>
                <div class="rounded-lg bg-white px-3 py-1.5 text-slate-700">Página ${page} de ${totalPages}</div>
                <button type="button" data-page-group="${key}" data-page-action="next" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page >= totalPages ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Siguiente</button>
              </div>
            </div>
        `;
    }

    function formatPeriodLabel() {
        return els.period?.selectedOptions?.[0]?.textContent || 'Periodo actual';
    }

    function renderSummary(summaryItems) {
        els.summary.innerHTML = summaryItems.length ? summaryItems.map((item) => `
            <article class="rounded-[1.75rem] border border-slate-200 bg-slate-50 p-4">
              <div class="text-xs uppercase tracking-wide text-slate-400">${escapeHtml(item.title)}</div>
              <div class="mt-2 text-2xl font-bold text-slate-900">${escapeHtml(item.value)}</div>
              <div class="mt-2 text-sm text-slate-600">${escapeHtml(item.detail || '')}</div>
              <div class="mt-2 text-xs text-slate-500">${escapeHtml(item.trend || '')}</div>
            </article>
        `).join('') : `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay resumen disponible para el periodo.</div>`;
    }

    function renderRangeMeta(items) {
        els.rangeMeta.innerHTML = items.length ? items.map((item) => `
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
              <span class="text-slate-600">${escapeHtml(item.label)}</span>
              <span class="font-semibold text-slate-900">${escapeHtml(item.value)}</span>
            </div>
        `).join('') : `<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Sin datos del rango.</div>`;
    }

    function renderRoles(roles) {
        els.roles.innerHTML = roles.length ? roles.map((item) => `
            <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 text-sm">
              <span class="text-slate-700">${escapeHtml(item.rol)}</span>
              <span class="font-semibold text-slate-900">${Number(item.total || 0)}</span>
            </div>
        `).join('') : `<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Sin datos por rol.</div>`;
    }

    function renderRolesChart(roles) {
        const total = roles.reduce((sum, item) => sum + Number(item.total || 0), 0);
        if (!roles.length || total <= 0) {
            els.rolesChart.innerHTML = `<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Sin actividad suficiente para graficar roles.</div>`;
            return;
        }

        const colors = ['#4E7287', '#22C55E', '#F59E0B', '#6366F1', '#EF4444', '#14B8A6'];
        els.rolesChart.innerHTML = `
            <div class="space-y-3">
              ${roles.map((item, index) => {
                  const totalRole = Number(item.total || 0);
                  const pct = Math.max(4, Math.round((totalRole / total) * 100));
                  return `
                    <div>
                      <div class="mb-1 flex items-center justify-between text-xs text-slate-500">
                        <span>${escapeHtml(item.rol)}</span>
                        <span>${totalRole} (${Math.round((totalRole / total) * 100)}%)</span>
                      </div>
                      <div class="h-3 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full" style="width:${pct}%;background:${colors[index % colors.length]};"></div>
                      </div>
                    </div>
                  `;
              }).join('')}
            </div>
        `;
    }

    function renderSeriesChart(series) {
        const labels = series.labels || [];
        const residenciales = series.residenciales || [];
        const usuarios = series.usuarios || [];
        const max = Math.max(1, ...residenciales, ...usuarios);

        if (!labels.length) {
            els.seriesChart.innerHTML = `<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Sin datos suficientes para construir la gráfica.</div>`;
            return;
        }

        els.seriesChart.innerHTML = `
            <div class="overflow-x-auto">
              <div class="flex min-w-max items-end gap-3 pb-2">
                ${labels.map((label, index) => {
                    const resHeight = Math.max(6, Math.round((Number(residenciales[index] || 0) / max) * 150));
                    const userHeight = Math.max(6, Math.round((Number(usuarios[index] || 0) / max) * 150));
                    return `
                      <div class="flex min-w-[52px] flex-col items-center gap-2">
                        <div class="flex h-40 items-end gap-1">
                          <div class="w-4 rounded-t-lg bg-[#4E7287]" style="height:${resHeight}px" title="Residenciales: ${Number(residenciales[index] || 0)}"></div>
                          <div class="w-4 rounded-t-lg bg-[#22C55E]" style="height:${userHeight}px" title="Usuarios: ${Number(usuarios[index] || 0)}"></div>
                        </div>
                        <div class="text-center text-[11px] text-slate-500">${escapeHtml(label)}</div>
                      </div>
                    `;
                }).join('')}
              </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-500">
              <div class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-[#4E7287]"></span>Residenciales</div>
              <div class="inline-flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-[#22C55E]"></span>Usuarios</div>
            </div>
        `;
    }

    function renderResidencialesTable(items = state.data?.latest_residenciales || []) {
        const { visible, page } = getPageInfo(items, state.residencialesPage);
        state.residencialesPage = page;

        els.residenciales.innerHTML = items.length ? `
            <div class="space-y-3 md:hidden">
              ${visible.map((row) => `
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                  <div class="font-semibold text-slate-800">${escapeHtml(row.nombre)}</div>
                  <div class="mt-1 text-xs text-slate-500">${escapeHtml(row.codigo || 'Sin código')}</div>
                  <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Ubicación</dt><dd class="mt-1 text-slate-700">${escapeHtml((row.ciudad || '') + ((row.estado ? ', ' + row.estado : '')))}</dd><dd class="text-xs text-slate-500">${escapeHtml(row.pais || '')}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Estatus</dt><dd class="mt-1 text-slate-700">${escapeHtml(row.estatus_plan || '—')}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Creado</dt><dd class="mt-1 text-slate-700">${escapeHtml(row.created_at || '—')}</dd></div>
                  </dl>
                </article>
              `).join('')}
            </div>
            <div class="hidden md:block">
              <div class="grid grid-cols-[minmax(220px,1.3fr)_minmax(180px,1fr)_150px_140px] items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                <div>Residencial</div>
                <div>Ubicación</div>
                <div>Estatus</div>
                <div>Creado</div>
              </div>
              <div class="mt-3 space-y-3">
                ${visible.map((row) => `
                  <article class="grid grid-cols-[minmax(220px,1.3fr)_minmax(180px,1fr)_150px_140px] items-center gap-4 rounded-[1.75rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="min-w-0">
                      <div class="font-semibold text-slate-800">${escapeHtml(row.nombre)}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(row.codigo || 'Sin código')}</div>
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm text-slate-700">${escapeHtml((row.ciudad || '') + ((row.estado ? ', ' + row.estado : '')))}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(row.pais || '')}</div>
                    </div>
                    <div class="text-sm text-slate-700">${escapeHtml(row.estatus_plan || '—')}</div>
                    <div class="text-sm text-slate-500">${escapeHtml(row.created_at || '')}</div>
                  </article>
                `).join('')}
              </div>
            </div>
        ` : `<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Sin residenciales recientes.</div>`;

        renderPager(els.residencialesPagination, items, state.residencialesPage, 'residenciales');
    }

    function renderUsersTable(items = state.data?.latest_users || []) {
        const { visible, page } = getPageInfo(items, state.usersPage);
        state.usersPage = page;

        els.users.innerHTML = items.length ? `
            <div class="space-y-3 md:hidden">
              ${visible.map((row) => `
                <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <div class="font-semibold text-slate-800">${escapeHtml(row.name)}</div>
                      <div class="mt-1 text-xs text-slate-500">${escapeHtml(row.email)}</div>
                    </div>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${Number(row.is_active || 0) === 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200'}">${Number(row.is_active || 0) === 1 ? 'Activo' : 'Inactivo'}</span>
                  </div>
                  <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Rol</dt><dd class="mt-1 text-slate-700">${escapeHtml(row.rol)}</dd></div>
                    <div><dt class="text-xs uppercase tracking-wide text-slate-400">Creado</dt><dd class="mt-1 text-slate-700">${escapeHtml(row.created_at || '—')}</dd></div>
                  </dl>
                </article>
              `).join('')}
            </div>
            <div class="hidden md:block">
              <div class="grid grid-cols-[minmax(200px,1fr)_minmax(220px,1.15fr)_160px_130px_140px] items-center gap-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                <div>Nombre</div>
                <div>Email</div>
                <div>Rol</div>
                <div>Estado</div>
                <div>Creado</div>
              </div>
              <div class="mt-3 space-y-3">
                ${visible.map((row) => `
                  <article class="grid grid-cols-[minmax(200px,1fr)_minmax(220px,1.15fr)_160px_130px_140px] items-center gap-4 rounded-[1.75rem] border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="font-semibold text-slate-800">${escapeHtml(row.name)}</div>
                    <div class="text-sm text-slate-700 min-w-0">${escapeHtml(row.email)}</div>
                    <div class="text-sm text-slate-700">${escapeHtml(row.rol)}</div>
                    <div><span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${Number(row.is_active || 0) === 1 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-600 border border-slate-200'}">${Number(row.is_active || 0) === 1 ? 'Activo' : 'Inactivo'}</span></div>
                    <div class="text-sm text-slate-500">${escapeHtml(row.created_at || '')}</div>
                  </article>
                `).join('')}
              </div>
            </div>
        ` : `<div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">Sin usuarios recientes.</div>`;

        renderPager(els.usersPagination, items, state.usersPage, 'users');
    }

    function downloadCsv(filename, rows) {
        const csv = rows.map((row) => row.map((cell) => {
            const text = String(cell ?? '');
            return `"${text.replaceAll('"', '""')}"`;
        }).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        URL.revokeObjectURL(url);
    }

    function exportCsv() {
        if (!state.data) return;
        const periodLabel = state.data.period?.label || formatPeriodLabel();
        const resPage = getPageInfo(state.data.latest_residenciales || [], state.residencialesPage).visible;
        const userPage = getPageInfo(state.data.latest_users || [], state.usersPage).visible;
        const rows = [
            ['Reporte superadmin', periodLabel],
            [],
            ['Resumen ejecutivo'],
            ...(state.data.summary || []).map((item) => [item.title, item.value, item.detail || '', item.trend || '']),
            [],
            ['Residenciales visibles'],
            ['Nombre', 'Código', 'Ciudad', 'Estado', 'Estatus', 'Creado'],
            ...resPage.map((row) => [row.nombre, row.codigo || '', row.ciudad || '', row.estado || '', row.estatus_plan || '', row.created_at || '']),
            [],
            ['Usuarios visibles'],
            ['Nombre', 'Email', 'Rol', 'Estado', 'Creado'],
            ...userPage.map((row) => [row.name, row.email, row.rol, Number(row.is_active || 0) === 1 ? 'Activo' : 'Inactivo', row.created_at || '']),
        ];
        downloadCsv(`reporte-superadmin-${state.data.period?.value || 'actual'}.csv`, rows);
    }

    function printReport() {
        if (!state.data) return;
        const printWindow = window.open('', '_blank', 'width=1000,height=800');
        if (!printWindow) {
            showAlert('error', 'Tu navegador bloqueó la ventana de impresión.');
            return;
        }

        const summaryHtml = (state.data.summary || []).map((item) => `
            <div style="border:1px solid #e2e8f0;border-radius:16px;padding:16px;background:#f8fafc;">
              <div style="font-size:11px;text-transform:uppercase;color:#64748b;">${escapeHtml(item.title)}</div>
              <div style="font-size:28px;font-weight:700;color:#0f172a;margin-top:8px;">${escapeHtml(item.value)}</div>
              <div style="font-size:13px;color:#334155;margin-top:8px;">${escapeHtml(item.detail || '')}</div>
              <div style="font-size:12px;color:#64748b;margin-top:8px;">${escapeHtml(item.trend || '')}</div>
            </div>
        `).join('');

        const resRows = (state.data.latest_residenciales || []).slice(0, 6).map((row) => `
            <tr>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(row.nombre)}</td>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(row.ciudad || '')}${row.estado ? ', ' + escapeHtml(row.estado) : ''}</td>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(row.estatus_plan || '—')}</td>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(row.created_at || '')}</td>
            </tr>
        `).join('');

        const userRows = (state.data.latest_users || []).slice(0, 6).map((row) => `
            <tr>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(row.name)}</td>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(row.email)}</td>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${escapeHtml(row.rol)}</td>
              <td style="padding:10px;border-bottom:1px solid #e2e8f0;">${Number(row.is_active || 0) === 1 ? 'Activo' : 'Inactivo'}</td>
            </tr>
        `).join('');

        printWindow.document.write(`
            <html>
              <head>
                <title>Reporte superadmin</title>
                <style>
                  body { font-family: Arial, sans-serif; margin: 32px; color: #0f172a; }
                  h1, h2 { margin: 0; }
                  .meta { color: #64748b; margin-top: 8px; }
                  .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 24px; }
                  table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
                  th { text-align: left; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 10px; }
                  @media print { body { margin: 16px; } }
                </style>
              </head>
              <body>
                <h1>Resumen de reportes</h1>
                <div class="meta">${escapeHtml(state.data.period?.label || formatPeriodLabel())} · Generado ${new Date().toLocaleString()}</div>
                <div class="grid">${summaryHtml}</div>
                <h2 style="margin-top:32px;">Residenciales recientes</h2>
                <table>
                  <thead><tr><th>Residencial</th><th>Ubicación</th><th>Estatus</th><th>Creado</th></tr></thead>
                  <tbody>${resRows}</tbody>
                </table>
                <h2 style="margin-top:32px;">Usuarios recientes</h2>
                <table>
                  <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th></tr></thead>
                  <tbody>${userRows}</tbody>
                </table>
              </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => printWindow.print(), 250);
    }

    function renderData(data) {
        state.data = data;
        els.summaryPeriodLabel.textContent = data.period?.label || formatPeriodLabel();
        els.metricActivos.textContent = String(data.metrics?.residenciales_activos ?? 0);
        els.metricActivosMeta.textContent = 'Estado general de la plataforma';
        els.metricResidenciales.textContent = String(data.metrics?.residenciales_periodo ?? 0);
        els.metricResidencialesMeta.textContent = data.period?.label || formatPeriodLabel();
        els.metricUsuariosActivos.textContent = String(data.metrics?.usuarios_activos ?? 0);
        els.metricUsuariosActivosMeta.textContent = 'Usuarios con acceso activo';
        els.metricUsuarios.textContent = String(data.metrics?.usuarios_periodo ?? 0);
        els.metricUsuariosMeta.textContent = data.period?.label || formatPeriodLabel();

        renderSummary(data.summary || []);
        renderRangeMeta(data.range_meta || []);
        renderSeriesChart(data.series || {});
        renderRolesChart(data.roles || []);
        renderRoles(data.roles || []);
        renderResidencialesTable(data.latest_residenciales || []);
        renderUsersTable(data.latest_users || []);
    }

    async function load(resetPages = true) {
        if (resetPages) {
            state.residencialesPage = 1;
            state.usersPage = 1;
        }

        const url = new URL(API, window.location.origin);
        url.searchParams.set('period', els.period?.value || '30');
        const res = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.ok) throw new Error(json?.error || 'No se pudieron cargar los reportes.');
        renderData(json.data || {});
    }

    function handlePagerClick(e) {
        const button = e.target.closest('button[data-page-group]');
        if (!button || !state.data) return;
        const group = button.getAttribute('data-page-group');
        const action = button.getAttribute('data-page-action');

        if (group === 'residenciales') {
            const { totalPages } = getPageInfo(state.data.latest_residenciales || [], state.residencialesPage);
            if (action === 'prev' && state.residencialesPage > 1) state.residencialesPage -= 1;
            if (action === 'next' && state.residencialesPage < totalPages) state.residencialesPage += 1;
            renderResidencialesTable(state.data.latest_residenciales || []);
            return;
        }

        const { totalPages } = getPageInfo(state.data.latest_users || [], state.usersPage);
        if (action === 'prev' && state.usersPage > 1) state.usersPage -= 1;
        if (action === 'next' && state.usersPage < totalPages) state.usersPage += 1;
        renderUsersTable(state.data.latest_users || []);
    }

    els.residencialesPagination?.addEventListener('click', handlePagerClick);
    els.usersPagination?.addEventListener('click', handlePagerClick);

    els.period?.addEventListener('change', () => {
        load(true).catch((err) => showAlert('error', err.message || 'No se pudieron actualizar los reportes.'));
    });

    els.btnRefresh?.addEventListener('click', () => {
        load(true).then(() => showAlert('ok', 'Reportes actualizados.')).catch((err) => showAlert('error', err.message || 'No se pudo refrescar.'));
    });

    els.btnExportCsv?.addEventListener('click', () => {
        try {
            exportCsv();
            showAlert('ok', 'CSV exportado correctamente.');
        } catch (err) {
            showAlert('error', err.message || 'No se pudo exportar el CSV.');
        }
    });

    els.btnPrint?.addEventListener('click', () => {
        try {
            printReport();
        } catch (err) {
            showAlert('error', err.message || 'No se pudo generar el resumen imprimible.');
        }
    });

    load(true).catch((err) => showAlert('error', err.message || 'No se pudieron cargar los reportes.'));
})();
