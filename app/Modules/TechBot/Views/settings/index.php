<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$s   = $settings;
$val = fn(string $k, string $d = '') => esc($s[$k] ?? $d);
$on  = fn(string $k, string $d = '0') => ($s[$k] ?? $d) === '1' ? 'checked' : '';
?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Configuración · TechBot</h1>
    <p class="page-subtitle">Token del bot, webhook, mensajes y formateo con IA. Los secretos se guardan cifrados.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('techbot.index') ?>" class="btn btn-secondary">‹ Panel</a>
  </div>
</div>

<!-- Connection actions -->
<div class="card" style="margin-bottom: var(--space-4);">
  <div class="card-header"><h2 class="card-title">Conexión con Telegram</h2></div>
  <div class="card-body">
    <p class="text-muted text-sm" style="margin-top:0;">
      1. Crea el bot con @BotFather y copia su token. 2. Pégalo abajo y guarda. 3. Prueba la conexión y registra el webhook.
    </p>
    <div class="field" style="margin-bottom: var(--space-3);">
      <label class="field-label">URL del webhook</label>
      <input class="input" type="text" value="<?= esc($webhookUrl) ?>" readonly onclick="this.select()">
      <p class="field-help">Se registra automáticamente en Telegram con el botón «Registrar webhook». Requiere HTTPS válido.</p>
    </div>
    <div style="display:flex; gap:var(--space-2); flex-wrap:wrap;">
      <form method="post" action="<?= route_to('techbot.settings.test') ?>" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-secondary" <?= $hasToken ? '' : 'disabled' ?>>Probar conexión</button>
      </form>
      <form method="post" action="<?= route_to('techbot.settings.webhook') ?>" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary" <?= $hasToken ? '' : 'disabled' ?>>Registrar webhook</button>
      </form>
    </div>
  </div>
</div>

<!-- Settings form -->
<form method="post" action="<?= route_to('techbot.settings.save') ?>">
  <?= csrf_field() ?>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Bot</h2></div>
    <div class="card-body">
      <label class="field-check" style="margin-bottom: var(--space-3);">
        <input type="checkbox" name="bot_enabled" value="1" <?= $on('bot_enabled') ?>>
        <span>Bot habilitado (interruptor maestro)</span>
      </label>

      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="telegram_bot_token">Token del bot (BotFather)</label>
        <input type="password" id="telegram_bot_token" name="telegram_bot_token" class="input" autocomplete="off"
               placeholder="<?= $hasToken ? 'Guardado (déjalo vacío para conservarlo)' : '7123456789:AA...' ?>">
        <p class="field-help">Se guarda cifrado. Déjalo vacío para conservar el token actual.</p>
      </div>

      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="telegram_bot_username">Username del bot (sin @)</label>
        <input type="text" id="telegram_bot_username" name="telegram_bot_username" class="input"
               value="<?= $val('telegram_bot_username') ?>" placeholder="mi_bot_soporte">
        <p class="field-help">Se usa para generar el enlace https://t.me/&lt;username&gt; que distribuyes a los técnicos.</p>
      </div>

      <div class="field">
        <label class="field-label" for="telegram_webhook_secret">Secreto del webhook</label>
        <input type="password" id="telegram_webhook_secret" name="telegram_webhook_secret" class="input" autocomplete="off"
               placeholder="<?= $hasSecret ? 'Definido (se regenera solo al registrar si está vacío)' : 'Se genera automáticamente al registrar el webhook' ?>">
        <p class="field-help">Valida que los mensajes vienen de Telegram. Se guarda cifrado; si lo dejas vacío se genera uno al registrar el webhook.</p>
      </div>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Mensajes y reglas</h2></div>
    <div class="card-body">
      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="welcome_message">Mensaje de bienvenida (tras vincular)</label>
        <textarea id="welcome_message" name="welcome_message" class="input" rows="3"><?= $val('welcome_message') ?></textarea>
      </div>
      <label class="field-check" style="margin-bottom: var(--space-2);">
        <input type="checkbox" name="require_photo_on_resolution" value="1" <?= $on('require_photo_on_resolution') ?>>
        <span>Exigir al menos una foto al documentar una resolución</span>
      </label>
      <label class="field-check" style="margin-bottom: var(--space-2);">
        <input type="checkbox" name="require_visto_bueno_on_resolution" value="1" <?= $on('require_visto_bueno_on_resolution', '1') ?>>
        <span>Exigir el campo de Visto Bueno al resolver</span>
      </label>
      <label class="field-check">
        <input type="checkbox" name="allow_resolucion_arbitraria" value="1" <?= $on('allow_resolucion_arbitraria') ?>>
        <span>Permitir a los técnicos el cierre administrativo (resolución arbitraria)</span>
      </label>
    </div>
  </div>

  <div class="card" style="margin-bottom: var(--space-4);">
    <div class="card-header"><h2 class="card-title">Formateo con IA (opcional)</h2></div>
    <div class="card-body">
      <?php if (! $aiConfigured): ?>
        <div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-3);">
          <div class="banner-body">La API key de Claude se toma del módulo Service Desk y aún no está configurada. Configúrala allí para usar el formateo.</div>
        </div>
      <?php endif; ?>
      <p class="text-muted text-sm" style="margin-top:0;">
        Reutiliza la API key y el modelo de Service Desk. Estructura el texto libre de diagnósticos y resoluciones; el técnico elige entre el texto original y el formateado. Nunca bloquea el flujo si falla.
      </p>
      <label class="field-check" style="margin-bottom: var(--space-3);">
        <input type="checkbox" name="ai_formatting_enabled" value="1" <?= $on('ai_formatting_enabled') ?>>
        <span>Habilitar formateo con Claude</span>
      </label>
      <div class="field" style="margin-bottom: var(--space-3);">
        <label class="field-label" for="ai_max_tokens">Máximo de tokens por formateo</label>
        <input type="number" id="ai_max_tokens" name="ai_max_tokens" class="input" min="256" value="<?= $val('ai_max_tokens', '1024') ?>">
      </div>
      <div class="field">
        <label class="field-label" for="ai_system_prompt">Instrucciones del formateador (prompt)</label>
        <textarea id="ai_system_prompt" name="ai_system_prompt" class="input" rows="12"
                  style="font-family: var(--font-mono, monospace); font-size:12px; line-height:1.5;"><?= esc($aiSystemPrompt) ?></textarea>
        <p class="field-help">Déjalo vacío para usar el prompt por defecto.</p>
      </div>
    </div>
  </div>

  <div style="text-align:right;">
    <button type="submit" class="btn btn-primary">Guardar configuración</button>
  </div>
</form>

<?= $this->endSection() ?>
