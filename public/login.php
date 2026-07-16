<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/layout.php';
if (user()) redirect('dashboard.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (login(trim((string)($_POST['identity'] ?? '')), (string)($_POST['password'] ?? ''))) redirect('dashboard.php');
    $error = 'Invalid username/email or password.';
}
page_header('Sign in');
?>
<section class="login-card card">
  <h1>Sign in</h1><p class="muted">Use your PricePlan account.</p>
  <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif ?>
  <form method="post"><?= csrf_field() ?>
    <label>Username or email<input name="identity" required autofocus autocomplete="username"></label>
    <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
    <button class="button primary" type="submit">Sign in</button>
  </form>
</section>
<?php page_footer(); ?>
