<?php
/** Manage FAQ entries. */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';
    $id = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        db_run('DELETE FROM faq_entries WHERE id = ?', [$id]);
        audit('faq_deleted', 'faq', $id);
        flash('FAQ entry deleted.');
        redirect('AdminFAQ');
    }

    $question = trim((string) ($_POST['question'] ?? ''));
    $answer   = trim((string) ($_POST['answer'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? 'General')) ?: 'General';
    $order    = (int) ($_POST['sort_order'] ?? 0);
    $active   = empty($_POST['active']) ? 0 : 1;

    if ($question === '' || $answer === '') {
        flash('A question and an answer are both required.', 'error');
        redirect('AdminFAQ');
    }

    if ($id) {
        db_run('UPDATE faq_entries SET question = ?, answer = ?, category = ?, sort_order = ?, active = ? WHERE id = ?',
               [$question, $answer, $category, $order, $active, $id]);
        audit('faq_updated', 'faq', $id, $question);
        flash('FAQ entry updated.');
    } else {
        $newId = db_insert('INSERT INTO faq_entries (question, answer, category, sort_order, active) VALUES (?, ?, ?, ?, ?)',
                           [$question, $answer, $category, $order, $active]);
        audit('faq_created', 'faq', $newId, $question);
        flash('FAQ entry added.');
    }
    redirect('AdminFAQ');
}

$entries = db_all('SELECT * FROM faq_entries ORDER BY category, sort_order, id');
$editing = ($id = (int) ($_GET['id'] ?? 0)) ? db_one('SELECT * FROM faq_entries WHERE id = ?', [$id]) : null;
$categories = array_values(array_unique(array_column($entries, 'category')));

require __DIR__ . '/../../includes/header.php';
?>
<div class="wrap">
  <div class="page-head">
    <div>
      <h1>Manage FAQ</h1>
      <p>Shown to the public at <a href="<?= url('FAQ') ?>">/FAQ</a>.</p>
    </div>
  </div>

  <div class="panel-grid">
    <div class="card" style="grid-column:1 / -1">
      <div class="section-bar"><h2><?= $editing ? 'Edit entry' : 'Add an entry' ?></h2></div>
      <div class="card__body">
        <form method="post" action="<?= url('AdminFAQ') ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) ($editing['id'] ?? 0) ?>">

          <div class="field">
            <label class="label" for="question">Question</label>
            <input class="input" type="text" id="question" name="question" value="<?= e($editing['question'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label class="label" for="answer">Answer</label>
            <textarea class="textarea" id="answer" name="answer" required><?= e($editing['answer'] ?? '') ?></textarea>
            <span class="field-hint">A Filipino translation after a slash helps, e.g. "Yes. / Opo."</span>
          </div>
          <div class="grid-3">
            <div class="field">
              <label class="label" for="category">Category</label>
              <input class="input" type="text" id="category" name="category" list="categoryList" value="<?= e($editing['category'] ?? 'General') ?>">
              <datalist id="categoryList">
                <?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div class="field">
              <label class="label" for="sort_order">Display order</label>
              <input class="input" type="number" id="sort_order" name="sort_order" value="<?= e($editing['sort_order'] ?? count($entries) + 1) ?>">
            </div>
            <div class="field">
              <label class="label">Visibility</label>
              <label style="display:flex;gap:10px;align-items:center;min-height:48px">
                <input type="checkbox" name="active" value="1" <?= ($editing['active'] ?? 1) ? 'checked' : '' ?> style="width:20px;height:20px;accent-color:var(--blue)">
                <span>Show publicly</span>
              </label>
            </div>
          </div>

          <div class="btn-row">
            <button class="btn btn--primary" type="submit"><?= icon('save') ?> <?= $editing ? 'Save entry' : 'Add entry' ?></button>
            <?php if ($editing): ?>
              <a class="btn btn--ghost" href="<?= url('AdminFAQ') ?>">Cancel</a>
              <button class="btn btn--danger" type="submit" name="action" value="delete"
                      data-confirm="Delete this FAQ entry?"><?= icon('trash') ?> Delete</button>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card__body">
      <div class="table-scroll">
        <table class="data">
          <thead><tr><th>Category</th><th>Question</th><th>Order</th><th>Public</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($entries as $entry): ?>
            <tr>
              <td class="small nowrap"><?= e($entry['category']) ?></td>
              <td>
                <strong><?= e($entry['question']) ?></strong><br>
                <span class="small muted"><?= e(mb_strimwidth($entry['answer'], 0, 120, '...')) ?></span>
              </td>
              <td class="num"><?= (int) $entry['sort_order'] ?></td>
              <td><?= $entry['active'] ? '<span class="status status--resolved">Yes</span>' : '<span class="status status--closed">Hidden</span>' ?></td>
              <td class="nowrap"><a class="btn btn--ghost btn--sm" href="<?= url('AdminFAQ', ['id' => $entry['id']]) ?>"><?= icon('edit', 15) ?> Edit</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (!$entries): ?>
        <div class="empty"><?= icon('help', 34) ?><p>No FAQ entries yet.</p></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
