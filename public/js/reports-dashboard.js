/*
 * Informe ejecutivo mensual (Reports) — renderizado de gráficas.
 *
 * La vista publica un JSON en #reports-dashboard-data[data-charts] con una
 * entrada por gráfica, cuya llave corresponde al canvas #chart-<llave>. Aquí
 * no se calcula nada del negocio: solo se dibuja lo que el servidor ya
 * congeló en el snapshot.
 *
 * Paleta y especificaciones de marca siguen el método de la skill dataviz
 * (color por el trabajo que hace, no por preferencia): magnitud = un solo
 * tono; identidad = categórico en orden fijo; estado = paleta de estado fija;
 * progreso de un pipeline = rampa ordinal de un solo tono, claro -> oscuro.
 * Los colores se leen de NxChartTheme (public/js/chart-theme.js), que a su
 * vez lee los tokens --chart-* de app.css — así la paleta responde al tema
 * claro/oscuro sin duplicar valores aquí.
 *
 * Tipos soportados:
 *   hbar     - ranking de una sola métrica (un tono; o colores explícitos
 *              por barra cuando la severidad/nivel de cada entidad importa)
 *   stagebar - una sola barra apilada, en el orden real del pipeline
 *              (Nuevo -> ... -> Cerrado), tono más oscuro = etapa más avanzada
 *   line     - series distintas en el tiempo (identidad categórica)
 */
