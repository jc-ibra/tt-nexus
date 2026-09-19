<?php
/**
 * Modo presentación: deck de pantalla completa para proyectar en junta.
 *
 * Identidad propia, deliberadamente distinta del dashboard (claro) y del
 * PPTX (claro, para lectura de cerca / impresión): esta vista se consume
 * proyectada en una sala, a distancia. Un fondo de tinta oscura rinde mejor
 * ahí que uno claro, así que en vez de repetir la misma paleta corporativa
 * en las tres superficies, aquí se trata como una consola de operaciones:
 * la mesa de ayuda ES, literalmente, monitoreo de una cola de trabajo.
 *
 * Color por el trabajo que hace (método dataviz), no por gusto: estado fijo
 * (bien/mal) nunca se reusa para una serie; identidad categórica en orden
 * fijo; magnitud en un solo tono. Ver docs/modulos/reports/spec.md.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= esc($pageTitle) ?> - Nexus</title>
  <link rel="icon" type="image/png" href="<?= base_url('img/tt-icon.png') ?>">
  <script src="<?= asset_url('js/vendor/chart.umd.min.js') ?>" defer></script>
  <style>
    :root {
      color-scheme: dark;
      --ink:        #10131A;
      --panel:      #171B24;
      --border:     #262B37;
      --text:       #F2F4F7;
      --text-muted: #8B93A3;
      --text-faint: #565D6B;
      --accent:     #4C9FE8;
      --good:       #34C77B;
      --warning:    #F0B429;
      --critical:   #F0544C;
      --font: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0; background: var(--ink); color: var(--text);
      font-family: var(--font); overflow: hidden;
    }
    @media (prefers-reduced-motion: no-preference) {
      .op-slide { transition: opacity .28s ease, transform .28s ease; }
      .op-slide:not(.is-active) { transform: translateY(6px); }
    }
    .op-deck { position: relative; width: 100vw; height: 100vh; }

    /* Barra de instrumento: función real (sección + posición), no adorno. */
    .op-bar {
      position: fixed; top: 0; left: 0; right: 0; height: 52px;
      display: flex; align-items: center; justify-content: space-between;
      padding: 0 32px; z-index: 5;
    }
    .op-bar-section { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--text-muted); }
    .op-bar-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); flex-shrink: 0; }
    .op-bar-pos { font-size: 13px; color: var(--text-faint); font-variant-numeric: tabular-nums; margin-left: 14px; padding-left: 14px; border-left: 1px solid var(--border); }
    .op-progress { position: fixed; top: 0; left: 0; height: 2px; background: var(--accent); z-index: 6; transition: width .25s ease; }

    .op-slide {
      position: absolute; inset: 0; display: none; opacity: 0;
      padding: 96px 64px 64px 64px; overflow-y: auto;
    }
    .op-slide.is-active { display: block; opacity: 1; }

    /*
     * Escala tipográfica deliberadamente corta (6 tamaños, no 8): un informe
     * ejecutivo se lee de un vistazo, y cada salto de tamaño debe significar
     * un salto real de jerarquía, no una variación cosmética.
     *   13  micro:    etiquetas, leyendas, posición de la barra
     *   15  cuerpo:   subtítulos y descripciones
     *   20  subhead:  período de portada
     *   28  título:   encabezado de sección
     *   38  hero:     números del marcador (scoreboard)
     *   44  cubierta: título de portada, único en todo el deck
     */
    .op-title { font-size: 28px; font-weight: 600; margin: 0 0 8px 0; letter-spacing: -0.01em; }
    .op-subtitle { font-size: 15px; line-height: 1.5; color: var(--text-muted); margin: 0 0 28px 0; max-width: 62ch; }
    .op-rule { height: 1px; background: var(--border); border: none; margin: 0 0 28px 0; }

    /* Marcador de posición, deliberado: la cubierta es una secuencia real. */
    .op-cover-mark { font-size: 13px; color: var(--text-faint); margin: 0 0 24px 0; font-variant-numeric: tabular-nums; }
    .op-cover-title { font-size: 44px; font-weight: 600; letter-spacing: -0.015em; line-height: 1.1; margin: 0 0 6px 0; }
    .op-cover-period { font-size: 20px; color: var(--accent); font-weight: 500; margin: 0 0 18px 0; }
    .op-cover-desc { font-size: 15px; line-height: 1.5; color: var(--text-muted); max-width: 60ch; margin: 0 0 48px 0; }

    /* Marcador tipo scoreboard: números separados por regla, no tarjetas. */
    .op-score-row { display: flex; flex-wrap: wrap; gap: 0; }
    .op-score { padding: 0 36px 0 0; margin-right: 36px; border-right: 1px solid var(--border); }
    .op-score:last-child { border-right: none; }
    .op-score-value { font-size: 38px; font-weight: 600; letter-spacing: -0.01em; line-height: 1; font-variant-numeric: tabular-nums; }
    .op-score-label { font-size: 13px; color: var(--text-muted); margin-top: 8px; }
    .op-score.is-compact .op-score-value { font-size: 28px; }

    .op-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 36px; }
    .op-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 32px; }
    .op-panel-title { font-size: 13px; color: var(--text-muted); margin: 0 0 14px 0; }
    .op-chart { position: relative; width: 100%; height: 240px; }
    .op-chart-tall { height: 320px; }
    .op-chart-short { height: 180px; }

    .op-legend { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 14px; }
    .op-legend-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); }
    .op-legend-dot { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

    .op-meter-track { height: 10px; border-radius: 6px; background: var(--panel); overflow: hidden; }
    .op-meter-fill { height: 100%; border-radius: 6px; background: var(--good); }
    .op-meter-value { font-size: 20px; font-weight: 600; margin: 14px 0 4px 0; font-variant-numeric: tabular-nums; }
    .op-meter-sub { font-size: 13px; color: var(--text-muted); }

    .op-summary { font-size: 15px; line-height: 1.7; color: var(--text); max-width: 78ch; white-space: pre-line; }
    .op-summary-empty { font-size: 15px; color: var(--text-faint); }

    .op-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .op-table th, .op-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--border); }
    .op-table th { color: var(--text-muted); font-weight: 500; }
    .op-table td { color: var(--text); font-variant-numeric: tabular-nums; }
    .op-table td:not(:first-child), .op-table th:not(:first-child) { text-align: right; }

    .op-nav { position: fixed; bottom: 28px; right: 32px; display: flex; gap: 8px; z-index: 6; }
    .op-nav button {
      width: 36px; height: 36px; border-radius: 50%; border: 1px solid var(--border);
      background: transparent; color: var(--text-muted); cursor: pointer;
      display: flex; align-items: center; justify-content: center;
    }
    .op-nav button:hover { border-color: var(--accent); color: var(--text); }
    .op-nav button:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .op-hint { position: fixed; bottom: 34px; left: 32px; font-size: 12px; color: var(--text-faint); z-index: 6; }
    .op-exit {
      position: fixed; top: 14px; right: 24px; font-size: 13px; color: var(--text-faint);
      text-decoration: none; z-index: 6;
    }
    .op-exit:hover { color: var(--text-muted); }

    @media (max-width: 760px) {
      .op-grid, .op-grid-3 { grid-template-columns: 1fr; }
      .op-slide { padding: 80px 24px 32px 24px; }
      .op-cover-title { font-size: 32px; }
    }
  </style>
