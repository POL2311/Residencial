# CONTEXT_OS_GATE_ANALISIS

Documento de contexto técnico y funcional del proyecto ubicado en `/Applications/XAMPP/xamppfiles/htdocs/Residencial`.

Fecha de análisis: 2026-05-23  
Alcance: lectura estática del código, estructura de carpetas, APIs PHP, JavaScript, plantillas HTML y SQL disponible.  
Restricciones respetadas: no se refactorizó código, no se eliminaron archivos, no se crearon migraciones, no se ejecutaron cambios de base de datos.  
Nota de seguridad: se detectaron credenciales y configuración sensible en archivos de configuración/SQL. Este documento no reproduce valores secretos.

## 1. Estructura general del proyecto

### Carpetas principales

| Ruta | Propósito actual |
|---|---|
| `config/` | Configuración de BD, sesión, autenticación, seguridad, perfiles de servicio, modo operativo, helpers residenciales, subida de imágenes, SMTP, recuperación de contraseña. |
| `superadmin/` | Dashboard y módulos de superadministración: residenciales/servicios, usuarios, turnos, incidencias, comunicados, seguridad, reportes y configuración global. |
| `admin_residencial/` | Dashboard del administrador del residencial/cliente: unidades, residentes, guardias, incidencias, comunicados, autos, personal recurrente, visitantes rápidos, materiales, bitácora, herramientas, perfil y reglamento. |
| `guardia/` | Dashboard del guardia/operador: accesos, QR, autos, incidencias, paquetería, personas dentro, materiales autorizados, bitácora, herramientas y perfil. |
| `residente/` | Dashboard del residente: visitas/QR, incidencias, paquetería, autos, pagos, comunicados, servicios, perfil y reglamento. |
| `assets/` | JS global (`assets/js/app-toast.js`), librería QR (`assets/vendor/jsQR.js`), imágenes, sonidos y uploads operativos. |
| `layouts/` | Layout heredado `layouts/dashboard_layout.php`, con menú por rol, no parece ser el mecanismo principal de los dashboards actuales. |
| `errors/` | Página de error HTTP compartida (`errors/http_error.php`). |
| `admin_supervisor/` | Pantalla `admin_supervisor/dashboard_admin_supervisor.php`; el rol existe pero su implementación está incompleta o no integrada al nuevo patrón modular. |

### Archivos raíz importantes

| Archivo | Propósito |
|---|---|
| `index.html` | Landing/comercial pública. |
| `index.php` | Archivo raíz PHP; no se identificó como dashboard principal de roles. |
| `login.php` | Login principal; valida usuario y redirige por rol. |
| `logout.php` | Cierra sesión y redirige a login. |
| `recuperar_password.php` | Vista de recuperación de contraseña. |
| `password_reset_api.php` | API para código de recuperación y cambio de contraseña. |
| `formulario.php` | Formulario operativo/reclutamiento; separado del dashboard principal. |
| `miinvit3_residencial_app.sql` | Dump/base SQL principal con tablas del sistema. |
| `accesos_residentes_dummy.sql` | SQL auxiliar para `accesos_residentes`, usado para accesos vehiculares/residentes por tags. |

### Convención de rutas

El sistema usa PHP tradicional con rutas por archivo. No se encontró un router central tipo MVC.

Patrón principal por rol:

| Rol | Dashboard PHP | Template base | JS controlador | APIs |
|---|---|---|---|---|
| Superadmin | `superadmin/php/dashboard.php` | `superadmin/templates/dashboard.html` | `superadmin/js/dashboard.js` | `superadmin/php/api/*.php` |
| Admin residencial | `admin_residencial/php/dashboard.php` | `admin_residencial/templates/dashboard.html` | `admin_residencial/js/dashboard.js` | `admin_residencial/php/api/*.php` |
| Guardia | `guardia/php/dashboard.php` | `guardia/templates/dashboard.html` | `guardia/js/dashboard.js` | `guardia/php/api/*.php` |
| Residente | `residente/php/dashboard.php` | `residente/templates/dashboard.html` | `residente/js/dashboard.js` | `residente/php/api/*.php` |

Cada dashboard carga vistas HTML parciales desde `templates/views/` y scripts por módulo desde `js/`.

### Separación frontend/backend

- Frontend: HTML parcial por vista en `*/templates/views/*.html` y JavaScript por módulo en `*/js/*.js`.
- Backend/API: PHP por módulo en `*/php/api/*.php`.
- No se identificó framework frontend. El JavaScript es vanilla con `fetch`.
- Los dashboards construyen navegación y vistas desde JS, usando `data-view`, `data-dock-primary-view` y atributos similares.
- Toasts/alerts compartidos: `assets/js/app-toast.js`.

### Archivos de seguridad y bootstrap

| Archivo | Funciones relevantes |
|---|---|
| `config/config.php` | Crea `$pdo`, carga `config/app_security.php`, inicia sesión con `app_ensure_session()`. |
| `config/auth.php` | `is_logged_in()`, `require_login()`, `current_user()`, `require_role(array $roles)`. |
| `config/app_security.php` | `app_json_out()`, `app_abort()`, `app_csrf_token()`, `app_require_write_guard()`, `app_role_home_url()`, manejo de errores y sesión. |
| `superadmin/php/api/_bootstrap.php` | Bootstrap de APIs superadmin: `sa_json_out()`, `sa_current_user()`, `sa_require_csrf()`, helpers de configuración. |
| `admin_residencial/php/api/_operational_bootstrap.php` | Bootstrap admin con validación de rol, residencial, perfil de servicio, modo operativo y módulos. |
| `guardia/php/api/_operational_bootstrap.php` | Bootstrap guardia con validación de asignación, perfil de servicio, modo operativo y módulos. |

## 2. Roles actuales

Los roles están en la tabla `tipos_usuario` y se consultan con `users.tipo_usuario_id`.

### Rol: `super_admin`

Permisos encontrados:

- Crear residenciales/servicios desde `superadmin/php/api/residenciales.php?action=create`.
- Activar/desactivar módulos de un residencial/cliente desde `superadmin/php/api/residenciales.php?action=update_service_profile`.
- Suspender servicio desde `superadmin/php/api/residenciales.php?action=disable_service`.
- Crear usuarios y asignarlos a residenciales desde `superadmin/php/api/usuarios.php`.
- Administrar turnos globales de guardias desde `superadmin/php/api/turnos_guardias.php`.
- Administrar comunicados globales/residenciales desde `superadmin/php/api/comunicados.php`.
- Administrar incidencias globales desde `superadmin/php/api/incidencias.php`.
- Ver reportes desde `superadmin/php/api/reportes.php`.
- Administrar configuración general y servicios globales desde `superadmin/php/api/configuracion.php`.
- Administrar parámetros de seguridad desde `superadmin/php/api/seguridad.php`.

Pantallas:

- `superadmin/templates/views/home.html`
- `superadmin/templates/views/residenciales.html`
- `superadmin/templates/views/usuarios.html`
- `superadmin/templates/views/turnos_guardias.html`
- `superadmin/templates/views/incidencias.html`
- `superadmin/templates/views/comunicados.html`
- `superadmin/templates/views/seguridad.html`
- `superadmin/templates/views/reportes.html`
- `superadmin/templates/views/configuracion.html`

Frontend:

- `superadmin/js/dashboard.js`
- `superadmin/js/home.js`
- `superadmin/js/residenciales.js`
- `superadmin/js/usuarios.js`
- `superadmin/js/turnos_guardias.js`
- `superadmin/js/incidencias.js`
- `superadmin/js/comunicados.js`
- `superadmin/js/seguridad.js`
- `superadmin/js/reportes.js`
- `superadmin/js/configuracion.js`

Validaciones:

- `superadmin/php/dashboard.php` llama `require_role(['super_admin'])`.
- `superadmin/php/api/_bootstrap.php` llama `require_login()` y `require_role(['super_admin'])`.
- Las mutaciones usan CSRF vía `sa_require_csrf()` o `app_require_write_guard()`.

### Rol: `admin_residencial`

Permisos encontrados:

- Operar un residencial/cliente asignado en `usuarios_residenciales`.
- Administrar unidades, residentes, guardias, turnos, incidencias, comunicados, autos, pagos por residente, reglamento y perfil.
- Operar módulos empresariales/operativos cuando están habilitados: personal recurrente, visitantes rápidos, catálogo de materiales, permisos de materiales, solicitudes pendientes, bitácora operativa y herramientas.

Pantallas:

- `admin_residencial/templates/views/home.html`
- `admin_residencial/templates/views/unidades.html`
- `admin_residencial/templates/views/residentes.html`
- `admin_residencial/templates/views/guardias.html`
- `admin_residencial/templates/views/incidencias.html`
- `admin_residencial/templates/views/comunicados.html`
- `admin_residencial/templates/views/autos.html`
- `admin_residencial/templates/views/personal_recurrente.html`
- `admin_residencial/templates/views/visitantes_rapidos.html`
- `admin_residencial/templates/views/materiales.html`
- `admin_residencial/templates/views/solicitudes_pendientes.html`
- `admin_residencial/templates/views/bitacora_operativa.html`
- `admin_residencial/templates/views/perfil.html`
- `admin_residencial/templates/views/reglamento.html`

Endpoints principales:

- `admin_residencial/php/api/contexto.php`
- `admin_residencial/php/api/home.php`
- `admin_residencial/php/api/unidades.php`
- `admin_residencial/php/api/residentes.php`
- `admin_residencial/php/api/guardias.php`
- `admin_residencial/php/api/guardias_turnos.php`
- `admin_residencial/php/api/incidencias.php`
- `admin_residencial/php/api/comunicados.php`
- `admin_residencial/php/api/autos_admin.php`
- `admin_residencial/php/api/pagos_residentes.php`
- `admin_residencial/php/api/personal_recurrente.php`
- `admin_residencial/php/api/visitantes_rapidos.php`
- `admin_residencial/php/api/materiales_catalogo.php`
- `admin_residencial/php/api/permisos_materiales.php`
- `admin_residencial/php/api/bitacora_operativa.php`
- `admin_residencial/php/api/herramientas.php`
- `admin_residencial/php/api/reglamento.php`
- `admin_residencial/php/api/perfil.php`
- `admin_residencial/php/api/modo_operacion.php`

