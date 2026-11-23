<?php
/**
 * Exports for every list and report page: CSV for spreadsheets, and an
 * Excel-readable table. PDF is produced by the browser's print dialog from the
 * print stylesheet, so no PDF library is needed on shared hosting.
 *
 * Administrator only - this is the raw personal data.
 */

require_once __DIR__ . '/../includes/auth.php';

if (!is_admin()) {
    http_response_code(403);
    exit('Administrator sign-in required.');
}

$type   = $_GET['type'] ?? 'visitors';
$format = ($_GET['format'] ?? 'csv') === 'excel' ? 'excel' : 'csv';

/** Build the WHERE clause from the same filters the list pages use. */
function export_dataset(string $type): array
{
    $where = [];
    $params = [];

    switch ($type) {
        case 'concerns':
            foreach (['status' => 'status', 'type' => 'concern_type'] as $key => $column) {
                if (trim((string) ($_GET[$key] ?? '')) !== '') {
                    $where[] = "{$column} = ?";
                    $params[] = $_GET[$key];
                }
            }
            if (trim((string) ($_GET['q'] ?? '')) !== '') {
                $where[] = '(ref_no LIKE ? OR name LIKE ? OR subject LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like, $like);
            }
            $sql = 'SELECT ref_no, name, email, contact, concern_type, subject, description, status,
                           assigned_office, official_response, date_received, date_resolved FROM concerns';
            $headers = ['Reference', 'Name', 'Email', 'Contact', 'Type', 'Subject', 'Description', 'Status',
                        'Assigned office', 'Official response', 'Date received', 'Date resolved'];
            $order = ' ORDER BY date_received DESC';
            break;

        case 'feedback':
            foreach (['status' => 'status', 'type' => 'feedback_type'] as $key => $column) {
                if (trim((string) ($_GET[$key] ?? '')) !== '') {
                    $where[] = "{$column} = ?";
                    $params[] = $_GET[$key];
                }
            }
            if (trim((string) ($_GET['q'] ?? '')) !== '') {
                $where[] = '(ref_no LIKE ? OR name LIKE ? OR message LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like, $like);
            }
            $sql = 'SELECT ref_no, name, email, contact, feedback_type, message, status,
                           official_response, date_received, date_responded FROM feedback';
            $headers = ['Reference', 'Name', 'Email', 'Contact', 'Type', 'Message', 'Status',
                        'Response', 'Date received', 'Date responded'];
            $order = ' ORDER BY date_received DESC';
            break;

        case 'csm':
            $sql = 'SELECT response_date, client_type, service, sex, age, cc1, cc2, cc3,
                           sqd0, sqd1, sqd2, sqd3, sqd4, sqd5, sqd6, sqd7, sqd8, remarks FROM csm_responses';
            $headers = ['Date', 'Client type', 'Service', 'Sex', 'Age', 'CC1', 'CC2', 'CC3',
                        'SQD0', 'SQD1', 'SQD2', 'SQD3', 'SQD4', 'SQD5', 'SQD6', 'SQD7', 'SQD8', 'Remarks'];
            $order = ' ORDER BY response_date DESC';
            break;

        default: // visitors
            if (trim((string) ($_GET['from'] ?? '')) !== '')    { $where[] = 'visit_date >= ?'; $params[] = $_GET['from']; }
            if (trim((string) ($_GET['to'] ?? '')) !== '')      { $where[] = 'visit_date <= ?'; $params[] = $_GET['to']; }
            if (trim((string) ($_GET['purpose'] ?? '')) !== '') { $where[] = 'purpose = ?';     $params[] = $_GET['purpose']; }
            if (trim((string) ($_GET['sex'] ?? '')) !== '')     { $where[] = 'sex = ?';         $params[] = $_GET['sex']; }
            if (trim((string) ($_GET['status'] ?? '')) !== '')  { $where[] = 'status = ?';      $params[] = $_GET['status']; }
            if (trim((string) ($_GET['q'] ?? '')) !== '') {
                $where[] = '(name LIKE ? OR ref_no LIKE ? OR address LIKE ?)';
                $like = '%' . $_GET['q'] . '%';
                array_push($params, $like, $like, $like);
            }
            $sql = 'SELECT ref_no, queue_no, name, sex, age, contact, address, purpose, purpose_other,
                           visit_date, time_in, time_out, status FROM visitors';
            $headers = ['Reference', 'Queue', 'Name', 'Sex', 'Age', 'Contact', 'Address', 'Purpose',
                        'Purpose (other)', 'Date', 'Time in', 'Time out', 'Status'];
            $order = ' ORDER BY time_in DESC';
    }

    $sql .= ($where ? ' WHERE ' . implode(' AND ', $where) : '') . $order;
    return ['headers' => $headers, 'rows' => db_all($sql, $params)];
}

