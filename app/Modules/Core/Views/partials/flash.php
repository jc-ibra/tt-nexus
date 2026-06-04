<?php
$error   = session()->getFlashdata('error');
$success = session()->getFlashdata('success');
$warning = session()->getFlashdata('warning');
$info    = session()->getFlashdata('info');
?>

<?php if ($error): ?>
<div class="banner banner-critical" role="alert" style="margin-bottom: var(--space-4);">
  <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
  </svg>
  <div class="banner-body"><?= esc($error) ?></div>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="banner banner-success" role="status" style="margin-bottom: var(--space-4);">
  <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <polyline points="20 6 9 17 4 12"/>
  </svg>
  <div class="banner-body"><?= esc($success) ?></div>
</div>
<?php endif; ?>

<?php if ($warning): ?>
<div class="banner banner-warning" role="alert" style="margin-bottom: var(--space-4);">
  <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
  </svg>
  <div class="banner-body"><?= esc($warning) ?></div>
</div>
<?php endif; ?>

<?php if ($info): ?>
<div class="banner banner-info" role="status" style="margin-bottom: var(--space-4);">
  <svg class="banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
  </svg>
  <div class="banner-body"><?= esc($info) ?></div>
</div>
<?php endif; ?>
