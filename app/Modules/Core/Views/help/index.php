<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  .help-hero {
    position: relative;
    overflow: hidden;
    border-radius: var(--radius-lg);
    padding: var(--space-8) var(--space-6);
    margin-bottom: var(--space-6);
    background: linear-gradient(135deg, var(--color-blue-500) 0%, var(--color-blue-600) 100%);
    color: var(--text-inverse);
  }
  .help-hero::after {
    content: "";
    position: absolute;
    right: -60px; top: -60px;
    width: 260px; height: 260px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
  }
  .help-hero-inner { position: relative; z-index: 1; max-width: 640px; }
  .help-hero h1 {
    font-size: var(--text-3xl);
    font-weight: var(--weight-bold, 700);
    margin-bottom: var(--space-2);
    color: var(--text-inverse);
  }
  .help-hero p { font-size: var(--text-md); opacity: 0.92; margin-bottom: var(--space-5); }
  .help-search {
    position: relative;
    max-width: 460px;
  }
  .help-search svg {
    position: absolute;
    left: var(--space-3); top: 50%;
    transform: translateY(-50%);
    width: 18px; height: 18px;
    color: var(--text-muted);
    pointer-events: none;
  }
  .help-search input {
    width: 100%;
    padding: var(--space-3) var(--space-3) var(--space-3) calc(var(--space-3) * 2 + 18px);
    border: 1px solid transparent;
    border-radius: var(--radius-md);
    background: #fff;
    color: var(--text-primary);
    font-size: var(--text-base);
    box-shadow: var(--shadow-sm);
  }
  .help-search input:focus { outline: none; box-shadow: var(--shadow-focus); }

  .help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: var(--space-4);
  }
  .help-card {
    display: flex;
    flex-direction: column;
    gap: var(--space-3);
    padding: var(--space-5);
    background: var(--bg-surface);
    border: 1px solid var(--color-neutral-200);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-xs);
    text-decoration: none;
    color: inherit;
    transition: transform var(--motion-fast, 120ms) ease, box-shadow var(--motion-fast, 120ms) ease, border-color var(--motion-fast, 120ms) ease;
  }
  .help-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    border-color: var(--color-blue-200, #B3D4F0);
    text-decoration: none;
  }
  .help-card-icon {
    width: 44px; height: 44px;
    display: inline-flex;
    align-items: center; justify-content: center;
    border-radius: var(--radius-md);
    background: var(--color-blue-50, #EAF3FB);
    color: var(--color-blue-500);
  }
  .help-card-icon svg { width: 24px; height: 24px; }
  .help-card-title {
    font-size: var(--text-lg);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
  }
  .help-card-summary {
    font-size: var(--text-base);
    color: var(--text-muted);
    line-height: var(--leading-normal);
    flex: 1;
  }
  .help-card-meta {
    display: inline-flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-sm);
    color: var(--color-blue-500);
    font-weight: var(--weight-medium);
  }
  .help-card-meta svg { width: 16px; height: 16px; }
  .help-empty {
    text-align: center;
    padding: var(--space-8) var(--space-4);
    color: var(--text-muted);
  }

  @media (max-width: 600px) {
    .help-hero { padding: var(--space-6) var(--space-4); }
    .help-hero h1 { font-size: var(--text-2xl); }
  }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="help-hero">
  <div class="help-hero-inner">
    <h1>Centro de ayuda</h1>
    <p>Guías paso a paso para sacarle el máximo provecho a la plataforma. Elige un tema o busca lo que necesitas.</p>
    <div class="help-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="search" id="help-search-input" placeholder="Buscar en la ayuda…" aria-label="Buscar en la ayuda" autocomplete="off">
    </div>
  </div>
</div>

<?php if (empty($topics)): ?>
  <div class="card">
    <div class="help-empty">
      <p>Aún no hay guías disponibles para tu perfil.</p>
    </div>
  </div>
<?php else: ?>
  <div class="help-grid" id="help-grid">
    <?php foreach ($topics as $topic): ?>
      <a href="<?= route_to('help.show', $topic['key']) ?>"
         class="help-card"
         data-search="<?= esc(mb_strtolower($topic['title'] . ' ' . $topic['summary']), 'attr') ?>">
        <span class="help-card-icon"><?= $topic['icon'] ?></span>
        <span class="help-card-title"><?= esc($topic['title']) ?></span>
        <span class="help-card-summary"><?= esc($topic['summary']) ?></span>
        <span class="help-card-meta">
          <?= count($topic['sections']) ?> <?= count($topic['sections']) === 1 ? 'sección' : 'secciones' ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="help-empty" id="help-no-results" style="display:none;">
    <p>No encontramos guías que coincidan con tu búsqueda.</p>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var input = document.getElementById('help-search-input');
  var grid  = document.getElementById('help-grid');
  if (!input || !grid) return;
  var cards    = Array.prototype.slice.call(grid.querySelectorAll('.help-card'));
  var noResult = document.getElementById('help-no-results');

  input.addEventListener('input', function () {
    var q = input.value.trim().toLowerCase();
    var visible = 0;
    cards.forEach(function (card) {
      var hit = q === '' || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
      card.style.display = hit ? '' : 'none';
      if (hit) visible++;
    });
    if (noResult) noResult.style.display = visible === 0 ? '' : 'none';
  });
})();
</script>
<?= $this->endSection() ?>
