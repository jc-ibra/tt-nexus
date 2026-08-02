<?php
/**
 * Backlog report email body (inline CSS for SMTP client compatibility).
 *
 * @var string $orgLabel
 * @var string $cutoffLabel   human cut-off ("15 de July de 2026")
 * @var int    $total
 * @var int    $pending
 * @var int    $critical
 * @var int    $criticalDays
 * @var ?int   $sinIdc
 * @var bool   $idcConfigured
 * @var array  $areas         area key => ['label','total','critical','pending','sinIdc','buckets','subcategories','oldest']
 * @var array  $areaOrder
 * @var array  $daily         area key => ['label','created','closed','net']  (same-day activity)
 * @var string $dailyLabel    day the activity refers to (dd/mm/YYYY)
 * @var array  $typeSummary   ['rows'=>[['label','total','atendidos','backlog'],...],'folios'=>[...]]
 * @var array  $buckets       config bucket defs (key,label,min,max,color)
 */
$pct = static fn(int $n, int $d): int => $d > 0 ? (int) round($n / $d * 100) : 0;
// Age color thresholds shared by the regional and client tables.
$avgColor = static function ($d): string {
    $d = (float) $d;
    if ($d <= 7)  return '#108043';
    if ($d <= 20) return '#c05717';
    return '#de3618';
};
$nf  = static fn($n) => number_format((int) $n);

// Areas that actually carry tickets, in configured order.
$shownAreas = [];
foreach ($areaOrder as $k) {
    if (isset($areas[$k]) && ($areas[$k]['total'] ?? 0) > 0) {
        $shownAreas[$k] = $areas[$k];
    }
}
// Per-area KPI cards render two-per-row.
$areaChunks = array_chunk($shownAreas, 2, true);

