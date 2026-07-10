<?php
/**
 * Provisioning welcome email.
 *
 * Sent to the employee's primary (institutional) mailbox right after their
 * accounts are created. Inline CSS only (email clients ignore <style> and CSS
 * custom properties). No em-dashes, no emojis per the project frontend rules.
 *
 * @var string      $orgName
 * @var string|null $logoUrl
 * @var string      $employeeName
 * @var string      $loginEmail
 * @var string      $tempPassword
 * @var array       $systems        list of ['name' => string, 'url' => string]
 * @var string      $helpDeskEmail
 */
$brand     = '#1773C8';
$brandDark = '#125a9c';
$brandSoft = '#e8f1fb';
$gray      = '#808285';
$ink       = '#1a1c1e';
$muted     = '#5c6670';
$border    = '#e3e6e9';
$bgSoft    = '#f4f6f8';

// Avatar initials from the employee name.
$parts    = preg_split('/\s+/', trim((string) $employeeName)) ?: [];
$initials = strtoupper(mb_substr($parts[0] ?? '', 0, 1) . (isset($parts[1]) ? mb_substr($parts[1], 0, 1) : ''));
if ($initials === '') {
    $initials = '?';
}
?><!-- preheader --><div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">Ya preparamos tus accesos. Aqui esta tu correo y tu contrasena temporal.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= $bgSoft ?>;margin:0;padding:24px 0;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
  <tr>
    <td align="center" style="padding:0 12px;">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:100%;background:#ffffff;border:1px solid <?= $border ?>;border-radius:14px;overflow:hidden;">

        <!-- Logo bar -->
        <tr>
          <td style="padding:20px 32px 16px;">
            <?php if (! empty($logoUrl)): ?>
              <img src="<?= esc($logoUrl, 'attr') ?>" alt="<?= esc($orgName, 'attr') ?>" height="34" style="height:34px;width:auto;display:block;border:0;">
            <?php else: ?>
              <div style="font-size:18px;font-weight:700;color:<?= $brand ?>;"><?= esc($orgName) ?></div>
            <?php endif; ?>
          </td>
        </tr>

        <!-- Hero -->
        <tr>
          <td style="padding:0 16px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:12px;background-color:<?= $brand ?>;background-image:radial-gradient(circle at 92% -20%, rgba(255,255,255,0.20) 0, rgba(255,255,255,0.20) 95px, transparent 96px),radial-gradient(circle at 6% 130%, rgba(255,255,255,0.14) 0, rgba(255,255,255,0.14) 78px, transparent 79px);">
              <tr>
                <td style="padding:34px 32px;">
                  <div style="color:#ffffff;font-size:13px;letter-spacing:1px;text-transform:uppercase;opacity:0.9;margin-bottom:8px;">Te damos la bienvenida</div>
                  <div style="color:#ffffff;font-size:28px;font-weight:800;line-height:1.2;">Bienvenido a <?= esc($orgName) ?></div>
                  <div style="color:#ffffff;font-size:15px;line-height:1.6;opacity:0.92;margin-top:10px;max-width:420px;">Nos da mucho gusto tenerte en el equipo. Ya dejamos listos tus accesos para que empieces con el pie derecho.</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Greeting with avatar -->
        <tr>
          <td style="padding:24px 32px 6px;">
            <table role="presentation" cellpadding="0" cellspacing="0">
              <tr>
                <td valign="middle" style="padding-right:16px;">
                  <div style="width:56px;height:56px;border-radius:50%;background:<?= $brandSoft ?>;color:<?= $brandDark ?>;font-size:20px;font-weight:700;text-align:center;line-height:56px;"><?= esc($initials) ?></div>
                </td>
                <td valign="middle">
                  <div style="font-size:13px;color:<?= $muted ?>;">Hola,</div>
                  <div style="font-size:20px;font-weight:700;color:<?= $ink ?>;line-height:1.25;"><?= esc($employeeName) ?></div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Credentials card -->
        <tr>
          <td style="padding:18px 32px 8px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= $bgSoft ?>;border:1px solid <?= $border ?>;border-radius:12px;">
              <tr>
                <td style="padding:20px 22px;">
                  <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:<?= $muted ?>;margin-bottom:4px;">Tu correo (usuario)</div>
                  <div style="font-size:17px;font-weight:600;color:<?= $ink ?>;word-break:break-all;"><?= esc($loginEmail) ?></div>
                  <div style="height:1px;background:<?= $border ?>;margin:16px 0;"></div>
                  <div style="font-size:12px;text-transform:uppercase;letter-spacing:0.5px;color:<?= $muted ?>;margin-bottom:4px;">Contrasena temporal</div>
                  <div style="display:inline-block;background:#ffffff;border:1px solid <?= $border ?>;border-radius:8px;padding:8px 14px;font-size:19px;font-weight:700;color:<?= $brandDark ?>;font-family:Menlo,Consolas,monospace;letter-spacing:0.5px;word-break:break-all;"><?= esc($tempPassword) ?></div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Security notice -->
        <tr>
          <td style="padding:8px 32px 4px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff7e6;border:1px solid #ffe1a8;border-radius:10px;">
              <tr>
                <td style="padding:14px 18px;color:#8a5a00;font-size:14px;line-height:1.55;">
                  <strong>Importante:</strong> esta contrasena es temporal. Por tu seguridad, cambiala lo antes posible en cada sistema.
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Tus accesos -->
        <tr>
          <td style="padding:22px 32px 6px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= $brandSoft ?>;border:1px solid #cfe2f7;border-radius:12px;">
              <tr>
                <td style="padding:18px 22px;">
                  <div style="font-size:14px;font-weight:700;color:<?= $brandDark ?>;margin-bottom:14px;">Tus otros accesos</div>
                  <?php if (! empty($systems)): ?>
                    <?php foreach ($systems as $i => $s): ?>
                      <div style="<?= $i > 0 ? 'margin-top:14px;padding-top:14px;border-top:1px solid #cfe2f7;' : '' ?>">
                        <div style="font-size:15px;font-weight:600;color:<?= $ink ?>;"><?= esc($s['name']) ?></div>
                        <?php if (! empty($s['url'])): ?>
                          <div style="margin-top:3px;font-size:13.5px;"><a href="<?= esc($s['url'], 'attr') ?>" style="color:<?= $brand ?>;text-decoration:none;word-break:break-all;"><?= esc($s['url']) ?></a></div>
                        <?php endif; ?>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div style="font-size:13.5px;color:<?= $muted ?>;">Se te informaran por separado.</div>
                  <?php endif; ?>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Primeros pasos -->
        <tr>
          <td style="padding:14px 32px 6px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid <?= $border ?>;border-radius:12px;">
              <tr>
                <td style="padding:18px 22px;">
                  <div style="font-size:14px;font-weight:700;color:<?= $ink ?>;margin-bottom:14px;">Primeros pasos</div>
                  <div style="font-size:14px;color:<?= $muted ?>;line-height:1.55;margin-bottom:12px;"><strong style="color:<?= $brandDark ?>;">1.</strong> Ya estas en tu correo institucional: este mensaje llego aqui, asi que tu correo ya funciona.</div>
                  <div style="font-size:14px;color:<?= $muted ?>;line-height:1.55;margin-bottom:12px;"><strong style="color:<?= $brandDark ?>;">2.</strong> Entra a la Intranet y a GLPI (enlaces arriba) con este mismo correo y tu contrasena temporal.</div>
                  <div style="font-size:14px;color:<?= $muted ?>;line-height:1.55;"><strong style="color:<?= $brandDark ?>;">3.</strong> Una vez dentro, actualiza tu contrasena en cada sistema por una propia.</div>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Help desk -->
        <tr>
          <td style="padding:20px 32px 24px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:<?= $ink ?>;border-radius:12px;">
              <tr>
                <td style="padding:18px 22px;">
                  <div style="color:#ffffff;font-size:14px;font-weight:700;margin-bottom:4px;">Soporte</div>
                  <div style="color:#c9ced3;font-size:13.5px;line-height:1.6;">Si tienes algun problema con tus accesos, reportalo directamente a la mesa de ayuda central:</div>
                  <a href="mailto:<?= esc($helpDeskEmail, 'attr') ?>" style="display:inline-block;margin-top:8px;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;"><?= esc($helpDeskEmail) ?></a>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Color bar -->
        <tr>
          <td style="padding:0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td height="6" style="background:<?= $brandDark ?>;width:45%;font-size:0;line-height:0;">&nbsp;</td>
                <td height="6" style="background:<?= $brand ?>;width:35%;font-size:0;line-height:0;">&nbsp;</td>
                <td height="6" style="background:<?= $gray ?>;width:20%;font-size:0;line-height:0;">&nbsp;</td>
              </tr>
            </table>
          </td>
        </tr>

      </table>

      <div style="max-width:600px;margin:16px auto 0;color:<?= $muted ?>;font-size:12px;line-height:1.5;text-align:center;">
        Este mensaje se genero automaticamente al crear tus cuentas. No es necesario responderlo.
      </div>
    </td>
  </tr>
</table>
