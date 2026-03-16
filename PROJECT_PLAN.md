# Project Plan

## Objective
Pasar de una base tecnica inicial del proyecto a una implementacion completa de la campana, manteniendo una separacion clara entre theme base reusable, logica especifica de campana y cambios operativos de WordPress.

## Diagnosis
- Ya existe una base tecnica funcional en `wp-content/themes/maruri`.
- El theme resuelve layout base, assets, branding simple y compatibilidad opcional con Elementor.
- No existen aun plugins custom de campana.
- La documentacion de arquitectura y sincronizacion ya define que la logica especifica no debe vivir en el theme.
- El repositorio aun tiene ruido en Git por borrados pendientes de themes por defecto.
- No hay todavia contexto funcional completo de negocio, flujos de usuario ni integraciones cerradas en la documentacion del repo.

## Strategy
1. Normalizar el estado del repositorio y decidir explicitamente si los themes por defecto borrados se van a eliminar del repo o restaurar.
2. Consolidar la documentacion base del proyecto para que Codex y Engram partan de contexto real y no de plantillas.
3. Validar el alcance funcional de la campana: paginas, contenidos, formularios, audio, integraciones, ranking, widgets y necesidades de Elementor.
4. Mantener `Maruri` como theme base reusable y mover la logica de campana a plugins o modulos dedicados.
5. Implementar primero la infraestructura tecnica minima de campana y luego avanzar por bloques funcionales pequenos y reversibles.
6. Validar cada cambio en staging sobre `develop` antes de pensar en promocion a `main`.

## Workstreams

### 1. Higiene del repositorio
- Resolver el destino de `twentytwentythree` y `twentytwentyfour`.
- Confirmar si `wp-content/themes/maruri/screenshot.jpg` debe versionarse o descartarse.
- Mantener el repo enfocado en codigo custom y documentacion tecnica.

### 2. Theme base
- Mantener `Maruri` como theme clasico, liviano y agnostico del builder.
- Mejorar solo lo que aporte reutilizacion real: layout base, opciones globales, wrappers, estilos utilitarios y templates nativos.

### 3. Logica de campana
- Crear un plugin custom del proyecto para funcionalidades especificas.
- Posibles frentes ya sugeridos por la memoria existente:
  - Speechify
  - submissions
  - ranking
  - widgets custom

### 4. Maquetacion y contenido
- Definir que vistas viven como templates del theme y cuales se maquetan con Elementor.
- Documentar o exportar cualquier layout importante creado en Elementor.

### 5. Operacion y despliegue
- Trabajar siempre sobre `develop`.
- Validar en staging desplegado automaticamente.
- Documentar cualquier cambio de DB, plugin manual o Elementor que no quede representado en Git.

## Risks
- Mezclar logica de campana dentro del theme puede volverlo rigido y dificil de reutilizar.
- Dejar cambios solo en staging o en la base de datos rompe la estrategia de fuente de verdad.
- El estado Git sucio puede contaminar commits futuros si no se separa el trabajo del ruido existente.
- Si Elementor se usa sin documentacion/export, luego cuesta reproducir cambios entre entornos.

## Validation
- El repositorio debe quedar con documentacion base util y contexto tecnico real.
- El theme `Maruri` debe seguir funcionando como base independiente.
- Toda nueva funcionalidad especifica de campana debe tener destino tecnico claro antes de implementarse.
- Los cambios deben poder viajar por `develop` hacia staging sin depender de ajustes manuales en produccion.
