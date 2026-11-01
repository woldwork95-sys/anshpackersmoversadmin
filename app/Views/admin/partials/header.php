<header class="topbar">
    <button type="button" class="nav-toggle" data-nav-toggle aria-controls="admin-nav" aria-expanded="false">
        <span class="nav-toggle-bars" aria-hidden="true"></span>
        <span class="visually-hidden">Menu</span>
    </button>
    <div class="topbar-title">
        <h1><?= esc($title) ?></h1>
        <p>Signed in as <?= esc($username !== '' ? $username : 'admin') ?></p>
    </div>
    <form method="post" action="<?= site_url('admin/logout') ?>" class="logout-form">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-ghost">Sign out</button>
    </form>
</header>
