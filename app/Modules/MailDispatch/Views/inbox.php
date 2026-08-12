<?= $this->extend('App\Modules\Core\Views\layouts\main') ?>
<?= $this->section('content') ?>

<?php
$minsOf = static function (?string $dt): int {
    $ts = $dt ? strtotime($dt) : false;
    return $ts === false ? 0 : (int) floor((time() - $ts) / 60);
};

$tabs = [
    'unassigned' => ['Sin asignar', 'M22 12h-6l-2 3h-4l-2-3H2'],
    'mine'       => ['Mías',       'M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    'all'        => ['Todas',      'M8 6h13 M8 12h13 M8 18h13 M3 6h.01 M3 12h.01 M3 18h.01'],
    'autoarchivo' => ['Autoarchivo', 'M21 8v13H3V8 M1 3h22v5H1z M10 12h4'],
    'autogenerado' => ['Autogenerados', 'M13 2 3 14h9l-1 8 10-12h-9l1-8z'],
    'closed'     => ['Cerradas',   'M20 6 9 17l-5-5'],
];

$q = $q ?? '';

$hl = static function (?string $text) use ($q): string {
    $text = (string) $text;
    if ($q === '' || $text === '') return esc($text);
    $safe = esc($text);
    return preg_replace('/(' . preg_quote(esc($q), '/') . ')/iu', '<mark class="md-hl">$1</mark>', $safe) ?? $safe;
};

$tabHref = static function (string $key) use ($q): string {
    $qs = ['filter' => $key];
    if ($q !== '') $qs['q'] = $q;
    return base_url('dispatch') . '?' . http_build_query($qs);
};

$meses = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
          7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
// Fecha + hora: hoy -> "Hoy 14:30"; este año -> "4 ago 15:22"; older -> "22/07/26 13:15".
$gmailTime = static function (?string $dt) use ($meses): string {
    if (! $dt) return '';
    $ts = strtotime($dt);
    if ($ts === false) return '';
    $hm = date('H:i', $ts);
    if (date('Y-m-d', $ts) === date('Y-m-d')) return 'Hoy ' . $hm;
    if (date('Y', $ts) === date('Y'))         return ((int) date('j', $ts)) . ' ' . $meses[(int) date('n', $ts)] . ' ' . $hm;
    return date('d/m/y', $ts) . ' ' . $hm;
};

// Iniciales para el avatar del solicitante.
$initials = static function (?string $name, ?string $email): string {
    $src = trim((string) $name) !== '' ? trim((string) $name) : trim((string) $email);
    if ($src === '') return '?';
    $parts = preg_split('/\s+/', $src) ?: [];
    $a = mb_substr($parts[0] ?? '', 0, 1);
    $b = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1) : '';
    $ini = mb_strtoupper($a . $b);
    return $ini !== '' ? $ini : '?';
};
?>

