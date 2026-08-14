<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($pageTitle ?? 'Dashboard') ?> - Nexus</title>
  <link rel="icon" type="image/png" href="<?= base_url('img/tt-icon.png') ?>">
  <link rel="stylesheet" href="<?= asset_url('css/app.css') ?>">
  <?= $this->include('App\Modules\Core\Views\partials\pwa') ?>
  <?= $this->renderSection('head') ?>
</head>
<body>
<div class="app-shell" id="app-shell">
  <script>
    // Restaura el estado colapsado del sidebar antes de pintar (evita parpadeo).
    try { if (localStorage.getItem('nx_sidebar_collapsed') === '1') document.getElementById('app-shell').classList.add('sidebar-collapsed'); } catch (e) {}
  </script>

  <!-- Sidebar -->
  <aside class="app-sidebar">
    <div class="sidebar-header">
      <a href="<?= route_to('dashboard') ?>" class="sidebar-brand" title="Nexus">
        <img src="<?= base_url('img/tt-icon.png') ?>" alt="" aria-hidden="true" class="sidebar-brand-icon">
        <span class="sidebar-brand-name">Nexus</span>
      </a>
      <button type="button" class="sidebar-collapse-btn" id="sidebar-collapse" aria-label="Colapsar menú" title="Colapsar menú">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>
      </button>
    </div>

    <nav class="sidebar-nav">
      <?= $this->include('App\Modules\Core\Views\partials\sidebar') ?>
    </nav>

    <div class="sidebar-footer">
      <?= $this->include('App\Modules\Core\Views\partials\sidebar_account') ?>
      <a href="<?= route_to('logout') ?>" class="nav-item sidebar-logout" title="Cerrar sesión">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <span class="nav-label">Cerrar sesión</span>
      </a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="app-main">
    <?= $this->include('App\Modules\Core\Views\partials\flash') ?>
    <?= $this->renderSection('content') ?>
  </main>

</div>
<script src="<?= asset_url('js/app.js') ?>"></script>
<script>
(function () {
  var shell = document.getElementById('app-shell');
  var btn   = document.getElementById('sidebar-collapse');
  if (!shell || !btn) return;
  var KEY = 'nx_sidebar_collapsed';

  function sync() {
    var collapsed = shell.classList.contains('sidebar-collapsed');
    var label = collapsed ? 'Expandir menú' : 'Colapsar menú';
    btn.setAttribute('aria-label', label);
    btn.setAttribute('title', label);
  }
  sync();

  btn.addEventListener('click', function () {
    shell.classList.toggle('sidebar-collapsed');
    try { localStorage.setItem(KEY, shell.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch (e) {}
    sync();
  });

  // Estando colapsado, tocar el ícono de un módulo expande el sidebar y abre ese
  // grupo (el handler de app.js se encarga del is-open al burbujear).
  document.querySelectorAll('[data-nav-toggle]').forEach(function (t) {
    t.addEventListener('click', function () {
      if (shell.classList.contains('sidebar-collapsed')) {
        shell.classList.remove('sidebar-collapsed');
        try { localStorage.setItem(KEY, '0'); } catch (e) {}
        sync();
      }
    }, true); // captura: corre antes del toggle de app.js
  });
})();
</script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
