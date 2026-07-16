<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
require_permission('updates');

$release = null;
$error = null;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $release = latest_release(true);
        if (($_POST['action'] ?? '') === 'install') {
            $version = install_release($release);
            flash('success', "PricePlan was updated to $version.");
            redirect('updates.php');
        }
    } else {
        $release = latest_release(isset($_GET['refresh']));
    }
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$latest = $release ? ltrim((string)$release['tag_name'], 'vV') : null;
$available = $latest !== null && version_compare($latest, app_version(), '>');
page_header('Updates');
?>
<div class="page-title"><div><h1>App updates</h1><p class="muted">Updates are downloaded from the configured GitHub Releases repository.</p></div></div>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif ?>
<section class="card narrow">
    <h2>Version status</h2>
    <dl class="update-status">
        <div><dt>Installed</dt><dd><?= e(app_version()) ?></dd></div>
        <div><dt>Latest release</dt><dd><?= e($latest ?? 'Unavailable') ?></dd></div>
    </dl>
    <?php if ($available): ?>
        <p><strong>Version <?= e($latest) ?> is ready to install.</strong></p>
        <?php if (!empty($release['body'])): ?><div class="release-notes"><?= nl2br(e($release['body'])) ?></div><?php endif ?>
        <form method="post" onsubmit="return confirm('Install this update now? Do not close this page until it finishes.');">
            <?= csrf_field() ?><input type="hidden" name="action" value="install">
            <button class="button primary">Install <?= e($latest) ?></button>
        </form>
    <?php elseif ($latest): ?><p>Your installation is up to date.</p><?php endif ?>
    <p><a class="button" href="<?= e(url('updates.php?refresh=1')) ?>">Check again</a></p>
</section>
<?php page_footer(); ?>
