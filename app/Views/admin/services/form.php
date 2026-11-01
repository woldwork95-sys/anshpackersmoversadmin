<?= $this->extend('admin/layout') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= base_url('assets/vendor/ckeditor5/ckeditor5.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/admin/content.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$id           = (int) ($service['id'] ?? 0);
$editing      = $id > 0;
$action       = $editing ? site_url('admin/services/' . $id) : site_url('admin/services');
$status       = admin_old('status', (string) ($service['status'] ?? 'draft'));
$publish      = $editing && $status === 'published' ? 'Update' : 'Publish';
$metaTitle    = admin_old('meta_title', (string) ($service['meta_title'] ?? ''));
$metaText     = admin_old('meta_description', (string) ($service['meta_description'] ?? ''));
$ogTitle      = admin_old('og_title', (string) ($service['og_title'] ?? ''));
$ogText       = admin_old('og_description', (string) ($service['og_description'] ?? ''));
$robotsIndex  = admin_old('robots_index', (string) ($service['robots_index'] ?? 'index'));
$robotsFollow = admin_old('robots_follow', (string) ($service['robots_follow'] ?? 'follow'));
if (! in_array($robotsIndex, ['index', 'noindex'], true)) {
    $robotsIndex = 'index';
}
if (! in_array($robotsFollow, ['follow', 'nofollow'], true)) {
    $robotsFollow = 'follow';
}
$ogImage    = (string) ($service['og_image'] ?? '');
$ogImageUrl = $ogImage !== '' ? service('blockSeo')->imageUrl($ogImage) : '';
$coverPath  = (string) ($service['cover_path'] ?? '');
$order      = admin_old('sort_order', (string) ($service['sort_order'] ?? '0'));
?>
<section class="card panel form-card form-card-editor">
    <?php if ($editing) : ?>
        <p class="meta">Created <?= esc(admin_datetime($service['created_at'] ?? null)) ?>. Updated <?= esc(admin_datetime($service['updated_at'] ?? null)) ?>.</p>
    <?php endif ?>
    <p class="hint">Fill in the title and the short summary. Save a draft to keep it private, or publish it on the website.</p>
    <form id="block-form" method="post" action="<?= esc($action, 'attr') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <button class="visually-hidden" type="submit">Save</button>
        <div class="editor-layout">
        <div class="editor-main">
        <label for="block-title">Title</label>
        <input id="block-title" name="title" type="text" maxlength="191" required value="<?= esc(admin_old('title', (string) ($service['title'] ?? ''))) ?>">

        <label for="service-order">Order</label>
        <input id="service-order" name="sort_order" type="number" min="0" max="9999" step="1" value="<?= esc($order) ?>">
        <p class="hint">Lower numbers are shown first on the website.</p>

        <label for="block-excerpt">Short summary</label>
        <textarea id="block-excerpt" name="excerpt" maxlength="300"><?= esc(admin_old('excerpt', (string) ($service['excerpt'] ?? ''))) ?></textarea>
        <p class="hint">One or two lines for the service card. Leave blank to use the start of the page.</p>

        <label for="service-image-alt">Image description</label>
        <input id="service-image-alt" name="image_alt" type="text" maxlength="191" value="<?= esc(admin_old('image_alt', (string) ($service['image_alt'] ?? ''))) ?>">

        <label for="og-image">Cover photo</label>
        <input id="og-image" name="og_image" type="file" accept="image/jpeg,image/png,image/gif,image/webp">
        <p class="hint">JPG, PNG, GIF, or WebP up to 2 MB. This photo replaces the current one on the service card. Leave empty to keep the current photo.</p>
        <?php if ($ogImageUrl !== '') : ?>
            <p class="hint"><img src="<?= esc($ogImageUrl, 'attr') ?>" alt="" width="120"></p>
            <label class="check-label"><input type="checkbox" name="remove_og_image" value="1"> Remove the uploaded photo</label>
        <?php elseif ($coverPath !== '') : ?>
            <p class="hint">Using the built-in website photo until you upload a new one.</p>
        <?php endif ?>

        <label for="block-content">Page</label>
        <textarea
            id="block-content"
            name="content"
            maxlength="<?= \App\Libraries\BlockHtml::MAX_LENGTH ?>"
            data-block-editor
            data-upload-url="<?= esc(site_url('admin/blocks/images'), 'attr') ?>"
            data-csrf-name="csrf_test_name"
        ><?= esc(admin_old('content', (string) ($service['content'] ?? ''))) ?></textarea>
        <p class="hint">Write the service page here. Leave it empty to keep the page that is already on the website. A new service needs this text.</p>

        <label for="block-slug">Web address</label>
        <input id="block-slug" name="slug" type="text" maxlength="191" value="<?= esc(admin_old('slug', (string) ($service['slug'] ?? ''))) ?>" spellcheck="false">
        <p class="hint">Leave blank to make this from the title. Existing services already have a page at this address. A new address opens as a service page.</p>

        <details class="seo-panel">
            <summary>Search appearance</summary>
            <p class="hint">Optional. Empty fields use the service title and the start of the page.</p>
            <p class="hint">Changing a published slug keeps the old address and sends visitors to the new one.</p>

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
            <p class="hint">The cover photo is also used when the service is shared.</p>
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
                    <a href="<?= site_url('admin/services/' . $id) ?>">Preview</a>
                <?php endif ?>
                <a href="<?= site_url('admin/services') ?>">Cancel</a>
                <button class="btn" type="submit" name="status" value="draft">Save draft</button>
                <button class="btn btn-primary" type="submit" name="status" value="published"><?= esc($publish) ?></button>
            </div>
            <p class="hint">Draft stays in the admin. Published services appear on the website.</p>
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
