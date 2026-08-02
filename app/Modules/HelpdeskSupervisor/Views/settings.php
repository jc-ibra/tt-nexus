<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$g = fn(string $k, string $d = '') => esc($all[$k] ?? $d);
$reuse = ($all['glpi_db_reuse_provisioning'] ?? '1') === '1';
$tabLabels = [
    'clientes_externos' => 'Clientes Externos',
    'areas_internas'    => 'Áreas Internas',
    'control_activos'   => 'Control de Activos',
    'control_envios'    => 'Control de Envíos',
    'ids'               => 'IDS',
];
?>

<div class="page-header">
  <div class="page-header-content"><h1 class="page-title">Configuración · Supervisor de Mesa</h1></div>
</div>

<form method="post" action="<?= route_to('helpdesk.settings.save') ?>">
  <?= csrf_field() ?>

  <!-- Connection -->
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Conexión a GLPI</h2></div>
    <div class="card-body">
      <div class="field">
        <label class="field-check">
          <input type="checkbox" name="glpi_db_reuse_provisioning" value="1" <?= $reuse ? 'checked' : '' ?>>
          <span>Reutilizar la conexión de Provisioning (misma instancia GLPI)</span>
        </label>
        <p class="field-help">Recomendado: el Supervisor audita la misma instancia que ya usa Provisioning. Desactívalo solo si vas a auditar una instancia distinta.</p>
      </div>

      <fieldset style="border:1px solid var(--color-border); border-radius:var(--radius-2); padding:var(--space-3); margin-top:var(--space-2);">
        <legend class="text-muted text-sm">Conexión propia (solo si no reutilizas Provisioning)</legend>
        <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
          <div class="field" style="flex:2; min-width:180px;">
            <label class="field-label" for="glpi_db_host">Host</label>
            <input type="text" id="glpi_db_host" name="glpi_db_host" class="input" value="<?= $g('glpi_db_host') ?>">
          </div>
          <div class="field" style="flex:1; min-width:100px;">
            <label class="field-label" for="glpi_db_port">Puerto</label>
            <input type="number" id="glpi_db_port" name="glpi_db_port" class="input" value="<?= $g('glpi_db_port', '3306') ?>">
          </div>
        </div>
        <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
          <div class="field" style="flex:1; min-width:160px;">
            <label class="field-label" for="glpi_db_name">Base de datos</label>
            <input type="text" id="glpi_db_name" name="glpi_db_name" class="input" value="<?= $g('glpi_db_name') ?>">
          </div>
          <div class="field" style="flex:1; min-width:160px;">
            <label class="field-label" for="glpi_db_user">Usuario</label>
            <input type="text" id="glpi_db_user" name="glpi_db_user" class="input" value="<?= $g('glpi_db_user') ?>">
          </div>
          <div class="field" style="flex:1; min-width:160px;">
            <label class="field-label" for="glpi_db_password">Contraseña</label>
            <input type="password" id="glpi_db_password" name="glpi_db_password" class="input" autocomplete="new-password" placeholder="Dejar vacío para conservar">
          </div>
        </div>
      </fieldset>

      <div style="margin-top:var(--space-3);">
        <button type="button" id="btn-test-conn" class="btn btn-secondary">Probar conexión</button>
        <span id="test-result" class="text-sm" style="margin-left:var(--space-2);"></span>
      </div>
    </div>
  </div>

  <!-- Audit params -->
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Parámetros de auditoría</h2></div>
    <div class="card-body">
      <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
        <div class="field" style="flex:1; min-width:200px;">
          <label class="field-label" for="business_days_abandonment">Días hábiles para abandono (KPI 4)</label>
          <input type="number" min="1" id="business_days_abandonment" name="business_days_abandonment" class="input" value="<?= $g('business_days_abandonment', '5') ?>">
        </div>
        <div class="field" style="flex:1; min-width:200px;">
          <label class="field-label" for="opening_date_tolerance_sec">Tolerancia fecha de apertura (segundos)</label>
          <input type="number" min="1" id="opening_date_tolerance_sec" name="opening_date_tolerance_sec" class="input" value="<?= $g('opening_date_tolerance_sec', '60') ?>">
        </div>
      </div>
    </div>
  </div>

  <!-- Tab mapping -->
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Mapeo de tabs a contenedores del plugin</h2></div>
    <div class="card-body">
      <p class="field-help" style="margin-bottom:var(--space-3);">
        Indica qué contenedor del plugin Additional Fields corresponde a cada tab del manual.
        Las reglas de completitud, tab correcta e IDS usan este mapeo para leer los campos.
      </p>
      <?php if ($containers === []): ?>
        <div class="banner banner-warning"><div class="banner-content">No se pudieron cargar los contenedores de GLPI. Verifica la conexión.</div></div>
      <?php else: ?>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:var(--space-3);">
          <?php foreach ($tabKeys as $tab): $sel = (int) ($all['tab_container_' . $tab] ?? 0); ?>
            <div class="field">
              <label class="field-label" for="tab_container_<?= $tab ?>"><?= esc($tabLabels[$tab] ?? $tab) ?></label>
              <select id="tab_container_<?= $tab ?>" name="tab_container_<?= $tab ?>" class="select">
                <option value="0">Sin mapear</option>
                <?php foreach ($containers as $c): ?>
                  <option value="<?= (int) $c['id'] ?>" <?= $sel === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= esc($c['label']) ?> (#<?= (int) $c['id'] ?>, <?= (int) $c['fieldCount'] ?> campos)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="card-footer">
      <button type="submit" class="btn btn-primary">Guardar configuración</button>
    </div>
  </div>
</form>

<!-- Notificaciones IA (Fase 2) -->
<form method="post" action="<?= route_to('helpdesk.settings.notifications') ?>">
  <?= csrf_field() ?>
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Notificaciones IA</h2></div>
    <div class="card-body">
      <div class="field">
        <label class="field-check">
          <input type="checkbox" name="ai_api_key_reuse_servicedesk" value="1" <?= ($all['ai_api_key_reuse_servicedesk'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span>Reutilizar la API key de Service Desk</span>
        </label>
        <p class="field-help">Recomendado. Si lo desactivas, captura una API key propia abajo.</p>
      </div>

      <div class="field">
        <label class="field-label" for="ai_api_key">API key de Anthropic (propia)</label>
        <input type="password" id="ai_api_key" name="ai_api_key" class="input" autocomplete="new-password" placeholder="Dejar vacío para conservar / usar la de Service Desk">
      </div>

      <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
        <div class="field" style="flex:1; min-width:220px;">
          <label class="field-label" for="ai_model">Modelo</label>
          <select id="ai_model" name="ai_model" class="select">
            <?php foreach (($aiModels ?? []) as $id => $label): ?>
              <option value="<?= esc($id) ?>" <?= ($all['ai_model'] ?? 'claude-haiku-4-5') === $id ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="flex:1; min-width:160px;">
          <label class="field-label" for="ai_max_tokens">Tokens máximos por correo</label>
          <input type="number" min="256" id="ai_max_tokens" name="ai_max_tokens" class="input" value="<?= $g('ai_max_tokens', '2048') ?>">
        </div>
      </div>

      <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
        <div class="field" style="flex:1; min-width:220px;">
          <label class="field-label" for="notification_sender_name">Nombre del remitente</label>
          <input type="text" id="notification_sender_name" name="notification_sender_name" class="input" value="<?= $g('notification_sender_name') ?>" placeholder="Ej: Gerencia de Service Desk">
        </div>
        <div class="field" style="flex:1; min-width:220px;">
          <label class="field-label" for="notification_sender_email">Email del remitente</label>
          <input type="email" id="notification_sender_email" name="notification_sender_email" class="input" value="<?= $g('notification_sender_email') ?>" placeholder="Vacío = usar el From del SMTP">
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="notification_cc">CC por defecto</label>
        <input type="text" id="notification_cc" name="notification_cc" class="input" value="<?= $g('notification_cc') ?>" placeholder="Separados por coma">
      </div>
    </div>
    <div class="card-footer">
      <button type="submit" class="btn btn-primary">Guardar notificaciones</button>
    </div>
  </div>
</form>

<script>
document.getElementById('btn-test-conn')?.addEventListener('click', function () {
  const out = document.getElementById('test-result');
  out.textContent = 'Probando...';
  out.style.color = '';
  fetch('<?= route_to('helpdesk.settings.test') ?>', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' }
  })
  .then(r => r.json())
  .then(d => {
    out.textContent = d.message || (d.status === 'success' ? 'Conexión exitosa.' : 'Error de conexión.');
    out.style.color = d.status === 'success' ? 'var(--color-success)' : 'var(--color-critical)';
  })
  .catch(() => { out.textContent = 'No se pudo probar la conexión.'; out.style.color = 'var(--color-critical)'; });
});
</script>

<?= $this->endSection() ?>
