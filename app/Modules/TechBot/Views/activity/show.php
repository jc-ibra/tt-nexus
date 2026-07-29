<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$name = trim(($row['employee_name'] ?? '') . ' ' . ($row['employee_lastname'] ?? '')) ?: '—';
$payload = null;
if (! empty($row['payload'])) {
    $decoded = json_decode((string) $row['payload'], true);
    $payload = is_array($decoded) ? $decoded : null;
}
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Acción #<?= (int) $row['id'] ?> · <?= esc($row['action']) ?></h1>
    <p class="page-subtitle">Ticket GLPI #<?= (int) $row['glpi_ticket_id'] ?> · <?= esc($name) ?></p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('techbot.activity') ?>" class="btn btn-secondary">‹ Registro</a>
  </div>
</div>

<?php if (($row['result'] ?? '') === 'error'): ?>
  <div class="banner banner-critical" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body"><?= esc($row['error_message'] ?? 'Error') ?></div>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-body">
    <dl style="display:grid; grid-template-columns:max-content 1fr; gap:var(--space-2) var(--space-4); margin:0;">
      <dt class="text-muted text-sm">Plantilla</dt><dd class="text-sm"><?= esc($row['template_key'] ?? '—') ?></dd>
      <dt class="text-muted text-sm">Followup / Solución (id)</dt><dd class="text-sm"><?= $row['glpi_followup_id'] !== null ? (int) $row['glpi_followup_id'] : '—' ?></dd>
      <dt class="text-muted text-sm">Estado GLPI</dt><dd class="text-sm"><?= (int) ($row['glpi_status_before'] ?? 0) ?> → <?= (int) ($row['glpi_status_after'] ?? 0) ?></dd>
      <dt class="text-muted text-sm">IA utilizada</dt><dd class="text-sm"><?= ! empty($row['ai_used']) ? 'Sí (' . (int) ($row['ai_tokens_used'] ?? 0) . ' tokens)' : 'No' ?></dd>
      <dt class="text-muted text-sm">Fotos adjuntas</dt><dd class="text-sm"><?= (int) ($payload['photos'] ?? 0) ?></dd>
      <dt class="text-muted text-sm">Resultado</dt><dd><?= ($row['result'] ?? '') === 'error' ? '<span class="badge badge-critical">Error</span>' : '<span class="badge badge-success">Éxito</span>' ?></dd>
      <dt class="text-muted text-sm">Fecha</dt><dd class="text-sm"><?= esc($row['created_at']) ?></dd>
    </dl>
  </div>
</div>

<?php if ($payload !== null && ! empty($payload['text'])): ?>
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Texto enviado a GLPI</h2></div>
    <div class="card-body">
      <pre style="white-space:pre-wrap; margin:0; font-family:var(--font-mono,monospace); font-size:var(--font-size-sm);"><?= esc($payload['text']) ?></pre>
    </div>
  </div>
<?php endif; ?>

<?php if ($payload !== null && ! empty($payload['data'])): ?>
  <div class="card">
    <div class="card-header"><h2 class="card-title">Campos capturados</h2></div>
    <div class="card-body">
      <dl style="display:grid; grid-template-columns:max-content 1fr; gap:var(--space-2) var(--space-4); margin:0;">
        <?php foreach ($payload['data'] as $k => $v): ?>
          <dt class="text-muted text-sm"><?= esc((string) $k) ?></dt>
          <dd class="text-sm" style="white-space:pre-wrap;"><?= esc(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)) ?></dd>
        <?php endforeach; ?>
      </dl>
    </div>
  </div>
<?php endif; ?>

<?= $this->endSection() ?>
