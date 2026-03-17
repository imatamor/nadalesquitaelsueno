# Goodsleep Reglas Snippet

Pega este bloque en un `HTML widget` dentro de la seccion `Reglas` en Elementor:

```html
<section id="reglas" class="goodsleep-rules-scene goodsleep-section goodsleep-anchor-offset">
  <div class="goodsleep-rules-scene__bg goodsleep-rules-scene__bg--off" aria-hidden="true"></div>
  <div class="goodsleep-rules-scene__bg goodsleep-rules-scene__bg--on" aria-hidden="true"></div>
  <div class="goodsleep-rules-scene__overlay" aria-hidden="true"></div>

  <div class="goodsleep-rules-scene__content">
    <h2 class="goodsleep-rules-scene__title">Respeta las reglas al publicar</h2>
    <p class="goodsleep-rules-scene__copy">
      No hay temas tabu: expresate en 500 caracteres. Evita incluir datos personales, como numeros de telefono o numeros de cedula.
      Cuida la ortografia, no agregues errores intencionales y recuerda que la historia debe poder publicarse.
    </p>
    <a class="goodsleep-rules-scene__cta" href="#historia">Escribe tu historia</a>
  </div>
</section>
```

Notas:
- No insertes las imagenes como `<img>`. El theme ya las carga como fondos de las capas `apagado` y `prendido`.
- La seccion de Elementor que contiene este HTML debe tener `min-height: 100vh` y no necesita fondo propio.
- Si el encuadre no se ve bien en mobile, el primer ajuste debe ser `background-position` en `goodsleep-landing.css`.
