# AGENTS.md

## Proyecto
Sitio WordPress de campana "Nada les quita el sueno".

## Branching
- develop = staging
- main = produccion
- nunca trabajar directo en main
- todo cambio debe salir primero por develop

## Infraestructura
- Servidor: DigitalOcean
- Droplet operativo: `Maruri-2.0` (`159.223.141.225`)
- Apache
- Produccion: /var/www/html/maruri/casos/nada_les_quita_el_sueno/prod
- Staging: /var/www/html/maruri/casos/nada_les_quita_el_sueno/staging

## Dominio
- Produccion: https://xn--nadalesquitaelsueo-30b.com
- Staging: https://staging.xn--nadalesquitaelsueo-30b.com

## Reglas
- no modificar wp-config.php desde Git
- no versionar uploads, cache, logs ni backups
- mantener compatibilidad con WordPress y Apache
- preferir cambios minimos y seguros
- toda pregunta de toma de decisiones dirigida al usuario debe hacerse en español
- todo texto visible para usuario debe revisarse por ortografia, gramatica y tono antes de cerrar una tarea; no se permiten errores de redaccion en copies de frontend
- si se modifica theme o plugin, indicar archivos tocados
- no romper el flujo de deploy automatico
- antes de cerrar una tarea, resumir que cambio

## Purpose
Define how agents should collaborate in this project.

## Active Tooling
- Skills:
  - wordpress-router
  - wordpress-theme-template-generator
  - wordpress-agency-dev
  - wordpress-plugin-generator
  - wordpress-plugin-dev
  - elementor-widget-dev
  - code-documentation-standards
  - human-in-the-loop
- Playbooks:
  - ../../playbooks/wordpress-development.md
- Teams:
  - ../../teams/wordpress-agency-team.md
- Templates:
  - ../../templates/wordpress-agency-starter
- Plans:
  - ../../plans/PLAN_TEMPLATE.md

## Default Workflow
1. Understand the objective and affected layers.
2. Consultar Engram al retomar contexto del proyecto o antes de continuar trabajo relacionado con decisiones, hallazgos o tareas previas.
3. Route the task with wordpress-router when scope is broad.
4. Present a plan first for architecture, risky flows or reusable foundations.
5. Request human approval before risky or high-impact changes.
6. Implement in small reversible steps.
7. Document code using code-documentation-standards.
8. Validate behavior in WordPress before closing the task.

## Architecture Rules
- El theme base debe ser WordPress-first y builder-agnostic.
- Elementor puede recibir compatibilidad opcional, pero nunca dependencia del theme.
- Toda logica especifica de campana, integraciones externas y widgets personalizados debe vivir en plugins o modulos del proyecto.
- Evitar wrappers rigidos o decisiones visuales que rompan builders o contenido nativo.

## Sync Strategy
- Git es la fuente de verdad para todo el codigo custom del proyecto.
- Staging es el entorno principal para validacion funcional, integraciones y maquetacion real.
- Produccion no se modifica manualmente.
- Ningun cambio de codigo debe existir solo en staging o produccion: si se toca un archivo en servidor, debe traerse de vuelta al proyecto y commitearse.

## What Goes In Git
- wp-content/themes/maruri
- plugins custom del proyecto
- CSS, JS, PHP y assets propios
- documentacion tecnica y estrategias operativas
- exportables de Elementor cuando se decida conservarlos como respaldo

## What Does Not Sync Automatically With Git
- contenido y configuracion guardados en base de datos
- activacion de plugins
- settings internos de WordPress o Elementor
- uploads y media
- plugins instalados manualmente desde admin si no estan versionados

## Elementor Rules
- Elementor puede instalarse en staging para maquetacion, pero su presencia no define la arquitectura del theme.
- Todo soporte tecnico para Elementor debe vivir en Git como codigo del theme o plugins custom.
- Los layouts importantes hechos en Elementor deben exportarse o documentarse si se quieren reproducir en otros entornos.
- Elementor Pro no debe subirse al repo; su instalacion se maneja de forma controlada por entorno.

