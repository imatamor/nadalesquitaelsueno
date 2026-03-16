# Sync Strategy

## Objetivo
Definir una forma predecible de sincronizar codigo, plugins, maquetacion y cambios operativos entre el proyecto local, staging y produccion.

## Fuente de verdad
- Git es la fuente de verdad para todo el codigo custom.
- `develop` representa staging.
- `main` representa produccion.
- El servidor nunca debe ser el unico lugar donde exista un cambio de codigo.

## Codigo versionado
Estos cambios deben hacerse en el proyecto local y subir por Git:
- themes propios
- plugins custom
- integraciones externas
- widgets personalizados
- CSS, JS y assets propios
- documentacion tecnica

## Cambios que viven fuera de Git
Estos cambios no vuelven solos al repo y deben tratarse aparte:
- paginas, posts y contenido de WordPress
- settings guardados en base de datos
- configuracion interna de Elementor
- activacion de plugins
- media y uploads
- plugins de terceros instalados manualmente

## Regla para Elementor
- Elementor se usa como herramienta de maquetacion, no como fuente de arquitectura del theme.
- Todo soporte tecnico para Elementor debe vivir en Git.
- Si un layout o template de Elementor se vuelve importante, exportarlo y guardarlo como respaldo.
- Elementor Pro no se sube al repo.

## Flujo recomendado
1. Hacer cambios de codigo en el proyecto local.
2. Commit y push a `develop`.
3. Esperar el deploy automatico a staging.
4. Validar en staging.
5. Si hubo cambios de contenido o maquetacion en Elementor, exportarlos o documentarlos.
6. Solo promover a `main` cuando staging este aprobado.

## Si se cambia algo directo en staging
### Archivos
- copiar los archivos modificados de vuelta al proyecto
- revisar diff
- commitear en Git
- volver a desplegar desde `develop`

### Base de datos
- exportar o documentar el cambio
- no asumir que Git lo conserva
- si el cambio debe repetirse, definir mecanismo de importacion o recreacion

## Plugins de terceros
### Elementor Free
- puede instalarse por admin en staging como paso operativo
- debe documentarse la version instalada
- conviene replicarlo en local para trabajar de forma consistente

### Elementor Pro
- instalacion manual controlada por entorno
- no versionar en el repo

## Checklist operativo
- Antes de cerrar una tarea, confirmar si hubo cambios de codigo, de DB o de ambos.
- Si hubo cambios de codigo, verificar que queden en Git.
- Si hubo cambios en Elementor o DB, documentar export, version o pasos de reproduccion.
- Nunca dejar una solucion importante solo en el droplet.