Validaciones:

- `admin_residencial/php/dashboard.php` llama `require_role(['admin_residencial'])`.
- Verifica `service_profile_resolve_residencial_id_for_user()` y `service_profile_require_role_enabled(..., 'admin_residencial')`.
- APIs operativas usan `admin_residencial/php/api/_operational_bootstrap.php`.
- Módulos usan `admin_module_required('modulo')` o `service_profile_api_require_module(...)`.

### Rol: `guardia`

Permisos encontrados:

- Operar accesos por QR/código.
- Registrar entrada/salida de visitas.
- Buscar residentes y registrar accesos directos de residentes.
- Gestionar o consultar autos, incidencias, paquetería, personas dentro, materiales autorizados, bitácora del día y herramientas si el módulo está habilitado.
- Crear solicitudes de materiales desde guardia cuando `materiales` está habilitado.

Pantallas:

- `guardia/templates/views/home.html`
- `guardia/templates/views/accesos.html`
- `guardia/templates/views/autos.html`
- `guardia/templates/views/incidencias.html`
- `guardia/templates/views/paqueteria.html`
- `guardia/templates/views/personas_dentro.html`
- `guardia/templates/views/materiales_autorizados.html`
- `guardia/templates/views/bitacora_hoy.html`
- `guardia/templates/views/herramientas.html`
- `guardia/templates/views/perfil.html`

Endpoints principales:

- `guardia/php/api/contexto.php`
- `guardia/php/api/accesos.php`
- `guardia/php/api/autos.php`
- `guardia/php/api/incidencias.php`
- `guardia/php/api/paqueteria.php`
- `guardia/php/api/materiales.php`
- `guardia/php/api/herramientas.php`
- `guardia/php/api/bitacora_reportes.php`
- `guardia/php/api/notificaciones.php`
- `guardia/php/api/perfil.php`
- `guardia/php/api/reglamento.php`
- `guardia/php/api/unidades.php`
- `guardia/php/api/propietarios.php`

Validaciones:

- `guardia/php/dashboard.php` permite `guardia` y `super_admin`, pero el contexto operativo real depende de asignación/residencial.
- `guardia/php/api/contexto.php` verifica asignación en `usuarios_residenciales`, servicio activo, rol `habilita_guardia`, turno activo con `guardia_schedule_find_active_turn()` y excepciones con `guardia_schedule_find_current_exception()`.
- `guardia/php/api/_operational_bootstrap.php` valida rol, asignación y módulo con `guardia_module_required()`.

### Rol: `residente`

Permisos encontrados:

- Crear/cancelar visitas y generar códigos/QR.
- Consultar comunicados, servicios, pagos y reglamento.
- Registrar incidencias.
- Consultar/confirmar paquetería.
- Administrar autos propios.
- Actualizar perfil y contactos de emergencia.

Pantallas:

- `residente/templates/views/home.html`
- `residente/templates/views/visitas.html`
- `residente/templates/views/incidencias.html`
- `residente/templates/views/paqueteria.html`
- `residente/templates/views/autos.html`
- `residente/templates/views/pagos.html`
- `residente/templates/views/comunicados.html`
- `residente/templates/views/servicios.html`
- `residente/templates/views/perfil.html`
- `residente/templates/views/reglamento.html`
- `residente/templates/views/ver_qr.html`

Endpoints principales:

- `residente/php/api/contexto.php`
- `residente/php/api/home.php`
- `residente/php/api/visitas.php`
- `residente/php/api/incidencias.php`
- `residente/php/api/paqueteria.php`
- `residente/php/api/autos.php`
- `residente/php/api/pagos.php`
- `residente/php/api/comunicados.php`
- `residente/php/api/servicios.php`
- `residente/php/api/perfil.php`
- `residente/php/api/reglamento.php`

Validaciones:

- `residente/php/dashboard.php` llama `require_role(['residente'])`.
- Verifica `service_profile_resolve_residencial_id_for_user()` y `service_profile_require_role_enabled(..., 'residente')`.
- APIs usan `resolve_resident_context()` para validar residencial, unidad y relación residente-unidad.
- Módulos usan `service_profile_api_require_module(..., 'residente', 'modulo')`.

### Rol: `admin_supervisor`

Estado:

- Existe en `tipos_usuario`.
- Existe redirección en `config/app_security.php` hacia `admin_supervisor/dashboard_admin_supervisor.php`.
- Existe referencia en `layouts/dashboard_layout.php`.
- No se encontró un árbol completo `admin_supervisor/php/api`, templates ni JS modular equivalente.

Conclusión: rol parcial/pendiente de confirmar.

## 3. Modelo de empresas/residenciales/servicios

### Entidad principal actual

La entidad tenant/cliente sigue llamándose `residenciales`.

Tabla: `residenciales`

Columnas clave:

- `id`
- `nombre`
- `codigo`
- `tipo`
- `modo_operacion`
- `max_casas`
- `max_guardias`
- `plan_id`
- `estatus_plan`
- `fecha_inicio_plan`
- `fecha_fin_plan`
- `timezone`
- `permite_qr`
- `permite_trabajadores_recurrentes`
- `requiere_placa_vehiculo`
- `requiere_identificacion_visita`
- `activo`

Aunque el nombre es residencial, ya existe un campo `modo_operacion` con valores generalizables.

### Modos de operación

Definidos en `config/operational_mode.php`:

- `residencial`
- `empresa`
- `obra`
- `comercio`
- `servicio`

Funciones importantes:

- `operational_allowed_modes()`
- `operational_normalize_mode()`
- `operational_is_operational_mode()`
- `operational_schema_ensure()`
- `operational_get_mode()`
- `operational_get_context()`
- `operational_user_options()`
- `operational_area_options()`
- `operational_validate_area()`
- `operational_validate_internal_user()`
- `operational_write_bitacora()`

### Servicios/módulos activables

Archivo central: `config/service_profile.php`.

Tabla de configuración: `residenciales_servicio_config`.

Columnas/flags principales:

- `preset_servicio`
- `habilita_admin_operativo`
- `habilita_guardia`
- `habilita_residente`
- `habilita_unidades`
- `habilita_residentes_catalogo`
- `habilita_guardias_catalogo`
- `habilita_guardias_admin_actions`
- `habilita_autos`
- `habilita_visitas_residente`
- `habilita_paqueteria`
- `habilita_pagos`
- `habilita_comunicados`
- `habilita_servicios_directorio`
- `habilita_control_acceso`
- `habilita_incidencias`
- `habilita_personal_recurrente`
- `habilita_visitantes_rapidos`
- `habilita_materiales`
- `habilita_solicitudes_pendientes`
- `habilita_bitacora_operativa`
- `habilita_herramientas`

Funciones importantes:

- `service_profile_defaults($preset)`
- `service_profile_schema_ensure($pdo)`
- `service_profile_seed_defaults($pdo, $residencialId, $residencial)`
- `service_profile_get($pdo, $residencialId)`
- `service_profile_save($pdo, $residencialId, $input)`
- `service_profile_module_catalog()`
- `service_profile_role_module_matrix()`
- `service_profile_role_view_matrix()`
- `service_profile_allowed_views($profile, $role)`
- `service_profile_frontend_payload($pdo, $residencialId, $role)`
- `service_profile_api_require_module($pdo, $residencialId, $role, $module)`
- `service_profile_require_role_enabled($pdo, $residencialId, $role)`
- `service_profile_role_assignable_to_service($profile, $role)`
- `service_profile_role_pending_reason($profile, $role)`

### Cómo se crea una empresa/residencial

Pantalla:

- `superadmin/templates/views/residenciales.html`

JS:

- `superadmin/js/residenciales.js`

API:

- `superadmin/php/api/residenciales.php?action=create`

Tablas afectadas:

- `residenciales`
- `residenciales_servicio_config`

Lógica:

1. Superadmin captura datos del servicio/residencial.
2. `superadmin/php/api/residenciales.php` valida datos básicos.
3. Inserta en `residenciales`.
4. Ejecuta `service_profile_seed_defaults()`.
5. Guarda overrides con `service_profile_save()`.

### Cómo se asignan servicios

Pantalla:

- `superadmin/templates/views/residenciales.html`

API:

- `superadmin/php/api/residenciales.php?action=update_service_profile`

Tablas:

- `residenciales`
- `residenciales_servicio_config`

Lógica:

1. Superadmin elige preset y flags.
2. Backend normaliza `preset_servicio`.
3. Actualiza `residenciales.modo_operacion`.
4. Actualiza flags de `residenciales_servicio_config`.
5. Devuelve perfil sanitizado y módulos apagados automáticamente si hay dependencias.

### Dónde se validan servicios activos

Frontend:

- `admin_residencial/js/dashboard.js` llama `admin_residencial/php/api/contexto.php` y usa `service_profile.allowed_views`.
- `guardia/js/dashboard.js` llama `guardia/php/api/contexto.php` y usa `service_profile.allowed_views` más `can_operate`.
- `residente/js/dashboard.js` llama `residente/php/api/contexto.php` y usa `service_profile.allowed_views`.

Backend:

- `config/service_profile.php`
- `admin_residencial/php/api/_operational_bootstrap.php`
- `guardia/php/api/_operational_bootstrap.php`
- APIs puntuales con `service_profile_api_require_module()`.

## 4. Servicios o módulos actuales

