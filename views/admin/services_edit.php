<?php
/**
 * Manage the Citizen's Charter services.
 *
 * RA 11032 requires the published Charter to be kept current, so fees,
 * processing times, requirements and steps are all editable here - and the
 * totals row is re-checked against the steps every time a service is saved.
 */

$editId = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        $service = db_one('SELECT * FROM services WHERE id = ?', [$id]);
        if ($service) {
            db_run('DELETE FROM services WHERE id = ?', [$id]);
            audit('service_deleted', 'service', $id, $service['name']);
            flash('Service deleted.');
        }
        redirect('AdminServices');
    }

    $fields = [
        'service_no'       => (int) ($_POST['service_no'] ?? 0),
        'name'             => trim((string) ($_POST['name'] ?? '')),
        'description'      => trim((string) ($_POST['description'] ?? '')),
        'office'           => trim((string) ($_POST['office'] ?? '')),
        'classification'   => trim((string) ($_POST['classification'] ?? '')),
        'transaction_type' => trim((string) ($_POST['transaction_type'] ?? '')),
        'who_may_avail'    => trim((string) ($_POST['who_may_avail'] ?? '')),
        'total_fee'        => trim((string) ($_POST['total_fee'] ?? 'PHP 0')),
        'total_time'       => trim((string) ($_POST['total_time'] ?? '')),
        'active'           => empty($_POST['active']) ? 0 : 1,
        'office_code'      => trim((string) ($_POST['office_code'] ?? '')),
        'service_type'     => ($_POST['service_type'] ?? '') === 'internal' ? 'internal' : 'external',
        'group_name'       => trim((string) ($_POST['group_name'] ?? '')) ?: null,
    ];
    $charterOffice = db_one('SELECT * FROM charter_offices WHERE code = ?', [$fields['office_code']]);

    if ($fields['name'] === '' || !$charterOffice) {
        flash($fields['name'] === '' ? 'The service needs a name.' : 'Choose the office that lists this service.', 'error');
        redirect('AdminServices', $id ? ['id' => $id] : ['id' => 'new']);
    }
    if ($fields['office'] === '') {
        $fields['office'] = $charterOffice['name'];
    }
    if ($fields['service_type'] === 'internal') {
        $fields['group_name'] = null;   // only External lists are split into program groups
    }

    if ($id) {
        db_run(
            'UPDATE services SET service_no = ?, name = ?, description = ?, office = ?, classification = ?,
                                 transaction_type = ?, who_may_avail = ?, total_fee = ?, total_time = ?, active = ?,
                                 office_code = ?, service_type = ?, group_name = ?
             WHERE id = ?',
            [...array_values($fields), $id]
        );
        audit('service_updated', 'service', $id, $fields['name']);
    } else {
        // A new service goes to the end of its office's list.
        $sortOrder = 1 + (int) db_val('SELECT MAX(sort_order) FROM services WHERE office_code = ?', [$fields['office_code']], 0);
        $id = db_insert(
            'INSERT INTO services (service_no, name, description, office, classification, transaction_type,
                                   who_may_avail, total_fee, total_time, active, office_code, service_type, group_name, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [...array_values($fields), $sortOrder]
        );
        audit('service_created', 'service', $id, $fields['name']);
    }

    // Requirements and steps are replaced wholesale - simpler and safer than diffing.
    db_run('DELETE FROM service_requirements WHERE service_id = ?', [$id]);
    foreach (($_POST['requirements'] ?? []) as $order => $row) {
        $requirement = trim((string) ($row['requirement'] ?? ''));
        if ($requirement === '') {
            continue;
        }
        db_run(
            'INSERT INTO service_requirements (service_id, requirement, where_to_secure, sort_order) VALUES (?, ?, ?, ?)',
            [$id, $requirement, trim((string) ($row['where_to_secure'] ?? '')), $order + 1]
        );
    }

    db_run('DELETE FROM service_steps WHERE service_id = ?', [$id]);
    // Agency actions and persons responsible are one per line; browsers send textarea breaks as \r\n.
    $lines = fn ($value) => trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
    foreach (($_POST['steps'] ?? []) as $order => $row) {
        $clientStep = trim((string) ($row['client_step'] ?? ''));
        if ($clientStep === '') {
            continue;
        }
        db_run(
            'INSERT INTO service_steps (service_id, client_step, agency_action, fees, processing_time,
                                        person_responsible, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$id, $clientStep, $lines($row['agency_action'] ?? ''), trim((string) ($row['fees'] ?? 'None')),
             trim((string) ($row['processing_time'] ?? '')), $lines($row['person_responsible'] ?? ''), $order + 1]
        );
    }

    $service = db_one('SELECT * FROM services WHERE id = ?', [$id]);
    $check = verify_service_totals($service, db_all('SELECT * FROM service_steps WHERE service_id = ?', [$id]));
    if ($check['fee_ok'] && $check['time_ok']) {
        flash('Saved. The totals row matches the sum of the steps.');
    } else {
        flash('Saved, but the totals row does not match the steps: steps add up to '
              . $check['time_sum'] . ' and PHP ' . number_format($check['fee_sum'], 2)
              . '. Fix this before the Charter is published.', 'error');
    }
    redirect('AdminServices', ['office' => $fields['office_code'], 'id' => $id]);
}

