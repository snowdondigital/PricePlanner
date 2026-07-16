<?php
declare(strict_types=1);

function page_header(string $title): void
{
    $u = user();
    ?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> &middot; <?= e(config('site_name')) ?></title>
<link rel="stylesheet" href="<?= e(url('../assets/css/app.css')) ?>">
<script defer src="<?= e(url('../assets/js/app.js')) ?>"></script>
</head><body>
<?php if ($u): ?><header class="topbar">
  <a class="brand" href="<?= e(url('dashboard.php')) ?>"><?= e(config('site_name')) ?></a>
  <button class="nav-toggle" type="button" aria-label="Toggle navigation">&#9776;</button>
  <nav>
    <a href="<?= e(url('dashboard.php')) ?>">Dashboard</a>
    <a href="<?= e(url('products.php')) ?>">Products</a>
    <a href="<?= e(url('pricelists.php')) ?>">Price lists</a>
    <?php if (can('settings')): ?><a href="<?= e(url('settings.php')) ?>">Settings</a><a href="<?= e(url('users.php')) ?>">Users</a><?php endif ?>
    <a href="<?= e(url('audit.php')) ?>">Audit</a>
    <?php if (can('import')): ?><a href="<?= e(url('import.php')) ?>">Import</a><?php endif ?>
    <?php if (can('export')): ?><a href="<?= e(url('export.php')) ?>">Export</a><?php endif ?>
  </nav>
  <div class="account"><?= e($u['username']) ?> <span class="role"><?= e(ucfirst($u['role'])) ?></span> <a href="<?= e(url('logout.php')) ?>">Log out</a></div>
</header><?php endif ?>
<main class="container">
<?php foreach (flashes() as $f): ?><div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach ?>
<?php
}

function page_footer(): void
{
    ?></main><footer>PricePlan &middot; &copy; <?= e(date('Y')) ?> <a href="https://snowdondigital.co.uk" target="_blank" rel="noopener">Snowdon Digital</a> &middot; PHP <?= e(PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) ?></footer></body></html><?php
}
