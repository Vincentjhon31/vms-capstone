<?php
/**
 * Server-specific credentials - TEMPLATE.
 *
 * Copy this file to config.local.php on the server and fill in the real values.
 * config.local.php is listed in .gitignore and must never be committed.
 *
 * On Hostinger the values come from hPanel > Databases > MySQL Databases.
 * Locally you usually need no copy at all: with this file absent, config.php
 * falls back to the XAMPP defaults (root, no password, vms_bongabong).
 */

define('DB_HOST', 'localhost');            // Hostinger uses localhost too
define('DB_USER', 'u000000000_example');
define('DB_PASS', 'put-the-real-password-here');
define('DB_NAME', 'u000000000_example');

// Optional: the Google Sheet the CSM form writes to, so staff can open it from
// /CustomerSatisfactionList. Leave the line out to hide that button.
// define('GOOGLE_SHEET_URL', 'https://docs.google.com/spreadsheets/d/.../edit');
