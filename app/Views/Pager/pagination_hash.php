<?php
/**
 * Same markup as App\Views\Pager\pagination, but every link keeps a fixed
 * URL fragment (e.g. "#estado"). Needed for a pager that lives inside a
 * hash-based tab panel: the tabs' own JS picks the active panel from
 * location.hash, so a plain paginate/filter link would silently reload back
 * onto the first tab. Pass the fragment via $pagerHash (no leading "#");
 * defaults to "estado" since that's the only caller today.
 */
$hash = '#' . ltrim((string) ($pagerHash ?? 'estado'), '#');
$pager->setSurroundCount(2);
?>

<div class="pager-bar">

  <span class="pager-summary text-sm text-muted">
    Mostrando <?= $pager->getPerPageStart() ?>-<?= $pager->getPerPageEnd() ?>
    de <?= number_format((int) $pager->getTotal()) ?>
    · Página <?= $pager->getCurrentPageNumber() ?> de <?= $pager->getPageCount() ?>
  </span>

  <nav aria-label="Paginación">
    <ul class="pagination">

      <?php if ($pager->hasPreviousPage()): ?>
        <li>
          <a href="<?= $pager->getFirst() . $hash ?>" class="pagination-item" aria-label="Primera página">«</a>
        </li>
        <li>
          <a href="<?= $pager->getPreviousPage() . $hash ?>" class="pagination-item" aria-label="Página anterior">‹</a>
        </li>
      <?php else: ?>
        <li><span class="pagination-item is-disabled" aria-hidden="true">«</span></li>
        <li><span class="pagination-item is-disabled" aria-hidden="true">‹</span></li>
      <?php endif; ?>

      <?php foreach ($pager->links() as $link): ?>
        <li>
          <?php if ($link['active']): ?>
            <span class="pagination-item is-active" aria-current="page"><?= $link['title'] ?></span>
          <?php else: ?>
            <a href="<?= $link['uri'] . $hash ?>" class="pagination-item"><?= $link['title'] ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>

      <?php if ($pager->hasNextPage()): ?>
        <li>
          <a href="<?= $pager->getNextPage() . $hash ?>" class="pagination-item" aria-label="Página siguiente">›</a>
        </li>
        <li>
          <a href="<?= $pager->getLast() . $hash ?>" class="pagination-item" aria-label="Última página">»</a>
        </li>
      <?php else: ?>
        <li><span class="pagination-item is-disabled" aria-hidden="true">›</span></li>
        <li><span class="pagination-item is-disabled" aria-hidden="true">»</span></li>
      <?php endif; ?>

    </ul>
  </nav>

</div>
