<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$statusLabels = [
    'draft'   => ['label' => 'Borrador',  'class' => 'badge-neutral'],
    'queued'  => ['label' => 'En cola',   'class' => 'badge-info'],
    'sending' => ['label' => 'Enviando',  'class' => 'badge-warning'],
    'sent'    => ['label' => 'Enviado',   'class' => 'badge-success'],
    'failed'  => ['label' => 'Fallido',   'class' => 'badge-critical'],
];
$st = $statusLabels[$communication['status']] ?? ['label' => $communication['status'], 'class' => 'badge-neutral'];
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title"><?= esc($communication['subject']) ?></h1>
    <p class="page-subtitle">
      <span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span>
      &nbsp; De: <?= esc($communication['from_name']) ?> &lt;<?= esc($communication['from_email']) ?>&gt;
    </p>
  </div>
  <div class="page-actions">
    <?php if (in_array($communication['status'], ['draft', 'failed'])): ?>
      <a href="<?= route_to('comms.edit', $communication['id']) ?>" class="btn btn-secondary">Editar</a>
    <?php endif; ?>
    <a href="<?= route_to('comms.preview', $communication['id']) ?>" class="btn btn-secondary" target="_blank">Vista previa</a>
    <?php if (in_array($communication['status'], ['draft', 'failed'])): ?>
      <form action="<?= route_to('comms.send', $communication['id']) ?>" method="post" style="display:inline;"
            onsubmit="return confirm('¿Confirmas el envío masivo a todas las listas asignadas?')">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary">Enviar</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="grid-2" style="align-items: start;">

  <!-- Details card -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Detalles</h2></div>
    <div class="card-body">
      <dl style="display:grid; grid-template-columns:auto 1fr; gap:var(--space-2) var(--space-4);">
        <dt class="text-muted text-sm">Estado</dt>
        <dd><span class="badge <?= $st['class'] ?>"><?= $st['label'] ?></span></dd>

        <dt class="text-muted text-sm">Remitente</dt>
        <dd class="text-sm"><?= esc($communication['from_name']) ?><br><span class="text-muted"><?= esc($communication['from_email']) ?></span></dd>

        <dt class="text-muted text-sm">Listas</dt>
        <dd>
          <?php if (empty($communication['lists'])): ?>
            <span class="text-muted text-sm">Sin asignar</span>
          <?php else: ?>
            <?php foreach ($communication['lists'] as $list): ?>
              <span class="badge badge-neutral" style="margin-bottom:var(--space-1)"><?= esc($list['name']) ?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </dd>

        <dt class="text-muted text-sm">Creado</dt>
        <dd class="text-sm"><?= date('d/m/Y H:i', strtotime($communication['created_at'])) ?></dd>

        <?php if ($communication['sent_at']): ?>
          <dt class="text-muted text-sm">Enviado</dt>
          <dd class="text-sm"><?= date('d/m/Y H:i', strtotime($communication['sent_at'])) ?></dd>
        <?php endif; ?>
      </dl>
    </div>
    <div class="card-footer" style="gap:var(--space-2);">
      <a href="<?= route_to('comms.logs', $communication['id']) ?>" class="btn btn-tertiary btn-sm">Ver registros de envío</a>
    </div>
  </div>

  <!-- Send test card -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Enviar correo de prueba</h2></div>
    <div class="card-body">
      <form action="<?= route_to('comms.send-test', $communication['id']) ?>" method="post">
        <?= csrf_field() ?>
        <div class="form-group">
          <div class="field">
            <label class="field-label" for="test-email">Correo de prueba</label>
            <input type="email" id="test-email" name="email" class="input" placeholder="test@empresa.com" required>
          </div>
          <button type="submit" class="btn btn-secondary">Enviar prueba</button>
        </div>
      </form>
    </div>
  </div>

</div>

<!-- Body preview -->
<div class="card" style="margin-top:var(--space-4);">
  <div class="card-header"><h2 class="card-title">Cuerpo del correo</h2></div>
  <div class="card-body" style="border:1px solid var(--color-neutral-200); border-radius:var(--radius-sm); padding:var(--space-4); background:var(--bg-surface-alt);">
    <?= $communication['body'] ?>
  </div>
</div>

<?= $this->endSection() ?>
