<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('head') ?>
<style>
  /* Scheme picker: three big, tappable cards instead of a dropdown — the
     choice (and its consequence) should be visible at a glance, not hidden
     behind option text. */
  .att-scheme-picker { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-3); }
  .att-scheme-option {
    position: relative; display: flex; align-items: flex-start; gap: var(--space-3);
    padding: var(--space-3); border: var(--border-width-default) solid var(--border-default);
    border-radius: var(--radius-md); background: var(--bg-surface); cursor: pointer;
    transition: border-color var(--duration-base) var(--ease-default), background var(--duration-base) var(--ease-default);
  }
  .att-scheme-option:hover { border-color: var(--accent-border); }
  .att-scheme-option input { position: absolute; opacity: 0; width: 1px; height: 1px; }
  .att-scheme-option:has(input:checked) { border-color: var(--accent-text); background: var(--accent-surface); }
  .att-scheme-option:has(input:focus-visible) { outline: 2px solid var(--accent-text); outline-offset: 2px; }
  .att-scheme-icon {
    flex: 0 0 auto; width: 36px; height: 36px; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    background: var(--bg-surface-alt); color: var(--text-secondary);
  }
  .att-scheme-icon svg { width: 20px; height: 20px; }
  .att-scheme-option:has(input:checked) .att-scheme-icon { background: var(--accent-text); color: var(--bg-surface); }
  .att-scheme-title { display: block; font-weight: var(--weight-semibold); color: var(--text-primary); }
  .att-scheme-desc { display: block; font-size: var(--text-sm); color: var(--text-muted); margin-top: 2px; line-height: 1.4; }

  .att-status-row { display: flex; align-items: center; gap: var(--space-3); }
  .att-status-icon {
    flex: 0 0 auto; width: 40px; height: 40px; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    background: var(--status-success-surface); color: var(--status-success-text);
  }
  .att-status-icon svg { width: 22px; height: 22px; }
  .att-status-done .att-status-icon { background: var(--bg-surface-alt); color: var(--text-secondary); }

  .att-board { overflow: auto; border: 1px solid var(--border-default); border-radius: var(--radius-md); background: var(--bg-surface); }
  .att-table { border-collapse: separate; border-spacing: 0; width: 100%; font-size: var(--text-sm); }
  .att-table th, .att-table td { padding: var(--space-2) var(--space-3); border-bottom: 1px solid var(--border-default); text-align: left; vertical-align: top; white-space: nowrap; }
  .att-table thead th { background: var(--bg-surface-alt); font-weight: var(--weight-semibold); }
  .att-table thead th.att-today { color: var(--accent-text); }
  .att-name { position: sticky; left: 0; z-index: 1; background: var(--bg-surface); font-weight: var(--weight-medium); min-width: 160px; }
  .att-table tbody tr:hover .att-name { background: var(--bg-surface-alt); }
  .att-me { background: var(--accent-surface) !important; }
  .att-cell-empty { color: var(--text-disabled); }
  .att-times { display: block; color: var(--text-muted); font-size: var(--text-xs); }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$schemeBadge = [
    'presencial'  => 'badge badge-success',
    'home_office' => 'badge',
    'permiso'     => 'badge badge-warning',
];
$permitLabel = ['pending' => 'Pendiente', 'approved' => 'Aprobado', 'rejected' => 'Rechazado'];

$icons = [
    'presencial' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v18"/><path d="M2 22h20"/><path d="M9 9h1"/><path d="M9 13h1"/><path d="M14 9h1"/><path d="M14 13h1"/><path d="M9 22v-4h6v4"/></svg>',
    'home_office' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5"/><path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"/><path d="M9 21v-6h6v6"/></svg>',
    'permiso' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5L12 3l9 6.5"/><path d="M5 10v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V10"/><circle cx="17.5" cy="16.5" r="4.5" fill="var(--bg-surface)" stroke-width="1.6"/><path d="M15.8 16.5l1.1 1.1 2-2.1" stroke-width="1.6"/></svg>',
];
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Asistencia</h1>
    <p class="page-subtitle">Registra tu jornada y consulta el estatus de todo el equipo esta semana.</p>
  </div>
</div>

