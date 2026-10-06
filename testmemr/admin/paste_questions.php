<?php
require_once __DIR__ . '/../includes/db.php';
require_admin();

$msg = '';
$msg_type = 'info';
$subject_id = (int)($_POST['subject_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subject_id && !empty($_POST['csv'])) {
    /* Get the subject */
    $st = $pdo->prepare('SELECT name, class_level FROM subjects WHERE id=? LIMIT 1');
    $st->execute([$subject_id]);
    $target = $st->fetch();

    if (!$target) {
        $msg = '❌ Invalid subject selected.';
        $msg_type = 'danger';
    } else {
        $lines = preg_split('/\r\n|\r|\n/', $_POST['csv']);
        $inserted = 0; $dup = 0; $bad = 0; $row = 0;

        $ins = $pdo->prepare('INSERT INTO questions
            (subject_id, class_level, exam_type, year, question,
             option_a, option_b, option_c, option_d, correct_index)
            VALUES (?,?,?,?,?,?,?,?,?,?)');
        $chk = $pdo->prepare('SELECT id FROM questions WHERE subject_id=? AND question=? LIMIT 1');

        foreach ($lines as $line) {
            $row++;
            $line = trim($line);
            if ($line === '') continue;
            if ($row === 1 && stripos($line, 'subject_id') !== false) continue;

            $data = str_getcsv($line);
            if (count($data) < 10) { $bad++; continue; }

            $qtext = trim($data[4] ?? '');
            if ($qtext === '') { $bad++; continue; }

            $chk->execute([$subject_id, $qtext]);
            if ($chk->fetch()) { $dup++; continue; }

            try {
                $ins->execute([
                    $subject_id,
                    $target['class_level'],
                    trim($data[2] ?? 'NAT'),
                    trim($data[3] ?? ''),
                    $qtext,
                    trim($data[5] ?? ''), trim($data[6] ?? ''),
                    trim($data[7] ?? ''), trim($data[8] ?? ''),
                    (int)($data[9] ?? 0),
                ]);
                $inserted++;
            } catch (Throwable $e) { $bad++; }
        }

        $msg = "✅ Inserted <b>$inserted</b> into <b>{$target['name']} — {$target['class_level']}</b>. Skipped: $dup duplicates, $bad bad rows.";
        $msg_type = 'success';
    }
}

$subs = $pdo->query("SELECT id, name, class_level FROM subjects WHERE class_level='NAT Grade 8' ORDER BY name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>
<h3>📋 Paste Questions (Grade 8)</h3>
<p class="text-muted">Copy a CSV file's contents, paste below, choose the subject, click Insert.</p>

<?php if ($msg): ?>
  <div class="alert alert-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<form method="post">
  <div class="mb-3">
    <label class="form-label"><b>Choose subject:</b></label>
    <select name="subject_id" class="form-select form-select-lg" required>
      <option value="">-- Choose --</option>
      <?php foreach ($subs as $s): ?>
        <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> — <?= e($s['class_level']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="mb-3">
    <label class="form-label"><b>Paste CSV content here:</b></label>
    <textarea name="csv" class="form-control" rows="12" required
      placeholder="subject_id,class_level,exam_type,year,question,option_a,option_b,option_c,option_d,correct_index&#10;9,NAT Grade 8,NAT,2024,&quot;Question?&quot;,&quot;A&quot;,&quot;B&quot;,&quot;C&quot;,&quot;D&quot;,0"></textarea>
  </div>

  <button class="btn btn-primary btn-lg w-100">📥 Insert Questions</button>
</form>

<a href="index.php" class="btn btn-link mt-3">← Back</a>

<?php include __DIR__ . '/../includes/footer.php'; ?>