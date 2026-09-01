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
$entityMode   = ($all['overview_entities_mode'] ?? 'all') === 'specific' ? 'specific' : 'all';
$openStatuses = $openStatuses ?? [1, 2, 3, 4];
$ticketTypes  = $ticketTypes ?? [1, 2];
$categoryRoots = $categoryRoots ?? [];
$statusLabels = $statusLabels ?? [];
$typeLabels   = $typeLabels ?? [];
$entities     = $entities ?? [];
$rootCategories = $rootCategories ?? [];
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Configuración · Supervisor de Mesa</h1>
    <p class="page-subtitle text-muted">Conexión GLPI, auditoría, resumen operativo y notificaciones.</p>
  </div>
</div>

<style>
.hs-tabs { display:flex; gap:var(--space-1); border-bottom:1px solid var(--color-neutral-200); margin-bottom:var(--space-4); flex-wrap:wrap; }
.hs-tab { appearance:none; background:none; border:none; border-bottom:2px solid transparent; margin-bottom:-1px;
  padding:var(--space-3) var(--space-4); cursor:pointer; font-size:var(--text-sm); font-weight:var(--weight-medium);
  color:var(--text-secondary); }
.hs-tab:hover { color:var(--text-primary); }
.hs-tab.is-active { color:var(--color-primary); font-weight:var(--weight-semibold); border-bottom-color:var(--color-primary); }
.hs-tab:focus-visible { outline:2px solid var(--color-primary); outline-offset:-2px; border-radius:var(--radius-sm); }
.hs-panel { display:none; }
.hs-panel.is-active { display:block; }
</style>

<div class="hs-tabs" role="tablist" aria-label="Secciones de configuración">
  <button type="button" class="hs-tab is-active" role="tab" data-panel="hs-panel-connection" data-hash="connection" aria-selected="true">Conexión</button>
  <button type="button" class="hs-tab" role="tab" data-panel="hs-panel-audit" data-hash="audit" aria-selected="false" tabindex="-1">Auditoría</button>
  <button type="button" class="hs-tab" role="tab" data-panel="hs-panel-overview" data-hash="overview" aria-selected="false" tabindex="-1">Resumen GLPI</button>
  <button type="button" class="hs-tab" role="tab" data-panel="hs-panel-notifications" data-hash="notifications" aria-selected="false" tabindex="-1">Notificaciones</button>
</div>

<!-- ===== Conexión ===== -->
<div class="hs-panel is-active" id="hs-panel-connection" role="tabpanel">
<form method="post" action="<?= route_to('helpdesk.settings.save') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="_settings_tab" value="connection">
  <input type="hidden" name="business_days_abandonment" value="<?= $g('business_days_abandonment', '5') ?>">
  <input type="hidden" name="opening_date_tolerance_sec" value="<?= $g('opening_date_tolerance_sec', '60') ?>">
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Conexión a GLPI</h2></div>
    <div class="card-body">
      <div class="field">
        <label class="field-check">
          <input type="checkbox" name="glpi_db_reuse_provisioning" value="1" <?= $reuse ? 'checked' : '' ?>>
          <span>Reutilizar la conexión de Provisioning (misma instancia GLPI)</span>
        </label>
        <p class="field-help">Recomendado. El resumen y la auditoría leen la misma BDD que Provisioning.</p>
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
    <div class="card-footer">
      <button type="submit" class="btn btn-primary">Guardar conexión</button>
    </div>
  </div>
</form>
</div>

