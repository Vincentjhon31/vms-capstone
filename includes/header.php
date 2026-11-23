<?php
/**
 * Page shell - opening half.
 *
 * Two layouts live here. The public pages get a centred column under a sticky
 * top bar. The admin pages get a fixed sidebar: staff move between the log,
 * concerns and reports all day, and a sidebar keeps every destination one click
 * away with room for the counts that tell them where to look first.
 *
 * Views set $pageTitle (index.php does this from the route table) and may set
 * $bodyClass before including this file.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/icons.php';

$pageTitle    = $pageTitle    ?? APP_NAME;
$currentRoute = $currentRoute ?? '';
$bodyClass    = $bodyClass    ?? '';
$admin        = current_admin();
$isAdminArea  = (str_starts_with($currentRoute, 'admin') && $currentRoute !== 'admin')
                || in_array($currentRoute, ['log', 'checkpoint', 'concernlist', 'feedbacklist', 'customersatisfactionlist', 'summary'], true);
$useSidebar   = $isAdminArea && $admin;

/* The live queue strip is fixed to the bottom of every public page. The admin
   sign-in page borrows the public shell but is not one of them. */
$showQueueStrip = !$isAdminArea && $currentRoute !== 'admin';

$publicNav = [
    ['route' => '',                           'label' => 'Home',                          'icon' => 'home'],
    ['route' => 'MayorsOfficeCitizensCharter','label' => "Citizen's Charter",             'icon' => 'book'],
    // 'short' is shown where five pills only just miss one row (see .nav-link__short in style.css).
    ['route' => 'OrganizationalChart',        'label' => 'Organizational Chart',          'icon' => 'users', 'short' => 'Org Chart'],
    ['route' => 'CustomerSatisfactionForm',   'label' => 'Customer Satisfaction Survey',  'icon' => 'search'],
    ['route' => 'FAQ',                        'label' => 'FAQ',                           'icon' => 'help'],
];

/**
 * Sidebar groups. Badge counts are only fetched inside the admin area, so a
 * public page never pays for the queries.
 */
$adminGroups = [];
if ($useSidebar) {
    $openConcerns = (int) db_val("SELECT COUNT(*) FROM concerns WHERE status NOT IN ('Resolved','Closed')", [], 0);
    $newFeedback  = (int) db_val("SELECT COUNT(*) FROM feedback WHERE status = 'Received'", [], 0);
    $waitingNow   = (int) db_val("SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE() AND status = 'Waiting'", [], 0);

    $adminGroups = [
        [
            'label' => null,
            'items' => [
                ['route' => 'AdminDashboard', 'label' => 'Dashboard', 'icon' => 'dashboard'],
            ],
        ],
        [
            'label' => 'Front desk',
            'items' => [
                ['route' => 'Checkpoint',  'label' => 'Check-in Desk', 'icon' => 'scan'],
                ['route' => 'Log',         'label' => 'Visitor Log', 'icon' => 'list',    'badge' => $waitingNow,   'badge_tone' => 'warn',  'badge_label' => 'waiting now'],
                ['route' => 'ConcernList', 'label' => 'Concerns',    'icon' => 'alert',   'badge' => $openConcerns, 'badge_tone' => 'danger','badge_label' => 'open'],
                ['route' => 'FeedbackList','label' => 'Feedback',    'icon' => 'message', 'badge' => $newFeedback,  'badge_tone' => 'info',  'badge_label' => 'new'],
            ],
        ],
        [
            'label' => 'Reporting',
            'items' => [
                ['route' => 'CustomerSatisfactionList', 'label' => 'Satisfaction', 'icon' => 'star'],
                ['route' => 'Summary',                  'label' => 'Reports',      'icon' => 'chart'],
            ],
        ],
        [
            'label' => 'Content',
            'items' => [
                ['route' => 'AdminServices', 'label' => 'Services',  'icon' => 'book'],
                ['route' => 'AdminFAQ',      'label' => 'FAQ',       'icon' => 'help'],
                ['route' => 'AdminCharter',  'label' => 'Charter text', 'icon' => 'edit'],
            ],
        ],
    ];
}

/** Human label for the current admin page, shown in the mobile top bar. */
$adminPageLabel = 'Admin';
foreach ($adminGroups as $group) {
    foreach ($group['items'] as $item) {
        if (strtolower($item['route']) === $currentRoute) {
            $adminPageLabel = $item['label'];
        }
    }
}
if ($currentRoute === 'adminsettings') {
    $adminPageLabel = 'Settings';
}

$flash = take_flash();