$charterOffices = db_all('SELECT * FROM charter_offices ORDER BY sort_order, id');
$officeNames = array_column($charterOffices, 'short_name', 'code');
$filterOffice = (string) ($_GET['office'] ?? '');
if (!isset($officeNames[$filterOffice])) {
    $filterOffice = '';
}
$services = db_all(
    'SELECT s.* FROM services s LEFT JOIN charter_offices o ON o.code = s.office_code'
    . ($filterOffice !== '' ? ' WHERE s.office_code = ?' : '')
    . ' ORDER BY o.sort_order IS NULL, o.sort_order, s.sort_order, s.service_no',
    $filterOffice !== '' ? [$filterOffice] : []
);
// Every service's steps in one query, for the totals check column.
$stepsBy = [];
foreach (db_all('SELECT * FROM service_steps ORDER BY service_id, sort_order') as $stepRow) {
    $stepsBy[$stepRow['service_id']][] = $stepRow;
}
$editing = $editId ? db_one('SELECT * FROM services WHERE id = ?', [$editId]) : null;
$editRequirements = $editing ? db_all('SELECT * FROM service_requirements WHERE service_id = ? ORDER BY sort_order', [$editId]) : [];
$editSteps = $editing ? db_all('SELECT * FROM service_steps WHERE service_id = ? ORDER BY sort_order', [$editId]) : [];
if (!$editRequirements) $editRequirements = [['requirement' => '', 'where_to_secure' => '']];
if (!$editSteps) $editSteps = [['client_step' => '', 'agency_action' => '', 'fees' => 'None', 'processing_time' => '', 'person_responsible' => '']];

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Manage Services</h1>
      <p>The Citizen's Charter published in the <a href="<?= url('ServicesDirectory') ?>">Services Directory</a>
        and on the <a href="<?= url('MayorsOfficeCitizensCharter') ?>">Mayor's Office Charter</a>.</p>
    </div>
    <div class="btn-row">
      <a class="btn btn--primary btn--sm" href="<?= url('AdminServices', ['office' => $filterOffice ?: null, 'id' => 'new']) ?>"><?= icon('plus', 16) ?> New service</a>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <form class="toolbar" method="get" action="<?= url('AdminServices') ?>">
        <?php if (!PRETTY_URLS): ?><input type="hidden" name="r" value="AdminServices"><?php endif; ?>
        <div class="field" style="min-width:240px">
          <label for="officeFilter">Office</label>
          <select class="select" id="officeFilter" name="office" data-auto-submit>
            <option value="">All offices</option>
            <?php foreach ($charterOffices as $office): ?>
              <option value="<?= e($office['code']) ?>" <?= $filterOffice === $office['code'] ? 'selected' : '' ?>><?= e($office['short_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <p class="small muted mb-0" style="align-self:center"><?= count($services) ?> service<?= count($services) === 1 ? '' : 's' ?></p>
      </form>
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>#</th><th>Service</th><th>List</th><th>Classification</th><th>Fee</th><th>Time</th><th>Totals check</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($services as $service): ?>
            <?php $serviceSteps = $stepsBy[$service['id']] ?? []; ?>
            <?php $check = verify_service_totals($service, $serviceSteps); ?>
            <tr>
              <td class="num"><?= (int) $service['service_no'] ?></td>
              <td>
                <strong><?= e($service['name']) ?></strong><br>
                <span class="small muted"><?= e($officeNames[$service['office_code']] ?? 'No office - first release') ?><?= $service['active'] ? '' : ' &middot; hidden' ?></span>
              </td>
              <td class="small"><?= $service['service_type'] === 'internal' ? 'Internal' : 'External' ?><?= $service['group_name'] ? '<br><span class="muted">' . e($service['group_name']) . '</span>' : '' ?></td>
              <td class="small"><?= e($service['classification']) ?></td>
              <td class="num small"><?= e($service['total_fee']) ?></td>
              <td class="num small"><?= e($service['total_time']) ?></td>
              <td>
                <?php if (!$serviceSteps): ?>
                  <span class="small muted">No write-up</span>
                <?php elseif ($check['fee_ok'] && $check['time_ok']): ?>
                  <span class="status status--resolved">Balanced</span>
                <?php else: ?>
                  <span class="status status--overdue">Check</span>
                  <span class="small muted" style="display:block">steps: <?= e($check['time_sum']) ?>, PHP <?= number_format($check['fee_sum'], 2) ?></span>
                <?php endif; ?>
              </td>
              <td class="nowrap">
                <a class="btn btn--ghost btn--sm" href="<?= url('AdminServices', ['office' => $filterOffice ?: null, 'id' => $service['id']]) ?>"><?= icon('edit', 15) ?> Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if ($editing || ($_GET['id'] ?? '') === 'new'): ?>
    <div class="card">
      <div class="section-bar">
        <h2><?= $editing ? 'Edit service' : 'New service' ?></h2>
        <p><?= $editing ? e($editing['name']) : 'Add a service to the Citizen\'s Charter' ?></p>
      </div>
      <div class="card__body">
        <form method="post" action="<?= url('AdminServices') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

          <?php $editOffice = $editing['office_code'] ?? $filterOffice; ?>
          <div class="grid-3">
            <div class="field">
              <label class="label" for="office_code">Office that lists it</label>
              <select class="select" id="office_code" name="office_code" required>
                <option value="">Choose an office</option>
                <?php foreach ($charterOffices as $office): ?>
                  <option value="<?= e($office['code']) ?>" <?= $editOffice === $office['code'] ? 'selected' : '' ?>><?= e($office['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label class="label" for="service_type">List</label>
              <select class="select" id="service_type" name="service_type">
                <option value="external">External - for the public</option>
                <option value="internal" <?= ($editing['service_type'] ?? '') === 'internal' ? 'selected' : '' ?>>Internal - for LGU offices and employees</option>
              </select>
            </div>
            <div class="field">
              <label class="label" for="group_name">Program group <span class="muted small">(optional)</span></label>
              <input class="input" type="text" id="group_name" name="group_name" list="groupList" value="<?= e($editing['group_name'] ?? '') ?>"
                     placeholder="e.g. Crops Production Program Services">
              <datalist id="groupList">
                <?php foreach (db_all("SELECT DISTINCT group_name FROM services WHERE group_name IS NOT NULL AND group_name <> '' ORDER BY group_name") as $group): ?><option value="<?= e($group['group_name']) ?>"><?php endforeach; ?>
              </datalist>
            </div>
          </div>

          <div class="grid-2">
            <div class="field">
              <label class="label" for="service_no">Service number</label>
              <input class="input" type="number" id="service_no" name="service_no" min="1"
                     value="<?= e($editing['service_no'] ?? count($services) + 1) ?>">
            </div>
            <div class="field">
              <label class="label" for="classification">Classification</label>
              <input class="input" type="text" id="classification" name="classification" list="classificationList"
                     value="<?= e($editing['classification'] ?? 'Simple') ?>">
              <datalist id="classificationList">
                <?php foreach (['Simple', 'Complex', 'Highly Technical', 'Simple to Complex'] as $option): ?><option value="<?= e($option) ?>"><?php endforeach; ?>
              </datalist>
            </div>
          </div>

          <div class="field">
            <label class="label" for="name">Service name</label>
            <input class="input" type="text" id="name" name="name" value="<?= e($editing['name'] ?? '') ?>" required>
          </div>

          <div class="field">
            <label class="label" for="description">Description</label>
            <textarea class="textarea" id="description" name="description"><?= e($editing['description'] ?? '') ?></textarea>
          </div>

          <div class="grid-2">
            <div class="field">
              <label class="label" for="office">Office / Division <span class="muted small">(as printed on the sheet)</span></label>
              <input class="input" type="text" id="office" name="office" list="officeList" value="<?= e($editing['office'] ?? '') ?>"
                     placeholder="Leave blank to use the office's name">
              <datalist id="officeList">
                <?php foreach (db_all('SELECT DISTINCT office FROM services ORDER BY office') as $office): ?><option value="<?= e($office['office']) ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div class="field">
              <label class="label" for="transaction_type">Transaction type</label>
              <input class="input" type="text" id="transaction_type" name="transaction_type"
                     value="<?= e($editing['transaction_type'] ?? 'G2C') ?>" placeholder="G2C, G2B, G2G, G2NGO">
            </div>
          </div>

          <div class="field">
            <label class="label" for="who_may_avail">Who may avail</label>
            <input class="input" type="text" id="who_may_avail" name="who_may_avail" value="<?= e($editing['who_may_avail'] ?? '') ?>">
          </div>

          <div class="grid-2">
            <div class="field">
              <label class="label" for="total_fee">Total fee</label>
              <input class="input" type="text" id="total_fee" name="total_fee" value="<?= e($editing['total_fee'] ?? 'PHP 0') ?>">
            </div>
            <div class="field">
              <label class="label" for="total_time">Total processing time</label>
              <input class="input" type="text" id="total_time" name="total_time"
                     value="<?= e($editing['total_time'] ?? '') ?>" placeholder="e.g. 3 Working Days and 25 minutes">
            </div>
          </div>

          <h3 class="subhead"><?= icon('check-circle', 18) ?> Checklist of Requirements</h3>
          <div id="requirementRows">
            <?php foreach ($editRequirements as $index => $requirement): ?>
              <div class="grid-2 repeat-row" data-repeat-row style="align-items:end;margin-bottom:10px">
                <div class="field mb-0">
                  <input class="input" type="text" name="requirements[<?= $index ?>][requirement]"
                         value="<?= e($requirement['requirement']) ?>" placeholder="Requirement">
                </div>
                <div class="field mb-0" style="display:flex;gap:8px">
                  <input class="input" type="text" name="requirements[<?= $index ?>][where_to_secure]"
                         value="<?= e($requirement['where_to_secure']) ?>" placeholder="Where to secure">
                  <button class="btn btn--ghost btn--sm" type="button" data-repeat-remove aria-label="Remove requirement"><?= icon('trash', 15) ?></button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" data-repeat-add="#requirementRows"><?= icon('plus', 15) ?> Add requirement</button>

          <h3 class="subhead mt-24"><?= icon('user', 18) ?> Process Flow</h3>
          <div id="stepRows">
            <?php foreach ($editSteps as $index => $step): ?>
              <div class="repeat-row" data-repeat-row style="border:1px solid var(--line);border-radius:10px;padding:12px;margin-bottom:10px">
                <div class="grid-2">
                  <div class="field mb-0">
                    <input class="input" type="text" name="steps[<?= $index ?>][client_step]"
                           value="<?= e($step['client_step']) ?>" placeholder="Client step">
                  </div>
                  <div class="field mb-0">
                    <textarea class="textarea" rows="3" name="steps[<?= $index ?>][agency_action]"
                              placeholder="Agency action - one action per line"><?= e($step['agency_action']) ?></textarea>
                  </div>
                </div>
                <div class="grid-3" style="margin-top:10px">
                  <div class="field mb-0">
                    <input class="input" type="text" name="steps[<?= $index ?>][fees]" value="<?= e($step['fees']) ?>" placeholder="Fees, e.g. None or PHP 100.00">
                  </div>
                  <div class="field mb-0">
                    <input class="input" type="text" name="steps[<?= $index ?>][processing_time]" value="<?= e($step['processing_time']) ?>" placeholder="Time, e.g. 15 minutes">
                  </div>
                  <div class="field mb-0" style="display:flex;gap:8px;align-items:flex-start">
                    <textarea class="textarea" rows="2" name="steps[<?= $index ?>][person_responsible]"
                              placeholder="Person responsible - one per line"><?= e($step['person_responsible']) ?></textarea>
                    <button class="btn btn--ghost btn--sm" type="button" data-repeat-remove aria-label="Remove step"><?= icon('trash', 15) ?></button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
          <button class="btn btn--ghost btn--sm" type="button" data-repeat-add="#stepRows"><?= icon('plus', 15) ?> Add step</button>

          <div class="field mt-24">
            <label style="display:flex;gap:10px;align-items:center">
              <input type="checkbox" name="active" value="1" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--blue)">
              <span>Show this service on the public Citizen's Charter</span>
            </label>
          </div>

          <div class="btn-row">
            <button class="btn btn--primary" type="submit"><?= icon('save') ?> Save service</button>
            <a class="btn btn--ghost" href="<?= url('AdminServices', ['office' => $filterOffice ?: null]) ?>">Cancel</a>
            <?php if ($editing): ?>
              <button class="btn btn--danger" type="submit" name="action" value="delete"
                      data-confirm="Delete &quot;<?= e($editing['name']) ?>&quot; and all its requirements and steps?">
                <?= icon('trash') ?> Delete
              </button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
