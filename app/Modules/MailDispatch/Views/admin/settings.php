<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$s    = $settings;
$val  = fn(string $k, string $d = '') => esc($s[$k] ?? $d);
$bool = fn(string $k, string $d = '0') => ($s[$k] ?? $d) === '1';
?>

<style>
  .md-tabs { display:flex; gap:var(--space-2); border-bottom:1px solid var(--border-subtle); margin-bottom:var(--space-5); flex-wrap:wrap; }
  .md-tab { appearance:none; background:none; border:none; padding:var(--space-3) var(--space-3);
    font:inherit; font-size:var(--font-size-sm); font-weight:600; color:var(--text-subdued); cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; white-space:nowrap; }
  .md-tab:hover { color:var(--text-primary); }
  .md-tab.is-active { color:var(--action-primary); border-bottom-color:var(--action-primary); }
  .md-tab:focus-visible { outline:2px solid var(--action-primary); outline-offset:2px; border-radius:var(--radius-1); }
  .md-panel { display:none; }
  .md-panel.is-active { display:block; }
  .md-hint { color:var(--text-subdued); font-size:var(--font-size-sm); }
  .md-status-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:6px; vertical-align:middle; }
  /* Provider-scoped blocks: shown only for the active connection type. */
  .md-prov { display:none; }
  #md-conexion.prov-graph .md-prov-graph { display:block; }
  #md-conexion.prov-imap .md-prov-imap { display:block; }
  /* Even vertical rhythm for the per-rule editor cards (fields + grids). */
  #md-autogestion .card .card > .card-body > * { margin-bottom:var(--space-4); }
  #md-autogestion .card .card > .card-body > *:last-child { margin-bottom:0; }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Despacho de Correo · Configuración</h1>
    <p class="page-subtitle">Conexión (Microsoft Graph o IMAP), buzón, agentes y sincronización.</p>
  </div>
  <div class="page-actions">
    <a href="<?= base_url('dispatch') ?>" class="btn btn-secondary">Ir a la bandeja</a>
  </div>
</div>

<?php if (! $isConfigured): ?>
  <div class="banner banner-warning" role="alert" style="margin-bottom:var(--space-4);">
    <div class="banner-body">La configuración está incompleta. Captura las credenciales de la conexión (Graph o IMAP) y la dirección de la mesa de ayuda, luego prueba la conexión y habilita la sincronización.</div>
  </div>
<?php endif; ?>

<div class="md-tabs" role="tablist" aria-label="Secciones de configuración">
  <button type="button" class="md-tab is-active" role="tab" data-panel="md-conexion" data-hash="conexion">Conexión</button>
  <button type="button" class="md-tab" role="tab" data-panel="md-agentes" data-hash="agentes">Agentes</button>
  <button type="button" class="md-tab" role="tab" data-panel="md-disposiciones" data-hash="disposiciones">Disposiciones</button>
  <button type="button" class="md-tab" role="tab" data-panel="md-reglas" data-hash="reglas">Autoarchivo</button>
  <button type="button" class="md-tab" role="tab" data-panel="md-autogestion" data-hash="autogestion">Autogestión</button>
  <button type="button" class="md-tab" role="tab" data-panel="md-estado" data-hash="estado">Estado</button>
  <button type="button" class="md-tab" role="tab" data-panel="md-peligro" data-hash="peligro">Zona de peligro</button>
</div>

