# Project Context

## Client
Maruri / campana "Nada les quita el sueno".

## Objective
Construir y mantener el sitio WordPress de la campana sobre una base tecnica reusable, segura y compatible con el flujo de despliegue automatico del proyecto.

## Stack
- WordPress core completo versionado en el repo
- Theme custom clasico `Maruri`
- Compatibilidad opcional con Elementor
- GitHub Actions para deploy
- Apache en DigitalOcean

## Custom Code
- Theme principal: `wp-content/themes/maruri`
- Modulos actuales del theme:
  - `functions.php`: bootstrap del theme
  - `inc/setup.php`: soportes base, menus y sidebars
  - `inc/assets.php`: carga de CSS, JS y snippets globales
  - `inc/helpers.php`: helpers de branding y opciones
  - `inc/builder-compat.php`: compatibilidad opcional con builders
  - `inc/admin/options-page.php`: opciones globales del theme

## Architecture Direction
- El theme debe ser WordPress-first y builder-agnostic.
- Elementor puede usarse para maquetacion, pero no define la arquitectura.
- Toda logica especifica de campana, integraciones externas, formularios, ranking o widgets custom debe vivir en plugins o modulos del proyecto, no en el theme base.
- El theme base se orienta a branding, layout global y utilidades compartidas.

## Infrastructure
- Staging: `develop`
- Produccion: `main`
- Servidor: DigitalOcean con Apache
- Produccion: `/var/www/html/maruri/casos/nada_les_quita_el_sueno/prod`
- Staging: `/var/www/html/maruri/casos/nada_les_quita_el_sueno/staging`

## Constraints
- No modificar `wp-config.php` desde Git.
- No versionar uploads, cache, logs ni backups.
- Mantener compatibilidad con WordPress y Apache.
- No romper el flujo de deploy automatico.
- Produccion no se modifica manualmente.
- Los cambios de DB o de Elementor no se sincronizan solos con Git y deben exportarse o documentarse.

## Current Repository Reality
- El repositorio incluye WordPress core, no solo codigo custom.
- No hay plugins custom implementados todavia.
- `PROJECT_CONTEXT.md` y `PROJECT_PLAN.md` nacieron como plantilla y fueron completados despues del arranque tecnico inicial.
- El estado Git actual incluye borrados pendientes de `twentytwentythree` y `twentytwentyfour`; esa decision aun debe normalizarse.

## Memory / Codex
- Engram esta configurado en Codex y usa `ENGRAM_DATA_DIR` apuntando a `../../memory`.
- La base observada en esta maquina es `Codex/memory/engram.db`.
