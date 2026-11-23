<?php
/**
 * Landing page - the Client Satisfaction Measurement hub.
 *
 * The hero is full-bleed and full-height, with the Mayor and Vice Mayor
 * standing behind the headline - the composition the office already uses on its
 * printed material. It carries the primary action: registering as a visitor.
 * That is what most people at the Municipal Hall are here to do, and the
 * earlier version only offered it through the QR panel - which is no help at
 * all to someone already reading the page on a screen.
 */
$showQrPanel = true;
$bodyClass   = 'has-hero-full';   // lets the hero run under the header, edge to edge
$pageScripts = ['js/confetti.js'];
$useConfetti = true;                       // the looping burst over the hero
require __DIR__ . '/../../includes/header.php';

$today   = (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE()', [], 0);
$waiting = (int) db_val("SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE() AND status = 'Waiting'", [], 0);

$cards = [
    [
        'title' => 'Customer Satisfaction Survey',
        'text'  => "Rate your experience with our services and help us understand how we're meeting your expectations.",
        'icon'  => 'clipboard',
        'tone'  => 'green',
        'route' => 'CustomerSatisfactionForm',
        'cta'   => 'Click Here for Survey Form',
        'btn'   => 'btn--green',
    ],
    [
        'title' => 'Feedback Form',
        'text'  => 'Share your suggestions, comments, or insights to help us continuously improve our municipal services.',
        'icon'  => 'message',
        'tone'  => 'blue',
        'route' => 'FeedbackForm',
        'cta'   => 'Click Here for Feedback Form',
        'btn'   => 'btn--primary',
    ],
    [
        'title' => 'Other Concern',
        'text'  => 'Report issues, complaints, or specific concerns that require attention from our municipal office.',
        'icon'  => 'alert',
        'tone'  => 'amber',
        'route' => 'ConcernForm',
        'cta'   => 'Click Here for Concern Form',
        'btn'   => 'btn--orange',
    ],
];

$quickLinks = [
    ['route' => 'MayorsOfficeCitizensCharter', 'icon' => 'book',  'title' => "Citizen's Charter", 'sub' => 'Services, fees and processing times'],
    ['route' => 'TrackSubmission',             'icon' => 'search','title' => 'Track a Submission', 'sub' => 'Check the status by reference number'],
    ['route' => 'FAQ',                         'icon' => 'help',  'title' => 'Frequently Asked Questions', 'sub' => '28 answers about our services'],
];
?>
<!-- Full-bleed hero: sits outside .wrap so the gradient reaches both edges. -->
<section class="hero-full" data-reveal data-confetti-loop>
  <!-- Decorative only: the Mayor and Vice Mayor stand behind the headline. -->
  <div class="hero-full__scene" aria-hidden="true">
    <span class="hero-full__glow hero-full__glow--one"></span>
    <span class="hero-full__glow hero-full__glow--two"></span>
    <img class="hero-full__portrait" src="<?= asset_url('assets/malbar.png') ?>"
         alt="" width="1214" height="1295" fetchpriority="high" decoding="async">
    <span class="hero-full__veil"></span>
  </div>

  <div class="wrap hero-full__body">
    <div class="hero-full__crest" aria-hidden="true">
      <img class="hero-full__seal" src="<?= asset('assets/seal-256.png') ?>" alt="" width="64" height="64">
      <img class="hero-full__mark" src="<?= asset('assets/logo-m.png') ?>" alt="" width="64" height="64">
    </div>

    <p class="hero-full__eyebrow">
      <span class="dot" aria-hidden="true"></span>
      <?php if ($today > 0): ?>
        <?= number_format($today) ?> visitor<?= $today === 1 ? '' : 's' ?> registered today<?= $waiting ? ' &middot; ' . $waiting . ' waiting' : '' ?>
      <?php else: ?>
        Open now &middot; Monday to Friday, 8:00 AM - 5:00 PM
      <?php endif; ?>
    </p>

    <h1 class="hero-full__welcome">WELCOME!</h1>

    <p class="hero-full__office">Office of the Municipal Mayor</p>
    <p class="hero-full__lgu"><?= e(APP_LGU) ?></p>

    <p class="hero-full__system">VISITOR MANAGEMENT SYSTEM</p>
    <p class="hero-full__lede">
      A modern digital solution for efficient tracking and managing of visitor registration.
      <em>Mag-rehistro muna bago pumasok.</em>
    </p>

    <div class="hero-full__actions">
      <a class="btn btn--primary btn--lg btn--shine" href="<?= url('Register') ?>">
        <?= icon('user-plus', 22) ?> Start Registration
      </a>
      <a class="btn btn--ghost btn--lg" href="<?= url('MayorsOfficeCitizensCharter') ?>">
        <?= icon('book', 20) ?> View Services
      </a>
    </div>

    <p class="hero-full__note">
      <?= icon('shield', 16) ?>
      Your details are used only for visitor management, under the Data Privacy Act.
      <a href="<?= url('Privacy') ?>">Read the notice</a>
    </p>
  </div>
</section>

<div class="wrap">
  <?php require __DIR__ . '/../../includes/qr_panel.php'; ?>

  <div class="hub-hero" data-reveal>
    <canvas class="confetti-canvas" id="confettiCanvas" aria-hidden="true"></canvas>
    <h1>Client Satisfaction Measurement &amp; Feedback System</h1>
    <p>Share your experience and help us improve our services</p>
  </div>

  <div class="hub-grid">
    <?php foreach ($cards as $index => $card): ?>
      <article class="hub-card" data-reveal data-reveal-delay="<?= $index * 70 ?>">
        <div class="hub-card__icon hub-card__icon--<?= e($card['tone']) ?>"><?= icon($card['icon'], 30) ?></div>
        <h2><?= e($card['title']) ?></h2>
        <p><?= e($card['text']) ?></p>
        <a class="btn <?= e($card['btn']) ?> btn--block" href="<?= url($card['route']) ?>"><?= e($card['cta']) ?></a>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="quick-strip">
    <?php foreach ($quickLinks as $index => $link): ?>
      <a class="quick-link" href="<?= url($link['route']) ?>" data-reveal data-reveal-delay="<?= $index * 60 ?>">
        <span class="quick-link__icon"><?= icon($link['icon'], 20) ?></span>
        <span>
          <strong><?= e($link['title']) ?></strong>
          <span><?= e($link['sub']) ?></span>
        </span>
        <span class="quick-link__arrow"><?= icon('arrow-right', 18) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
