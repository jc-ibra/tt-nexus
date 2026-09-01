<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$isEdit = $escalation !== null;
$errors = session()->getFlashdata('errors') ?? [];
$val    = fn(string $k, mixed $d = '') => old($k, $isEdit ? ($escalation[$k] ?? $d) : $d);
$action = $isEdit
    ? route_to('helpdesk.escalations.update', (int) $escalation['id'])
    : route_to('helpdesk.escalations.store');
?>

<?= view('App\Modules\HelpdeskSupervisor\Views\partials/styles') ?>

<div class="page-header">
  <div class="page-header-content"><h1 class="page-title"><?= esc($pageTitle) ?></h1></div>
  <div class="page-actions">
    <a href="<?= route_to('helpdesk.escalations.index') ?>" class="btn btn-secondary">Cancelar</a>
  </div>
</div>

<?php if ($errors !== []): ?>
  <div class="banner banner-critical"><div class="banner-content">
    <ul style="margin:0; padding-left:1rem;"><?php foreach ($errors as $err): ?><li><?= esc($err) ?></li><?php endforeach; ?></ul>
  </div></div>
<?php endif; ?>

<div class="card" style="max-width:680px;">
  <form method="post" action="<?= $action ?>">
    <?= csrf_field() ?>
    <div class="card-body">
      <div class="form-group">

        <div class="field">
          <label class="field-label" for="glpi_user_id">Agente <span class="required" aria-hidden="true">*</span></label>
          <select id="glpi_user_id" name="glpi_user_id" class="select" required>
            <option value="">Selecciona un agente</option>
            <?php foreach ($agents as $a): ?>
              <option value="<?= (int) $a['glpi_user_id'] ?>" <?= (int) $val('glpi_user_id') === (int) $a['glpi_user_id'] ? 'selected' : '' ?>>
                <?= esc($a['name']) ?> (GLPI #<?= (int) $a['glpi_user_id'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <?php if ($agents === []): ?><p class="field-help">No hay agentes mapeados. Asigna un "ID de usuario en GLPI" a los usuarios en Administración.</p><?php endif; ?>
        </div>

        <div class="field">
          <label class="field-label" for="glpi_ticket_id">Ticket GLPI <span class="required" aria-hidden="true">*</span></label>
          <input type="number" min="1" id="glpi_ticket_id" name="glpi_ticket_id" class="input" value="<?= esc($val('glpi_ticket_id')) ?>" required>
        </div>

        <div class="field">
          <label class="field-label" for="escalation_date">Fecha de la escalación <span class="required" aria-hidden="true">*</span></label>
          <input type="date" id="escalation_date" name="escalation_date" class="input" value="<?= esc($val('escalation_date', date('Y-m-d'))) ?>" required>
        </div>

        <div class="field">
          <label class="field-label" for="reason">Motivo <span class="required" aria-hidden="true">*</span></label>
          <textarea id="reason" name="reason" class="input" rows="3" required><?= esc($val('reason')) ?></textarea>
        </div>

        <div class="field">
          <label class="field-label" for="reported_by">Reportado por</label>
          <input type="text" id="reported_by" name="reported_by" class="input" value="<?= esc($val('reported_by')) ?>">
        </div>

        <div style="display:flex; gap:var(--space-3);">
          <div class="field" style="flex:1;">
            <label class="field-label" for="period_month">Mes del período</label>
            <input type="number" min="1" max="12" id="period_month" name="period_month" class="input" value="<?= esc($val('period_month', date('n'))) ?>">
          </div>
          <div class="field" style="flex:1;">
            <label class="field-label" for="period_year">Año del período</label>
            <input type="number" min="2020" max="2100" id="period_year" name="period_year" class="input" value="<?= esc($val('period_year', date('Y'))) ?>">
          </div>
        </div>

        <div class="field">
          <label class="field-check">
            <input type="checkbox" name="is_valid" value="1" <?= (int) $val('is_valid', 1) === 1 ? 'checked' : '' ?>>
            <span>Escalación válida (procede para KPI 5)</span>
          </label>
        </div>

      </div>
    </div>
    <div class="card-footer">
      <a href="<?= route_to('helpdesk.escalations.index') ?>" class="btn btn-secondary">Cancelar</a>
      <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Guardar cambios' : 'Registrar' ?></button>
    </div>
  </form>
</div>

<?= $this->endSection() ?>
