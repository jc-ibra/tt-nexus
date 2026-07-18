<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php $v = fn(string $k): string => esc($values[$k] ?? ''); ?>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Correo de bienvenida</h1>
    <p class="page-subtitle">Controla si se envia el correo al aprovisionar y edita sus textos. Los enlaces de cada sistema se toman de su URL configurada.</p>
  </div>
  <div class="page-actions">
    <a href="<?= route_to('provisioning.systems.index') ?>" class="btn btn-secondary">Sistemas destino</a>
  </div>
</div>

<div style="max-width:720px; display:flex; flex-direction:column; gap:var(--space-4);">

<form method="post" action="<?= route_to('provisioning.settings.update') ?>" class="card">
  <?= csrf_field() ?>

  <div class="card-header">
    <h2 class="card-title">Envio y contenido</h2>
  </div>

  <div class="card-body" style="display:flex; flex-direction:column; gap:var(--space-4);">

    <div class="field">
      <label class="field-check">
        <input type="checkbox" name="welcome_email_enabled" value="1" <?= ($values['welcome_email_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
        <span><strong>Enviar el correo de bienvenida</strong> al aprovisionar o reactivar empleados.</span>
      </label>
      <p class="text-muted text-sm" style="margin-top:var(--space-1);">Si lo desactivas, las cuentas se crean igual en todos los sistemas, pero no se envia el correo con la contrasena temporal.</p>
    </div>

    <div class="field">
      <label class="field-label" for="welcome_email_subject">Asunto</label>
      <input type="text" id="welcome_email_subject" name="welcome_email_subject" class="input" value="<?= $v('welcome_email_subject') ?>">
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-3);">
      <div class="field">
        <label class="field-label" for="welcome_email_org_name">Nombre de la organizacion</label>
        <input type="text" id="welcome_email_org_name" name="welcome_email_org_name" class="input" value="<?= $v('welcome_email_org_name') ?>">
      </div>
      <div class="field">
        <label class="field-label" for="welcome_email_help_desk_email">Correo de mesa de ayuda</label>
        <input type="text" id="welcome_email_help_desk_email" name="welcome_email_help_desk_email" class="input" value="<?= $v('welcome_email_help_desk_email') ?>">
      </div>
    </div>

    <p class="text-muted text-sm">En cualquier texto puedes usar <code>{org}</code> y se reemplaza por el nombre de la organizacion. Deja un campo vacio para usar el texto por defecto.</p>

    <div class="field">
      <label class="field-label" for="welcome_email_hero_eyebrow">Encabezado superior</label>
      <input type="text" id="welcome_email_hero_eyebrow" name="welcome_email_hero_eyebrow" class="input" value="<?= $v('welcome_email_hero_eyebrow') ?>">
    </div>

    <div class="field">
      <label class="field-label" for="welcome_email_hero_title">Titulo principal</label>
      <input type="text" id="welcome_email_hero_title" name="welcome_email_hero_title" class="input" value="<?= $v('welcome_email_hero_title') ?>">
    </div>

    <div class="field">
      <label class="field-label" for="welcome_email_hero_intro">Introduccion</label>
      <textarea id="welcome_email_hero_intro" name="welcome_email_hero_intro" class="input" rows="2"><?= $v('welcome_email_hero_intro') ?></textarea>
    </div>

    <div class="field">
      <label class="field-label" for="welcome_email_security_notice">Aviso de seguridad</label>
      <textarea id="welcome_email_security_notice" name="welcome_email_security_notice" class="input" rows="2"><?= $v('welcome_email_security_notice') ?></textarea>
      <p class="text-muted text-sm" style="margin-top:var(--space-1);">Aparece despues de la etiqueta fija <strong>Importante:</strong></p>
    </div>

    <div class="field">
      <label class="field-label" for="welcome_email_support_intro">Texto de soporte</label>
      <textarea id="welcome_email_support_intro" name="welcome_email_support_intro" class="input" rows="2"><?= $v('welcome_email_support_intro') ?></textarea>
    </div>

    <div class="field">
      <label class="field-label" for="welcome_email_footer">Pie del correo</label>
      <textarea id="welcome_email_footer" name="welcome_email_footer" class="input" rows="2"><?= $v('welcome_email_footer') ?></textarea>
    </div>

  </div>

  <div class="card-footer" style="display:flex; gap:var(--space-2); justify-content:flex-end;">
    <button type="submit" class="btn btn-primary">Guardar cambios</button>
  </div>
</form>

<div class="card">
  <div class="card-header">
    <h2 class="card-title">Enlaces de los sistemas</h2>
  </div>
  <div class="card-body">
    <p class="text-muted text-sm" style="margin-bottom:var(--space-3);">Los enlaces que aparecen en la seccion "Tus otros accesos" del correo se toman de la URL de cada sistema. Edita esas URLs en <a href="<?= route_to('provisioning.systems.index') ?>">Sistemas destino</a>.</p>
    <table class="table" style="width:100%;">
      <thead>
        <tr><th>Sistema</th><th>Clave</th><th>URL usada en el correo</th></tr>
      </thead>
      <tbody>
        <?php foreach ($systems as $s): ?>
          <?php if (($s['key'] ?? '') === 'mailcow') { continue; } ?>
          <tr>
            <td><strong><?= esc($s['name']) ?></strong></td>
            <td><code><?= esc($s['key']) ?></code></td>
            <td class="text-muted text-sm"><?= esc($s['base_url'] ?: '(sin URL; no se mostrara enlace)') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<form method="post" action="<?= route_to('provisioning.settings.test') ?>" class="card">
  <?= csrf_field() ?>
  <div class="card-header">
    <h2 class="card-title">Enviar correo de prueba</h2>
  </div>
  <div class="card-body">
    <p class="text-muted text-sm" style="margin-bottom:var(--space-3);">Envia una muestra con datos de ejemplo y los enlaces reales de los sistemas activos. Ignora el interruptor de arriba.</p>
    <div class="field" style="max-width:420px;">
      <label class="field-label" for="test_email">Correo destino</label>
      <input type="email" id="test_email" name="test_email" class="input" placeholder="tu-correo@dominio.mx" required>
    </div>
  </div>
  <div class="card-footer" style="display:flex; gap:var(--space-2); justify-content:flex-end;">
    <button type="submit" class="btn btn-secondary">Enviar prueba</button>
  </div>
</form>

</div>

<?= $this->endSection() ?>
