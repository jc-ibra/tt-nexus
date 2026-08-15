/*
 * Panel de empleados (RRHH) — renderizado de gráficas.
 *
 * La vista publica un JSON en #employees-dashboard-data[data-charts] con una
 * entrada por gráfica, cuya llave corresponde al canvas #chart-<llave>. Aquí no
 * se calcula nada del negocio: solo se dibuja lo que el servidor ya agregó.
 *
 * Tipos soportados: doughnut, hbar (barras horizontales), grouped (barras
 * verticales agrupadas).
 */
(function () {
  'use strict';

  // Paleta alineada con la del dashboard de KPIs para que ambos módulos se lean
  // como un mismo sistema.
  var PALETTE = [
    '#1773C8', // blue 500 - primario
    '#7B61FF', // violeta
    '#00A39E', // teal
    '#B98900', // ámbar
    '#D72C0D', // rojo
    '#008060', // verde
    '#F97316', // naranja
    '#EC4899', // rosa
    '#57A5E0', // blue 300
    '#5C6166', // neutral 600
    '#09345A', // blue 800
    '#8A6500', // ámbar fuerte
    '#C9CCCF'  // neutral 300 (cola / "Otros")
  ];

  var TICK_COLOR = '#44494D';
  var GRID_COLOR = '#E3E4E5';
  var FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";

  function colorsFor(count) {
    var out = [];
    for (var i = 0; i < count; i++) {
      out.push(PALETTE[i % PALETTE.length]);
    }
    return out;
  }

  function commonOptions(extra) {
    return Object.assign({
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#1A1C1E',
          titleColor: '#FFFFFF',
          bodyColor: '#FFFFFF',
          borderColor: 'transparent',
          padding: 10,
          cornerRadius: 6,
          displayColors: true
        }
      }
    }, extra || {});
  }

  function doughnutConfig(spec) {
    return {
      type: 'doughnut',
      data: {
        labels: spec.labels,
        datasets: [{
          data: spec.values,
          backgroundColor: colorsFor(spec.labels.length),
          borderColor: '#FFFFFF',
          borderWidth: 2
        }]
      },
      options: commonOptions({
        cutout: '58%',
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: TICK_COLOR, padding: 12, boxWidth: 12, boxHeight: 12 }
          },
          tooltip: {
            backgroundColor: '#1A1C1E',
            titleColor: '#FFFFFF',
            bodyColor: '#FFFFFF',
            padding: 10,
            cornerRadius: 6,
            callbacks: {
              label: function (ctx) {
                var sum = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                var pct = sum > 0 ? (ctx.parsed / sum * 100).toFixed(1) : '0.0';
                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
              }
            }
          }
        }
      })
    };
  }

  function hbarConfig(spec) {
    return {
      type: 'bar',
      data: {
        labels: spec.labels,
        datasets: [{
          data: spec.values,
          backgroundColor: spec.mono ? PALETTE[0] : colorsFor(spec.labels.length),
          borderRadius: 4,
          barThickness: 'flex',
          maxBarThickness: 26
        }]
      },
      options: commonOptions({
        indexAxis: 'y',
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: GRID_COLOR, drawBorder: false },
            ticks: { color: TICK_COLOR, precision: 0 }
          },
          y: {
            grid: { display: false },
            ticks: { color: TICK_COLOR, font: { weight: 500 }, autoSkip: false }
          }
        }
      })
    };
  }

  function groupedConfig(spec) {
    var datasets = spec.series.map(function (s, i) {
      return {
        label: s.label,
        data: s.values,
        backgroundColor: PALETTE[i % PALETTE.length],
        borderRadius: 4,
        maxBarThickness: 22
      };
    });

    return {
      type: 'bar',
      data: { labels: spec.labels, datasets: datasets },
      options: commonOptions({
        plugins: {
          legend: {
            display: true,
            position: 'bottom',
            labels: { color: TICK_COLOR, padding: 12, boxWidth: 12, boxHeight: 12 }
          },
          tooltip: {
            backgroundColor: '#1A1C1E',
            titleColor: '#FFFFFF',
            bodyColor: '#FFFFFF',
            padding: 10,
            cornerRadius: 6
          }
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: TICK_COLOR } },
          y: {
            beginAtZero: true,
            grid: { color: GRID_COLOR, drawBorder: false },
            ticks: { color: TICK_COLOR, precision: 0 }
          }
        }
      })
    };
  }

  // Navegación al directorio filtrado cuando la gráfica trae enlaces.
  function attachDrillDown(config, spec, canvas) {
    if (!spec.links || !spec.links.length) {
      return config;
    }

    config.options.onClick = function (evt, elements) {
      if (!elements || !elements.length) {
        return;
      }
      var url = spec.links[elements[0].index];
      if (url) {
        window.location.href = url;
      }
    };

    config.options.onHover = function (evt, elements) {
      var url = elements && elements.length ? spec.links[elements[0].index] : null;
      canvas.style.cursor = url ? 'pointer' : 'default';
    };

    return config;
  }

  function build(spec, canvas) {
    if (spec.type === 'doughnut') {
      return attachDrillDown(doughnutConfig(spec), spec, canvas);
    }
    if (spec.type === 'hbar') {
      return attachDrillDown(hbarConfig(spec), spec, canvas);
    }
    if (spec.type === 'grouped') {
      return groupedConfig(spec);
    }
    return null;
  }

  function bootstrap() {
    var host = document.getElementById('employees-dashboard-data');
    if (!host) {
      return;
    }

    // Chart.js se carga con defer: puede no estar listo todavía.
    if (typeof Chart === 'undefined') {
      setTimeout(bootstrap, 60);
      return;
    }

    var charts;
    try {
      charts = JSON.parse(host.dataset.charts || '{}');
    } catch (e) {
      return;
    }

    Chart.defaults.font.family = FONT;
    Chart.defaults.font.size = 12;
    Chart.defaults.color = TICK_COLOR;

    Object.keys(charts).forEach(function (key) {
      var canvas = document.getElementById('chart-' + key);
      if (!canvas) {
        return;
      }

      var spec = charts[key];
      if (!spec || !spec.labels || !spec.labels.length) {
        return;
      }

      var config = build(spec, canvas);
      if (config) {
        new Chart(canvas, config);
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
