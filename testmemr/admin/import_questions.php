<?php
require_once __DIR__ . '/../includes/db.php';
require_admin();

$stage       = $_POST['stage'] ?? 'pick';   // pick → preview → import
$selected_id = (int)($_POST['subject_id'] ?? 0);
$result      = '';
$result_type = 'info';
$preview     = [];
$csv_tmp     = $_FILES['csv']['tmp_name'] ?? ($_POST['csv_tmp'] ?? '');
$csv_name    = $_FILES['csv']['name']    ?? ($_POST['csv_name'] ?? '');

/* ---------- Stage 1: user uploaded the CSV — show preview ---------- */
if ($stage === 'pick' && !empty($_FILES['csv']['tmp_name']) && $selected_id) {

    /* Move uploaded file to a temp location so it survives the next POST */
    $tmp_dir = __DIR__ . '/../uploads/tmp';
    if (!is_dir($tmp_dir)) @mkdir($tmp_dir, 0775, true);
    $tmp_path = $tmp_dir . '/import_' . session_id() . '_' . time() . '.csv';
    move_uploaded_file($_FILES['csv']['tmp_name'], $tmp_path);

    /* Read preview rows */
    $fh = fopen($tmp_path, 'r');
    $i = 0;
    while (($row = fgetcsv($fh)) !== false && $i < 10) {
        if ($i === 0 && !is_numeric($row[0] ?? '')) { $i = -1; continue; } // skip header
        $preview[] = $row;
        $i++;
    }
    fclose($fh);

    $csv_tmp = $tmp_path;

    /* Get target subject details */
    $st = $pdo->prepare('SELECT name, class_level FROM subjects WHERE id=? LIMIT 1');
    $st->execute([$selected_id]);
    $target = $st->fetch();

    /* Show preview screen */
    ?>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <style>
      .prev-banner {
        background: linear-gradient(135deg, #d97706, #f59e0b);
        color:#fff; border-radius:18px; padding:22px; margin-bottom:18px;
      }
      .prev-banner h3 { margin:0; font-weight:800; }
      .prev-banner p { margin:4px 0 0; opacity:.9; font-size:.9rem; }
      .target-chip {
        display:inline-block; background:#0C1E7F; color:#fff;
        padding:6px 14px; border-radius:20px; font-weight:700; font-size:.9rem;
      }
      .prev-card { background:#fff; border-radius:14px; padding:20px; box-shadow:0 6px 20px rgba(0,0,0,.06); }
      .prev-row { padding:10px 12px; border-bottom:1px solid #f1f5f9; font-size:.88rem; }
      .prev-row:last-child { border-bottom:none; }
      .prev-row .q { font-weight:600; color:#0f172a; }
      .prev-row .a { color:#64748b; font-size:.8rem; margin-top:2px; }
      .hint-warn {
        background:#fef3c7; border-left:5px solid #d97706; border-radius:10px;
        padding:14px 16px; font-size:.88rem; color:#78350f; margin-bottom:16px;
      }
      .confirm-btn {
        background: linear-gradient(135deg, #3A7728, #16a34a);
        color:#fff; font-weight:800; border:none; border-radius:12px;
        padding:16px; width:100%; font-size:1.05rem;
      }
      .cancel-btn {
        background:#e5e7eb; color:#374151; font-weight:700; border:none;
        border-radius:12px; padding:16px; width:100%; text-decoration:none; display:block; text-align:center;
      }
      [data-theme="dark"] .prev-card { background: #1e293b; }
      [data-theme="dark"] .prev-row { border-color: #334155; }
      [data-theme="dark"] .prev-row .q { color: #f1f5f9; }
    </style>

    <div class="prev-banner">
      <h3>👀 Preview — Nothing imported yet</h3>
      <p>Check the questions below. If they look right, confirm to import.</p>
    </div>

    <div class="mb-3">
      <b>Target subject:</b>
      <span class="target-chip">
        <?= e($target['name']) ?> — <?= e($target['class_level']) ?>
      </span>
    </div>

    <div class="hint-warn">
      ⚠️ <b>Do these questions match the subject above?</b>
      If the questions look like English but the target is Science, click <b>Cancel</b> and pick the correct subject.
    </div>

    <div class="prev-card mb-3">
      <h6 style="color:#0C1E7F;font-weight:800;margin-bottom:12px;">
        First <?= count($preview) ?> questions:
      </h6>
      <?php foreach ($preview as $i => $row): ?>
        <div class="prev-row">
          <div class="q"><?= ($i+1) ?>. <?= e($row[4] ?? '') ?></div>
          <div class="a">
            A) <?= e($row[5] ?? '') ?> ·
            B) <?= e($row[6] ?? '') ?> ·
            C) <?= e($row[7] ?? '') ?> ·
            D) <?= e($row[8] ?? '') ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="post" class="mb-2">
      <input type="hidden" name="stage" value="import">
      <input type="hidden" name="subject_id" value="<?= $selected_id ?>">
      <input type="hidden" name="csv_tmp" value="<?= e($csv_tmp) ?>">
      <input type="hidden" name="csv_name" value="<?= e($csv_name) ?>">
      <button class="confirm-btn mb-2">✅ Yes, import these into <?= e($target['name']) ?> — <?= e($target['class_level']) ?></button>
    </form>
    <a href="import_questions.php" class="cancel-btn">❌ Cancel — pick a different subject</a>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <?php
    exit;
}

/* ---------- Stage 2: user confirmed — actually import ---------- */
if ($stage === 'import' && $csv_tmp && $selected_id && file_exists($csv_tmp)) {

    $st = $pdo->prepare('SELECT name, class_level FROM subjects WHERE id=? LIMIT 1');
    $st->execute([$selected_id]);
    $target = $st->fetch();

    if (!$target) {
        $result = '❌ Invalid subject.';
        $result_type = 'danger';
    } else {
        $fh = fopen($csv_tmp, 'r');
        $inserted = 0; $skipped_dup = 0; $skipped_bad = 0; $row = 0;

        $ins = $pdo->prepare('INSERT INTO questions
            (subject_id, class_level, exam_type, year, question,
             option_a, option_b, option_c, option_d, correct_index)
            VALUES (?,?,?,?,?,?,?,?,?,?)');

        $checkDup = $pdo->prepare('SELECT id FROM questions WHERE subject_id=? AND question=? LIMIT 1');

        while (($data = fgetcsv($fh)) !== false) {
            $row++;
            if ($row === 1 && !is_numeric($data[0] ?? '')) continue;
            if (count($data) < 10) { $skipped_bad++; continue; }

            $qtext = trim($data[4] ?? '');
            if ($qtext === '') { $skipped_bad++; continue; }

            $checkDup->execute([$selected_id, $qtext]);
            if ($checkDup->fetch()) { $skipped_dup++; continue; }

            try {
                $ins->execute([
                    $selected_id, $target['class_level'],
                    trim($data[2] ?? 'NAT'), trim($data[3] ?? ''),
                    $qtext,
                    trim($data[5] ?? ''), trim($data[6] ?? ''),
                    trim($data[7] ?? ''), trim($data[8] ?? ''),
                    (int)($data[9] ?? 0),
                ]);
                $inserted++;
            } catch (Throwable $e) { $skipped_bad++; }
        }
        fclose($fh);
        @unlink($csv_tmp); // clean up temp file

        $result = "✅ Imported <b>$inserted</b> question(s) into <b>{$target['name']} — {$target['class_level']}</b>.<br>"
                . "↪ Skipped: <b>$skipped_dup</b> duplicates, <b>$skipped_bad</b> bad rows.";
        $result_type = 'success';
    }
}

/* ---------- Stage 0 (default): show the picker form ---------- */
$subjects = $pdo->query('SELECT s.*,
    (SELECT COUNT(*) FROM questions q WHERE q.subject_id=s.id) AS qcount
    FROM subjects s ORDER BY s.class_level, s.name')->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
  .imp-banner {
    background: linear-gradient(135deg, #CE1126 0%, #0C1E7F 50%, #3A7728 100%);
    color: #fff; border-radius: 18px; padding: 22px;
    margin-bottom: 18px; position: relative; overflow: hidden;
    box-shadow: 0 10px 28px rgba(12,30,127,.25);
  }
  .imp-banner::after {
    content: ""; position: absolute; right: -50px; top: -50px;
    width: 160px; height: 160px; background: rgba(255,255,255,.1); border-radius: 50%;
  }
  .imp-banner h3 { margin: 0; font-weight: 800; position: relative; z-index: 2; }
  .imp-banner p  { margin: 4px 0 0; opacity: .9; position: relative; z-index: 2; font-size: .9rem; }
  .imp-card {
    background: var(--card); border-radius: 16px; padding: 24px;
    box-shadow: 0 6px 20px rgba(0,0,0,.06); border: none;
  }
  .imp-label {
    font-weight: 800; color: #0C1E7F; font-size: .85rem;
    text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px;
    display: block;
  }
  [data-theme="dark"] .imp-label { color: #93c5fd; }
  .imp-btn {
    background: linear-gradient(135deg, #0C1E7F, #1e40af, #2563eb);
    color: #fff; font-weight: 800; border: none; border-radius: 12px;
    padding: 15px; width: 100%; font-size: 1.05rem; letter-spacing: .5px;
    box-shadow: 0 8px 22px rgba(12,30,127,.35); transition: transform .15s;
  }
  .imp-btn:hover { transform: translateY(-2px); color: #fff; }
  .hint {
    background: #eff6ff; border-left: 4px solid #2563eb;
    border-radius: 10px; padding: 12px 16px; font-size: .85rem;
    color: #1e3a8a; margin-bottom: 16px;
  }
  [data-theme="dark"] .hint { background: #1e293b; color: #93c5fd; border-color: #3b82f6; }
</style>

<div class="imp-banner">
  <h3>⚡ Auto-Import Questions</h3>
  <p>Preview first, then confirm — no more mis-filed questions.</p>
</div>

<?php if ($result): ?>
  <div class="alert alert-<?= $result_type ?>"><?= $result ?></div>
<?php endif; ?>

<div class="hint">
  🛡️ <b>Two-step import:</b> First you'll see a <b>preview</b> of the questions. If they match the subject you picked, confirm. If not, cancel. Prevents accidental mistakes.
</div>

<div class="imp-card mb-4">
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="stage" value="pick">

    <div class="mb-3">
      <label class="imp-label">🎯 Where should the questions go?</label>
      <select name="subject_id" class="form-select form-select-lg" required>
        <option value="">— Choose Subject & Grade —</option>
        <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>">
            <?= e($s['name']) ?> — <?= e($s['class_level']) ?>
            (<?= (int)$s['qcount'] ?> existing)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="imp-label">📁 CSV File</label>
      <input type="file" name="csv" class="form-control form-control-lg" accept=".csv" required>
    </div>

    <button class="imp-btn">👀 Preview Questions</button>
  </form>
</div>

<h5 class="mt-4 mb-3">📊 Current Question Counts</h5>
<div class="table-responsive">
  <table class="table bg-white">
    <thead style="background:#0C1E7F;color:#fff;">
      <tr><th>Subject</th><th>Grade</th><th>Questions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($subjects as $s): ?>
        <tr>
          <td><b><?= e($s['name']) ?></b></td>
          <td><span class="badge bg-primary"><?= e($s['class_level']) ?></span></td>
          <td>
            <b style="color: <?= (int)$s['qcount'] > 0 ? '#3A7728' : '#9ca3af' ?>;">
              <?= (int)$s['qcount'] ?>
            </b>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<a href="index.php" class="btn btn-outline-secondary mt-3">← Back to Admin Dashboard</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>