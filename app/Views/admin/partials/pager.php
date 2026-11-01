<?php if (isset($pager) && $pager->getPageCount() > 1) : ?>
    <?php $previous = $pager->getPreviousPageURI(); ?>
    <?php $next = $pager->getNextPageURI(); ?>
    <nav class="pager" aria-label="Pages">
        <?php if (is_string($previous)) : ?>
            <a href="<?= esc($previous, 'attr') ?>">Previous</a>
        <?php endif ?>
        <span>Page <?= esc((string) $pager->getCurrentPage()) ?> of <?= esc((string) $pager->getPageCount()) ?></span>
        <?php if (is_string($next)) : ?>
            <a href="<?= esc($next, 'attr') ?>">Next</a>
        <?php endif ?>
    </nav>
<?php endif ?>
