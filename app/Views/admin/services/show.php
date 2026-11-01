<?= $this->extend('admin/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/admin/content.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $id = (int) ($service['id'] ?? 0); ?>
<div class="toolbar">
    <a class="btn btn-primary" href="<?= site_url('admin/services/' . $id . '/edit') ?>">Edit</a>
    <a class="btn" href="<?= site_url('admin/services') ?>">Back to services</a>
</div>
<section class="card panel">
    <p class="meta">
        <?= admin_badge((string) ($service['status'] ?? '')) ?>
        <span>Order <?= esc((string) ($service['sort_order'] ?? 0)) ?></span>
        <span>Slug <?= esc((string) ($service['slug'] ?? '')) ?></span>
    </p>
    <?php if ((string) ($service['status'] ?? '') === 'published') : ?>
        <p class="meta">Published services are included in the website API.</p>
    <?php else : ?>
        <p class="meta">This service is not on the website.</p>
    <?php endif ?>
    <p class="meta">Created <?= esc(admin_datetime($service['created_at'] ?? null)) ?>. Updated <?= esc(admin_datetime($service['updated_at'] ?? null)) ?>.</p>
    <?php $excerpt = trim((string) ($service['excerpt'] ?? '')); ?>
    <?php if ($excerpt !== '') : ?>
        <p><?= esc($excerpt) ?></p>
    <?php endif ?>
    <?php $html = block_html((string) ($service['content'] ?? '')); ?>
    <?php if ($html === '') : ?>
        <?= admin_empty('No page content yet. The existing website page stays in place until you add it.') ?>
    <?php else : ?>
        <div class="block-content"><?= $html ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