/** The Summary export is a small report workbook rather than a raw table. */
function export_summary(): array
{
    $from = $_GET['from'] ?? date('Y-m-01');
    $to   = $_GET['to'] ?? date('Y-m-d');
    $range = [$from, $to];
    $total = (int) db_val('SELECT COUNT(*) FROM visitors WHERE visit_date BETWEEN ? AND ?', $range, 0);

    $rows = [['Report', APP_LGU . ' - ' . APP_NAME], ['Period', $from . ' to ' . $to], ['', ''],
             ['VISITOR STATISTICS', ''], ['Total visitors', $total]];

    foreach (db_all('SELECT purpose, COUNT(*) AS total FROM visitors WHERE visit_date BETWEEN ? AND ? GROUP BY purpose ORDER BY total DESC', $range) as $row) {
        $rows[] = ['Purpose: ' . $row['purpose'], $row['total']];
    }
    foreach (db_all('SELECT sex, COUNT(*) AS total FROM visitors WHERE visit_date BETWEEN ? AND ? GROUP BY sex', $range) as $row) {
        $rows[] = ['Sex: ' . $row['sex'], $row['total']];
    }
    $brackets = ['17 and below' => 0, '18-25' => 0, '26-40' => 0, '41-59' => 0, '60 and above' => 0];
    foreach (db_all('SELECT age FROM visitors WHERE visit_date BETWEEN ? AND ?', $range) as $row) {
        $brackets[age_bracket((int) $row['age'])]++;
    }
    foreach ($brackets as $label => $count) {
        $rows[] = ['Age ' . $label, $count];
    }

    $rows[] = ['', ''];
    $rows[] = ['CONCERNS AND FEEDBACK', ''];
    $rows[] = ['Concerns received', (int) db_val('SELECT COUNT(*) FROM concerns WHERE DATE(date_received) BETWEEN ? AND ?', $range, 0)];
    $rows[] = ['Concerns resolved', (int) db_val('SELECT COUNT(*) FROM concerns WHERE DATE(date_resolved) BETWEEN ? AND ?', $range, 0)];
    $rows[] = ['Open concerns today', (int) db_val("SELECT COUNT(*) FROM concerns WHERE status NOT IN ('Resolved','Closed')", [], 0)];
    $hours = db_val('SELECT AVG(TIMESTAMPDIFF(HOUR, date_received, date_resolved)) FROM concerns WHERE date_resolved IS NOT NULL AND DATE(date_resolved) BETWEEN ? AND ?', $range);
    $rows[] = ['Average resolution (days)', $hours !== null ? round((float) $hours / 24, 1) : ''];
    $rows[] = ['Feedback received', (int) db_val('SELECT COUNT(*) FROM feedback WHERE DATE(date_received) BETWEEN ? AND ?', $range, 0)];

    $csm = (int) db_val('SELECT COUNT(*) FROM csm_responses WHERE response_date BETWEEN ? AND ?', $range, 0);
    $rows[] = ['', ''];
    $rows[] = ['CLIENT SATISFACTION', ''];
    $rows[] = ['Responses imported', $csm];
    if ($csm) {
        $overall = db_val('SELECT (AVG(COALESCE(sqd1,0)+COALESCE(sqd2,0)+COALESCE(sqd3,0)+COALESCE(sqd4,0)
                                      +COALESCE(sqd5,0)+COALESCE(sqd6,0)+COALESCE(sqd7,0)+COALESCE(sqd8,0))/8)
                           FROM csm_responses WHERE response_date BETWEEN ? AND ?', $range);
        $rows[] = ['Overall average (out of 5)', round((float) $overall, 2)];
    }
    $rows[] = ['', ''];
    $rows[] = ['Generated', date('Y-m-d H:i') . ' by ' . ($_SESSION['admin_username'] ?? '')];

    return ['headers' => ['Item', 'Value'], 'rows' => array_map(fn ($r) => ['item' => $r[0], 'value' => $r[1]], $rows)];
}

$data = $type === 'summary' ? export_summary() : export_dataset($type);
$filename = 'bongabong-vms-' . $type . '-' . date('Ymd-His');
audit('export', $type, null, $format . ', ' . count($data['rows']) . ' rows');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");           // BOM so Excel reads UTF-8 correctly
    fputcsv($out, $data['headers']);
    foreach ($data['rows'] as $row) {
        fputcsv($out, array_values($row));
    }
    fclose($out);
    exit;
}

header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
echo "\xEF\xBB\xBF";
echo '<html><head><meta charset="utf-8"></head><body>';
echo '<table border="1"><thead><tr>';
foreach ($data['headers'] as $header) {
    echo '<th style="background:#1d4ed8;color:#fff">' . e($header) . '</th>';
}
echo '</tr></thead><tbody>';
foreach ($data['rows'] as $row) {
    echo '<tr>';
    foreach ($row as $cell) {
        echo '<td>' . e($cell) . '</td>';
    }
    echo '</tr>';
}
echo '</tbody></table></body></html>';
