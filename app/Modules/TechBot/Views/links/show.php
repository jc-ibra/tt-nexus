<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$name = trim(($link['employee_name'] ?? '') . ' ' . ($link['employee_lastname'] ?? '')) ?: '—';
$active = ($link['status'] ?? '') === 'active';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($name) ?></h1>
    <p class="page-subtitle">Vinculación de Telegram · TechBot</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('techbot.links') ?>" class="btn btn-secondary">‹ Técnicos</a>
    <?php if ($active): ?>
      <form method="post" action="<?= route_to('techbot.links.deactivate', (int) $link['id']) ?>" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-critical">Desactivar</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= route_to('techbot.links.activate', (int) $link['id']) ?>" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Reactivar</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-body">
    <dl style="display:grid; grid-template-columns:max-content 1fr; gap:var(--space-2) var(--space-4); margin:0;">
      <dt class="text-muted text-sm">Número de empleado</dt><dd class="text-sm"><?= esc($link['employee_number'] ?? '—') ?></dd>
      <dt class="text-muted text-sm">Usuario GLPI (id)</dt><dd class="text-sm"><?= (int) $link['glpi_user_id'] ?></dd>
      <dt class="text-muted text-sm">Chat de Telegram</dt><dd class="text-sm"><?= (int) $link['telegram_chat_id'] ?></dd>
      <dt class="text-muted text-sm">Usuario de Telegram</dt><dd class="text-sm"><?= esc($link['telegram_username'] ? '@' . $link['telegram_username'] : '—') ?></dd>
      <dt class="text-muted text-sm">Nombre en Telegram</dt><dd class="text-sm"><?= esc($link['telegram_first_name'] ?? '—') ?></dd>
      <dt class="text-muted text-sm">Estado</dt><dd><?= $active ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-neutral">Inactivo</span>' ?></dd>
      <dt class="text-muted text-sm">Vinculado</dt><dd class="text-sm"><?= esc($link['verified_at'] ?? '—') ?></dd>
    </dl>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2 class="card-title">Actividad del técnico</h2></div>
  <div class="card-body" style="padding:0;">
    <?php if (empty($activity)): ?>
      <p class="text-muted" style="padding: var(--space-4);">Sin actividad registrada.</p>
    <?php else: ?>
      <table class="table" style="width:100%;">
        <thead><tr><th>Ticket</th><th>Acción</th><th>Estado GLPI</th><th>Resultado</th><th>IA</th><th>Fecha</th></tr></thead>
        <tbody>
          <?php foreach ($activity as $a): ?>
            <tr>
              <td class="text-sm"><a href="<?= route_to('techbot.activity.show', (int) $a['id']) ?>">#<?= (int) $a['glpi_ticket_id'] ?></a></td>
              <td class="text-sm"><?= esc($a['action']) ?></td>
              <td class="text-sm text-muted"><?= (int) ($a['glpi_status_before'] ?? 0) ?> → <?= (int) ($a['glpi_status_after'] ?? 0) ?></td>
              <td><?= ($a['result'] ?? '') === 'error' ? '<span class="badge badge-critical">Error</span>' : '<span class="badge badge-success">OK</span>' ?></td>
              <td class="text-sm"><?= ! empty($a['ai_used']) ? 'Sí' : '—' ?></td>
              <td class="text-muted text-sm"><?= esc($a['created_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
