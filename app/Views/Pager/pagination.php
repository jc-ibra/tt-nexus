<?php $pager->setSurroundCount(2); ?>

<nav aria-label="Paginación">
  <ul class="pagination">

    <?php if ($pager->hasPreviousPage()): ?>
      <li>
        <a href="<?= $pager->getFirst() ?>" class="pagination-item" aria-label="Primera página">«</a>
      </li>
      <li>
        <a href="<?= $pager->getPreviousPage() ?>" class="pagination-item" aria-label="Página anterior">‹</a>
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
          <a href="<?= $link['uri'] ?>" class="pagination-item"><?= $link['title'] ?></a>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>

    <?php if ($pager->hasNextPage()): ?>
      <li>
        <a href="<?= $pager->getNextPage() ?>" class="pagination-item" aria-label="Página siguiente">›</a>
      </li>
      <li>
        <a href="<?= $pager->getLast() ?>" class="pagination-item" aria-label="Última página">»</a>
      </li>
    <?php else: ?>
      <li><span class="pagination-item is-disabled" aria-hidden="true">›</span></li>
      <li><span class="pagination-item is-disabled" aria-hidden="true">»</span></li>
    <?php endif; ?>

  </ul>
</nav>