<!-- ============================= Conexión ============================= -->
<?php $provider = $provider ?? ($s['provider'] ?? 'graph'); ?>
<div id="md-conexion" class="md-panel is-active prov-<?= $provider === 'imap' ? 'imap' : 'graph' ?>" role="tabpanel">
  <form action="<?= route_to('dispatch.settings.save') ?>" method="post" style="max-width:780px;">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Tipo de conexión</h2>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
      <div class="card-body">
        <p class="md-hint" style="margin-bottom:var(--space-3);">Elige cómo Nexus lee el buzón de la mesa de ayuda.</p>
        <label class="field-check" style="margin-bottom:var(--space-2);">
          <input type="radio" name="provider" value="graph" data-provider-radio <?= $provider !== 'imap' ? 'checked' : '' ?>>
          <span>Microsoft 365 (Graph): permisos de aplicación</span>
        </label>
        <label class="field-check">
          <input type="radio" name="provider" value="imap" data-provider-radio <?= $provider === 'imap' ? 'checked' : '' ?>>
          <span>IMAP: buzón que recibe todo por regla de reenvío</span>
        </label>
      </div>
    </div>

    <!-- -------- Microsoft Graph (solo provider=graph) -------- -->
    <div class="card md-prov md-prov-graph" style="margin-bottom:var(--space-4);">
      <div class="card-header"><h2 class="card-title">Microsoft Graph (aplicación)</h2></div>
      <div class="card-body">
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="graph_tenant_id">Tenant ID</label>
          <input type="text" id="graph_tenant_id" name="graph_tenant_id" class="input" value="<?= $val('graph_tenant_id') ?>" autocomplete="off" spellcheck="false">
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="graph_client_id">Client ID</label>
          <input type="text" id="graph_client_id" name="graph_client_id" class="input" value="<?= $val('graph_client_id') ?>" autocomplete="off" spellcheck="false">
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="graph_client_secret">Client Secret</label>
          <input type="password" id="graph_client_secret" name="graph_client_secret" class="input"
                 value="<?= $hasSecret ? esc($secretMask) : '' ?>" autocomplete="new-password" spellcheck="false"
                 placeholder="<?= $hasSecret ? 'Guardado. Deja el valor para conservarlo' : 'Pega el secret de la app registration' ?>">
          <p class="field-help">Se guarda cifrado. Nunca se muestra en claro. Deja «<?= esc($secretMask) ?>» para conservar el actual.</p>
        </div>
      </div>
    </div>

    <!-- -------- IMAP (solo provider=imap) -------- -->
    <div class="card md-prov md-prov-imap" style="margin-bottom:var(--space-4);">
      <div class="card-header"><h2 class="card-title">Cuenta IMAP (lectura)</h2></div>
      <div class="card-body">
        <p class="md-hint" style="margin-bottom:var(--space-3);">Buzón que recibe todos los correos de la mesa de ayuda mediante una regla de reenvío (entrantes y copias de las respuestas de los agentes).</p>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="imap_host">Servidor IMAP</label>
          <input type="text" id="imap_host" name="imap_host" class="input" value="<?= $val('imap_host') ?>" placeholder="imap.dominio.com" autocomplete="off" spellcheck="false">
        </div>
        <div style="display:flex; gap:var(--space-3);">
          <div class="field" style="margin-bottom:var(--space-3); flex:1;">
            <label class="field-label" for="imap_port">Puerto</label>
            <input type="number" id="imap_port" name="imap_port" class="input" min="1" max="65535" value="<?= $val('imap_port', '993') ?>">
          </div>
          <div class="field" style="margin-bottom:var(--space-3); flex:1;">
            <label class="field-label" for="imap_encryption">Cifrado</label>
            <select id="imap_encryption" name="imap_encryption" class="input">
              <?php $ie = $s['imap_encryption'] ?? 'ssl'; ?>
              <option value="ssl" <?= $ie === 'ssl' ? 'selected' : '' ?>>SSL</option>
              <option value="tls" <?= $ie === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
              <option value="none" <?= $ie === 'none' ? 'selected' : '' ?>>Sin cifrado</option>
            </select>
          </div>
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="imap_username">Usuario</label>
          <input type="text" id="imap_username" name="imap_username" class="input" value="<?= $val('imap_username') ?>" placeholder="cuenta@dominio.com" autocomplete="off" spellcheck="false">
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="imap_password">Contraseña</label>
          <input type="password" id="imap_password" name="imap_password" class="input"
                 value="<?= $hasImapPassword ? esc($secretMask) : '' ?>" autocomplete="new-password" spellcheck="false"
                 placeholder="<?= $hasImapPassword ? 'Guardada. Deja el valor para conservarla' : 'Contraseña de la cuenta IMAP' ?>">
          <p class="field-help">Se guarda cifrada. Deja «<?= esc($secretMask) ?>» para conservar la actual.</p>
        </div>
        <div style="display:flex; gap:var(--space-3); align-items:flex-end;">
          <div class="field" style="margin-bottom:var(--space-3); flex:1;">
            <label class="field-label" for="imap_folder">Carpeta</label>
            <input type="text" id="imap_folder" name="imap_folder" class="input" value="<?= $val('imap_folder', 'INBOX') ?>" placeholder="INBOX" autocomplete="off" spellcheck="false">
          </div>
          <label class="field-check" style="flex:1; margin-bottom:var(--space-3);">
            <input type="checkbox" name="imap_validate_cert" value="1" <?= $bool('imap_validate_cert', '1') ? 'checked' : '' ?>>
            <span>Validar certificado TLS</span>
          </label>
        </div>

        <div class="field" style="border-top:1px solid var(--border-default); padding-top:var(--space-3);">
          <label class="field-check">
            <input type="checkbox" name="treat_as_forwards" value="1" <?= $bool('treat_as_forwards') ? 'checked' : '' ?>>
            <span><strong>Modo reenvío:</strong> usar el remitente original del cuerpo</span>
          </label>
          <p class="field-help">Actívalo si este buzón recibe los correos por una regla de <strong>reenvío</strong> (el remitente SMTP siempre es el reenviador). Nexus leerá el bloque «De:/From:» dentro del cuerpo y registrará a esa persona como <strong>solicitante</strong>, además de limpiar el encabezado del reenvío en el detalle.</p>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header"><h2 class="card-title">Buzón y sincronización</h2></div>
      <div class="card-body">
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="mailbox_address">Dirección de la mesa de ayuda</label>
          <input type="email" id="mailbox_address" name="mailbox_address" class="input" value="<?= $val('mailbox_address') ?>" placeholder="mesadeayuda@dominio.com" autocomplete="off">
          <p class="field-help">Dirección a la que escriben los clientes y desde la que responden los agentes. En modo Graph es el buzón que se sincroniza; en modo IMAP se usa para detectar la dirección (entrada/salida) de cada correo.</p>
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="sync_page_size">Tamaño de página</label>
          <input type="number" id="sync_page_size" name="sync_page_size" class="input" min="1" max="999" value="<?= $val('sync_page_size', '50') ?>">
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <?php
            $syncSinceRaw = trim((string) ($s['sync_since'] ?? ''));
            $syncSinceVal = $syncSinceRaw !== '' && ($ts = strtotime($syncSinceRaw)) !== false ? date('Y-m-d\TH:i', $ts) : '';
          ?>
          <label class="field-label" for="sync_since">Importar correos desde</label>
          <input type="datetime-local" id="sync_since" name="sync_since" class="input" value="<?= esc($syncSinceVal) ?>">
          <p class="field-help">Solo se importarán los correos recibidos <strong>a partir de</strong> esta fecha y hora. Déjalo vacío para importar todo el buzón. Útil cuando el buzón es muy grande y no quieres traer el histórico completo.<br>
          <strong>Nota:</strong> el corte aplica en la primera sincronización (o en una resincronización completa). Si cambias la fecha con la bandeja ya poblada, corre <code>php spark maildispatch:sync-mailbox --full</code> o usa la <em>Zona de peligro</em> para reiniciar y volver a importar desde el corte.</p>
        </div>
        <label class="field-check" style="margin-bottom:var(--space-3);">
          <input type="checkbox" name="sync_enabled" value="1" <?= $bool('sync_enabled') ? 'checked' : '' ?>>
          <span>Sincronización habilitada</span>
        </label>
      </div>
    </div>

    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header"><h2 class="card-title">Umbrales de SLA (minutos)</h2></div>
      <div class="card-body">
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="sla_unassigned_minutes">Máximo sin asignar</label>
          <input type="number" id="sla_unassigned_minutes" name="sla_unassigned_minutes" class="input" min="0" value="<?= $val('sla_unassigned_minutes', '30') ?>">
          <p class="field-help">Las conversaciones sin asignar que superan este umbral se resaltan en la bandeja.</p>
        </div>
        <div class="field">
          <label class="field-label" for="sla_first_response_minutes">Máximo sin primera respuesta</label>
          <input type="number" id="sla_first_response_minutes" name="sla_first_response_minutes" class="input" min="0" value="<?= $val('sla_first_response_minutes', '120') ?>">
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:var(--space-4);">
      <div class="card-header"><h2 class="card-title">Respuesta desde Nexus</h2></div>
      <div class="card-body">
        <label class="field-check">
          <input type="checkbox" name="send_from_nexus_enabled" value="1" <?= $bool('send_from_nexus_enabled') ? 'checked' : '' ?>>
          <span>Permitir responder al hilo desde Nexus</span>
        </label>
        <p class="field-help md-prov md-prov-graph">Modo Graph: requiere el permiso <code>Mail.Send</code> en la app. Si está apagado, Nexus opera en solo lectura y los agentes responden desde Outlook.</p>
        <p class="field-help md-prov md-prov-imap">Modo IMAP: la respuesta se envía por SMTP (configúralo abajo). Si está apagado, Nexus opera en solo lectura y los agentes responden desde Outlook.</p>
      </div>
    </div>

    <!-- -------- SMTP (solo provider=imap) -------- -->
    <div class="card md-prov md-prov-imap" style="margin-bottom:var(--space-4);">
      <div class="card-header"><h2 class="card-title">Envío SMTP (respuesta en modo IMAP)</h2></div>
      <div class="card-body">
        <p class="md-hint" style="margin-bottom:var(--space-3);">Credenciales para enviar respuestas al hilo. Solo se usan si la respuesta desde Nexus está habilitada.</p>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="smtp_host">Servidor SMTP</label>
          <input type="text" id="smtp_host" name="smtp_host" class="input" value="<?= $val('smtp_host') ?>" placeholder="smtp.dominio.com" autocomplete="off" spellcheck="false">
        </div>
        <div style="display:flex; gap:var(--space-3);">
          <div class="field" style="margin-bottom:var(--space-3); flex:1;">
            <label class="field-label" for="smtp_port">Puerto</label>
            <input type="number" id="smtp_port" name="smtp_port" class="input" min="1" max="65535" value="<?= $val('smtp_port', '587') ?>">
          </div>
          <div class="field" style="margin-bottom:var(--space-3); flex:1;">
            <label class="field-label" for="smtp_encryption">Cifrado</label>
            <select id="smtp_encryption" name="smtp_encryption" class="input">
              <?php $se = $s['smtp_encryption'] ?? 'tls'; ?>
              <option value="tls" <?= $se === 'tls' ? 'selected' : '' ?>>TLS (STARTTLS)</option>
              <option value="ssl" <?= $se === 'ssl' ? 'selected' : '' ?>>SSL</option>
              <option value="none" <?= $se === 'none' ? 'selected' : '' ?>>Sin cifrado</option>
            </select>
          </div>
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="smtp_username">Usuario</label>
          <input type="text" id="smtp_username" name="smtp_username" class="input" value="<?= $val('smtp_username') ?>" placeholder="cuenta@dominio.com" autocomplete="off" spellcheck="false">
        </div>
        <div class="field" style="margin-bottom:var(--space-3);">
          <label class="field-label" for="smtp_password">Contraseña</label>
          <input type="password" id="smtp_password" name="smtp_password" class="input"
                 value="<?= $hasSmtpPassword ? esc($secretMask) : '' ?>" autocomplete="new-password" spellcheck="false"
                 placeholder="<?= $hasSmtpPassword ? 'Guardada. Deja el valor para conservarla' : 'Contraseña SMTP' ?>">
          <p class="field-help">Se guarda cifrada. Deja «<?= esc($secretMask) ?>» para conservar la actual.</p>
        </div>
        <div style="display:flex; gap:var(--space-3);">
          <div class="field" style="margin-bottom:var(--space-3); flex:1;">
            <label class="field-label" for="smtp_from_email">Remitente (From)</label>
            <input type="email" id="smtp_from_email" name="smtp_from_email" class="input" value="<?= $val('smtp_from_email') ?>" placeholder="mesadeayuda@dominio.com" autocomplete="off">
            <p class="field-help">Vacío = usa la dirección de la mesa de ayuda.</p>
          </div>
          <div class="field" style="margin-bottom:var(--space-3); flex:1;">
            <label class="field-label" for="smtp_from_name">Nombre del remitente</label>
            <input type="text" id="smtp_from_name" name="smtp_from_name" class="input" value="<?= $val('smtp_from_name') ?>" placeholder="Mesa de ayuda" autocomplete="off">
          </div>
        </div>
      </div>
    </div>
  </form>

  <div class="card" style="max-width:780px;">
    <div class="card-header"><h2 class="card-title">Probar conexión</h2></div>
    <div class="card-body">
      <p class="md-hint" style="margin-bottom:var(--space-3);">Valida en vivo: obtención de token, acceso al buzón y lectura de la bandeja de entrada. Usa los valores del formulario (guarda primero para conservarlos).</p>
      <button type="button" id="md-test-btn" class="btn btn-secondary">Probar conexión</button>
      <div id="md-test-result" style="margin-top:var(--space-3);"></div>
    </div>
  </div>