| Módulo/servicio | Descripción funcional | Frontend relacionado | Backend/API relacionado | Tablas relacionadas | Roles | Estado |
|---|---|---|---|---|---|---|
| Login/auth | Autenticación por email/password, sesión y redirección por rol. | `login.php`, `recuperar_password.php` | `login.php`, `logout.php`, `password_reset_api.php`, `config/auth.php`, `config/app_security.php` | `users`, `tipos_usuario`, `password_reset_codes` | Todos | Completo básico |
| Dashboard | Shell por rol, carga dinámica de vistas y contexto. | `*/templates/dashboard.html`, `*/js/dashboard.js` | `*/php/dashboard.php`, `*/php/api/contexto.php` | Varias | Todos | Completo |
| Empresas/residenciales | Alta y administración del tenant actual. | `superadmin/templates/views/residenciales.html`, `superadmin/js/residenciales.js` | `superadmin/php/api/residenciales.php` | `residenciales`, `planes`, `residenciales_servicio_config`, `usuarios_residenciales` | `super_admin` | Completo con acoplamiento residencial |
| Servicios activables | Perfil modular por residencial/cliente. | `superadmin/js/residenciales.js`, dashboards por rol | `config/service_profile.php`, `superadmin/php/api/residenciales.php`, `*/contexto.php` | `residenciales_servicio_config` | `super_admin`, todos consumen | Bastante avanzado |
| Usuarios | Alta, listado, asignaciones, baja lógica. | `superadmin/templates/views/usuarios.html`, `superadmin/js/usuarios.js` | `superadmin/php/api/usuarios.php` | `users`, `tipos_usuario`, `usuarios_residenciales` | `super_admin` | Completo |
| Unidades | Casas/departamentos/locales por residencial. | `admin_residencial/templates/views/unidades.html`, `admin_residencial/js/unidades.js` | `admin_residencial/php/api/unidades.php`, `guardia/php/api/unidades.php` | `unidades` | `admin_residencial`, `guardia` parcial | Completo residencial, generalizable |
| Residentes | Alta, edición, asignación a unidad, bloqueo manual de acceso. | `admin_residencial/templates/views/residentes.html`, `admin_residencial/js/residentes.js` | `admin_residencial/php/api/residentes.php` | `users`, `residentes_unidades`, `usuarios_residenciales`, `unidades` | `admin_residencial` | Completo |
| Guardias | Alta, edición, activación, servicio y turnos. | `admin_residencial/templates/views/guardias.html`, `admin_residencial/js/guardias.js` | `admin_residencial/php/api/guardias.php`, `admin_residencial/php/api/guardias_turnos.php`, `superadmin/php/api/turnos_guardias.php` | `users`, `usuarios_residenciales`, `guardias_turnos`, `guardias_turnos_excepciones` | `admin_residencial`, `super_admin` | Completo |
| Accesos | Escaneo QR/código, historial, entrada/salida, accesos de residente. | `guardia/templates/views/accesos.html`, `guardia/js/accesos.js`, `assets/vendor/jsQR.js` | `guardia/php/api/accesos.php` | `visitas`, `accesos_guardia`, `personas_recurrentes`, `visitantes_rapidos`, `permisos_materiales`, `bitacora_operativa`, `accesos_residentes` | `guardia` | Completo con varias rutas operativas |
| QR visitas | Generación de código de visita por residente y validación por guardia. | `residente/templates/views/visitas.html`, `residente/templates/views/ver_qr.html`, `residente/js/visitas.js`, `guardia/js/accesos.js` | `residente/php/api/visitas.php`, `guardia/php/api/accesos.php` | `visitas`, `accesos_guardia` | `residente`, `guardia` | Completo básico |
| QR operativo | QR para personas recurrentes, visitantes rápidos y permisos de materiales. | `admin_residencial/js/personal_recurrente.js`, `admin_residencial/js/visitantes_rapidos.js`, `admin_residencial/js/materiales.js`, `guardia/js/accesos.js` | `admin_residencial/php/api/personal_recurrente.php`, `admin_residencial/php/api/visitantes_rapidos.php`, `admin_residencial/php/api/permisos_materiales.php`, `guardia/php/api/accesos.php` | `personas_recurrentes`, `visitantes_rapidos`, `permisos_materiales` | `admin_residencial`, `guardia` | En desarrollo avanzado |
| Incidencias | Creación, listado, actualización y cierre de incidencias. | `superadmin/js/incidencias.js`, `admin_residencial/js/incidencias.js`, `guardia/js/incidencias.js`, `residente/js/incidencias.js` | `superadmin/php/api/incidencias.php`, `admin_residencial/php/api/incidencias.php`, `guardia/php/api/incidencias.php`, `residente/php/api/incidencias.php` | `incidencias`, `unidades`, `users`, tablas operativas opcionales | Todos | Completo |
| Comunicados | Comunicados con imagen, prioridad, publicación/expiración. | `admin_residencial/js/comunicados.js`, `superadmin/js/comunicados.js`, `residente/js/comunicados.js` | `admin_residencial/php/api/comunicados.php`, `superadmin/php/api/comunicados.php`, `residente/php/api/comunicados.php`, `config/comunicados_helpers.php` | `comunicados_residenciales`, `archivos_operativos` para imágenes | `admin_residencial`, `super_admin`, `residente` | Completo |
| Pagos | Pagos asociados a residente/unidad; residente consulta. | `residente/js/pagos.js`, `admin_residencial/js/residentes.js` | `residente/php/api/pagos.php`, `admin_residencial/php/api/pagos_residentes.php` | `pagos`, `residentes_unidades`, `unidades`, `users` | `admin_residencial`, `residente` | Parcial y residencial |
| Vehículos/autos | CRUD de autos, placas, tag opcional, consulta de accesos. | `admin_residencial/js/autos.js`, `guardia/js/autos.js`, `residente/js/autos.js` | `admin_residencial/php/api/autos_admin.php`, `admin_residencial/php/api/autos.php`, `admin_residencial/php/api/accesos_residentes.php`, `guardia/php/api/autos.php`, `residente/php/api/autos.php` | `autos`, `accesos_residentes`, `users`, `unidades` | `admin_residencial`, `guardia`, `residente` | Completo con helpers dinámicos |
| Paquetería | Registro por guardia y confirmación por residente. | `guardia/js/paqueteria.js`, `residente/js/paqueteria.js` | `guardia/php/api/paqueteria.php`, `residente/php/api/paqueteria.php` | `paqueteria`, `unidades`, `residentes_unidades`, `users` | `guardia`, `residente` | Completo residencial |
| Servicios/directorio | Directorio de servicios globales/residenciales para residente. | `residente/js/servicios.js`, `superadmin/js/configuracion.js` | `residente/php/api/servicios.php`, `superadmin/php/api/configuracion.php` | `home_servicios_globales`, `home_servicios_residenciales` | `residente`, `super_admin` | Completo básico |
| Reglamentos | Reglamento por residencial. | `admin_residencial/js/reglamento.js`, `residente/js/reglamento.js`, `guardia/php/api/reglamento.php` | `admin_residencial/php/api/reglamento.php`, `residente/php/api/reglamento.php`, `guardia/php/api/reglamento.php` | `reglamentos_residenciales` | `admin_residencial`, `residente`, `guardia` | Completo básico |
| Personal recurrente | Personas autorizadas con QR/PIN, estado dentro/fuera. | `admin_residencial/js/personal_recurrente.js`, `guardia/js/personas_dentro.js` | `admin_residencial/php/api/personal_recurrente.php`, `guardia/php/api/accesos.php` | `personas_recurrentes`, `areas_operativas`, `archivos_operativos`, `bitacora_operativa` | `admin_residencial`, `guardia` | Operativo/en desarrollo avanzado |
| Visitantes rápidos | Pases temporales operativos con QR. | `admin_residencial/js/visitantes_rapidos.js` | `admin_residencial/php/api/visitantes_rapidos.php`, `guardia/php/api/accesos.php` | `visitantes_rapidos`, `areas_operativas`, `bitacora_operativa` | `admin_residencial`, `guardia` | Operativo/en desarrollo |
| Materiales | Catálogo, permisos de entrada/salida, aprobación y ejecución por QR. | `admin_residencial/js/materiales.js`, `admin_residencial/js/solicitudes_pendientes.js`, `guardia/js/materiales_autorizados.js` | `admin_residencial/php/api/materiales_catalogo.php`, `admin_residencial/php/api/permisos_materiales.php`, `guardia/php/api/materiales.php`, `guardia/php/api/accesos.php` | `catalogo_materiales`, `permisos_materiales`, `permisos_materiales_items`, `bitacora_operativa` | `admin_residencial`, `guardia` | Operativo/en desarrollo avanzado |
| Bitácora operativa | Eventos operativos de accesos/materiales/personas y reportes. | `admin_residencial/js/bitacora_operativa.js`, `guardia/js/bitacora_hoy.js` | `admin_residencial/php/api/bitacora_operativa.php`, `guardia/php/api/bitacora_reportes.php`, `guardia/php/api/accesos.php` | `bitacora_operativa`, `archivos_operativos` | `admin_residencial`, `guardia` | Operativo/en desarrollo |
| Herramientas | Catálogo y préstamos/devoluciones con evidencias. | `admin_residencial/js/herramientas.js`, `guardia/js/herramientas.js` | `admin_residencial/php/api/herramientas.php`, `guardia/php/api/herramientas.php` | `catalogo_herramientas`, `prestamos_herramientas`, `archivos_operativos` | `admin_residencial`, `guardia` | En desarrollo; dashboard guardia requiere confirmar navegación |
| Áreas operativas | Catálogo de áreas para modo empresa/obra/comercio/servicio. | Consumido por módulos operativos | `admin_residencial/php/api/areas_operativas.php`, `config/operational_mode.php` | `areas_operativas` | `admin_residencial` | Base operativa |

## 5. Flujos principales del sistema

### Flujo: login

Usuario inicia: cualquier rol.

Pantalla:

- `login.php`

Endpoint/archivo:

- `login.php` con método `POST`.

Tablas:

- `users`
- `tipos_usuario`

Paso a paso:

1. Usuario captura email y contraseña.
2. `login.php` consulta `users` unido a `tipos_usuario`.
3. Valida `is_active`.
4. Valida contraseña con `password_verify($password, $user['password_hash'])`.
5. Guarda en sesión: `user_id`, `user_name`, `user_role`.
6. Genera token CSRF con `app_csrf_token()`.
7. Redirige con `app_role_home_url($role)`.

Errores:

- Usuario no encontrado.
- Contraseña inválida.
- Usuario inactivo.
- Rol sin home definido redirige a `login.php`.

### Flujo: creación de empresa/residencial

Usuario inicia: `super_admin`.

Pantalla:

- `superadmin/templates/views/residenciales.html`

JS:

- `superadmin/js/residenciales.js`

Endpoint:

- `superadmin/php/api/residenciales.php?action=create`

Tablas:

- `residenciales`
- `residenciales_servicio_config`
- `planes` para opciones/relación.

