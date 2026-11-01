<?= $this->extend('admin/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/admin/content.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $id = (int) ($block['id'] ?? 0); ?>
<div class="toolbar">
    <a class="btn btn-primary" href="<?= site_url('admin/blocks/' . $id . '/edit') ?>">Edit</a>
    <a class="btn" href="<?= site_url('admin/blocks') ?>">Back to posts</a>
</div>
<section class="card panel">
    <p class="meta">
        <?= admin_badge((string) ($block['status'] ?? '')) ?>
        <span><?= esc($categoryName !== '' ? $categoryName : 'No category') ?></span>
        <span>Slug <?= esc((string) ($block['slug'] ?? '')) ?></span>
    </p>
    <?php $publicUrl = service('blockSeo')->publicUrl((string) ($block['slug'] ?? '')); ?>
    <?php if ((string) ($block['status'] ?? '') === 'published' && $publicUrl !== '') : ?>
        <p class="meta">Public URL <a href="<?= esc($publicUrl, 'attr') ?>"><?= esc($publicUrl) ?></a></p>
    <?php else : ?>
        <p class="meta">This post is not on the public blog.</p>
    <?php endif ?>
    <p class="meta">Created <?= esc(admin_datetime($block['created_at'] ?? null)) ?>. Updated <?= esc(admin_datetime($block['updated_at'] ?? null)) ?>.</p>
    <?php $html = block_html((string) ($block['content'] ?? '')); ?>
    <?php if ($html === '') : ?>
        <?= admin_empty('No content yet.') ?>
    <?php else : ?>
        <div class="block-content"><?= $html ?></div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
