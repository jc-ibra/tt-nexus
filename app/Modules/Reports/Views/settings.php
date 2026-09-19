<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>

<?= $this->section('content') ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Configuración de Informes</h1>
    <p class="page-subtitle">Mapeo de campos GLPI, umbrales, IA y destinatarios del correo mensual.</p>
  </div>
</div>

<form method="post" action="<?= route_to('reports.admin.settings.save') ?>">
  <?= csrf_field() ?>
  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header">
      <h2 class="card-title">Campos GLPI</h2>
      <p class="card-subtitle">Cada campo puede vivir en un contenedor distinto del plugin Additional Fields (por ejemplo, IDS suele tener su propio contenedor, separado del de Regional/Estado/Municipio/Sucursal).</p>
    </div>
    <div class="card-body">
      <?php if (! $glpiReady): ?>
        <div class="banner banner-warning" role="alert"><div class="banner-body">No se pudo leer el esquema de GLPI. Verifica la conexión en Provisioning.</div></div>
      <?php elseif ($fieldOptions === []): ?>
        <div class="banner banner-warning" role="alert"><div class="banner-body">GLPI no tiene contenedores del plugin Additional Fields activos.</div></div>
      <?php else: ?>
        <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
          <?php foreach ($logicalFields as $key => $label): ?>
            <?php $current = isset($bindings[$key]) ? $bindings[$key]['container_id'] . ':' . $bindings[$key]['field'] : ''; ?>
            <div class="field">
              <label class="field-label" for="binding_<?= $key ?>"><?= esc($label) ?></label>
              <select name="binding_<?= $key ?>" id="binding_<?= $key ?>" class="select">
                <option value="">No mapeado</option>
                <?php foreach ($fieldOptions as $value => $optLabel): ?>
                  <option value="<?= esc($value, 'attr') ?>" <?= $current === $value ? 'selected' : '' ?>><?= esc($optLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endforeach; ?>
        </div>
        <p class="field-help" style="margin-top: var(--space-3);">"Proyecto" y "Cliente" no se mapean aquí: esa información ya viene dada por la categoría del ticket.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Umbrales</h2></div>
    <div class="card-body">
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
        <div class="field">
          <label class="field-label" for="sla_hours">SLA (horas)</label>
          <input type="number" min="1" name="sla_hours" id="sla_hours" class="input" value="<?= esc($settings['sla_hours']) ?>">
        </div>
        <div class="field">
          <label class="field-label" for="trend_months">Meses de tendencia</label>
          <input type="number" min="1" max="24" name="trend_months" id="trend_months" class="input" value="<?= esc($settings['trend_months']) ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Resumen ejecutivo con IA</h2></div>
    <div class="card-body">
      <div class="field-check" style="margin-bottom: var(--space-4);">
        <input type="checkbox" id="ai_enabled" name="ai_enabled" value="1" <?= $settings['ai_enabled'] === '1' ? 'checked' : '' ?>>
        <label for="ai_enabled">Habilitar generación de resumen con IA</label>
      </div>
      <div class="field-check" style="margin-bottom: var(--space-4);">
        <input type="checkbox" id="ai_reuse_helpdesk_supervisor" name="ai_reuse_helpdesk_supervisor" value="1" <?= $settings['ai_reuse_helpdesk_supervisor'] === '1' ? 'checked' : '' ?>>
        <label for="ai_reuse_helpdesk_supervisor">Reusar la clave de IA de HelpdeskSupervisor</label>
      </div>
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
        <div class="field">
          <label class="field-label" for="ai_model">Modelo</label>
          <input type="text" name="ai_model" id="ai_model" class="input" value="<?= esc($settings['ai_model']) ?>">
        </div>
        <div class="field">
          <label class="field-label" for="ai_api_key">Clave API propia (opcional)</label>
          <input type="password" name="ai_api_key" id="ai_api_key" class="input" placeholder="<?= $settings['ai_api_key'] !== '' ? 'definida' : 'sk-ant-...' ?>">
          <p class="field-help">Déjala en blanco para conservar la actual o para usar la de HelpdeskSupervisor.</p>
        </div>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Envío por correo</h2></div>
    <div class="card-body">
      <div class="form-group" style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-4);">
        <div class="field">
          <label class="field-label" for="email_recipients">Destinatarios (separados por coma)</label>
          <input type="text" name="email_recipients" id="email_recipients" class="input" value="<?= esc($settings['email_recipients']) ?>">
        </div>
        <div class="field">
          <label class="field-label" for="email_sender_name">Nombre del remitente</label>
          <input type="text" name="email_sender_name" id="email_sender_name" class="input" value="<?= esc($settings['email_sender_name']) ?>">
        </div>
      </div>
    </div>
  </div>

  <div class="page-actions">
    <button type="submit" class="btn btn-primary">Guardar configuración</button>
  </div>
</form>

<?= $this->endSection() ?>
