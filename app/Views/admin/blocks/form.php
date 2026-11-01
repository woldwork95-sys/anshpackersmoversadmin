<?= $this->extend('admin/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/ckeditor5/ckeditor5.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/admin/content.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$id         = (int) ($block['id'] ?? 0);
$editing    = $id > 0;
$action     = $editing ? site_url('admin/blocks/' . $id) : site_url('admin/blocks');
$status       = admin_old('status', (string) ($block['status'] ?? 'draft'));
$categoryId   = admin_old('category_id', (string) ($block['category_id'] ?? ''));
$publish      = $editing && $status === 'published' ? 'Update' : 'Publish';
$metaTitle    = admin_old('meta_title', (string) ($block['meta_title'] ?? ''));
$metaText     = admin_old('meta_description', (string) ($block['meta_description'] ?? ''));
$ogTitle      = admin_old('og_title', (string) ($block['og_title'] ?? ''));
$ogText       = admin_old('og_description', (string) ($block['og_description'] ?? ''));
$robotsIndex  = admin_old('robots_index', (string) ($block['robots_index'] ?? 'index'));
$robotsFollow = admin_old('robots_follow', (string) ($block['robots_follow'] ?? 'follow'));
if (! in_array($robotsIndex, ['index', 'noindex'], true)) {
    $robotsIndex = 'index';
}
if (! in_array($robotsFollow, ['follow', 'nofollow'], true)) {
    $robotsFollow = 'follow';
}
$publicUrl  = $editing ? service('blockSeo')->publicUrl((string) ($block['slug'] ?? '')) : '';
$ogImage    = (string) ($block['og_image'] ?? '');
$ogImageUrl = $ogImage !== '' ? service('blockSeo')->imageUrl($ogImage) : '';
?>
<section class="card panel form-card form-card-editor">
    <?php if ($categories === []) : ?>
        <p class="hint">Create a category before you save a post. <a href="<?= site_url('admin/categories/create') ?>">New category</a></p>
    <?php endif ?>
    <?php if ($editing) : ?>
        <p class="meta">Created <?= esc(admin_datetime($block['created_at'] ?? null)) ?>. Updated <?= esc(admin_datetime($block['updated_at'] ?? null)) ?>.</p>
    <?php endif ?>
    <p class="hint">Fill in the title, category, and post. Save a draft to keep it private, or publish it on the blog.</p>
    <form id="block-form" method="post" action="<?= esc($action, 'attr') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <button class="visually-hidden" type="submit">Save</button>
        <div class="editor-layout">
        <div class="editor-main">
        <label for="block-title">Title</label>
        <input id="block-title" name="title" type="text" maxlength="191" required value="<?= esc(admin_old('title', (string) ($block['title'] ?? ''))) ?>">

        <label for="block-category-id">Category</label>
        <select id="block-category-id" name="category_id" required>
            <option value="">Choose a category</option>
            <?php foreach ($categories as $category) : ?>
                <?php $optionId = (string) ((int) ($category['id'] ?? 0)); ?>
                <?php $inactive = (string) ($category['status'] ?? '') === 'inactive'; ?>
                <option value="<?= esc($optionId, 'attr') ?>"<?= $categoryId === $optionId ? ' selected' : '' ?>><?= esc((string) ($category['name'] ?? '')) ?><?= $inactive ? ' (inactive)' : '' ?></option>
            <?php endforeach ?>
        </select>
        <p class="hint">Shown on the blog next to the post. <a href="<?= site_url('admin/categories/create') ?>">Add a category</a></p>

        <label for="block-author">Author</label>
        <input id="block-author" name="author_name" type="text" maxlength="80" value="<?= esc(admin_old('author_name', (string) ($block['author_name'] ?? 'Admin'))) ?>">

        <label for="block-excerpt">Short summary</label>
        <textarea id="block-excerpt" name="excerpt" maxlength="300"><?= esc(admin_old('excerpt', (string) ($block['excerpt'] ?? ''))) ?></textarea>
        <p class="hint">One or two lines for the blog card. Leave blank to use the start of the post.</p>

        <label for="og-image">Cover photo</label>
        <input id="og-image" name="og_image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
        <p class="hint">JPG, PNG, GIF, or WebP up to 2 MB. This is the photo on the blog card. Leave empty to keep the current photo.</p>
        <?php if ($ogImageUrl !== '') : ?>
            <p class="hint"><img src="<?= esc($ogImageUrl, 'attr') ?>" alt="" width="120"></p>
            <label class="check-label"><input type="checkbox" name="remove_og_image" value="1"> Remove the current photo</label>
        <?php endif ?>

        <label for="block-content">Post</label>
        <textarea
            id="block-content"
            name="content"
            maxlength="<?= \App\Libraries\BlockHtml::MAX_LENGTH ?>"
            data-block-editor
            data-upload-url="<?= esc(site_url('admin/blocks/images'), 'attr') ?>"
            data-csrf-name="csrf_test_name"
        ><?= esc(admin_old('content', (string) ($block['content'] ?? ''))) ?></textarea>
        <p class="hint">Use the toolbar for headings, bold, lists, links, photos, and tables. The post is checked before it is saved.</p>

        <label for="block-slug">Web address</label>
        <input id="block-slug" name="slug" type="text" maxlength="191" value="<?= esc(admin_old('slug', (string) ($block['slug'] ?? ''))) ?>" spellcheck="false">
        <p class="hint">Leave blank to make this from the title. The blog address is /blog/ followed by this text.</p>

        <details class="seo-panel">
            <summary>Search appearance</summary>
            <p class="hint">Optional. Empty fields use the block title and the start of the content. The public address is created from the slug.</p>
            <?php if ($publicUrl !== '') : ?>
                <p class="hint public-url">Public URL <?= esc($publicUrl) ?></p>
            <?php endif ?>
            <p class="hint">Changing a published slug keeps the old address and redirects it to the new one.</p>

            <label for="meta-title">Meta title</label>
            <input id="meta-title" name="meta_title" type="text" maxlength="70" value="<?= esc($metaTitle) ?>">

            <label for="meta-description">Meta description</label>
            <textarea id="meta-description" name="meta_description" maxlength="<?= \App\Libraries\BlockSeo::DESCRIPTION_LIMIT ?>"><?= esc($metaText) ?></textarea>
            <p class="hint">Up to <?= \App\Libraries\BlockSeo::DESCRIPTION_LIMIT ?> characters. The full text is saved.</p>

            <label for="robots-index">Indexing</label>
            <select id="robots-index" name="robots_index">
                <option value="index"<?= $robotsIndex === 'index' ? ' selected' : '' ?>>Index</option>
                <option value="noindex"<?= $robotsIndex === 'noindex' ? ' selected' : '' ?>>Noindex</option>
            </select>

            <label for="robots-follow">Link following</label>
            <select id="robots-follow" name="robots_follow">
                <option value="follow"<?= $robotsFollow === 'follow' ? ' selected' : '' ?>>Follow</option>
                <option value="nofollow"<?= $robotsFollow === 'nofollow' ? ' selected' : '' ?>>Nofollow</option>
            </select>

            <label for="og-title">Open Graph title</label>
            <input id="og-title" name="og_title" type="text" maxlength="70" value="<?= esc($ogTitle) ?>">

            <label for="og-description">Open Graph description</label>
            <textarea id="og-description" name="og_description" maxlength="<?= \App\Libraries\BlockSeo::DESCRIPTION_LIMIT ?>"><?= esc($ogText) ?></textarea>
            <p class="hint">The cover photo is also used when the post is shared.</p>
        </details>
        </div>

        <div class="publish-bar">
            <h2>Publish</h2>
            <label class="status-field" for="block-status">Status
                <select id="block-status" name="status">
                    <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option>
                    <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
                </select>
            </label>
            <div class="form-actions">
                <?php if ($editing) : ?>
                    <a href="<?= site_url('admin/blocks/' . $id) ?>">Preview</a>
                <?php endif ?>
                <a href="<?= site_url('admin/blocks') ?>">Cancel</a>
                <button class="btn" type="submit" name="status" value="draft">Save draft</button>
                <button class="btn btn-primary" type="submit" name="status" value="published"><?= esc($publish) ?></button>
            </div>
            <p class="hint">Draft stays in the admin. Published posts appear on the blog.</p>
        </div>
        </div>
    </form>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="importmap">
<?= json_encode(['imports' => ['ckeditor5' => base_url('assets/vendor/ckeditor5/ckeditor5.js')]], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) . "\n" ?>
</script>
<script type="module" src="<?= base_url('assets/admin/block-editor.js') ?>?v=<?= (int) filemtime(FCPATH . 'assets/admin/block-editor.js') ?>"></script>
<?= $this->endSection() ?>
