<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
  <style>
    .source-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(320px, 380px));
      gap: var(--space-4);
    }

    .source-card,
    .source-card:hover,
    .source-card *,
    .source-card *:hover {
      text-decoration: none !important;
    }

    .source-card {
      display: flex;
      flex-direction: column;
      gap: var(--space-3);
      padding: var(--space-5);
      background: var(--bg-surface);
      border: 1px solid var(--color-neutral-200);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
      color: inherit;
      position: relative;
      overflow: hidden;
      transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .source-card::before {
      content: "";
      position: absolute;
      inset: 0 0 auto 0;
      height: 4px;
      background: var(--accent-color, var(--color-blue-500));
    }
    .source-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
      border-color: var(--accent-color, var(--color-blue-500));
    }
    .source-card.is-disabled {
      opacity: 0.55;
      pointer-events: none;
    }

    .source-card-head {
      display: flex;
      align-items: flex-start;
      gap: var(--space-3);
    }
    .source-icon {
      flex-shrink: 0;
      width: 44px;
      height: 44px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: var(--radius-md);
      background: var(--accent-bg, var(--color-blue-50));
      color: var(--accent-color, var(--color-blue-500));
    }
    .source-icon svg { width: 22px; height: 22px; }

    .source-card-title {
      font-size: var(--text-lg);
      font-weight: var(--weight-bold, 600);
      margin: 0;
      color: var(--text-primary);
    }
    .source-card-desc {
      font-size: var(--text-sm);
      color: var(--text-muted);
      margin: var(--space-1) 0 0 0;
      line-height: 1.5;
    }

    .source-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-3);
      padding-top: var(--space-3);
      border-top: 1px solid var(--color-neutral-100);
    }
    .source-stat-label {
      font-size: var(--text-xs);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-muted);
      margin: 0;
    }
    .source-stat-value {
      font-size: var(--text-xl);
      font-weight: var(--weight-bold, 700);
      color: var(--text-primary);
      margin: 2px 0 0 0;
      line-height: 1.2;
    }

    .source-card-foot {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: var(--text-sm);
      color: var(--text-muted);
    }
    .source-card-cta {
      color: var(--accent-color, var(--color-blue-500));
      font-weight: var(--weight-medium, 500);
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    /* Accent variants */
    .source-card.accent-blue   { --accent-color: var(--color-blue-500);  --accent-bg: var(--color-blue-50); }
    .source-card.accent-mint   { --accent-color: #00A39E;                --accent-bg: #E6F7F6; }
    .source-card.accent-violet { --accent-color: #7B61FF;                --accent-bg: #EEEAFF; }
  </style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$icons = [
    'ticket' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7v4a1 1 0 0 0 1 1 2 2 0 1 1 0 4 1 1 0 0 0-1 1v4h18v-4a1 1 0 0 0-1-1 2 2 0 1 1 0-4 1 1 0 0 0 1-1V7z"/><line x1="13" y1="5" x2="13" y2="7"/><line x1="13" y1="11" x2="13" y2="13"/><line x1="13" y1="17" x2="13" y2="19"/></svg>',
];
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">KPIs Operativos</h1>
    <p class="page-subtitle">Indicadores operativos por fuente de datos</p>
  </div>
</div>

<div class="source-grid">
  <?php foreach ($sources as $src):
    $accent = 'accent-' . ($src['accent'] ?? 'blue');
    $disabled = empty($src['available']);
  ?>
    <a href="<?= $disabled ? '#' : $src['url'] ?>"
       class="source-card <?= $accent ?> <?= $disabled ? 'is-disabled' : '' ?>">
      <div class="source-card-head">
        <div class="source-icon">
          <?= $icons[$src['icon'] ?? 'ticket'] ?? $icons['ticket'] ?>
        </div>
        <div style="flex: 1; min-width: 0;">
          <h2 class="source-card-title"><?= esc($src['name']) ?></h2>
          <p class="source-card-desc"><?= esc($src['description']) ?></p>
        </div>
      </div>

      <?php if (! $disabled): ?>
        <div class="source-stats">
          <div>
            <p class="source-stat-label">Reportes</p>
            <p class="source-stat-value"><?= number_format((int) ($src['reports_count'] ?? 0)) ?></p>
          </div>
          <div>
            <p class="source-stat-label">Tickets totales</p>
            <p class="source-stat-value"><?= number_format((int) ($src['total_tickets'] ?? 0)) ?></p>
          </div>
        </div>

        <div class="source-card-foot">
          <span>
            <?php if (! empty($src['latest']['name'])): ?>
              Último: <?= esc($src['latest']['name']) ?>
            <?php else: ?>
              Sin reportes aún
            <?php endif; ?>
          </span>
          <span class="source-card-cta">
            Abrir
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
            </svg>
          </span>
        </div>
      <?php else: ?>
        <p class="text-muted text-sm" style="margin:0;">Próximamente</p>
      <?php endif; ?>
    </a>
  <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
