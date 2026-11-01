<?php
$items = [
    'dashboard'  => ['label' => 'Dashboard', 'path' => 'admin'],
    'categories' => ['label' => 'Categories', 'path' => 'admin/categories'],
    'blocks'     => ['label' => 'Blog', 'path' => 'admin/blocks'],
    'services'   => ['label' => 'Services', 'path' => 'admin/services'],
    'settings'   => ['label' => 'Settings', 'path' => 'admin/settings'],
];
$activeNav = $activeNav ?? '';
?>
<aside id="admin-nav" class="sidebar" data-nav>
    <a class="brand" href="<?= site_url('admin') ?>">Ansh <span>Admin</span></a>
    <nav aria-label="Admin">
        <ul>
            <?php foreach ($items as $key => $item) : ?>
                <li>
                    <a href="<?= site_url($item['path']) ?>"<?= $activeNav === $key ? ' aria-current="page"' : '' ?>>
                        <?= esc($item['label']) ?>
                    </a>
                </li>
            <?php endforeach ?>
        </ul>
    </nav>
</aside>
