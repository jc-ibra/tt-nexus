<?php
/**
 * Para/CC address chips for one message, rendered into the body slot (see
 * _message_row.php) alongside the body itself — moved out of the always-sent
 * metadata row because a message can carry 15-20 recipients, and one <button>
 * per address per (collapsed) message is what pushed a 25-message page past
 * budget. In show.php an address is clickable (adds it to the reply's CC); in
 * the read-only preview pane it's a plain span.
 *
 * @var array $toAddrs
 * @var array $ccAddrs
 * @var bool  $pane
 */
?>
<div class="md-recipients">
  <?php foreach (['Para' => $toAddrs, 'CC' => $ccAddrs] as $label => $addrs): ?>
    <?php if ($addrs !== []): ?>
      <div class="md-recipients-row<?= $label === 'CC' ? ' is-cc' : '' ?>">
        <span class="md-recipients-label"><?= $label ?><?php if (count($addrs) > 1): ?><span class="md-recipients-count"><?= count($addrs) ?></span><?php endif; ?></span>
        <span class="md-recipients-list">
          <?php foreach ($addrs as $addr): ?>
            <?php if ($pane): ?>
              <span class="md-addr"><?= esc($addr) ?></span>
            <?php else: ?>
              <button type="button" class="md-addr" data-addr="<?= esc($addr, 'attr') ?>"
                      title="Agregar a copia de la respuesta"><?= esc($addr) ?></button>
            <?php endif; ?>
          <?php endforeach; ?>
        </span>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