Validaciones:

- Sesión y rol en `superadmin/php/api/_bootstrap.php`.
- CSRF para POST.
- Datos requeridos del residencial/servicio.
- Preset de servicio normalizado por `service_profile_normalize_preset()`.

Respuesta esperada:

- `ok: true`, datos del residencial creado y perfil de servicio.

Errores posibles:

- Faltan campos obligatorios.
- Código duplicado o error SQL.
- Preset inválido normalizado a valor permitido.

### Flujo: activación/desactivación de servicios

Usuario inicia: `super_admin`.

Pantalla:

- `superadmin/templates/views/residenciales.html`

Endpoint:

- `superadmin/php/api/residenciales.php?action=update_service_profile`
- `superadmin/php/api/residenciales.php?action=disable_service`

Tablas:

- `residenciales`
- `residenciales_servicio_config`

Paso a paso:

1. Superadmin abre perfil de servicio.
2. Frontend pide `get_service_profile`.
3. Usuario activa/desactiva flags.
4. Backend guarda flags con `service_profile_save()`.
5. Backend actualiza `residenciales.modo_operacion`.
6. Frontend de cada rol recibirá nuevas `allowed_views` desde su `contexto.php`.

Validaciones:

- CSRF.
- Rol `super_admin`.
- Flags permitidos por `service_profile_all_flags()`.
- Dependencias de módulos en `service_profile_sanitize_flags()`.

Errores:

- Residencial no encontrado.
- Módulos incompatibles/dependencias apagadas.
- Servicio suspendido si `activo=0` o `estatus_plan='suspendido'`.

### Flujo: alta de residente

Usuario inicia: `admin_residencial`.

Pantalla:

- `admin_residencial/templates/views/residentes.html`

JS:

- `admin_residencial/js/residentes.js`

Endpoint:

- `admin_residencial/php/api/residentes.php?action=create`

Tablas:

- `users`
- `tipos_usuario`
- `usuarios_residenciales`
- `residentes_unidades`
- `unidades`

Validaciones:

- Rol `admin_residencial`.
- Módulo `residentes`/`residentes_catalogo` habilitado.
- Residencial asignado con `require_residencial_id()`.
- Unidad pertenece al residencial.
- Email único.
- Password mínimo para creación.

Respuesta esperada:

- `ok: true`, mensaje de creación.

Errores:

- Unidad inválida.
- Email ya registrado.
- Perfil de servicio no habilita residentes.

### Flujo: alta de guardia

Usuario inicia: `admin_residencial`.

Pantalla:

- `admin_residencial/templates/views/guardias.html`

JS:

- `admin_residencial/js/guardias.js`

Endpoint:

- `admin_residencial/php/api/guardias.php?action=create_guardia`

Tablas:

- `users`
- `tipos_usuario`
- `usuarios_residenciales`
- `guardias_turnos` si se configuran turnos aparte.

Validaciones:

- Rol `admin_residencial`.
- Módulo `guardias` habilitado.
- Acciones administrativas de guardias dependen de `habilita_guardias_admin_actions`.
- Email único.
- Rol asignable al servicio vía `service_profile_role_assignable_to_service()`.

Errores:

- Servicio no permite guardias.
- Acciones admin de guardia deshabilitadas.
- Guardia ya asignado o email duplicado.

### Flujo: registro de acceso

Usuario inicia: `guardia`.

Pantalla:

- `guardia/templates/views/accesos.html`

JS:

- `guardia/js/accesos.js`
- `assets/vendor/jsQR.js`

Endpoint:

- `guardia/php/api/accesos.php`

Acciones principales:

- `GET action=buscar&code=...`
- `GET action=buscar_residente&q=...`
- `GET action=hist`
- `POST action=scan`
- `POST action=scan_residente`
- `POST action=confirm_persona_recurrente`
- `POST action=confirm_visitante_rapido`
- `POST action=confirm_permiso_material`
- `GET action=personas_dentro`
- `GET action=materiales_autorizados`
- `GET action=bitacora_hoy`

Tablas:

- `visitas`
- `accesos_guardia`
- `personas_recurrentes`
- `visitantes_rapidos`
- `permisos_materiales`
- `permisos_materiales_items`
- `bitacora_operativa`
- `archivos_operativos`
- `accesos_residentes`

Validaciones:

- Guardia asignado a residencial.
- Rol/módulo `accesos` habilitado.
- Turno activo y sin excepción bloqueante desde `guardia/php/api/contexto.php`.
- Vigencia de visita con `evaluar_visita()`.
- Código operativo decodificado con `decode_operational_code()`.

Errores:

- Código no encontrado.
- QR vencido/cancelado/usado.
- Servicio/módulo deshabilitado.
- Guardia sin turno activo.
- Persona/material/visitante no pertenece al cliente.

### Flujo: generación y uso de QR de visita

Usuario inicia: `residente`.

Pantalla:

- `residente/templates/views/visitas.html`
- `residente/templates/views/ver_qr.html`

JS:

- `residente/js/visitas.js`

Endpoint creación:

- `residente/php/api/visitas.php?action=create`

Endpoint uso:

- `guardia/php/api/accesos.php?action=buscar`
- `guardia/php/api/accesos.php?action=scan`

Tablas:

- `visitas`
- `accesos_guardia`

Lógica:

1. Residente crea visita con nombre, motivo, placa, fecha/hora y uso único.
2. `residente/php/api/visitas.php` genera código con `build_code()`.
3. El guardia escanea o captura código.
4. `guardia/php/api/accesos.php` evalúa vigencia y estado.
5. Al confirmar se registra evento en `accesos_guardia` y se actualiza visita si aplica.

### Flujo: creación de incidencia

Usuarios: `residente`, `guardia`, `admin_residencial`, `super_admin`.

Pantallas:

- `residente/templates/views/incidencias.html`
- `guardia/templates/views/incidencias.html`
- `admin_residencial/templates/views/incidencias.html`
- `superadmin/templates/views/incidencias.html`

Endpoints:

- `residente/php/api/incidencias.php?action=create`
- `guardia/php/api/incidencias.php`
- `admin_residencial/php/api/incidencias.php`
- `superadmin/php/api/incidencias.php`

Tablas:

- `incidencias`
- `unidades`
- `users`
- En modo operativo: `areas_operativas`, `personas_recurrentes`, `visitantes_rapidos`, `permisos_materiales`

Validaciones:

- Módulo `incidencias` habilitado.
- En modo residencial se valida unidad/residente.
- En modo operativo puede haber incidencia general con `area_id`, `persona_recurrente_id`, `visitante_rapido_id` o `permiso_material_id`.
- Prioridad y estado restringidos a catálogos esperados.

### Flujo: creación/envío de comunicado

Usuario inicia: `admin_residencial` o `super_admin`.

Pantallas:

- `admin_residencial/templates/views/comunicados.html`
- `superadmin/templates/views/comunicados.html`

Endpoints:

- `admin_residencial/php/api/comunicados.php`
- `superadmin/php/api/comunicados.php`
- Lectura residente: `residente/php/api/comunicados.php`

Tablas:

- `comunicados_residenciales`
- `archivos_operativos` indirectamente para imágenes procesadas

Validaciones:

- Módulo `comunicados` habilitado para admin/residente.
- `titulo` y `mensaje` requeridos.
- `tipo` permitido: `general`, `mantenimiento`, `seguridad`, `pagos`.
- `prioridad` permitida: `baja`, `media`, `alta`.
- `estado` permitido para admin: `publicado`, `borrador`; archivado por acción `archive`.
- Fechas `fecha_publicacion` y `fecha_expiracion` con formato `YYYY-MM-DD`.

### Flujo: dashboard/contexto

Usuario inicia: cualquier rol autenticado.

Archivos:

- `superadmin/php/dashboard.php`
- `admin_residencial/php/dashboard.php`
- `guardia/php/dashboard.php`
- `residente/php/dashboard.php`

JS:

- `superadmin/js/dashboard.js`
- `admin_residencial/js/dashboard.js`
- `guardia/js/dashboard.js`
- `residente/js/dashboard.js`

Endpoints:

- `superadmin/php/api/contexto.php`
- `admin_residencial/php/api/contexto.php`
- `guardia/php/api/contexto.php`
- `residente/php/api/contexto.php`

Lógica:

1. PHP valida sesión y rol.
2. Renderiza template HTML base.
3. JS carga contexto.
4. JS calcula vistas permitidas desde `service_profile.allowed_views`.
5. JS carga vista HTML parcial y script del módulo.
6. Módulos hacen fetch a sus APIs.

### Flujo: paquetería

Usuario inicia:

- Guardia registra paquete.
- Residente confirma entrega/devolución.

Endpoints:

- `guardia/php/api/paqueteria.php?action=create`
- `guardia/php/api/paqueteria.php?action=update_status`
- `residente/php/api/paqueteria.php?action=confirm_entregado`
- `residente/php/api/paqueteria.php?action=confirm_devuelto`

Tablas:

- `paqueteria`
- `unidades`
- `residentes_unidades`
- `users`

Estado: completo para residencial; para retail se puede reutilizar como entregas internas/proveedores.

### Flujo: materiales

Usuarios:

- Admin crea/aprueba/cancela permisos.
- Guardia crea solicitud o ejecuta permiso por QR.

Endpoints:

- `admin_residencial/php/api/materiales_catalogo.php`
- `admin_residencial/php/api/permisos_materiales.php`
- `guardia/php/api/materiales.php?action=create_solicitud`
- `guardia/php/api/accesos.php?action=confirm_permiso_material`

Tablas:

- `catalogo_materiales`
- `permisos_materiales`
- `permisos_materiales_items`
- `bitacora_operativa`

Estado: muy reutilizable para obra, retail, almacén y proveedores.

## 6. Base de datos

Fuente principal: `miinvit3_residencial_app.sql`.  
Fuente auxiliar: `accesos_residentes_dummy.sql`.  
También hay creación/alter dinámico en `config/operational_mode.php`, `config/service_profile.php`, `config/residencial_helpers.php`, `config/resident_access.php`, `config/comunicados_helpers.php` y `config/smtp_mailer.php`.

### Tabla: `users`

Propósito: usuarios del sistema.