</head>
<body>
<div class="op-progress" id="op-progress" style="width: 0%;"></div>
<div class="op-bar">
  <span class="op-bar-section"><span class="op-bar-dot"></span><span id="op-bar-label">Informe ejecutivo</span><span class="op-bar-pos" id="op-bar-pos"></span></span>
  <a href="<?= route_to('reports.show', $period->year, $period->month) ?>" class="op-exit">Salir</a>
</div>

<?php
$glpi = $payload['glpi_tickets'] ?? ['available' => false];
$trend = $payload['trend'] ?? ['available' => false];
$dispatch = $payload['dispatch'] ?? ['available' => false];
$quality = $payload['quality'] ?? ['available' => false];
$agents = $payload['agents'] ?? ['available' => false];
$summary = trim($commentary['summary']['body'] ?? '');
?>

<div class="op-deck" id="op-deck">

  <section class="op-slide" data-section="Informe ejecutivo">
    <p class="op-cover-mark">Informe ejecutivo mensual</p>
    <h1 class="op-cover-title"><?= esc($period->label) ?></h1>
    <p class="op-cover-period">Mesa de ayuda, correo, calidad y desempeño de agentes</p>
    <p class="op-cover-desc">Estado operativo del mes, congelado al cierre. El detalle en vivo queda a una pantalla de distancia si algo necesita revisarse ahora mismo.</p>
    <div class="op-score-row">
      <?php if ($glpi['available'] ?? false): ?>
        <div class="op-score"><div class="op-score-value"><?= number_format($glpi['total']) ?></div><div class="op-score-label">Tickets GLPI</div></div>
        <div class="op-score"><div class="op-score-value"><?= number_format($glpi['tasa_cierre'], 1) ?>%</div><div class="op-score-label">Tasa de cierre</div></div>
        <div class="op-score"><div class="op-score-value"><?= number_format($glpi['sla_pct'], 1) ?>%</div><div class="op-score-label">SLA cumplido</div></div>
      <?php endif; ?>
      <?php if ($dispatch['available'] ?? false): ?>
        <div class="op-score"><div class="op-score-value"><?= number_format($dispatch['received']) ?></div><div class="op-score-label">Correos recibidos</div></div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($summary !== ''): ?>
  <section class="op-slide" data-section="Resumen ejecutivo">
    <h2 class="op-title">Resumen ejecutivo</h2>
    <p class="op-subtitle"><?= esc($period->label) ?></p>
    <hr class="op-rule">
    <p class="op-summary"><?= esc($summary) ?></p>
  </section>
  <?php endif; ?>

  <?php if (($glpi['available'] ?? false) && ($glpi['total'] ?? 0) > 0): ?>
  <section class="op-slide" data-section="Mesa de ayuda">
    <h2 class="op-title">Resumen del período</h2>
    <p class="op-subtitle">Indicadores principales de GLPI</p>
    <hr class="op-rule">
    <div class="op-score-row" style="margin-bottom: 40px;">
      <div class="op-score"><div class="op-score-value"><?= number_format($glpi['en_curso']) ?></div><div class="op-score-label">En curso</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($glpi['cerrados']) ?></div><div class="op-score-label">Cerrados</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($glpi['prom_h'], 1) ?>h</div><div class="op-score-label">Tiempo promedio</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($glpi['sin_reg']) ?></div><div class="op-score-label">Sin regional</div></div>
    </div>
    <p class="op-panel-title">Distribución por estado (orden de flujo)</p>
    <div class="op-chart" style="height: 60px;"><canvas id="op-estados"></canvas></div>
    <div class="op-legend" id="op-estados-legend"></div>
  </section>

  <section class="op-slide" data-section="Mesa de ayuda">
    <h2 class="op-title">Regional y estado geográfico</h2>
    <p class="op-subtitle">Tickets por regional, y estado geográfico con mayor y menor carga del período</p>
    <hr class="op-rule">
    <div class="op-grid-3">
      <div><p class="op-panel-title">Tickets por regional</p><div class="op-chart op-chart-tall"><canvas id="op-regional"></canvas></div></div>
      <div><p class="op-panel-title">Estado geográfico, mayor carga (Top 10)</p><div class="op-chart op-chart-tall"><canvas id="op-estado-geo"></canvas></div></div>
      <div><p class="op-panel-title">Estado geográfico, menor carga (Top 10)</p><div class="op-chart op-chart-tall"><canvas id="op-estado-geo-bottom"></canvas></div></div>
    </div>
  </section>

  <?php if (($glpi['ids_top'] ?? []) !== [] || ($glpi['ids_bottom'] ?? []) !== []): ?>
  <section class="op-slide" data-section="Mesa de ayuda">
    <h2 class="op-title">Ranking de técnicos (IDS)</h2>
    <p class="op-subtitle">Carga de tickets por técnico, excluyendo control de envíos y almacén</p>
    <hr class="op-rule">
    <div class="op-grid">
      <div><p class="op-panel-title">Mayor carga (Top 10)</p><div class="op-chart op-chart-tall"><canvas id="op-ids"></canvas></div></div>
      <div><p class="op-panel-title">Menor carga (Top 10)</p><div class="op-chart op-chart-tall"><canvas id="op-ids-bottom"></canvas></div></div>
    </div>
  </section>
  <?php endif; ?>

  <?php if (($glpi['cat_top'] ?? []) !== []): ?>
  <?php
  // Todas las filas, sin recorte: el slide hace scroll (.op-slide ya tiene
  // overflow-y: auto) en vez de esconder categorías. La altura del canvas
  // crece con el número real de filas para que ninguna barra quede
  // apretada.
  $catShown       = $glpi['cat_top'];
  $catGroupsTotal = count(array_filter($catShown, static fn($r) => $r['tier'] !== 'child'));
  $catHeight      = max(320, count($catShown) * 26);
  ?>
  <section class="op-slide" data-section="Mesa de ayuda">
    <h2 class="op-title">Tickets por categoría</h2>
    <p class="op-subtitle">Agrupadas por rama del árbol: <?= $catGroupsTotal ?> grupos, <?= (int) $glpi['cat_leaf_total'] ?> categorías registradas en el período. En negritas, el total del grupo; el detalle por hoja aparece debajo</p>
    <hr class="op-rule">
    <div class="op-chart" style="height: <?= $catHeight ?>px;"><canvas id="op-categorias"></canvas></div>
  </section>
  <?php endif; ?>

  <?php if (($glpi['env_total'] ?? 0) > 0): ?>
  <section class="op-slide" data-section="Mesa de ayuda">
    <h2 class="op-title">Control de envíos</h2>
    <p class="op-subtitle">Sub-pipeline de envíos y logística del período</p>
    <hr class="op-rule">
    <div class="op-score-row" style="margin-bottom: 40px;">
      <div class="op-score"><div class="op-score-value"><?= number_format($glpi['env_total']) ?></div><div class="op-score-label">Total</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($glpi['env_cerr']) ?></div><div class="op-score-label">Cerrados</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($glpi['env_pend']) ?></div><div class="op-score-label">Pendientes</div></div>
    </div>
    <p class="op-panel-title">Avance de cierre</p>
    <div class="op-meter-track" style="max-width: 560px;"><div class="op-meter-fill" style="width: <?= number_format($glpi['env_pct'], 1) ?>%;"></div></div>
    <p class="op-meter-value"><?= number_format($glpi['env_pct'], 1) ?>% cerrado</p>
    <p class="op-meter-sub"><?= number_format($glpi['env_cerr']) ?> de <?= number_format($glpi['env_total']) ?> tickets, <?= number_format($glpi['env_pend']) ?> pendientes</p>
  </section>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($trend['available'] ?? false): ?>
  <section class="op-slide" data-section="Tendencia">
    <h2 class="op-title">Evolución mensual</h2>
    <p class="op-subtitle">Total y cerrados en los últimos <?= count($trend['series']) ?> meses</p>
    <hr class="op-rule">
    <?php $vs = $trend['vs_previous_month'] ?? null; ?>
    <?php if ($vs): ?>
    <div class="op-score-row" style="margin-bottom: 32px;">
      <?php foreach (['total' => 'Total', 'cerrados' => 'Cerrados', 'tasa_cierre' => 'Tasa de cierre %', 'sla_pct' => 'SLA %'] as $metric => $label): ?>
        <?php $m = $vs[$metric]; $sign = $m['delta_abs'] >= 0 ? '+' : ''; ?>
        <div class="op-score is-compact">
          <div class="op-score-value"><?= is_float($m['current']) ? number_format($m['current'], 1) : number_format($m['current']) ?></div>
          <div class="op-score-label"><?= esc($label) ?> · <?= $sign ?><?= is_float($m['delta_abs']) ? number_format($m['delta_abs'], 1) : $m['delta_abs'] ?> vs. mes anterior</div>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="op-chart op-chart-tall"><canvas id="op-trend"></canvas></div>
  </section>

  <?php $aging = $trend['backlog_aging'] ?? ['available' => false]; ?>
  <?php if ($aging['available'] ?? false): ?>
  <section class="op-slide" data-section="Tendencia">
    <h2 class="op-title">Antigüedad del backlog</h2>
    <p class="op-subtitle">Tickets abiertos hoy, no del mes del informe: es el estado operativo al momento</p>
    <hr class="op-rule">
    <div class="op-score-row" style="margin-bottom: 40px;">
      <div class="op-score"><div class="op-score-value"><?= number_format($aging['total_open']) ?></div><div class="op-score-label">Abiertos hoy</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($aging['critical_count']) ?></div><div class="op-score-label">Más de <?= (int) $aging['critical_days'] ?> días</div></div>
    </div>
    <table class="op-table">
      <thead><tr><th>0-3 días</th><th>4-7 días</th><th>8-15 días</th><th>16-30 días</th><th>31+ días</th></tr></thead>
      <tbody><tr><?php foreach ($aging['buckets'] as $n): ?><td><?= number_format($n) ?></td><?php endforeach; ?></tr></tbody>
    </table>
  </section>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($dispatch['available'] ?? false): ?>
  <section class="op-slide" data-section="Correo">
    <h2 class="op-title">Mesa de ayuda por correo</h2>
    <p class="op-subtitle">Indicadores del período en MailDispatch</p>
    <hr class="op-rule">
    <div class="op-score-row" style="margin-bottom: 32px;">
      <div class="op-score"><div class="op-score-value"><?= number_format($dispatch['received']) ?></div><div class="op-score-label">Recibidos</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($dispatch['closed']) ?></div><div class="op-score-label">Cerrados</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($dispatch['backlog_unassigned']) ?></div><div class="op-score-label">Sin asignar</div></div>
      <div class="op-score"><div class="op-score-value"><?= $dispatch['avg_first_response_min'] !== null ? number_format($dispatch['avg_first_response_min'], 0) . ' min' : '-' ?></div><div class="op-score-label">Primera respuesta</div></div>
    </div>
    <p class="op-panel-title">Disposiciones de conversaciones cerradas</p>
    <div class="op-chart"><canvas id="op-dispatch"></canvas></div>
  </section>
  <?php endif; ?>

  <?php if ($quality['available'] ?? false): ?>
  <section class="op-slide" data-section="Calidad">
    <h2 class="op-title">Calidad documental</h2>
    <p class="op-subtitle">Auditoría HelpdeskSupervisor del período</p>
    <hr class="op-rule">
    <div class="op-score-row" style="margin-bottom: 32px;">
      <div class="op-score"><div class="op-score-value"><?= number_format($quality['run']['total_tickets_audited']) ?></div><div class="op-score-label">Tickets auditados</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($quality['run']['total_deviations_found']) ?></div><div class="op-score-label">Desviaciones</div></div>
      <div class="op-score"><div class="op-score-value"><?= number_format($quality['valid_escalations']) ?></div><div class="op-score-label">Escalaciones válidas</div></div>
    </div>
    <p class="op-panel-title">Reglas con más desviaciones</p>
    <div class="op-chart"><canvas id="op-quality"></canvas></div>
    <div class="op-legend">
      <span class="op-legend-item"><span class="op-legend-dot" style="background: var(--critical);"></span>Crítica</span>
      <span class="op-legend-item"><span class="op-legend-dot" style="background: var(--warning);"></span>Warning</span>
      <span class="op-legend-item"><span class="op-legend-dot" style="background: var(--accent);"></span>Info</span>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($agents['available'] ?? false): ?>
  <section class="op-slide" data-section="Desempeño">
    <h2 class="op-title">Desempeño de agentes</h2>
    <p class="op-subtitle">Evaluación mensual AgentKpis</p>
    <hr class="op-rule">
    <div class="op-score-row" style="margin-bottom: 32px;">
      <div class="op-score"><div class="op-score-value"><?= number_format($agents['avg_final_score'], 1) ?></div><div class="op-score-label">Promedio general</div></div>
      <div class="op-score"><div class="op-score-value"><?= $agents['evaluated_count'] ?></div><div class="op-score-label">Evaluados</div></div>
      <div class="op-score"><div class="op-score-value"><?= $agents['blocked_count'] ?></div><div class="op-score-label">Bloqueados</div></div>
    </div>
    <p class="op-panel-title">Score final por agente</p>
    <div class="op-chart"><canvas id="op-agents"></canvas></div>
    <div class="op-legend">
      <span class="op-legend-item"><span class="op-legend-dot" style="background: var(--good);"></span>9.0 o más</span>
      <span class="op-legend-item"><span class="op-legend-dot" style="background: var(--warning);"></span>7.0 a 8.9</span>
      <span class="op-legend-item"><span class="op-legend-dot" style="background: var(--critical);"></span>Menos de 7.0</span>
    </div>
  </section>
  <?php endif; ?>

  <section class="op-slide" data-section="Cierre">
    <h2 class="op-title">Conclusiones y próximos pasos</h2>
    <p class="op-subtitle"><?= esc($period->label) ?></p>
    <hr class="op-rule">
  </section>

