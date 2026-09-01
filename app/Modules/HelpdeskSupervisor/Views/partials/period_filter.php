<?php
use App\Modules\HelpdeskSupervisor\Services\PeriodFilter;

/** @var string $formAction */
/** @var string $periodStart */
/** @var string $periodEnd */
/** @var array<string,string> $extraHidden */

$extraHidden   = $extraHidden ?? [];
$months        = PeriodFilter::monthLabels();
$calMonth      = PeriodFilter::calendarMonth($periodStart, $periodEnd);
$selMonth      = $calMonth['month'] ?? (int) date('n', strtotime($periodStart));
$selYear       = $calMonth['year'] ?? (int) date('Y', strtotime($periodStart));
$isCustomRange = $calMonth === null;
?>
<div class="hs-period-filter">
  <form method="get" action="<?= esc($formAction) ?>" class="hs-field-row" style="margin:0;">
    <?php foreach ($extraHidden as $name => $value): ?>
      <input type="hidden" name="<?= esc($name) ?>" value="<?= esc($value) ?>">
    <?php endforeach; ?>
    <div class="field" style="margin:0;">
      <label class="field-label" for="hs_period_month">Mes</label>
      <select id="hs_period_month" name="month" class="select">
        <?php foreach ($months as $m => $label): ?>
          <option value="<?= $m ?>" <?= $m === $selMonth ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="margin:0;">
      <label class="field-label" for="hs_period_year">Año</label>
      <input type="number" id="hs_period_year" name="year" class="input" value="<?= esc((string) $selYear) ?>" min="2020" max="2100" style="width:100px;">
    </div>
    <button type="submit" class="btn btn-primary">Ver mes</button>
  </form>

  <span class="hs-period-divider" aria-hidden="true"></span>

  <form method="get" action="<?= esc($formAction) ?>" class="hs-field-row" style="margin:0;">
    <?php foreach ($extraHidden as $name => $value): ?>
      <input type="hidden" name="<?= esc($name) ?>" value="<?= esc($value) ?>">
    <?php endforeach; ?>
    <div class="field" style="margin:0;">
      <label class="field-label" for="period_start">Desde</label>
      <input type="date" id="period_start" name="period_start" class="input" value="<?= esc($periodStart) ?>">
    </div>
    <div class="field" style="margin:0;">
      <label class="field-label" for="period_end">Hasta</label>
      <input type="date" id="period_end" name="period_end" class="input" value="<?= esc($periodEnd) ?>">
    </div>
    <button type="submit" class="btn btn-secondary">Ver período</button>
    <?php if ($isCustomRange): ?>
      <span class="text-sm text-muted" style="align-self:center;">Rango personalizado activo</span>
    <?php endif; ?>
  </form>
</div>