Columnas clave:

- `id`
- `tipo_usuario_id`
- `name`
- `email`
- `telefono`
- `password_hash`
- `is_active`
- `guardia_en_servicio`
- `created_at`

Relaciones:

- `tipo_usuario_id` -> `tipos_usuario.id`
- Se relaciona con `usuarios_residenciales`, `residentes_unidades`, `guardias_turnos`, `pagos`, `paqueteria`, `incidencias`.

Endpoints:

- `login.php`
- `superadmin/php/api/usuarios.php`
- `admin_residencial/php/api/residentes.php`
- `admin_residencial/php/api/guardias.php`
- `guardia/php/api/perfil.php`
- `residente/php/api/perfil.php`

Observaciones:

- Modelo de roles fijo vía `tipos_usuario`.
- Para plataforma modular conviene evolucionar a permisos granulares sin romper este catálogo.

### Tabla: `tipos_usuario`

Propósito: catálogo de roles.

Valores encontrados:

- `super_admin`
- `admin_supervisor`
- `admin_residencial`
- `guardia`
- `residente`

Observaciones:

- `admin_supervisor` existe pero su implementación está pendiente o parcial.

### Tabla: `residenciales`

Propósito: tenant/cliente actual, aunque nombrado como residencial.

Columnas clave:

- `id`, `nombre`, `codigo`, `tipo`, `modo_operacion`, `plan_id`, `estatus_plan`, `activo`
- límites: `max_casas`, `max_guardias`
- operación: `permite_qr`, `permite_trabajadores_recurrentes`, `requiere_placa_vehiculo`, `requiere_identificacion_visita`
- contacto/dirección y fechas de plan.

Relaciones:

- `plan_id` -> `planes.id`
- Referenciada por casi todas las tablas mediante `residencial_id`.

Observaciones:

- Es el candidato natural para renombrarse conceptualmente a `cliente`, `empresa` o `sede`, pero una renombrada física requiere migración amplia.

### Tabla: `residenciales_servicio_config`

Propósito: flags de módulos/roles activables por residencial/cliente.

Columnas clave:

- `residencial_id`
- `preset_servicio`
- todos los flags `habilita_*`.

Relaciones:

- `residencial_id` -> `residenciales.id`

Endpoints:

- `superadmin/php/api/residenciales.php`
- `*/php/api/contexto.php`
- Validaciones en `config/service_profile.php`.

Observaciones:

- Es una base sólida para convertir el producto en SaaS modular.

### Tabla: `usuarios_residenciales`

Propósito: asignación de usuario a residencial/cliente.

Columnas:

- `id`
- `user_id`
- `residencial_id`
- `es_principal`
- `created_at`

Endpoints:

- `superadmin/php/api/usuarios.php`
- `admin_residencial/php/api/residentes.php`
- `admin_residencial/php/api/guardias.php`
- contextos de guardia/residente/admin.

Observaciones:

- Es la tabla actual de membership multi-tenant.

### Tabla: `unidades`

Propósito: unidad física/residencial: casa, departamento, local u otro.

Columnas:

- `id`
- `residencial_id`
- `tipo`
- `clave`
- datos de calle/número/interior/edificio/piso/referencia
- `notas`
- `activo`

Endpoints:

- `admin_residencial/php/api/unidades.php`
- `guardia/php/api/unidades.php`
- APIs de residentes, pagos, incidencias, autos y paquetería.

Observaciones:

- Ya tiene `tipo='local'`, lo que ayuda a retail. El lenguaje sigue muy residencial.

### Tabla: `residentes_unidades`

Propósito: relación residente-usuario con unidad.

Columnas:

- `id`
- `user_id`
- `unidad_id`
- `es_titular`
- `activo`
- `acceso_baneado_manual`
- `acceso_baneo_motivo`
- `acceso_baneado_at`
- `acceso_estado_actualizado_at`

Endpoints:

- `admin_residencial/php/api/residentes.php`
- `residente/php/api/contexto.php`
- `config/resident_access.php`

Observaciones:

- Muy útil como asignación persona-ubicación, pero el nombre está acoplado a residentes.

### Tabla: `visitas`

Propósito: pases de visita generados por residente.

Columnas:

- `id`
- `residencial_id`
- `unidad_id`
- `residente_id`
- `tipo`
- `nombre_visitante`
- `motivo`
- `placa_vehiculo`
- `fecha_visita`
- `hora_inicio`
- `hora_fin`
- `uso_unico`
- `codigo_acceso`
- `estado`
- `notas`

Endpoints:

- `residente/php/api/visitas.php`
- `guardia/php/api/accesos.php`

Observaciones:

- Reutilizable como pase temporal.

### Tabla: `accesos_guardia`

Propósito: bitácora de eventos de acceso tradicionales.

Columnas:

- `id`
- `visita_id`
- `guardia_id`
- `fecha_hora`
- `tipo_evento`
- `resultado`
- `observaciones`
- `origen_acceso`
- `residente_id`
- `unidad_id`

Endpoints:

- `guardia/php/api/accesos.php`

Observaciones:

- Complementa `bitacora_operativa` para flujos nuevos.

### Tabla: `accesos_residentes`

Propósito: accesos vehiculares/residente por auto/tag.

Fuente:

- Creada dinámicamente por `resident_vehicle_access_schema_ensure()` en `config/residencial_helpers.php`.
- Existe auxiliar `accesos_residentes_dummy.sql`.

Columnas:

- `id`
- `residencial_id`
- `residente_id`
- `unidad_id`
- `auto_id`
- `tag_id`
- `lector_check_id`
- `tipo_movimiento`
- `fecha_hora`
- `fuente`
- `metadata_json`

Endpoints:

- `admin_residencial/php/api/accesos_residentes.php`
- `admin_residencial/php/api/autos_admin.php`

Observaciones:

- No aparece como tabla base en el dump principal; depende de schema ensure/helper SQL.

### Tabla: `autos`

Propósito: vehículos.

Columnas:

- `id`
- `propietario_user_id`
- `residencial_id`
- `unidad_id`
- `placas`
- `modelo`
- `color`
- `activo`
- `notas`
- `tag_id` puede agregarse dinámicamente.

Endpoints:

- `admin_residencial/php/api/autos_admin.php`
- `admin_residencial/php/api/autos.php`
- `guardia/php/api/autos.php`
- `residente/php/api/autos.php`

Observaciones:

- Buen módulo para accesos de colaboradores/proveedores con vehículos.

### Tabla: `incidencias`

Propósito: incidencias residenciales y operativas.

Columnas:

- `id`
- `residencial_id`
- `unidad_id`
- `residente_id`
- `guardia_id`
- `area_id`
- `persona_recurrente_id`
- `visitante_rapido_id`
- `permiso_material_id`
- `origen_tipo`
- `tipo`
- `titulo`
- `descripcion`
- `prioridad`
- `estado`

Endpoints:

- `superadmin/php/api/incidencias.php`
- `admin_residencial/php/api/incidencias.php`
- `guardia/php/api/incidencias.php`
- `residente/php/api/incidencias.php`

Observaciones:

- Ya fue extendida hacia operación general.

### Tabla: `comunicados_residenciales`

Propósito: comunicados/avisos.

Columnas:

- `id`
- `residencial_id`
- `titulo`
- `mensaje`
- `imagen_url`
- `tipo`
- `prioridad`
- `fecha_publicacion`
- `fecha_expiracion`
- `visible_para_residentes`
- `estado`
- `creado_por`
- `actualizado_por`

Endpoints:

- `admin_residencial/php/api/comunicados.php`
- `superadmin/php/api/comunicados.php`
- `residente/php/api/comunicados.php`
- `residente/php/api/home.php`

Observaciones:

- Reutilizable como avisos operativos. Nombre de tabla acoplado.

### Tabla: `pagos`

Propósito: pagos por residente/unidad.

Columnas:

- `id`
- `user_id`
- `residencial_id`
- `unidad_id`
- `monto`
- `fecha`
- `metodo`
- `concepto`
- `activo`

Endpoints:

- `admin_residencial/php/api/pagos_residentes.php`
- `residente/php/api/pagos.php`
- `config/resident_access.php` usa pagos para estado de acceso.

Observaciones:

- Módulo residencial; para retail no es prioritario salvo cobranza interna.

### Tabla: `paqueteria`

Propósito: paquetes registrados por caseta/guardia.

Columnas:

- `id`
- `residencial_id`
- `unidad_id`
- `residente_id`
- `guardia_id`
- `empresa`
- `descripcion`
- `codigo_rastreo`
- `estado`
- `notas`

Endpoints:

- `guardia/php/api/paqueteria.php`
- `residente/php/api/paqueteria.php`

Observaciones:

- Puede transformarse en entregas/recepción de proveedores.

### Tablas operativas nuevas/extendidas

| Tabla | Propósito | APIs principales |
|---|---|---|
| `areas_operativas` | Áreas internas por cliente/sede. | `admin_residencial/php/api/areas_operativas.php`, módulos operativos |
| `personas_recurrentes` | Personal autorizado recurrente con QR/PIN y estado dentro/fuera. | `admin_residencial/php/api/personal_recurrente.php`, `guardia/php/api/accesos.php` |
| `visitantes_rapidos` | Pases temporales rápidos con QR. | `admin_residencial/php/api/visitantes_rapidos.php`, `guardia/php/api/accesos.php` |
| `catalogo_materiales` | Materiales/equipo. | `admin_residencial/php/api/materiales_catalogo.php`, `guardia/php/api/materiales.php` |
| `permisos_materiales` | Permisos de entrada/salida de materiales con QR. | `admin_residencial/php/api/permisos_materiales.php`, `guardia/php/api/accesos.php` |
| `permisos_materiales_items` | Detalle de materiales por permiso. | `admin_residencial/php/api/permisos_materiales.php`, `guardia/php/api/materiales.php` |
| `bitacora_operativa` | Eventos operativos generalizados. | `admin_residencial/php/api/bitacora_operativa.php`, `guardia/php/api/accesos.php`, `guardia/php/api/bitacora_reportes.php` |
| `catalogo_herramientas` | Catálogo de herramientas. | `admin_residencial/php/api/herramientas.php`, `guardia/php/api/herramientas.php` |
| `prestamos_herramientas` | Préstamo/devolución de herramientas. | `admin_residencial/php/api/herramientas.php`, `guardia/php/api/herramientas.php` |
| `archivos_operativos` | Evidencias/imágenes operativas. | `config/image_uploads.php`, APIs de comunicados, accesos, herramientas y bitácora |

