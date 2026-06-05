/**
 * KPI dashboard - GLPI Tickets
 *
 * Lee el snapshot kpi_json desde un <div id="kpi-data" data-kpi="..."> y
 * construye las 7 visualizaciones con Chart.js.
 *
 * Charts:
 *   chart-estados       (doughnut)  - estados_ticket
 *   chart-regionales    (bar horiz) - reg_top
 *   chart-coordinacion  (bar horiz) - coord_tickets sorted desc
 *   chart-estado-geo    (bar vert)  - est_top (top 8)
 *   chart-idc-top       (bar horiz) - idc_top
 *   chart-idc-bottom    (bar horiz) - idc_bottom
 *   chart-categorias    (bar horiz) - cat_top
 *   chart-proyectos     (bar horiz) - proy_top
 *   chart-envios        (doughnut)  - env_cerr vs env_pend
 */
(function () {
  'use strict';

  function bootstrap() {
    if (typeof Chart === 'undefined') {
      // Chart.js cargó con defer, tal vez aún no esté listo
      setTimeout(bootstrap, 60);
      return;
    }

    const dataNode = document.getElementById('kpi-data');
    if (!dataNode) return;

    let kpi;
    try {
      kpi = JSON.parse(dataNode.dataset.kpi || '{}');
    } catch (e) {
      console.error('[kpi-glpi] no se pudo parsear data-kpi:', e);
      return;
    }

    const TICKETS_URL = dataNode.dataset.ticketsUrl || '';

    /**
     * Navega al drill-down con el filtro indicado.
     */
    function drillDown(filterKey, value) {
      if (!TICKETS_URL || !filterKey || value == null || value === '') return;
      const url = TICKETS_URL + '?' + filterKey + '=' + encodeURIComponent(String(value));
      window.location.href = url;
    }

    /**
     * Inyecta un onClick en el chart leyendo data-filter del wrapper.
     * `labelToValue(label)` permite mapear la categoría del chart a un valor
     * de filtro distinto (ej. nombre del coordinador → zona).
     */
    function makeChartClickable(canvasId, config, labelToValue) {
      const canvas = document.getElementById(canvasId);
      if (!canvas) return config;
      const wrap = canvas.closest('.chart-wrap.is-clickable');
      if (!wrap) return config;

      const filterKey = wrap.dataset.filter;
      if (!filterKey) return config;

      config.options = config.options || {};
      config.options.onClick = function (evt, elements) {
        if (!elements || !elements.length) return;
        const idx = elements[0].index;
        const label = config.data.labels[idx];
        const value = typeof labelToValue === 'function' ? labelToValue(label, idx) : label;
        drillDown(filterKey, value);
      };
      return config;
    }

    // Paleta inspirada en Polaris (consistente con el resto del sistema)
    const PALETTE = [
      '#1773C8', // blue 500 - primario
      '#7B61FF', // violeta - secundario
      '#00A39E', // teal - terciario
      '#B98900', // amber - alerta
      '#D72C0D', // rojo - crítico
      '#008060', // verde - éxito
      '#F97316', // naranja
      '#EC4899', // rosa
    ];

    const TICK_COLOR = '#44494D';
    const GRID_COLOR = '#E3E4E5';
    const FONT       = "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";

    Chart.defaults.font.family = FONT;
    Chart.defaults.font.size   = 12;
    Chart.defaults.color       = TICK_COLOR;

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
            displayColors: true,
          },
        },
      }, extra || {});
    }

    function horizontalBarConfig(labels, values, colorMode) {
      const colors = colorMode === 'multi' ? PALETTE.slice(0, labels.length) : PALETTE[0];

      return {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            data: values,
            backgroundColor: colors,
            borderRadius: 4,
            barThickness: 'flex',
            maxBarThickness: 28,
          }],
        },
        options: commonOptions({
          indexAxis: 'y',
          scales: {
            x: {
              beginAtZero: true,
              grid: { color: GRID_COLOR, drawBorder: false },
              ticks: { color: TICK_COLOR, precision: 0 },
            },
            y: {
              grid: { display: false },
              ticks: { color: TICK_COLOR, font: { weight: 500 } },
            },
          },
        }),
      };
    }

    function verticalBarConfig(labels, values) {
      return {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            data: values,
            backgroundColor: PALETTE[0],
            hoverBackgroundColor: PALETTE[1],
            borderRadius: 4,
            maxBarThickness: 50,
          }],
        },
        options: commonOptions({
          scales: {
            x: {
              grid: { display: false },
              ticks: {
                color: TICK_COLOR,
                font: { size: 11 },
                callback: function (v) {
                  const lbl = this.getLabelForValue(v);
                  return lbl && lbl.length > 14 ? lbl.slice(0, 13) + '…' : lbl;
                },
              },
            },
            y: {
              beginAtZero: true,
              grid: { color: GRID_COLOR, drawBorder: false },
              ticks: { color: TICK_COLOR, precision: 0 },
            },
          },
        }),
      };
    }

    function doughnutConfig(labels, values, colors) {
      return {
        type: 'doughnut',
        data: {
          labels: labels,
          datasets: [{
            data: values,
            backgroundColor: colors || PALETTE.slice(0, labels.length),
            borderColor: '#FFFFFF',
            borderWidth: 2,
          }],
        },
        options: commonOptions({
          cutout: '60%',
          plugins: {
            legend: {
              display: true,
              position: 'bottom',
              labels: { color: TICK_COLOR, padding: 12, boxWidth: 12, boxHeight: 12 },
            },
            tooltip: {
              backgroundColor: '#1A1C1E',
              titleColor: '#FFFFFF',
              bodyColor: '#FFFFFF',
              callbacks: {
                label: function (ctx) {
                  const sum = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                  const pct = sum > 0 ? (ctx.parsed / sum * 100).toFixed(2) : '0.00';
                  return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                },
              },
            },
          },
        }),
      };
    }

    function mkChart(canvasId, config) {
      const el = document.getElementById(canvasId);
      if (!el) return null;
      return new Chart(el, config);
    }

    function tupleSplit(arr) {
      const labels = [];
      const values = [];
      (arr || []).forEach(function (t) {
        if (Array.isArray(t) && t.length >= 2) {
          labels.push(String(t[0]));
          values.push(Number(t[1]));
        }
      });
      return { labels, values };
    }

    // ── 1. Estados (doughnut) ─────────────────────────────────────────────
    {
      const { labels, values } = tupleSplit(kpi.estados_ticket);
      const colors = ['#B98900', '#008060', '#1773C8', '#7B61FF', '#D72C0D', '#6D7175'];
      mkChart('chart-estados', makeChartClickable('chart-estados', doughnutConfig(labels, values, colors.slice(0, labels.length))));

      const rows = document.querySelectorAll('.state-row');
      rows.forEach(function (row, i) {
        row.style.borderLeftColor = colors[i] || '#6D7175';
      });
    }

    // ── 2. Regionales (bar horiz, single color) ──────────────────────────
    {
      const { labels, values } = tupleSplit(kpi.reg_top);
      mkChart('chart-regionales', makeChartClickable('chart-regionales', horizontalBarConfig(labels, values, 'single')));
    }

    // ── 3. Coordinación (bar horiz, multi color) ─────────────────────────
    // El chart muestra nombres de coordinadores como labels, pero el filtro
    // del drill-down debe ser el ZONE (regional). Mapeo coord→zone para el onClick.
    {
      const coordTickets = kpi.coord_tickets || {};
      const entries = Object.keys(coordTickets).map(function (k) {
        return [k, Number(coordTickets[k])];
      });
      entries.sort(function (a, b) { return b[1] - a[1]; });

      const zonesByIdx = entries.map(function (e) { return e[0]; });
      const labels = entries.map(function (e) {
        const zone = e[0];
        const info = (kpi.coord_info || {})[zone] || { coord: zone };
        return info.coord;
      });
      const values = entries.map(function (e) { return e[1]; });

      mkChart(
        'chart-coordinacion',
        makeChartClickable(
          'chart-coordinacion',
          horizontalBarConfig(labels, values, 'multi'),
          function (_label, idx) { return zonesByIdx[idx]; }
        )
      );
    }

    // ── 4. Estado geográfico (vertical bar) ──────────────────────────────
    {
      const { labels, values } = tupleSplit(kpi.est_top);
      mkChart('chart-estado-geo', makeChartClickable('chart-estado-geo', verticalBarConfig(labels, values)));
    }

    // ── 5. IDC Top 10 (bar horiz, blue) ───────────────────────────────────
    {
      const { labels, values } = tupleSplit(kpi.idc_top);
      mkChart('chart-idc-top', makeChartClickable('chart-idc-top', horizontalBarConfig(labels, values, 'single')));
    }

    // ── 6. IDC Bottom 10 (bar horiz, warning amber) ───────────────────────
    {
      const { labels, values } = tupleSplit(kpi.idc_bottom);
      const cfg = horizontalBarConfig(labels, values, 'single');
      cfg.data.datasets[0].backgroundColor = '#B98900';
      mkChart('chart-idc-bottom', makeChartClickable('chart-idc-bottom', cfg));
    }

    // ── 7. Categorías (bar horiz, multi) ─────────────────────────────────
    {
      const { labels, values } = tupleSplit(kpi.cat_top);
      mkChart('chart-categorias', makeChartClickable('chart-categorias', horizontalBarConfig(labels, values, 'multi')));
    }

    // ── 8. Proyectos (bar horiz, multi) ──────────────────────────────────
    {
      const { labels, values } = tupleSplit(kpi.proy_top);
      mkChart('chart-proyectos', makeChartClickable('chart-proyectos', horizontalBarConfig(labels, values, 'multi')));
    }

    // ── 9. Envíos (doughnut) - no clickeable: el sub-pipeline ya tiene su CTA ─
    {
      const cerr = Number(kpi.env_cerr || 0);
      const pend = Number(kpi.env_pend || 0);
      mkChart(
        'chart-envios',
        doughnutConfig(['Cerrados', 'Pendientes'], [cerr, pend], ['#008060', '#D72C0D'])
      );
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
