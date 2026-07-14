<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$s = $settings;
$includedRaw = trim((string) ($s['included_container_ids'] ?? ''));
$included    = $includedRaw === '' ? [] : array_map('intval', explode(',', $includedRaw));
$val = fn(string $k, string $d = '') => esc($s[$k] ?? $d);
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Configuración · Service Desk</h1>
    <p class="page-subtitle">Controla los límites de importación y los destinos en GLPI. Los operadores solo consumen esta configuración.</p>
  </div>
  <div class="page-actions">
    <a href="#ai" class="btn btn-secondary">Ir a IA</a>
    <a href="<?= route_to('servicedesk.categories') ?>" class="btn btn-secondary">Categorías y CLIENTE</a>
    <button type="submit" form="sd-settings" class="btn btn-primary">Guardar cambios</button>
  </div>
</div>

<?php if (! $configured): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body">La conexión a GLPI no está configurada; los contenedores no se pueden listar todavía. Configúrala en Configuración · Sistemas.</div>
  </div>
<?php endif; ?>

<form id="sd-settings" action="<?= route_to('servicedesk.settings.save') ?>" method="post" style="max-width: 760px;">
  <?= csrf_field() ?>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Límites de importación</h2></div>
    <div class="card-body">
      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="import_max_rows">Máximo de tickets por archivo</label>
        <input type="number" id="import_max_rows" name="import_max_rows" class="input" min="0" value="<?= $val('import_max_rows', '500') ?>">
        <p class="text-muted text-sm">0 = sin límite.</p>
      </div>
      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="import_batch_size">Tamaño de lote</label>
        <input type="number" id="import_batch_size" name="import_batch_size" class="input" min="1" value="<?= $val('import_batch_size', '30') ?>">
      </div>
      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="import_batch_pause_sec">Pausa entre lotes (segundos)</label>
        <input type="number" id="import_batch_pause_sec" name="import_batch_pause_sec" class="input" min="0" value="<?= $val('import_batch_pause_sec', '2') ?>">
      </div>
      <label class="field-check">
        <input type="checkbox" name="import_enabled" value="1" <?= ($s['import_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
        <span>Importación habilitada para el rol Service Desk</span>
      </label>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Destino en GLPI</h2></div>
    <div class="card-body">
      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="glpi_entities_id">Entidad (entities_id)</label>
        <input type="number" id="glpi_entities_id" name="glpi_entities_id" class="input" min="0" value="<?= $val('glpi_entities_id', '0') ?>">
      </div>
      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="glpi_requester_user_id">Solicitante por defecto (users_id, 0 = ninguno)</label>
        <input type="number" id="glpi_requester_user_id" name="glpi_requester_user_id" class="input" min="0" value="<?= $val('glpi_requester_user_id', '0') ?>">
      </div>
      <label class="field-check">
        <input type="checkbox" name="autocreate_catalog_values" value="1" <?= ($s['autocreate_catalog_values'] ?? '0') === '1' ? 'checked' : '' ?>>
        <span>Autocrear valores de catálogo faltantes al importar</span>
      </label>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Contenedores disponibles</h2></div>
    <div class="card-body">
      <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
        Marca qué tipos de ticket pueden importar los operadores. Si no marcas ninguno, se permiten todos los activos.
      </p>
      <?php if (empty($containers)): ?>
        <p class="text-muted">No hay contenedores para mostrar.</p>
      <?php else: ?>
        <div style="display:flex; flex-direction:column; gap: var(--space-2);">
          <?php foreach ($containers as $c): ?>
            <label class="field-check">
              <input type="checkbox" name="included_container_ids[]" value="<?= (int) $c['id'] ?>"
                     <?= in_array((int) $c['id'], $included, true) ? 'checked' : '' ?>>
              <span><strong><?= esc($c['label']) ?></strong>
                <span class="text-muted text-sm">· <?= (int) $c['fieldCount'] ?> campos · id <?= (int) $c['id'] ?></span>
              </span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php
$aiModels = $aiModels ?? [];
$aiHasKey = $aiHasKey ?? false;
$u        = $aiUsage ?? ['calls' => 0, 'input' => 0, 'output' => 0, 'cost' => 0, 'proposed' => 0, 'created' => 0];
$curModel = $s['ai_model'] ?? 'claude-haiku-4-5';
?>

<form id="sd-ai" action="<?= route_to('servicedesk.ai.save') ?>" method="post" style="max-width: 760px;">
  <?= csrf_field() ?>

  <div id="ai" class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
      <h2 class="card-title">Creador de tickets con IA (Claude)</h2>
      <button type="submit" class="btn btn-primary">Guardar IA</button>
    </div>
    <div class="card-body">
      <p class="text-muted text-sm" style="margin-top:0;">
        Habilita un asistente que ayuda a aperturar tickets casi idénticos por chat. Solo propone filas: la creación pasa por la misma validación y worker del importador.
      </p>

      <label class="field-check" style="margin-bottom: var(--space-3);">
        <input type="checkbox" name="ai_enabled" value="1" <?= ($s['ai_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
        <span>Habilitar el creador con IA para el rol Service Desk</span>
      </label>

      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="ai_api_key">API key de Claude</label>
        <input type="password" id="ai_api_key" name="ai_api_key" class="input" autocomplete="off"
               placeholder="<?= $aiHasKey ? 'Guardada (déjalo vacío para conservarla)' : 'sk-ant-...' ?>">
        <p class="text-muted text-sm">Se guarda cifrada. Déjalo vacío para conservar la key actual.</p>
      </div>

      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="ai_model">Modelo</label>
        <select id="ai_model" name="ai_model" class="input">
          <?php foreach ($aiModels as $id => $label): ?>
            <option value="<?= esc($id, 'attr') ?>" <?= $curModel === $id ? 'selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="ai_max_tickets_per_request">Máximo de tickets por solicitud</label>
        <input type="number" id="ai_max_tickets_per_request" name="ai_max_tickets_per_request" class="input" min="1" value="<?= $val('ai_max_tickets_per_request', '25') ?>">
      </div>

      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="ai_daily_token_budget">Presupuesto diario de tokens (0 = sin límite)</label>
        <input type="number" id="ai_daily_token_budget" name="ai_daily_token_budget" class="input" min="0" value="<?= $val('ai_daily_token_budget', '0') ?>">
      </div>

      <div class="field">
        <label class="field-label" for="ai_system_prompt">Instrucciones del asistente (prompt)</label>
        <textarea id="ai_system_prompt" name="ai_system_prompt" class="input" rows="12"
                  style="font-family: var(--font-mono, monospace); font-size: 12px; line-height:1.5;"><?= esc($aiInstructions ?? '') ?></textarea>
        <p class="text-muted text-sm">
          Define la personalidad, el alcance y el estilo del asistente. El sistema añade automáticamente las reglas técnicas
          (uso de la herramienta, estatus EN CURSO, fechas, máximo de tickets) y la lista de campos y catálogos del contenedor,
          así que no necesitas incluirlas aquí. Déjalo vacío para restaurar el texto por defecto.
        </p>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Uso de IA (últimos 30 días)</h2></div>
    <div class="card-body">
      <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: var(--space-3);">
        <div><div class="text-muted text-sm">Llamadas</div><div style="font-size:1.4rem; font-weight:600;"><?= number_format($u['calls']) ?></div></div>
        <div><div class="text-muted text-sm">Tokens entrada</div><div style="font-size:1.4rem; font-weight:600;"><?= number_format($u['input']) ?></div></div>
        <div><div class="text-muted text-sm">Tokens salida</div><div style="font-size:1.4rem; font-weight:600;"><?= number_format($u['output']) ?></div></div>
        <div><div class="text-muted text-sm">Costo estimado</div><div style="font-size:1.4rem; font-weight:600;">$<?= number_format((float) $u['cost'], 4) ?></div></div>
        <div><div class="text-muted text-sm">Tickets propuestos</div><div style="font-size:1.4rem; font-weight:600;"><?= number_format($u['proposed']) ?></div></div>
        <div><div class="text-muted text-sm">Tickets creados</div><div style="font-size:1.4rem; font-weight:600;"><?= number_format($u['created']) ?></div></div>
      </div>
    </div>
  </div>
</form>

<?= $this->endSection() ?>
