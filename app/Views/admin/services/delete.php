<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<?php $id = (int) ($service['id'] ?? 0); ?>
<?php $title = (string) ($service['title'] ?? ''); ?>
<section class="card panel form-card">
    <p>Delete the service “<?= esc($title) ?>”? This cannot be undone.</p>
    <form method="post" action="<?= site_url('admin/services/' . $id . '/delete') ?>">
        <?= csrf_field() ?>
        <div class="form-actions">
            <button class="btn btn-danger" type="submit">Delete service</button>
            <a href="<?= site_url('admin/services') ?>">Cancel</a>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
