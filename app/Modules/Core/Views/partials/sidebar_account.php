<?php
  $userName   = session()->get('user_name')  ?? '';
  $userEmail  = session()->get('user_email') ?? '';
  $parts      = explode(' ', trim($userName));
  $initials   = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));

  $access     = service('access');
  $userRoles  = $access->getUserRoles();
  $activeRole = $access->getActiveRole();
?>
<div class="user-menu sidebar-account" id="user-menu">
  <button class="sidebar-account-trigger" id="user-menu-btn" aria-haspopup="true" aria-expanded="false" aria-controls="user-menu-dropdown">
    <span class="user-avatar" aria-hidden="true"><?= esc($initials) ?></span>
    <span class="sidebar-account-meta">
      <span class="sidebar-account-name"><?= esc($userName) ?></span>
      <?php if ($activeRole !== null): ?>
        <span class="sidebar-account-role"><?= esc($activeRole['name']) ?></span>
      <?php endif; ?>
    </span>
    <svg class="user-menu-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
  </button>

  <div class="user-menu-dropdown" id="user-menu-dropdown" role="menu" aria-labelledby="user-menu-btn">
    <div class="user-menu-profile">
      <span class="user-avatar user-avatar-lg" aria-hidden="true"><?= esc($initials) ?></span>
      <div class="user-menu-info">
        <p class="user-menu-fullname"><?= esc($userName) ?></p>
        <p class="user-menu-email"><?= esc($userEmail) ?></p>
      </div>
    </div>

    <?php if ($activeRole !== null): ?>
      <div class="user-menu-divider"></div>
      <div class="user-menu-roles">
        <p class="user-menu-section-label">Rol activo</p>
        <p class="user-menu-active-role"><?= esc($activeRole['name']) ?></p>

        <?php if (count($userRoles) > 1): ?>
          <p class="user-menu-section-label" style="margin-top: var(--space-3);">Cambiar rol</p>
          <?php foreach ($userRoles as $role): ?>
            <?php if ((int) $role['id'] === (int) $activeRole['id']) { continue; } ?>
            <form action="<?= route_to('switch-role') ?>" method="post" class="user-menu-role-form">
              <?= csrf_field() ?>
              <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
              <button type="submit" class="user-menu-item user-menu-role-btn" role="menuitem">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                <?= esc($role['name']) ?>
              </button>
            </form>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
(function () {
  const btn      = document.getElementById('user-menu-btn');
  const dropdown = document.getElementById('user-menu-dropdown');
  if (!btn || !dropdown) return;

  function open()  { dropdown.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true'); }
  function close() { dropdown.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }
  function toggle() { dropdown.classList.contains('is-open') ? close() : open(); }

  btn.addEventListener('click', (e) => { e.stopPropagation(); toggle(); });
  dropdown.addEventListener('click', (e) => e.stopPropagation());
  document.addEventListener('click', () => close());
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