</div>

<!-- ============================= Agentes ============================= -->
<div id="md-agentes" class="md-panel" role="tabpanel">
  <form action="<?= route_to('dispatch.agents.save') ?>" method="post" style="max-width:780px;">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Agentes de despacho</h2>
        <button type="submit" class="btn btn-primary">Guardar agentes</button>
      </div>
      <div class="card-body" style="padding:0;">
        <p class="md-hint" style="padding:var(--space-4);">Marca quién participa como agente del despacho y quién puede asignar/reasignar a otros (dispatcher). Esto es adicional al acceso por rol al módulo.</p>
        <table class="table" style="width:100%;">
          <thead><tr><th>Usuario</th><th>Correo</th><th style="text-align:center;">Agente</th><th style="text-align:center;">Dispatcher</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): $uid = (int) $u['id']; $a = $agentMap[$uid] ?? null; ?>
              <tr>
                <td><?= esc($u['name']) ?></td>
                <td class="text-muted text-sm"><?= esc($u['email']) ?></td>
                <td style="text-align:center;">
                  <input type="checkbox" name="agent[<?= $uid ?>][active]" value="1" <?= ($a && (int) $a['is_active'] === 1) ? 'checked' : '' ?>>
                </td>
                <td style="text-align:center;">
                  <input type="checkbox" name="agent[<?= $uid ?>][dispatcher]" value="1" <?= ($a && (int) $a['is_dispatcher'] === 1) ? 'checked' : '' ?>>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </form>
