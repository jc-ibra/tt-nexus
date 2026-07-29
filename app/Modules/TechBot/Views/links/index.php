<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Técnicos vinculados</h1>
    <p class="page-subtitle">Cuentas de Telegram asociadas a empleados. Cada empleado se vincula a una sola cuenta.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('techbot.index') ?>" class="btn btn-secondary">‹ Panel</a>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <?php if (empty($links)): ?>
      <p class="text-muted" style="padding: var(--space-4);">Aún no hay técnicos vinculados. Se vinculan desde Telegram con su número de empleado.</p>
    <?php else: ?>
      <table class="table" style="width:100%;">
        <thead>
          <tr><th>Empleado</th><th># Empleado</th><th>Telegram</th><th>Vinculado</th><th>Última actividad</th><th>Estado</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($links as $l): ?>
            <?php
              $name = trim(($l['employee_name'] ?? '') . ' ' . ($l['employee_lastname'] ?? '')) ?: '—';
              $tg   = $l['telegram_username'] ? '@' . $l['telegram_username'] : ($l['telegram_first_name'] ?: '—');
              $last = $lastSeen[(int) $l['employee_id']] ?? null;
              $active = ($l['status'] ?? '') === 'active';
            ?>
            <tr>
              <td class="text-sm"><a href="<?= route_to('techbot.links.show', (int) $l['id']) ?>"><?= esc($name) ?></a></td>
              <td class="text-sm"><?= esc($l['employee_number'] ?? '—') ?></td>
              <td class="text-sm"><?= esc($tg) ?></td>
              <td class="text-muted text-sm"><?= esc($l['verified_at'] ?? '') ?></td>
              <td class="text-muted text-sm"><?= esc($last ?? '—') ?></td>
              <td><?= $active ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-neutral">Inactivo</span>' ?></td>
              <td style="text-align:right;">
                <?php if ($active): ?>
                  <form method="post" action="<?= route_to('techbot.links.deactivate', (int) $l['id']) ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm">Desactivar</button>
                  </form>
                <?php else: ?>
                  <form method="post" action="<?= route_to('techbot.links.activate', (int) $l['id']) ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-secondary btn-sm">Reactivar</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?= $this->endSection() ?>
