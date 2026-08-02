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

<div class="page-header">
  <div class="page-header-content"><h1 class="page-title">Notificaciones</h1></div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.index') ?>" class="btn btn-secondary">Tablero</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <table class="table">
      <thead><tr><th>Agente</th><th>Período</th><th>Desv.</th><th>Estado</th><th>Enviada</th><th>Tokens</th><th></th></tr></thead>
      <tbody>
        <?php if ($notifications === []): ?>
          <tr><td colspan="7" class="text-muted" style="text-align:center;">Aún no hay notificaciones. Prepáralas desde el detalle de un agente.</td></tr>
        <?php else: foreach ($notifications as $n): ?>
          <tr>
            <td><?= esc($n['agent_name'] !== '' ? $n['agent_name'] : ('GLPI #' . $n['glpi_user_id'])) ?></td>
            <td class="text-sm"><?= esc(date('d/m/Y', strtotime((string) $n['period_start']))) ?> - <?= esc(date('d/m/Y', strtotime((string) $n['period_end']))) ?></td>
            <td><?= (int) $n['total_deviations'] ?></td>
            <td><?= $badge((string) $n['status']) ?></td>
            <td class="text-sm text-muted"><?= $n['sent_at'] ? esc(date('d/m/Y H:i', strtotime((string) $n['sent_at']))) : '' ?></td>
            <td class="text-sm text-muted"><?= (int) $n['ai_tokens_input'] ?>/<?= (int) $n['ai_tokens_output'] ?></td>
            <td>
              <a href="<?= route_to('helpdesk.notifications.review', (int) $n['id']) ?>" class="btn btn-tertiary btn-sm">Revisar</a>
              <form method="post" action="<?= route_to('helpdesk.notifications.delete', (int) $n['id']) ?>" style="display:inline;" onsubmit="return confirm('¿Descartar esta notificación?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-tertiary btn-sm btn-critical">Descartar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= $this->endSection() ?>
