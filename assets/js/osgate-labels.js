(function () {
  const PRESETS = {
    residencial: {
      label: 'Residencial',
      description: 'Operación residencial para residentes, guardias, accesos, comunicados, pagos y servicios.',
      terms: {
        home: 'Home',
        access: 'Accesos',
        service: 'Residencial',
        people: 'Residentes',
        units: 'Unidades',
        notices: 'Comunicados',
        incidents: 'Incidencias',
        operators: 'Guardias',
        passes: 'Visitas',
        logbook: 'Bitácora',
        materials: 'Materiales',
        tools: 'Herramientas',
        settings: 'Perfil',
        policy: 'Reglamento',
      },
    },
    empresa: {
      label: 'Empresa',
      description: 'Control operativo para oficinas, empleados, accesos e incidencias.',
      terms: {
        home: 'Dashboard',
        access: 'Escanear QR',
        service: 'Empresa / Sucursal',
        people: 'Personal autorizado',
        units: 'Áreas',
        notices: 'Avisos operativos',
        incidents: 'Incidentes',
        operators: 'Operadores',
        passes: 'Pases temporales',
        logbook: 'Bitácora',
        materials: 'Materiales',
        tools: 'Herramientas',
        settings: 'Configuración',
        policy: 'Políticas',
      },
    },
    comercio: {
      label: 'Comercio',
      description: 'Control operativo para comercios con accesos, guardias e incidencias.',
      terms: null,
    },
    obra: {
      label: 'Obra',
      description: 'Control de obra para personal, materiales, accesos e incidencias.',
      terms: {
        home: 'Dashboard',
        access: 'Escanear QR',
        service: 'Obra / Frente',
        people: 'Personal autorizado',
        units: 'Áreas',
        notices: 'Avisos operativos',
        incidents: 'Incidentes',
        operators: 'Operadores',
        passes: 'Pases temporales',
        logbook: 'Bitácora',
        materials: 'Materiales',
        tools: 'Herramientas',
        settings: 'Configuración',
        policy: 'Políticas',
      },
    },
    servicio: {
      label: 'Servicio',
      description: 'Operación ligera para servicios con guardias, accesos, incidencias y bitácora.',
      terms: {
        home: 'Inicio',
        access: 'Accesos',
        service: 'Servicio',
        people: 'Personal autorizado',
        units: 'Áreas',
        notices: 'Avisos operativos',
        incidents: 'Incidentes',
        operators: 'Operadores',
        passes: 'Accesos',
        logbook: 'Bitácora',
        materials: 'Materiales',
        tools: 'Herramientas',
        settings: 'Configuración',
        policy: 'Políticas',
      },
    },
    retailops: {
      label: 'RetailOps',
      description: 'Control operativo para tiendas, clubes, proveedores, contratistas, bitácoras, accesos, incidencias y materiales.',
      terms: null,
    },
  };

  PRESETS.comercio.terms = { ...PRESETS.empresa.terms };
  PRESETS.retailops.terms = { ...PRESETS.empresa.terms };

  const VIEW_TO_TERM = {
    home: 'home',
    accesos: 'access',
    unidades: 'units',
    residentes: 'people',
    personal_recurrente: 'people',
    visitantes_rapidos: 'passes',
    materiales: 'materials',
    materiales_autorizados: 'Materiales autorizados',
    solicitudes_pendientes: 'Solicitudes',
    bitacora_operativa: 'logbook',
    bitacora_hoy: 'Bitácora de hoy',
    incidencias: 'incidents',
    guardias: 'operators',
    comunicados: 'notices',
    herramientas: 'tools',
    autos: 'Autos',
    paqueteria: 'Paquetería',
    perfil: 'settings',
    reglamento: 'policy',
  };

  function key(value) {
    return String(value || 'residencial').trim().toLowerCase() || 'residencial';
  }

  function preset(value) {
    return PRESETS[key(value)] || PRESETS.residencial;
  }

  function labelsFor(value) {
    return preset(value).terms || PRESETS.residencial.terms;
  }

  function resolveTerm(term, mode) {
    const labels = labelsFor(mode);
    return labels[term] || term;
  }

  function viewLabel(view, mode) {
    const labelKey = VIEW_TO_TERM[String(view || '').trim()] || view;
    return labelsFor(mode)[labelKey] || labelKey;
  }

  function apply(root, mode) {
    const scope = root || document;
    const currentMode = key(mode);
    scope.querySelectorAll('[data-os-label]').forEach((el) => {
      const term = el.getAttribute('data-os-label') || '';
      el.textContent = resolveTerm(term, currentMode);
    });
    scope.querySelectorAll('[data-view]').forEach((el) => {
      const view = el.getAttribute('data-view') || '';
      const label = viewLabel(view, currentMode);
      const target = el.querySelector('.mobile-dock-item-label, .mobile-more-item-title, .desktop-more-item-title, [data-os-view-label]');
      if (target) target.textContent = label;
    });
  }

  window.OSGateLabels = {
    presetLabel: (value) => preset(value).label,
    presetDescription: (value) => preset(value).description || '',
    isRetailLike: (value) => ['retailops', 'empresa', 'comercio'].includes(key(value)),
    labelsFor,
    viewLabel,
    apply,
  };
})();
