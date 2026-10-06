<?php
require_once __DIR__ . '/../includes/db.php';
require_admin();

$result = '';
$result_type = 'info';

/* ---------- Handle upload ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['target']) && !empty($_FILES['csvs'])) {

    $target = $_POST['target'];

    /* Map the 4 G8 target names to real subject_id + class_level */
    $targets = [];
    $rows = $pdo->query("SELECT id, name, class_level FROM subjects WHERE class_level = 'NAT Grade 8'")->fetchAll();
    foreach ($rows as $r) {
        $key = $r['name'] . '_G8';
        $targets[$key] = [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'class_level' => $r['class_level'],
        ];
    }

    if (!isset($targets[$target])) {
        $result = '❌ Invalid target subject.';
        $result_type = 'danger';
    } else {
        $info = $targets[$target];
        $total_inserted = 0;
        $total_dup = 0;
        $total_bad = 0;
        $files_done = 0;

        $ins = $pdo->prepare('INSERT INTO questions
            (subject_id, class_level, exam_type, year, question,
             option_a, option_b, option_c, option_d, correct_index)
            VALUES (?,?,?,?,?,?,?,?,?,?)');

        $dup = $pdo->prepare('SELECT id FROM questions WHERE subject_id=? AND question=? LIMIT 1');

        /* Loop through each uploaded file */
        $names = $_FILES['csvs']['name'];
        $tmps  = $_FILES['csvs']['tmp_name'];

        for ($i = 0; $i < count($names); $i++) {
            if (empty($tmps[$i])) continue;

            $fh = fopen($tmps[$i], 'r');
            $row = 0;

            while (($data = fgetcsv($fh)) !== false) {
                $row++;
                /* Skip header */
                if ($row === 1 && !is_numeric($data[0] ?? '')) continue;
                if (count($data) < 10) { $total_bad++; continue; }

                $qtext = trim($data[4] ?? '');
                if ($qtext === '') { $total_bad++; continue; }

                /* Skip if question already exists in this subject */
                $dup->execute([$info['id'], $qtext]);
                if ($dup->fetch()) { $total_dup++; continue; }

                try {
                    $ins->execute([
                        $info['id'],                 // FORCE correct subject_id
                        $info['class_level'],        // FORCE correct class_level
                        trim($data[2] ?? 'NAT'),
                        trim($data[3] ?? ''),
                        $qtext,
                        trim($data[5] ?? ''),
                        trim($data[6] ?? ''),
                        trim($data[7] ?? ''),
                        trim($data[8] ?? ''),
                        (int)($data[9] ?? 0),
                    ]);
                    $total_inserted++;
                } catch (Throwable $e) {
                    $total_bad++;
                }
            }
            fclose($fh);
            $files_done++;
        }

        $result = "✅ Imported <b>$total_inserted</b> question(s) into <b>{$info['name']} — {$info['class_level']}</b> from <b>$files_done</b> file(s).<br>"
                . "↪ Skipped: <b>$total_dup</b> duplicates, <b>$total_bad</b> bad rows.";
        $result_type = 'success';
    }
}

/* ---------- Fetch G8 subjects for the buttons ---------- */
$g8 = $pdo->query("SELECT s.id, s.name, s.class_level,
    (SELECT COUNT(*) FROM questions q WHERE q.subject_id = s.id) AS qcount
    FROM subjects s WHERE s.class_level = 'NAT Grade 8' ORDER BY s.name")->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
  .up-banner {
    background: linear-gradient(135deg, #0C1E7F, #1e40af);
    color: #fff; border-radius: 18px; padding: 22px; margin-bottom: 20px;
    box-shadow: 0 10px 28px rgba(12,30,127,.25);
    position: relative; overflow: hidden;
  }
  .up-banner::after {
    content:""; position:absolute; right:-50px; top:-50px;
    width:160px; height:160px; background:rgba(255,255,255,.1); border-radius:50%;
  }
  .up-banner h3 { margin:0; font-weight:800; position:relative; z-index:2; }
  .up-banner p  { margin:4px 0 0; opacity:.9; position:relative; z-index:2; font-size:.9rem; }

  .target-card {
    background: #fff; border-radius: 16px; padding: 20px;
    box-shadow: 0 6px 20px rgba(0,0,0,.06); border: none;
    margin-bottom: 16px; border-left: 5px solid #16a34a;
  }
  .target-card h5 { font-weight: 800; margin: 0 0 6px; }
  .target-card .qcount { font-size: .85rem; color: #6b7280; }
  .target-card form { margin-top: 14px; }
  .target-card input[type=file] {
    border: 2px dashed #cbd5e1; border-radius: 10px; padding: 12px;
    width: 100%; background: #f8fafc;
  }
  .target-card button {
    background: linear-gradient(135deg, #0C1E7F, #1e40af); color: #fff;
    border: none; border-radius: 10px; padding: 12px 22px;
    font-weight: 700; margin-top: 10px; width: 100%;
  }
  .target-card button:hover { background: #1e40af; }
  .target-card.done { border-left-color: #3A7728; }
  .target-card.done h5::after { content: " ✅"; }
</style>

<div class="up-banner">
  <h3>⚡ G8 Bulk Uploader</h3>
  <p>Upload all batch CSVs for each Grade 8 subject at once — nothing gets misfiled.</p>
</div>

<?php if ($result): ?>
  <div class="alert alert-<?= $result_type ?>"><?= $result ?></div>
<?php endif; ?>

<div class="alert alert-info small">
  <b>How to use:</b> For each subject below, click <b>Choose Files</b>, select ALL the batch CSVs for that subject at once
  (hold <b>Ctrl</b> or <b>Shift</b> while clicking to select multiple), then click <b>Upload</b>.
  The questions will go into the correct G8 subject automatically.
</div>

<?php foreach ($g8 as $g): ?>
  <div class="target-card <?= (int)$g['qcount'] > 0 ? 'done' : '' ?>">
    <h5><?= e($g['name']) ?> — <?= e($g['class_level']) ?></h5>
    <div class="qcount">
      Currently has <b><?= (int)$g['qcount'] ?></b> question(s).
      <?php if ((int)$g['qcount'] > 0): ?>
        Upload will skip duplicates.
      <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="target" value="<?= e($g['name']) ?>_G8">
      <input type="file" name="csvs[]" accept=".csv" multiple required>
      <button>⬆️ Upload to <?= e($g['name']) ?> G8</button>
    </form>
  </div>
<?php endforeach; ?>

<div class="text-center mt-4">
  <a href="subjects.php" class="btn btn-outline-secondary">← Back to Subjects</a>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>