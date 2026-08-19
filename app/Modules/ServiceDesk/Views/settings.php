<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$s = $settings;
$includedRaw = trim((string) ($s['included_container_ids'] ?? ''));
$included    = $includedRaw === '' ? [] : array_map('intval', explode(',', $includedRaw));
$val = fn(string $k, string $d = '') => esc($s[$k] ?? $d);

$aiModels = $aiModels ?? [];
$aiHasKey = $aiHasKey ?? false;
$u        = $aiUsage ?? ['calls' => 0, 'input' => 0, 'output' => 0, 'cost' => 0, 'proposed' => 0, 'created' => 0];
$curModel = $s['ai_model'] ?? 'claude-haiku-4-5';

$wContainer   = (int) ($s['widget_container_id'] ?? 0);
$embedSnippet = '<script src="' . base_url('servicedesk/widget/embed.js?key=' . rawurlencode($widgetSiteKey)) . '" async></script>';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Configuración · Service Desk</h1>
    <p class="page-subtitle">Controla los límites de importación y los destinos en GLPI. Los operadores solo consumen esta configuración.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('servicedesk.categories') ?>" class="btn btn-secondary">Categorías y CLIENTE</a>
  </div>
</div>

<?php if (! $configured): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
    <div class="banner-body">La conexión a GLPI no está configurada; los contenedores no se pueden listar todavía. Configúrala en Configuración · Sistemas.</div>
  </div>
<?php endif; ?>

<style>
.sd-tabs {
  display: flex;
  gap: var(--space-1);
  border-bottom: 1px solid var(--color-neutral-200);
  margin-bottom: var(--space-4);
}
.sd-tab {
  appearance: none;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  padding: var(--space-3) var(--space-4);
  cursor: pointer;
  font-size: var(--text-sm);
  font-weight: var(--weight-medium);
  color: var(--text-secondary);
  transition: color var(--duration-base), border-color var(--duration-base);
}
.sd-tab:hover { color: var(--text-primary); }
.sd-tab.is-active {
  color: var(--color-primary);
  font-weight: var(--weight-semibold);
  border-bottom-color: var(--color-primary);
}
.sd-tab:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: -2px;
  border-radius: var(--radius-sm);
}
</style>

<?php if (! empty($glpiError)): ?>
<div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
  <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
  </svg>
  <div class="banner-body"><?= esc($glpiError) ?></div>
</div>
<?php endif; ?>

<div class="sd-tabs" role="tablist" aria-label="Secciones de configuración">
  <button type="button" class="sd-tab is-active" id="sd-tab-import" role="tab"
          aria-selected="true" aria-controls="sd-panel-import" data-panel="sd-panel-import" data-hash="import">
    Importación
  </button>
  <button type="button" class="sd-tab" id="sd-tab-update" role="tab"
          aria-selected="false" aria-controls="sd-panel-update" tabindex="-1" data-panel="sd-panel-update" data-hash="update">
    Actualización masiva
  </button>
  <button type="button" class="sd-tab" id="sd-tab-ai" role="tab"
          aria-selected="false" aria-controls="sd-panel-ai" tabindex="-1" data-panel="sd-panel-ai" data-hash="ai">
    Creador con IA
  </button>
  <button type="button" class="sd-tab" id="sd-tab-widget" role="tab"
          aria-selected="false" aria-controls="sd-panel-widget" tabindex="-1" data-panel="sd-panel-widget" data-hash="widget">
    Widget
  </button>
  <button type="button" class="sd-tab" id="sd-tab-backlog" role="tab"
          aria-selected="false" aria-controls="sd-panel-backlog" tabindex="-1" data-panel="sd-panel-backlog" data-hash="backlog">
    Reporte de Backlog
  </button>
</div>

<!-- Tab: Importación -->
<div id="sd-panel-import" class="sd-tab-panel" role="tabpanel" aria-labelledby="sd-tab-import">
  <form id="sd-settings" action="<?= route_to('servicedesk.settings.save') ?>" method="post" style="max-width: 760px;">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Límites de importación</h2>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
      <div class="card-body">
        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="import_max_rows">Máximo de tickets por archivo</label>
          <input type="number" id="import_max_rows" name="import_max_rows" class="input" min="0" value="<?= $val('import_max_rows', '500') ?>">
          <p class="field-help">0 = sin límite.</p>
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
          <p class="text-muted text-sm" style="margin-top: var(--space-1);">
            Se usa como respaldo: si el usuario de Nexus que sube la importación tiene un GLPI user asignado, ese será el solicitante.
          </p>
        </div>
        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="glpi_request_source_id">Origen de la solicitud en GLPI (requesttypes_id, 0 = predeterminado de GLPI)</label>
          <input type="number" id="glpi_request_source_id" name="glpi_request_source_id" class="input" min="0" value="<?= $val('glpi_request_source_id', '0') ?>">
          <p class="text-muted text-sm" style="margin-top: var(--space-1);">
            Crea un origen de la solicitud en GLPI para las importaciones y pega aquí su id; cada ticket importado se marcará con ese origen.
          </p>
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
</div><!-- /sd-panel-import -->

