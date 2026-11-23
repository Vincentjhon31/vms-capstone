<?php
/**
 * Satisfaction Results.
 *
 * The survey itself lives in Google Forms, so its responses land in the linked
 * Google Sheet rather than in this database. This page does two honest things:
 * it links staff straight to the form and sheet, and it accepts the sheet's CSV
 * export so the figures can be tabulated and reported from inside the system.
 */

require_once __DIR__ . '/../../includes/charts.php';

/** The nine ARTA service quality dimensions, in their prescribed order. */
function csm_dimensions(): array
{
    return [
        'sqd0' => 'SQD0 - Overall satisfaction with the service',
        'sqd1' => 'SQD1 - Responsiveness (time spent)',
        'sqd2' => 'SQD2 - Reliability (quality of service)',
        'sqd3' => 'SQD3 - Access and facilities',
        'sqd4' => 'SQD4 - Communication',
        'sqd5' => 'SQD5 - Costs',
        'sqd6' => 'SQD6 - Integrity',
        'sqd7' => 'SQD7 - Assurance (staff competence)',
        'sqd8' => 'SQD8 - Outcome of the transaction',
    ];
}

/** Turn a Likert answer - a number or its wording, English or Filipino - into 1-5. */
function csm_score($value): ?int
{
    $value = trim(mb_strtolower((string) $value));
    if ($value === '' || str_contains($value, 'n/a') || str_contains($value, 'hindi ko alam')) {
        return null;
    }
    if (preg_match('/^([1-5])/', $value, $match)) {
        return (int) $match[1];
    }
    if (str_contains($value, 'strongly agree') || str_contains($value, 'lubos'))       return 5;
    if (str_contains($value, 'strongly disagree') || str_contains($value, 'lubos na hindi')) return 1;
    if (str_contains($value, 'disagree') || str_contains($value, 'hindi sumasang'))    return 2;
    if (str_contains($value, 'neither') || str_contains($value, 'neutral'))            return 3;
    if (str_contains($value, 'agree') || str_contains($value, 'sumasang'))             return 4;
    return null;
}

/** Match a CSV header to one of our columns. */
function csm_map_header(string $header): ?string
{
    $header = mb_strtolower(trim($header));
    foreach (['sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8', 'cc1', 'cc2', 'cc3'] as $key) {
        if (str_contains($header, $key)) {
            return $key;
        }
    }
    if (str_contains($header, 'timestamp') || str_contains($header, 'petsa'))    return 'response_date';
    if (str_contains($header, 'uri ng kliyente') || str_contains($header, 'client type')) return 'client_type';
    if (str_contains($header, 'serbisyo') || str_contains($header, 'service'))   return 'service';
    if (str_contains($header, 'kasarian') || $header === 'sex')                  return 'sex';
    if (str_contains($header, 'edad') || $header === 'age')                      return 'age';
    if (str_contains($header, 'suhestyon') || str_contains($header, 'remark')
        || str_contains($header, 'comment') || str_contains($header, 'mungkahi')) return 'remarks';
    return null;
}

