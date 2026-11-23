<?php
/**
 * Bongabong VMS - configuration.
 *
 * NO DATABASE PASSWORD BELONGS IN THIS FILE. It is committed to git, so a
 * password here would be published with the code.
 *
 * Real credentials live in includes/config.local.php, which .gitignore keeps
 * out of the repository. That file exists only on the machine it belongs to:
 * on the Hostinger account it holds the live database, and locally it is simply
 * absent, so the XAMPP defaults below apply.
 *
 * Copy config.local.example.php to config.local.php to set one up. See DEPLOY.md.
 */

/** True when this request is being served from a local development machine. */
function vms_is_local(): bool
{
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
    if ($host !== '') {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test');
    }
    // CLI (install scripts, cron): fall back to the machine name.
    return in_array(strtolower(php_uname('n')), ['localhost'], true) || PHP_OS_FAMILY === 'Windows';
}

// Server-specific secrets, if this machine has any. The file defines whichever
// DB_* constants it needs; anything it leaves out falls through to the defaults.
if (is_readable(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// ---- Defaults: local XAMPP -------------------------------------------------
// defined() guards mean config.local.php always wins where it has spoken.
defined('DB_HOST') || define('DB_HOST', 'localhost');
defined('DB_USER') || define('DB_USER', 'root');
defined('DB_PASS') || define('DB_PASS', '');
defined('DB_NAME') || define('DB_NAME', 'vms_bongabong');

// A live server with no config.local.php would silently try the XAMPP defaults
// and fail with a confusing connection error. Say what is actually wrong.
if (!vms_is_local() && DB_USER === 'root' && DB_PASS === '') {
    http_response_code(500);
    exit(
        '<h1 style="font:600 20px system-ui;margin:40px">Configuration missing</h1>'
        . '<p style="font:15px system-ui;margin:0 40px">This server has no '
        . '<code>includes/config.local.php</code>. Copy <code>config.local.example.php</code> '
        . 'to <code>config.local.php</code> and fill in the database credentials.</p>'
    );
}

// ---- Application -----------------------------------------------------------
define('APP_NAME',      'Visitor Management System');
define('APP_LGU',       'Municipality of Bongabong');
define('APP_PROVINCE',  'Lalawigan ng Or. Mindoro');
define('APP_YEAR',      '2026');

// The live ARTA-harmonised Client Satisfaction Measurement form. Kept as the
// source of truth for the survey - see README.
define('GOOGLE_FORM_URL', 'https://docs.google.com/forms/d/e/1FAIpQLSeaEGbjnyQuamowxx_RLIPWwCFYJUJWIQDoxiQYFGqWz0OXNA/viewform');
define('GOOGLE_FORM_EMBED_URL', GOOGLE_FORM_URL . '?embedded=true');

// Where the CSM responses actually land. Set this to your linked Google Sheet
// so /CustomerSatisfactionList can link staff straight to it. config.local.php
// may set it instead, which is why this is guarded.
defined('GOOGLE_SHEET_URL') || define('GOOGLE_SHEET_URL', '');

// Personal data retention, in days (Data Privacy Act, RA 10173).
define('RETENTION_DAYS', 365);

// Working days a concern may sit before it is flagged overdue.
define('CONCERN_SLA_DAYS', 3);

// Public submissions allowed per IP per hour, per form.
define('RATE_LIMIT_PER_HOUR', 10);

// Philippine Standard Time. Everything the system records and shows is in this
// zone: PHP is set to it here, and includes/db.php sets the MySQL session to the
// matching offset so NOW() and CURDATE() agree with PHP. Without that second
// half, a server whose MySQL runs on UTC (Hostinger's does) writes time_in eight
// hours behind the wall clock in the office.
define('APP_TIMEZONE', 'Asia/Manila');
date_default_timezone_set(APP_TIMEZONE);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

/** Folder the app is served from, e.g. "/vms-bongabong" or "" at a domain root. */
define('BASE_PATH', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'));

/** .htaccess sets this when mod_rewrite is available, so /Register works. */
define('PRETTY_URLS', ($_SERVER['VMS_REWRITE'] ?? getenv('VMS_REWRITE')) === '1');
