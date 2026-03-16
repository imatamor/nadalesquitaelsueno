# Nada les quita el sueno

Proyecto WordPress de campana para "Nada les quita el sueno".

## Estado actual
- El repositorio contiene una instalacion WordPress completa.
- El codigo custom principal vive en `wp-content/themes/maruri`.
- No existen plugins custom del proyecto todavia.
- `develop` representa staging y `main` representa produccion.

## Documentacion base
- `AGENTS.md`: reglas operativas, arquitectura y sincronizacion.
- `PROJECT_CONTEXT.md`: contexto real del proyecto.
- `PROJECT_PLAN.md`: plan operativo de continuidad.
- `docs/sync-strategy.md`: estrategia de trabajo entre local, staging y produccion.

## Nota operativa
- El repositorio tiene cambios pendientes ligados al borrado de themes por defecto (`twentytwentythree` y `twentytwentyfour`).
- Esos cambios deben decidirse explicitamente antes de cerrar una limpieza final de Git o preparar deploy.
