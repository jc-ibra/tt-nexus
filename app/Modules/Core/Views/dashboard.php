<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  .dash-hero {
    padding: var(--space-2) 0 var(--space-6);
    margin-bottom: var(--space-6);
    border-bottom: var(--border-width-default) solid var(--color-neutral-200);
  }
  .dash-hero h1 {
    color: var(--text-primary); margin: 0 0 var(--space-1);
    font-size: var(--text-2xl); font-weight: var(--weight-semibold); letter-spacing: -0.01em;
  }
  .dash-hero p { color: var(--text-muted); margin: 0; font-size: var(--text-md); }
  .dash-hero .role-chip {
    display:inline-flex; align-items:center; gap: var(--space-2);
    margin-top: var(--space-4); padding: 4px 12px; border-radius: var(--radius-full);
    background: var(--bg-surface-alt); border: var(--border-width-default) solid var(--color-neutral-200);
    color: var(--text-secondary); font-size: var(--text-xs); font-weight: var(--weight-medium);
  }
  .dash-hero .role-chip::before {
    content: ""; width: 6px; height: 6px; border-radius: 50%;
    background: var(--color-blue-500); flex-shrink: 0;
  }
  .dash-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: var(--space-4); }
  .dash-tile {
    display:flex; flex-direction:column; gap: var(--space-2);
    background: var(--bg-surface); border: var(--border-width-default) solid var(--color-neutral-200);
    border-radius: var(--radius-md); box-shadow: var(--shadow-sm);
    padding: var(--space-4); text-decoration:none; color: inherit;
    transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
  }
  .dash-tile:hover { box-shadow: var(--shadow-md, 0 6px 18px rgba(0,0,0,.10)); transform: translateY(-2px); border-color: var(--color-primary-300, #7bb0e0); }
  .dash-tile, .dash-tile:hover, .dash-tile:focus, .dash-tile * { text-decoration: none !important; }
  .dash-tile .tile-icon {
    width:44px; height:44px; border-radius: 10px; display:flex; align-items:center; justify-content:center;
    background: var(--color-primary-50, #e8f1fb); color: var(--color-primary-600, #135ba1);
  }
  .dash-tile .tile-icon svg { width:24px; height:24px; }
  .dash-tile h3 { margin: var(--space-1) 0 0; font-size: 1.05rem; }
  .dash-tile .tile-desc { color: var(--text-muted); font-size: var(--text-sm); flex:1; }
  .dash-tile .tile-open { color: var(--color-primary-600, #135ba1); font-weight:600; font-size: var(--text-sm); }
  .dash-quick { display:flex; flex-wrap:wrap; gap: var(--space-2); margin-top: var(--space-3); }

  .dash-admin { display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: var(--space-3); }
  .dash-admin-tile {
    display:flex; align-items:center; gap: var(--space-3);
    background: var(--bg-surface); border: var(--border-width-default) solid var(--color-neutral-200);
    border-radius: var(--radius-md); box-shadow: var(--shadow-sm);
    padding: var(--space-3) var(--space-4); text-decoration:none; color: inherit;
    transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
  }
  .dash-admin-tile:hover { box-shadow: var(--shadow-md, 0 6px 18px rgba(0,0,0,.10)); transform: translateY(-2px); border-color: var(--color-primary-300, #7bb0e0); }
  .dash-admin-tile, .dash-admin-tile:hover, .dash-admin-tile:focus, .dash-admin-tile * { text-decoration: none !important; }
  .dash-admin-tile .ai-icon {
    width:40px; height:40px; border-radius:10px; flex:none; display:flex; align-items:center; justify-content:center;
    background: var(--color-neutral-100, #f1f5f9); color: var(--text-default, #334155);
  }
  .dash-admin-tile .ai-icon svg { width:20px; height:20px; }
  .dash-admin-tile .ai-title { font-weight:600; font-size:.98rem; display:block; }
  .dash-admin-tile .ai-sub { color: var(--text-muted); font-size: var(--text-sm); display:block; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$name = trim((string) (session()->get('user_name') ?? '')) ?: 'usuario';
$first = explode(' ', $name)[0];

$fallbackIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>';
$icons = [
  'communications'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
  'kpis_operativos' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/></svg>',
  'mailboxes'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>',
  'employees'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'provisioning'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
  'servicedesk'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14v-2a9 9 0 0 1 18 0v2"/><path d="M21 16a2 2 0 0 1-2 2h-1a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h3z"/><path d="M3 16a2 2 0 0 0 2 2h1a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1H3z"/><path d="M12 18v1a3 3 0 0 1-3 3"/></svg>',
  'helpdesk_supervisor' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
  'agent_kpis'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="5"/><path d="M8.5 12.5L7 22l5-3 5 3-1.5-9.5"/></svg>',
];

$accessibleModuleKeys = array_column($modules, 'key');
$mergeAgentKpisHome = in_array('helpdesk_supervisor', $accessibleModuleKeys, true)
    && in_array('agent_kpis', $accessibleModuleKeys, true);

$homeModules = [];
foreach ($modules as $m) {
    if ($mergeAgentKpisHome && ($m['key'] ?? '') === 'agent_kpis') {
        continue;
    }
    if ($mergeAgentKpisHome && ($m['key'] ?? '') === 'helpdesk_supervisor') {
        $m['description'] = 'Auditoría MAC, resumen GLPI, desviaciones por agente y evaluación mensual N1.';
    }
    $homeModules[] = $m;
}
?>

<div class="dash-hero">
  <h1>Hola, <?= esc($first) ?></h1>
  <p>Bienvenido a Nexus. Selecciona un módulo para comenzar a trabajar.</p>
  <?php if (! empty($activeRole['name'])): ?>
    <span class="role-chip"><?= esc($activeRole['name']) ?></span>
  <?php endif; ?>
</div>

<div class="page-header" style="margin-bottom: var(--space-3);">
  <div class="page-header-content">
    <h2 class="card-title" style="margin:0;">Tus módulos</h2>
    <p class="page-subtitle">Accesos directos a los módulos habilitados para tu rol.</p>
  </div>
</div>

<?php if (empty($homeModules)): ?>
  <div class="card"><div class="card-body">
    <p class="text-muted" style="margin:0;">Aún no tienes módulos asignados. Contacta a un administrador para que te dé acceso.</p>
  </div></div>
<?php else: ?>
  <div class="dash-grid">
    <?php foreach ($homeModules as $m): ?>
      <a href="<?= base_url(ltrim($m['route_base'], '/')) ?>" class="dash-tile">
        <span class="tile-icon"><?= $icons[$m['key']] ?? $fallbackIcon ?></span>
        <h3><?= esc($m['name']) ?></h3>
        <span class="tile-desc"><?= esc($m['description'] ?? '') ?></span>
        <span class="tile-open">Abrir &rarr;</span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($isSuperAdmin): ?>
  <div class="page-header" style="margin: var(--space-5) 0 var(--space-3);">
    <div class="page-header-content">
      <h2 class="card-title" style="margin:0;">Administración</h2>
      <p class="page-subtitle">Gestión de la plataforma.</p>
    </div>
  </div>
  <div class="dash-admin">
    <a href="<?= route_to('admin.users.index') ?>" class="dash-admin-tile">
      <span class="ai-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
      <span><span class="ai-title">Usuarios</span><span class="ai-sub">Altas, accesos y estado</span></span>
    </a>
    <a href="<?= route_to('admin.roles.index') ?>" class="dash-admin-tile">
      <span class="ai-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg></span>
      <span><span class="ai-title">Roles</span><span class="ai-sub">Permisos por módulo</span></span>
    </a>
    <a href="<?= route_to('provisioning.systems.index') ?>" class="dash-admin-tile">
      <span class="ai-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="3" width="20" height="7" rx="1.5"/><rect x="2" y="14" width="20" height="7" rx="1.5"/><line x1="6" y1="6.5" x2="6.01" y2="6.5"/><line x1="6" y1="17.5" x2="6.01" y2="17.5"/></svg></span>
      <span><span class="ai-title">Sistemas</span><span class="ai-sub">GLPI, Mailcow, Intranet</span></span>
    </a>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
