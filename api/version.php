<?php
/**
 * Build stamp endpoint - what the kiosk polls to notice a deploy.
 *
 * Returns the newest modification time across the app's own code. When that
 * number moves, the site has been updated and any screen still showing the old
 * one should reload itself. Deliberately free of database access and of session
 * handling: this is hit on a timer by every open tab, and it must stay cheap.
 *
 * Served directly by Apache (the rewrite in .htaccess leaves real files alone),
 * so it includes only what it needs.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

echo json_encode(['build' => build_stamp()], JSON_UNESCAPED_SLASHES);
