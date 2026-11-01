<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<section class="stats" aria-label="Statistics">
    <article class="card stat">
        <p>Total posts</p>
        <strong data-stat="total-blocks"><?= esc((string) $stats['totalBlocks']) ?></strong>
    </article>
    <article class="card stat">
        <p>Published posts</p>
        <strong data-stat="published-blocks"><?= esc((string) $stats['publishedBlocks']) ?></strong>
    </article>
    <article class="card stat">
        <p>Draft posts</p>
        <strong data-stat="draft-blocks"><?= esc((string) $stats['draftBlocks']) ?></strong>
    </article>
    <article class="card stat">
        <p>Total categories</p>
        <strong data-stat="total-categories"><?= esc((string) $stats['totalCategories']) ?></strong>
    </article>
    <article class="card stat">
        <p>Total services</p>
        <strong data-stat="total-services"><?= esc((string) $stats['totalServices']) ?></strong>
    </article>
    <article class="card stat">
        <p>Published services</p>
        <strong data-stat="published-services"><?= esc((string) $stats['publishedServices']) ?></strong>
    </article>
</section>

<section class="card panel">
    <h2>Quick actions</h2>
    <div class="actions">
        <a class="btn btn-primary" href="<?= site_url('admin/blocks/create') ?>">New post</a>
        <a class="btn" href="<?= site_url('admin/blocks') ?>">All posts</a>
        <a class="btn" href="<?= site_url('admin/categories') ?>">Manage Categories</a>
        <a class="btn btn-primary" href="<?= site_url('admin/services/create') ?>">New service</a>
        <a class="btn" href="<?= site_url('admin/services') ?>">All services</a>
    </div>
</section>

<section class="card panel">
    <h2>Recently updated posts</h2>
    <?php if ($stats['recentBlocks'] === []) : ?>
        <?= admin_empty('No posts have been updated yet.') ?>
    <?php else : ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Title</th>
                        <th scope="col">Status</th>
                        <th scope="col">Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['recentBlocks'] as $block) : ?>
                        <?php $status = (string) ($block['status'] ?? ''); ?>
                        <?php $statusClass = in_array($status, ['published', 'draft'], true) ? $status : 'unknown'; ?>
                        <?php $updatedAt = strtotime((string) ($block['updated_at'] ?? '')); ?>
                        <tr>
                            <td><a href="<?= site_url('admin/blocks/' . (int) ($block['id'] ?? 0)) ?>"><?= esc((string) ($block['title'] ?? '')) ?></a></td>
                            <td><span class="badge badge-<?= esc($statusClass, 'attr') ?>"><?= esc($status) ?></span></td>
                            <td><?= $updatedAt === false ? '—' : esc(date('M j, Y g:i A', $updatedAt)) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
    <?php endif ?>
</section>
<?= $this->endSection() ?>
