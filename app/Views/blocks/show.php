<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($seo['title']) ?> | Ansh Packers &amp; Movers</title>
    <?php if ($seo['description'] !== '') : ?>
        <meta name="description" content="<?= esc($seo['description']) ?>">
    <?php endif ?>
    <link rel="canonical" href="<?= esc($seo['canonical']) ?>">
    <meta name="robots" content="<?= esc($seo['robots']) ?>">
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?= esc($seo['ogTitle']) ?>">
    <?php if ($seo['ogDescription'] !== '') : ?>
        <meta property="og:description" content="<?= esc($seo['ogDescription']) ?>">
    <?php endif ?>
    <meta property="og:url" content="<?= esc($seo['canonical']) ?>">
    <?php if ($seo['image'] !== '') : ?>
        <meta property="og:image" content="<?= esc($seo['image']) ?>">
    <?php endif ?>
    <link rel="stylesheet" href="<?= base_url('assets/admin/content.css') ?>">
</head>
<body>
    <main class="public-block">
        <p class="public-brand"><a href="<?= esc(site_url('blog', null, config(\Config\App::class))) ?>">Ansh Packers &amp; Movers</a></p>
        <h1><?= esc($title) ?></h1>
        <p class="blog-meta">
            <?php $stamp = strtotime((string) ($publishedAt ?? '')); ?>
            <?= $stamp === false ? '' : esc(date('M j, Y', $stamp)) ?>
            <span><?= esc((string) ($author ?? 'Admin')) ?></span>
            <?php if (trim((string) ($categoryName ?? '')) !== '') : ?>
                <span><?= esc((string) $categoryName) ?></span>
            <?php endif ?>
        </p>
        <div class="block-content"><?= block_html($content) ?></div>
    </main>
</body>
</html>