<!-- Tab: Actualización masiva -->
<div id="sd-panel-update" class="sd-tab-panel" role="tabpanel" aria-labelledby="sd-tab-update" style="display:none;">
  <form id="sd-update" action="<?= route_to('servicedesk.update.save') ?>" method="post" style="max-width: 760px;">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Actualización y cierre masivo</h2>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
          Permite subir el mismo Excel del importador con la columna TICKET_ID llena para corregir tickets que
          ya existen en GLPI y cerrarlos. Una celda llena se escribe encima de lo que haya; una celda vacía no
          se toca.
        </p>

        <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
          <div class="banner-body">
            Es una operación destructiva y, al cerrar tickets, GLPI envía sus notificaciones a los
            solicitantes. Habilítala solo cuando el equipo sepa correr primero una simulación.
          </div>
        </div>

        <label class="field-check" style="margin-bottom: var(--space-3);">
          <input type="checkbox" name="update_enabled" value="1" <?= ($s['update_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span>Habilitar la sección Actualizar y cerrar para el rol Service Desk</span>
        </label>

        <label class="field-check" style="margin-bottom: var(--space-3);">
          <input type="checkbox" name="update_reopen_closed" value="1" <?= ($s['update_reopen_closed'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span>
            Reabrir tickets cerrados para poder corregirlos
            <span class="text-muted text-sm">
              · GLPI bloquea la edición de cerrados. Se reabren, se escriben y se vuelven a cerrar con su
              fecha original. Sin esto no se pueden corregir tickets ya cerrados.
            </span>
          </span>
        </label>

        <label class="field-check" style="margin-bottom: var(--space-3);">
          <input type="checkbox" name="update_verify_writes" value="1" <?= ($s['update_verify_writes'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span>
            Verificar cada escritura releyendo el ticket
            <span class="text-muted text-sm">
              · si una regla de negocio de GLPI reescribe el valor, la fila se reporta como DESVIACION en
              lugar de darse por buena.
            </span>
          </span>
        </label>

        <label class="field-check" style="margin-bottom: var(--space-4);">
          <input type="checkbox" name="update_rehomologate_title" value="1" <?= ($s['update_rehomologate_title'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span>
            Rehomologar el título al cambiar la categoría
            <span class="text-muted text-sm">
              · cuando la fila cambia CATEGORIA pero no trae TITULO, intercambia el prefijo CLIENTE del
              título. Solo actúa si el título empieza exactamente con el cliente anterior.
            </span>
          </span>
        </label>

        <div class="field">
          <label class="field-label" for="update_solution_text">Texto de solución por default</label>
          <textarea id="update_solution_text" name="update_solution_text" class="input" rows="3"
                    maxlength="2000"><?= esc($s['update_solution_text'] ?? 'Cierre masivo desde Nexus. Ticket atendido y validado.') ?></textarea>
          <p class="text-muted text-sm" style="margin-top: var(--space-1);">
            Se registra como solución en GLPI al cerrar un ticket cuya fila no trae la columna SOLUCION.
            Los tickets que ya tenían una solución no se ensucian con otra.
          </p>
        </div>
      </div>
    </div>
  </form>
</div><!-- /sd-panel-update -->

<!-- Tab: Creador con IA -->
<div id="sd-panel-ai" class="sd-tab-panel" role="tabpanel" aria-labelledby="sd-tab-ai" style="display:none;">
  <form id="sd-ai" action="<?= route_to('servicedesk.ai.save') ?>" method="post" style="max-width: 760px;">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Creador de tickets con IA (Claude)</h2>
        <button type="submit" class="btn btn-primary">Guardar IA</button>
      </div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
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
          <p class="field-help">Se guarda cifrada. Déjalo vacío para conservar la key actual.</p>
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

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="ai_request_source_id">Origen de la solicitud en GLPI (requesttypes_id, 0 = predeterminado de GLPI)</label>
          <input type="number" id="ai_request_source_id" name="ai_request_source_id" class="input" min="0" value="<?= $val('ai_request_source_id', '0') ?>">
          <p class="text-muted text-sm" style="margin-top: var(--space-1);">
            Crea un origen de la solicitud en GLPI para el creador con IA y pega aquí su id; cada ticket creado por IA se marcará con ese origen.
          </p>
        </div>

        <div class="field">
          <label class="field-label" for="ai_system_prompt">Instrucciones del asistente (prompt)</label>
          <textarea id="ai_system_prompt" name="ai_system_prompt" class="input" rows="12"
                    style="font-family: var(--font-mono, monospace); font-size: 12px; line-height:1.5;"><?= esc($aiInstructions ?? '') ?></textarea>
          <p class="field-help">
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
</div><!-- /sd-panel-ai -->

<!-- Tab: Widget -->
<div id="sd-panel-widget" class="sd-tab-panel" role="tabpanel" aria-labelledby="sd-tab-widget" style="display:none;">
  <form id="sd-widget" action="<?= route_to('servicedesk.widget.save') ?>" method="post" style="max-width: 760px;"
        data-schema-url="<?= route_to('servicedesk.schema') ?>"
        data-equipo="<?= esc($s['widget_field_equipo'] ?? '', 'attr') ?>"
        data-modelo="<?= esc($s['widget_field_modelo'] ?? '', 'attr') ?>"
        data-serie="<?= esc($s['widget_field_serie'] ?? '', 'attr') ?>"
        data-categoria="<?= esc($s['widget_field_categoria'] ?? '', 'attr') ?>">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Widget de autoservicio</h2>
        <button type="submit" class="btn btn-primary">Guardar widget</button>
      </div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
          Un chat con IA embebible en la intranet. El usuario final describe su problema y se crea un ticket en GLPI
          con estatus <strong>NUEVO</strong> y la categoría fija que elijas en
          <a href="<?= route_to('servicedesk.categories') ?>">Categorías</a>. Comparte la misma API key y modelo de la sección IA.
        </p>

        <label class="field-check" style="margin-bottom: var(--space-4);">
          <input type="checkbox" name="widget_enabled" value="1" <?= ($s['widget_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span>Habilitar el widget de autoservicio</span>
        </label>

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="widget_title">Título del widget</label>
          <input type="text" id="widget_title" name="widget_title" class="input" maxlength="60"
                 value="<?= $val('widget_title') ?>" placeholder="Soporte">
        </div>

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="widget_welcome">Mensaje de bienvenida</label>
          <textarea id="widget_welcome" name="widget_welcome" class="input" rows="2"
                    placeholder="Hola, cuéntame qué problema tienes..."><?= esc($s['widget_welcome'] ?? '') ?></textarea>
        </div>

        <div class="field">
          <label class="field-label" for="widget_system_prompt">Instrucciones del asistente (prompt)</label>
          <textarea id="widget_system_prompt" name="widget_system_prompt" class="input" rows="8"
                    style="font-family: var(--font-mono, monospace); font-size:12px; line-height:1.5;"><?= esc($widgetPrompt ?? '') ?></textarea>
          <p class="field-help">
            Personalidad y alcance del asistente hacia el usuario final. El sistema añade automáticamente las reglas
            (estatus NUEVO, tipo inferido, fecha actual, campos de hardware) y los catálogos. Vacío = texto por defecto.
          </p>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Instalación y seguridad</h2></div>
      <div class="card-body">
        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label">Código para insertar en la intranet</label>
          <input type="text" class="input" readonly onclick="this.select()"
                 value="<?= esc($embedSnippet, 'attr') ?>" style="font-family: var(--font-mono, monospace); font-size:12px;">
          <p class="field-help">Pega este script en la página de la intranet. Incluye la clave pública del widget.</p>
        </div>

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="widget_allowed_origins">Orígenes permitidos (uno por línea)</label>
          <textarea id="widget_allowed_origins" name="widget_allowed_origins" class="input" rows="3"
                    placeholder="https://intranet.miempresa.com"><?= esc($s['widget_allowed_origins'] ?? '') ?></textarea>
          <p class="field-help">Solo estos dominios podrán embeber el widget. Formato origen: esquema://host[:puerto], sin ruta.</p>
        </div>

        <label class="field-check" style="margin-bottom: var(--space-3);">
          <input type="checkbox" name="regenerate_site_key" value="1">
          <span>Regenerar la clave pública (invalida todas las instalaciones actuales)</span>
        </label>

        <div class="field">
          <label class="field-label" for="widget_rate_limit_per_hour">Límite de solicitudes por IP por hora (0 = sin límite)</label>
          <input type="number" id="widget_rate_limit_per_hour" name="widget_rate_limit_per_hour" class="input" min="0"
                 value="<?= $val('widget_rate_limit_per_hour', '20') ?>">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Destino y hardware</h2></div>
      <div class="card-body">
        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="widget_requester_user_id">Solicitante genérico en GLPI (users_id, 0 = usar el de importación)</label>
          <input type="number" id="widget_requester_user_id" name="widget_requester_user_id" class="input" min="0"
                 value="<?= $val('widget_requester_user_id', '0') ?>">
        </div>
        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="widget_entities_id">Entidad (entities_id, vacío = usar la de importación)</label>
          <input type="number" id="widget_entities_id" name="widget_entities_id" class="input" min="0"
                 value="<?= $val('widget_entities_id') ?>">
        </div>
        <div class="field" style="margin-bottom: var(--space-4);">
          <label class="field-label" for="widget_request_source_id">Origen de la solicitud en GLPI (requesttypes_id, 0 = predeterminado de GLPI)</label>
          <input type="number" id="widget_request_source_id" name="widget_request_source_id" class="input" min="0"
                 value="<?= $val('widget_request_source_id', '0') ?>">
          <p class="text-muted text-sm" style="margin-top: var(--space-1);">
            Crea un origen de la solicitud en GLPI para el widget y pega aquí su id; cada ticket del widget se marcará con ese origen.
          </p>
        </div>

        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-3);">
          Cuando el reporte involucra un equipo, el widget captura equipo, modelo y serie en un contenedor de campos
          adicionales (Áreas Internas). Elige el contenedor y a qué campo corresponde cada dato. El campo "categoría"
          lo infiere la IA. Deja el contenedor en "Ninguno" para desactivar la captura de hardware.
        </p>

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="widget_container_id">Contenedor de hardware</label>
          <select id="widget_container_id" name="widget_container_id" class="input">
            <option value="0">Ninguno (sin captura de hardware)</option>
            <?php foreach ($containers as $c): ?>
              <option value="<?= (int) $c['id'] ?>" <?= $wContainer === (int) $c['id'] ? 'selected' : '' ?>>
                <?= esc($c['label']) ?> · id <?= (int) $c['id'] ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-3);">
          <div class="field">
            <label class="field-label" for="widget_field_equipo">Campo: Equipo (dropdown)</label>
            <select id="widget_field_equipo" name="widget_field_equipo" class="input" data-role="equipo"><option value="">—</option></select>
          </div>
          <div class="field">
            <label class="field-label" for="widget_field_modelo">Campo: Modelo</label>
            <select id="widget_field_modelo" name="widget_field_modelo" class="input" data-role="modelo"><option value="">—</option></select>
          </div>
          <div class="field">
            <label class="field-label" for="widget_field_serie">Campo: Serie</label>
            <select id="widget_field_serie" name="widget_field_serie" class="input" data-role="serie"><option value="">—</option></select>
          </div>
          <div class="field">
            <label class="field-label" for="widget_field_categoria">Campo: Categoría (dropdown)</label>
            <select id="widget_field_categoria" name="widget_field_categoria" class="input" data-role="categoria"><option value="">—</option></select>
          </div>
        </div>
        <p class="field-help" style="margin-top: var(--space-3);">La categoría ITIL del ticket se elige en la pantalla de Categorías; aquí solo se mapea el campo del contenedor.</p>
      </div>
    </div>
  </form>

  <?php
  $landingUrl = base_url('soporte');
  $landingReady = $landingReady ?? false;
  $hasSupportedCats = $hasSupportedCats ?? false;
  ?>
  <form id="sd-landing" action="<?= route_to('servicedesk.landing.save') ?>" method="post" style="max-width: 760px;">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Landing pública de autoservicio</h2>
        <button type="submit" class="btn btn-primary">Guardar landing</button>
      </div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
          Página pública e independiente del widget embebido: vive en una URL propia de Nexus, con su propia clave, y es
          la única página pública del sistema. Muestra un <strong>formulario completo</strong> donde la persona captura sus datos,
          <strong>elige la categoría ITIL</strong> (de las soportadas en Categorías) y llena los campos adicionales de los
          contenedores que selecciones abajo. Encima del formulario aparece un <strong>chat flotante</strong> opcional (requiere IA)
          para quien prefiera ser guiado. El formulario funciona sin IA.
        </p>

        <?php if (! $hasSupportedCats): ?>
          <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
            <div class="banner-body">
              No hay categorías marcadas como soportadas todavía. La landing necesita al menos una:
              defínelas en <a href="<?= route_to('servicedesk.categories') ?>">Categorías y CLIENTE</a>.
            </div>
          </div>
        <?php endif; ?>

        <label class="field-check" style="margin-bottom: var(--space-4);">
          <input type="checkbox" name="landing_enabled" value="1" <?= ($s['landing_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span>Habilitar la landing pública</span>
        </label>

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label">URL pública</label>
          <div style="display:flex; gap: var(--space-2); flex-wrap:wrap;">
            <input type="text" class="input" readonly value="<?= esc($landingUrl, 'attr') ?>"
                   id="landing-url" style="flex:1; min-width:240px;" onclick="this.select()">
            <button type="button" class="btn btn-secondary" onclick="navigator.clipboard&amp;&amp;navigator.clipboard.writeText(document.getElementById('landing-url').value)">Copiar</button>
            <a href="<?= esc($landingUrl, 'attr') ?>" target="_blank" rel="noopener" class="btn btn-secondary">Abrir</a>
          </div>
          <p class="field-help">Comparte solo esta URL. El resto del sistema sigue protegido; únicamente esta página es pública.</p>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
          <div class="field">
            <label class="field-label" for="landing_title">Título de la página</label>
            <input type="text" id="landing_title" name="landing_title" class="input" maxlength="60"
                   value="<?= $val('landing_title') ?>" placeholder="Mesa de Ayuda">
          </div>
          <div class="field">
            <label class="field-label" for="landing_rate_limit_per_hour">Límite de solicitudes por IP por hora (0 = sin límite)</label>
            <input type="number" id="landing_rate_limit_per_hour" name="landing_rate_limit_per_hour" class="input" min="0"
                   value="<?= $val('landing_rate_limit_per_hour', '10') ?>">
          </div>
        </div>

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="landing_intro">Texto de bienvenida</label>
          <textarea id="landing_intro" name="landing_intro" class="input" rows="2"
                    placeholder="Completa tus datos, elige la categoría y cuéntame qué necesitas..."><?= esc($s['landing_intro'] ?? '') ?></textarea>
          <p class="field-help">El asistente usa las mismas instrucciones (prompt) y la misma API de IA que el widget.</p>
        </div>

        <?php
        $landingContainers = trim((string) ($s['landing_container_ids'] ?? ''));
        $landingContSel    = $landingContainers === '' ? [] : array_map('intval', explode(',', $landingContainers));
        ?>
        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label">Campos adicionales del formulario</label>
          <p class="field-help" style="margin-top:0; margin-bottom: var(--space-2);">
            Elige los contenedores cuyos campos adicionales pedirá el formulario público. Igual que en el creador de tickets:
            si marcas un contenedor, sus campos se reflejan en el formulario. El título, la descripción, el tipo, la ubicación
            y la categoría ya se piden siempre.
          </p>
          <?php if (empty($containers)): ?>
            <p class="field-help">No hay contenedores disponibles (¿GLPI configurado?).</p>
          <?php else: ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-2);">
              <?php foreach ($containers as $c): ?>
                <label class="field-check" style="margin:0;">
                  <input type="checkbox" name="landing_container_ids[]" value="<?= (int) $c['id'] ?>"
                         <?= in_array((int) $c['id'], $landingContSel, true) ? 'checked' : '' ?>>
                  <span><?= esc($c['label']) ?> <span class="text-muted">· <?= (int) ($c['fieldCount'] ?? 0) ?> campos</span></span>
                </label>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <label class="field-check" style="margin-bottom: var(--space-2);">
          <input type="checkbox" name="regenerate_landing_key" value="1">
          <span>Regenerar la clave de la landing (invalida la clave actual de la URL)</span>
        </label>

        <?php if ((($s['landing_enabled'] ?? '0') === '1') && ! $landingReady): ?>
          <p class="field-help" style="color: var(--color-warning-text, #8a6d00);">
            <?php if (! $hasSupportedCats): ?>
              La landing está habilitada pero el formulario aún no puede crear tickets: falta al menos una categoría soportada.
            <?php else: ?>
              El formulario está listo. El chat flotante permanece oculto hasta configurar la IA (pestaña Creador con IA).
            <?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </form>
</div><!-- /sd-panel-widget -->

<!-- Tab: Reporte de Backlog -->
<?php
$bArea    = $backlogAreaMap ?? [];
$bAreas   = $backlogAreas ?? [];
$bRoots   = $backlogRoots ?? [];
$bRuns    = $backlogRuns ?? [];
$bIdcCont = (int) ($s['backlog_idc_container_id'] ?? 0);
$bRegCont = (int) ($s['backlog_regional_container_id'] ?? 0);
$bEstCont = (int) ($s['backlog_estado_container_id'] ?? 0);
$bMunCont = (int) ($s['backlog_municipio_container_id'] ?? 0);
?>
<div id="sd-panel-backlog" class="sd-tab-panel" role="tabpanel" aria-labelledby="sd-tab-backlog" style="display:none;">

  <form id="sd-backlog" action="<?= route_to('servicedesk.backlog.save') ?>" method="post" style="max-width: 760px;"
        data-schema-url="<?= route_to('servicedesk.schema') ?>"
        data-idc="<?= esc($s['backlog_idc_field'] ?? '', 'attr') ?>"
        data-regional="<?= esc($s['backlog_regional_field'] ?? '', 'attr') ?>"
        data-estado="<?= esc($s['backlog_estado_field'] ?? '', 'attr') ?>"
        data-municipio="<?= esc($s['backlog_municipio_field'] ?? '', 'attr') ?>">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Reporte diario de backlog</h2>
        <button type="submit" class="btn btn-primary">Guardar reporte</button>
      </div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-4);">
          Envía por correo, a una hora de corte, un resumen del backlog de tickets abiertos de GLPI (KPIs y antigüedad por área)
          con el detalle completo en un archivo Excel adjunto. Usa el SMTP global con el remitente que definas aquí.
        </p>

        <label class="field-check" style="margin-bottom: var(--space-4);">
          <input type="checkbox" name="backlog_enabled" value="1" <?= ($s['backlog_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span>Habilitar el envío automático del reporte</span>
        </label>

        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-3);">
          <div class="field">
            <label class="field-label" for="backlog_send_hour">Hora de corte y envío</label>
            <input type="time" id="backlog_send_hour" name="backlog_send_hour" class="input" value="<?= $val('backlog_send_hour', '08:00') ?>">
            <p class="field-help">Zona horaria del servidor. El cron dispara al llegar esta hora.</p>
          </div>
          <div class="field">
            <label class="field-label" for="backlog_critical_days">Umbral de "crítico" (días)</label>
            <input type="number" id="backlog_critical_days" name="backlog_critical_days" class="input" min="1" value="<?= $val('backlog_critical_days', '30') ?>">
          </div>
        </div>

        <label class="field-check" style="margin-bottom: var(--space-3);">
          <input type="checkbox" name="backlog_weekends" value="1" <?= ($s['backlog_weekends'] ?? '1') === '1' ? 'checked' : '' ?>>
          <span>Enviar también sábados y domingos</span>
        </label>
      </div>
    </div>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Remitente y destinatarios</h2></div>
      <div class="card-body">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
          <div class="field">
            <label class="field-label" for="backlog_from_name">Nombre del remitente</label>
            <input type="text" id="backlog_from_name" name="backlog_from_name" class="input" maxlength="80"
                   value="<?= $val('backlog_from_name', 'Mesa de Ayuda') ?>" placeholder="Mesa de Ayuda">
          </div>
          <div class="field">
            <label class="field-label" for="backlog_from_email">Correo del remitente</label>
            <input type="email" id="backlog_from_email" name="backlog_from_email" class="input"
                   value="<?= $val('backlog_from_email') ?>" placeholder="mesadeayuda@empresa.com">
            <p class="field-help">Vacío = usar el From del SMTP global.</p>
          </div>
        </div>

        <div class="field" style="margin-bottom: var(--space-3);">
          <label class="field-label" for="backlog_to">Destinatarios directos (Para)</label>
          <textarea id="backlog_to" name="backlog_to" class="input" rows="2"
                    placeholder="uno@empresa.com, otro@empresa.com"><?= esc($s['backlog_to'] ?? '') ?></textarea>
          <p class="field-help">Separados por coma o salto de línea.</p>
        </div>

        <div class="field">
          <label class="field-label" for="backlog_cc">En copia (CC)</label>
          <textarea id="backlog_cc" name="backlog_cc" class="input" rows="2"
                    placeholder="copia@empresa.com"><?= esc($s['backlog_cc'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header"><h2 class="card-title">Contenido y campos del plugin (IDC y Regional)</h2></div>
      <div class="card-body">
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); margin-bottom: var(--space-3);">
          <div class="field">
            <label class="field-label" for="backlog_org_label">Etiqueta de la organización</label>
            <input type="text" id="backlog_org_label" name="backlog_org_label" class="input" maxlength="80"
                   value="<?= $val('backlog_org_label', 'Mesa de Ayuda') ?>">
          </div>
          <div class="field">
            <label class="field-label" for="backlog_subject_prefix">Asunto del correo</label>
            <input type="text" id="backlog_subject_prefix" name="backlog_subject_prefix" class="input" maxlength="120"
                   value="<?= $val('backlog_subject_prefix', 'Reporte Diario de Backlog') ?>">
            <p class="field-help">Se le agrega " - DD/MM/AAAA" automáticamente.</p>
          </div>
        </div>

        <p class="text-muted text-sm" style="margin-bottom: var(--space-3);">
          "Sin IDC" cuenta los tickets cuyo campo del contenedor está vacío. Elige el contenedor y el campo que representa el IDC;
          si el campo está vacío en un ticket, se considera sin IDC. Deja el contenedor en "Ninguno" para ocultar este KPI.
        </p>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
          <div class="field">
            <label class="field-label" for="backlog_idc_container_id">Contenedor del campo IDC</label>
            <select id="backlog_idc_container_id" name="backlog_idc_container_id" class="input">
              <option value="0">Ninguno (ocultar KPI Sin IDC)</option>
              <?php foreach ($containers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $bIdcCont === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= esc($c['label']) ?> · id <?= (int) $c['id'] ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field-label" for="backlog_idc_field">Campo IDC</label>
            <select id="backlog_idc_field" name="backlog_idc_field" class="input" data-role="idc"><option value="">—</option></select>
          </div>
        </div>

        <p class="text-muted text-sm" style="margin:var(--space-4) 0 var(--space-3) 0;">
          "Por regional" agrupa el backlog por el valor de un campo del plugin (tickets, promedio de días abiertos y sin IDC por regional).
          Elige el contenedor y el campo que representa la regional. Deja el contenedor en "Ninguno" para ocultar esta sección.
        </p>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
          <div class="field">
            <label class="field-label" for="backlog_regional_container_id">Contenedor del campo Regional</label>
            <select id="backlog_regional_container_id" name="backlog_regional_container_id" class="input">
              <option value="0">Ninguno (ocultar sección Por regional)</option>
              <?php foreach ($containers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $bRegCont === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= esc($c['label']) ?> · id <?= (int) $c['id'] ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field-label" for="backlog_regional_field">Campo Regional</label>
            <select id="backlog_regional_field" name="backlog_regional_field" class="input" data-role="regional"><option value="">—</option></select>
          </div>
        </div>

        <p class="text-muted text-sm" style="margin:var(--space-4) 0 var(--space-3) 0;">
          Estado y municipio se agregan como columnas del Excel adjunto, junto a Regional. Salen de la misma pestaña de campos
          adicionales del ticket, así que <strong>no necesitas configurarlos</strong>: se detectan solos dentro del contenedor
          que elegiste para Regional. Usa estos selectores solo si el campo tiene otro nombre o vive en otra pestaña.
          No alimentan ningún KPI ni sección del correo.
        </p>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--space-3);">
          <div class="field">
            <label class="field-label" for="backlog_estado_container_id">Contenedor del campo Estado</label>
            <select id="backlog_estado_container_id" name="backlog_estado_container_id" class="input">
              <option value="0">Automático (detectar en el contenedor de Regional)</option>
              <?php foreach ($containers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $bEstCont === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= esc($c['label']) ?> · id <?= (int) $c['id'] ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field-label" for="backlog_estado_field">Campo Estado</label>
            <select id="backlog_estado_field" name="backlog_estado_field" class="input" data-role="estado"><option value="">—</option></select>
          </div>
          <div class="field">
            <label class="field-label" for="backlog_municipio_container_id">Contenedor del campo Municipio</label>
            <select id="backlog_municipio_container_id" name="backlog_municipio_container_id" class="input">
              <option value="0">Automático (detectar en el contenedor de Regional)</option>
              <?php foreach ($containers as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $bMunCont === (int) $c['id'] ? 'selected' : '' ?>>
                  <?= esc($c['label']) ?> · id <?= (int) $c['id'] ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label class="field-label" for="backlog_municipio_field">Campo Municipio</label>
            <select id="backlog_municipio_field" name="backlog_municipio_field" class="input" data-role="municipio"><option value="">—</option></select>
          </div>
        </div>
      </div>
    </div>
  </form>

  <!-- Area mapping (separate form: root ITIL category -> area) -->
  <form action="<?= route_to('servicedesk.backlog.areas.save') ?>" method="post" style="max-width: 760px;">
    <?= csrf_field() ?>
    <div class="card" style="margin-bottom: var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; gap: var(--space-3);">
        <h2 class="card-title" style="margin:0;">Áreas (Administración / Operaciones)</h2>
        <button type="submit" class="btn btn-primary">Guardar áreas</button>
      </div>
      <div class="card-body">
        <p class="text-muted text-sm" style="margin-top:0; margin-bottom: var(--space-3);">
          Asigna cada categoría raíz de GLPI a un área. Los tickets heredan el área de la raíz de su categoría; las subcategorías
          del "Resumen por área" salen del segundo nivel de la categoría.
        </p>
        <?php if (empty($bRoots)): ?>
          <p class="text-muted">No hay categorías raíz para mostrar (revisa la conexión a GLPI).</p>
        <?php else: ?>
          <table class="table" style="width:100%;">
            <thead><tr><th>Categoría raíz</th><th style="width:240px;">Área</th></tr></thead>
            <tbody>
              <?php foreach ($bRoots as $c): $id = (int) $c['id']; $cur = $bArea[$id]['area'] ?? ''; ?>
                <tr>
                  <td class="text-sm"><?= esc($c['name']) ?> <span class="text-muted">· id <?= $id ?></span></td>
                  <td>
                    <select name="area[<?= $id ?>]" class="input">
                      <option value="">Sin clasificar</option>
                      <?php foreach ($bAreas as $k => $label): ?>
                        <option value="<?= esc($k, 'attr') ?>" <?= $cur === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  </form>

  <!-- Test send + recent runs -->
  <div class="card" style="margin-bottom: var(--space-4); max-width: 760px;">
    <div class="card-header"><h2 class="card-title">Enviar prueba e historial</h2></div>
    <div class="card-body">
      <form action="<?= route_to('servicedesk.backlog.test') ?>" method="post"
            style="display:flex; gap: var(--space-2); align-items:flex-end; margin-bottom: var(--space-4); flex-wrap:wrap;">
        <?= csrf_field() ?>
        <div class="field" style="flex:1; min-width:240px; margin:0;">
          <label class="field-label" for="test_email">Enviar reporte de prueba a</label>
          <input type="email" id="test_email" name="test_email" class="input" placeholder="tu-correo@empresa.com (vacío = lista Para)">
        </div>
        <button type="submit" class="btn btn-secondary">Enviar prueba ahora</button>
      </form>

      <?php if (empty($bRuns)): ?>
        <p class="text-muted text-sm" style="margin:0;">Aún no se ha enviado ningún reporte.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead><tr><th>Fecha</th><th>Origen</th><th>Estado</th><th style="text-align:right;">Tickets</th></tr></thead>
          <tbody>
            <?php foreach ($bRuns as $run): ?>
              <tr>
                <td class="text-sm"><?= esc((string) ($run['created_at'] ?? '')) ?></td>
                <td class="text-sm"><?= esc((string) ($run['trigger'] ?? '')) ?></td>
                <td>
                  <?php if (($run['status'] ?? '') === 'ok'): ?>
                    <span class="badge badge-success">Enviado</span>
                  <?php else: ?>
                    <span class="badge badge-critical" title="<?= esc((string) ($run['error'] ?? ''), 'attr') ?>">Falló</span>
                  <?php endif; ?>
                </td>
                <td class="text-sm" style="text-align:right;"><?= number_format((int) ($run['total_open'] ?? 0)) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /sd-panel-backlog -->

<script>
(function () {
  'use strict';
  const tabs   = [...document.querySelectorAll('.sd-tab')];
  const panel  = id => document.getElementById(id);

  function activate(tab, updateHash) {
    tabs.forEach(t => {
      const active = t === tab;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
      t.tabIndex = active ? 0 : -1;
      const p = panel(t.dataset.panel);
      if (p) p.style.display = active ? '' : 'none';
    });
    if (updateHash && tab.dataset.hash) {
      history.replaceState(null, '', '#' + tab.dataset.hash);
    }
  }

  tabs.forEach((tab, i) => {
    tab.addEventListener('click', () => activate(tab, true));
    // Arrow-key navigation between tabs (WCAG tablist pattern)
    tab.addEventListener('keydown', e => {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      e.preventDefault();
      const next = e.key === 'ArrowRight'
        ? tabs[(i + 1) % tabs.length]
        : tabs[(i - 1 + tabs.length) % tabs.length];
      activate(next, true);
      next.focus();
    });
  });

  // Deep-link support: open the tab named in the URL hash (#ai, #widget, #import).
  const fromHash = (window.location.hash || '').replace('#', '');
  const target = tabs.find(t => t.dataset.hash === fromHash);
  if (target) activate(target, false);
}());
</script>

<script>
(function () {
  var form = document.getElementById('sd-widget');
  if (!form) return;
  var containerSel = document.getElementById('widget_container_id');
  var schemaUrl = form.dataset.schemaUrl;
  var saved = {
    equipo: form.dataset.equipo || '',
    modelo: form.dataset.modelo || '',
    serie: form.dataset.serie || '',
    categoria: form.dataset.categoria || ''
  };
  var selects = Array.prototype.slice.call(form.querySelectorAll('select[data-role]'));

  function fill(fields) {
    selects.forEach(function (sel) {
      var role = sel.dataset.role;
      var want = saved[role] || '';
      sel.innerHTML = '<option value="">—</option>';
      fields.forEach(function (f) {
        var opt = document.createElement('option');
        opt.value = f.field;
        opt.textContent = f.header + (f.type ? ' (' + f.type + ')' : '');
        if (f.field === want) opt.selected = true;
        sel.appendChild(opt);
      });
    });
  }

  function load(cid) {
    if (!cid || cid === '0') { fill([]); return; }
    fetch(schemaUrl + '?container=' + encodeURIComponent(cid), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var cols = (j && j.data) || [];
        var fields = cols.filter(function (c) { return c.kind === 'plugin'; })
          .map(function (c) { return { field: c.field, header: c.header, type: c.type }; });
        fill(fields);
      })
      .catch(function () { fill([]); });
  }

  containerSel.addEventListener('change', function () { load(this.value); });
  load(containerSel.value);
})();
</script>

<script>
// Backlog plugin-field pickers (IDC, Regional, Estado, Municipio): each fills
// its field select from the chosen container's live schema (reuses the schema
// AJAX endpoint).
(function () {
  var form = document.getElementById('sd-backlog');
  if (!form) return;
  var schemaUrl = form.dataset.schemaUrl;

  var pairs = [
    { container: 'backlog_idc_container_id',      field: 'backlog_idc_field',      saved: form.dataset.idc || '' },
    { container: 'backlog_regional_container_id', field: 'backlog_regional_field', saved: form.dataset.regional || '' },
    { container: 'backlog_estado_container_id',    field: 'backlog_estado_field',    saved: form.dataset.estado || '' },
    { container: 'backlog_municipio_container_id', field: 'backlog_municipio_field', saved: form.dataset.municipio || '' }
  ];

  pairs.forEach(function (p) {
    var containerSel = document.getElementById(p.container);
    var fieldSel = document.getElementById(p.field);
    if (!containerSel || !fieldSel) return;

    function fill(fields) {
      fieldSel.innerHTML = '<option value="">—</option>';
      fields.forEach(function (f) {
        var opt = document.createElement('option');
        opt.value = f.field;
        opt.textContent = f.header + (f.type ? ' (' + f.type + ')' : '');
        if (f.field === p.saved) opt.selected = true;
        fieldSel.appendChild(opt);
      });
    }

    function load(cid) {
      if (!cid || cid === '0') { fill([]); return; }
      fetch(schemaUrl + '?container=' + encodeURIComponent(cid), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var cols = (j && j.data) || [];
          var fields = cols.filter(function (c) { return c.kind === 'plugin'; })
            .map(function (c) { return { field: c.field, header: c.header, type: c.type }; });
          fill(fields);
        })
        .catch(function () { fill([]); });
    }

    containerSel.addEventListener('change', function () { load(this.value); });
    load(containerSel.value);
  });
})();
</script>

<?= $this->endSection() ?>