### Posibles inconsistencias o acoplamientos de base de datos

- `residencial_id` aparece como columna tenant en casi todo el modelo.
- `residenciales` representa tanto cliente como sede/servicio.
- `unidades` tiene semántica de vivienda, aunque admite `local`.
- `residentes_unidades` es una relación persona-ubicación pero está nombrada como residente.
- `comunicados_residenciales`, `reglamentos_residenciales`, `home_servicios_residenciales` y `usuarios_residenciales` acoplan nombres.
- `autos.tag_id` no aparece en el dump principal, pero el código lo agrega/usa dinámicamente.
- `accesos_residentes` no aparece como tabla base en el dump principal; existe helper de creación y SQL auxiliar.
- Hay schema changes en runtime (`ALTER TABLE`/`CREATE TABLE`) dentro de helpers. Para producción modular conviene migraciones controladas.

## 7. Endpoints/API

Las rutas son archivos PHP. La columna "método" indica lo observado por lectura estática.

| Método | Ruta/API | Propósito | Parámetros/acciones | Respuesta esperada | Rol | Tablas | Frontend |
|---|---|---|---|---|---|---|---|
| POST | `login.php` | Login | `email`, `password` | Redirect por rol | Público | `users`, `tipos_usuario` | `login.php` |
| POST | `password_reset_api.php` | Recuperación password | `action=request_reset_code`, `verify_reset_code`, `reset_password_with_code` | JSON `ok` | Público | `password_reset_codes`, `users` | `recuperar_password.php` |
| GET | `superadmin/php/api/contexto.php` | Contexto superadmin | Ninguno | user, config, stats, csrf | `super_admin` | `config_general`, varias | `superadmin/js/dashboard.js` |
| GET/POST | `superadmin/php/api/residenciales.php` | CRUD servicio/residencial y perfil modular | `meta`, `list`, `get_service_profile`, `update_service_profile`, `disable_service`, `create` | JSON `ok`, datos/perfil | `super_admin` | `residenciales`, `planes`, `residenciales_servicio_config`, `usuarios_residenciales` | `superadmin/js/residenciales.js` |
| GET/POST | `superadmin/php/api/usuarios.php` | Usuarios y asignaciones | `meta`, `list_users`, `list_assignments`, `create_user`, `create_user_with_assignment`, `assign_residencial`, `remove_assignment`, `disable_user` | JSON `ok` | `super_admin` | `users`, `tipos_usuario`, `usuarios_residenciales` | `superadmin/js/usuarios.js` |
| GET/POST | `superadmin/php/api/turnos_guardias.php` | Turnos/excepciones globales | `meta`, `list`, `list_exceptions`, `create`, `update`, `toggle_active`, `delete`, `create_exception`, `update_exception`, `toggle_exception`, `delete_exception` | JSON `ok` | `super_admin` | `guardias_turnos`, `guardias_turnos_excepciones`, `users`, `residenciales` | `superadmin/js/turnos_guardias.js` |
| GET | `superadmin/php/api/reportes.php` | Reportes ejecutivos | `period` | series, métricas | `super_admin` | `residenciales`, `users`, `incidencias` | `superadmin/js/reportes.js` |
| GET/POST | `superadmin/php/api/configuracion.php` | Config general y servicios globales | `get`, `save`, `test_smtp`, `save_service`, `delete_service` | JSON `ok` | `super_admin` | `config_general`, `home_servicios_globales` | `superadmin/js/configuracion.js` |
| GET/POST | `superadmin/php/api/seguridad.php` | Config seguridad | `get`, `save` | JSON `ok` | `super_admin` | `config_seguridad` | `superadmin/js/seguridad.js` |
| GET/POST | `superadmin/php/api/incidencias.php` | Incidencias globales | `meta`, `list`, `create`, `update`, `delete` | JSON `ok` | `super_admin` | `incidencias`, `residenciales`, `users`, `unidades` | `superadmin/js/incidencias.js` |
| GET/POST | `superadmin/php/api/comunicados.php` | Comunicados superadmin | Parcial: listar/crear/actualizar/archivar | JSON `ok` | `super_admin` | `comunicados_residenciales` | `superadmin/js/comunicados.js` |
| GET | `admin_residencial/php/api/contexto.php` | Contexto admin | Ninguno | ctx, service_profile, modo_operacion | `admin_residencial` | `users`, `residenciales`, `unidades`, `autos` | `admin_residencial/js/dashboard.js` |
| GET | `admin_residencial/php/api/home.php` | Dashboard admin | Ninguno | métricas/cards | `admin_residencial` | Varias | `admin_residencial/js/home.js` |
| GET/POST | `admin_residencial/php/api/unidades.php` | CRUD unidades | `list`, `create`, `update`, `delete`, `create_inline_for_residente` | JSON `ok` | `admin_residencial` | `unidades` | `admin_residencial/js/unidades.js` |
| GET/POST | `admin_residencial/php/api/residentes.php` | CRUD residentes | `list`, `get`, `create`, `update`, `toggle_active`, `toggle_manual_ban`, `delete` | JSON `ok` | `admin_residencial` | `users`, `residentes_unidades`, `usuarios_residenciales`, `unidades` | `admin_residencial/js/residentes.js` |
| GET/POST | `admin_residencial/php/api/guardias.php` | CRUD guardias | `create_guardia`, `edit_guardia`, `toggle_servicio`, `delete_guardia`, listado | JSON `ok` | `admin_residencial` | `users`, `usuarios_residenciales` | `admin_residencial/js/guardias.js` |
| GET/POST | `admin_residencial/php/api/guardias_turnos.php` | Turnos guardias | `list`, `list_exceptions`, `create`, `update`, `delete`, `create_exception`, `update_exception`, `toggle_exception`, `delete_exception` | JSON `ok` | `admin_residencial` | `guardias_turnos`, `guardias_turnos_excepciones` | `admin_residencial/js/guardias.js` |
| GET/POST | `admin_residencial/php/api/incidencias.php` | Incidencias admin | `meta`, listar, `create`, `update`, `delete` | JSON `ok` | `admin_residencial` | `incidencias`, `unidades`, operativas | `admin_residencial/js/incidencias.js` |
| GET/POST | `admin_residencial/php/api/comunicados.php` | Comunicados admin | GET list, POST save/update, `archive` | JSON `ok` | `admin_residencial` | `comunicados_residenciales` | `admin_residencial/js/comunicados.js` |
| GET/POST | `admin_residencial/php/api/autos_admin.php` | Autos admin | `list_by_resident`, `list_all`, `create`, `update` | JSON `ok` | `admin_residencial` | `autos`, `users`, `unidades` | `admin_residencial/js/autos.js`, `admin_residencial/js/residentes.js` |
| GET | `admin_residencial/php/api/accesos_residentes.php` | Historial accesos vehiculares | filtros por auto/residente | JSON `ok`, items | `admin_residencial` | `accesos_residentes` | `admin_residencial/js/autos.js` |
| GET/POST | `admin_residencial/php/api/pagos_residentes.php` | Pagos por residente | `list`, `create`, `update`, `delete` | JSON `ok` | `admin_residencial` | `pagos`, `users`, `unidades` | `admin_residencial/js/residentes.js` |
| GET/POST | `admin_residencial/php/api/personal_recurrente.php` | Personal recurrente | `meta`, save, `delete`, `reset_pin`, `regenerate_qr` | JSON `ok` | `admin_residencial` | `personas_recurrentes`, `areas_operativas`, `archivos_operativos` | `admin_residencial/js/personal_recurrente.js` |
| GET/POST | `admin_residencial/php/api/visitantes_rapidos.php` | Visitantes rápidos | `meta`, save, `cancel` | JSON `ok` | `admin_residencial` | `visitantes_rapidos`, `areas_operativas` | `admin_residencial/js/visitantes_rapidos.js` |
| GET/POST | `admin_residencial/php/api/materiales_catalogo.php` | Catálogo materiales | GET list, POST save/update, `delete` | JSON `ok` | `admin_residencial` | `catalogo_materiales` | `admin_residencial/js/materiales.js` |
| GET/POST | `admin_residencial/php/api/permisos_materiales.php` | Permisos materiales | GET list/meta, POST save/update, `approve`, `cancel`, `delete` | JSON `ok`, `qr_payload` al crear | `admin_residencial` | `permisos_materiales`, `permisos_materiales_items`, `catalogo_materiales` | `admin_residencial/js/materiales.js`, `admin_residencial/js/solicitudes_pendientes.js` |
| GET | `admin_residencial/php/api/bitacora_operativa.php` | Bitácora operativa | filtros | JSON `ok`, items | `admin_residencial` | `bitacora_operativa`, `archivos_operativos` | `admin_residencial/js/bitacora_operativa.js` |
| GET/POST | `admin_residencial/php/api/herramientas.php` | Herramientas admin | `catalogo`, `create_tool`, `update_tool`, `toggle_tool`, `delete_tool`, `prestamos`, `marcar_devuelto` | JSON `ok` | `admin_residencial` | `catalogo_herramientas`, `prestamos_herramientas` | `admin_residencial/js/herramientas.js` |
| GET/POST | `admin_residencial/php/api/reglamento.php` | Reglamento | listar/guardar | JSON `ok` | `admin_residencial` | `reglamentos_residenciales` | `admin_residencial/js/reglamento.js` |
| GET/POST | `admin_residencial/php/api/perfil.php` | Perfil admin/residencial | acciones de perfil | JSON `ok` | `admin_residencial` | `users`, `residenciales` | `admin_residencial/js/perfil.js` |
| GET/POST | `admin_residencial/php/api/modo_operacion.php` | Modo operación | GET contexto; POST denegado | JSON `ok` o error | `admin_residencial` | `residenciales`, `residenciales_servicio_config` | Dashboard/admin |
| GET | `guardia/php/api/contexto.php` | Contexto guardia | Ninguno | user, can_operate, service_profile, stats | `guardia` | `usuarios_residenciales`, `guardias_turnos`, varias | `guardia/js/dashboard.js` |
| GET/POST | `guardia/php/api/accesos.php` | Accesos/QR | `buscar`, `buscar_residente`, `hist`, `scan`, `scan_residente`, confirmaciones operativas | JSON `ok`, scan data/evento | `guardia` | `visitas`, `accesos_guardia`, operativas | `guardia/js/accesos.js` |
| GET/POST | `guardia/php/api/incidencias.php` | Incidencias guardia | `meta`, `unidades`, `residentes_unidad`, list, `update`, `delete`, create | JSON `ok` | `guardia` | `incidencias`, `unidades`, operativas | `guardia/js/incidencias.js` |
| GET/POST | `guardia/php/api/paqueteria.php` | Paquetería guardia | `unidades`, `residentes`, `list`, `create`, `update_status` | JSON `ok` | `guardia` | `paqueteria`, `unidades`, `users` | `guardia/js/paqueteria.js` |
| GET/POST | `guardia/php/api/materiales.php` | Solicitudes materiales guardia | `meta`, `create_solicitud` | JSON `ok`, `qr_payload` | `guardia` | `catalogo_materiales`, `permisos_materiales`, `permisos_materiales_items` | `guardia/js/materiales_autorizados.js` |
| GET/POST | `guardia/php/api/herramientas.php` | Herramientas guardia | `catalogo`, `unidades`, `residentes_unidad`, `prestamos`, `create_prestamo`, `marcar_devuelto` | JSON `ok` | `guardia` | `catalogo_herramientas`, `prestamos_herramientas`, `archivos_operativos` | `guardia/js/herramientas.js` |
| GET/POST | `guardia/php/api/autos.php` | Autos guardia | listar/crear/actualizar según JS | JSON `ok` | `guardia` | `autos`, `users`, `unidades` | `guardia/js/autos.js` |
| GET/POST | `guardia/php/api/perfil.php` | Perfil guardia | `get`, `update_name`, `update_phone`, `update_email`, password | JSON `ok` | `guardia` | `users` | `guardia/js/perfil.js` |
| GET | `guardia/php/api/notificaciones.php` | Notificaciones guardia | Ninguno | JSON `ok`, items | `guardia` | `residentes_unidades`, `pagos` vía helper | `guardia/js/dashboard.js` |
| GET | `residente/php/api/contexto.php` | Contexto residente | Ninguno | ctx, service_profile, autos, setup | `residente` | `usuarios_residenciales`, `residentes_unidades`, `unidades`, `autos` | `residente/js/dashboard.js` |
| GET | `residente/php/api/home.php` | Home residente | Ninguno | ctx, banners, tips | `residente` | `comunicados_residenciales`, `usuarios_residenciales` | `residente/js/home.js` |
| GET/POST | `residente/php/api/visitas.php` | Visitas/QR | `list`, `create`, `cancel` | JSON `ok`, visita/código | `residente` | `visitas` | `residente/js/visitas.js` |
| GET/POST | `residente/php/api/incidencias.php` | Incidencias residente | `list`, `create` | JSON `ok` | `residente` | `incidencias` | `residente/js/incidencias.js` |
| GET/POST | `residente/php/api/paqueteria.php` | Paquetería residente | `list`, `confirm_entregado`, `confirm_devuelto` | JSON `ok` | `residente` | `paqueteria` | `residente/js/paqueteria.js` |
| GET/POST | `residente/php/api/autos.php` | Autos residente | `list`, `create`, `update`, `delete` | JSON `ok` | `residente` | `autos` | `residente/js/autos.js` |
| GET | `residente/php/api/pagos.php` | Pagos residente | list | JSON `ok` | `residente` | `pagos` | `residente/js/pagos.js` |
| GET | `residente/php/api/comunicados.php` | Comunicados residente | list | JSON `ok` | `residente` | `comunicados_residenciales` | `residente/js/comunicados.js` |
| GET | `residente/php/api/servicios.php` | Servicios/directorio | list | JSON `ok`, items | `residente` | `home_servicios_globales`, `home_servicios_residenciales` | `residente/js/servicios.js` |
| GET/POST | `residente/php/api/perfil.php` | Perfil residente | `get`, `update_name`, `update_phone`, `update_email`, `change_password`, `add_ce`, `update_ce`, `delete_ce` | JSON `ok` | `residente` | `users`, `contactos_emergencia`, contexto | `residente/js/perfil.js` |
| GET | `residente/php/api/reglamento.php` | Reglamento residente | Ninguno | JSON `ok` | `residente` | `reglamentos_residenciales` | `residente/js/reglamento.js` |

