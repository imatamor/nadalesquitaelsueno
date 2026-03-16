# AGENTS.md

## Proyecto
Sitio WordPress de campaña "Nada les quita el sueño".

## Branching
- develop = staging
- main = producción
- nunca trabajar directo en main
- todo cambio debe salir primero por develop

## Infraestructura
- Servidor: DigitalOcean
- Apache
- Producción: /var/www/html/maruri/casos/nada_les_quita_el_sueno/prod
- Staging: /var/www/html/maruri/casos/nada_les_quita_el_sueno/staging

## Dominio
- Producción: https://xn--nadalesquitaelsueo-30b.com
- Staging: https://staging.xn--nadalesquitaelsueo-30b.com

## Reglas
- no modificar wp-config.php desde Git
- no versionar uploads, cache, logs ni backups
- mantener compatibilidad con WordPress y Apache
- preferir cambios mínimos y seguros
- si se modifica theme o plugin, indicar archivos tocados
- no romper el flujo de deploy automático
- antes de cerrar una tarea, resumir qué cambió
