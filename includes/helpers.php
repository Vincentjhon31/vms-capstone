<?php
/**
 * Shared helpers: escaping, URLs, CSRF, reference numbers, rate limiting,
 * audit logging, and the option lists the public forms are built from.
 */

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------- output ----

/** Escape for HTML. Every value printed into a page goes through this. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build an in-app URL: url('Register') -> /vms-bongabong/Register */
function url(string $route = '', array $query = []): string
{
    $route = ltrim($route, '/');
    if (PRETTY_URLS) {
        $path = BASE_PATH . '/' . $route;
    } else {
        $path = BASE_PATH . '/index.php' . ($route === '' ? '' : '?r=' . rawurlencode($route));
        if ($route !== '' && $query) {
            return $path . '&' . http_build_query($query);
        }
    }
    return $query ? $path . '?' . http_build_query($query) : $path;
}

/** Direct link to a PHP file that is not a route, e.g. the export endpoint. */
function file_url(string $file, array $query = []): string
{
    $path = BASE_PATH . '/' . ltrim($file, '/');
    return $query ? $path . '?' . http_build_query($query) : $path;
}

/** Path to a static file (css, js, images). */
function asset(string $file): string
{
    return BASE_PATH . '/' . ltrim($file, '/');
}

/**
 * An asset URL stamped with the file's own modification time.
 *
 * Cache busting used to be a hand-edited ?v=N in header.php and footer.php,
 * which is exactly the sort of step that gets forgotten - and the kiosk in the
 * lobby has no keyboard, so nobody standing in front of it can clear a stale
 * stylesheet with Ctrl+Shift+R. The stamp now changes by itself whenever the
 * file does, and never otherwise, so a cached copy stays cached until it is
 * genuinely out of date.
 */
function asset_url(string $file): string
{
    $file  = ltrim($file, '/');
    $path  = dirname(__DIR__) . '/' . $file;
    $stamp = is_file($path) ? filemtime($path) : 0;

    return asset($file) . ($stamp ? '?v=' . $stamp : '');
}

/**
 * Newest modification time across everything the browser renders.
 *
 * This is the build stamp the kiosk polls: when it moves, the deployed code has
 * changed and the screen should reload itself. Cheap enough to call on a timer -
 * a few dozen stat() calls against the local filesystem, no database.
 */
function build_stamp(): string
{
    static $stamp = null;
    if ($stamp !== null) {
        return $stamp;
    }

    $root   = dirname(__DIR__);
    $latest = 0;

    foreach (['css', 'js', 'views', 'includes'] as $dir) {
        $path = $root . '/' . $dir;
        if (!is_dir($path)) {
            continue;
        }
        $walk = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($walk as $file) {
            if ($file->isFile()) {
                $latest = max($latest, $file->getMTime());
            }
        }
    }

    foreach (['index.php', 'sw.js', 'assets/malbar.png'] as $file) {
        if (is_file($root . '/' . $file)) {
            $latest = max($latest, filemtime($root . '/' . $file));
        }
    }

    return $stamp = (string) $latest;
}

function redirect(string $route, array $query = []): void
{
    header('Location: ' . url($route, $query));
    exit;
}

/** Absolute URL of the site root - used for the printable QR code. */
function site_url(string $route = '', array $query = []): string
{
    $scheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') == 443) ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . url($route, $query);
}

// ------------------------------------------------------------------ CSRF ----

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Verify a POSTed token; ends the request if it does not match. */
function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        exit('Your session expired. Please go back, reload the page and submit again.');
    }
}

// ------------------------------------------------------- reference numbers ---

/**
 * Next human-readable reference number, e.g. CON-2026-00123.
 * Derived from the highest existing number for the current year, so it stays
 * readable and gapless even if rows are deleted.
 */
function next_ref_no(string $prefix, string $table): string
{
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';
    $last = db_val(
        "SELECT MAX(CAST(SUBSTRING_INDEX(ref_no, '-', -1) AS UNSIGNED)) FROM {$table} WHERE ref_no LIKE ?",
        [$like],
        0
    );
    return sprintf('%s-%s-%05d', $prefix, $year, ((int) $last) + 1);
}