</div>

<p class="op-hint">&larr; &rarr; para navegar</p>
<div class="op-nav">
  <button type="button" id="op-prev" aria-label="Anterior">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
  </button>
  <button type="button" id="op-next" aria-label="Siguiente">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
  </button>
</div>

<script>
(function () {
  'use strict';

  // Mismo método de color que el dashboard (dataviz): estado fijo nunca se
  // reusa para una serie; identidad en orden fijo; magnitud en un solo tono.
  // Pasos oscuros (fondo #10131A): mismo slot 1 categórico que el resto del
  // informe, aclarado para legibilidad sobre tinta oscura.
  var CATEGORICAL = ['#3987e5', '#d95926', '#199e70'];
  var STATUS = { good: '#34C77B', warning: '#F0B429', critical: '#F0544C', info: '#3987e5' };
  // Rampa ordinal para el pipeline de estados: aquí se invierte a propósito
  // (mudo -> vívido) porque sobre fondo oscuro un tono más saturado es el
  // que "aparece": cerrado, el estado que importa resaltar, es el vívido.
  var STAGE_RAMP = ['#3A4356', '#4A5C7A', '#3987E5', '#5CA8FF'];

  var TEXT_MUTED = '#8B93A3';
  var GRID = 'rgba(255,255,255,0.06)';
  var FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";

  function stepsFrom(ramp, n) {
    if (n <= 1) { return [ramp[ramp.length - 1]]; }
    var out = [];
    for (var i = 0; i < n; i++) { out.push(ramp[Math.round((i * (ramp.length - 1)) / (n - 1))]); }
    return out;
  }
  function fmt(n) { return Number(n).toLocaleString('es-MX'); }

  function baseOptions(extra) {
    return Object.assign({
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { backgroundColor: '#0A0C10', titleFont: { family: FONT }, bodyFont: { family: FONT }, padding: 10, cornerRadius: 6 },
      },
    }, extra || {});
  }

  var PAYLOAD = <?= json_encode([
      'estados' => $glpi['available'] ?? false ? $glpi['estados_ticket'] : [],
      'regional' => $glpi['available'] ?? false ? $glpi['reg_top'] : [],
      'estado_geo' => $glpi['available'] ?? false ? array_slice($glpi['est_top'], 0, 10) : [],
      'estado_geo_bottom' => $glpi['available'] ?? false ? array_slice($glpi['est_bottom'], 0, 10) : [],
      'categorias' => $catShown ?? [],
      'ids' => $glpi['available'] ?? false ? array_slice($glpi['ids_top'], 0, 10) : [],
      'ids_bottom' => $glpi['available'] ?? false ? array_slice($glpi['ids_bottom'], 0, 10) : [],
      'trend' => $trend['available'] ?? false ? $trend['series'] : [],
      'dispositions' => $dispatch['available'] ?? false ? $dispatch['dispositions'] : [],
      'rules' => $quality['available'] ?? false ? array_slice($quality['rules'], 0, 8) : [],
      'agents' => $agents['available'] ?? false ? $agents['agents'] : [],
  ], JSON_UNESCAPED_UNICODE) ?>;

  function renderEstados() {
    var el = document.getElementById('op-estados');
    if (! el || ! PAYLOAD.estados.length) { return; }
    var labels = PAYLOAD.estados.map(function (r) { return r[0]; });
    var values = PAYLOAD.estados.map(function (r) { return r[1]; });
    var total = values.reduce(function (a, b) { return a + b; }, 0);
    var colors = stepsFrom(STAGE_RAMP, labels.length);
    new Chart(el, {
      type: 'bar',
      data: { labels: [''], datasets: labels.map(function (l, i) {
        return { label: l, data: [values[i]], backgroundColor: colors[i], borderRadius: 3, borderSkipped: false };
      }) },
      options: baseOptions({
        indexAxis: 'y',
        plugins: {
          legend: { display: false },
          tooltip: { backgroundColor: '#0A0C10', titleFont: { family: FONT }, bodyFont: { family: FONT }, padding: 10, cornerRadius: 6,
            callbacks: { label: function (c) { var pct = Math.round((c.parsed.x / total) * 100); return ' ' + c.dataset.label + ': ' + fmt(c.parsed.x) + ' (' + pct + '%)'; } } },
        },
        scales: { x: { stacked: true, display: false }, y: { stacked: true, display: false } },
      }),
    });
    var legend = document.getElementById('op-estados-legend');
    labels.forEach(function (l, i) {
      var pct = Math.round((values[i] / total) * 100);
      var span = document.createElement('span');
      span.className = 'op-legend-item';
      span.innerHTML = '<span class="op-legend-dot" style="background:' + colors[i] + ';"></span>' + l + ' (' + pct + '%)';
      legend.appendChild(span);
    });
  }

  /**
   * tiers (opcional): array paralelo 'group'|'child'|'single'; cuando viene,
   * el renglón de grupo se marca en negritas y el de hoja en tamaño menor y
   * tono apagado, para leer la jerarquía sin depender solo del texto.
   */
  function renderHbar(id, labels, values, color, tiers) {
    var el = document.getElementById(id);
    if (! el || ! labels.length) { return; }
    new Chart(el, {
      type: 'bar',
      data: { labels: labels, datasets: [{ data: values, backgroundColor: color || CATEGORICAL[0], borderRadius: 4, borderSkipped: false, maxBarThickness: 20 }] },
      options: baseOptions({
        indexAxis: 'y',
        scales: {
          x: { beginAtZero: true, ticks: { color: TEXT_MUTED, font: { family: FONT } }, grid: { color: GRID, drawTicks: false } },
          y: {
            ticks: {
              color: tiers ? function (c) { return tiers[c.index] === 'child' ? TEXT_MUTED : '#F2F4F7'; } : '#F2F4F7',
              font: tiers ? function (c) {
                return tiers[c.index] === 'child' ? { family: FONT, size: 11 } : { family: FONT, size: 13, weight: '600' };
              } : { family: FONT },
            },
            grid: { display: false },
          },
        },
      }),
    });
  }

  function renderTrend() {
    var el = document.getElementById('op-trend');
    if (! el || ! PAYLOAD.trend.length) { return; }
    new Chart(el, {
      type: 'line',
      data: {
        labels: PAYLOAD.trend.map(function (r) { return r.period; }),
        datasets: [
          { label: 'Total', data: PAYLOAD.trend.map(function (r) { return r.total; }), borderColor: CATEGORICAL[0], backgroundColor: CATEGORICAL[0], borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#10131A', pointBorderWidth: 2 },
          { label: 'Cerrados', data: PAYLOAD.trend.map(function (r) { return r.cerrados; }), borderColor: CATEGORICAL[1], backgroundColor: CATEGORICAL[1], borderWidth: 2, pointRadius: 3, pointBackgroundColor: '#10131A', pointBorderWidth: 2 },
        ],
      },
      options: baseOptions({
        plugins: { legend: { display: true, labels: { color: '#F2F4F7', font: { family: FONT }, boxWidth: 12, boxHeight: 12 } } },
        scales: {
          x: { ticks: { color: TEXT_MUTED, font: { family: FONT } }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { color: TEXT_MUTED, font: { family: FONT } }, grid: { color: GRID, drawTicks: false } },
        },
      }),
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') { return; }
    renderEstados();
    // Top/bottom del mismo ranking: mismo tono para "mayor carga" en todos los
    // paneles (CATEGORICAL[0]) y un segundo tono fijo para "menor carga"
    // (warning), igual que en el PPTX: el color distingue el extremo del
    // ranking que se está viendo, no un estado bueno/malo.
    renderHbar('op-regional', PAYLOAD.regional.map(function (r) { return r[0]; }), PAYLOAD.regional.map(function (r) { return r[1]; }));
    renderHbar('op-estado-geo', PAYLOAD.estado_geo.map(function (r) { return r[0]; }), PAYLOAD.estado_geo.map(function (r) { return r[1]; }));
    renderHbar('op-estado-geo-bottom', PAYLOAD.estado_geo_bottom.map(function (r) { return r[0]; }), PAYLOAD.estado_geo_bottom.map(function (r) { return r[1]; }), STATUS.warning);
    renderHbar('op-categorias',
      PAYLOAD.categorias.map(function (r) { return r.tier === 'child' ? '   - ' + r.label : r.label; }),
      PAYLOAD.categorias.map(function (r) { return r.value; }),
      PAYLOAD.categorias.map(function (r) { return r.tier === 'child' ? '#4A5C7A' : CATEGORICAL[0]; }),
      PAYLOAD.categorias.map(function (r) { return r.tier; }));
    renderHbar('op-ids', PAYLOAD.ids.map(function (r) { return r[0]; }), PAYLOAD.ids.map(function (r) { return r[1]; }));
    renderHbar('op-ids-bottom', PAYLOAD.ids_bottom.map(function (r) { return r[0]; }), PAYLOAD.ids_bottom.map(function (r) { return r[1]; }), STATUS.warning);
    renderTrend();
    renderHbar('op-dispatch', PAYLOAD.dispositions.map(function (r) { return r.disposition; }), PAYLOAD.dispositions.map(function (r) { return r.total; }));
    renderHbar('op-quality', PAYLOAD.rules.map(function (r) { return r.rule_name; }), PAYLOAD.rules.map(function (r) { return r.count; }),
      PAYLOAD.rules.map(function (r) { return r.severity === 'critical' ? STATUS.critical : (r.severity === 'warning' ? STATUS.warning : STATUS.info); }));
    renderHbar('op-agents', PAYLOAD.agents.map(function (r) { return r.agent_name; }), PAYLOAD.agents.map(function (r) { return Math.round(r.final_score || 0); }),
      PAYLOAD.agents.map(function (r) { var s = r.final_score || 0; return s >= 9 ? STATUS.good : (s >= 7 ? STATUS.warning : STATUS.critical); }));
  });
})();

(function () {
  var slides = Array.prototype.slice.call(document.querySelectorAll('.op-slide'));
  var idx = 0;

  function show(i) {
    idx = Math.max(0, Math.min(slides.length - 1, i));
    slides.forEach(function (s, j) { s.classList.toggle('is-active', j === idx); });
    document.getElementById('op-progress').style.width = ((idx + 1) / slides.length * 100) + '%';
    document.getElementById('op-bar-label').textContent = slides[idx].getAttribute('data-section') || '';
    document.getElementById('op-bar-pos').textContent = (idx + 1) + ' / ' + slides.length;
  }
  document.getElementById('op-prev').addEventListener('click', function () { show(idx - 1); });
  document.getElementById('op-next').addEventListener('click', function () { show(idx + 1); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight' || e.key === ' ') { e.preventDefault(); show(idx + 1); }
    if (e.key === 'ArrowLeft') { show(idx - 1); }
  });
  show(0);
})();
</script>
</body>
</html>