$importSummary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'clear') {
        $removed = (int) db_val('SELECT COUNT(*) FROM csm_responses', [], 0);
        db()->exec('TRUNCATE TABLE csm_responses');
        audit('csm_cleared', 'csm', null, $removed . ' rows');
        flash('Imported CSM responses cleared.');
        redirect('CustomerSatisfactionList');
    }

    $file = $_FILES['csv'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('Please choose the CSV file exported from the Google Sheet.', 'error');
        redirect('CustomerSatisfactionList');
    }

    $handle = fopen($file['tmp_name'], 'r');
    $headers = fgetcsv($handle);
    if (!$headers) {
        fclose($handle);
        flash('That file has no header row - export the sheet again as CSV.', 'error');
        redirect('CustomerSatisfactionList');
    }

    $map = [];
    foreach ($headers as $index => $header) {
        $column = csm_map_header((string) $header);
        if ($column && !in_array($column, $map, true)) {
            $map[$index] = $column;
        }
    }

    $imported = 0;
    $skipped = 0;
    while (($row = fgetcsv($handle)) !== false) {
        if (count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
            continue;
        }
        $record = array_fill_keys(
            ['response_date', 'client_type', 'service', 'sex', 'age', 'cc1', 'cc2', 'cc3',
             'sqd0', 'sqd1', 'sqd2', 'sqd3', 'sqd4', 'sqd5', 'sqd6', 'sqd7', 'sqd8', 'remarks'],
            null
        );
        foreach ($map as $index => $column) {
            $value = trim((string) ($row[$index] ?? ''));
            if ($value === '') {
                continue;
            }
            if (str_starts_with($column, 'sqd')) {
                $record[$column] = csm_score($value);
            } elseif ($column === 'response_date') {
                $timestamp = strtotime($value);
                $record[$column] = $timestamp ? date('Y-m-d', $timestamp) : null;
            } else {
                $record[$column] = mb_substr($value, 0, $column === 'remarks' ? 2000 : 250);
            }
        }
        if (!array_filter(array_intersect_key($record, array_flip(array_keys(csm_dimensions()))), fn ($v) => $v !== null)) {
            $skipped++;
            continue;
        }
        db_run(
            'INSERT INTO csm_responses (response_date, client_type, service, sex, age, cc1, cc2, cc3,
                                        sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, remarks)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_values($record)
        );
        $imported++;
    }
    fclose($handle);

    audit('csm_imported', 'csm', null, $imported . ' rows imported, ' . $skipped . ' skipped');
    flash($imported . ' response(s) imported' . ($skipped ? ', ' . $skipped . ' row(s) skipped with no ratings.' : '.'));
    redirect('CustomerSatisfactionList');
}

$total = (int) db_val('SELECT COUNT(*) FROM csm_responses', [], 0);

$averages = [];
$satisfied = [];
foreach (array_keys(csm_dimensions()) as $key) {
    $averages[$key] = $total ? db_val("SELECT AVG({$key}) FROM csm_responses WHERE {$key} IS NOT NULL") : null;
    $satisfied[$key] = $total
        ? [
            'good'  => (int) db_val("SELECT COUNT(*) FROM csm_responses WHERE {$key} >= 4", [], 0),
            'rated' => (int) db_val("SELECT COUNT(*) FROM csm_responses WHERE {$key} IS NOT NULL", [], 0),
          ]
        : ['good' => 0, 'rated' => 0];
}
$scored = array_filter($averages, fn ($value) => $value !== null);
$overall = $scored ? array_sum($scored) / count($scored) : null;