/**
 * Short reference number for a same-day record, e.g. VIS-0903-4817.
 *
 * A visitor pass lives for one afternoon, so a four-digit year in the middle of
 * it carries no information the visitor could use - what they actually want to
 * read back to the desk, or copy off a phone screen, is the shortest string
 * that still identifies them. Month and day replace the year, and the tail is
 * random rather than sequential.
 *
 * Random matters for more than length. A gapless counter published on a receipt
 * tells anyone holding one exactly how many people registered before them, and
 * lets them guess a neighbouring reference and pull up that visit on the
 * tracking page. Four random digits inside one day's namespace do not.
 *
 * Uniqueness is checked against the table rather than assumed; ref_no also
 * carries a UNIQUE index, so a lost race fails the insert rather than merging
 * two visits.
 */
function short_ref_no(string $prefix, string $table, ?string $date = null): string
{
    // $date exists for the demo generator, which back-dates three weeks of
    // visits: a row stamped with today's month-day but last Tuesday's
    // visit_date would be a reference number that contradicts its own record.
    $stamp = date('md', $date !== null ? strtotime($date) : time());

    for ($attempt = 0; $attempt < 25; $attempt++) {
        $ref = sprintf('%s-%s-%04d', $prefix, $stamp, random_int(0, 9999));
        if (!db_val("SELECT 1 FROM {$table} WHERE ref_no = ?", [$ref])) {
            return $ref;
        }
    }

    // 25 collisions in a row means the day's 10,000 slots are genuinely
    // crowded. Widen the tail rather than hand back a duplicate.
    return sprintf('%s-%s-%06d', $prefix, $stamp, random_int(0, 999999));
}