</div>

<!-- ========================== Disposiciones ========================== -->
<div id="md-disposiciones" class="md-panel" role="tabpanel">
  <form action="<?= route_to('dispatch.dispositions.save') ?>" method="post" style="max-width:780px;">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Disposiciones de cierre</h2>
        <button type="submit" class="btn btn-primary">Guardar catálogo</button>
      </div>
      <div class="card-body" style="padding:0;">
        <p class="md-hint" style="padding:var(--space-4);">Al cerrar una conversación el agente elige una disposición. «Requiere folio» exige capturar el número de ticket GLPI.</p>
        <table class="table" style="width:100%;" id="md-disp-table">
          <thead><tr><th>Nombre</th><th style="text-align:center;">Requiere folio</th><th style="text-align:center;">Activa</th></tr></thead>
          <tbody>
            <?php foreach ($dispositions as $i => $d): ?>
              <tr>
                <td>
                  <input type="hidden" name="disposition[<?= $i ?>][id]" value="<?= (int) $d['id'] ?>">
                  <input type="text" name="disposition[<?= $i ?>][name]" class="input" value="<?= esc($d['name']) ?>">
                </td>
                <td style="text-align:center;"><input type="checkbox" name="disposition[<?= $i ?>][requires_folio]" value="1" <?= (int) $d['requires_folio'] === 1 ? 'checked' : '' ?>></td>
                <td style="text-align:center;"><input type="checkbox" name="disposition[<?= $i ?>][is_active]" value="1" <?= (int) $d['is_active'] === 1 ? 'checked' : '' ?>></td>
              </tr>
            <?php endforeach; ?>
            <!-- one empty row to add a new disposition -->
            <?php $n = count($dispositions); ?>
            <tr>
              <td><input type="text" name="disposition[<?= $n ?>][name]" class="input" placeholder="Nueva disposición…"></td>
              <td style="text-align:center;"><input type="checkbox" name="disposition[<?= $n ?>][requires_folio]" value="1"></td>
              <td style="text-align:center;"><input type="checkbox" name="disposition[<?= $n ?>][is_active]" value="1" checked></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </form>
</div>

<!-- ========================= Reglas de autoarchivo ========================= -->
<div id="md-reglas" class="md-panel" role="tabpanel">
  <form action="<?= route_to('dispatch.rules.save') ?>" method="post" style="max-width:900px;">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Reglas de autoarchivo</h2>
        <button type="submit" class="btn btn-primary">Guardar reglas</button>
      </div>
      <div class="card-body" style="padding:0;">
        <p class="md-hint" style="padding:var(--space-4);">
          Si un correo <strong>entrante nuevo</strong> coincide con una regla activa, entra directo al estado
          <strong>Autoarchivo</strong> (fuera de la bandeja principal). Ahí cualquier agente puede
          <strong>Verificar</strong> (queda registrado quién) o <strong>Mover a la bandeja</strong> para el flujo normal.
          El <em>remitente</em> se compara contra el correo real (usa <code>@dominio.com</code> para todo un dominio);
          el <em>asunto</em> por coincidencia parcial. Deja un patrón vacío para no filtrar por él (pero al menos uno es obligatorio).
        </p>
        <table class="table" style="width:100%;" id="md-rules-table">
          <thead><tr>
            <th>Nombre</th>
            <th>Remitente contiene / @dominio</th>
            <th>Asunto contiene</th>
            <th style="text-align:center;">Activa</th>
          </tr></thead>
          <tbody>
            <?php foreach (($rules ?? []) as $i => $r): ?>
              <tr>
                <td>
                  <input type="hidden" name="rule[<?= $i ?>][id]" value="<?= (int) $r['id'] ?>">
                  <input type="text" name="rule[<?= $i ?>][name]" class="input" value="<?= esc($r['name'], 'attr') ?>">
                </td>
                <td><input type="text" name="rule[<?= $i ?>][sender_pattern]" class="input" value="<?= esc($r['sender_pattern'], 'attr') ?>" placeholder="notificaciones@ o @dominio.com" autocomplete="off" spellcheck="false"></td>
                <td><input type="text" name="rule[<?= $i ?>][subject_pattern]" class="input" value="<?= esc($r['subject_pattern'], 'attr') ?>" placeholder="Reporte Diario de Backlog" autocomplete="off" spellcheck="false"></td>
                <td style="text-align:center;"><input type="checkbox" name="rule[<?= $i ?>][is_active]" value="1" <?= (int) $r['is_active'] === 1 ? 'checked' : '' ?>></td>
              </tr>
            <?php endforeach; ?>
            <!-- fila vacía para agregar una regla -->
            <?php $n = count($rules ?? []); ?>
            <tr>
              <td><input type="text" name="rule[<?= $n ?>][name]" class="input" placeholder="Nueva regla…"></td>
              <td><input type="text" name="rule[<?= $n ?>][sender_pattern]" class="input" placeholder="notificaciones@ o @dominio.com" autocomplete="off" spellcheck="false"></td>
              <td><input type="text" name="rule[<?= $n ?>][subject_pattern]" class="input" placeholder="texto del asunto" autocomplete="off" spellcheck="false"></td>
              <td style="text-align:center;"><input type="checkbox" name="rule[<?= $n ?>][is_active]" value="1" checked></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </form>
