<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog | Ansh Packers &amp; Movers</title>
    <meta name="description" content="Packing and moving notes from Ansh Packers &amp; Movers.">
    <link rel="canonical" href="<?= esc($canonical) ?>">
    <meta name="robots" content="index, follow">
    <link rel="stylesheet" href="<?= base_url('assets/admin/content.css') ?>">
</head>
<body>
    <header class="blog-bar">
        <a class="public-brand" href="<?= esc($canonical) ?>">Ansh Packers &amp; Movers</a>
        <nav aria-label="Blog">
            <a href="<?= esc($canonical) ?>" aria-current="page">Blog</a>
        </nav>
    </header>
    <main class="public-block">
        <h1>Blog</h1>
        <p class="blog-intro">Published posts for the Ansh Packers &amp; Movers blog. Drafts stay in the admin until you publish them.</p>
        <?php if ($posts === []) : ?>
            <p>No published posts yet.</p>
        <?php else : ?>
            <div class="blog-list">
                <?php foreach ($posts as $post) : ?>
                    <?php
                    $slug    = (string) ($post['slug'] ?? '');
                    $url     = service('blockSeo')->publicUrl($slug);
                    $excerpt = trim((string) ($post['excerpt'] ?? ''));
                    if ($excerpt === '') {
                        $excerpt = service('blockSeo')->excerpt((string) ($post['content'] ?? ''));
                    }
                    $image = service('blockSeo')->imageUrl((string) ($post['og_image'] ?? ''));
                    $when     = (string) ($post['published_at'] ?? '');
                    $stamp    = strtotime($when);
                    $category = trim((string) ($post['category_name'] ?? ''));
                    ?>
                    <article class="blog-card">
                        <?php if ($image !== '' && $url !== '') : ?>
                            <a href="<?= esc($url) ?>"><img src="<?= esc($image) ?>" alt=""></a>
                        <?php endif ?>
                        <p class="blog-meta">
                            <?= $stamp === false ? '' : esc(date('M j, Y', $stamp)) ?>
                            <span><?= esc((string) ($post['author_name'] ?? 'Admin')) ?></span>
                            <?php if ($category !== '') : ?>
                                <span><?= esc($category) ?></span>
                            <?php endif ?>
                        </p>
                        <h2>
                            <?php if ($url !== '') : ?>
                                <a href="<?= esc($url) ?>"><?= esc((string) ($post['title'] ?? '')) ?></a>
                            <?php else : ?>
                                <?= esc((string) ($post['title'] ?? '')) ?>
                            <?php endif ?>
                        </h2>
                        <?php if ($excerpt !== '') : ?>
                            <p><?= esc($excerpt) ?></p>
                        <?php endif ?>
                    </article>
                <?php endforeach ?>
            </div>
        <?php endif ?>
    </main>
</body>
</html>
