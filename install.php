<?php
/**
 * One-click installer.
 *
 * Creates the database if it is missing, applies sql/schema.sql, seeds the
 * Citizen's Charter content, and then re-checks every service's totals row
 * against the sum of its own steps (RA 11032 - the published Charter has to
 * be accurate, and this is what an audit looks at first).
 *
 * Safe to run again: it never overwrites data that is already there unless you
 * ask it to with ?reseed=services.
 *
 * IT LOCKS ITSELF ONCE INSTALLED. The first run is open, because there is no
 * account to sign in as yet; from the moment an administrator exists, this page
 * requires that administrator to be signed in. That matters because the site is
 * deployed by `git pull` - deleting this file from the server does not keep it
 * deleted, the next deploy restores it. Without the lock, ?reseed=services
 * would let anyone on the internet wipe and reseed the Charter.
 */

require_once __DIR__ . '/includes/config.php';

$steps = [];
$fail = null;

/**
 * Has this system already been installed? True once an administrator exists.
 * Any failure here (no database, no table) means "not yet", which is the safe
 * reading: an installer that cannot see an admin account has nothing to guard.
 */
function vms_already_installed(): bool
{
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        return (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
    } catch (Throwable $ex) {
        return false;
    }
}

if (vms_already_installed()) {
    require_once __DIR__ . '/includes/auth.php';

    if (!is_admin()) {
        http_response_code(403);
        $signIn = url('Admin');
        exit(
            '<!doctype html><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Already installed</title>'
            . '<div style="font:16px/1.6 system-ui,Segoe UI,sans-serif;max-width:34rem;margin:12vh auto;padding:0 24px;color:#16202e">'
            . '<h1 style="font-size:1.4rem;margin:0 0 12px">This system is already installed</h1>'
            . '<p style="color:#475569">The installer is closed once an administrator account exists. '
            . 'Sign in first if you need to re-run it.</p>'
            . '<p><a href="' . htmlspecialchars($signIn, ENT_QUOTES) . '" '
            . 'style="display:inline-block;margin-top:8px;padding:12px 22px;border-radius:10px;'
            . 'background:#1d4ed8;color:#fff;text-decoration:none;font-weight:700">Sign in</a></p>'
            . '</div>'
        );
    }
}

function step(string $label, string $detail = '', string $state = 'ok'): void
{
    global $steps;
    $steps[] = ['label' => $label, 'detail' => $detail, 'state' => $state];
}

/* ---- 1. database ------------------------------------------------------- */