/** One sidebar link. */
function sidebar_link(array $item, string $currentRoute): void
{
    $active = strtolower($item['route']) === $currentRoute;
    $badge  = $item['badge'] ?? 0;
    ?>
    <a class="side-link<?= $active ? ' is-active' : '' ?>" href="<?= url($item['route']) ?>"
       <?= $active ? 'aria-current="page"' : '' ?>>
      <span class="side-link__icon"><?= icon($item['icon'], 18) ?></span>
      <span class="side-link__label"><?= e($item['label']) ?></span>
      <?php if ($badge > 0): ?>
        <span class="side-badge side-badge--<?= e($item['badge_tone'] ?? 'info') ?>"
              title="<?= e($badge . ' ' . ($item['badge_label'] ?? '')) ?>"><?= $badge > 99 ? '99+' : $badge ?></span>
      <?php endif; ?>
    </a>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en" class="no-js">
<head>
<meta charset="utf-8">
<script>document.documentElement.classList.remove('no-js');</script>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?> | <?= e(APP_LGU) ?></title>
<meta name="description" content="Visitor Management System of the Municipality of Bongabong, Oriental Mindoro - visitor registration, Citizen's Charter, client satisfaction survey, feedback and concerns.">
<meta name="theme-color" content="#1d4ed8">
<link rel="icon" href="<?= asset('assets/seal.png') ?>" type="image/png">
<link rel="apple-touch-icon" href="<?= asset('assets/icon-192.png') ?>">
<link rel="manifest" href="<?= asset('assets/manifest.webmanifest') ?>">
<link rel="preload" as="font" type="font/woff2" href="<?= asset('assets/fonts/inter-latin.woff2') ?>" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="<?= asset('assets/fonts/manrope-latin.woff2') ?>" crossorigin>
<link rel="stylesheet" href="<?= asset_url('css/style.css') ?>">
</head>
<body class="<?= e(trim($bodyClass . ($useSidebar ? ' admin has-sidebar' : ($isAdminArea ? ' admin' : '')) . ($showQueueStrip ? ' has-queue-strip' : ''))) ?>">
<a class="skip-link" href="#main">Skip to main content</a>

<?php if ($useSidebar): ?>

  <!-- ================= admin: sidebar layout ================= -->
  <div class="admin-shell">
    <div class="sidebar-scrim" data-close-sidebar hidden></div>

    <aside class="sidebar" id="adminSidebar" aria-label="Administration">
      <a class="sidebar__brand" href="<?= url('AdminDashboard') ?>">
        <img src="<?= asset('assets/seal.png') ?>" alt="" width="40" height="40">
        <span>
          <strong><?= e(APP_LGU) ?></strong>
          <span>Administration</span>
        </span>
      </a>

      <nav class="sidebar__nav">
        <?php foreach ($adminGroups as $group): ?>
          <?php if ($group['label']): ?>
            <p class="side-group"><?= e($group['label']) ?></p>
          <?php endif; ?>
          <?php foreach ($group['items'] as $item): ?>
            <?php sidebar_link($item, $currentRoute); ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>

      <div class="sidebar__foot">
        <a class="side-link<?= $currentRoute === 'adminsettings' ? ' is-active' : '' ?>" href="<?= url('AdminSettings') ?>">
          <span class="side-link__icon"><?= icon('shield', 18) ?></span>
          <span class="side-link__label">Settings</span>
        </a>
        <a class="side-link side-link--quiet" href="<?= url('') ?>" target="_blank" rel="noopener">
          <span class="side-link__icon"><?= icon('eye', 18) ?></span>
          <span class="side-link__label">View public site</span>
        </a>

        <div class="sidebar__user">
          <span class="sidebar__avatar" aria-hidden="true"><?= e(strtoupper(mb_substr($admin['full_name'], 0, 1))) ?></span>
          <span class="sidebar__who">
            <strong><?= e($admin['full_name']) ?></strong>
            <span><?= e($admin['username']) ?></span>
          </span>
          <a class="sidebar__out" href="<?= url('AdminLogout') ?>" title="Sign out" aria-label="Sign out">
            <?= icon('logout', 18) ?>
          </a>
        </div>
      </div>
    </aside>

    <div class="admin-main">
      <div class="admin-topbar">
        <button class="nav-toggle" type="button" data-open-sidebar aria-expanded="false"
                aria-controls="adminSidebar" aria-label="Open menu">
          <?= icon('menu', 22) ?>
        </button>
        <strong><?= e($adminPageLabel) ?></strong>
        <a class="admin-topbar__out" href="<?= url('AdminLogout') ?>" aria-label="Sign out"><?= icon('logout', 18) ?></a>
      </div>

      <main id="main" class="site-main">
      <?php if ($flash): ?>
        <div class="wrap">
          <div class="flash flash--<?= e($flash['type']) ?>" role="status">
            <?= icon($flash['type'] === 'error' ? 'alert' : 'check-circle', 20) ?>
            <span><?= e($flash['message']) ?></span>
          </div>
        </div>
      <?php endif; ?>

<?php else: ?>

  <!-- ================= public: top bar layout ================= -->
  <header class="site-header">
    <div class="wrap site-header__inner">
      <a class="brand" href="<?= url('') ?>">
        <img class="brand__seal" src="<?= asset('assets/seal.png') ?>" alt="" width="46" height="46">
        <img class="brand__mark" src="<?= asset('assets/logo-m.png') ?>" alt="" width="34" height="34">
        <span class="brand__text">
          <strong><?= e(APP_LGU) ?></strong>
          <span><?= e(APP_NAME) ?></span>
        </span>
      </a>

      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="Open menu">
        <?= icon('menu', 24) ?>
      </button>

      <nav class="site-nav" id="site-nav" aria-label="Main">
        <?php foreach ($publicNav as $item):
            $active = strtolower($item['route']) === $currentRoute; ?>
          <?php /* The live system shows the page you are on as a filled blue pill. */ ?>
          <a class="nav-link<?= $active ? ' nav-link--primary is-active' : '' ?>"
             href="<?= url($item['route']) ?>"<?= $active ? ' aria-current="page"' : '' ?><?= !empty($item['short']) ? ' aria-label="' . e($item['label']) . '"' : '' ?>>
            <?= icon($item['icon'], 18) ?><?php if (!empty($item['short'])): ?><span class="nav-link__full"><?= e($item['label']) ?></span><span class="nav-link__short" aria-hidden="true"><?= e($item['short']) ?></span><?php else: ?><span><?= e($item['label']) ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </header>

  <main id="main" class="site-main">
  <?php if ($flash): ?>
    <div class="wrap">
      <div class="flash flash--<?= e($flash['type']) ?>" role="status">
        <?= icon($flash['type'] === 'error' ? 'alert' : 'check-circle', 20) ?>
        <span><?= e($flash['message']) ?></span>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>
