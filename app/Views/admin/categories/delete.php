<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<?php $id = (int) ($category['id'] ?? 0); ?>
<?php $name = (string) ($category['name'] ?? ''); ?>
<section class="card panel form-card">
    <?php if ($inUse) : ?>
        <?= admin_empty($message) ?>
        <p class="panel-note"><a href="<?= site_url('admin/categories') ?>">Back to categories</a></p>
    <?php else : ?>
        <p>Delete the category “<?= esc($name) ?>”? This cannot be undone.</p>
        <form method="post" action="<?= site_url('admin/categories/' . $id . '/delete') ?>">
            <?= csrf_field() ?>
            <div class="form-actions">
                <button class="btn btn-danger" type="submit">Delete category</button>
                <a href="<?= site_url('admin/categories') ?>">Cancel</a>
            </div>
        </form>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
