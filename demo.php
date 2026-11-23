<?php
/**
 * Sample data for demonstrations and training.
 *
 * Creates realistic visitor, concern and feedback records so the dashboard and
 * reports have something to show, and removes them again in one click. Every
 * record it creates is flagged in the database, so clearing never touches real
 * entries.
 *
 * Administrator only. Delete this file on a live server once training is done.
 */

require_once __DIR__ . '/includes/auth.php';
$admin = require_admin();

$action = $_POST['action'] ?? '';
$message = null;

if ($action !== '' ) {
    csrf_check();
}

if ($action === 'clear') {
    $removed = (int) db_val('SELECT COUNT(*) FROM visitors WHERE is_demo = 1', [], 0)
             + (int) db_val('SELECT COUNT(*) FROM concerns WHERE is_demo = 1', [], 0)
             + (int) db_val('SELECT COUNT(*) FROM feedback WHERE is_demo = 1', [], 0);
    db_run('DELETE FROM visitors WHERE is_demo = 1');
    db_run('DELETE FROM concerns WHERE is_demo = 1');
    db_run('DELETE FROM feedback WHERE is_demo = 1');
    audit('demo_cleared', 'demo', null, $removed . ' records');
    $message = $removed . ' sample record(s) removed.';
}

if ($action === 'seed') {
    $firstNames = ['Maria', 'Jose', 'Ana', 'Pedro', 'Rosario', 'Ricardo', 'Luzviminda', 'Antonio', 'Cristina',
                   'Rodel', 'Jenny', 'Marlon', 'Aileen', 'Ferdinand', 'Grace', 'Noel', 'Divina', 'Bernard'];
    $lastNames  = ['Santos', 'Reyes', 'Dela Cruz', 'Bautista', 'Villanueva', 'Ramos', 'Mercado', 'Aguilar',
                   'Panganiban', 'Marasigan', 'Fabregas', 'Manalo', 'Tolentino', 'Gonzales'];
    $barangays  = ['Poblacion', 'Anilao', 'Bagumbayan I', 'Batangan', 'Camantigue', 'Dayhagan', 'Hagan',
                   'Kaligtasan', 'Labasan', 'Libertad', 'Malitbog', 'Ogbot', 'Sagana', 'Tawas'];
    $purposeList = array_keys(purposes());
    $statuses = ['Completed', 'Completed', 'Completed', 'Attended', 'Waiting', 'Left'];

    $created = 0;
    for ($dayOffset = 20; $dayOffset >= 0; $dayOffset--) {
        $date = date('Y-m-d', strtotime("-{$dayOffset} days"));
        if (in_array(date('N', strtotime($date)), ['6', '7'], true)) {
            continue;                                  // the Municipal Hall is closed at weekends
        }
        $visitsToday = random_int(4, 14);
        for ($visit = 1; $visit <= $visitsToday; $visit++) {
            $hour = random_int(8, 16);
            $timeIn = $date . ' ' . sprintf('%02d:%02d:00', $hour, random_int(0, 59));
            $status = $dayOffset === 0 ? $statuses[array_rand($statuses)] : 'Completed';
            $timeOut = $status === 'Completed'
                ? date('Y-m-d H:i:s', strtotime($timeIn) + random_int(10, 95) * 60)
                : null;
            $purpose = $purposeList[array_rand($purposeList)];

            db_run(
                'INSERT INTO visitors (ref_no, queue_no, name, sex, age, contact, address, purpose, purpose_other,
                                       consent, visit_date, time_in, time_out, attended_at, status, is_demo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 1)',
                [
                    short_ref_no('VIS', 'visitors', $date),
                    $visit,
                    $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
                    random_int(0, 1) ? 'Male' : 'Female',
                    random_int(18, 74),
                    random_int(0, 1) ? '09' . random_int(100000000, 999999999) : null,
                    'Purok ' . random_int(1, 7) . ', Barangay ' . $barangays[array_rand($barangays)] . ', Bongabong',
                    $purpose,
                    $purpose === 'Others' ? 'Follow-up on a previous request' : null,
                    $date, $timeIn, $timeOut,
                    // Anyone past Waiting was called at some point; without this
                    // the queue board has no order to read on the demo data.
                    $status === 'Waiting'
                        ? null
                        : date('Y-m-d H:i:s', strtotime($timeIn) + random_int(2, 40) * 60),
                    $status,
                ]
            );
            $created++;
        }
    }

    $concernSeeds = [
        ['Service Delay', 'Long queue for Mayor\'s Clearance', 'I waited almost two hours last Monday although the Citizen\'s Charter says the clearance takes one hour.', 'Resolved'],
        ['Facility / Utilities', 'No drinking water at the lobby', 'The water dispenser at the ground floor lobby has been empty for a week.', 'In Progress'],
        ['Documentation Issue', 'MEAP requirements unclear', 'The list of requirements posted at the entrance differs from what the coordinator asked for.', 'Under Review'],
        ['Request for Assistance', 'Medical assistance follow-up', 'I submitted my MAIFIPP application two weeks ago and have not heard anything since.', 'Received'],
        ['Staff Conduct', 'Commendation for the front desk', 'The staff at the information counter went out of her way to help my elderly mother.', 'Closed'],
    ];
    foreach ($concernSeeds as $index => $seed) {
        $received = date('Y-m-d H:i:s', strtotime('-' . (2 + $index * 3) . ' days'));
        $resolved = in_array($seed[3], ['Resolved', 'Closed'], true)
            ? date('Y-m-d H:i:s', strtotime($received) + random_int(1, 4) * 86400)
            : null;
        db_run(
            'INSERT INTO concerns (ref_no, name, email, contact, concern_type, subject, description, status,
                                   assigned_office, official_response, date_received, date_resolved, is_demo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                next_ref_no('CON', 'concerns'),
                $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
                'resident' . ($index + 1) . '@example.com',
                '09' . random_int(100000000, 999999999),
                $seed[0], $seed[1], $seed[2], $seed[3],
                $resolved ? 'Office of the Mayor' : null,
                $resolved ? 'Thank you for raising this. The office has acted on your concern.' : null,
                $received, $resolved,
            ]
        );
        $created++;
    }

    $feedbackSeeds = [
        ['Commendation', 'The new QR registration at the entrance is much faster than the logbook.'],
        ['Suggestion', 'Please add a covered waiting area outside the Mayor\'s Office.'],
        ['Service Improvement', 'It would help if the Citizen\'s Charter were also posted at the barangay halls.'],
    ];
    foreach ($feedbackSeeds as $index => $seed) {
        db_run(
            'INSERT INTO feedback (ref_no, name, email, contact, feedback_type, message, status, date_received, is_demo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                next_ref_no('FBK', 'feedback'),
                $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
                'citizen' . ($index + 1) . '@example.com', null,
                $seed[0], $seed[1], $index === 0 ? 'Resolved' : 'Received',
                date('Y-m-d H:i:s', strtotime('-' . ($index + 1) . ' days')),
            ]
        );
        $created++;
    }

    audit('demo_seeded', 'demo', null, $created . ' records');
    $message = $created . ' sample record(s) created.';
}

