<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$body   = (string) ($n['final_body'] ?? $n['ai_draft_body'] ?? '');
$isSent = (string) $n['status'] === 'sent';
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Revisar notificación</h1>
    <p class="page-subtitle text-muted">
      <?= esc($n['agent_name'] !== '' ? $n['agent_name'] : ('GLPI #' . $n['glpi_user_id'])) ?> ·
      <?= esc(date('d/m/Y', strtotime((string) $n['period_start']))) ?> a <?= esc(date('d/m/Y', strtotime((string) $n['period_end']))) ?> ·
      <?= (int) $n['total_deviations'] ?> desviaciones ·
      tokens <?= (int) $n['ai_tokens_input'] ?>/<?= (int) $n['ai_tokens_output'] ?>
    </p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.notifications.index') ?>" class="btn btn-secondary">Volver</a>
  </div>
</div>

<?php if ($isSent): ?>
  <div class="banner banner-success"><div class="banner-content">Esta notificación ya fue enviada el <?= esc(date('d/m/Y H:i', strtotime((string) $n['sent_at']))) ?>. Reenviarla generará un nuevo envío.</div></div>
<?php endif; ?>
<?php if (! empty($n['error_message']) && (string) $n['status'] !== 'sent'): ?>
  <div class="banner banner-warning"><div class="banner-content"><?= esc($n['error_message']) ?></div></div>
<?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-4); align-items:start;">
  <!-- Editor -->
  <form method="post" action="<?= route_to('helpdesk.notifications.send', (int) $n['id']) ?>" class="card">
    <?= csrf_field() ?>
    <div class="card-body">
      <div class="field">
        <label class="field-label" for="to">Destinatario</label>
        <input type="email" id="to" name="to" class="input" value="<?= esc($n['agent_email']) ?>" required>
        <?php if (trim((string) $n['agent_email']) === ''): ?><p class="field-help">El usuario Nexus del agente no tiene email; captúralo aquí.</p><?php endif; ?>
      </div>
      <div class="field">
        <label class="field-label" for="cc">CC</label>
        <input type="text" id="cc" name="cc" class="input" value="<?= esc($cc) ?>" placeholder="Separados por coma">
      </div>
      <div class="field">
        <label class="field-label" for="subject">Asunto</label>
        <input type="text" id="subject" name="subject" class="input" value="<?= esc($defaultSubject) ?>" required>
      </div>
      <div class="field">
        <label class="field-label" for="final_body">Cuerpo (HTML)</label>
        <textarea id="final_body" name="final_body" class="input" rows="16" style="font-family:var(--font-mono,monospace); font-size:13px;" oninput="document.getElementById('preview').srcdoc=this.value;"><?= esc($body) ?></textarea>
      </div>
      <?php if ($excelName !== ''): ?>
        <p class="field-help">Adjunto: <strong><?= esc($excelName) ?></strong></p>
      <?php else: ?>
        <p class="field-help text-muted">Sin adjunto Excel (no se generó).</p>
      <?php endif; ?>
    </div>
    <div class="card-footer" style="gap:var(--space-2);">
      <button type="submit" class="btn btn-primary">Enviar</button>
      <button type="submit" form="regen-form" class="btn btn-secondary">Regenerar con IA</button>
      <button type="submit" form="discard-form" class="btn btn-tertiary btn-critical" onclick="return confirm('¿Descartar esta notificación?');">Descartar</button>
    </div>
  </form>

  <!-- Preview -->
  <div class="card">
    <div class="card-header"><h2 class="card-title">Vista previa</h2></div>
    <div class="card-body" style="padding:0;">
      <iframe id="preview" title="Vista previa del correo" sandbox="" style="width:100%; height:520px; border:0; background:#fff;" srcdoc="<?= esc($body, 'attr') ?>"></iframe>
    </div>
  </div>
</div>

<form id="regen-form" method="post" action="<?= route_to('helpdesk.notifications.regenerate', (int) $n['id']) ?>"><?= csrf_field() ?></form>
<form id="discard-form" method="post" action="<?= route_to('helpdesk.notifications.delete', (int) $n['id']) ?>"><?= csrf_field() ?></form>

<?= $this->endSection() ?>
