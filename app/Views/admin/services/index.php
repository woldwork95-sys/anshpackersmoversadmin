<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<?php
$q         = (string) ($filters['q'] ?? '');
$status    = (string) ($filters['status'] ?? '');
$filtering = $q !== '' || $status !== '';
?>
<div class="toolbar">
    <form class="filters" method="get" action="<?= site_url('admin/services') ?>">
        <label>
            Search
            <input id="service-q" type="search" name="q" value="<?= esc($q) ?>" maxlength="100" placeholder="Title or slug">
        </label>
        <label>
            Status
            <select id="service-status" name="status">
                <option value="">All statuses</option>
                <option value="draft"<?= $status === 'draft' ? ' selected' : '' ?>>Draft</option>
                <option value="published"<?= $status === 'published' ? ' selected' : '' ?>>Published</option>
            </select>
        </label>
        <button class="btn" type="submit">Search</button>
        <?php if ($filtering) : ?>
            <a href="<?= site_url('admin/services') ?>">Clear</a>
        <?php endif ?>
    </form>
    <a class="btn btn-primary" href="<?= site_url('admin/services/create') ?>">New service</a>
</div>

<section class="card panel">
    <?php if ($services === []) : ?>
        <?= admin_empty($filtering ? 'No services match these filters.' : 'No services yet.') ?>
    <?php else : ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Title</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service) : ?>
                        <?php $id = (int) ($service['id'] ?? 0); ?>
                        <?php $title = (string) ($service['title'] ?? ''); ?>
                        <tr>
                            <td><?= esc((string) ($service['sort_order'] ?? 0)) ?></td>
                            <td><a href="<?= site_url('admin/services/' . $id) ?>"><?= esc($title) ?></a></td>
                            <td><?= admin_badge((string) ($service['status'] ?? '')) ?></td>
                            <td><?= esc(admin_datetime($service['updated_at'] ?? null)) ?></td>
                            <td class="row-actions">
                                <a href="<?= site_url('admin/services/' . $id) ?>">View<span class="visually-hidden"> <?= esc($title) ?></span></a>
                                <a href="<?= site_url('admin/services/' . $id . '/edit') ?>">Edit<span class="visually-hidden"> <?= esc($title) ?></span></a>
                                <a href="<?= site_url('admin/services/' . $id . '/delete') ?>">Delete<span class="visually-hidden"> <?= esc($title) ?></span></a>
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