(function () {
  'use strict';

  var charts = [];
  var specsByCanvas = null;

  function fmt(n) {
    return Number(n).toLocaleString('es-MX');
  }

  function commonOptions(t, extra) {
    return Object.assign({
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          titleFont: { family: t.FONT }, bodyFont: { family: t.FONT },
          backgroundColor: t.TOOLTIP_BG, titleColor: t.TOOLTIP_TEXT, bodyColor: t.TOOLTIP_TEXT,
          padding: 10, cornerRadius: 6,
          callbacks: { label: function (ctx) { return ' ' + fmt(ctx.parsed.x != null ? ctx.parsed.x : ctx.parsed.y); } },
        },
      },
    }, extra || {});
  }

  // spec.colors puede traer llaves semánticas ('critical'/'warning'/'good'/
  // 'info') en vez de hex — el servidor no conoce el tema activo, así que
  // manda la severidad y aquí se resuelve contra la paleta de estado viva.
  var STATUS_KEYS = { critical: 1, warning: 1, good: 1, info: 1 };
  function resolveColor(c, t) {
    if (STATUS_KEYS[c]) { return c === 'info' ? t.CATEGORICAL[0] : t.STATUS[c]; }
    return c;
  }

  /**
   * Ranking de una sola métrica. Prioridad de color: spec.colors (por barra,
   * severidad/banda) > spec.tiers (grupo/hoja de una jerarquía: mismo tono,
   * un paso más claro para la hoja) > spec.mono (un solo tono) > categórico.
   *
   * Con spec.tiers, además de recolorear cada barra, el renglón del GRUPO se
   * marca en negritas y el de la HOJA en un tamaño menor y tono apagado —
   * la jerarquía se lee sin depender solo de la indentación del texto.
   */
  function renderHbar(ctx, spec, t) {
    var tiers = spec.tiers || null;
    var colors = spec.colors
      ? spec.colors.map(function (c) { return resolveColor(c, t); })
      : (tiers ? tiers.map(function (x) { return x === 'child' ? t.SEQUENTIAL[1] : t.SEQUENTIAL[4]; })
      : (spec.mono ? spec.labels.map(function () { return t.SEQUENTIAL[4]; }) : t.CATEGORICAL));
    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels: spec.labels,
        datasets: [{
          data: spec.values,
          backgroundColor: colors,
          borderRadius: 4,
          borderSkipped: false,
          maxBarThickness: 22,
        }],
      },
      options: commonOptions(t, {
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, ticks: { color: t.TEXT_MUTED, font: { family: t.FONT }, precision: 0 }, grid: { color: t.GRID_COLOR, drawTicks: false } },
          y: {
            ticks: {
              color: tiers ? function (c) { return tiers[c.index] === 'child' ? t.TEXT_MUTED : t.TEXT_PRIMARY; } : t.TEXT_PRIMARY,
              font: tiers ? function (c) {
                return tiers[c.index] === 'child'
                  ? { family: t.FONT, size: 11 }
                  : { family: t.FONT, size: 12, weight: '600' };
              } : { family: t.FONT },
            },
            grid: { display: false },
          },
        },
      }),
    });
  }

  /**
   * Una sola barra apilada horizontal: cada segmento es una etapa del
   * pipeline (spec.labels/spec.values ya vienen en orden real, no por
   * conteo), coloreada con una rampa ordinal clara->oscura. La leyenda
   * de Chart.js hace de identidad de cada etapa.
   */
  function renderStageBar(ctx, spec, t) {
    var colors = NxChartTheme.stepsFrom(t.SEQUENTIAL, spec.labels.length);
    var total = spec.values.reduce(function (a, b) { return a + b; }, 0);
    var datasets = spec.labels.map(function (label, i) {
      return {
        label: label,
        data: [spec.values[i]],
        backgroundColor: colors[i],
        borderRadius: 3,
        borderSkipped: false,
      };
    });
    return new Chart(ctx, {
      type: 'bar',
      data: { labels: [''], datasets: datasets },
      options: commonOptions(t, {
        indexAxis: 'y',
        plugins: {
          legend: {
            display: true, position: 'bottom', labels: { color: t.TEXT_PRIMARY, font: { family: t.FONT, size: 11 }, boxWidth: 12, boxHeight: 12, padding: 14 },
          },
          tooltip: {
            titleFont: { family: t.FONT }, bodyFont: { family: t.FONT },
            backgroundColor: t.TOOLTIP_BG, titleColor: t.TOOLTIP_TEXT, bodyColor: t.TOOLTIP_TEXT,
            padding: 10, cornerRadius: 6,
            callbacks: {
              label: function (c) {
                var pct = total > 0 ? Math.round((c.parsed.x / total) * 100) : 0;
                return ' ' + c.dataset.label + ': ' + fmt(c.parsed.x) + ' (' + pct + '%)';
              },
            },
          },
        },
        scales: {
          x: { stacked: true, beginAtZero: true, display: false, grid: { display: false } },
          y: { stacked: true, display: false, grid: { display: false } },
        },
      }),
    });
  }

  function renderLine(ctx, spec, t) {
    return new Chart(ctx, {
      type: 'line',
      data: {
        labels: spec.labels,
        datasets: (spec.series || []).map(function (s, i) {
          var color = t.CATEGORICAL[i % t.CATEGORICAL.length];
          return {
            label: s.label,
            data: s.values,
            borderColor: color,
            backgroundColor: color,
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: t.POINT_BG,
            pointBorderColor: color,
            pointBorderWidth: 2,
            tension: 0,
            fill: false,
          };
        }),
      },
      options: commonOptions(t, {
        plugins: {
          legend: { display: (spec.series || []).length > 1, labels: { font: { family: t.FONT }, color: t.TEXT_PRIMARY, boxWidth: 12, boxHeight: 12 } },
          tooltip: {
            titleFont: { family: t.FONT }, bodyFont: { family: t.FONT },
            backgroundColor: t.TOOLTIP_BG, titleColor: t.TOOLTIP_TEXT, bodyColor: t.TOOLTIP_TEXT,
            padding: 10, cornerRadius: 6,
            callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + fmt(c.parsed.y); } },
          },
        },
        scales: {
          x: { ticks: { color: t.TEXT_MUTED, font: { family: t.FONT } }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { color: t.TEXT_MUTED, font: { family: t.FONT }, precision: 0 }, grid: { color: t.GRID_COLOR, drawTicks: false } },
        },
      }),
    });
  }

  function renderAll() {
    charts.forEach(function (c) { c.destroy(); });
    charts = [];
    if (! specsByCanvas) { return; }
    var t = NxChartTheme.palette();
    Object.keys(specsByCanvas).forEach(function (key) {
      var canvas = document.getElementById('chart-' + key);
      if (! canvas) { return; }
      var spec = specsByCanvas[key];
      var ctx = canvas.getContext('2d');
      var chart;
      if (spec.type === 'hbar') {
        chart = renderHbar(ctx, spec, t);
      } else if (spec.type === 'stagebar') {
        chart = renderStageBar(ctx, spec, t);
      } else if (spec.type === 'line') {
        chart = renderLine(ctx, spec, t);
      }
      if (chart) { charts.push(chart); }
    });
  }

  window.ReportsCharts = {
    get STATUS() { return NxChartTheme.palette().STATUS; },
    get CATEGORICAL() { return NxChartTheme.palette().CATEGORICAL; },
    get SEQUENTIAL_BLUE() { return NxChartTheme.palette().SEQUENTIAL; },
    stepsFrom: function (ramp, n) { return NxChartTheme.stepsFrom(ramp, n); },
  };

  document.addEventListener('DOMContentLoaded', function () {
    var holder = document.getElementById('reports-dashboard-data');
    if (! holder || typeof Chart === 'undefined' || typeof NxChartTheme === 'undefined') {
      return;
    }
    try {
      specsByCanvas = JSON.parse(holder.getAttribute('data-charts') || '{}');
    } catch (e) {
      return;
    }
    renderAll();
    NxChartTheme.onChange(renderAll);
  });
})();