<!-- ===== Auditoría ===== -->
<div class="hs-panel" id="hs-panel-audit" role="tabpanel" hidden>
<form method="post" action="<?= route_to('helpdesk.settings.save') ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="_settings_tab" value="audit">
  <input type="hidden" name="glpi_db_reuse_provisioning" value="<?= $reuse ? '1' : '0' ?>">
  <input type="hidden" name="glpi_db_host" value="<?= $g('glpi_db_host') ?>">
  <input type="hidden" name="glpi_db_port" value="<?= $g('glpi_db_port', '3306') ?>">
  <input type="hidden" name="glpi_db_name" value="<?= $g('glpi_db_name') ?>">
  <input type="hidden" name="glpi_db_user" value="<?= $g('glpi_db_user') ?>">

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

  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Mapeo de tabs a contenedores del plugin</h2></div>
    <div class="card-body">
      <p class="field-help" style="margin-bottom:var(--space-3);">
        Indica qué contenedor del plugin Additional Fields corresponde a cada tab del manual.
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
      <button type="submit" class="btn btn-primary">Guardar auditoría</button>
    </div>
  </div>
</form>
</div>

<!-- ===== Resumen GLPI ===== -->
<div class="hs-panel" id="hs-panel-overview" role="tabpanel" hidden>
<form method="post" action="<?= route_to('helpdesk.settings.overview') ?>">
  <?= csrf_field() ?>
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Filtros del resumen operativo</h2></div>
    <div class="card-body">
      <p class="field-help" style="margin-bottom:var(--space-3);">
        Controla qué cuenta el espejo de GLPI (entidad, estatus, categorías, tops). La conexión es la misma de arriba; aquí solo se acota el alcance.
        <a href="<?= route_to('helpdesk.overview') ?>">Ver resumen →</a>
      </p>

      <div class="field">
        <label class="field-label">Alcance de entidad</label>
        <label class="field-check" style="display:block; margin-bottom:var(--space-1);">
          <input type="radio" name="overview_entities_mode" value="all" <?= $entityMode === 'all' ? 'checked' : '' ?>>
          <span>Todas las entidades</span>
        </label>
        <label class="field-check" style="display:block;">
          <input type="radio" name="overview_entities_mode" value="specific" <?= $entityMode === 'specific' ? 'checked' : '' ?>>
          <span>Una entidad concreta</span>
        </label>
      </div>

      <div style="display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:flex-end;">
        <div class="field" style="flex:2; min-width:220px;">
          <label class="field-label" for="overview_entities_id">Entidad (entities_id)</label>
          <?php if ($entities !== []): ?>
            <select id="overview_entities_id" name="overview_entities_id" class="select">
              <?php foreach ($entities as $e): ?>
                <option value="<?= (int) $e['id'] ?>" <?= (int) ($all['overview_entities_id'] ?? 0) === (int) $e['id'] ? 'selected' : '' ?>>
                  <?= esc($e['label']) ?> (#<?= (int) $e['id'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          <?php else: ?>
            <input type="number" min="0" id="overview_entities_id" name="overview_entities_id" class="input" value="<?= $g('overview_entities_id', '0') ?>">
            <p class="field-help">No se pudo listar entidades; escribe el ID (0 = Root).</p>
          <?php endif; ?>
        </div>
        <div class="field" style="flex:1; min-width:180px;">
          <label class="field-check">
            <input type="checkbox" name="overview_entities_recursive" value="1" <?= ($all['overview_entities_recursive'] ?? '1') === '1' ? 'checked' : '' ?>>
            <span>Incluir sub-entidades</span>
          </label>
        </div>
      </div>

      <div class="field" style="margin-top:var(--space-3);">
        <label class="field-label">Estatus que cuentan como backlog abierto</label>
        <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
          <?php foreach ($statusLabels as $sid => $slabel): ?>
            <label class="field-check">
              <input type="checkbox" name="overview_open_statuses[]" value="<?= (int) $sid ?>"
                <?= in_array((int) $sid, $openStatuses, true) ? 'checked' : '' ?>>
              <span><?= esc($slabel) ?> (<?= (int) $sid ?>)</span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field">
        <label class="field-label">Tipos de ticket</label>
        <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
          <?php foreach ($typeLabels as $tid => $tlabel): ?>
            <label class="field-check">
              <input type="checkbox" name="overview_ticket_types[]" value="<?= (int) $tid ?>"
                <?= in_array((int) $tid, $ticketTypes, true) ? 'checked' : '' ?>>
              <span><?= esc($tlabel) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="field">
        <label class="field-label">Categorías raíz a incluir (vacío = todas)</label>
        <?php if ($rootCategories !== []): ?>
          <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:var(--space-2); max-height:220px; overflow:auto; border:1px solid var(--color-border); border-radius:var(--radius-2); padding:var(--space-2);">
            <?php foreach ($rootCategories as $c): ?>
              <label class="field-check">
                <input type="checkbox" name="overview_category_roots[]" value="<?= (int) $c['id'] ?>"
                  <?= in_array((int) $c['id'], $categoryRoots, true) ? 'checked' : '' ?>>
                <span><?= esc($c['label']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <p class="field-help">Si marcas raíces, se incluyen también sus subcategorías.</p>
        <?php else: ?>
          <input type="text" name="overview_category_roots" class="input" value="<?= $g('overview_category_roots') ?>" placeholder="IDs separados por coma, vacío = todas">
        <?php endif; ?>
      </div>

      <div style="display:flex; gap:var(--space-3); flex-wrap:wrap;">
        <div class="field" style="flex:1; min-width:140px;">
          <label class="field-label" for="overview_top_n_categories">Top categorías</label>
          <input type="number" min="1" max="50" id="overview_top_n_categories" name="overview_top_n_categories" class="input" value="<?= $g('overview_top_n_categories', '10') ?>">
        </div>
        <div class="field" style="flex:1; min-width:140px;">
          <label class="field-label" for="overview_top_n_requesters">Top solicitantes</label>
          <input type="number" min="1" max="100" id="overview_top_n_requesters" name="overview_top_n_requesters" class="input" value="<?= $g('overview_top_n_requesters', '15') ?>">
        </div>
        <div class="field" style="flex:1; min-width:140px;">
          <label class="field-label" for="overview_top_n_assignees">Top asignados</label>
          <input type="number" min="1" max="100" id="overview_top_n_assignees" name="overview_top_n_assignees" class="input" value="<?= $g('overview_top_n_assignees', '15') ?>">
        </div>
        <div class="field" style="flex:1; min-width:140px;">
          <label class="field-label" for="overview_critical_days">Días para crítico</label>
          <input type="number" min="1" id="overview_critical_days" name="overview_critical_days" class="input" value="<?= $g('overview_critical_days', '30') ?>">
        </div>
        <div class="field" style="flex:1; min-width:140px;">
          <label class="field-label" for="overview_cache_ttl">Caché (segundos)</label>
          <input type="number" min="0" max="3600" id="overview_cache_ttl" name="overview_cache_ttl" class="input" value="<?= $g('overview_cache_ttl', '120') ?>">
          <p class="field-help">0 = sin caché. Fuentes se listan todas; solicitantes y asignados usan el top.</p>
        </div>
      </div>
    </div>
    <div class="card-footer">
      <button type="submit" class="btn btn-primary">Guardar resumen</button>
    </div>
  </div>
</form>
</div>

<!-- ===== Notificaciones ===== -->
<div class="hs-panel" id="hs-panel-notifications" role="tabpanel" hidden>
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
</div>

<script>
(function () {
  const tabs = Array.from(document.querySelectorAll('.hs-tab'));
  const panels = Array.from(document.querySelectorAll('.hs-panel'));

  function activate(hash) {
    const target = hash || 'connection';
    tabs.forEach(function (tab) {
      const on = tab.dataset.hash === target;
      tab.classList.toggle('is-active', on);
      tab.setAttribute('aria-selected', on ? 'true' : 'false');
      tab.tabIndex = on ? 0 : -1;
    });
    panels.forEach(function (panel) {
      const on = panel.id === 'hs-panel-' + target;
      panel.classList.toggle('is-active', on);
      panel.hidden = !on;
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      const hash = tab.dataset.hash || 'connection';
      if (history.replaceState) {
        history.replaceState(null, '', '#' + hash);
      } else {
        location.hash = hash;
      }
      activate(hash);
    });
  });

  activate((location.hash || '#connection').replace(/^#/, ''));

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
})();
</script>

<?= $this->endSection() ?>
