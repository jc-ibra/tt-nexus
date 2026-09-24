<?php
/**
 * Downloadable-attachment chips for one message's body: attachments not
 * embedded in the HTML (or past AttachmentService::prepareBody()'s cap).
 * Rendered server-side by Dispatch::messageBody() into `files_html`, so the
 * client-side loader never has to duplicate this markup in JS.
 *
 * @var array $files
 */
$fmtSize = static function (int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024) . ' KB';
    return $bytes . ' B';
};
$attUrl = static fn (int $id): string => base_url('dispatch/attachments/' . $id);
?>
<div class="md-attachments">
  <?php foreach ($files as $a): ?>
    <a class="md-chip" href="<?= esc($attUrl((int) $a['id']), 'attr') ?>" target="_blank" rel="noopener"
       <?= empty($a['storage_path']) ? 'aria-disabled="true" style="opacity:.55; pointer-events:none;"' : '' ?>>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
      <span class="md-chip-name"><?= esc($a['filename']) ?></span>
      <span class="md-chip-size"><?= esc($fmtSize((int) ($a['size_bytes'] ?? 0))) ?></span>
    </a>
  <?php endforeach; ?>
</div>
