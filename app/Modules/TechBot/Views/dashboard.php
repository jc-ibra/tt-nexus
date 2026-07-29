<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  .tb-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:var(--space-4); margin-bottom:var(--space-5); }
  .tb-kpi { background:var(--surface); border:1px solid var(--border-subtle); border-radius:var(--radius-2); padding:var(--space-4); }
  .tb-kpi-value { font-size:var(--font-size-2xl,28px); font-weight:700; color:var(--text-primary); }
  .tb-kpi-label { color:var(--text-subdued); font-size:var(--font-size-sm); margin-top:4px; }
  .tb-cols { display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4); align-items:start; }
  @media (max-width: 900px){ .tb-cols { grid-template-columns:1fr; } }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">TechBot</h1>
    <p class="page-subtitle">Canal de Telegram para que los técnicos de campo documenten sus tickets de GLPI.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('techbot.links') ?>" class="btn btn-secondary">Técnicos</a>
    <a href="<?= route_to('techbot.settings') ?>" class="btn btn-primary">Configuración</a>
  </div>
</div>

<?php if (! $botReady): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body">El bot no está listo. Configura el token en <a href="<?= route_to('techbot.settings') ?>">Configuración</a> y actívalo para empezar a recibir mensajes.</div>
  </div>
<?php endif; ?>

<div class="tb-kpis">
  <div class="tb-kpi"><div class="tb-kpi-value"><?= (int) $activeCount ?></div><div class="tb-kpi-label">Técnicos activos</div></div>
  <div class="tb-kpi"><div class="tb-kpi-value"><?= (int) $inactiveCount ?></div><div class="tb-kpi-label">Desactivados</div></div>
  <div class="tb-kpi"><div class="tb-kpi-value"><?= (int) $actionsToday ?></div><div class="tb-kpi-label">Acciones hoy</div></div>
  <div class="tb-kpi"><div class="tb-kpi-value"><?= (int) $actionsWeek ?></div><div class="tb-kpi-label">Esta semana</div></div>
  <div class="tb-kpi"><div class="tb-kpi-value"><?= (int) $actionsMonth ?></div><div class="tb-kpi-label">Este mes</div></div>
  <div class="tb-kpi"><div class="tb-kpi-value"><?= (int) $errorsToday ?></div><div class="tb-kpi-label">Errores hoy</div></div>
</div>

<div class="tb-cols">
  <div class="card">
    <div class="card-header"><h2 class="card-title">Actividad reciente</h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($recentActivity)): ?>
        <p class="text-muted" style="padding: var(--space-4);">Sin actividad todavía.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead><tr><th>Técnico</th><th>Ticket</th><th>Acción</th><th>Resultado</th><th>Fecha</th></tr></thead>
          <tbody>
            <?php foreach ($recentActivity as $a): ?>
              <tr>
                <td class="text-sm"><?= esc(trim(($a['employee_name'] ?? '') . ' ' . ($a['employee_lastname'] ?? '')) ?: '—') ?></td>
                <td class="text-sm"><a href="<?= route_to('techbot.activity') ?>?ticket=<?= (int) $a['glpi_ticket_id'] ?>">#<?= (int) $a['glpi_ticket_id'] ?></a></td>
                <td class="text-sm"><?= esc($a['action']) ?></td>
                <td><?= ($a['result'] ?? '') === 'error' ? '<span class="badge badge-critical">Error</span>' : '<span class="badge badge-success">OK</span>' ?></td>
                <td class="text-muted text-sm"><?= esc($a['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    <div class="card-footer" style="text-align:right;"><a href="<?= route_to('techbot.activity') ?>" class="text-sm">Ver todo el registro ›</a></div>
  </div>

  <div class="card">
    <div class="card-header"><h2 class="card-title">Errores recientes</h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($recentErrors)): ?>
        <p class="text-muted" style="padding: var(--space-4);">Sin errores recientes.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead><tr><th>Técnico</th><th>Ticket</th><th>Acción</th><th>Detalle</th><th>Fecha</th></tr></thead>
          <tbody>
            <?php foreach ($recentErrors as $e): ?>
              <tr>
                <td class="text-sm"><?= esc(trim(($e['employee_name'] ?? '') . ' ' . ($e['employee_lastname'] ?? '')) ?: '—') ?></td>
                <td class="text-sm">#<?= (int) $e['glpi_ticket_id'] ?></td>
                <td class="text-sm"><?= esc($e['action']) ?></td>
                <td class="text-sm text-muted"><?= esc(mb_substr((string) ($e['error_message'] ?? ''), 0, 80)) ?></td>
                <td class="text-muted text-sm"><?= esc($e['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
