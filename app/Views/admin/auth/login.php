<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin sign in | Ansh Packers &amp; Movers</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            min-height: 100%;
            color: rgba(33, 37, 41, 1);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji";
            background: #f7f8f9;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid #f2f2f2;
            border-radius: 12px;
            padding: 2rem 1.5rem;
            box-shadow: 0 8px 24px rgba(33, 37, 41, 0.06);
        }
        .brand {
            margin: 0 0 0.35rem;
            font-size: 1.35rem;
            line-height: 1.3;
        }
        .brand span { color: rgba(221, 72, 20, 1); }
        p.lead {
            margin: 0 0 1.5rem;
            color: rgba(0, 0, 0, 0.62);
        }
        label {
            display: block;
            margin-bottom: 0.35rem;
            font-weight: 600;
        }
        input {
            width: 100%;
            min-height: 44px;
            margin-bottom: 1rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid #d5d7db;
            border-radius: 8px;
            font: inherit;
        }
        input:focus {
            outline: none;
            border-color: rgba(221, 72, 20, 1);
            background: rgba(221, 72, 20, 0.06);
        }
        button {
            width: 100%;
            min-height: 44px;
            border: 0;
            border-radius: 8px;
            background: rgba(221, 72, 20, 1);
            color: #fff;
            font: inherit;
            font-weight: 700;
            cursor: pointer;
        }
        button:hover, button:focus { background: rgba(186, 58, 14, 1); }
        .alert {
            margin: 0 0 1rem;
            padding: 0.75rem 0.85rem;
            border-radius: 8px;
            background: rgba(221, 72, 20, 0.12);
            color: #7a2b0c;
        }
        .alert.success {
            background: #e8f6ee;
            color: #0f5132;
        }
        .alert ul { margin: 0.4rem 0 0; padding-left: 1.1rem; }
        .back {
            display: inline-block;
            margin-top: 1rem;
            color: rgba(0, 0, 0, 0.55);
        }
        @media (max-width: 480px) {
            body { align-items: flex-start; }
            .card { padding: 1.25rem 1rem; }
        }
    </style>
</head>
<body>
    <main class="card">
        <h1 class="brand">Ansh <span>Packers &amp; Movers</span></h1>
        <p class="lead">Sign in to the admin area.</p>

        <?php if (session('success')) : ?>
            <div class="alert success" role="status"><?= esc(session('success')) ?></div>
        <?php endif ?>

        <?php if (session('error')) : ?>
            <div class="alert" role="alert"><?= esc(session('error')) ?></div>
        <?php endif ?>

        <?php $errors = session('errors'); ?>
        <?php if (is_array($errors) && $errors !== []) : ?>
            <div class="alert" role="alert">
                <ul>
                    <?php foreach ($errors as $message) : ?>
                        <li><?= esc((string) $message) ?></li>
                    <?php endforeach ?>
                </ul>
            </div>
        <?php endif ?>

        <form method="post" action="<?= site_url('admin/login') ?>" autocomplete="on">
            <?= csrf_field() ?>
            <label for="identity">Email or username</label>
            <input id="identity" name="identity" type="text" value="<?= esc($identity ?? '') ?>" autocomplete="username" maxlength="191" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" value="" autocomplete="current-password" maxlength="255" required>
            <button type="submit">Sign in</button>
        </form>
        <a class="back" href="<?= site_url('/') ?>">Back to site</a>
    </main>
</body>
</html>
