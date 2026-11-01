<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<?php
$id           = (int) ($category['id'] ?? 0);
$editing      = $id > 0;
$action       = $editing ? site_url('admin/categories/' . $id) : site_url('admin/categories');
$status       = admin_old('status', (string) ($category['status'] ?? 'active'));
$metaTitle    = admin_old('meta_title', (string) ($category['meta_title'] ?? ''));
$metaText     = admin_old('meta_description', (string) ($category['meta_description'] ?? ''));
$ogTitle      = admin_old('og_title', (string) ($category['og_title'] ?? ''));
$ogText       = admin_old('og_description', (string) ($category['og_description'] ?? ''));
$robotsIndex  = admin_old('robots_index', (string) ($category['robots_index'] ?? 'index'));
$robotsFollow = admin_old('robots_follow', (string) ($category['robots_follow'] ?? 'follow'));
if (! in_array($robotsIndex, ['index', 'noindex'], true)) {
    $robotsIndex = 'index';
}
if (! in_array($robotsFollow, ['follow', 'nofollow'], true)) {
    $robotsFollow = 'follow';
}
?>
<section class="card panel form-card">
    <?php if ($editing) : ?>
        <p class="meta">Created <?= esc(admin_datetime($category['created_at'] ?? null)) ?>. Updated <?= esc(admin_datetime($category['updated_at'] ?? null)) ?>.</p>
    <?php endif ?>
    <form method="post" action="<?= esc($action, 'attr') ?>">
        <?= csrf_field() ?>
        <label for="category-name">Name</label>
        <input id="category-name" name="name" type="text" maxlength="191" required value="<?= esc(admin_old('name', (string) ($category['name'] ?? ''))) ?>">

        <label for="category-slug">Slug</label>
        <input id="category-slug" name="slug" type="text" maxlength="191" value="<?= esc(admin_old('slug', (string) ($category['slug'] ?? ''))) ?>" spellcheck="false">
        <p class="hint">Leave blank to generate from the name. Use lowercase letters, numbers, and hyphens.</p>

        <label for="category-description">Description</label>
        <textarea id="category-description" name="description" maxlength="5000"><?= esc(admin_old('description', (string) ($category['description'] ?? ''))) ?></textarea>
        <p class="hint">Optional. Shown with the category.</p>

        <label for="category-status">Status</label>
        <select id="category-status" name="status">
            <option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option>
            <option value="inactive"<?= $status === 'inactive' ? ' selected' : '' ?>>Inactive</option>
        </select>

        <details class="seo-panel">
            <summary>Search appearance</summary>
            <p class="hint">Optional. Empty fields use the category name and description.</p>

            <label for="meta-title">Meta title</label>
            <input id="meta-title" name="meta_title" type="text" maxlength="<?= \App\Libraries\BlockSeo::TITLE_LIMIT ?>" value="<?= esc($metaTitle) ?>">

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
            <input id="og-title" name="og_title" type="text" maxlength="<?= \App\Libraries\BlockSeo::TITLE_LIMIT ?>" value="<?= esc($ogTitle) ?>">

            <label for="og-description">Open Graph description</label>
            <textarea id="og-description" name="og_description" maxlength="<?= \App\Libraries\BlockSeo::DESCRIPTION_LIMIT ?>"><?= esc($ogText) ?></textarea>
        </details>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save category</button>
            <a href="<?= site_url('admin/categories') ?>">Cancel</a>
        </div>
    </form>
</section>
<?= $this->endSection() ?>