## 8. Frontend

### Pantallas principales por rol

Superadmin:

- Dashboard base: `superadmin/templates/dashboard.html`
- Vistas: `superadmin/templates/views/*.html`
- Controlador: `superadmin/js/dashboard.js`

Admin residencial:

- Dashboard base: `admin_residencial/templates/dashboard.html`
- Vistas: `admin_residencial/templates/views/*.html`
- Controlador: `admin_residencial/js/dashboard.js`

Guardia:

- Dashboard base: `guardia/templates/dashboard.html`
- Vistas: `guardia/templates/views/*.html`
- Controlador: `guardia/js/dashboard.js`

Residente:

- Dashboard base: `residente/templates/dashboard.html`
- Vistas: `residente/templates/views/*.html`
- Controlador: `residente/js/dashboard.js`

### Cómo se cargan datos

- Cada `dashboard.js` carga primero su `contexto.php`.
- Cada vista tiene un JS específico que usa `fetch()` contra `php/api/*.php`.
- Los módulos normalmente exponen funciones `init` o registran controladores en objetos globales por rol.
- Ejemplos:
  - `window.AdminResidencialDashboard`
  - `window.GuardiaViews`
  - `window.ResidenteDashboard`
  - `window.SuperadminDashboard`

### Modales

- No se detectó framework modal central.
- Cada módulo maneja sus modales/formularios en su JS específico.
- Ejemplos: residentes, comunicados, materiales, visitantes rápidos y herramientas tienen lógica propia en sus archivos `js/*.js`.

### Alerts/toasts

- Archivo global: `assets/js/app-toast.js`.
- Los módulos usan mensajes JSON `message`/`error` y los pintan como toast/alert.

### Ocultar/mostrar módulos

Backend:

- `config/service_profile.php` calcula `allowed_views`.

Frontend:

- `admin_residencial/js/dashboard.js` oculta elementos de menú/dock con base en `service_profile.allowed_views`.
- `guardia/js/dashboard.js` además puede mostrar estado `activation_pending` si `can_operate=false`.
- `residente/js/dashboard.js` filtra `inlineViews` y navegación según `allowed_views`.

### Dónde se construye el menú/footer/dock

- En templates base `*/templates/dashboard.html`.
- Control lógico en `*/js/dashboard.js`.
- Atributos relevantes:
  - `data-view`
  - `data-dock-primary-view`
  - `data-dock-secondary`

## 9. Lógica reutilizable para plataforma modular RetailOps

| Actual | Reutilización sugerida | Comentario |
|---|---|---|
| `residenciales` | Cliente, empresa, sede o tienda | Mantener físicamente al inicio, renombrar visualmente por modo. |
| `usuarios_residenciales` | Usuarios asignados a cliente/sede | Es una membership multi-tenant usable. |
| `unidades` | Áreas, locales, departamentos, sucursales internas | Requiere alias visual y quizá nuevo modelo a futuro. |
| `residentes_unidades` | Personas autorizadas asignadas a área/sede | Reutilizable con nombre visual de colaboradores/personas autorizadas. |
| `guardia` | Operador de seguridad | Directamente reutilizable. |
| `visitas` | Pases temporales | Directamente reutilizable. |
| `visitantes_rapidos` | Visitas/proveedores temporales | Muy relevante para retail/corporativo. |
| `personas_recurrentes` | Proveedores/contratistas/colaboradores recurrentes | Muy relevante para retail/industrial. |
| `incidencias` | Incidentes operativos | Directamente reutilizable. |
| `comunicados_residenciales` | Avisos operativos | Reutilizable con alias visual. |
| `permisos_materiales` | Entradas/salidas de mercancía, equipo o herramientas | Muy relevante para tiendas, obra y almacenes. |
| `bitacora_operativa` | Bitácora general de operación | Base de auditoría para plataforma modular. |
| `residenciales_servicio_config` | Módulos por empresa/cliente | Base fuerte para SaaS modular. |
| `guardias_turnos` | Turnos de operadores | Reutilizable. |
| `archivos_operativos` | Evidencias | Reutilizable. |

## 10. Acoplamientos fuertes al concepto residencial

| Lugar | Acoplamiento | Recomendación |
|---|---|---|
| Tabla `residenciales` | Tenant llamado residencial | Mantener por ahora; crear alias visual `cliente/sede`; renombrar físico después con migración. |
| Columna `residencial_id` | Tenant key en casi todas las tablas | Mantener por ahora; introducir capa de servicio/repository o alias `tenant_id` en nueva arquitectura. |
| Carpeta `admin_residencial/` | Rol y rutas nombradas como residencial | Mantener para no romper rutas; alias visual como "Admin operativo". |
| Carpeta `residente/` | Rol final nombrado residente | Para RetailOps ocultar/desactivar `residente` o renombrar visualmente a "persona autorizada". |
| Tabla `residentes_unidades` | Relación residente-unidad | Generalizar después a `personas_unidades` o `usuarios_ubicaciones`. |
| Tabla `unidades` | Casas/departamentos | Crear alias visual "áreas/locales/sucursales"; migrar después si hace falta. |
| Tabla `comunicados_residenciales` | Comunicados vecinales | Alias visual "avisos operativos"; renombrar después. |
| Tabla `reglamentos_residenciales` | Reglamento residencial | Alias visual "políticas/procedimientos". |
| Tabla `home_servicios_residenciales` | Servicios residenciales | Mantener para residencial; en retail podría ser "directorio/proveedores". |
| Módulo `pagos` | Pagos de residente/mantenimiento | Mantener para vertical residencial; no priorizar en RetailOps demo. |
| Textos UI | "residencial", "residente", "unidad", "casa", "departamento", "caseta" | Crear diccionario de labels por `modo_operacion`. |
| Helpers `require_residencial_id()`, `resolve_resident_context()` | Nombres residenciales | Mantener internamente; crear wrappers `require_tenant_id()`/`resolve_person_context()` después. |

