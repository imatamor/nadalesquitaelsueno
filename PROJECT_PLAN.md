# Project Plan

## Objective
Implementar la landing one-page de la campana Goodsleep sobre `develop`, usando Elementor, UAE y Smart Slider 3 para maquetacion, y dejando toda la logica reusable de campana en codigo versionado.

## Current Direction
- `Maruri` sigue siendo el theme base reusable y builder-agnostic.
- La landing se edita como una sola pagina en Elementor.
- UAE controla header y footer.
- Smart Slider 3 controla el hero.
- La logica custom vive en el plugin `Goodsleep Elementor`.

## Campaign Build

### 1. Landing shell
- La web funciona como one-page scroll con secciones de alto minimo `100vh`.
- Los anchors principales son:
  - `top`
  - `como-funciona` (completada)
  - `reglas`
  - `historia`
  - `historias`
- Los fondos, overlays y composiciones visuales viven en Elementor salvo cuando el comportamiento deba ser reusable.

### 2. Hero Slider
- Se implementa en Smart Slider 3.
- Debe eliminarse el fade-in de texto por slide para que el texto se perciba estatico y solo cambie el fondo.
- Si la configuracion del plugin no basta, se corrige con CSS/JS puntual.

### 3. Seccion Reglas
- Se construye como bloque HTML en Elementor.
- Usa estilos globales y JS reusable.
- Tiene dos estados:
  - apagado
  - prendido
- El cambio de estado ocurre al entrar al viewport, tras un delay, y se resetea al salir.

### 4. Plugin Goodsleep Elementor
- Se crea en `wp-content/plugins/goodsleep-elementor`.
- Registra:
  - categoria propia de widgets Elementor
  - widgets `Historia Generator` y `Historias List`
  - ajustes de Speechify y Mailjet
  - catalogo configurable de voces y tracks
  - CPT publico para historias
  - endpoints REST propios
  - rutas cortas para historias compartidas

### 5. Historias
- Persistencia con CPT + meta.
- Publicacion automatica tras generacion exitosa.
- Audio guardado en Media Library.
- Compartido por WhatsApp con mensaje + ruta corta propia.
- Votos y favoritos restringidos con cookie + huella ligera.

### 6. Integraciones
- Speechify se usa server-side para generar audio.
- Mailjet se usa por API interna para correo transaccional.
- No se exponen claves en frontend.

## Validation
- El hero no debe animar el texto entre slides.
- La seccion Reglas debe repetir correctamente el ciclo apagado/prendido al entrar y salir del viewport.
- `Historia Generator` debe cubrir formulario, loader y resultado.
- `Historias List` debe soportar filtros, scroll interno, favoritos y votos.
- La siguiente seccion activa del plan es `historias`.
- Toda validacion final se hace en staging sobre `develop`.

## Operational Notes
- Todo cambio manual en staging que afecte archivos debe regresar al repo.
- Los plugins default no deben volver al proyecto.
- La memoria del proyecto debe mantenerse en Engram y `../../memory`.
