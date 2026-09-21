/*
 * Puente entre los tokens CSS de tema (public/css/app.css, --chart-*) y las
 * gráficas de Chart.js. Cada dashboard de gráficas debe leer su paleta de
 * aquí en vez de tener colores fijos: así responden al claro/oscuro sin
 * tener que recargar la página.
 *
 * Uso en un dashboard:
 *   var charts = [];
 *   function renderAll() {
 *     charts.forEach(function (c) { c.destroy(); });
 *     charts = [];
 *     var p = NxChartTheme.palette();
 *     // ...usar p.CATEGORICAL, p.TEXT_MUTED, etc. al construir cada Chart...
 *     charts.push(new Chart(ctx, config));
 *   }
 *   renderAll();
 *   NxChartTheme.onChange(renderAll);
 *
 * Se destruye y se vuelve a crear cada Chart en vez de mutar sus `options`
 * en caliente: varias gráficas de este proyecto pasan callbacks/arrays de
 * color por punto (ticks.color, backgroundColor por barra), y parchear eso
 * en cada ruta anidada es frágil. El cambio de tema es una acción del
 * usuario, no un bucle de animación, así que reconstruir es barato y no
 * puede quedar a medias.
 */
(function () {
  'use strict';

  var root = document.documentElement;
  var listeners = [];

  function token(name) {
    return getComputedStyle(root).getPropertyValue('--' + name).trim();
  }

  function palette() {
    return {
      isDark: root.getAttribute('data-theme-resolved') === 'dark',
      CATEGORICAL: [token('chart-cat-1'), token('chart-cat-2'), token('chart-cat-3')],
      STATUS: {
        good: token('chart-good'),
        warning: token('chart-warning'),
        serious: token('chart-serious'),
        critical: token('chart-critical'),
      },
      SEQUENTIAL: [
        token('chart-seq-1'), token('chart-seq-2'), token('chart-seq-3'),
        token('chart-seq-4'), token('chart-seq-5'),
      ],
      TEXT_PRIMARY: token('chart-text'),
      TEXT_MUTED: token('chart-text-muted'),
      GRID_COLOR: token('chart-grid'),
      TOOLTIP_BG: token('chart-tooltip-bg'),
      TOOLTIP_TEXT: token('chart-tooltip-text'),
      POINT_BG: token('chart-point-bg'),
      FONT: token('font-sans'),
    };
  }

  /** Interpola N tonos uniformemente repartidos en una rampa (clara->oscura
   *  en claro; oscura->clara en oscuro, porque SEQUENTIAL ya viene invertida
   *  por tema en app.css). */
  function stepsFrom(ramp, n) {
    if (n <= 1) { return [ramp[ramp.length - 1]]; }
    var out = [];
    for (var i = 0; i < n; i++) {
      var idx = Math.round((i * (ramp.length - 1)) / (n - 1));
      out.push(ramp[idx]);
    }
    return out;
  }

  function onChange(fn) {
    listeners.push(fn);
  }

  document.addEventListener('nx:themechange', function () {
    listeners.forEach(function (fn) {
      try { fn(palette()); } catch (e) { /* un dashboard roto no debe tumbar a los demás */ }
    });
  });

  window.NxChartTheme = { palette: palette, stepsFrom: stepsFrom, onChange: onChange };
})();
