<?php
/**
 * Administrator authentication.
 *
 * The guard runs in PHP before a view is included, so an unauthenticated user
 * hitting an admin URL is redirected to the login page and never receives the
 * admin page shell.
 */

require_once __DIR__ . '/helpers.php';

/** Session lifetime for an admin, in seconds. */
const ADMIN_SESSION_TTL = 7200;

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    if ((time() - ($_SESSION['admin_seen'] ?? 0)) > ADMIN_SESSION_TTL) {
        admin_logout();
        return null;
    }
    $_SESSION['admin_seen'] = time();
    return [
        'id'        => $_SESSION['admin_id'],
        'username'  => $_SESSION['admin_username'],
        'full_name' => $_SESSION['admin_name'],
    ];
}

function is_admin(): bool
{
    return current_admin() !== null;
}

/** Route guard for every admin page. */
function require_admin(): array
{
    $admin = current_admin();
    if ($admin === null) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('Please sign in to continue.', 'error');
        redirect('Admin');
    }
    return $admin;
}

function admin_login(string $username, string $password): bool
{
    $admin = db_one('SELECT * FROM admins WHERE username = ?', [$username]);
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        audit('login_failed', 'admin', null, 'username: ' . $username);
        return false;
    }
    session_regenerate_id(true);
    $_SESSION['admin_id']       = (int) $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name']     = $admin['full_name'];
    $_SESSION['admin_seen']     = time();
    db_run('UPDATE admins SET last_login = NOW() WHERE id = ?', [$admin['id']]);
    audit('login', 'admin', $admin['id']);
    return true;
}

function admin_logout(): void
{
    if (!empty($_SESSION['admin_id'])) {
        audit('logout', 'admin', $_SESSION['admin_id']);
    }
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_name'], $_SESSION['admin_seen']);
}

function change_admin_password(int $adminId, string $current, string $new): bool
{
    $admin = db_one('SELECT * FROM admins WHERE id = ?', [$adminId]);
    if (!$admin || !password_verify($current, $admin['password_hash'])) {
        return false;
    }
    db_run('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $adminId]);
    audit('password_changed', 'admin', $adminId);
    return true;
}
