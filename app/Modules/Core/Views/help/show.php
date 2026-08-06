<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  .help-breadcrumb {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    font-size: var(--text-sm);
    color: var(--text-muted);
    margin-bottom: var(--space-4);
  }
  .help-breadcrumb a { color: var(--text-muted); text-decoration: none; }
  .help-breadcrumb a:hover { color: var(--text-link); text-decoration: underline; }
  .help-breadcrumb svg { width: 14px; height: 14px; }

  .help-guide-header {
    display: flex;
    align-items: flex-start;
    gap: var(--space-4);
    margin-bottom: var(--space-6);
  }
  .help-guide-icon {
    flex-shrink: 0;
    width: 48px; height: 48px;
    display: inline-flex;
    align-items: center; justify-content: center;
    border-radius: var(--radius-md);
    background: var(--color-blue-50, #EAF3FB);
    color: var(--color-blue-500);
  }
  .help-guide-icon svg { width: 26px; height: 26px; }
  .help-guide-header h1 { font-size: var(--text-2xl); font-weight: var(--weight-bold, 700); color: var(--text-primary); }
  .help-guide-header p { font-size: var(--text-md); color: var(--text-muted); margin-top: 2px; max-width: 640px; }

  .help-layout {
    display: grid;
    grid-template-columns: 232px minmax(0, 1fr);
    gap: var(--space-6);
    align-items: start;
  }

  .help-toc {
    position: sticky;
    top: var(--space-4);
  }
  .help-toc-label {
    font-size: var(--text-xs);
    font-weight: var(--weight-semibold);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    margin-bottom: var(--space-2);
    padding-left: var(--space-3);
  }
  .help-toc a {
    display: block;
    padding: var(--space-2) var(--space-3);
    font-size: var(--text-base);
    color: var(--text-secondary);
    text-decoration: none;
    border-left: 2px solid transparent;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    transition: color var(--motion-fast, 120ms) ease, border-color var(--motion-fast, 120ms) ease, background var(--motion-fast, 120ms) ease;
  }
  .help-toc a:hover { color: var(--text-primary); background: var(--color-neutral-50); text-decoration: none; }
  .help-toc a.is-active {
    color: var(--color-blue-500);
    border-left-color: var(--color-blue-500);
    background: var(--color-blue-50, #EAF3FB);
    font-weight: var(--weight-medium);
  }

  /* ---- Article content (classes reused by every guide content view) ---- */
  .help-article { max-width: 760px; }
  .help-article section { scroll-margin-top: var(--space-4); margin-bottom: var(--space-8); }
  .help-article h2 {
    font-size: var(--text-xl);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    padding-bottom: var(--space-2);
    margin-bottom: var(--space-3);
    border-bottom: 1px solid var(--color-neutral-200);
  }
  .help-article h3 {
    font-size: var(--text-md);
    font-weight: var(--weight-semibold);
    color: var(--text-primary);
    margin: var(--space-5) 0 var(--space-2);
  }
  .help-article p { color: var(--text-secondary); line-height: var(--leading-relaxed, 1.6); margin-bottom: var(--space-3); }
  .help-article ul { margin: 0 0 var(--space-3) var(--space-5); color: var(--text-secondary); line-height: var(--leading-relaxed, 1.6); }
  .help-article ul li { margin-bottom: var(--space-1); }
  .help-article strong { color: var(--text-primary); font-weight: var(--weight-semibold); }
  .help-article code {
    background: var(--color-neutral-100);
    padding: 1px 6px;
    border-radius: var(--radius-sm);
    font-size: 0.85em;
    color: var(--text-primary);
  }
  .help-kbd {
    display: inline-flex;
    align-items: center;
    gap: var(--space-1);
    background: var(--color-neutral-100);
    border: 1px solid var(--color-neutral-200);
    border-radius: var(--radius-sm);
    padding: 1px 6px;
    font-size: var(--text-sm);
    color: var(--text-primary);
    font-weight: var(--weight-medium);
  }
  .help-kbd svg { width: 13px; height: 13px; }

  /* Numbered steps */
  .help-steps { list-style: none; margin: 0 0 var(--space-4); padding: 0; counter-reset: help-step; }
  .help-steps > li {
    position: relative;
    padding: 0 0 var(--space-4) var(--space-6);
    border-left: 2px solid var(--color-neutral-200);
    margin-left: 12px;
  }
  .help-steps > li:last-child { border-left-color: transparent; padding-bottom: 0; }
  .help-steps > li::before {
    counter-increment: help-step;
    content: counter(help-step);
    position: absolute;
    left: -13px; top: -2px;
    width: 24px; height: 24px;
    display: flex; align-items: center; justify-content: center;
    background: var(--color-blue-500);
    color: #fff;
    border-radius: 50%;
    font-size: var(--text-xs);
    font-weight: var(--weight-bold, 700);
  }
  .help-steps > li > strong { display: block; margin-bottom: 2px; }
  .help-steps > li p { margin-bottom: 0; }

  /* Callouts */
  .help-callout {
    display: flex;
    gap: var(--space-3);
    padding: var(--space-3) var(--space-4);
    border-radius: var(--radius-md);
    border: 1px solid transparent;
    margin: var(--space-4) 0;
    font-size: var(--text-base);
    line-height: var(--leading-normal);
  }
  .help-callout svg { flex-shrink: 0; width: 20px; height: 20px; margin-top: 1px; }
  .help-callout p { margin: 0; color: inherit; }
  .help-callout strong { color: inherit; }
  .help-callout-tip     { background: var(--color-success-surface); border-color: #B8E0D4; color: var(--color-success-strong); }
  .help-callout-info    { background: var(--color-info-surface, #EAF3FB); border-color: var(--color-blue-200, #B3D4F0); color: var(--color-blue-800, #115EA3); }
  .help-callout-warning { background: var(--color-warning-surface); border-color: #FFDF99; color: var(--color-warning-strong); }

  /* FAQ accordion (native details/summary) */
  .help-faq details {
    border: 1px solid var(--color-neutral-200);
    border-radius: var(--radius-md);
    margin-bottom: var(--space-2);
    background: var(--bg-surface);
    overflow: hidden;
  }
  .help-faq summary {
    cursor: pointer;
    list-style: none;
    padding: var(--space-3) var(--space-4);
    font-weight: var(--weight-medium);
    color: var(--text-primary);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-3);
  }
  .help-faq summary::-webkit-details-marker { display: none; }
  .help-faq summary .help-faq-chevron {
    flex-shrink: 0;
    width: 18px; height: 18px;
    color: var(--text-muted);
    transition: transform var(--motion-fast, 150ms) ease;
  }
  .help-faq details[open] summary .help-faq-chevron { transform: rotate(180deg); }
  .help-faq details[open] summary { border-bottom: 1px solid var(--color-neutral-200); }
  .help-faq-body { padding: var(--space-3) var(--space-4); }
  .help-faq-body p { margin: 0; color: var(--text-secondary); line-height: var(--leading-relaxed, 1.6); }

  .help-footer-card {
    margin-top: var(--space-6);
    display: flex;
    align-items: center;
    gap: var(--space-3);
    flex-wrap: wrap;
  }

  @media (max-width: 860px) {
    .help-layout { grid-template-columns: 1fr; }
    .help-toc { position: static; margin-bottom: var(--space-4); }
    .help-toc-nav { display: flex; flex-wrap: wrap; gap: var(--space-1); }
    .help-toc-nav a { border-left: none; border: 1px solid var(--color-neutral-200); border-radius: var(--radius-full); }
    .help-toc-nav a.is-active { border-color: var(--color-blue-500); }
  }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<nav class="help-breadcrumb" aria-label="Ruta de navegación">
  <a href="<?= route_to('help.index') ?>">Centro de ayuda</a>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
  <span><?= esc($topic['title']) ?></span>
</nav>

<div class="help-guide-header">
  <span class="help-guide-icon"><?= $topic['icon'] ?></span>
  <div>
    <h1><?= esc($topic['title']) ?></h1>
    <p><?= esc($topic['summary']) ?></p>
  </div>
</div>

<div class="help-layout">
  <aside class="help-toc">
    <p class="help-toc-label">En esta guía</p>
    <nav class="help-toc-nav" aria-label="Contenido de la guía">
      <?php foreach ($topic['sections'] as $i => $section): ?>
        <a href="#<?= esc($section['id'], 'attr') ?>" data-toc-link="<?= esc($section['id'], 'attr') ?>" class="<?= $i === 0 ? 'is-active' : '' ?>">
          <?= esc($section['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>

  <div class="help-article">
    <?= $this->include($topic['view']) ?>

    <div class="card help-footer-card">
      <div class="card-body" style="display:flex; align-items:center; gap:var(--space-3); flex-wrap:wrap; width:100%;">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="var(--color-blue-500)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span style="flex:1; min-width:200px; color:var(--text-secondary); font-size:var(--text-base);">
          ¿Aún tienes dudas? Escríbele al equipo de Sistemas y con gusto te ayudamos.
        </span>
        <a href="<?= route_to('help.index') ?>" class="btn btn-tertiary btn-sm">Volver al centro de ayuda</a>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  var links = Array.prototype.slice.call(document.querySelectorAll('[data-toc-link]'));
  if (!links.length) return;

  var byId = {};
  links.forEach(function (l) { byId[l.getAttribute('data-toc-link')] = l; });

  var sections = links
    .map(function (l) { return document.getElementById(l.getAttribute('data-toc-link')); })
    .filter(Boolean);

  function setActive(id) {
    links.forEach(function (l) { l.classList.remove('is-active'); });
    if (byId[id]) byId[id].classList.add('is-active');
  }

  // The app shell scrolls the window (the main column grows with its content and
  // never overflows), so a native #hash jump targets the wrong element. Resolve
  // the element that actually scrolls and move it ourselves, with a top offset.
  function scrollerFor(el) {
    var n = el.parentElement;
    while (n && n !== document.body) {
      var oy = getComputedStyle(n).overflowY;
      if ((oy === 'auto' || oy === 'scroll') && n.scrollHeight > n.clientHeight + 1) return n;
      n = n.parentElement;
    }
    return window;
  }
  function currentTop(s)   { return s === window ? window.pageYOffset : s.scrollTop; }
  function applyTop(s, v)  { s === window ? window.scrollTo(0, v) : (s.scrollTop = v); }

  // Native smooth scrolling is unreliable across environments, so animate the
  // (instant, always-supported) scroll position ourselves. Driven by setTimeout
  // rather than requestAnimationFrame so it also runs where rAF is throttled.
  var now = (window.performance && performance.now) ? function () { return performance.now(); } : function () { return +new Date(); };
  function animateScroll(scroller, to) {
    var start = currentTop(scroller);
    var dist  = to - start;
    if (Math.abs(dist) < 2) { applyTop(scroller, to); return; }
    var dur = 340, t0 = null;
    function ease(p) { return p < 0.5 ? 2 * p * p : -1 + (4 - 2 * p) * p; }
    function step() {
      if (t0 === null) t0 = now();
      var p = Math.min(1, (now() - t0) / dur);
      applyTop(scroller, start + dist * ease(p));
      if (p < 1) setTimeout(step, 16); else applyTop(scroller, to);
    }
    step();
  }

  var OFFSET = 24;
  links.forEach(function (l) {
    l.addEventListener('click', function (e) {
      var id = l.getAttribute('data-toc-link');
      var target = document.getElementById(id);
      if (!target) return;
      e.preventDefault();
      var scroller = scrollerFor(target);
      var rel = scroller === window
        ? target.getBoundingClientRect().top + window.pageYOffset
        : target.getBoundingClientRect().top - scroller.getBoundingClientRect().top + scroller.scrollTop;
      animateScroll(scroller, Math.max(0, rel - OFFSET));
      if (history.replaceState) history.replaceState(null, '', '#' + id);
      setActive(id);
      spyLock = now() + 400; // don't let the scroll spy fight the click animation
    });
  });

  // Scrollspy: highlight the section whose top has passed a line near the top of
  // the viewport. A plain scroll listener works where IntersectionObserver/rAF
  // are throttled, and covers both a window- and an element-based scroller.
  var scroller = sections.length ? scrollerFor(sections[0]) : window;
  var spyLock = 0, ticking = false;
  function updateSpy() {
    ticking = false;
    if (now() < spyLock) return;
    var line = 96, activeId = sections[0] ? sections[0].id : null;
    for (var i = 0; i < sections.length; i++) {
      if (sections[i].getBoundingClientRect().top - line <= 1) activeId = sections[i].id;
      else break;
    }
    if (activeId) setActive(activeId);
  }
  function onScroll() {
    if (ticking) return;
    ticking = true;
    setTimeout(updateSpy, 60);
  }
  (scroller === window ? window : scroller).addEventListener('scroll', onScroll, { passive: true });
  updateSpy();
})();
</script>
<?= $this->endSection() ?>
