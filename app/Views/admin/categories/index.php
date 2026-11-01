<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<?php $q = (string) ($filters['q'] ?? ''); ?>
<div class="toolbar">
    <form class="filters" method="get" action="<?= site_url('admin/categories') ?>">
        <label>
            Search
            <input id="category-q" type="search" name="q" value="<?= esc($q) ?>" maxlength="100" placeholder="Name or slug">
        </label>
        <button class="btn" type="submit">Search</button>
        <?php if ($q !== '') : ?>
            <a href="<?= site_url('admin/categories') ?>">Clear</a>
        <?php endif ?>
    </form>
    <a class="btn btn-primary" href="<?= site_url('admin/categories/create') ?>">New category</a>
</div>

<section class="card panel">
    <?php if ($categories === []) : ?>
        <?= admin_empty($q === '' ? 'No categories yet.' : 'No categories match this search.') ?>
    <?php else : ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Slug</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category) : ?>
                        <?php $id = (int) ($category['id'] ?? 0); ?>
                        <?php $name = (string) ($category['name'] ?? ''); ?>
                        <tr>
                            <td><?= esc($name) ?></td>
                            <td><?= esc((string) ($category['slug'] ?? '')) ?></td>
                            <td><?= admin_badge((string) ($category['status'] ?? '')) ?></td>
                            <td><?= esc(admin_datetime($category['updated_at'] ?? null)) ?></td>
                            <td class="row-actions">
                                <a href="<?= site_url('admin/categories/' . $id . '/edit') ?>">Edit<span class="visually-hidden"> <?= esc($name) ?></span></a>
                                <a href="<?= site_url('admin/categories/' . $id . '/delete') ?>">Delete<span class="visually-hidden"> <?= esc($name) ?></span></a>
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
