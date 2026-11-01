<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<?php $id = (int) ($block['id'] ?? 0); ?>
<?php $title = (string) ($block['title'] ?? ''); ?>
<section class="card panel form-card">
    <p>Delete the post “<?= esc($title) ?>”? This cannot be undone.</p>
    <form method="post" action="<?= site_url('admin/blocks/' . $id . '/delete') ?>">
        <?= csrf_field() ?>
        <div class="form-actions">
            <button class="btn btn-danger" type="submit">Delete post</button>
            <a href="<?= site_url('admin/blocks') ?>">Cancel</a>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