## Environment Workflow
1. Desarrollar codigo custom en local o en el workspace versionado.
2. Hacer commit a develop.
3. Dejar que GitHub Actions despliegue a staging.
4. Validar en staging.
5. Exportar o documentar cambios de DB/Elementor cuando sean relevantes.
6. Promover a main solo despues de aprobacion.

## Recovery Rules
- Si se instala o modifica algo manualmente en staging y afecta archivos, esos cambios deben copiarse de vuelta al proyecto, revisarse y commitearse.
- Los cambios de base de datos no se consideran sincronizados por estar en staging; requieren export, documentacion o recreacion controlada.
- No usar staging como fuente unica de verdad para codigo o configuracion critica.

## Memory Rules
- Engram usa `ENGRAM_DATA_DIR` apuntando a `../../memory`.
- La memoria documental del proyecto se guarda en `../../memory`.
- Antes de retomar contexto, revisar Engram para recuperar decisiones, descubrimientos y trabajo previo del proyecto.
- Toda decision, descubrimiento, cambio de configuracion, hito de implementacion o aprendizaje reusable importante debe guardarse en Engram y, cuando aplique, resumirse tambien en `../../memory`.
- Toda decision reusable de arquitectura debe dejar un resumen legible en `../../memory` aunque la persistencia en Engram no se ejecute desde esta sesion.
- El acceso operativo a Engram en este proyecto debe hacerse por CLI cuando este disponible, usando `engram context`, `engram search` y `engram save` desde terminal; no asumir falta de acceso a Engram sin verificar primero esos comandos.
- Al cerrar una tarea con hallazgos importantes, confirmar la persistencia en Engram con una busqueda verificable (`engram search`) en lugar de asumir que el guardado se realizo correctamente.

## Documentation Rules
- Documentar funciones, clases, hooks y bloques no obvios con code-documentation-standards.
- Explicar proposito, uso, parametros relevantes, retornos y notas cuando aplique.
- Evitar comentarios redundantes o decorativos.

## Safety Rules
- Avoid destructive changes without approval.
- Prefer small reversible commits.
- Always document complex logic.
- Require human confirmation before touching production-only deployment logic, payments, checkout, pricing or destructive refactors.

## Operational Access
- Este entorno tiene acceso operativo a DigitalOcean por `doctl`.
- La autenticacion operativa recomendada para `doctl` en este proyecto es la variable de entorno `DIGITALOCEAN_ACCESS_TOKEN`.
- Antes de asumir falta de acceso a infraestructura, probar `doctl account get --access-token $env:DIGITALOCEAN_ACCESS_TOKEN` y `doctl compute droplet list --access-token $env:DIGITALOCEAN_ACCESS_TOKEN`.
- Para entrar al droplet por SSH se puede usar `PuTTY/plink` o `ssh` contra `Maruri-2.0` (`159.223.141.225`) cuando haya credenciales disponibles.
- En el droplet esta disponible `wp-cli` para manejo operativo de recursos de WordPress y verificaciones del entorno.
- No guardar passwords, tokens ni llaves privadas en Git, Engram ni documentos del proyecto.
- Si se formaliza la conexion por variables de entorno, preferir nombres como `MARURI_DROPLET_HOST=159.223.141.225`, `MARURI_DROPLET_NAME=Maruri-2.0` y referencias separadas para usuario o llaves fuera del repo.
- Los directorios operativos del proyecto en el droplet son `/var/www/html/maruri/casos/nada_les_quita_el_sueno/prod` y `/var/www/html/maruri/casos/nada_les_quita_el_sueno/staging`.
- Con ese acceso se pueden hacer revisiones operativas y, cuando el usuario lo apruebe, modificaciones de archivos o base de datos directamente en el droplet, recordando que cualquier cambio de codigo debe volver al repo.
- Para verificaciones locales con PHP, usar `C:\tools\php`.
