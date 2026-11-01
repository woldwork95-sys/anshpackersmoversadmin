<?= $this->extend('admin/layout') ?>

<?= $this->section('content') ?>
<section class="card panel">
    <h2>Account</h2>
    <dl class="details">
        <div>
            <dt>Username</dt>
            <dd><?= esc($username !== '' ? $username : 'admin') ?></dd>
        </div>
    </dl>
</section>
<?= $this->endSection() ?>