<div class="card" style="margin-bottom: var(--space-4); max-width: 680px;">
  <div class="card-header"><h2 class="card-title">Mi jornada de hoy</h2></div>
  <div class="card-body">
    <?php if ($today === null || $today['check_in_at'] === null): ?>
      <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
        Elige cómo trabajas hoy y marca tu entrada. Se esperan a las <strong><?= esc($expectedCheckin) ?></strong>.
      </p>
      <form action="<?= route_to('servicedesk.attendance.checkin') ?>" method="post">
        <?= csrf_field() ?>
        <fieldset class="att-scheme-picker" style="border:0; padding:0; margin:0 0 var(--space-4);">
          <legend class="sr-only">Esquema de hoy</legend>
          <?php foreach ($schemes as $value => $label): ?>
            <label class="att-scheme-option">
              <input type="radio" name="scheme" value="<?= esc($value, 'attr') ?>" <?= $value === 'presencial' ? 'checked' : '' ?>>
              <span class="att-scheme-icon" aria-hidden="true"><?= $icons[$value] ?></span>
              <span>
                <span class="att-scheme-title"><?= esc($label) ?></span>
                <span class="att-scheme-desc"><?= esc($schemeDescriptions[$value]) ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </fieldset>
        <div class="field" id="att-permiso-notes" style="display:none; margin-bottom: var(--space-4);">
          <label class="field-label" for="att-notes">Motivo del permiso</label>
          <textarea id="att-notes" name="notes" class="input" rows="2"
                    placeholder="Por qué ingresas en home office teniendo presencial"></textarea>
          <p class="field-help">Tu supervisor de asistencia lo aprueba o rechaza más tarde; hoy queda registrado igual.</p>
        </div>
        <button type="submit" class="btn btn-primary">Iniciar jornada</button>
      </form>
      <script>
        (function () {
          var radios = document.querySelectorAll('input[name="scheme"]');
          var box = document.getElementById('att-permiso-notes');
          function sync() {
            var checked = document.querySelector('input[name="scheme"]:checked');
            box.style.display = (checked && checked.value === 'permiso') ? '' : 'none';
          }
          radios.forEach(function (r) { r.addEventListener('change', sync); });
          sync();
        }());
      </script>
    <?php else: ?>
      <?php if ($today['check_out_at'] === null): ?>
        <div class="att-status-row" style="margin-bottom: var(--space-4);">
          <span class="att-status-icon"><?= $icons[$today['scheme']] ?? $icons['presencial'] ?></span>
          <span>
            Entraste a las <strong><?= esc(date('H:i', strtotime((string) $today['check_in_at']))) ?></strong>
            · <span class="<?= $schemeBadge[$today['scheme']] ?? 'badge' ?>"><?= esc($schemes[$today['scheme']] ?? $today['scheme']) ?></span>
          </span>
        </div>
        <form action="<?= route_to('servicedesk.attendance.checkout') ?>" method="post">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary">Cerrar jornada</button>
        </form>
      <?php else: ?>
        <div class="att-status-row att-status-done">
          <span class="att-status-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
          </span>
          <span>
            Jornada cerrada · <strong><?= esc(date('H:i', strtotime((string) $today['check_in_at']))) ?></strong>
            – <strong><?= esc(date('H:i', strtotime((string) $today['check_out_at']))) ?></strong>
            · <span class="<?= $schemeBadge[$today['scheme']] ?? 'badge' ?>"><?= esc($schemes[$today['scheme']] ?? $today['scheme']) ?></span>
          </span>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Equipo esta semana</h2>
    <p class="text-muted text-sm" style="margin:0;">
      <?= esc(date('d/m', strtotime($board['start']))) ?> — <?= esc(date('d/m/Y', strtotime($board['end']))) ?>.
      Si alguien no ha reportado, pueden cubrirlo mientras se conecta.
    </p>
  </div>
  <div class="card-body" style="padding:0;">
    <div class="att-board">
      <table class="att-table">
        <thead>
          <tr>
            <th scope="col" class="att-name">Agente</th>
            <?php foreach ($board['days'] as $day): $isToday = $day === date('Y-m-d'); ?>
              <th scope="col" class="<?= $isToday ? 'att-today' : '' ?>">
                <?= esc(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'][(int) date('N', strtotime($day)) - 1]) ?> <?= esc(date('d/m', strtotime($day))) ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($board['rows'] as $row): ?>
            <tr>
              <td class="att-name<?= $row['user_id'] === $myUserId ? ' att-me' : '' ?>">
                <?= esc($row['name']) ?><?= $row['user_id'] === $myUserId ? ' <span class="text-muted text-xs">(tú)</span>' : '' ?>
              </td>
              <?php foreach ($board['days'] as $day): $cell = $row['cells'][$day]; $isTodayCell = $day === date('Y-m-d'); $isWeekdayCell = (int) date('N', strtotime($day)) <= 5; ?>
                <td class="<?= $row['user_id'] === $myUserId ? 'att-me' : '' ?>">
                  <?php if ($cell['is_future']): ?>
                    <span class="att-cell-empty">·</span>
                  <?php elseif (! $cell['has_log']): ?>
                    <?php if ($cell['is_absent']): ?>
                      <span class="badge badge-critical">No reportó</span>
                    <?php elseif ($isTodayCell && $isWeekdayCell): ?>
                      <span class="badge badge-warning">Pendiente</span>
                    <?php else: ?>
                      <span class="att-cell-empty">·</span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="<?= $schemeBadge[$cell['scheme']] ?? 'badge' ?>"><?= esc($schemesShort[$cell['scheme']] ?? $cell['scheme']) ?></span>
                    <?php if ($cell['permit_status'] !== null): ?>
                      <span class="text-muted text-xs"><?= esc($permitLabel[$cell['permit_status']] ?? $cell['permit_status']) ?></span>
                    <?php endif; ?>
                    <span class="att-times">
                      <?= $cell['check_in_at'] ? esc(date('H:i', strtotime($cell['check_in_at']))) : '—' ?>
                      –
                      <?= $cell['check_out_at'] ? esc(date('H:i', strtotime($cell['check_out_at']))) : '—' ?>
                    </span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
