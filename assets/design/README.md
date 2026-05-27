# OS Gate Shared Design

Esta carpeta concentra las reglas visuales compartidas entre Admin, Guardia y Residente.

## Diagnóstico

- Admin usa formularios densos, tablas y detalles largos; necesita modales centrados en desktop y paneles amplios solo cuando el contenido lo pide.
- Guardia es mobile-first y operativo; sus formularios suelen ser cortos y no deben ocupar toda la pantalla salvo cámara, QR o flujos largos.
- Residente es mobile-first y más simple; visitas, paquetes, incidencias y consejos deben sentirse como sheets limpios, no pantallas completas con espacios vacíos.

El problema visual venía de mezclar estructuras legacy (`fixed inset-0`, `items-end`, wrappers `min-h-full`) con Modal V2. Además, la clase `os-modal-v2__panel--form` estaba forzando altura completa en móvil, lo que generaba huecos grandes en formularios cortos.

## Contrato

- `assets/css/osgate-modals.css` conserva compatibilidad legacy.
- `assets/design/shared-ui.css` es la capa compartida nueva y se carga después del CSS legacy en los tres dashboards.
- Los formularios cortos usan `os-modal-v2__panel--form` y crecen por contenido.
- Los formularios largos usan `os-modal-v2__panel--full-mobile` explícitamente.
- Las vistas migradas deben usar `os-modal-v2`, `os-form-v2`, `os-field-v2` y `data-close-modal`.

## Próxima migración

Migrar módulos restantes a Modal V2 sin cambiar endpoints ni nombres de campos. Evitar nuevos modales legacy salvo casos temporales.
