<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$badge = static fn(string $s) => match ($s) {
    'sent'   => '<span class="badge badge-success">Enviada</span>',
    'ready'  => '<span class="badge badge-warning">Lista</span>',
    'failed' => '<span class="badge badge-critical">Falló</span>',
    default  => '<span class="badge">Borrador</span>',
};
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Notificaciones</h1>
    <p class="page-subtitle text-muted">Borradores y envíos de correo a agentes con desviaciones confirmadas.</p>
  </div>
  <div class="page-actions" style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
    <a href="<?= route_to('helpdesk.index') ?>" class="btn btn-secondary">Dashboard</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Notificaciones</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead><tr><th>Agente</th><th>Período</th><th>Desv.</th><th>Estado</th><th>Enviada</th><th>Tokens</th><th style="width:12.5rem; text-align:right;">Acciones</th></tr></thead>
      <tbody>
        <?php if ($notifications === []): ?>
          <tr><td colspan="7" class="text-muted text-sm" style="text-align:center; padding:var(--space-4);">Aún no hay notificaciones. Prepáralas desde el dashboard o el detalle de un agente.</td></tr>
        <?php else: foreach ($notifications as $n):
          $reviewHref = route_to('helpdesk.notifications.review', (int) $n['id']);
        ?>
          <tr>
            <td><a href="<?= esc($reviewHref) ?>"><?= esc($n['agent_name'] !== '' ? $n['agent_name'] : ('GLPI #' . $n['glpi_user_id'])) ?></a></td>
            <td class="text-sm">
              <a href="<?= esc($reviewHref) ?>">
                <?= esc(date('d/m/Y', strtotime((string) $n['period_start']))) ?> – <?= esc(date('d/m/Y', strtotime((string) $n['period_end']))) ?>
              </a>
            </td>
            <td style="text-align:right;"><a href="<?= esc($reviewHref) ?>"><?= (int) $n['total_deviations'] ?></a></td>
            <td><?= $badge((string) $n['status']) ?></td>
            <td class="text-sm text-muted"><?= $n['sent_at'] ? esc(date('d/m/Y H:i', strtotime((string) $n['sent_at']))) : '' ?></td>
            <td class="text-sm text-muted"><?= (int) $n['ai_tokens_input'] ?>/<?= (int) $n['ai_tokens_output'] ?></td>
            <td style="text-align:right;">
              <div class="table-actions hs-row-actions">
                <a href="<?= esc($reviewHref) ?>" class="btn btn-secondary btn-sm">Revisar</a>
                <form method="post" action="<?= route_to('helpdesk.notifications.delete', (int) $n['id']) ?>" class="hs-row-actions-form" onsubmit="return confirm('¿Descartar esta notificación?');">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-critical btn-sm">Descartar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