try {
    $root = new PDO('mysql:host=' . DB_HOST, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $root->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    step('Database ready', DB_NAME . ' on ' . DB_HOST);
} catch (PDOException $ex) {
    $fail = 'Could not connect to MySQL: ' . $ex->getMessage()
          . ' - start MySQL in the XAMPP control panel, or check the DB_ settings in includes/config.php.';
}

if (!$fail) {
    require_once __DIR__ . '/includes/helpers.php';

    /* ---- 2. schema ----------------------------------------------------- */

    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
    // Drop comment lines first - a statement preceded by one must still run.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $created = 0;
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        db()->exec($statement);
        $created++;
    }
    step('Schema applied', $created . ' statements from sql/schema.sql');

    // Columns added after the first release - add them to installs that predate them.
    $added = 0;
    foreach (['visitors', 'concerns', 'feedback'] as $table) {
        $exists = (int) db_val(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [DB_NAME, $table, 'is_demo'],
            0
        );
        if (!$exists) {
            db()->exec("ALTER TABLE {$table} ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0");
            $added++;
        }
    }
    if ($added) {
        step('Schema updated', $added . ' table(s) gained the is_demo column');
    }

    // The public "now serving" board orders by when the desk called a visitor,
    // which registration order does not tell it.
    $hasAttendedAt = (int) db_val(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [DB_NAME, 'visitors', 'attended_at'],
        0
    );
    if (!$hasAttendedAt) {
        db()->exec('ALTER TABLE visitors ADD COLUMN attended_at DATETIME DEFAULT NULL AFTER time_out');
        // Existing attended rows have no recorded moment; time_out is the
        // closest thing that is true, and time_in is true of every row.
        db()->exec("UPDATE visitors SET attended_at = COALESCE(time_out, time_in)
                    WHERE attended_at IS NULL AND status <> 'Waiting'");
        step('Schema updated', 'visitors gained the attended_at column');
    }

    // The Citizen's Charter 2026 lists services per office, as External or
    // Internal, and its fees and times run longer than the first release's columns.
    $column = fn (string $table, string $name) => db_one(
        'SELECT CHARACTER_MAXIMUM_LENGTH AS len FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [DB_NAME, $table, $name]
    );
    $charterChanges = [];
    foreach ([
        'office_code'  => 'ADD COLUMN office_code VARCHAR(40) DEFAULT NULL AFTER active',
        'service_type' => "ADD COLUMN service_type ENUM('external','internal') NOT NULL DEFAULT 'external' AFTER office_code",
        'group_name'   => 'ADD COLUMN group_name VARCHAR(150) DEFAULT NULL AFTER service_type',
    ] as $name => $ddl) {
        if (!$column('services', $name)) {
            db()->exec('ALTER TABLE services ' . $ddl);
            $charterChanges[] = 'services.' . $name;
        }
    }
    foreach ([
        ['services', 'classification', 120, 'VARCHAR(120) NOT NULL'],
        ['services', 'transaction_type', 255, 'VARCHAR(255) NOT NULL'],
        ['services', 'total_fee', 500, "VARCHAR(500) NOT NULL DEFAULT 'PHP 0'"],
        ['services', 'total_time', 255, 'VARCHAR(255) NOT NULL'],
        ['service_steps', 'fees', 500, "VARCHAR(500) NOT NULL DEFAULT 'None'"],
        ['service_steps', 'processing_time', 255, 'VARCHAR(255) NOT NULL'],
    ] as [$table, $name, $length, $type]) {
        $current = $column($table, $name);
        if ($current && (int) $current['len'] < $length) {
            db()->exec("ALTER TABLE {$table} MODIFY {$name} {$type}");
            $charterChanges[] = "{$table}.{$name} widened";
        }
    }
    $hasOfficeIndex = (int) db_val(
        'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
        [DB_NAME, 'services', 'idx_office_code'],
        0
    );
    if (!$hasOfficeIndex) {
        db()->exec('ALTER TABLE services ADD INDEX idx_office_code (office_code)');
    }
    if ($charterChanges) {
        step('Schema updated', 'for the Citizen\'s Charter 2026: ' . implode(', ', $charterChanges));
    }

    /* ---- 3. seeds ------------------------------------------------------ */

    $seed = require __DIR__ . '/includes/seed_data.php';
    $reseed = ($_GET['reseed'] ?? '') === 'services';

    // Administrator
    if ((int) db_val('SELECT COUNT(*) FROM admins', [], 0) === 0) {
        db_run(
            'INSERT INTO admins (username, password_hash, full_name) VALUES (?, ?, ?)',
            ['admin', password_hash('Password@1234', PASSWORD_DEFAULT), 'Municipal Administrator']
        );
        step('Administrator created', 'username: admin / password: Password@1234 - change this now', 'warn');
    } else {
        step('Administrator already exists', 'left untouched');
    }

    // Charter content
    if ((int) db_val('SELECT COUNT(*) FROM charter_content', [], 0) === 0) {
        $charter = $seed['charter'];
        db_run(
            'INSERT INTO charter_content (foreword, signatory_title, signatory_office, signatory_place, signatory_year,
                                          vision, mission, address, contact_number, email, office_hours)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$charter['foreword'], $charter['signatory_title'], $charter['signatory_office'], $charter['signatory_place'],
             $charter['signatory_year'], $charter['vision'], $charter['mission'], $charter['address'],
             $charter['contact_number'], $charter['email'], $charter['office_hours']]
        );
        step('Charter content seeded', 'foreword, vision, mission, contact details');
    } else {
        step('Charter content already present', 'left untouched');
    }

    // Services: the Citizen's Charter Handbook 2026, from sql/citizens-charter-2026.json
    require_once __DIR__ . '/includes/charter_data.php';
    $existing = (int) db_val('SELECT COUNT(*) FROM services', [], 0);
    $fromCharter = (int) db_val('SELECT COUNT(*) FROM services WHERE office_code IS NOT NULL', [], 0);
    if ($existing === 0 || $fromCharter === 0 || $reseed) {
        if ($reseed) {
            db()->exec('DELETE FROM services');
        } elseif ($existing) {
            // The first release carried the Mayor's Office only, from an earlier
            // transcription. Its rows are hidden rather than deleted, so nothing an
            // administrator typed into them is lost.
            $hidden = db_run('UPDATE services SET active = 0 WHERE office_code IS NULL AND active = 1')->rowCount();
            step('Earlier services hidden', $hidden . " Mayor's Office services from the first release are hidden, not deleted - "
                 . 'the 2026 Charter replaces them. Delete them under Manage Services once you have looked them over.', 'warn');
        }
        $loaded = charter_import();
        step("Citizen's Charter 2026 loaded", $loaded['offices'] . ' offices, ' . $loaded['services'] . ' services ('
             . $loaded['detailed'] . ' with write-ups), ' . $loaded['requirements'] . ' requirements, '
             . $loaded['steps'] . ' process steps');
    } else {
        step('Services already present', $fromCharter . " Charter services - add ?reseed=services to the URL to reload the 2026 Charter and replace them");
        // An office added to the Charter file since this site was installed (the
        // Municipal Administrator's Office was) gets its services; nothing already
        // in the database is touched.
        $known = array_column(db_all('SELECT code FROM charter_offices'), 'code');
        $new = array_values(array_diff(array_column(charter_json()['offices'], 'id'), $known));
        if ($new) {
            $loaded = charter_import($new);
            step('New Charter offices loaded', implode(', ', $new) . ': ' . $loaded['services'] . ' services, '
                 . $loaded['requirements'] . ' requirements, ' . $loaded['steps'] . ' process steps');
        }
    }

    // FAQ
    if ((int) db_val('SELECT COUNT(*) FROM faq_entries', [], 0) === 0) {
        foreach ($seed['faq'] as $entry) {
            db_run(
                'INSERT INTO faq_entries (question, answer, category, sort_order) VALUES (?, ?, ?, ?)',
                [$entry[0], $entry[1], $entry[2], $entry[3]]
            );
        }
        step('FAQ seeded', count($seed['faq']) . ' entries');
    } else {
        step('FAQ already present', 'left untouched');
    }
}

