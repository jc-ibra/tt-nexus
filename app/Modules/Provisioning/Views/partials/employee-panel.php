<?php
/**
 * Embedded provisioning panel for the employee detail view.
 *
 * Expects: $employee (array with at least id, employee_number, name, lastname, email, active)
 *
 * Loads accounts/log/systems on the fly so the host view doesn't need to know about provisioning.
 */
$canSeeProvisioning = service('access')->canAccessModule('provisioning');
if (! $canSeeProvisioning) {
    return;
}

$employeeId = (int) ($employee['id'] ?? 0);
$systems    = (new \App\Modules\Provisioning\Models\ProvisioningSystemModel())->listAll();
$accounts   = (new \App\Modules\Provisioning\Models\ProvisioningExternalAccountModel())->listForEmployee($employeeId);
$log        = (new \App\Modules\Provisioning\Models\ProvisioningLogModel())->listForEmployee($employeeId, 10);

$accountsBySystem = [];
foreach ($accounts as $a) {
    $accountsBySystem[(int) $a['system_id']] = $a;
}
?>

<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
    <h2 class="card-title">Aprovisionamiento</h2>
    <span class="text-muted text-sm">Sistemas externos</span>
  </div>
  <div class="card-body" style="padding:0;">
    <table class="table" style="width:100%;">
      <thead>
        <tr>
          <th>Sistema</th>
          <th>Estado</th>
          <th>ID externo</th>
          <th>Último mensaje</th>
          <th style="text-align:right;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($systems as $s):
          $sysId   = (int) $s['id'];
          $account = $accountsBySystem[$sysId] ?? null;
          $isActiveSystem = (int) $s['is_active'] === 1;
        ?>
          <tr>
            <td><strong><?= esc($s['name']) ?></strong>
              <?php if (! $isActiveSystem): ?>
                <br><span class="badge badge-neutral">Sistema inactivo</span>
              <?php endif; ?>
            </td>
            <td>
              <?php
                $status = $account['status'] ?? 'sin_cuenta';
                if ($status === 'active'):
              ?>
                <span class="badge badge-success">Activa</span>
              <?php elseif ($status === 'disabled'): ?>
                <span class="badge badge-neutral">Desactivada</span>
              <?php elseif ($status === 'error'): ?>
                <span class="badge badge-critical">Error</span>
              <?php elseif ($status === 'pending'): ?>
                <span class="badge badge-warning">Pendiente</span>
              <?php else: ?>
                <span class="badge badge-neutral">Sin cuenta</span>
              <?php endif; ?>
            </td>
            <td class="text-sm"><?= esc($account['external_id'] ?? '-') ?></td>
            <td class="text-sm"><?= esc($account['last_message'] ?? '-') ?></td>
            <td style="text-align:right;">
              <?php if (! $account || empty($account['external_id'])): ?>
                <?php if ($isActiveSystem): ?>
                  <form method="post" action="<?= route_to('provisioning.employee.system.provision', $employeeId, $sysId) ?>"
                        style="display:flex; gap:var(--space-1); align-items:center; justify-content:flex-end; flex-wrap:wrap;">
                    <?= csrf_field() ?>
                    <input type="password" name="password" class="input" placeholder="Contraseña" minlength="8" style="max-width:160px; font-size:var(--text-sm);">
                    <button type="submit" class="btn btn-tertiary btn-sm" title="Crear cuenta en <?= esc($s['name']) ?>">Crear</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <?php if ($status !== 'disabled'): ?>
                  <form method="post" action="<?= route_to('provisioning.employee.system.deprovision', $employeeId, $sysId) ?>" style="display:inline;" onsubmit="return confirm('¿Desactivar la cuenta en <?= esc($s['name']) ?>?');">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm">Desactivar</button>
                  </form>
                <?php endif; ?>
                <?php if ($status === 'error'): ?>
                  <form method="post" action="<?= route_to('provisioning.employee.system.provision', $employeeId, $sysId) ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-tertiary btn-sm">Reintentar alta</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card-footer" style="display:flex; flex-direction:column; gap:var(--space-3);">

    <!-- Alta / Baja -->
    <div style="display:flex; gap:var(--space-2); flex-wrap:wrap; align-items:flex-end;">
      <form method="post" action="<?= route_to('provisioning.employee.provision', $employeeId) ?>"
            style="display:flex; gap:var(--space-2); align-items:flex-end; flex-wrap:wrap;"
            onsubmit="return confirm('¿Lanzar alta en todos los sistemas activos?');">
        <?= csrf_field() ?>
        <div>
          <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">Contraseña inicial</label>
          <input type="password" name="password" class="input" placeholder="Requerida para crear cuentas" minlength="8" style="max-width:240px;">
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Alta en todos</button>
      </form>

      <form method="post" action="<?= route_to('provisioning.employee.deprovision', $employeeId) ?>"
            style="display:inline; align-self:flex-end;"
            onsubmit="return confirm('¿Lanzar baja en todos los sistemas? El empleado quedará marcado como inactivo en Nexus.');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary btn-sm" style="color:var(--color-critical-default);">Baja en todos</button>
      </form>
    </div>

    <!-- Cambiar contraseña (siempre visible) -->
    <form method="post" action="<?= route_to('provisioning.employee.password', $employeeId) ?>"
          style="display:flex; gap:var(--space-2); align-items:flex-end; flex-wrap:wrap; padding-top:var(--space-2); border-top:1px solid var(--color-neutral-200);"
          onsubmit="return confirm('¿Propagar la nueva contraseña a todos los sistemas activos?');">
      <?= csrf_field() ?>
      <div>
        <label class="label" style="font-size:var(--text-sm); margin-bottom:var(--space-1); display:block;">Cambiar contraseña en todos los sistemas</label>
        <input type="password" name="password" class="input" placeholder="Nueva contraseña (mín. 8)" minlength="8" required style="max-width:260px;">
      </div>
      <button type="submit" class="btn btn-secondary btn-sm">Propagar contraseña</button>
    </form>

  </div>
</div>

<?php if (! empty($log)): ?>
  <details class="card" style="margin-bottom: var(--space-4);">
    <summary style="padding:var(--space-3) var(--space-4); cursor:pointer; font-weight:600;">
      Bitácora reciente de aprovisionamiento (<?= count($log) ?>)
    </summary>
    <div class="card-body" style="padding:0; border-top:1px solid var(--color-neutral-200);">
      <table class="table" style="width:100%;">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Sistema</th>
            <th>Operación</th>
            <th>Estado</th>
            <th>Mensaje</th>
            <th>Ejecutor</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log as $l): ?>
            <tr>
              <td class="text-sm"><?= esc(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
              <td class="text-sm"><?= esc($l['system_name'] ?: '-') ?></td>
              <td class="text-sm"><code><?= esc($l['operation']) ?></code></td>
              <td>
                <?php if ($l['status'] === 'success'): ?>
                  <span class="badge badge-success">Éxito</span>
                <?php elseif ($l['status'] === 'error'): ?>
                  <span class="badge badge-critical">Error</span>
                <?php else: ?>
                  <span class="badge badge-warning">Pendiente</span>
                <?php endif; ?>
              </td>
              <td class="text-sm"><?= esc($l['message'] ?: '-') ?></td>
              <td class="text-sm"><?= esc($l['executor_name'] ?: '-') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </details>
<?php endif; ?>
