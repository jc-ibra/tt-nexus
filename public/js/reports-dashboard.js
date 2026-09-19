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

  // Categórico (orden fijo, no se recicla): usar siempre desde el slot 1.
  var CATEGORICAL = ['#2a78d6', '#eb6834', '#1baf7a'];

  // Estado (fijo, nunca se reusa para una serie cualquiera).
  var STATUS = { good: '#0ca30c', warning: '#fab219', serious: '#ec835a', critical: '#d03b3b' };

  // Rampa secuencial azul (100->700), clara -> oscura, para magnitud/ordinal.
  var SEQUENTIAL_BLUE = ['#86b6ef', '#6da7ec', '#5598e7', '#3987e5', '#2a78d6', '#256abf', '#1c5cab', '#184f95', '#104281', '#0d366b'];

  var TEXT_PRIMARY = '#1A1C1E';
  var TEXT_MUTED = '#6D7175';
  var GRID_COLOR = '#E9EAEB';
  var FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";

  /** N tonos uniformemente repartidos en una rampa, del más claro al más oscuro. */
  function stepsFrom(ramp, n) {
    if (n <= 1) { return [ramp[ramp.length - 1]]; }
    var out = [];
    for (var i = 0; i < n; i++) {
      var idx = Math.round((i * (ramp.length - 1)) / (n - 1));
      out.push(ramp[idx]);
    }
    return out;
  }

  function fmt(n) {
    return Number(n).toLocaleString('es-MX');
  }

  function commonOptions(extra) {
    return Object.assign({
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          titleFont: { family: FONT }, bodyFont: { family: FONT },
          backgroundColor: '#1A1C1E', padding: 10, cornerRadius: 6,
          callbacks: { label: function (ctx) { return ' ' + fmt(ctx.parsed.x != null ? ctx.parsed.x : ctx.parsed.y); } },
        },
      },
    }, extra || {});
  }

  /**
   * Ranking de una sola métrica. Prioridad de color: spec.colors (por barra,
   * severidad/banda) > spec.tiers (grupo/hoja de una jerarquía: mismo tono,
   * un paso más claro para la hoja) > spec.mono (un solo tono) > categórico.
   */
  function renderHbar(ctx, spec) {
    var colors = spec.colors
      || (spec.tiers ? spec.tiers.map(function (t) { return t === 'child' ? SEQUENTIAL_BLUE[1] : SEQUENTIAL_BLUE[4]; })
      : (spec.mono ? spec.labels.map(function () { return SEQUENTIAL_BLUE[4]; }) : CATEGORICAL));
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
      options: commonOptions({
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, ticks: { color: TEXT_MUTED, font: { family: FONT }, precision: 0 }, grid: { color: GRID_COLOR, drawTicks: false } },
          y: { ticks: { color: TEXT_PRIMARY, font: { family: FONT } }, grid: { display: false } },
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
  function renderStageBar(ctx, spec) {
    var colors = stepsFrom(SEQUENTIAL_BLUE, spec.labels.length);
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
      options: commonOptions({
        indexAxis: 'y',
        plugins: {
          legend: {
            display: true, position: 'bottom', labels: { color: TEXT_PRIMARY, font: { family: FONT, size: 11 }, boxWidth: 12, boxHeight: 12, padding: 14 },
          },
          tooltip: {
            titleFont: { family: FONT }, bodyFont: { family: FONT }, backgroundColor: '#1A1C1E', padding: 10, cornerRadius: 6,
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

  function renderLine(ctx, spec) {
    return new Chart(ctx, {
      type: 'line',
      data: {
        labels: spec.labels,
        datasets: (spec.series || []).map(function (s, i) {
          return {
            label: s.label,
            data: s.values,
            borderColor: CATEGORICAL[i % CATEGORICAL.length],
            backgroundColor: CATEGORICAL[i % CATEGORICAL.length],
            borderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            pointBackgroundColor: '#fff',
            pointBorderColor: CATEGORICAL[i % CATEGORICAL.length],
            pointBorderWidth: 2,
            tension: 0,
            fill: false,
          };
        }),
      },
      options: commonOptions({
        plugins: {
          legend: { display: (spec.series || []).length > 1, labels: { font: { family: FONT }, color: TEXT_PRIMARY, boxWidth: 12, boxHeight: 12 } },
          tooltip: {
            titleFont: { family: FONT }, bodyFont: { family: FONT }, backgroundColor: '#1A1C1E', padding: 10, cornerRadius: 6,
            callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + fmt(c.parsed.y); } },
          },
        },
        scales: {
          x: { ticks: { color: TEXT_MUTED, font: { family: FONT } }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { color: TEXT_MUTED, font: { family: FONT }, precision: 0 }, grid: { color: GRID_COLOR, drawTicks: false } },
        },
      }),
    });
  }

  window.ReportsCharts = { STATUS: STATUS, CATEGORICAL: CATEGORICAL, SEQUENTIAL_BLUE: SEQUENTIAL_BLUE, stepsFrom: stepsFrom };

  document.addEventListener('DOMContentLoaded', function () {
    var holder = document.getElementById('reports-dashboard-data');
    if (! holder || typeof Chart === 'undefined') {
      return;
    }
    var charts;
    try {
      charts = JSON.parse(holder.getAttribute('data-charts') || '{}');
    } catch (e) {
      return;
    }

    Object.keys(charts).forEach(function (key) {
      var canvas = document.getElementById('chart-' + key);
      if (! canvas) {
        return;
      }
      var spec = charts[key];
      var ctx = canvas.getContext('2d');
      if (spec.type === 'hbar') {
        renderHbar(ctx, spec);
      } else if (spec.type === 'stagebar') {
        renderStageBar(ctx, spec);
      } else if (spec.type === 'line') {
        renderLine(ctx, spec);
      }
    });
  });
})();
