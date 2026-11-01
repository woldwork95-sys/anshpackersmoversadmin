<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($title) ?> | Ansh Packers &amp; Movers</title>
    <link rel="stylesheet" href="<?= base_url('assets/admin/admin.css') ?>?v=<?= (int) filemtime(FCPATH . 'assets/admin/admin.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body class="admin-body">
    <div class="page-loading" data-page-loading hidden>
        <span class="page-loading-bar"></span>
        <span class="visually-hidden">Loading</span>
    </div>
    <div class="nav-backdrop" data-nav-backdrop hidden></div>
    <div class="admin-shell">
        <?= $this->include('admin/partials/sidebar') ?>
        <div class="admin-main">
            <?= $this->include('admin/partials/header') ?>
            <main id="content" class="admin-content">
                <?= $this->include('admin/partials/alerts') ?>
                <?= $this->renderSection('content') ?>
            </main>
        </div>
    </div>
    <script src="<?= base_url('assets/admin/admin.js') ?>" defer></script>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