</div>

<!-- ========================= Autogestión ========================= -->
<div id="md-autogestion" class="md-panel" role="tabpanel">
  <form action="<?= route_to('dispatch.autogen.save') ?>" method="post" style="max-width:900px; margin-bottom:var(--space-5);">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Autogestión</h2>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
      <div class="card-body">
        <p class="md-hint" style="margin-bottom:var(--space-4);">
          Crea tickets GLPI automáticamente desde correos que coincidan con una regla (asunto + lista blanca).
          Estos <strong>defaults</strong> se usan cuando una regla no define el suyo.
        </p>
        <label class="field-check" style="margin-bottom:var(--space-3); display:flex; gap:8px; align-items:center;">
          <input type="checkbox" name="autogestion_enabled" value="1" <?= ($settings['autogestion_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span><strong>Activar autogestión</strong> (crear tickets automáticamente)</span>
        </label>
        <label class="field-check" style="margin-bottom:var(--space-3); display:flex; gap:8px; align-items:center;">
          <input type="checkbox" name="autogestion_ai_enabled" value="1" <?= ($settings['autogestion_ai_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
          <span><strong>Detección con IA</strong> (respaldo del parser cuando faltan datos; reusa la llave de ServiceDesk)</span>
        </label>
        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:var(--space-3); margin-bottom:var(--space-4);">
          <div class="field"><label class="field-label">Umbral de confianza IA (0–1)</label><input type="number" step="0.05" min="0" max="1" name="autogen_ai_confidence" class="input" value="<?= esc($settings['autogen_ai_confidence'] ?? '0.6', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Máx. tokens IA</label><input type="number" name="autogen_ai_max_tokens" class="input" value="<?= esc($settings['autogen_ai_max_tokens'] ?? '1024', 'attr') ?>"></div>
          <div class="field" style="grid-column:1 / -1;"><label class="field-label">Prompt de sistema IA (opcional)</label><textarea name="autogen_ai_system_prompt" class="input" rows="3" placeholder="(usa el prompt por defecto si lo dejas vacío)"><?= esc($settings['autogen_ai_system_prompt'] ?? '') ?></textarea></div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(2,1fr); gap:var(--space-3);">
          <div class="field">
            <label class="field-label">Tipo de ticket por defecto</label>
            <?php $dt = $settings['autogen_default_ticket_type'] ?? 'INCIDENCIA'; ?>
            <select name="autogen_default_ticket_type" class="input">
              <option value="INCIDENCIA" <?= $dt === 'INCIDENCIA' ? 'selected' : '' ?>>Incidencia</option>
              <option value="REQUERIMIENTO" <?= $dt === 'REQUERIMIENTO' ? 'selected' : '' ?>>Requerimiento</option>
            </select>
          </div>
          <div class="field"><label class="field-label">Categoría GLPI (id)</label><input type="number" name="autogen_default_category_id" class="input" value="<?= esc($settings['autogen_default_category_id'] ?? '', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Entidad GLPI (id)</label><input type="number" name="autogen_default_entities_id" class="input" value="<?= esc($settings['autogen_default_entities_id'] ?? '', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Usuario solicitante GLPI (id)</label><input type="number" name="autogen_default_requester_user_id" class="input" value="<?= esc($settings['autogen_default_requester_user_id'] ?? '', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Origen de solicitud GLPI (id)</label><input type="number" name="autogen_default_request_source_id" class="input" value="<?= esc($settings['autogen_default_request_source_id'] ?? '', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Contenedores plugin (ids, coma)</label><input type="text" name="autogen_default_container_ids" class="input" value="<?= esc($settings['autogen_default_container_ids'] ?? '', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Usuario de sistema (id Nexus, para la respuesta)</label><input type="number" name="autogen_system_user_id" class="input" value="<?= esc($settings['autogen_system_user_id'] ?? '', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Límite por remitente/hora (0 = sin límite)</label><input type="number" name="autogen_rate_limit_per_hour" class="input" value="<?= esc($settings['autogen_rate_limit_per_hour'] ?? '0', 'attr') ?>"></div>
          <div class="field"><label class="field-label">Reintentos máx.</label><input type="number" name="autogen_max_attempts" class="input" value="<?= esc($settings['autogen_max_attempts'] ?? '3', 'attr') ?>"></div>
        </div>
      </div>
    </div>
  </form>

  <form action="<?= route_to('dispatch.autogenrules.save') ?>" method="post" style="max-width:900px;">
    <?= csrf_field() ?>
    <div class="card">
      <div class="card-header" style="display:flex; align-items:center; justify-content:space-between;">
        <h2 class="card-title">Reglas de autogestión</h2>
        <button type="submit" class="btn btn-primary">Guardar reglas</button>
      </div>
      <div class="card-body">
        <p class="md-hint" style="margin-bottom:var(--space-4);">
          Cada regla dispara si el <strong>asunto</strong> coincide y el remitente/destinatario está en la
          <strong>lista blanca</strong> (obligatoria). El cuerpo se lee como <code>Campo: valor</code> según el
          <strong>mapeo de campos</strong>. Lista blanca: una por línea, <code>sender:correo@dominio</code> o
          <code>recipient:@dominio.com</code>. Mapeo: una por línea,
          <code>Etiqueta | destino | requerido(1/0)</code>, donde <em>destino</em> es
          <code>title</code>, <code>description</code>, <code>ignore</code> o
          <code>plugin:&lt;contenedor&gt;:&lt;campo&gt;</code> (escribe el dato en un campo de tab/plugin GLPI).
        </p>
        <?php if (! empty($pluginRef)): ?>
          <details style="margin:0 0 var(--space-4);">
            <summary class="field-label" style="cursor:pointer;">Campos de plugin/tab disponibles (copia el <code>plugin:…</code> al mapeo)</summary>
            <div style="padding:var(--space-3) 0;">
              <?php foreach ($pluginRef as $cont): ?>
                <p class="text-sm" style="margin:var(--space-2) 0 4px;"><strong><?= esc($cont['label']) ?></strong> · id <?= (int) $cont['id'] ?></p>
                <ul style="margin:0 0 var(--space-2) var(--space-4);">
                  <?php foreach ($cont['fields'] as $fld): ?>
                    <li class="text-sm"><code><?= esc($fld['target']) ?></code> · <?= esc($fld['label']) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endforeach; ?>
            </div>
          </details>
        <?php endif; ?>
        <?php
          $agrules = $autogenRules ?? [];
          $blank = ['id' => 0, 'name' => '', 'is_active' => 1, 'subject_pattern' => '', 'subject_match_mode' => 'contains', 'glpi_ticket_type' => '', 'glpi_category_id' => '', 'glpi_entities_id' => '', 'glpi_requester_user_id' => '', 'request_source_id' => '', 'container_ids' => '', 'reply_body' => '', '_whitelist_text' => '', '_field_map_text' => ''];
        ?>
        <?php foreach (array_merge($agrules, [$blank]) as $i => $r): ?>
          <div class="card" style="margin-bottom:var(--space-4); border:1px solid var(--border-default);">
            <div class="card-body">
              <input type="hidden" name="agrule[<?= $i ?>][id]" value="<?= (int) ($r['id'] ?? 0) ?>">
              <div style="display:grid; grid-template-columns:2fr 1fr auto; gap:var(--space-3); align-items:end;">
                <div class="field"><label class="field-label">Nombre</label><input type="text" name="agrule[<?= $i ?>][name]" class="input" value="<?= esc($r['name'] ?? '', 'attr') ?>" placeholder="Nueva regla…"></div>
                <div class="field"><label class="field-label">Modo asunto</label>
                  <select name="agrule[<?= $i ?>][subject_match_mode]" class="input">
                    <option value="contains" <?= ($r['subject_match_mode'] ?? 'contains') === 'contains' ? 'selected' : '' ?>>Contiene</option>
                    <option value="exact" <?= ($r['subject_match_mode'] ?? '') === 'exact' ? 'selected' : '' ?>>Exacto</option>
                  </select>
                </div>
                <label class="field-check" style="display:flex; gap:6px; align-items:center; padding-bottom:10px;"><input type="checkbox" name="agrule[<?= $i ?>][is_active]" value="1" <?= (int) ($r['is_active'] ?? 1) === 1 ? 'checked' : '' ?>><span>Activa</span></label>
              </div>
              <div class="field"><label class="field-label">Asunto (contiene / exacto)</label><input type="text" name="agrule[<?= $i ?>][subject_pattern]" class="input" value="<?= esc($r['subject_pattern'] ?? '', 'attr') ?>" placeholder="SOLICITUD DE TICKET"></div>
              <div class="field"><label class="field-label">Lista blanca</label><textarea name="agrule[<?= $i ?>][whitelist]" class="input" rows="2" placeholder="sender:@acme.com"><?= esc($r['_whitelist_text'] ?? '') ?></textarea></div>
              <div class="field"><label class="field-label">Mapeo de campos del cuerpo</label><textarea name="agrule[<?= $i ?>][field_map]" class="input" rows="3" placeholder="Título | title | 1&#10;Descripción | description | 1"><?= esc($r['_field_map_text'] ?? '') ?></textarea></div>
              <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:var(--space-3);">
                <div class="field"><label class="field-label">Tipo ticket</label>
                  <select name="agrule[<?= $i ?>][glpi_ticket_type]" class="input">
                    <option value="">(default)</option>
                    <option value="INCIDENCIA" <?= ($r['glpi_ticket_type'] ?? '') === 'INCIDENCIA' ? 'selected' : '' ?>>Incidencia</option>
                    <option value="REQUERIMIENTO" <?= ($r['glpi_ticket_type'] ?? '') === 'REQUERIMIENTO' ? 'selected' : '' ?>>Requerimiento</option>
                  </select>
                </div>
                <div class="field"><label class="field-label">Categoría (id)</label><input type="number" name="agrule[<?= $i ?>][glpi_category_id]" class="input" value="<?= esc((string) ($r['glpi_category_id'] ?? ''), 'attr') ?>"></div>
                <div class="field"><label class="field-label">Entidad (id)</label><input type="number" name="agrule[<?= $i ?>][glpi_entities_id]" class="input" value="<?= esc((string) ($r['glpi_entities_id'] ?? ''), 'attr') ?>"></div>
                <div class="field"><label class="field-label">Solicitante (id)</label><input type="number" name="agrule[<?= $i ?>][glpi_requester_user_id]" class="input" value="<?= esc((string) ($r['glpi_requester_user_id'] ?? ''), 'attr') ?>"></div>
                <div class="field"><label class="field-label">Origen (id)</label><input type="number" name="agrule[<?= $i ?>][request_source_id]" class="input" value="<?= esc((string) ($r['request_source_id'] ?? ''), 'attr') ?>"></div>
                <div class="field"><label class="field-label">Contenedores (ids, coma)</label><input type="text" name="agrule[<?= $i ?>][container_ids]" class="input" value="<?= esc($r['container_ids'] ?? '', 'attr') ?>"></div>
              </div>
              <div class="field"><label class="field-label">Respuesta automática (variables: {{ticket_id}}, {{titulo}}, {{asunto}})</label><textarea name="agrule[<?= $i ?>][reply_body]" class="input" rows="3"><?= esc($r['reply_body'] ?? '') ?></textarea></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </form>
</div>

<!-- ============================= Estado ============================= -->
<div id="md-estado" class="md-panel" role="tabpanel">
  <div class="card" style="margin-bottom:var(--space-4);">
    <div class="card-header"><h2 class="card-title">Estado por carpeta</h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($syncState)): ?>
        <p class="text-muted" style="padding:var(--space-4);">Aún no se ha ejecutado ninguna sincronización.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead><tr><th>Carpeta</th><th>Resultado</th><th>Última corrida</th><th>Procesados</th><th>Errores</th><th>Mensaje</th></tr></thead>
          <tbody>
            <?php foreach ($syncState as $st): ?>
              <tr>
                <td><?= esc($st['folder']) ?></td>
                <td>
                  <?php $ok = ($st['last_result'] ?? '') === 'ok'; $never = ($st['last_result'] ?? '') === 'never'; ?>
                  <span class="badge <?= $never ? 'badge-neutral' : ($ok ? 'badge-success' : 'badge-critical') ?>"><?= esc($st['last_result']) ?></span>
                </td>
                <td class="text-muted text-sm"><?= esc($st['last_run_at'] ?? '-') ?></td>
                <td><?= (int) $st['processed_count'] ?></td>
                <td><?= (int) $st['error_count'] ?></td>
                <td class="text-sm"><?= esc($st['last_message'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h2 class="card-title">Corridas recientes</h2></div>
    <div class="card-body" style="padding:0;">
      <?php if (empty($syncRuns)): ?>
        <p class="text-muted" style="padding:var(--space-4);">Sin corridas registradas.</p>
      <?php else: ?>
        <table class="table" style="width:100%;">
          <thead><tr><th>Fecha</th><th>Origen</th><th>Resultado</th><th>Proc.</th><th>Nuevas</th><th>Actual.</th><th>Errores</th><th>Detalle</th></tr></thead>
          <tbody>
            <?php foreach ($syncRuns as $r): ?>
              <tr>
                <td class="text-muted text-sm"><?= esc($r['created_at']) ?></td>
                <td class="text-sm"><?= esc($r['trigger']) ?></td>
                <td><span class="badge <?= $r['status'] === 'ok' ? 'badge-success' : 'badge-critical' ?>"><?= esc($r['status']) ?></span></td>
                <td><?= (int) $r['processed'] ?></td>
                <td><?= (int) $r['created'] ?></td>
                <td><?= (int) $r['updated'] ?></td>
                <td><?= (int) $r['errors'] ?></td>
                <td class="text-sm"><?= esc($r['message']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <p class="md-hint" style="margin-top:var(--space-4);">La sincronización corre por cron: <code>php spark maildispatch:sync-mailbox</code> (cada 1–2 minutos sugerido).</p>
</div>

<!-- ============================= Zona de peligro ============================= -->
<div id="md-peligro" class="md-panel" role="tabpanel">
  <?php $mailboxAddr = trim((string) ($s['mailbox_address'] ?? '')); ?>

  <div class="banner banner-critical" role="alert" style="margin-bottom:var(--space-4); max-width:820px;">
    <div class="banner-body">
      <strong>Estas acciones eliminan datos de forma permanente.</strong> Solo borran la <strong>bandeja que ves en Nexus</strong> (conversaciones, mensajes, adjuntos, historial). <strong>No</strong> se toca la configuración (conexión, agentes, disposiciones, reglas, plantillas, firmas) ni el <strong>correo real</strong> en el buzón: la sincronización solo lo lee.
    </div>
  </div>

  <?php if ($mailboxAddr === ''): ?>
    <div class="card" style="max-width:820px; border:1px solid var(--border-critical, #d72c0d);">
      <div class="card-body">
        <p class="text-muted">Configura primero la dirección del buzón para habilitar estas acciones.</p>
      </div>
    </div>
  <?php else: ?>

    <!-- Borrar todo -->
    <div class="card" style="max-width:820px; margin-bottom:var(--space-4); border:1px solid var(--border-critical, #d72c0d);">
      <div class="card-header"><h2 class="card-title" style="color:var(--text-critical, #d72c0d);">Limpiar toda la bandeja</h2></div>
      <div class="card-body">
        <p style="margin-bottom:var(--space-3);">Elimina <strong>todas</strong> las conversaciones y sus datos, y <strong>reinicia el cursor de sincronización</strong>. La próxima corrida volverá a importar desde la fecha de corte configurada («Importar correos desde»).</p>
        <form method="post" action="<?= route_to('dispatch.purge') ?>" class="md-purge-form" data-mailbox="<?= esc($mailboxAddr, 'attr') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="all">
          <div class="field" style="margin-bottom:var(--space-3);">
            <label class="field-label" for="confirm_all">Escribe <code><?= esc($mailboxAddr) ?></code> para confirmar</label>
            <input type="text" id="confirm_all" name="confirm" class="input md-purge-confirm" autocomplete="off" spellcheck="false" placeholder="<?= esc($mailboxAddr, 'attr') ?>">
          </div>
          <button type="submit" class="btn btn-critical md-purge-btn" disabled>Limpiar toda la bandeja</button>
        </form>
      </div>
    </div>

    <!-- Borrar anteriores a una fecha -->
    <div class="card" style="max-width:820px; border:1px solid var(--border-critical, #d72c0d);">
      <div class="card-header"><h2 class="card-title">Podar conversaciones antiguas</h2></div>
      <div class="card-body">
        <p style="margin-bottom:var(--space-3);">Elimina solo las conversaciones <strong>sin actividad</strong> desde la fecha indicada. Conserva los hilos recientes y <strong>no</strong> reinicia el cursor de sincronización.</p>
        <form method="post" action="<?= route_to('dispatch.purge') ?>" class="md-purge-form" data-mailbox="<?= esc($mailboxAddr, 'attr') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="mode" value="before">
          <div class="field" style="margin-bottom:var(--space-3);">
            <label class="field-label" for="before_date">Eliminar conversaciones anteriores a</label>
            <input type="datetime-local" id="before_date" name="before_date" class="input" required>
          </div>
          <div class="field" style="margin-bottom:var(--space-3);">
            <label class="field-label" for="confirm_before">Escribe <code><?= esc($mailboxAddr) ?></code> para confirmar</label>
            <input type="text" id="confirm_before" name="confirm" class="input md-purge-confirm" autocomplete="off" spellcheck="false" placeholder="<?= esc($mailboxAddr, 'attr') ?>">
          </div>
          <button type="submit" class="btn btn-critical md-purge-btn" disabled>Podar conversaciones antiguas</button>
        </form>
      </div>
    </div>

  <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
  // Tabs
  var tabs   = Array.prototype.slice.call(document.querySelectorAll('.md-tab'));
  var panels = Array.prototype.slice.call(document.querySelectorAll('.md-panel'));
  function activate(hash, push) {
    var tab = tabs.find(function (t) { return t.dataset.hash === hash; }) || tabs[0];
    tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
    panels.forEach(function (p) { p.classList.toggle('is-active', p.id === tab.dataset.panel); });
    if (push && history.replaceState) { history.replaceState(null, '', '#' + tab.dataset.hash); }
  }
  tabs.forEach(function (t) { t.addEventListener('click', function () { activate(t.dataset.hash, true); }); });
  activate((location.hash || '#conexion').slice(1), false);

  // Provider toggle: show only the active connection type's blocks.
  var conexion = document.getElementById('md-conexion');
  var radios   = Array.prototype.slice.call(document.querySelectorAll('[data-provider-radio]'));
  function applyProvider() {
    var checked = radios.find(function (r) { return r.checked; });
    var prov = checked ? checked.value : 'graph';
    conexion.classList.toggle('prov-imap', prov === 'imap');
    conexion.classList.toggle('prov-graph', prov !== 'imap');
  }
  radios.forEach(function (r) { r.addEventListener('change', applyProvider); });
  applyProvider();

  // Probar conexión (AJAX)
  var btn = document.getElementById('md-test-btn');
  var out = document.getElementById('md-test-result');
  if (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true; btn.textContent = 'Probando…';
      out.innerHTML = '';
      var form = document.querySelector('#md-conexion form');
      var data = new FormData(form);
      fetch('<?= route_to('dispatch.settings.test') ?>', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: data
      })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var cls = j.status === 'success' ? 'banner-success' : 'banner-critical';
        out.innerHTML = '<div class="banner ' + cls + '"><div class="banner-body">' + (j.message || '') + '</div></div>';
      })
      .catch(function () {
        out.innerHTML = '<div class="banner banner-critical"><div class="banner-body">No se pudo ejecutar la prueba.</div></div>';
      })
      .finally(function () { btn.disabled = false; btn.textContent = 'Probar conexión'; });
    });
  }

  // Danger zone: enable each purge button only when the typed confirmation
  // matches the mailbox (case-insensitive), and ask once more on submit.
  Array.prototype.slice.call(document.querySelectorAll('.md-purge-form')).forEach(function (form) {
    var mailbox = (form.dataset.mailbox || '').trim().toLowerCase();
    var input   = form.querySelector('.md-purge-confirm');
    var submit  = form.querySelector('.md-purge-btn');
    if (!input || !submit) { return; }
    function sync() {
      submit.disabled = input.value.trim().toLowerCase() !== mailbox || mailbox === '';
    }
    input.addEventListener('input', sync);
    sync();
    form.addEventListener('submit', function (e) {
      if (submit.disabled || !window.confirm('Esta acción elimina datos de la bandeja de forma permanente y no se puede deshacer. ¿Continuar?')) {
        e.preventDefault();
      }
    });
  });
})();
</script>
<?= $this->endSection() ?>
