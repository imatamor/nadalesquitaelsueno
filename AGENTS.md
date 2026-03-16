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
  - C:\Users\IMatamoros\OneDrive - MARURI ECUADOR S.A\Maruri\Codex\playbooks\wordpress-development.md
- Teams:
  - C:\Users\IMatamoros\OneDrive - MARURI ECUADOR S.A\Maruri\Codex\teams\wordpress-agency-team.md
- Templates:
  - C:\Users\IMatamoros\OneDrive - MARURI ECUADOR S.A\Maruri\Codex\templates\wordpress-agency-starter
- Plans:
  - C:\Users\IMatamoros\OneDrive - MARURI ECUADOR S.A\Maruri\Codex\plans\PLAN_TEMPLATE.md

## Default Workflow
1. Understand the objective and affected layers.
2. Route the task with wordpress-router when scope is broad.
3. Present a plan first for architecture, risky flows or reusable foundations.
4. Request human approval before risky or high-impact changes.
5. Implement in small reversible steps.
6. Document code using code-documentation-standards.
7. Validate behavior in WordPress before closing the task.

## Architecture Rules
- El theme base debe ser WordPress-first y builder-agnostic.
- Elementor puede recibir compatibilidad opcional, pero nunca dependencia del theme.
- Toda logica especifica de campana, integraciones externas y widgets personalizados debe vivir en plugins o modulos del proyecto.
- Evitar wrappers rigidos o decisiones visuales que rompan builders o contenido nativo.

## Memory Rules
- Engram vive en C:\Users\IMatamoros\.engram.
- La memoria documental del proyecto se guarda en C:\Users\IMatamoros\OneDrive - MARURI ECUADOR S.A\Maruri\Codex\memory.
- Toda decision reusable de arquitectura debe dejar un resumen legible en Codex\memory aunque la persistencia en Engram no se ejecute desde esta sesion.

## Documentation Rules
- Documentar funciones, clases, hooks y bloques no obvios con code-documentation-standards.
- Explicar proposito, uso, parametros relevantes, retornos y notas cuando aplique.
- Evitar comentarios redundantes o decorativos.

## Safety Rules
- Avoid destructive changes without approval.
- Prefer small reversible commits.
- Always document complex logic.
- Require human confirmation before touching production-only deployment logic, payments, checkout, pricing or destructive refactors.