Estrategia recomendada:

1. Corto plazo: alias visual por `modo_operacion`.
2. Mediano plazo: capa conceptual `tenant/site/person/module` encima de tablas actuales.
3. Largo plazo: migración física a tablas neutrales.

## 11. Propuesta de evolución sin tocar código

### Objetivo: OS Gate Plataforma Modular

Verticales:

- Residencial
- RetailOps
- Corporativo
- Industrial/Obra

### Arquitectura sugerida

Mantener el sistema actual como núcleo operativo y agregar una capa de producto modular:

- Tenant: empresa/cliente.
- Sede: residencial, tienda, corporativo, obra o planta.
- Módulos: flags actuales y futuros.
- Roles: roles actuales más permisos granulares.
- Personas: residentes, colaboradores, proveedores, contratistas, visitantes.
- Operación: accesos, bitácora, incidencias, materiales, herramientas, rondines y órdenes.

### Tablas nuevas recomendadas

| Tabla nueva | Propósito | Relación con actual |
|---|---|---|
| `clientes` o `empresas` | Agrupar razón social/cliente comercial. | Podría contener varias `residenciales` actuales como sedes. |
| `sedes` | Sustituir conceptualmente `residenciales`. | Migración futura desde `residenciales`. |
| `modulos` | Catálogo formal de módulos. | Formaliza flags `habilita_*`. |
| `planes_modulos` | Módulos incluidos por plan. | Complementa `planes`. |
| `cliente_modulos` o `sede_modulos` | Activación por cliente/sede con fechas/estado. | Evoluciona `residenciales_servicio_config`. |
| `roles` y `permisos` | Permisos granulares. | Evoluciona `tipos_usuario`. |
| `rol_permisos` | Matriz permisos. | Sustituye validaciones fijas por rol. |
| `usuarios_sedes` | Membership neutral. | Evoluciona `usuarios_residenciales`. |
| `personas` | Personas autorizadas internas/externas. | Unifica residentes, recurrentes y visitantes frecuentes. |
| `personas_sedes` | Persona asignada a sede/área. | Evoluciona `residentes_unidades`. |
| `proveedores` | Empresas proveedoras/contratistas. | Relevante retail/industrial. |
| `contratistas` | Personal externo agrupado. | Puede mapear a `personas_recurrentes`. |
| `ordenes_servicio` | Solicitudes y seguimiento de trabajo. | Nuevo módulo clave para corporativo/retail. |
| `rondines` | Definición de rondines. | Nuevo módulo guardias. |
| `puntos_rondin` | Puntos/checkpoints. | Nuevo módulo guardias. |
| `rondin_checks` | Evidencia/check del rondín. | Conecta con `archivos_operativos`. |
| `dispositivos_acceso` | Lectores, torniquetes, plumas, QR scanners. | Evoluciona `accesos_residentes.lector_check_id`. |
| `eventos_dispositivo` | Eventos de hardware externo. | Base para integraciones. |

### Módulos reutilizables inmediatamente

- Login/auth.
- Usuarios/asignaciones.
- Servicios activables.
- Guardias/operadores.
- Turnos.
- Accesos QR.
- Incidencias.
- Comunicados/avisos.
- Personal recurrente.
- Visitantes rápidos.
- Materiales/permisos.
- Bitácora operativa.
- Herramientas.
- Evidencias.

### Módulos nuevos necesarios

- Rondines con checkpoints.
- Órdenes de servicio.
- Proveedores/contratistas formalizados.
- Catálogos por vertical.
- Integración con dispositivos físicos.
- Auditoría centralizada de cambios administrativos.
- Reportes operativos por SLA/tienda/sede.
- Reglas de acceso por horarios, áreas, proveedor y tipo de pase.

### Endpoints que se podrían extender

| Endpoint actual | Extensión sugerida |
|---|---|
| `superadmin/php/api/residenciales.php` | Convertir "residencial" en "sede/servicio" visual; agregar relación futura a `clientes`. |
| `superadmin/php/api/usuarios.php` | Permisos granulares y asignación por sede/módulo. |
| `guardia/php/api/accesos.php` | Unificar tipos de pase y eventos de dispositivo. |
| `admin_residencial/php/api/incidencias.php` | SLA, categorías por vertical y responsables. |
| `admin_residencial/php/api/permisos_materiales.php` | Convertir a órdenes de movimiento de mercancía/equipo. |
| `guardia/php/api/bitacora_reportes.php` | Base para bitácora operacional general. |
| `admin_residencial/php/api/herramientas.php` | Inventario/equipo asignable por tienda/área. |

### Pantallas que se podrían adaptar

| Pantalla actual | Adaptación RetailOps |
|---|---|
| `superadmin/templates/views/residenciales.html` | Clientes/Sedes/Servicios. |
| `admin_residencial/templates/views/home.html` | Dashboard operativo de sede. |
| `admin_residencial/templates/views/unidades.html` | Áreas/zonas/departamentos. |
| `admin_residencial/templates/views/residentes.html` | Personas autorizadas/colaboradores. |
| `admin_residencial/templates/views/guardias.html` | Operadores de seguridad. |
| `admin_residencial/templates/views/incidencias.html` | Incidentes operativos. |
| `admin_residencial/templates/views/comunicados.html` | Avisos operativos. |
| `admin_residencial/templates/views/materiales.html` | Entradas/salidas de materiales/mercancía. |
| `admin_residencial/templates/views/bitacora_operativa.html` | Bitácora de seguridad y operación. |
| `guardia/templates/views/accesos.html` | Control de accesos tienda/sede. |
| `guardia/templates/views/herramientas.html` | Préstamo de equipo. |

### Riesgos técnicos

- Alto acoplamiento semántico a `residencial_id`.
- Validaciones y SQL distribuidos en muchos archivos PHP sin capa de servicio central.
- Algunos schemas se crean/alteran en runtime; para producción conviene migraciones versionadas.
- Módulos frontend tienen lógica propia y duplicada para modales, fetch y errores.
- Roles fijos en `tipos_usuario`; faltan permisos granulares.
- `admin_supervisor` existe pero está incompleto.
- Algunas tablas dinámicas/columnas (`accesos_residentes`, `autos.tag_id`) no están completamente reflejadas en el dump principal.
- Datos sensibles en config/dump deben salir a variables de entorno o archivo no versionado.

### Camino corto para demo empresarial tipo Walmart/Sam's

Sin migraciones:

1. Crear un residencial/servicio con `modo_operacion='comercio'` o `empresa`.
2. Activar:
   - `habilita_admin_operativo`
   - `habilita_guardia`
   - `habilita_control_acceso`
   - `habilita_incidencias`
   - `habilita_personal_recurrente`
   - `habilita_visitantes_rapidos`
   - `habilita_materiales`
   - `habilita_solicitudes_pendientes`
   - `habilita_bitacora_operativa`
   - `habilita_herramientas`
3. Ocultar o no vender:
   - `habilita_residente`
   - `habilita_pagos`
   - `habilita_servicios_directorio`, salvo directorio de proveedores.
4. Usar alias visuales:
   - Residencial -> Sede/Tienda
   - Guardia -> Operador
   - Residente -> Persona autorizada
   - Unidad -> Área/Departamento
   - Comunicado -> Aviso operativo
5. Preparar datos demo:
   - Sede "Walmart Universidad" o "Sam's Club Demo".
   - Áreas: Recibo, Almacén, Piso de venta, Seguridad, Andén.
   - Operadores: guardias por turno.
   - Proveedores recurrentes.
   - Visitantes temporales.
   - Permisos de entrada/salida de material.
   - Incidencias y bitácora del día.

## 12. Resumen ejecutivo

### Cómo está construido actualmente

El sistema es una aplicación PHP tradicional con dashboards separados por rol, APIs por archivo y frontend vanilla JavaScript. La entidad tenant principal se llama `residenciales`, pero ya existe una capa importante para operación modular mediante `modo_operacion` y `residenciales_servicio_config`.

### Qué módulos ya existen

Ya existen módulos sólidos para:

- Autenticación.
- Superadmin.
- Administración de residenciales/servicios.
- Usuarios y asignaciones.
- Unidades.
- Residentes.
- Guardias y turnos.
- Accesos QR.
- Incidencias.
- Comunicados.
- Autos.
- Paquetería.
- Pagos.
- Directorio de servicios.
- Personal recurrente.
- Visitantes rápidos.
- Materiales/permisos.
- Bitácora operativa.
- Herramientas.

### Qué tan listo está para servicios activables

El sistema está bastante avanzado para servicios activables. `config/service_profile.php` centraliza flags, presets, módulos, roles permitidos y vistas permitidas. Los dashboards consumen `service_profile.allowed_views` y varias APIs validan módulos con `service_profile_api_require_module()` o helpers equivalentes.

Punto pendiente: formalizar esta capa como catálogo de módulos/permisos persistente, y mover cambios de schema runtime a migraciones controladas.

### Qué habría que cambiar para venderlo como plataforma modular

Cambios prioritarios:

- Crear alias visuales por `modo_operacion`.
- Generalizar textos residenciales en UI.
- Introducir concepto comercial `cliente/sede` encima de `residenciales`.
- Formalizar módulos y permisos.
- Separar vertical residencial de módulos operativos.
- Crear rondines y órdenes de servicio.
- Mejorar reportes operativos.
- Proteger secretos y crear migraciones versionadas.

### Qué sería más rápido construir para demo Walmart/Sam's

Lo más rápido es usar el sistema actual en modo `comercio` o `empresa`, activar módulos operativos ya existentes y presentar:

- Control de acceso por QR.
- Operadores de seguridad.
- Proveedores/personas recurrentes.
- Visitantes temporales.
- Permisos de materiales.
- Incidencias operativas.
- Bitácora del día.
- Herramientas/evidencias.

Esto evita migraciones iniciales y aprovecha lo ya construido. El trabajo principal para demo sería de naming/UX, seed de datos y configuración de servicios desde superadmin.
