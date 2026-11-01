<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<?php
$q        = (string) ($filters['q'] ?? '');
$status   = (string) ($filters['status'] ?? '');
$category = (int) ($filters['category'] ?? 0);
$filtering = $q !== '' || $status !== '' || $category > 0;
?>
<div class="toolbar">
    <form class="filters" method="get" action="<?= site_url('admin/blocks') ?>">
        <label>
            Search
            <input id="block-q" type="search" name="q" value="<?= esc($q) ?>" maxlength="100" placeholder="Title or slug">
        </label>
        <label>
            Category
            <select id="block-category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $item) : ?>
                    <?php $itemId = (int) ($item['id'] ?? 0); ?>
                    <option value="<?= esc((string) $itemId, 'attr') ?>"<?= $category === $itemId ? ' selected' : '' ?>><?= esc((string) ($item['name'] ?? '')) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>
            Status
            <select id="block-status" name="status">
                <option value="">All statuses</option>
                <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option>
                <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
            </select>
        </label>
        <button class="btn" type="submit">Search</button>
        <?php if ($filtering) : ?>
            <a href="<?= site_url('admin/blocks') ?>">Clear</a>
        <?php endif ?>
    </form>
    <a class="btn btn-primary" href="<?= site_url('admin/blocks/create') ?>">New post</a>
</div>

<section class="card panel">
    <?php if ($blocks === []) : ?>
        <?= admin_empty($filtering ? 'No posts match these filters.' : 'No posts yet.') ?>
    <?php else : ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Title</th>
                        <th scope="col">Category</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blocks as $block) : ?>
                        <?php $id = (int) ($block['id'] ?? 0); ?>
                        <?php $title = (string) ($block['title'] ?? ''); ?>
                        <tr>
                            <td><a href="<?= site_url('admin/blocks/' . $id) ?>"><?= esc($title) ?></a></td>
                            <td><?= esc((string) ($names[(int) ($block['category_id'] ?? 0)] ?? '—')) ?></td>
                            <td><?= admin_badge((string) ($block['status'] ?? '')) ?></td>
                            <td><?= esc(admin_datetime($block['updated_at'] ?? null)) ?></td>
                            <td class="row-actions">
                                <a href="<?= site_url('admin/blocks/' . $id) ?>">View<span class="visually-hidden"> <?= esc($title) ?></span></a>
                                <a href="<?= site_url('admin/blocks/' . $id . '/edit') ?>">Edit<span class="visually-hidden"> <?= esc($title) ?></span></a>
                                <a href="<?= site_url('admin/blocks/' . $id . '/delete') ?>">Delete<span class="visually-hidden"> <?= esc($title) ?></span></a>
                            </td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?= $this->include('admin/partials/pager') ?>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