// Same-day activity: always show the real areas; hide "Sin clasificar" unless it
// had movement today. Totals row summed from the shown areas.
$daily      = $daily ?? [];
$dailyShown = [];
$dailyTot   = ['created' => 0, 'closed' => 0, 'net' => 0];
foreach ($daily as $dk => $d) {
    if ($dk === 'sin_clasificar' && ((int) $d['created'] + (int) $d['closed']) === 0) {
        continue;
    }
    $dailyShown[$dk] = $d;
    $dailyTot['created'] += (int) $d['created'];
    $dailyTot['closed']  += (int) $d['closed'];
    $dailyTot['net']     += (int) $d['net'];
}
// Net = backlog delta: positive (grew) is bad → red; negative (shrank) is good → green.
$netColor = static fn(int $n): string => $n > 0 ? '#de3618' : ($n < 0 ? '#108043' : '#637381');
$netText  = static fn(int $n): string => ($n > 0 ? '+' : '') . number_format($n);
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0; padding:0; background:#eef0f2;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f2; padding:24px 0;">
  <tr><td align="center">
    <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="width:640px; max-width:640px; background:#ffffff; border-radius:12px; overflow:hidden; font-family:Arial,Helvetica,sans-serif; color:#212b36;">

      <!-- Header -->
      <tr><td style="background:#1c2530; padding:24px 28px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <td width="52" valign="top">
            <div style="width:44px; height:44px; border-radius:50%; border:2px solid #1773C8; color:#ffffff; font-weight:bold; font-size:15px; line-height:44px; text-align:center;">TT</div>
          </td>
          <td valign="middle">
            <div style="color:#8f9bb3; font-size:11px; letter-spacing:.08em; text-transform:uppercase;"><?= esc($orgLabel) ?></div>
            <div style="color:#ffffff; font-size:22px; font-weight:bold; line-height:1.2;">Reporte de Backlog</div>
            <div style="color:#8f9bb3; font-size:12px; margin-top:4px;">Corte: <?= esc($cutoffLabel) ?> · <?= $nf($total) ?> tickets · Fuente: GLPI</div>
          </td>
        </tr></table>
      </td></tr>

      <!-- KPI tiles -->
      <tr><td style="padding:20px 24px 8px 24px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <td width="25%" style="padding:4px;">
            <div style="background:#f4f6f8; border-radius:8px; padding:16px 14px; text-align:center;">
              <div style="font-size:26px; font-weight:bold; color:#212b36;"><?= $nf($total) ?></div>
              <div style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; margin-top:4px;">Total abiertos</div>
              <div style="font-size:10px; color:#919eab;">al cierre del día</div>
            </div>
          </td>
          <td width="25%" style="padding:4px;">
            <div style="background:#fbeae5; border-radius:8px; padding:16px 14px; text-align:center;">
              <div style="font-size:26px; font-weight:bold; color:#de3618;"><?= $nf($critical) ?></div>
              <div style="font-size:10px; letter-spacing:.05em; color:#de3618; text-transform:uppercase; margin-top:4px;">Críticos +<?= (int) $criticalDays ?>D</div>
              <div style="font-size:10px; color:#b0655a;"><?= $pct($critical, $total) ?>% del backlog</div>
            </div>
          </td>
          <td width="25%" style="padding:4px;">
            <div style="background:#fcf1e5; border-radius:8px; padding:16px 14px; text-align:center;">
              <?php if ($idcConfigured): $idcBase = ! empty($idcScopeTotal) ? (int) $idcScopeTotal : $total; ?>
                <div style="font-size:26px; font-weight:bold; color:#c05717;"><?= $nf($sinIdc) ?></div>
                <div style="font-size:10px; letter-spacing:.05em; color:#c05717; text-transform:uppercase; margin-top:4px;">Sin IDC</div>
                <div style="font-size:10px; color:#b0805a;"><?= $pct((int) $sinIdc, $idcBase) ?>% sin asignar</div>
              <?php else: ?>
                <div style="font-size:26px; font-weight:bold; color:#919eab;">—</div>
                <div style="font-size:10px; letter-spacing:.05em; color:#919eab; text-transform:uppercase; margin-top:4px;">Sin IDC</div>
                <div style="font-size:10px; color:#919eab;">no configurado</div>
              <?php endif; ?>
            </div>
          </td>
          <td width="25%" style="padding:4px;">
            <div style="background:#e8f0fb; border-radius:8px; padding:16px 14px; text-align:center;">
              <div style="font-size:26px; font-weight:bold; color:#1773C8;"><?= $nf($pending) ?></div>
              <div style="font-size:10px; letter-spacing:.05em; color:#1773C8; text-transform:uppercase; margin-top:4px;">En espera</div>
              <div style="font-size:10px; color:#6a92c0;">requieren acción</div>
            </div>
          </td>
        </tr></table>
      </td></tr>

      <?php
      $typeSummary = $typeSummary ?? ['rows' => [], 'folios' => null];
      if (! empty($typeSummary['rows'])):
        $tsFolios = $typeSummary['folios'] ?? null;
        $backlogColor = static fn(int $n): string => $n > 0 ? '#de3618' : '#108043';
      ?>
      <!-- Resumen por tipo (Incidencias / Requerimientos) -->
      <tr><td style="padding:12px 28px 4px 28px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin-bottom:4px;">Resumen por tipo</div>
        <div style="font-size:10px; color:#919eab; margin-bottom:12px;">Total = abiertos + atendidos hoy · Atendidos = cerrados hoy (<?= esc($dailyLabel) ?>) · Backlog = siguen abiertos (foco)</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e1e4e8; border-radius:8px; overflow:hidden;">
          <tr style="background:#f4f6f8;">
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px;">Tipo</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Total</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Atendidos hoy</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Backlog</td>
          </tr>
          <?php foreach ($typeSummary['rows'] as $ts): ?>
            <tr>
              <td style="font-size:12px; color:#212b36; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3;"><?= esc($ts['label']) ?></td>
              <td style="font-size:12px; color:#212b36; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($ts['total']) ?></td>
              <td style="font-size:12px; color:#108043; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($ts['atendidos']) ?></td>
              <td style="font-size:12px; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $backlogColor((int) $ts['backlog']) ?>;"><?= $nf($ts['backlog']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (! empty($tsFolios)): ?>
          <tr style="background:#f4f6f8;">
            <td style="font-size:12px; color:#212b36; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8;"><?= esc($tsFolios['label']) ?></td>
            <td style="font-size:12px; color:#212b36; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8; text-align:right;"><?= $nf($tsFolios['total']) ?></td>
            <td style="font-size:12px; color:#108043; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8; text-align:right;"><?= $nf($tsFolios['atendidos']) ?></td>
            <td style="font-size:12px; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8; text-align:right; color:<?= $backlogColor((int) $tsFolios['backlog']) ?>;"><?= $nf($tsFolios['backlog']) ?></td>
          </tr>
          <?php endif; ?>
        </table>
        <div style="font-size:10px; color:#919eab; margin-top:8px;">El detalle completo de tickets del backlog está en el archivo Excel adjunto (hoja Resumen y hoja Backlog).</div>
      </td></tr>
      <?php endif; ?>

      <?php if ($dailyShown !== []): ?>
      <!-- Same-day activity by area -->
      <tr><td style="padding:12px 28px 4px 28px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin-bottom:4px;">Actividad del día por área</div>
        <div style="font-size:10px; color:#919eab; margin-bottom:12px;">Tickets generados y cerrados hoy (<?= esc($dailyLabel) ?>) · Neto = generados - cerrados (variación del backlog)</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e1e4e8; border-radius:8px; overflow:hidden;">
          <tr style="background:#f4f6f8;">
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px;">Área</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Generados hoy</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Cerrados hoy</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Neto backlog</td>
          </tr>
          <?php foreach ($dailyShown as $dk => $d): $net = (int) $d['net']; $isSin = $dk === 'sin_clasificar'; ?>
            <tr<?= $isSin ? ' style="background:#fcf1e5;"' : '' ?>>
              <td style="font-size:12px; color:<?= $isSin ? '#919eab' : '#212b36' ?>; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3;"><?= esc($d['label']) ?></td>
              <td style="font-size:12px; color:#1773C8; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($d['created']) ?></td>
              <td style="font-size:12px; color:#108043; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($d['closed']) ?></td>
              <td style="font-size:12px; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $netColor($net) ?>;"><?= $netText($net) ?></td>
            </tr>
          <?php endforeach; ?>
          <tr style="background:#f4f6f8;">
            <td style="font-size:12px; color:#212b36; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8;">Total</td>
            <td style="font-size:12px; color:#1773C8; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8; text-align:right;"><?= $nf($dailyTot['created']) ?></td>
            <td style="font-size:12px; color:#108043; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8; text-align:right;"><?= $nf($dailyTot['closed']) ?></td>
            <td style="font-size:12px; font-weight:bold; padding:8px 12px; border-top:2px solid #e1e4e8; text-align:right; color:<?= $netColor((int) $dailyTot['net']) ?>;"><?= $netText((int) $dailyTot['net']) ?></td>
          </tr>
        </table>
      </td></tr>
      <?php endif; ?>

      <?php if ($shownAreas !== []): ?>
      <!-- Per-area KPI summary (cuadro resumen por área) -->
      <tr><td style="padding:12px 24px 4px 24px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin:0 4px 12px 4px;">Resumen por área</div>
        <?php foreach ($areaChunks as $chunk): ?>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
            <?php foreach ($chunk as $ak => $area): ?>
              <td width="50%" valign="top" style="padding:4px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e1e4e8; border-radius:8px; overflow:hidden;">
                  <tr><td colspan="2" style="background:#1c2530; color:#ffffff; font-size:13px; font-weight:bold; padding:8px 12px;"><?= esc($area['label']) ?> · <?= $nf($area['total']) ?> abiertos</td></tr>
                  <tr>
                    <td width="50%" style="padding:10px 12px; border-top:1px solid #f0f1f3; border-right:1px solid #f0f1f3;">
                      <div style="font-size:20px; font-weight:bold; color:#de3618;"><?= $nf($area['critical']) ?></div>
                      <div style="font-size:10px; color:#637381; text-transform:uppercase; letter-spacing:.04em;">Críticos +<?= (int) $criticalDays ?>d</div>
                    </td>
                    <td width="50%" style="padding:10px 12px; border-top:1px solid #f0f1f3;">
                      <div style="font-size:20px; font-weight:bold; color:#1773C8;"><?= $nf($area['pending']) ?></div>
                      <div style="font-size:10px; color:#637381; text-transform:uppercase; letter-spacing:.04em;">En espera</div>
                    </td>
                  </tr>
                  <tr>
                    <td style="padding:10px 12px; border-top:1px solid #f0f1f3; border-right:1px solid #f0f1f3;">
                      <div style="font-size:20px; font-weight:bold; color:<?= $idcConfigured ? '#c05717' : '#c4cdd5' ?>;"><?= $idcConfigured ? $nf($area['sinIdc']) : 'n/d' ?></div>
                      <div style="font-size:10px; color:#637381; text-transform:uppercase; letter-spacing:.04em;">Sin IDC</div>
                    </td>
                    <td style="padding:10px 12px; border-top:1px solid #f0f1f3;">
                      <div style="font-size:20px; font-weight:bold; color:#212b36;"><?= $pct((int) $area['critical'], (int) $area['total']) ?>%</div>
                      <div style="font-size:10px; color:#637381; text-transform:uppercase; letter-spacing:.04em;">% crítico</div>
                    </td>
                  </tr>
                </table>
              </td>
            <?php endforeach; ?>
            <?php if (count($chunk) === 1): ?><td width="50%" style="padding:4px;">&nbsp;</td><?php endif; ?>
          </tr></table>
        <?php endforeach; ?>
      </td></tr>
      <?php endif; ?>

      <?php if ($shownAreas !== []): ?>
      <!-- Age by area -->
      <tr><td style="padding:12px 28px 4px 28px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin-bottom:12px;">Antigüedad del backlog por área</div>
        <?php foreach ($shownAreas as $area): ?>
          <div style="margin-bottom:16px;">
            <div style="font-size:12px; font-weight:bold; color:#1773C8; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;"><?= esc($area['label']) ?></div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-radius:5px; overflow:hidden;"><tr>
              <?php foreach ($buckets as $b): $c = (int) ($area['buckets'][$b['key']] ?? 0); if ($c <= 0) continue; ?>
                <td width="<?= $pct($c, (int) $area['total']) ?>%" style="background:<?= $b['color'] ?>; color:#ffffff; font-size:11px; font-weight:bold; text-align:center; padding:6px 2px; white-space:nowrap;"><?= $nf($c) ?></td>
              <?php endforeach; ?>
            </tr></table>
            <div style="margin-top:6px; font-size:10px; color:#637381;">
              <?php foreach ($buckets as $b): ?>
                <span style="display:inline-block; margin-right:10px;"><span style="display:inline-block; width:8px; height:8px; background:<?= $b['color'] ?>; border-radius:2px; margin-right:3px;"></span><?= esc($b['label']) ?> (<?= $nf($area['buckets'][$b['key']] ?? 0) ?>)</span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </td></tr>

      <!-- Subcategory breakdown by area -->
      <tr><td style="padding:8px 28px 24px 28px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin:8px 0 12px 0;">Desglose por subcategoría</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
          <?php foreach ($shownAreas as $area): ?>
            <td width="50%" valign="top" style="padding:4px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e1e4e8; border-radius:8px; overflow:hidden;">
                <tr><td style="background:#1773C8; color:#ffffff; font-size:13px; font-weight:bold; padding:8px 12px;"><?= esc($area['label']) ?></td>
                    <td style="background:#1773C8; color:#ffffff; font-size:13px; font-weight:bold; padding:8px 12px; text-align:right;"><?= $nf($area['total']) ?></td></tr>
                <?php foreach ($area['subcategories'] as $name => $count): ?>
                  <tr><td style="font-size:12px; color:#454f5b; padding:6px 12px; border-top:1px solid #f0f1f3;"><?= esc($name) ?></td>
                      <td style="font-size:12px; color:#212b36; font-weight:bold; padding:6px 12px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($count) ?></td></tr>
                <?php endforeach; ?>
              </table>
            </td>
          <?php endforeach; ?>
        </tr></table>
      </td></tr>

      <!-- Top 5 oldest tickets per area -->
      <?php
      // Only the real business areas (Administración / Operaciones); the residual
      // "Sin clasificar" bucket is left out of this ranking.
      $oldestAreas = [];
      foreach ($shownAreas as $ak => $area) {
          if ($ak !== 'sin_clasificar' && ! empty($area['oldest'])) {
              $oldestAreas[$ak] = $area;
          }
      }
      ?>
      <?php if ($oldestAreas !== []): ?>
      <tr><td style="padding:4px 28px 24px 28px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin:8px 0 12px 0;">Top 5 tickets más antiguos por área</div>
        <?php foreach ($oldestAreas as $area): ?>
          <div style="margin-bottom:16px;">
            <div style="font-size:12px; font-weight:bold; color:#1773C8; text-transform:uppercase; letter-spacing:.04em; margin-bottom:6px;"><?= esc($area['label']) ?></div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e1e4e8; border-radius:8px; overflow:hidden;">
              <tr style="background:#f4f6f8;">
                <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px;">#</td>
                <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px;">ID</td>
                <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px;">Título</td>
                <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px;">Estatus</td>
                <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">Días</td>
              </tr>
              <?php foreach ($area['oldest'] as $i => $o): ?>
                <tr>
                  <td style="font-size:12px; color:#919eab; font-weight:bold; padding:7px 10px; border-top:1px solid #f0f1f3;"><?= $i + 1 ?></td>
                  <td style="font-size:12px; color:#454f5b; padding:7px 10px; border-top:1px solid #f0f1f3;">#<?= (int) $o['id'] ?></td>
                  <td style="font-size:12px; color:#212b36; padding:7px 10px; border-top:1px solid #f0f1f3;"><?= esc($o['title']) ?></td>
                  <td style="font-size:12px; color:#637381; padding:7px 10px; border-top:1px solid #f0f1f3;"><?= esc($o['status']) ?></td>
                  <td style="font-size:12px; font-weight:bold; padding:7px 10px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $avgColor($o['age']) ?>;"><?= $nf($o['age']) ?>d</td>
                </tr>
              <?php endforeach; ?>
            </table>
          </div>
        <?php endforeach; ?>
      </td></tr>
      <?php endif; ?>
      <?php endif; ?>

      <?php if (! empty($regionalConfigured) && ! empty($regionals)): ?>
      <!-- Por regional -->
      <tr><td style="padding:4px 28px 24px 28px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin:8px 0 12px 0;">Por regional</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e1e4e8; border-radius:8px; overflow:hidden;">
          <tr style="background:#f4f6f8;">
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px;">Regional</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Tickets</td>
            <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Avg días</td>
            <?php if (! empty($idcConfigured)): ?>
              <td style="font-size:10px; letter-spacing:.05em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 12px; text-align:right;">Sin IDC</td>
            <?php endif; ?>
          </tr>
          <?php foreach ($regionals as $reg):
            $isSin  = $reg['name'] === 'Sin regional';
            $nameCol = $isSin ? '#919eab' : '#212b36';
            $rowBg   = $isSin ? 'background:#fcf1e5;' : '';
          ?>
            <tr style="<?= $rowBg ?>">
              <td style="font-size:12px; color:<?= $nameCol ?>; padding:7px 12px; border-top:1px solid #f0f1f3;<?= $isSin ? ' font-style:italic;' : '' ?>"><?= esc($reg['name']) ?><?= $isSin ? ' (deberían tener regional)' : '' ?></td>
              <td style="font-size:12px; color:#212b36; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($reg['tickets']) ?></td>
              <td style="font-size:12px; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $avgColor($reg['avgDays']) ?>;"><?= rtrim(rtrim(number_format((float) $reg['avgDays'], 1), '0'), '.') ?>d</td>
              <?php if (! empty($idcConfigured)): ?>
                <td style="font-size:12px; font-weight:bold; padding:7px 12px; border-top:1px solid #f0f1f3; text-align:right; color:<?= ((int) $reg['sinIdc']) > 0 ? '#de3618' : '#108043' ?>;"><?= $nf($reg['sinIdc']) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </table>
      </td></tr>
      <?php endif; ?>

      <?php if (! empty($clients)): ?>
      <!-- Por cliente -->
      <tr><td style="padding:4px 28px 24px 28px;">
        <div style="font-size:11px; letter-spacing:.06em; color:#454f5b; text-transform:uppercase; font-weight:bold; margin:8px 0 12px 0;">Por cliente</div>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e1e4e8; border-radius:8px; overflow:hidden;">
          <tr style="background:#f4f6f8;">
            <td style="font-size:10px; letter-spacing:.04em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px;">Cliente</td>
            <td style="font-size:10px; letter-spacing:.04em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">Total</td>
            <td style="font-size:10px; letter-spacing:.04em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">Avg d</td>
            <td style="font-size:10px; letter-spacing:.04em; color:#637381; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">Máx</td>
            <td style="font-size:10px; letter-spacing:.04em; color:#1773C8; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">Asignada</td>
            <td style="font-size:10px; letter-spacing:.04em; color:#919eab; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">Planificada</td>
            <td style="font-size:10px; letter-spacing:.04em; color:#c05717; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">En espera</td>
            <?php if (! empty($idcConfigured)): ?>
              <td style="font-size:10px; letter-spacing:.04em; color:#de3618; text-transform:uppercase; font-weight:bold; padding:8px 10px; text-align:right;">Sin IDC</td>
            <?php endif; ?>
          </tr>
          <?php foreach ($clients as $cli): $gray = static fn($n) => ((int) $n) > 0 ? null : '#c4cdd5'; ?>
            <tr>
              <td style="font-size:12px; color:#212b36; font-weight:bold; padding:8px 10px; border-top:1px solid #f0f1f3;"><?= esc($cli['name']) ?></td>
              <td style="font-size:12px; color:#212b36; font-weight:bold; padding:8px 10px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($cli['total']) ?></td>
              <td style="font-size:12px; font-weight:bold; padding:8px 10px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $avgColor($cli['avgDays']) ?>;"><?= rtrim(rtrim(number_format((float) $cli['avgDays'], 1), '0'), '.') ?>d</td>
              <td style="font-size:12px; color:#454f5b; padding:8px 10px; border-top:1px solid #f0f1f3; text-align:right;"><?= $nf($cli['maxDays']) ?>d</td>
              <td style="font-size:12px; font-weight:bold; padding:8px 10px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $gray($cli['asignada']) ?? '#1773C8' ?>;"><?= $nf($cli['asignada']) ?></td>
              <td style="font-size:12px; padding:8px 10px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $gray($cli['planificada']) ?? '#454f5b' ?>;"><?= $nf($cli['planificada']) ?></td>
              <td style="font-size:12px; font-weight:bold; padding:8px 10px; border-top:1px solid #f0f1f3; text-align:right; color:<?= $gray($cli['enEspera']) ?? '#c05717' ?>;"><?= $nf($cli['enEspera']) ?></td>
              <?php if (! empty($idcConfigured)): ?>
                <td style="font-size:12px; font-weight:bold; padding:8px 10px; border-top:1px solid #f0f1f3; text-align:right; color:<?= ((int) $cli['sinIdc']) > 0 ? '#de3618' : '#108043' ?>;"><?= $nf($cli['sinIdc']) ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </table>
      </td></tr>
      <?php endif; ?>

      <!-- Footer -->
      <tr><td style="background:#f4f6f8; padding:14px 28px; border-top:1px solid #e1e4e8;">
        <div style="font-size:11px; color:#919eab;">Generado automáticamente por Nexus · <?= esc($orgLabel) ?>. El detalle completo de tickets está en el archivo adjunto.</div>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
