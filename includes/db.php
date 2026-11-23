<?php
/**
 * Database connection and query helpers.
 *
 * Everything goes through prepared statements - this app takes input from the
 * public internet, so no string-built SQL anywhere.
 */

require_once __DIR__ . '/config.php';

/**
 * APP_TIMEZONE as a MySQL offset string, e.g. "+08:00".
 *
 * Taken for the current moment, so a zone that observes DST stays correct as it
 * changes. Manila never does, but the code should not depend on that.
 */
function vms_db_utc_offset(): string
{
    $now = new DateTime('now', new DateTimeZone(APP_TIMEZONE));
    $seconds = $now->getOffset();
    return sprintf('%s%02d:%02d', $seconds < 0 ? '-' : '+', intdiv(abs($seconds), 3600), intdiv(abs($seconds) % 3600, 60));
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        // MySQL keeps its own clock. NOW(), CURDATE() and CURRENT_TIMESTAMP read
        // the session time zone, which defaults to the database server's system
        // zone - UTC on Hostinger. PHP being on Asia/Manila does not change that,
        // so without this line time_in and time_out are written eight hours behind
        // the office wall clock, and CURDATE() rolls the queue over at 8am.
        //
        // The offset is derived from APP_TIMEZONE rather than hard-coded, and it
        // is a numeric offset rather than the name: named zones need the
        // mysql.time_zone tables loaded, which shared hosting usually leaves empty.
        $pdo->exec("SET time_zone = '" . vms_db_utc_offset() . "'");
    } catch (PDOException $ex) {
        http_response_code(500);
        exit(
            '<h1 style="font:600 20px system-ui;margin:40px">Database connection failed</h1>'
            . '<p style="font:15px system-ui;margin:0 40px">Start MySQL in the XAMPP control panel, then run '
            . '<a href="install.php">install.php</a> to create the database.</p>'
            . '<pre style="font:13px ui-monospace;margin:20px 40px;color:#b91c1c">'
            . htmlspecialchars($ex->getMessage()) . '</pre>'
        );
    }
    return $pdo;
}

/**
 * What this database server's clock says when nobody has pinned it.
 *
 * Opens a second, unpinned connection purely to read NOW() as the server would
 * have answered before includes/db.php set the session zone. Only the Settings
 * page asks for this, and only to show an administrator whether the host needs
 * the pin at all. Null if the connection fails - it is a diagnostic, not a
 * reason to break the page.
 */
function db_unpinned_now(): ?string
{
    try {
        $raw = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        return (string) $raw->query('SELECT NOW()')->fetchColumn();
    } catch (Throwable $ex) {
        return null;
    }
}

/** Run a statement, return the PDOStatement. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** All matching rows. */
function db_all(string $sql, array $params = []): array
{
    return db_run($sql, $params)->fetchAll();
}

/** First matching row, or null. */
function db_one(string $sql, array $params = []): ?array
{
    $row = db_run($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** First column of the first row - counts, sums, single values. */
function db_val(string $sql, array $params = [], $default = null)
{
    $val = db_run($sql, $params)->fetchColumn();
    return $val === false ? $default : $val;
}

/** Insert and return the new id. */
function db_insert(string $sql, array $params = []): int
{
    db_run($sql, $params);
    return (int) db()->lastInsertId();
}
