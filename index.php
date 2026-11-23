<?php
/**
 * Front controller.
 *
 * Every request comes through here, which is what lets the route guard run in
 * PHP before any admin view is loaded. Routes keep the URL names used by the
 * live system: /Register, /MayorsOfficeCitizensCharter, /AdminDashboard.
 */

require_once __DIR__ . '/includes/auth.php';

$routes = [
    // route            => [view file,                     admin only, page title]
    ''                          => ['public/landing.php',  false, 'Client Satisfaction Measurement & Feedback'],
    'register'                  => ['public/register.php', false, 'Visitor Registration'],
    'customersatisfactionform'  => ['public/survey.php',   false, 'Customer Satisfaction Survey'],
    'feedbackform'              => ['public/feedback.php', false, 'Feedback Form'],
    'concernform'               => ['public/concern.php',  false, 'Other Concern Form'],
    'mayorsofficecitizenscharter' => ['public/charter.php', false, "Mayor's Office Citizen's Charter"],
    'servicesdirectory'         => ['public/services.php', false, 'Services Directory'],
    'organizationalchart'       => ['public/org_chart.php', false, "Office of the Municipal Mayor - Organizational Chart"],
    'faq'                       => ['public/faq.php',      false, 'Frequently Asked Questions'],
    'tracksubmission'           => ['public/track.php',    false, 'Track a Submission'],
    'privacy'                   => ['public/privacy.php',  false, 'Data Privacy Notice'],

    'admin'                     => ['admin/login.php',     false, 'Administrator Sign In'],
    'admindashboard'            => ['admin/dashboard.php', true,  'Admin Dashboard'],
    'log'                       => ['admin/log.php',       true,  'Visitor Log'],
    'checkpoint'                => ['admin/checkpoint.php', true, 'Check-in Desk'],
    'concernlist'               => ['admin/concerns.php',  true,  'Concern Management'],
    'feedbacklist'              => ['admin/feedback.php',  true,  'Feedback Management'],
    'customersatisfactionlist'  => ['admin/csm.php',       true,  'Satisfaction Results'],
    'summary'                   => ['admin/summary.php',   true,  'Reports & Summary'],
    'adminservices'             => ['admin/services_edit.php', true, 'Manage Services'],
    'adminfaq'                  => ['admin/faq_edit.php',  true,  'Manage FAQ'],
    'admincharter'              => ['admin/charter_edit.php', true, 'Manage Charter Content'],
    'adminsettings'             => ['admin/settings.php',  true,  'Settings'],
];

$request = $_GET['r'] ?? '';
$request = trim(preg_replace('#[^A-Za-z0-9_/-]#', '', $request), '/');
$key     = strtolower($request);

if ($key === 'adminlogout') {
    admin_logout();
    flash('You have been signed out.');
    redirect('Admin');
}

if (!isset($routes[$key])) {
    http_response_code(404);
    [$viewFile, $adminOnly, $pageTitle] = ['public/404.php', false, 'Page not found'];
} else {
    [$viewFile, $adminOnly, $pageTitle] = $routes[$key];
}

// Guard first: an unauthenticated visitor never receives the admin shell.
if ($adminOnly) {
    $admin = require_admin();
}

$currentRoute = $key;
require __DIR__ . '/views/' . $viewFile;
