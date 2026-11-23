<?php
/** Administrator sign in. */

if (is_admin()) {
    redirect('AdminDashboard');
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!rate_limit_ok('login')) {
        $error = 'Too many sign-in attempts. Please wait a few minutes and try again.';
    } elseif (admin_login($username, $password)) {
        $next = $_SESSION['after_login'] ?? null;
        unset($_SESSION['after_login']);
        flash('Signed in as ' . $_SESSION['admin_name'] . '.');
        if ($next && !str_contains($next, 'AdminLogout')) {
            header('Location: ' . $next);
            exit;
        }
        redirect('AdminDashboard');
    } else {
        $error = 'Incorrect username or password.';
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="card" style="max-width:420px;margin-inline:auto">
    <div class="card__header">
      <img src="<?= asset('assets/seal.png') ?>" alt="">
      <div>
  <h1>Administrator Sign In</h1>
        <p><?= e(APP_NAME) ?></p>
      </div>
    </div>
    <div class="card__body">
      <?php if ($error): ?>
        <div class="flash flash--error" role="alert"><?= icon('alert', 20) ?><span><?= e($error) ?></span></div>
      <?php endif; ?>

      <form method="post" action="<?= url('Admin') ?>" data-guard>
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="username"><?= icon('user', 17) ?>Username</label>
          <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>"
                 required autocomplete="username" autofocus>
        </div>
        <div class="field">
          <label class="label" for="password"><?= icon('lock', 17) ?>Password</label>
          <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
        </div>
        <button class="btn btn--primary btn--block" type="submit"><?= icon('shield') ?> Sign In</button>
      </form>

      <p class="small muted center mt-24 mb-0">
        Authorised municipal personnel only. Sign-in attempts are logged.
      </p>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