/* ---- 4. audit the totals rows ------------------------------------------ */

$audit = [];
if (!$fail) {
    // Only published services with steps have a totals row to check.
    foreach (db_all('SELECT s.* FROM services s LEFT JOIN charter_offices o ON o.code = s.office_code
                     WHERE s.active = 1 AND EXISTS (SELECT 1 FROM service_steps st WHERE st.service_id = s.id)
                     ORDER BY o.sort_order, s.sort_order, s.service_no') as $service) {
        $stepRows = db_all('SELECT * FROM service_steps WHERE service_id = ? ORDER BY sort_order', [$service['id']]);
        $audit[] = verify_service_totals($service, $stepRows) + [
            'no'   => $service['service_no'],
            'name' => $service['name'],
        ];
    }
}
$auditFailures = count(array_filter($audit, fn ($row) => !$row['fee_ok'] || !$row['time_ok']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install | Bongabong VMS</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<main class="site-main">
<div class="wrap wrap--narrow">
  <div class="card">
    <div class="card__header">
      <img src="assets/seal.png" alt="">
      <h1>Visitor Management System - Setup</h1>
      <p>Municipality of Bongabong</p>
    </div>
    <div class="card__body">

      <?php if ($fail): ?>
        <div class="flash flash--error"><?= htmlspecialchars($fail) ?></div>
      <?php else: ?>
        <table class="data" style="min-width:0;margin-bottom:22px">
          <tbody>
          <?php foreach ($steps as $item): ?>
            <tr>
              <td style="width:34px"><?= $item['state'] === 'warn' ? '&#9888;&#65039;' : '&#9989;' ?></td>
              <td><strong><?= htmlspecialchars($item['label']) ?></strong>
                <?php if ($item['detail']): ?><br><span class="small muted"><?= htmlspecialchars($item['detail']) ?></span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>

        <h2 class="subhead">Charter totals audit</h2>
        <p class="small muted">Each service's totals row is re-added from its own process steps.</p>
        <div class="table-scroll">
          <table class="data">
            <thead><tr><th>#</th><th>Service</th><th>Steps</th><th>Fees</th><th>Processing time</th></tr></thead>
            <tbody>
            <?php foreach ($audit as $row): ?>
              <tr>
                <td><?= (int) $row['no'] ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td class="num"><?= (int) $row['steps'] ?></td>
                <td class="num"><?= $row['fee_ok']
                    ? '&#9989; PHP ' . number_format($row['fee_stated'], 2)
                    : '&#10060; steps say ' . number_format($row['fee_sum'], 2) . ', total says ' . number_format($row['fee_stated'], 2) ?></td>
                <td class="num"><?= $row['time_ok']
                    ? '&#9989; ' . htmlspecialchars($row['time_stated'])
                    : '&#10060; steps say ' . htmlspecialchars($row['time_sum']) . ', total says ' . htmlspecialchars($row['time_stated']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <?php if ($auditFailures === 0 && $audit): ?>
          <div class="flash flash--success" style="margin-top:18px">
            All <?= count($audit) ?> services balance: every totals row equals the sum of its steps.
          </div>
        <?php elseif ($audit): ?>
          <div class="flash flash--error" style="margin-top:18px">
            <?= $auditFailures ?> service(s) do not balance. Fix them under Content &rarr; Manage Services before publishing.
          </div>
        <?php endif; ?>

        <div class="note mt-24">
          <span><strong>Next:</strong> delete or rename <code>install.php</code> on a live server, then
            <a href="index.php">open the system</a> and sign in at
            <a href="index.php?r=Admin">/Admin</a> to change the default password.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
</main>
</body>
</html>