/** Queue number shown to the citizen - resets each day. */
function next_queue_no(): int
{
    return (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE()', [], 0) + 1;
}

/**
 * Every reference-number shape this system has issued.
 *
 * Two live side by side: VIS-0903-4817 for visits, and the older year-based
 * CON-2026-00123 that concerns and feedback still use, because those are quoted
 * back weeks later in correspondence where the year earns its place. Rows
 * created before the change keep their old numbers, so the pattern has to admit
 * both for ever.
 */
const REF_NO_SHAPE = '(CON|FBK|VIS)-\d{4}-\d{4,6}';

/**
 * Pull a reference number out of whatever was scanned or typed.
 *
 * A visitor pass encodes the tracking URL rather than the bare number, so that
 * a visitor pointing a phone camera at it lands on their own status page
 * instead of being shown a string of text they then have to do something with.
 * The desk's scanner therefore receives a URL, and this is what turns it back
 * into a reference number. Typing the number by hand goes through the same
 * door and comes out the same way.
 */
function extract_ref_no(string $input): string
{
    return preg_match('/' . REF_NO_SHAPE . '/', strtoupper(trim($input)), $match) ? $match[0] : '';
}

// ------------------------------------------------------------- the queue ----

/**
 * Who is being served and who is next, for today.
 *
 * Read by the strip on every public page and by api/queue.php on a timer, so it
 * stays to three indexed counts over one day's rows. Queue numbers only -
 * nothing here identifies a person.
 *
 * "Now serving" is the visit the desk called most recently, which is why
 * visitors carries attended_at: registration order is not calling order, and a
 * board that reads them as the same thing points at the wrong person the first
 * time the desk takes someone out of turn.
 */
function queue_state(): array
{
    /* attended_at arrives with install.php, and a deploy is a git pull - so
       there is a window where the new code is live and the column is not. This
       strip renders on every public page, so an unknown-column error there
       would take the whole public site down until someone opened the installer.
       Fall back to registration order for that window instead: the board is
       then only as good as it was before this feature, which is a far better
       failure than a white screen. Remembered per request, so one failed query
       is the entire cost, and it heals itself the moment the column exists. */
    static $hasAttendedAt = true;

    $serving = null;
    if ($hasAttendedAt) {
        try {
            $serving = db_val(
                "SELECT queue_no FROM visitors
                  WHERE visit_date = CURDATE() AND status = 'Attended'
                  ORDER BY attended_at DESC, id DESC LIMIT 1",
                [],
                null
            );
        } catch (PDOException $ex) {
            $hasAttendedAt = false;
        }
    }
    if (!$hasAttendedAt) {
        $serving = db_val(
            "SELECT queue_no FROM visitors
              WHERE visit_date = CURDATE() AND status = 'Attended'
              ORDER BY id DESC LIMIT 1",
            [],
            null
        );
    }

    $next = db_val(
        "SELECT queue_no FROM visitors
          WHERE visit_date = CURDATE() AND status = 'Waiting'
          ORDER BY queue_no ASC LIMIT 1",
        [],
        null
    );

    return [
        'serving' => $serving === null ? null : (int) $serving,
        'next'    => $next === null ? null : (int) $next,
        'waiting' => (int) db_val("SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE() AND status = 'Waiting'", [], 0),
        'today'   => (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date = CURDATE()', [], 0),
    ];
}

/**
 * Mark a visit as being served, stamping when it was called.
 *
 * Shared by the visitor log and the check-in desk so the two cannot drift, and
 * guarded the same way queue_state() is: between a git pull and the installer
 * run that adds attended_at, this still records the status rather than throwing
 * a 500 at whoever pressed the button.
 */
function visitor_mark_attended(int $id): void
{
    try {
        db_run("UPDATE visitors SET status = 'Attended', attended_at = NOW() WHERE id = ?", [$id]);
    } catch (PDOException $ex) {
        db_run("UPDATE visitors SET status = 'Attended' WHERE id = ?", [$id]);
    }
}

/**
 * Set an arbitrary status, stamping attended_at the first time it becomes
 * Attended. Same fallback as above.
 */
function visitor_set_status(int $id, string $status): void
{
    try {
        db_run(
            "UPDATE visitors SET status = ?,
                    attended_at = CASE WHEN ? = 'Attended' AND attended_at IS NULL THEN NOW() ELSE attended_at END
              WHERE id = ?",
            [$status, $status, $id]
        );
    } catch (PDOException $ex) {
        db_run('UPDATE visitors SET status = ? WHERE id = ?', [$status, $id]);
    }
}

/**
 * Where one visit sits in today's queue, for the holder of its reference
 * number. Returns null for a number that is not ours or not from today.
 */
function queue_position(string $refNo): ?array
{
    $visit = db_one(
        'SELECT queue_no, status, visit_date FROM visitors WHERE ref_no = ?',
        [$refNo]
    );
    if (!$visit || $visit['visit_date'] !== date('Y-m-d')) {
        return null;
    }

    $ahead = (int) db_val(
        "SELECT COUNT(*) FROM visitors
          WHERE visit_date = CURDATE() AND status = 'Waiting' AND queue_no < ?",
        [$visit['queue_no']],
        0
    );

    return [
        'queue'  => (int) $visit['queue_no'],
        'status' => $visit['status'],
        'ahead'  => $visit['status'] === 'Waiting' ? $ahead : 0,
    ];
}

// --------------------------------------------------------- request context ---

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

/**
 * Throttle public submissions per IP. Returns false when the caller has gone
 * over RATE_LIMIT_PER_HOUR for this action in the past hour.
 */
function rate_limit_ok(string $action): bool
{
    $ip = client_ip();
    db_run('DELETE FROM rate_limit WHERE created_at < (NOW() - INTERVAL 2 HOUR)');
    $recent = (int) db_val(
        'SELECT COUNT(*) FROM rate_limit WHERE ip_address = ? AND action = ? AND created_at > (NOW() - INTERVAL 1 HOUR)',
        [$ip, $action],
        0
    );
    if ($recent >= RATE_LIMIT_PER_HOUR) {
        return false;
    }
    db_run('INSERT INTO rate_limit (ip_address, action) VALUES (?, ?)', [$ip, $action]);
    return true;
}

/** Record an administrative action. Every admin write calls this. */
function audit(string $action, string $entity, $entityId = null, string $details = ''): void
{
    db_run(
        'INSERT INTO audit_log (actor, action, entity, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)',
        [$_SESSION['admin_username'] ?? 'public', $action, $entity, $entityId, $details, client_ip()]
    );
}

// ----------------------------------------------------------------- flash ----

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ----------------------------------------------------------- option lists ---

/**
 * The eight purposes of visit, English title with its Tagalog subtitle. The
 * Office of the Municipal Administrator shares the building with the Mayor's
 * Office, so its visitors register at the same desk.
 */
function purposes(): array
{
    return [
        'See the Mayor'                => 'Makipagkita sa Mayor',
        'See the Municipal Administrator' => 'Makipagkita sa Municipal Administrator',
        'Avail of Services'            => 'Kumuha ng Serbisyo',
        'Courtesy Visit/Appointment'   => 'Pagbisita/Appointment',
        'Inquiry'                      => 'Katanungan',
        'Attend Meeting'               => 'Dumalo sa Pulong',
        'Document Request'             => 'Kahilingan ng Dokumento',
        'Others'                       => 'Iba Pa',
    ];
}

function feedback_types(): array
{
    return ['Suggestion', 'Commendation', 'Comment', 'Service Improvement', 'Website / System', 'Others'];
}

function concern_types(): array
{
    return ['Service Delay', 'Staff Conduct', 'Facility / Utilities', 'Documentation Issue',
            'Fees and Charges', 'Complaint', 'Request for Assistance', 'Others'];
}

function offices(): array
{
    return [
        'Office of the Mayor',
        'Office of the Mayor - Education & Social Services Unit',
        'Office of the Municipal Administrator',
        "Municipal Treasurer's Office",
        'Municipal Civil Registrar',
        'Municipal Social Welfare and Development Office',
        'Municipal Health Office',
        'Municipal Engineering Office',
        'Business Permits and Licensing Office',
        'Municipal Assessor',
        'Human Resource Management Office',
        'MDRRMO',
        'General Services Office',
    ];
}

function concern_statuses(): array
{
    return ['Received', 'Under Review', 'In Progress', 'Resolved', 'Closed'];
}

function visitor_statuses(): array
{
    return ['Waiting', 'Attended', 'Completed', 'Left'];
}

// ------------------------------------------------------------ formatting ----

function fmt_date($value): string
{
    return $value ? date('M j, Y', strtotime($value)) : '-';
}

function fmt_datetime($value): string
{
    return $value ? date('M j, Y g:i A', strtotime($value)) : '-';
}

function fmt_time($value): string
{
    return $value ? date('g:i A', strtotime($value)) : '-';
}

/** CSS modifier for a status pill, e.g. status--in-progress. */
function status_class(string $status): string
{
    return 'status--' . strtolower(str_replace(' ', '-', $status));
}

/** A concern is overdue when it has sat unresolved past the SLA. */
function is_overdue(array $concern): bool
{
    if (in_array($concern['status'], ['Resolved', 'Closed'], true)) {
        return false;
    }
    $days = (time() - strtotime($concern['date_received'])) / 86400;
    return $days > CONCERN_SLA_DAYS;
}

// ------------------------------------------- charter totals verification ---

/**
 * Pull minutes and days out of a phrase like "3 working days and 25 minutes".
 * Used to re-add a service's totals row from its own steps.
 */
function parse_duration(string $text): array
{
    $text = strtolower($text);
    $minutes = 0;
    $days = 0;
    if (preg_match_all('/(\d+)\s*(?:working\s+|calendar\s+|business\s+)?(minute|min|hour|hr|day)/', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $value = (int) $match[1];
            if (str_starts_with($match[2], 'min'))   $minutes += $value;
            elseif (str_starts_with($match[2], 'h')) $minutes += $value * 60;
            else                                     $days += $value;
        }
    }
    return ['minutes' => $minutes, 'days' => $days];
}

function parse_fee(string $text): float
{
    if (!preg_match('/([\d,]+(?:\.\d+)?)/', str_replace('PHP', '', $text), $match)) {
        return 0.0;
    }
    return (float) str_replace(',', '', $match[1]);
}

function human_duration(array $duration): string
{
    $parts = [];
    if ($duration['days'])    $parts[] = $duration['days'] . ' day(s)';
    if ($duration['minutes']) $parts[] = $duration['minutes'] . ' minute(s)';
    return $parts ? implode(' + ', $parts) : '0';
}

/**
 * Re-add one service's steps and compare against its published totals row.
 * Returns [fee_ok, time_ok, ...details].
 */
function verify_service_totals(array $service, array $steps): array
{
    $feeSum = 0.0;
    $sum = ['minutes' => 0, 'days' => 0];
    foreach ($steps as $step) {
        $feeSum += parse_fee($step['fees']);
        $parsed = parse_duration($step['processing_time']);
        $sum['minutes'] += $parsed['minutes'];
        $sum['days'] += $parsed['days'];
    }
    // A total written as "60 Minutes (1 Hour)" restates the same figure twice.
    $stated     = parse_duration(preg_replace('/\(.*?\)/', '', $service['total_time']));
    $statedFee  = parse_fee($service['total_fee']);

    return [
        'fee_ok'      => abs($feeSum - $statedFee) < 0.01,
        'fee_sum'     => $feeSum,
        'fee_stated'  => $statedFee,
        'time_ok'     => $sum == $stated,
        'time_sum'    => human_duration($sum),
        'time_stated' => $service['total_time'],
        'steps'       => count($steps),
    ];
}

/** Age bracket used by the reports. */
function age_bracket(int $age): string
{
    if ($age <= 17) return '17 and below';
    if ($age <= 25) return '18-25';
    if ($age <= 40) return '26-40';
    if ($age <= 59) return '41-59';
    return '60 and above';
}
