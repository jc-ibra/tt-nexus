<header class="app-topnav">
  <a href="<?= route_to('dashboard') ?>" class="topnav-brand">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
      <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
    </svg>
    Nexus
  </a>

  <div class="topnav-spacer"></div>

  <div class="topnav-user">
    <span><?= esc(session()->get('user_name') ?? '') ?></span>
    <span>·</span>
    <a href="<?= route_to('logout') ?>">Salir</a>
  </div>
</header>
