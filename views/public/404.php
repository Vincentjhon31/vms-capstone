<?php
require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap wrap--narrow">
  <div class="card">
    <div class="card__body center" style="padding:56px 24px">
      <img src="<?= asset('assets/seal.png') ?>" alt="" width="76" height="76">
      <h1>Page not found</h1>
      <p class="muted">The page you were looking for is not part of the Visitor Management System.</p>
      <div class="btn-row" style="justify-content:center">
        <a class="btn btn--primary" href="<?= url('') ?>"><?= icon('home') ?> Back to home</a>
        <a class="btn btn--ghost" href="<?= url('Register') ?>"><?= icon('user-plus') ?> Register as a visitor</a>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