<style>
  /* ===================== Layout de 3 paneles ===================== */
  /* Altura fija (la fija el JS); cada columna scrollea por dentro, la página no. */
  .gm-app { display:grid; grid-template-columns:196px minmax(340px, 470px) 1fr; gap:var(--space-4);
    align-items:stretch; overflow:hidden; }
  .gm-col { min-width:0; min-height:0; }
  .gm-card { background:var(--bg-surface); border:1px solid var(--border-default); border-radius:var(--radius-lg);
    box-shadow:var(--shadow-sm); overflow:hidden; }

  /* Columna lista: buscador fijo arriba, lista scrolleable, pie fijo abajo. */
  .gm-listcard { display:flex; flex-direction:column; max-height:100%; }
  .gm-listcard .gm-list { flex:1 1 auto; overflow-y:auto; min-height:0; }
  .gm-listcard .card-footer { flex:0 0 auto; }

  /* ---- Rail izquierdo (tabs verticales tipo Gmail) ---- */
  .gm-rail { display:flex; flex-direction:column; gap:2px; padding:var(--space-2); }
  .gm-rail a { display:flex; align-items:center; gap:var(--space-3); padding:var(--space-2) var(--space-3);
    border-radius:var(--radius-full); color:var(--text-secondary); text-decoration:none; font-size:var(--text-sm);
    font-weight:var(--weight-medium); }
  .gm-rail a svg { width:18px; height:18px; flex:0 0 auto; color:var(--text-muted); }
  .gm-rail a:hover { background:var(--bg-surface-alt); color:var(--text-primary); }
  .gm-rail a.is-active { background:var(--color-blue-50); color:var(--color-blue-700); font-weight:var(--weight-bold); }
  .gm-rail a.is-active svg { color:var(--color-blue-600); }
  .gm-rail .gm-rail-count { margin-left:auto; font-size:var(--text-xs); font-weight:var(--weight-bold);
    color:var(--text-muted); }
  .gm-rail a.is-active .gm-rail-count { color:var(--color-blue-700); }
  .gm-rail-sep { height:1px; background:var(--border-default); margin:var(--space-2) var(--space-1); }
  .gm-rail-link { display:flex; align-items:center; gap:var(--space-3); padding:var(--space-2) var(--space-3);
    color:var(--text-secondary); text-decoration:none; font-size:var(--text-sm); font-weight:var(--weight-medium); border-radius:var(--radius-full); }
  .gm-rail-link:hover { background:var(--bg-surface-alt); color:var(--text-primary); }

  /* Botón para colapsar/expandir el rail. */
  .gm-rail-toggle { display:flex; align-items:center; justify-content:flex-end; gap:var(--space-2);
    background:none; border:0; cursor:pointer; color:var(--text-muted);
    padding:var(--space-1) var(--space-2); margin-bottom:2px; border-radius:var(--radius-full); }
  .gm-rail-toggle:hover { background:var(--bg-surface-alt); color:var(--text-primary); }
  .gm-rail-toggle svg { width:18px; height:18px; flex:0 0 auto; transition:transform .15s ease; }

  /* Estado colapsado: rail angosto, solo íconos (labels/contadores ocultos). */
  .gm-app.rail-collapsed { grid-template-columns:54px minmax(340px, 470px) 1fr; }
  .rail-collapsed .gm-rail a span, .rail-collapsed .gm-rail-link span,
  .rail-collapsed .gm-rail-count { display:none; }
  .rail-collapsed .gm-rail a, .rail-collapsed .gm-rail-link { justify-content:center; padding-left:0; padding-right:0; }
  .rail-collapsed .gm-rail-toggle { justify-content:center; }
  .rail-collapsed .gm-rail-toggle svg { transform:rotate(180deg); }

  /* ---- Buscador ---- */
  .gm-searchbar { padding:var(--space-2); border-bottom:1px solid var(--border-default); background:var(--bg-surface-alt); }
  .gm-search { display:flex; align-items:center; gap:var(--space-2); }
  .gm-search-field { position:relative; flex:1; }
  .gm-search-field svg { position:absolute; left:var(--space-3); top:50%; transform:translateY(-50%);
    width:16px; height:16px; color:var(--text-muted); pointer-events:none; }
  .gm-search input { width:100%; padding-left:calc(var(--space-3) + 22px); }
  .gm-search-clear { color:var(--text-muted); text-decoration:none; font-size:var(--text-sm); white-space:nowrap; }
  /* Recarga la vista (re-consulta la BD); NO sincroniza el buzón. */
  .gm-refresh { flex:0 0 auto; display:inline-flex; align-items:center; gap:6px; white-space:nowrap;
    border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-secondary);
    border-radius:var(--radius-md); padding:6px 10px; font-size:var(--text-sm); font-weight:var(--weight-medium);
    text-decoration:none; cursor:pointer; }
  .gm-refresh:hover { background:var(--bg-surface-alt); color:var(--text-primary); border-color:var(--border-strong); }
  .gm-refresh svg { width:15px; height:15px; }

  /* ---- Lista de conversaciones ---- */
  .gm-list { display:flex; flex-direction:column; }
  .gm-row { display:grid; grid-template-columns:34px 1fr; align-items:center; gap:var(--space-3);
    padding:10px var(--space-4); border-bottom:1px solid var(--border-default);
    text-decoration:none; color:var(--text-primary); cursor:pointer; }
  .gm-row:last-child { border-bottom:0; }
  .gm-row:hover { background:var(--bg-surface-alt); text-decoration:none; }
  .gm-row:hover .gm-sender, .gm-row:hover .gm-subject { text-decoration:none; }
  .gm-row.is-active { background:var(--color-blue-50); box-shadow:inset 3px 0 0 var(--color-blue-500); }
  .gm-row.is-breach { box-shadow:inset 3px 0 0 var(--color-critical-default); }
  .gm-row.is-breach.is-active { box-shadow:inset 3px 0 0 var(--color-critical-default); background:var(--color-blue-50); }

  /* Avatar con iniciales del solicitante; tono por estado. */
  .gm-av { width:34px; height:34px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
    font-size:var(--text-xs); font-weight:var(--weight-bold); line-height:1; flex:0 0 auto; }
  .gm-av.info { background:var(--color-blue-50); color:var(--color-blue-700); }
  .gm-av.success { background:var(--color-success-surface); color:var(--color-success-strong); }
  .gm-av.warning { background:var(--color-warning-surface); color:var(--color-warning-strong); }
  .gm-av.critical { background:var(--color-critical-surface); color:var(--color-critical-strong); }
  .gm-av.neutral { background:var(--color-neutral-100); color:var(--color-neutral-700); }

  .gm-body { min-width:0; display:flex; flex-direction:column; gap:2px; }
  /* Línea 1: remitente ......... [SLA] [adjunto] hora. */
  .gm-line1 { display:flex; align-items:center; gap:6px; }
  .gm-sender { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    font-size:var(--text-sm); color:var(--text-primary); }
  .gm-clip { flex:0 0 auto; width:14px; height:14px; color:var(--text-muted); }
  .gm-sla { flex:0 0 auto; font-size:10px; font-weight:var(--weight-bold); letter-spacing:.03em; color:var(--color-critical-strong);
    background:var(--color-critical-surface); border-radius:var(--radius-sm); padding:1px 5px; white-space:nowrap; }
  .gm-time { flex:0 0 auto; margin-left:2px; font-size:var(--text-xs); color:var(--text-muted); white-space:nowrap; }
  /* Línea 2: asunto ......... [Tomar]. */
  .gm-line2 { display:flex; align-items:center; gap:var(--space-2); min-width:0; }
  .gm-subject { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
    font-size:var(--text-sm); color:var(--text-secondary); }

  /* Sin leer -> solo el remitente en negrita (el asunto queda en peso normal). */
  .gm-row.is-unread .gm-sender { font-weight:var(--weight-bold); }

  /* Acción rápida (aparece al pasar el cursor). */
  .gm-claim { flex:0 0 auto; border:1px solid var(--border-default); background:var(--bg-surface); color:var(--action-primary);
    font-size:var(--text-xs); font-weight:var(--weight-semibold); border-radius:var(--radius-full); padding:2px 9px;
    cursor:pointer; opacity:0; transition:opacity .12s ease; white-space:nowrap; }
  .gm-row:hover .gm-claim { opacity:1; }
  .gm-claim:hover { border-color:var(--action-primary); background:var(--color-blue-50); }

  /* Autoarchivo: acción Verificar (siempre visible) y sello de verificado. */
  .gm-verify { flex:0 0 auto; border:1px solid var(--color-success-default); background:var(--color-success-surface);
    color:var(--color-success-strong); font-size:var(--text-xs); font-weight:var(--weight-semibold);
    border-radius:var(--radius-full); padding:2px 9px; cursor:pointer; white-space:nowrap; }
  .gm-verify:hover { filter:brightness(0.97); }
  .gm-verified { flex:0 0 auto; display:inline-flex; align-items:center; gap:4px; white-space:nowrap;
    font-size:var(--text-xs); font-weight:var(--weight-medium); color:var(--color-success-strong); }
  .gm-verified svg { width:13px; height:13px; }

  /* Pill de estado (mismo tono que el avatar) a la derecha del asunto. */
  .gm-status { flex:0 0 auto; font-size:10px; font-weight:var(--weight-semibold); letter-spacing:.02em;
    padding:1px 7px; border-radius:var(--radius-full); white-space:nowrap; }
  .gm-status.info { background:var(--color-blue-50); color:var(--color-blue-700); }
  .gm-status.success { background:var(--color-success-surface); color:var(--color-success-strong); }
  .gm-status.warning { background:var(--color-warning-surface); color:var(--color-warning-strong); }
  .gm-status.critical { background:var(--color-critical-surface); color:var(--color-critical-strong); }
  .gm-status.neutral { background:var(--color-neutral-100); color:var(--color-neutral-700); }

  .gm-empty { padding:var(--space-6); color:var(--text-muted); text-align:center; }

  /* ---- Panel de lectura ---- */
  .gm-reader { max-height:100%; overflow-y:auto; }
  .md-pane-msg, .gm-reader-empty { padding:var(--space-8) var(--space-6); text-align:center; color:var(--text-muted); }
  .gm-reader-empty svg { width:44px; height:44px; color:var(--border-strong); margin-bottom:var(--space-3); }
  .md-pane-head { padding:var(--space-4) var(--space-5); border-bottom:1px solid var(--border-default); background:var(--bg-surface-alt); position:sticky; top:0; z-index:2; }
  .md-pane-top { display:flex; align-items:flex-start; gap:var(--space-3); }
  .md-pane-subject { font-size:var(--text-xl); font-weight:var(--weight-semibold); color:var(--text-primary); margin:0; line-height:1.25; flex:1; }
  .md-pane-open { color:var(--text-muted); flex:0 0 auto; }
  .md-pane-open:hover { color:var(--action-primary); }
  .md-pane-open svg { width:18px; height:18px; }
  .md-pane-sub { display:flex; align-items:center; gap:var(--space-3); margin-top:var(--space-3); }
  .md-pane-name { font-size:var(--text-sm); font-weight:var(--weight-semibold); color:var(--text-primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .md-pane-email { font-size:var(--text-xs); color:var(--text-secondary); text-decoration:none; }
  .md-pane-email:hover { color:var(--action-primary); text-decoration:underline; }
  .md-pane-actions { display:flex; align-items:center; gap:var(--space-2); margin-top:var(--space-4); flex-wrap:wrap; }
  .md-pane-actions .btn { padding:var(--space-2) var(--space-3); font-size:var(--text-sm); }
  .md-pane-tag { font-size:var(--text-sm); color:var(--text-secondary); background:var(--bg-surface-alt); padding:var(--space-2) var(--space-3); border-radius:var(--radius-full); }
  .md-pane-thread { padding:var(--space-4) var(--space-5); background:var(--bg-page); }

  /* ---- Estilos de mensajes (compartidos con el detalle) ---- */
  .md-avatar { flex:0 0 auto; width:40px; height:40px; border-radius:var(--radius-full); display:inline-flex;
    align-items:center; justify-content:center; font-weight:var(--weight-bold); font-size:var(--text-sm); line-height:1; }
  .md-avatar.in { background:var(--color-blue-50); color:var(--color-blue-700); }
  .md-avatar.out { background:var(--color-success-surface); color:var(--color-success-strong); }
  .md-msg { border:1px solid var(--border-default); border-radius:var(--radius-lg); margin-bottom:var(--space-4);
    overflow:hidden; box-shadow:var(--shadow-xs); background:var(--bg-surface); }
  .md-msg.in { border-left:3px solid var(--color-blue-500); } .md-msg.out { border-left:3px solid var(--color-success-default); }
  .md-msg-head { display:flex; align-items:center; gap:var(--space-3); padding:var(--space-3) var(--space-4);
    border-bottom:1px solid var(--border-default); background:var(--bg-surface-alt); cursor:pointer; user-select:none; }
  .md-msg-who { min-width:0; flex:1; }
  .md-msg-name { font-size:var(--text-md); font-weight:var(--weight-semibold); color:var(--text-primary); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .md-msg-from { font-size:var(--text-xs); color:var(--text-muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .md-msg-side { display:flex; flex-direction:column; align-items:flex-end; gap:var(--space-1); flex:0 0 auto; }
  .md-msg-time { font-size:var(--text-xs); color:var(--text-secondary); white-space:nowrap; }
  .md-msg-toggle { flex:0 0 auto; display:inline-flex; color:var(--text-muted); transition:transform .15s ease; }
  .md-msg-toggle svg { width:18px; height:18px; }
  .md-msg.is-collapsed .md-msg-toggle { transform:rotate(-90deg); }
  .md-msg.is-collapsed .md-msg-collapsible { display:none; }
  /* Destinatarios reales del correo (Para / CC) sobre el cuerpo del mensaje:
     franja con fondo propio y fichas, para que los copiados se vean al leer. */
  .md-recipients { padding:var(--space-3) var(--space-4); background:var(--bg-page);
    border-bottom:1px solid var(--border-default); display:flex; flex-direction:column; gap:var(--space-2); }
  .md-recipients-row { display:flex; gap:var(--space-3); align-items:flex-start; }
  .md-recipients-label { flex:0 0 auto; min-width:56px; padding-top:3px; font-size:var(--text-xs);
    text-transform:uppercase; letter-spacing:.04em; font-weight:var(--weight-semibold); color:var(--text-muted); }
  .md-recipients-row.is-cc .md-recipients-label { color:var(--color-blue-700); }
  .md-recipients-count { display:inline-flex; align-items:center; justify-content:center; min-width:18px;
    height:18px; margin-left:4px; padding:0 5px; border-radius:var(--radius-full);
    background:var(--color-blue-100); color:var(--color-blue-700); font-size:11px; letter-spacing:0; }
  .md-recipients-list { display:flex; flex-wrap:wrap; gap:var(--space-1) var(--space-2); min-width:0; }
  .md-addr { display:inline-flex; align-items:center; max-width:100%; font-size:var(--text-xs); line-height:1.4;
    padding:3px var(--space-2); border-radius:var(--radius-full); border:1px solid var(--border-default);
    background:var(--bg-surface); color:var(--text-secondary); overflow-wrap:anywhere; }
  .md-recipients-row.is-cc .md-addr { border-color:var(--color-blue-200); background:var(--color-blue-50);
    color:var(--color-blue-700); font-weight:var(--weight-medium); }
  .md-cc-flag { display:inline-flex; align-items:center; gap:4px; padding:1px var(--space-2);
    border:1px solid var(--color-blue-200); border-radius:var(--radius-full); background:var(--color-blue-50);
    color:var(--color-blue-700); font-size:var(--text-xs); font-weight:var(--weight-medium); white-space:nowrap; }
  .md-cc-flag svg { width:13px; height:13px; }
  .md-msg-preview { display:none; padding:var(--space-2) var(--space-4) var(--space-3); color:var(--text-secondary); font-size:var(--text-sm); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .md-msg.is-collapsed .md-msg-preview { display:block; }
  .md-msg-body-frame { width:100%; border:0; min-height:200px; background:#fff; display:block; }
  .md-msg-pre { white-space:pre-wrap; word-break:break-word; padding:var(--space-4); margin:0; font-family:inherit; font-size:var(--text-sm); color:var(--text-primary); line-height:1.55; }
  .md-attachments { display:flex; flex-wrap:wrap; gap:var(--space-2); padding:var(--space-3) var(--space-4); border-bottom:1px solid var(--border-default); background:var(--bg-page); }
  .md-chip { display:inline-flex; align-items:center; gap:var(--space-2); max-width:100%; padding:var(--space-2) var(--space-3); background:var(--bg-surface); border:1px solid var(--border-default); border-radius:var(--radius-md); text-decoration:none; color:var(--text-primary); font-size:var(--text-sm); }
  .md-chip:hover { border-color:var(--action-primary); }
  .md-chip svg { width:16px; height:16px; color:var(--text-muted); flex:0 0 auto; }
  .md-chip-name { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:220px; font-weight:var(--weight-medium); }
  .md-chip-size { color:var(--text-muted); font-size:var(--text-xs); flex:0 0 auto; }

  .md-hl { background:#fff3bf; border-radius:2px; padding:0 1px; }

  @media (max-width:1180px) {
    .gm-app { grid-template-columns:170px 1fr; }
    .gm-app.rail-collapsed { grid-template-columns:54px 1fr; }
    .gm-reader { display:none; }
  }
  @media (max-width:760px) {
    .gm-app, .gm-app.rail-collapsed { grid-template-columns:1fr; }
    .gm-rail { flex-direction:row; overflow-x:auto; }
    .gm-rail-toggle { display:none; }
    .rail-collapsed .gm-rail a span, .rail-collapsed .gm-rail-link span,
    .rail-collapsed .gm-rail-count { display:inline; }
    .rail-collapsed .gm-rail a, .rail-collapsed .gm-rail-link { justify-content:flex-start; }
  }
</style>

<div class="page-header">
  <div class="page-header-content">
    <h1 class="page-title">Despacho de correo</h1>
    <p class="page-subtitle">
      <?php if ($q !== ''): ?><?= (int) ($total ?? 0) ?> resultado(s) para «<?= esc($q) ?>».
      <?php else: ?>Buzón compartido · <?= (int) ($counts['unassigned'] ?? 0) ?> sin asignar.<?php endif; ?>
    </p>
  </div>
</div>

<div class="gm-app">
  <!-- ================= Rail izquierdo ================= -->
  <nav class="gm-col gm-card gm-rail" aria-label="Filtros">
    <button type="button" class="gm-rail-toggle" id="gm-rail-toggle" aria-label="Colapsar barra lateral" title="Colapsar barra lateral">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m11 17-5-5 5-5"/><path d="m18 17-5-5 5-5"/></svg>
    </button>
    <?php foreach ($tabs as $key => [$label, $icon]): ?>
      <a href="<?= esc($tabHref($key), 'attr') ?>" class="<?= $filter === $key ? 'is-active' : '' ?>" title="<?= esc($label, 'attr') ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= $icon ?>"/></svg>
        <span><?= esc($label) ?></span>
        <span class="gm-rail-count"><?= (int) ($counts[$key] ?? 0) ?></span>
      </a>
    <?php endforeach; ?>
    <div class="gm-rail-sep"></div>
    <a class="gm-rail-link" href="<?= base_url('dispatch/my-metrics') ?>" title="Mis métricas">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      <span>Mis métricas</span>
    </a>
    <a class="gm-rail-link" href="<?= base_url('dispatch/signature') ?>" title="Mi firma">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19l7-7 3 3-7 7-3 0z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
      <span>Mi firma</span>
    </a>
    <?php if (! empty($canDispatch)): ?>
      <a class="gm-rail-link" href="<?= base_url('dispatch/metrics') ?>" title="Métricas del equipo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3v18h18 M18 17V9 M13 17V5 M8 17v-3"/></svg>
        <span>Métricas del equipo</span>
      </a>
    <?php endif; ?>
  </nav>

  <!-- ================= Lista ================= -->
  <div class="gm-col gm-card gm-listcard">
    <div class="gm-searchbar">
      <form class="gm-search" method="get" action="<?= base_url('dispatch') ?>" role="search">
        <input type="hidden" name="filter" value="<?= esc($filter, 'attr') ?>">
        <div class="gm-search-field">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input type="search" name="q" class="input" value="<?= esc($q, 'attr') ?>" placeholder="Buscar por asunto, solicitante, correo o folio…" autocomplete="off" spellcheck="false">
        </div>
        <?php if ($q !== ''): ?><a class="gm-search-clear" href="<?= base_url('dispatch') ?>?filter=<?= esc($filter, 'attr') ?>">Limpiar</a><?php endif; ?>
        <?php $reloadHref = base_url('dispatch') . '?' . http_build_query(array_filter(['filter' => $filter, 'q' => $q])); ?>
        <a class="gm-refresh" href="<?= esc($reloadHref, 'attr') ?>" title="Recargar la lista (no consulta el buzón)">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
          <span>Refrescar</span>
        </a>
      </form>
    </div>

    <?php if (empty($conversations)): ?>
      <div class="gm-empty"><?= $q !== '' ? 'Sin resultados para «' . esc($q) . '».' : 'No hay conversaciones en esta vista.' ?></div>
    <?php else: ?>
      <div class="gm-list">
        <?php foreach ($conversations as $c):
            $isAuto  = $c['status'] === 'autoarchivo';
            $isAutogen = $c['status'] === 'autogenerado';
            $isUnassigned = $c['agent_id'] === null && ! in_array($c['status'], ['cerrada', 'autoarchivo', 'autogenerado'], true);
            $breach  = $isUnassigned && $slaUnassigned > 0 && $minsOf($c['received_at']) > $slaUnassigned;
            $tone    = $statusTones[$c['status']] ?? 'neutral';
            $unread  = in_array($c['status'], ['nueva', 'esperando_agente'], true);
            $cls = 'gm-row' . ($unread ? ' is-unread' : '') . ($breach ? ' is-breach' : '');
        ?>
          <a class="<?= $cls ?>" href="<?= route_to('dispatch.show', $c['id']) ?>" data-id="<?= (int) $c['id'] ?>"
             data-preview="<?= esc(base_url('dispatch/' . (int) $c['id'] . '/preview'), 'attr') ?>">
            <span class="gm-av <?= esc($tone) ?>" title="<?= esc($statusLabels[$c['status']] ?? $c['status'], 'attr') ?>"><?= esc($initials($c['requester_name'] ?? '', $c['requester_email'] ?? '')) ?></span>
            <span class="gm-body">
              <span class="gm-line1">
                <span class="gm-sender">
                  <?php if (! empty($c['outbound_only'])): ?>
                    <span class="text-muted" style="font-weight:var(--weight-regular);">Para:</span>
                  <?php endif; ?>
                  <?= $hl($c['requester_name'] ?: ($c['requester_email'] ?: 'Sin remitente')) ?>
                </span>
                <?php if (! empty($c['has_attachments'])): ?>
                  <svg class="gm-clip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                <?php endif; ?>
                <?php if ($breach): ?><span class="gm-sla" title="SLA de asignación excedido">SLA</span><?php endif; ?>
                <span class="gm-time"><?= esc($gmailTime($c['last_activity_at'] ?? $c['received_at'])) ?></span>
              </span>
              <span class="gm-line2">
                <span class="gm-subject"><?= $hl($c['subject'] ?: '(sin asunto)') ?></span>
                <?php if ($isAutogen): ?>
                  <?php $agState = (string) ($c['autogen_state'] ?? ''); ?>
                  <?php if (! empty($c['auto_ticket_id'])): ?><span class="gm-status success">#<?= (int) $c['auto_ticket_id'] ?></span><?php endif; ?>
                  <?php if ($agState === 'created' && ! empty($c['verified_at'])): ?>
                    <span class="gm-verified" title="Verificado por <?= esc($c['verified_name'] ?? 'agente', 'attr') ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>Verificado
                    </span>
                  <?php elseif ($agState === 'created'): ?>
                    <button type="button" class="gm-verify" data-agverify="<?= (int) $c['id'] ?>">Verificar</button>
                  <?php elseif ($agState === 'review'): ?>
                    <span class="gm-status warning">Revisión</span>
                  <?php elseif ($agState === 'failed'): ?>
                    <span class="gm-status critical">Error</span>
                    <button type="button" class="gm-verify" data-agretry="<?= (int) $c['id'] ?>">Reintentar</button>
                  <?php else: ?>
                    <span class="gm-status info">En cola</span>
                  <?php endif; ?>
                <?php elseif ($isAuto): ?>
                  <?php if (! empty($c['verified_at'])): ?>
                    <span class="gm-verified" title="Verificado por <?= esc($c['verified_name'] ?? 'agente', 'attr') ?>">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>Verificado
                    </span>
                  <?php else: ?>
                    <button type="button" class="gm-verify" data-verify="<?= (int) $c['id'] ?>">Verificar</button>
                  <?php endif; ?>
                <?php elseif ($isUnassigned): ?>
                  <button type="button" class="gm-claim" data-claim="<?= (int) $c['id'] ?>">Tomar</button>
                <?php else: ?>
                  <span class="gm-status <?= esc($tone) ?>"><?= esc($statusLabels[$c['status']] ?? $c['status']) ?></span>
                <?php endif; ?>
              </span>
            </span>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if (! empty($pager) && $pager->getPageCount('default') > 1): ?>
        <div class="card-footer" style="display:flex; align-items:center; justify-content:center; gap:var(--space-2); background:var(--bg-surface-alt);">
          <?= $pager->only(['filter', 'q'])->links('default', 'pagination') ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- ================= Panel de lectura ================= -->
  <div class="gm-col gm-card gm-reader" id="gm-reader">
    <div class="gm-reader-empty">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
      <p>Selecciona una conversación para leerla aquí.</p>
    </div>
  </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
var MD_CSRF = { header: '<?= csrf_header() ?>', hash: '<?= csrf_hash() ?>' };

// Ajuste de altura del iframe del correo (usado por el panel de lectura).
// El iframe toma la altura de su contenido; el panel de lectura hace el scroll.
function mdFitFrame(f) {
  try {
    var d = f.contentWindow.document;
    var fit = function () {
      var h = Math.max(d.body ? d.body.scrollHeight : 0, d.documentElement ? d.documentElement.scrollHeight : 0);
      if (h > 0) { f.style.height = (h + 24) + 'px'; }
    };
    fit();
    Array.prototype.forEach.call(d.images || [], function (img) { if (!img.complete) { img.addEventListener('load', fit); } });
    setTimeout(fit, 300);
  } catch (e) {}
}

(function () {
  var reader = document.getElementById('gm-reader');
  var rows   = Array.prototype.slice.call(document.querySelectorAll('.gm-row'));

  // Altura fija de la app = viewport menos su posición; cada columna scrollea
  // internamente y la página no. Se recalcula al redimensionar.
  var app = document.querySelector('.gm-app');

  // Colapsar/expandir el rail izquierdo (persistente por navegador).
  var railToggle = document.getElementById('gm-rail-toggle');
  var RAIL_KEY = 'md_rail_collapsed';
  try { if (localStorage.getItem(RAIL_KEY) === '1' && app) app.classList.add('rail-collapsed'); } catch (e) {}
  function syncRailToggle() {
    if (!railToggle || !app) return;
    var collapsed = app.classList.contains('rail-collapsed');
    var label = collapsed ? 'Expandir barra lateral' : 'Colapsar barra lateral';
    railToggle.setAttribute('aria-label', label);
    railToggle.setAttribute('title', label);
  }
  syncRailToggle();
  if (railToggle && app) {
    railToggle.addEventListener('click', function () {
      app.classList.toggle('rail-collapsed');
      try { localStorage.setItem(RAIL_KEY, app.classList.contains('rail-collapsed') ? '1' : '0'); } catch (e) {}
      syncRailToggle();
    });
  }

  function sizeApp() {
    if (!app) return;
    var top = app.getBoundingClientRect().top + window.scrollY;
    app.style.height = Math.max(420, window.innerHeight - top - 16) + 'px';
  }
  sizeApp();
  window.addEventListener('resize', sizeApp);

  function markActive(id) {
    rows.forEach(function (r) { r.classList.toggle('is-active', r.dataset.id === String(id)); });
  }

  function loadPane(row) {
    if (!reader) return;
    markActive(row.dataset.id);
    reader.innerHTML = '<div class="md-pane-msg">Cargando…</div>';
    fetch(row.dataset.preview, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.text(); })
      .then(function (html) { reader.innerHTML = html; if (history.replaceState) history.replaceState(null, '', '#c' + row.dataset.id); })
      .catch(function () { reader.innerHTML = '<div class="md-pane-msg">No se pudo cargar la conversación.</div>'; });
  }

  // Abrir en el panel (si está visible); si no, seguir el enlace al detalle.
  rows.forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('.gm-claim') || e.target.closest('.gm-verify')) return; // acciones se manejan aparte
      if (!reader || getComputedStyle(reader).display === 'none') return; // móvil: ir al detalle
      e.preventDefault();
      loadPane(row);
    });
  });

  // Delegación: colapsar/expandir mensajes dentro del panel.
  if (reader) {
    reader.addEventListener('click', function (e) {
      var head = e.target.closest('.md-msg-head');
      if (!head) return;
      var msg = head.closest('.md-msg');
      var collapsed = msg.classList.toggle('is-collapsed');
      head.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      if (!collapsed) { var f = msg.querySelector('.md-msg-body-frame'); if (f) setTimeout(function () { mdFitFrame(f); }, 30); }
    });
  }

  // Acción rápida (POST vía AJAX) para "Tomar", tanto en la fila como en el panel.
  function quickPost(url, body) {
    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    headers[MD_CSRF.header] = MD_CSRF.hash;
    return fetch(url, { method: 'POST', headers: headers, body: body || new FormData() }).then(function (r) { return r.json(); });
  }

  document.addEventListener('click', function (e) {
    var claim = e.target.closest('[data-claim]');
    var paneClaim = e.target.closest('.md-qa[data-action="claim"]');
    var id = claim ? claim.dataset.claim : (paneClaim ? paneClaim.dataset.id : null);
    if (!id) return;
    e.preventDefault();
    e.stopPropagation();
    var btn = claim || paneClaim; btn.disabled = true;
    quickPost('<?= base_url('dispatch') ?>/' + id + '/claim')
      .then(function () { location.hash = '#c' + id; location.reload(); })
      .catch(function () { btn.disabled = false; });
  });

  // Autoarchivo: Verificar / Mover a la bandeja (fila y panel de lectura).
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-verify], [data-toinbox]');
    if (!el) return;
    e.preventDefault();
    e.stopPropagation();
    var verify = el.hasAttribute('data-verify');
    var id  = verify ? el.dataset.verify : el.dataset.toinbox;
    var act = verify ? 'verify' : 'to-inbox';
    el.disabled = true;
    quickPost('<?= base_url('dispatch') ?>/' + id + '/' + act)
      .then(function () { if (verify) location.hash = '#c' + id; location.reload(); })
      .catch(function () { el.disabled = false; });
  });

  // Autogestión: verificar / reintentar (fila y panel de lectura).
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-agverify], [data-agretry]');
    if (!el) return;
    e.preventDefault();
    e.stopPropagation();
    var verify = el.hasAttribute('data-agverify');
    var id  = verify ? el.dataset.agverify : el.dataset.agretry;
    var act = verify ? 'autogen/verify' : 'autogen/retry';
    el.disabled = true;
    quickPost('<?= base_url('dispatch') ?>/' + id + '/' + act)
      .then(function () { location.reload(); })
      .catch(function () { el.disabled = false; });
  });

  // Cambio de estado desde el panel.
  document.addEventListener('change', function (e) {
    var sel = e.target.closest('.md-qa-status');
    if (!sel) return;
    var fd = new FormData(); fd.append('status', sel.value);
    quickPost('<?= base_url('dispatch') ?>/' + sel.dataset.id + '/status', fd)
      .then(function () { location.hash = '#c' + sel.dataset.id; location.reload(); });
  });

  // Al cargar: abrir la conversación del hash (#c123) si existe.
  var m = (location.hash || '').match(/^#c(\d+)$/);
  if (m && reader && getComputedStyle(reader).display !== 'none') {
    var target = rows.find(function (r) { return r.dataset.id === m[1]; });
    if (target) loadPane(target);
  }
})();
</script>
<?= $this->endSection() ?>