$byClientType = db_all('SELECT client_type, COUNT(*) AS total FROM csm_responses WHERE client_type IS NOT NULL GROUP BY client_type');
$recent = db_all('SELECT * FROM csm_responses ORDER BY response_date DESC, id DESC LIMIT 15');

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Satisfaction Results</h1>
      <p>Client Satisfaction Measurement, harmonised with the ARTA instrument.</p>
    </div>
    <div class="btn-row">
      <a class="btn btn--green btn--sm" href="<?= e(GOOGLE_FORM_URL) ?>" target="_blank" rel="noopener"><?= icon('clipboard', 16) ?> Open the form</a>
      <?php if (GOOGLE_SHEET_URL !== ''): ?>
        <a class="btn btn--ghost btn--sm" href="<?= e(GOOGLE_SHEET_URL) ?>" target="_blank" rel="noopener"><?= icon('grid', 16) ?> Open the sheet</a>
      <?php endif; ?>
      <?php if ($total): ?>
        <a class="btn btn--ghost btn--sm" href="<?= file_url('api/export.php', ['type' => 'csm', 'format' => 'csv']) ?>"><?= icon('download', 16) ?> CSV</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="note" style="margin-bottom:20px">
    <?= icon('help', 20) ?>
    <span>
      The survey runs in Google Forms, so responses land in the linked Google Sheet, not in this database.
      To report from inside the system: in the Sheet choose <strong>File &rarr; Download &rarr;
      Comma Separated Values (.csv)</strong>, then upload it here. Re-uploading adds to what is already stored,
      so clear first if you are replacing a period.
    </span>
  </div>

  <div class="panel-grid" style="margin-bottom:20px">
    <div class="panel">
      <div class="panel__head"><h2>Import responses</h2></div>
      <div class="panel__body">
        <form method="post" action="<?= url('CustomerSatisfactionList') ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="field">
            <label class="label" for="csv"><?= icon('upload', 17) ?> Google Sheet CSV export</label>
            <input class="input" type="file" id="csv" name="csv" accept=".csv,text/csv" required>
            <span class="field-hint">Columns are matched by name - SQD0 to SQD8, CC1 to CC3, client type, service, sex, age and remarks.</span>
          </div>
          <div class="btn-row">
            <button class="btn btn--primary" type="submit"><?= icon('upload') ?> Import CSV</button>
            <?php if ($total): ?>
              <button class="btn btn--danger" type="submit" name="action" value="clear"
                      data-confirm="Delete all <?= $total ?> imported CSM responses? The Google Sheet is not affected.">
                <?= icon('trash') ?> Clear imported data
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>

    <div class="panel">
      <div class="panel__head"><h2>Overall</h2><span class="chip"><?= number_format($total) ?> responses</span></div>
      <div class="panel__body">
        <?php if ($overall !== null): ?>
          <p class="kpi__value" style="font-size:2.6rem"><?= number_format($overall, 2) ?><span style="font-size:1.1rem;color:#94a3b8">/5</span></p>
          <p class="muted small">Average across the nine service quality dimensions.</p>
          <?php if ($byClientType): ?>
            <p class="cat-head">Respondents by client type</p>
            <?php
            $clientData = array_column($byClientType, 'total', 'client_type');
            echo bar_rows(array_map('intval', $clientData));
            ?>
          <?php endif; ?>
        <?php else: ?>
          <div class="empty">
            <?= icon('star', 30) ?>
            <p>No responses imported yet. The live figures are in the Google Sheet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($total): ?>
    <div class="card">
      <div class="section-bar"><h2>Per-dimension averages</h2><p>Service quality dimensions, ARTA harmonised CSM</p></div>
      <div class="card__body">
        <div class="table-scroll">
          <table class="data">
            <thead><tr><th>Dimension</th><th>Responses</th><th>Average</th><th>Satisfied (4-5)</th></tr></thead>
            <tbody>
            <?php foreach (csm_dimensions() as $key => $label): ?>
              <tr>
                <td><?= e($label) ?></td>
                <td class="num"><?= $satisfied[$key]['rated'] ?></td>
                <td class="num"><strong><?= $averages[$key] !== null ? number_format((float) $averages[$key], 2) : '-' ?></strong></td>
                <td class="num">
                  <?= $satisfied[$key]['rated']
                      ? round($satisfied[$key]['good'] / $satisfied[$key]['rated'] * 100) . '%'
                      : '-' ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <tr class="row-total">
              <td>OVERALL</td>
              <td class="num"><?= number_format($total) ?></td>
              <td class="num"><?= $overall !== null ? number_format($overall, 2) : '-' ?></td>
              <td class="num"></td>
            </tr>
            </tbody>
          </table>
        </div>
        <div class="btn-row">
          <button class="btn btn--ghost btn--sm" type="button" data-print><?= icon('printer', 16) ?> Print for reporting</button>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="section-bar"><h2>Latest responses</h2></div>
      <div class="card__body">
        <div class="table-scroll">
          <table class="data">
            <thead>
              <tr><th>Date</th><th>Client type</th><th>Service</th><th>Sex</th><th>Age</th>
                  <?php foreach (array_keys(csm_dimensions()) as $key): ?><th class="num"><?= strtoupper($key) ?></th><?php endforeach; ?>
                  <th>Remarks</th></tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
              <tr>
                <td class="nowrap"><?= e(fmt_date($row['response_date'])) ?></td>
                <td><?= e($row['client_type']) ?></td>
                <td class="small"><?= e($row['service']) ?></td>
                <td><?= e($row['sex']) ?></td>
                <td class="num"><?= e($row['age']) ?></td>
                <?php foreach (array_keys(csm_dimensions()) as $key): ?>
                  <td class="num"><?= $row[$key] !== null ? (int) $row[$key] : '-' ?></td>
                <?php endforeach; ?>
                <td class="small"><?= e(mb_strimwidth((string) $row['remarks'], 0, 90, '...')) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
