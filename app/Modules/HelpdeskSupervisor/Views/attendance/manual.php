<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<?php
$hm = static fn(?string $dt): string => $dt ? date('H:i', strtotime($dt)) : '';

$selUser   = (int) (old('user_id') ?: $userId);
$selDate   = (string) (old('work_date') ?: $workDate);
$selScheme = (string) (old('scheme') ?: ($existing['scheme'] ?? 'presencial'));
if (! in_array($selScheme, ['presencial', 'home_office'], true)) {
    $selScheme = 'presencial';
}
$inValue  = (string) (old('check_in') ?? $hm($existing['check_in_at'] ?? null));
$outValue = (string) (old('check_out') ?? $hm($existing['check_out_at'] ?? null));
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Registrar entrada o salida</h1>
    <p class="page-subtitle text-muted">Para cuando alguien del equipo olvidó marcar. Puedes elegir cualquier fecha pasada.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.attendance.index') ?>" class="btn btn-secondary">Volver a asistencia</a>
  </div>
</div>

<?php if ($existing !== null): ?>
  <div class="banner banner-info" style="margin-bottom: var(--space-4);">
    <div class="banner-body">
      <div class="banner-title">Ya hay un registro para este día</div>
      <div class="banner-message">
        Entrada <?= esc($hm($existing['check_in_at']) ?: 'sin marcar') ?>, salida <?= esc($hm($existing['check_out_at']) ?: 'sin marcar') ?>.
        Al guardar se actualizan las horas que captures; las que dejes en blanco se conservan.
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="max-width: 640px;">
  <div class="card-body">
    <form method="post" action="<?= route_to('helpdesk.attendance.manual.save') ?>" class="form-group">
      <?= csrf_field() ?>

      <div class="field">
        <label class="field-label" for="man-user">Agente <span class="required">*</span></label>
        <select id="man-user" name="user_id" class="select" required>
          <option value="">Selecciona a una persona</option>
          <?php foreach ($roster as $agent): ?>
            <option value="<?= (int) $agent['id'] ?>" <?= $selUser === (int) $agent['id'] ? 'selected' : '' ?>><?= esc($agent['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label class="field-label" for="man-date">Fecha <span class="required">*</span></label>
        <input id="man-date" name="work_date" type="date" class="input" required
               max="<?= esc(date('Y-m-d'), 'attr') ?>" value="<?= esc($selDate, 'attr') ?>">
      </div>

      <div class="field">
        <label class="field-label" for="man-scheme">Esquema</label>
        <select id="man-scheme" name="scheme" class="select">
          <option value="presencial" <?= $selScheme === 'presencial' ? 'selected' : '' ?>>Presencial</option>
          <option value="home_office" <?= $selScheme === 'home_office' ? 'selected' : '' ?>>Home office</option>
        </select>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
        <div class="field">
          <label class="field-label" for="man-in">Hora de entrada</label>
          <input id="man-in" name="check_in" type="time" class="input" value="<?= esc($inValue, 'attr') ?>">
        </div>
        <div class="field">
          <label class="field-label" for="man-out">Hora de salida</label>
          <input id="man-out" name="check_out" type="time" class="input" value="<?= esc($outValue, 'attr') ?>">
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="man-reason">Motivo <span class="required">*</span></label>
        <textarea id="man-reason" name="reason" class="input" rows="3" maxlength="500" required
                  placeholder="Por qué se registra a mano, por ejemplo: olvidó marcar la salida"><?= esc((string) old('reason')) ?></textarea>
      </div>

      <div>
        <button type="submit" class="btn btn-primary">Guardar registro</button>
      </div>
    </form>
  </div>
</div>

<?= $this->endSection() ?>
