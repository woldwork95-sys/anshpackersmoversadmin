<?php if ($message = session('success')) : ?>
    <div class="alert alert-success" role="status"><?= esc($message) ?></div>
<?php endif ?>

<?php if ($message = session('error')) : ?>
    <div class="alert alert-error" role="alert"><?= esc($message) ?></div>
<?php endif ?>

<?php $errors = session('errors'); ?>
<?php if (is_array($errors) && $errors !== []) : ?>
    <div class="alert alert-error" role="alert">
        <ul>
            <?php foreach ($errors as $error) : ?>
                <?php if (is_string($error) && $error !== '') : ?>
                    <li><?= esc($error) ?></li>
                <?php endif ?>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>
