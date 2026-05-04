(function () {
    const root = document.getElementById('superadminResidencialesView');
    if (!root || root.dataset.bound === '1') return;
    root.dataset.bound = '1';

    const API = (window.SuperadminDashboard?.API || '/superadmin/php/api/') + 'residenciales.php';
    const PER_PAGE = 3;
    const els = {
        alert: document.getElementById('residencialesAlert'),
        summaryTotal: document.getElementById('resSummaryTotal'),
        summaryActivos: document.getElementById('resSummaryActivos'),
        summaryPrueba: document.getElementById('resSummaryPrueba'),
        summaryPlan: document.getElementById('resSummaryPlan'),
        filterForm: document.getElementById('residencialesFilterForm'),
        q: document.getElementById('resFiltroQ'),
        plan: document.getElementById('resFiltroPlan'),
        estatus: document.getElementById('resFiltroEstatus'),
        modo: document.getElementById('resFiltroModo'),
        btnClear: document.getElementById('btnLimpiarResidenciales'),
        btnRefresh: document.getElementById('btnRefreshResidenciales'),
        tableWrap: document.getElementById('residencialesTableWrap'),
        pagination: document.getElementById('residencialesPagination'),
        btnNuevo: document.getElementById('btnNuevoResidencial'),
        modal: document.getElementById('residencialModal'),
        form: document.getElementById('residencialForm'),
        btnCloseModal: document.getElementById('btnCloseResidencialModal'),
        btnCancelModal: document.getElementById('btnCancelResidencialModal'),
        modalError: document.getElementById('residencialModalError'),
        planSelectModal: document.getElementById('resPlanSelect'),
        createPreset: document.getElementById('resServicePreset'),
        createRoleFields: document.getElementById('resServiceRoleFields'),
        createModuleFields: document.getElementById('resServiceModuleFields'),
        createModuleHint: document.getElementById('resServiceModuleHint'),
        createAdvancedToggle: document.getElementById('resServiceAdvancedToggle'),
        serviceModal: document.getElementById('serviceProfileModal'),
        serviceModalName: document.getElementById('serviceProfileModalName'),
        serviceForm: document.getElementById('serviceProfileForm'),
        serviceId: document.getElementById('serviceProfileResidencialId'),
        servicePreset: document.getElementById('serviceProfilePreset'),
        serviceRoleFields: document.getElementById('serviceProfileRoleFields'),
        serviceModuleFields: document.getElementById('serviceProfileModuleFields'),
        serviceModuleHint: document.getElementById('serviceProfileModuleHint'),
        serviceAdvancedToggle: document.getElementById('serviceProfileAdvancedToggle'),
        serviceModalError: document.getElementById('serviceProfileModalError'),
        serviceOperatorsStatus: document.getElementById('serviceOperatorsStatus'),
        serviceOperatorsWrap: document.getElementById('serviceOperatorsWrap'),
        btnServiceCreateOperator: document.getElementById('btnServiceCreateOperator'),
        btnServiceAssignOperator: document.getElementById('btnServiceAssignOperator'),
        btnCloseServiceModal: document.getElementById('btnCloseServiceProfileModal'),
        btnCancelServiceModal: document.getElementById('btnCancelServiceProfileModal'),
        onboardingModal: document.getElementById('serviceOnboardingModal'),
        onboardingName: document.getElementById('serviceOnboardingName'),
        btnCloseOnboardingModal: document.getElementById('btnCloseServiceOnboardingModal'),
        btnCancelOnboardingModal: document.getElementById('btnCancelServiceOnboardingModal'),
        btnOnboardingCreateUser: document.getElementById('btnOnboardingCreateUser'),
        btnOnboardingAssignExisting: document.getElementById('btnOnboardingAssignExisting'),
        btnOnboardingLater: document.getElementById('btnOnboardingLater'),
    };

    let csrf = '';
    let planes = [];
    let serviceLabels = { roles: {}, modules: {} };
    let servicePresets = [];
    let serviceMatrix = { roles: [], modules: {} };
    const state = {
        items: [],
        page: 1,
        serviceProfileItem: null,
        serviceProfileDirty: false,
        suppressServiceDirty: false,
        onboardingService: null,
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
        const normalizedType = type === 'ok' ? 'success' : type;
        if (window.AppToast?.show) {
            window.AppToast.show({ type: normalizedType, message: msg });
            return;
        }
        if (!els.alert) return;
        els.alert.classList.remove('hidden');
        els.alert.className = `rounded-2xl px-4 py-3 text-sm ${normalizedType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'}`;
        els.alert.textContent = msg;
    }

    function showInlineError(el, msg) {
        if (!el) {
            showAlert('error', msg);
            return;
        }
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    function clearInlineError(el) {
        if (!el) return;
        el.textContent = '';
        el.classList.add('hidden');
    }

    async function api(data) {
        const fd = new FormData();
        Object.entries(data || {}).forEach(([key, value]) => fd.append(key, value));
        const res = await fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.ok) throw new Error(json?.error || 'No se pudo procesar la solicitud.');
        return json;
    }

    function fillPlans() {
        const options = ['<option value="">Todos</option>'].concat(
            planes.map((plan) => `<option value="${escapeHtml(plan.id)}">${escapeHtml(plan.nombre)} (${escapeHtml(plan.codigo)})</option>`)
        );
        els.plan.innerHTML = options.join('');

        const modalOptions = ['<option value="">Sin asignar</option>'].concat(
            planes.map((plan) => `<option value="${escapeHtml(plan.id)}">${escapeHtml(plan.nombre)} (${escapeHtml(plan.codigo)})</option>`)
        );
        els.planSelectModal.innerHTML = modalOptions.join('');
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

    function statusClass(status) {
        if (status === 'activo') return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        if (status === 'prueba') return 'bg-sky-50 text-sky-700 border border-sky-200';
        if (status === 'suspendido') return 'bg-amber-50 text-amber-700 border border-amber-200';
        return 'bg-rose-50 text-rose-700 border border-rose-200';
    }

    function presetLabel(preset) {
        const key = String(preset || 'residencial');
        return key.charAt(0).toUpperCase() + key.slice(1);
    }

    function modeLabel(mode) {
        return presetLabel(mode || 'residencial');
    }

    function enabledSummary(profile, fieldGroup) {
        const source = profile?.[fieldGroup] || {};
        return Object.values(source)
            .filter((meta) => meta?.enabled)
            .map((meta) => meta.label)
            .slice(0, fieldGroup === 'roles' ? 3 : 4)
            .join(', ');
    }

    function roleSummary(profile) {
        const summary = enabledSummary(profile, 'roles');
        const guardEnabled = !!profile?.roles?.habilita_guardia?.enabled;
        if (!guardEnabled) {
            return summary ? `${summary} · Guardia deshabilitado` : 'Guardia deshabilitado';
        }
        return summary || 'Sin roles visibles';
    }

    function moduleSummary(profile) {
        return enabledSummary(profile, 'modules') || 'Sin módulos visibles';
    }

    function operatorStatusMeta(item) {
        const total = Number(item?.operators_summary?.total ?? item?.usuarios_asignados ?? 0);
        const active = Number(item?.operators_summary?.active ?? item?.usuarios_activos_asignados ?? 0);
        const ready = Number(item?.operators_summary?.ready ?? 0);
        const pending = Number(item?.operators_summary?.pending ?? 0);

        if (total <= 0) {
            return {
                text: 'Sin operador asignado',
                className: 'bg-slate-100 text-slate-600 border border-slate-200',
            };
        }

        if (active <= 0) {
            return {
                text: `${total} operador${total === 1 ? '' : 'es'} asignado${total === 1 ? '' : 's'} sin activar`,
                className: 'bg-amber-50 text-amber-700 border border-amber-200',
            };
        }

        if (pending > 0) {
            return {
                text: `${pending} pendiente${pending === 1 ? '' : 's'} de ${total} asignados`,
                className: 'bg-amber-50 text-amber-700 border border-amber-200',
            };
        }

        if (total === 1 && ready === 1) {
            return {
                text: '1 operador listo',
                className: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            };
        }

        return {
            text: ready === total
                ? `${total} operadores asignados`
                : `${ready} listo${ready === 1 ? '' : 's'} de ${total} asignados`,
            className: 'bg-sky-50 text-sky-700 border border-sky-200',
        };
    }

    function renderPagination() {
        if (!els.pagination) return;
        const { total, totalPages, page, start, end } = getPageInfo(state.items, state.page);
        state.page = page;

        if (total <= PER_PAGE) {
            els.pagination.innerHTML = '';
            return;
        }

        els.pagination.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 md:flex-row md:items-center md:justify-between">
              <div>Mostrando ${start + 1}-${end} de ${total}</div>
              <div class="flex items-center gap-2">
                <button type="button" data-page-action="prev" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page <= 1 ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Anterior</button>
                <div class="rounded-lg bg-white px-3 py-1.5 text-slate-700">Página ${page} de ${totalPages}</div>
                <button type="button" data-page-action="next" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 ${page >= totalPages ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-100'}">Siguiente</button>
              </div>
            </div>
        `;
    }

    function renderTable(items = state.items) {
        state.items = Array.isArray(items) ? items : [];

        if (!state.items.length) {
            els.tableWrap.innerHTML = `<div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">No hay servicios que coincidan con el filtro actual.</div>`;
            if (els.pagination) els.pagination.innerHTML = '';
            return;
        }

        const { visible } = getPageInfo(state.items, state.page);
        els.tableWrap.innerHTML = `
            <div class="space-y-3 md:hidden">
              ${visible.map((item) => {
                  const roles = roleSummary(item.service_profile);
                  const modules = moduleSummary(item.service_profile);
                  const operatorStatus = operatorStatusMeta(item);
                  return `
                    <article class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                      <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                          <div class="font-semibold text-slate-800">${escapeHtml(item.nombre)}</div>
                          <div class="mt-1 text-xs text-slate-500">Código: ${escapeHtml(item.codigo || '—')}</div>
                          <div class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] ${operatorStatus.className}">${escapeHtml(operatorStatus.text)}</div>
                        </div>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${statusClass(item.estatus_plan)}">${escapeHtml(item.estatus_label || item.estatus_plan || '—')}</span>
                      </div>
                      <dl class="mt-4 grid grid-cols-1 gap-3 text-sm">
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Ubicación</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml((item.ciudad || '') + (item.estado ? ', ' + item.estado : ''))}</dd>
                          <dd class="text-xs text-slate-500">${escapeHtml(item.pais || '')}</dd>
                        </div>
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Tipo de servicio</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml(presetLabel(item.preset_servicio || 'residencial'))}</dd>
                          <dd class="text-xs text-slate-500">Modo base: ${escapeHtml(modeLabel(item.modo_operacion || 'residencial'))}</dd>
                        </div>
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Roles habilitados</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml(roles)}</dd>
                        </div>
                        <div>
                          <dt class="text-xs uppercase tracking-wide text-slate-400">Módulos habilitados</dt>
                          <dd class="mt-1 text-slate-700">${escapeHtml(modules)}</dd>
                        </div>
                      </dl>
                      <div class="mt-4 flex justify-end">
                        <button type="button" data-service-profile-id="${escapeHtml(item.id)}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">Configurar</button>
                      </div>
                    </article>
                  `;
              }).join('')}
            </div>
            <div class="hidden md:block space-y-4">
              ${visible.map((item) => {
                  const roles = roleSummary(item.service_profile);
                  const modules = moduleSummary(item.service_profile);
                  const operatorStatus = operatorStatusMeta(item);
                  return `
                    <article class="rounded-[1.9rem] border border-slate-200 bg-white px-5 py-5 shadow-sm">
                      <div class="grid grid-cols-[minmax(240px,1.1fr)_minmax(170px,0.7fr)_minmax(190px,0.8fr)_minmax(210px,0.9fr)_minmax(260px,1.15fr)_150px] gap-5 items-start">
                        <div class="min-w-0">
                          <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Servicio / cliente</div>
                          <div class="mt-2 font-semibold text-[1.05rem] text-slate-800">${escapeHtml(item.nombre)}</div>
                          <div class="mt-1 text-sm text-slate-500">Código: ${escapeHtml(item.codigo || '—')}</div>
                          <div class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[11px] ${operatorStatus.className}">${escapeHtml(operatorStatus.text)}</div>
                        </div>
                        <div class="min-w-0">
                          <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Ubicación</div>
                          <div class="mt-2 text-sm text-slate-700">${escapeHtml((item.ciudad || '') + ((item.estado ? ', ' + item.estado : '')))}</div>
                          <div class="mt-1 text-xs text-slate-500">${escapeHtml(item.pais || '')}</div>
                        </div>
                        <div class="min-w-0">
                          <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Tipo de servicio</div>
                          <div class="mt-2 text-sm text-slate-700">${escapeHtml(presetLabel(item.preset_servicio || 'residencial'))}</div>
                          <div class="mt-1 text-xs text-slate-500">Modo base: ${escapeHtml(modeLabel(item.modo_operacion || 'residencial'))}</div>
                        </div>
                        <div class="min-w-0">
                          <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Roles habilitados</div>
                          <div class="mt-2 text-sm text-slate-700 leading-6">${escapeHtml(roles)}</div>
                        </div>
                        <div class="min-w-0">
                          <div class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-400">Módulos habilitados</div>
                          <div class="mt-2 text-sm text-slate-700 leading-6">${escapeHtml(modules)}</div>
                        </div>
                        <div class="flex h-full min-h-[90px] flex-col items-end justify-between gap-4">
                          <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] ${statusClass(item.estatus_plan)}">${escapeHtml(item.estatus_label || item.estatus_plan || '—')}</span>
                          <button type="button" data-service-profile-id="${escapeHtml(item.id)}" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Configurar</button>
                        </div>
                      </div>
                    </article>
                  `;
              }).join('')}
            </div>
        `;

        renderPagination();
    }

    function renderServiceFields(container, labels, values = {}, kind = 'modules') {
        if (!container) return;
        const isModuleGroup = kind === 'modules';
        container.innerHTML = Object.entries(labels).map(([key, label]) => {
            const moduleMeta = isModuleGroup ? (serviceMatrix.modules?.[key] || null) : null;
            const supportBadge = moduleMeta?.support_only
                ? '<span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[10px] uppercase tracking-wide text-slate-500">Acción</span>'
                : '';
            const wrapperAttr = isModuleGroup ? `data-service-module-flag="${escapeHtml(key)}"` : `data-service-role-flag="${escapeHtml(key)}"`;
            return `
                <label ${wrapperAttr} class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                  <input type="checkbox" name="${escapeHtml(key)}" value="1" ${values[key] ? 'checked' : ''}>
                  <span class="flex-1">${escapeHtml(label)}</span>
                  ${supportBadge}
                </label>
            `;
        }).join('');
    }

    function getPresetDefaults(presetKey) {
        return servicePresets.find((item) => item.key === presetKey)?.defaults || {};
    }

    function selectedRoleKeys(form) {
        const selected = new Set();
        const roleMap = serviceMatrix.roles || [];
        roleMap.forEach((meta) => {
            const field = form?.querySelector(`[name="${meta.flag}"]`);
            if (field?.checked) selected.add(String(meta.role || ''));
        });
        return selected;
    }

    function currentModuleValues(form) {
        const values = {};
        Object.keys(serviceLabels.modules || {}).forEach((flag) => {
            values[flag] = !!form?.querySelector(`[name="${flag}"]`)?.checked;
        });
        return values;
    }

    function syncServiceModuleVisibility(form, options = {}) {
        if (!form) return;

        const isCreate = form === els.form;
        const hintEl = isCreate ? els.createModuleHint : els.serviceModuleHint;
        const advanced = isCreate ? !!els.createAdvancedToggle?.checked : !!els.serviceAdvancedToggle?.checked;
        const presetKey = form.querySelector('[name="preset_servicio"]')?.value || 'residencial';
        const defaults = getPresetDefaults(presetKey);
        const selectedRoles = selectedRoleKeys(form);
        const moduleValues = currentModuleValues(form);

        let visibleCount = 0;
        let optionalHiddenCount = 0;

        Object.entries(serviceLabels.modules || {}).forEach(([flag]) => {
            const wrapper = form.querySelector(`[data-service-module-flag="${flag}"]`);
            const field = form.querySelector(`[name="${flag}"]`);
            const meta = serviceMatrix.modules?.[flag] || null;
            if (!wrapper || !field || !meta) return;

            const roleCompatible = (meta.roles || []).some((role) => selectedRoles.has(String(role || '')));
            const dependencies = Array.isArray(meta.depends_on) ? meta.depends_on : [];
            const depsSatisfied = dependencies.every((depFlag) => {
                const depField = form.querySelector(`[name="${depFlag}"]`);
                return !!depField?.checked;
            });

            if (!roleCompatible || !depsSatisfied) {
                field.checked = false;
                wrapper.classList.add('hidden');
                return;
            }

            const isDefault = Number(defaults?.[flag] || 0) === 1;
            const isChecked = !!field.checked;
            const shouldShow = advanced || isDefault || isChecked;

            if (shouldShow) {
                visibleCount += 1;
                wrapper.classList.remove('hidden');
            } else {
                optionalHiddenCount += 1;
                wrapper.classList.add('hidden');
            }
        });

        if (hintEl) {
            if (!selectedRoles.size) {
                hintEl.textContent = 'Activa al menos un rol para ver módulos compatibles.';
            } else if (optionalHiddenCount > 0 && !advanced) {
                hintEl.textContent = `Se muestran ${visibleCount} módulos compatibles por default. Activa "Mostrar avanzados" para ver ${optionalHiddenCount} opcionales.`;
            } else {
                hintEl.textContent = `Se muestran ${visibleCount} módulos compatibles con los roles activos.`;
            }
        }

        if (options.markDirty && !state.suppressServiceDirty) {
            state.serviceProfileDirty = true;
        }
    }

    function applyPresetToForm(form, presetKey) {
        const preset = servicePresets.find((item) => item.key === presetKey);
        const defaults = preset?.defaults || {};
        Object.keys(serviceLabels.roles || {}).forEach((key) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) field.checked = Number(defaults[key] || 0) === 1;
        });
        Object.keys(serviceLabels.modules || {}).forEach((key) => {
            const field = form.querySelector(`[name="${key}"]`);
            if (field) field.checked = Number(defaults[key] || 0) === 1;
        });
    }

    function fillServicePresetOptions(select) {
        if (!select) return;
        select.innerHTML = servicePresets.map((preset) => `<option value="${escapeHtml(preset.key)}">${escapeHtml(preset.label)}</option>`).join('');
    }

    function initServiceForms() {
        fillServicePresetOptions(els.createPreset);
        fillServicePresetOptions(els.servicePreset);
        renderServiceFields(els.createRoleFields, serviceLabels.roles || {}, {}, 'roles');
        renderServiceFields(els.createModuleFields, serviceLabels.modules || {}, {}, 'modules');
        renderServiceFields(els.serviceRoleFields, serviceLabels.roles || {}, {}, 'roles');
        renderServiceFields(els.serviceModuleFields, serviceLabels.modules || {}, {}, 'modules');
        if (els.createPreset) {
            applyPresetToForm(els.form, els.createPreset.value || 'residencial');
            syncServiceModuleVisibility(els.form);
        }
    }

    function openCreateModal() {
        els.form.reset();
        clearInlineError(els.modalError);
        if (els.form.pais) els.form.pais.value = 'México';
        if (els.form.zona_horaria) els.form.zona_horaria.value = 'America/Mexico_City';
        if (els.form.estatus_plan) els.form.estatus_plan.value = 'activo';
        if (els.form.modo_operacion) els.form.modo_operacion.value = 'residencial';
        if (els.createAdvancedToggle) els.createAdvancedToggle.checked = false;
        if (els.createPreset) {
            els.createPreset.value = 'residencial';
            applyPresetToForm(els.form, 'residencial');
        }
        syncServiceModuleVisibility(els.form);
        els.modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeCreateModal() {
        els.modal.classList.add('hidden');
        clearInlineError(els.modalError);
        document.body.style.overflow = '';
    }

    function renderServiceOperators(item) {
        const operatorStatus = operatorStatusMeta(item);
        if (els.serviceOperatorsStatus) {
            els.serviceOperatorsStatus.className = `inline-flex rounded-full px-3 py-1.5 text-xs font-medium ${operatorStatus.className}`;
            els.serviceOperatorsStatus.textContent = operatorStatus.text;
        }

        const operators = Array.isArray(item?.operators) ? item.operators : [];
        if (!els.serviceOperatorsWrap) return;

        if (!operators.length) {
            els.serviceOperatorsWrap.innerHTML = `
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                  Aún no hay operadores asignados a este servicio.
                </div>
            `;
            return;
        }

        els.serviceOperatorsWrap.innerHTML = operators.map((operator) => `
            <article class="rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-3">
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <div class="font-medium text-slate-800">${escapeHtml(operator.name || 'Usuario')}</div>
                      ${Number(operator.es_principal || 0) === 1 ? '<span class="inline-flex rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-[11px] text-sky-700">Principal</span>' : ''}
                      ${(() => {
                          const code = String(operator.operator_status?.code || '');
                          if (code === 'listo') return '<span class="inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] text-emerald-700">Listo</span>';
                          if (code === 'pendiente') return '<span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[11px] text-amber-700">Pendiente</span>';
                          return '<span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">Inactivo</span>';
                      })()}
                    </div>
                  <div class="mt-1 text-sm text-slate-600">${escapeHtml(operator.email || 'Sin correo')}</div>
                  ${operator.operator_status?.code === 'pendiente' && operator.operator_status?.reason_message
                    ? `<div class="mt-2 text-xs text-amber-700">${escapeHtml(operator.operator_status.reason_message)}</div>`
                    : ''}
                </div>
                <div class="text-right">
                  <div class="text-xs uppercase tracking-wide text-slate-400">Rol</div>
                  <div class="mt-1 text-sm text-slate-700">${escapeHtml(operator.rol_nombre || '—')}</div>
                </div>
              </div>
            </article>
        `).join('');
    }

    function populateServiceModal(item) {
        state.serviceProfileItem = item;
        state.suppressServiceDirty = true;
        els.serviceId.value = String(item.id);
        els.serviceModalName.textContent = `${item.nombre} · ${presetLabel(item.service_profile?.preset_servicio || item.modo_operacion || 'residencial')}`;
        if (els.servicePreset) {
            els.servicePreset.value = item.service_profile?.preset_servicio || item.modo_operacion || 'residencial';
        }
        if (els.serviceAdvancedToggle) els.serviceAdvancedToggle.checked = false;

        Object.keys(serviceLabels.roles || {}).forEach((key) => {
            const field = els.serviceForm.querySelector(`[name="${key}"]`);
            if (field) field.checked = !!item.service_profile?.roles?.[key]?.enabled;
        });
        Object.keys(serviceLabels.modules || {}).forEach((key) => {
            const field = els.serviceForm.querySelector(`[name="${key}"]`);
            if (field) field.checked = !!item.service_profile?.modules?.[key]?.enabled;
        });

        renderServiceOperators(item);
        syncServiceModuleVisibility(els.serviceForm);
        clearInlineError(els.serviceModalError);
        state.serviceProfileDirty = false;
        state.suppressServiceDirty = false;
    }

    async function openServiceModal(id) {
        const json = await api({ action: 'get_service_profile', id });
        const item = json.data?.item || null;
        if (!item) throw new Error('No encontramos el cliente seleccionado.');

        populateServiceModal(item);
        els.serviceModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeServiceModal() {
        els.serviceModal.classList.add('hidden');
        clearInlineError(els.serviceModalError);
        document.body.style.overflow = '';
        state.serviceProfileItem = null;
        state.serviceProfileDirty = false;
        state.suppressServiceDirty = false;
    }

    function openOnboardingModal(service) {
        state.onboardingService = service || null;
        if (els.onboardingName) {
            const type = service?.preset_servicio ? presetLabel(service.preset_servicio) : 'Servicio';
            els.onboardingName.textContent = `${service?.nombre || 'Servicio'} · ${type}`;
        }
        els.onboardingModal?.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeOnboardingModal() {
        els.onboardingModal?.classList.add('hidden');
        state.onboardingService = null;
        document.body.style.overflow = '';
    }

    async function ensureUsuariosFlowReady() {
        if (window.SuperadminUsuarios?.refreshAll) {
            return window.SuperadminUsuarios;
        }
        await window.SuperadminDashboard?.loadView?.('usuarios', { force: true });
        return new Promise((resolve, reject) => {
            const started = Date.now();
            const tick = () => {
                if (window.SuperadminUsuarios?.refreshAll) {
                    resolve(window.SuperadminUsuarios);
                    return;
                }
                if (Date.now() - started > 3000) {
                    reject(new Error('No pudimos abrir el flujo de usuarios en este momento.'));
                    return;
                }
                window.setTimeout(tick, 80);
            };
            tick();
        });
    }

    function requireSavedServiceProfile() {
        if (!state.serviceProfileDirty) return true;
        showInlineError(els.serviceModalError, 'Guarda primero el perfil del servicio para continuar con operadores.');
        return false;
    }

    async function loadMeta() {
        const json = await api({ action: 'meta' });
        planes = json.data?.planes || [];
        csrf = json.data?.csrf_token || '';
        serviceLabels = json.data?.service_labels || { roles: {}, modules: {} };
        servicePresets = json.data?.service_presets || [];
        serviceMatrix = json.data?.service_matrix || { roles: [], modules: {} };
        fillPlans();
        initServiceForms();
    }

    async function loadList(resetPage = true) {
        if (resetPage) state.page = 1;
        const json = await api({
            action: 'list',
            q: els.q.value,
            plan_id: els.plan.value,
            estatus_plan: els.estatus.value,
            modo_operacion: els.modo?.value || '',
        });

        const summary = json.data?.summary || {};
        els.summaryTotal.textContent = String(summary.total ?? 0);
        els.summaryActivos.textContent = String(summary.activos ?? 0);
        els.summaryPrueba.textContent = String(summary.prueba ?? 0);
        els.summaryPlan.textContent = String(summary.con_plan ?? 0);

        renderTable(json.data?.items || []);
    }

    function collectServicePayload(form) {
        const payload = {};
        payload.preset_servicio = form.querySelector('[name="preset_servicio"]')?.value || 'residencial';
        Object.keys(serviceLabels.roles || {}).forEach((key) => {
            payload[key] = form.querySelector(`[name="${key}"]`)?.checked ? '1' : '0';
        });
        Object.keys(serviceLabels.modules || {}).forEach((key) => {
            payload[key] = form.querySelector(`[name="${key}"]`)?.checked ? '1' : '0';
        });
        return payload;
    }

    els.pagination?.addEventListener('click', (e) => {
        const button = e.target.closest('button[data-page-action]');
        if (!button) return;
        const action = button.getAttribute('data-page-action');
        const { totalPages } = getPageInfo(state.items, state.page);
        if (action === 'prev' && state.page > 1) state.page -= 1;
        if (action === 'next' && state.page < totalPages) state.page += 1;
        renderTable();
    });

    els.filterForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await loadList(true);
        } catch (err) {
            showAlert('error', err.message || 'No se pudo filtrar.');
        }
    });

    els.btnClear?.addEventListener('click', () => {
        els.filterForm.reset();
        if (els.modo) els.modo.value = '';
        loadList(true).catch((err) => showAlert('error', err.message || 'No se pudo limpiar.'));
    });

    els.btnRefresh?.addEventListener('click', () => {
        loadList(true).then(() => showAlert('ok', 'Listado actualizado.')).catch((err) => showAlert('error', err.message || 'No se pudo actualizar.'));
    });

    els.btnNuevo?.addEventListener('click', openCreateModal);
    els.btnCloseModal?.addEventListener('click', closeCreateModal);
    els.btnCancelModal?.addEventListener('click', closeCreateModal);
    els.modal?.addEventListener('click', (e) => { if (e.target === els.modal) closeCreateModal(); });

    els.createPreset?.addEventListener('change', (e) => {
        const nextPreset = e.target.value || 'residencial';
        const modeField = els.form?.querySelector('[name="modo_operacion"]');
        if (modeField) modeField.value = nextPreset;
        applyPresetToForm(els.form, e.target.value || 'residencial');
        syncServiceModuleVisibility(els.form);
    });
    els.form?.querySelector('[name="modo_operacion"]')?.addEventListener('change', (e) => {
        if (els.createPreset) {
            els.createPreset.value = e.target.value || 'residencial';
            applyPresetToForm(els.form, els.createPreset.value);
            syncServiceModuleVisibility(els.form);
        }
    });
    els.createAdvancedToggle?.addEventListener('change', () => syncServiceModuleVisibility(els.form));
    els.createRoleFields?.addEventListener('change', (e) => {
        if (e.target instanceof HTMLInputElement) {
            syncServiceModuleVisibility(els.form);
        }
    });
    els.createModuleFields?.addEventListener('change', (e) => {
        if (e.target instanceof HTMLInputElement) {
            syncServiceModuleVisibility(els.form);
        }
    });

    els.tableWrap?.addEventListener('click', (e) => {
        const button = e.target.closest('[data-service-profile-id]');
        if (!button) return;
        openServiceModal(Number(button.getAttribute('data-service-profile-id') || 0)).catch((err) => {
            showAlert('error', err.message || 'No se pudo abrir el perfil de servicio.');
        });
    });

    els.btnCloseServiceModal?.addEventListener('click', closeServiceModal);
    els.btnCancelServiceModal?.addEventListener('click', closeServiceModal);
    els.serviceModal?.addEventListener('click', (e) => { if (e.target === els.serviceModal) closeServiceModal(); });
    els.servicePreset?.addEventListener('change', (e) => {
        applyPresetToForm(els.serviceForm, e.target.value || 'residencial');
        syncServiceModuleVisibility(els.serviceForm, { markDirty: true });
    });
    els.serviceAdvancedToggle?.addEventListener('change', () => syncServiceModuleVisibility(els.serviceForm));
    els.serviceForm?.addEventListener('change', (e) => {
        if (state.suppressServiceDirty) return;
        if (!(e.target instanceof HTMLInputElement || e.target instanceof HTMLSelectElement || e.target instanceof HTMLTextAreaElement)) return;
        if (!e.target.name || e.target.name === 'id') return;
        state.serviceProfileDirty = true;
        if (e.target instanceof HTMLInputElement || e.target instanceof HTMLSelectElement) {
            syncServiceModuleVisibility(els.serviceForm);
        }
    });

    els.btnServiceCreateOperator?.addEventListener('click', async () => {
        if (!state.serviceProfileItem || !requireSavedServiceProfile()) return;
        const service = {
            id: state.serviceProfileItem.id,
            nombre: state.serviceProfileItem.nombre,
            preset_servicio: state.serviceProfileItem.service_profile?.preset_servicio || state.serviceProfileItem.modo_operacion || 'residencial',
        };
        try {
            const usersFlow = await ensureUsuariosFlowReady();
            await usersFlow.refreshAll();
            closeServiceModal();
            usersFlow.openUserModalForService?.(service);
        } catch (err) {
            showInlineError(els.serviceModalError, err.message || 'No se pudo abrir el flujo de usuarios.');
        }
    });

    els.btnServiceAssignOperator?.addEventListener('click', async () => {
        if (!state.serviceProfileItem || !requireSavedServiceProfile()) return;
        const service = {
            id: state.serviceProfileItem.id,
            nombre: state.serviceProfileItem.nombre,
            preset_servicio: state.serviceProfileItem.service_profile?.preset_servicio || state.serviceProfileItem.modo_operacion || 'residencial',
        };
        try {
            const usersFlow = await ensureUsuariosFlowReady();
            await usersFlow.refreshAll();
            closeServiceModal();
            usersFlow.openAssignmentModalForService?.(service);
        } catch (err) {
            showInlineError(els.serviceModalError, err.message || 'No se pudo abrir el flujo de asignación.');
        }
    });

    els.form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearInlineError(els.modalError);
        const fd = new FormData(els.form);
        const payload = { action: 'create', csrf_token: csrf };
        fd.forEach((value, key) => {
            if (key === 'permite_qr' || key === 'permite_trabajadores_recurrentes' || key === 'requiere_placa_vehiculo' || key === 'requiere_identificacion_visita') {
                payload[key] = '1';
            } else {
                payload[key] = value;
            }
        });
        ['permite_qr', 'permite_trabajadores_recurrentes', 'requiere_placa_vehiculo', 'requiere_identificacion_visita'].forEach((field) => {
            if (!Object.prototype.hasOwnProperty.call(payload, field)) payload[field] = '0';
        });
        Object.assign(payload, collectServicePayload(els.form));

        try {
            const json = await api(payload);
            closeCreateModal();
            await loadList(true);
            window.SuperadminDashboard?.loadContext?.();
            const service = json.data?.item || null;
            if (service) {
                openOnboardingModal(service);
            } else {
                showAlert('ok', json.message || 'Servicio creado correctamente.');
            }
        } catch (err) {
            showInlineError(els.modalError, err.message || 'No se pudo crear el servicio.');
        }
    });

    els.serviceForm?.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearInlineError(els.serviceModalError);
        const payload = {
            action: 'update_service_profile',
            csrf_token: csrf,
            id: els.serviceId?.value || '',
            ...collectServicePayload(els.serviceForm),
        };

        try {
            await api(payload);
            closeServiceModal();
            await loadList(false);
            showAlert('ok', 'Perfil de servicio actualizado correctamente.');
        } catch (err) {
            showInlineError(els.serviceModalError, err.message || 'No se pudo guardar el perfil de servicio.');
        }
    });

    els.btnCloseOnboardingModal?.addEventListener('click', closeOnboardingModal);
    els.btnCancelOnboardingModal?.addEventListener('click', closeOnboardingModal);
    els.onboardingModal?.addEventListener('click', (e) => { if (e.target === els.onboardingModal) closeOnboardingModal(); });

    els.btnOnboardingLater?.addEventListener('click', () => {
        const serviceName = state.onboardingService?.nombre || 'Servicio';
        closeOnboardingModal();
        showAlert('ok', `${serviceName} quedó creado. Puedes asignar usuarios después desde Usuarios.`);
    });

    els.btnOnboardingCreateUser?.addEventListener('click', async () => {
        const service = state.onboardingService;
        if (!service) return;
        closeOnboardingModal();
        try {
            const usersFlow = await ensureUsuariosFlowReady();
            await usersFlow.refreshAll();
            usersFlow.openUserModalForService?.(service);
        } catch (err) {
            showAlert('error', err.message || 'No se pudo abrir el flujo de usuarios.');
        }
    });

    els.btnOnboardingAssignExisting?.addEventListener('click', async () => {
        const service = state.onboardingService;
        if (!service) return;
        closeOnboardingModal();
        try {
            const usersFlow = await ensureUsuariosFlowReady();
            await usersFlow.refreshAll();
            usersFlow.openAssignmentModalForService?.(service);
        } catch (err) {
            showAlert('error', err.message || 'No se pudo abrir el flujo de asignación.');
        }
    });

    Promise.all([loadMeta(), loadList(true)]).catch((err) => showAlert('error', err.message || 'No se pudo cargar residenciales.'));
})();