$demoVisitors = (int) db_val('SELECT COUNT(*) FROM visitors WHERE is_demo = 1', [], 0);

$pageTitle = 'Sample data';
$currentRoute = 'adminsettings';
require __DIR__ . '/includes/header.php';
?>
<div class="wrap wrap--narrow">
  <div class="page-head">
    <div>
      <h1>Sample data</h1>
      <p>For demonstrations and staff training only.</p>
    </div>
    <a class="btn btn--ghost btn--sm" href="<?= url('AdminDashboard') ?>"><?= icon('dashboard', 16) ?> Dashboard</a>
  </div>

  <?php if ($message): ?>
    <div class="flash flash--success"><?= icon('check-circle', 20) ?><span><?= e($message) ?></span></div>
  <?php endif; ?>

  <div class="card">
    <div class="card__body">
      <p>This fills the system with three weeks of plausible visitor traffic, a handful of concerns and some
        feedback, so the dashboard, charts and reports can be shown to staff before the system goes live.</p>
      <p class="small muted">Every sample record is flagged in the database, so clearing removes only what was generated here -
        real registrations and submissions are never touched. There
        <?= $demoVisitors === 1 ? 'is' : 'are' ?> currently <strong><?= number_format($demoVisitors) ?></strong>
        sample visitor record(s).</p>

      <form method="post" action="<?= file_url('demo.php') ?>">
        <?= csrf_field() ?>
        <div class="btn-row">
          <button class="btn btn--primary" type="submit" name="action" value="seed"><?= icon('plus') ?> Create sample data</button>
          <button class="btn btn--danger" type="submit" name="action" value="clear"
                  data-confirm="Remove every sample record?"><?= icon('trash') ?> Remove sample data</button>
        </div>
      </form>

      <div class="note mt-24">
        <?= icon('shield', 20) ?>
        <span>Delete <code>demo.php</code> from the server once training is finished, along with
          <code>install.php</code>.</span>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
